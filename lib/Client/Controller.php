<?php

namespace WHMCS\Module\Addon\ClientHealthScore\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ClientHealthScore\ScoreBandResolver;
use WHMCS\Module\Addon\ClientHealthScore\TierResolver;
use WHMCS\Module\Addon\ClientHealthScore\WeightResolver;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class Controller
{
    /**
     * Calculate health score for a single client.
     *
     * @param int $clientId
     * @return int Calculated score
     */
    public function calculateForClient(int $clientId): int
    {
        if ($clientId <= 0) {
            return 100;
        }
        $batchResult = $this->calculateBatch([$clientId]);
        return $batchResult[$clientId] ?? 100;
    }

    /**
     * Calculate health scores for a batch of clients.
     *
     * @param array $clientIds
     * @return array Array of scores indexed by client ID
     */
    private $profileRulesCache = [];

    /**
     * Get setting value by key with default fallback.
     */
    private function getSetting(string $key, $default = null)
    {
        $val = Capsule::table('mod_chs_settings')
            ->where('key', $key)
            ->value('value');
        return $val !== null ? $val : $default;
    }

    /**
     * Classify metric key into Payment or Engagement category.
     */
    private function isPaymentMetric(string $key): bool
    {
        $keyLower = strtolower($key);
        return strpos($keyLower, 'invoice') !== false 
            || strpos($keyLower, 'payment') !== false 
            || strpos($keyLower, 'revenue') !== false 
            || strpos($keyLower, 'billing') !== false;
    }

    /**
     * Resolve profile ID for a client based on priority: Client -> Client Group -> Product -> Default.
     */
    public function resolveProfileIdForClient(int $clientId): int
    {
        // 1. Client specific profile assignment
        $profileId = Capsule::table('mod_chs_profile_assignments')
            ->where('client_id', $clientId)
            ->value('profile_id');
        if ($profileId) {
            return (int)$profileId;
        }

        // 2. Client Group specific profile assignment
        $groupId = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->value('groupid');
        if ($groupId) {
            $profileId = Capsule::table('mod_chs_profile_assignments')
                ->where('group_id', $groupId)
                ->value('profile_id');
            if ($profileId) {
                return (int)$profileId;
            }
        }

        // 3. Product specific profile assignment (based on client's active products)
        $productIds = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->pluck('packageid')
            ->toArray();
        if (!empty($productIds)) {
            $profileId = Capsule::table('mod_chs_profile_assignments')
                ->whereIn('product_id', $productIds)
                ->value('profile_id');
            if ($profileId) {
                return (int)$profileId;
            }
        }

        // 4. Fallback to default profile
        $defaultProfileId = Capsule::table('mod_chs_profiles')
            ->where('is_default', 1)
            ->value('id');

        return $defaultProfileId ? (int)$defaultProfileId : 1;
    }

    /**
     * Fetch rules for a specific profile (cached).
     */
    private function getRulesForProfile(int $profileId): array
    {
        if (!isset($this->profileRulesCache[$profileId])) {
            $this->profileRulesCache[$profileId] = Capsule::table('mod_chs_profile_rules')
                ->where('profile_id', $profileId)
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->toArray();
        }
        return $this->profileRulesCache[$profileId];
    }

    /**
     * Get profile specific settings.
     */
    private function getProfileSettings(int $profileId): array
    {
        $profile = Capsule::table('mod_chs_profiles')->where('id', $profileId)->first();
        $settings = [];
        if ($profile && !empty($profile->settings)) {
            $settings = json_decode($profile->settings, true) ?: [];
        }

        // Merge general settings overrides for the default profile
        if ($profileId === 1 || ($profile && $profile->is_default)) {
            $generalSettings = [
                'payment_weight' => 'payment_weight',
                'engagement_weight' => 'engagement_weight',
                'trend_lookback_days' => 'trend_lookback_days',
            ];
            foreach ($generalSettings as $field => $dbKey) {
                $dbVal = Capsule::table('mod_chs_settings')->where('key', $dbKey)->value('value');
                if ($dbVal !== null) {
                    if ($field === 'trend_lookback_days') {
                        $settings[$field] = (int)$dbVal;
                    } else {
                        $settings[$field] = (float)$dbVal;
                    }
                }
            }
        }
        return $settings;
    }

    /**
     * Calculate health scores for a batch of clients.
     *
     * @param array $clientIds
     * @return array Array of scores indexed by client ID
     */
    public function calculateBatch(array $clientIds): array
    {
        $clientIds = array_filter(array_map('intval', $clientIds), function ($id) {
            return $id > 0;
        });
        if (empty($clientIds)) {
            return [];
        }

        $results = [];

        foreach ($clientIds as $clientId) {
            try {
                $profileId = $this->resolveProfileIdForClient($clientId);
                $profileSettings = $this->getProfileSettings($profileId);

                // 1. Fetch rules for the profile
                $rulesFromDb = $this->getRulesForProfile($profileId);
                $dbWeights = [];
                $enabledSignals = [];
                foreach ($rulesFromDb as $r) {
                    $dbWeights[$r['metric_key']] = (float)$r['weight'];
                    if ($r['is_enabled']) {
                        $enabledSignals[$r['metric_key']] = true;
                    }
                }

                // 2. Collect raw signals
                $collector = new \WHMCS\Module\Addon\ClientHealthScore\ClientSignalCollector();
                $collected = $collector->collect($clientId, $enabledSignals);
                $metadata = $collected['metadata'];

                // 3. Determine if New Account Dampening is applicable
                $isNewAccount = false;
                $dampeningEnabled = (bool)($profileSettings['dampening_enabled'] ?? true);
                if ($dampeningEnabled) {
                    $signupDate = $metadata['datecreated'] ?? '';
                    if ($signupDate) {
                        $signupTs = strtotime($signupDate);
                        if ($signupTs > 0) {
                            $ageDays = (time() - $signupTs) / 86400;
                            $threshold = (int)($profileSettings['dampening_threshold'] ?? 60);
                            if ($ageDays < $threshold) {
                                $isNewAccount = true;
                            }
                        }
                    }
                }
                $dampMultiplier = (float)($profileSettings['dampening_multiplier'] ?? 1.5);

                // 4. Run Calculation via HealthScoreCalculator
                $calcResult = \WHMCS\Module\Addon\ClientHealthScore\HealthScoreCalculator::calculate(
                    $clientId,
                    $collected,
                    $dbWeights,
                    $profileSettings,
                    $isNewAccount,
                    $dampMultiplier
                );

                $finalScore = $calcResult['display_score'];
                $prevScoreVal = (int)Capsule::table('mod_chs_scores')
                    ->where('client_id', $clientId)
                    ->value('score');

                if ($prevScoreVal === null) {
                    $prevScoreVal = $finalScore;
                }

                // Setup breakdown structure to match what is saved in mod_chs_scores
                $breakdown = [];
                // Flatten pay & eng breakdowns into a single array for dashboard display
                foreach ($calcResult['breakdown']['payment'] as $b) {
                    $breakdown[$b['signal']] = [
                        'name'        => str_replace('_', ' ', $b['signal']),
                        'raw_value'   => $b['raw_value'],
                        'score'       => $b['score'],
                        'band_label'  => $b['band_label'],
                        'weight'      => $b['weight'],
                        'points'      => round($b['score'] - 100.0, 2),
                        'explanation' => str_replace(' (Weight: ' . round($b['weight'], 1) . '%)', '', $b['band_label']) . ' (Weight: ' . round($b['weight'], 1) . '%)',
                    ];
                }
                foreach ($calcResult['breakdown']['engagement'] as $b) {
                    $breakdown[$b['signal']] = [
                        'name'        => str_replace('_', ' ', $b['signal']),
                        'raw_value'   => $b['raw_value'],
                        'score'       => $b['score'],
                        'band_label'  => $b['band_label'],
                        'weight'      => $b['weight'],
                        'points'      => round($b['score'] - 100.0, 2),
                        'explanation' => str_replace(' (Weight: ' . round($b['weight'], 1) . '%)', '', $b['band_label']) . ' (Weight: ' . round($b['weight'], 1) . '%)' . ($isNewAccount ? " [Dampened]" : ""),
                    ];
                }

                // Add refund flag breakdown if present
                if (!empty($collected['payment']['refund_or_chargeback']['value'])) {
                    $breakdown['refund_or_chargeback'] = [
                        'name'        => 'refund or chargeback flag',
                        'raw_value'   => 1,
                        'score'       => -30.0,
                        'band_label'  => 'Refunded',
                        'weight'      => 0.0,
                        'points'      => -30.0,
                        'explanation' => 'Refund/chargeback deduction applied (-30 points)',
                    ];
                }

                $breakdown['risk_drivers'] = $calcResult['risk_drivers_sorted'];

                // 5. Calculate Trend
                $lookbackDays = (int)($profileSettings['trend_lookback_days'] ?? $this->getSetting('trend_lookback_days', 14));
                $targetDate = date('Y-m-d', strtotime("-{$lookbackDays} days"));

                $prevScoreRecord = Capsule::table('mod_chs_snapshots')
                    ->where('client_id', $clientId)
                    ->where('date', '<=', $targetDate)
                    ->orderBy('date', 'desc')
                    ->first();

                if ($prevScoreRecord) {
                    $trendScore = (int)$prevScoreRecord->score;
                } else {
                    $trendScore = $prevScoreVal;
                }

                $delta = $finalScore - $trendScore;
                if ($delta >= 5) {
                    $trend = 'up';
                } elseif ($delta <= -5) {
                    $trend = 'down';
                } else {
                    $trend = 'stable';
                }

                // 6. Save results to DB
                $this->saveScore(
                    $clientId,
                    $finalScore,
                    (int)round($calcResult['payment_score']),
                    (int)round($calcResult['engagement_score']),
                    $trend,
                    $prevScoreVal,
                    $breakdown
                );

                // 7. Process alerts via AlertService
                $alertService = new \WHMCS\Module\Addon\ClientHealthScore\AlertService();
                $alertService->checkAndSendAlerts($clientId, $calcResult, $prevScoreVal);

                $results[$clientId] = $finalScore;
            } catch (\Exception $clientEx) {
                logActivity("Client Health Score Recalculation failed for Client ID {$clientId}: " . $clientEx->getMessage());
                Capsule::table('mod_chs_audit_logs')->insert([
                    'client_id'    => $clientId,
                    'action'       => 'recalculation_failure',
                    'level'        => 'error',
                    'description'  => "Recalculation error: " . $clientEx->getMessage(),
                    'performed_by' => 'system',
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $results;
    }

    /**
     * Calculate scores for all active/inactive clients in chunks (suitable for cron job/background task).
     *
     * @param int $chunkSize
     * @return int Total processed clients
     */
    public function calculateAll(int $chunkSize = 200): int
    {
        $processedCount = 0;
        $offset = 0;

        while (true) {
            $clientIds = $this->getClientIdsPaginated($offset, $chunkSize);
            if (empty($clientIds)) {
                break;
            }

            $this->calculateBatch($clientIds);
            $processedCount += count($clientIds);
            $offset += $chunkSize;
        }

        $this->sendWeeklyDigestIfDue();

        return $processedCount;
    }

    /**
     * Get paginated active client IDs (excluding closed).
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getClientIdsPaginated(int $offset, int $limit): array
    {
        return Capsule::table('tblclients')
            ->where('status', '!=', 'Closed')
            ->orderBy('id', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->select('id')
            ->get()
            ->map(function ($item) {
                return (int)$item->id;
            })
            ->toArray();
    }

    /**
     * Get the health score record for a single client.
     *
     * @param int $clientId
     * @return array|null
     */
    public function getScoreForClient(int $clientId): ?array
    {
        $record = Capsule::table('mod_chs_scores')
            ->where('client_id', $clientId)
            ->first();

        return $record ? (array)$record : null;
    }

    /**
     * Save/Update client health score and record history.
     */
    public function saveScore(int $clientId, int $score, int $paymentScore, int $engagementScore, string $trend, int $prevScore, array $breakdown)
    {
        $exists = Capsule::table('mod_chs_scores')
            ->where('client_id', $clientId)
            ->exists();

        if ($exists) {
            Capsule::table('mod_chs_scores')
                ->where('client_id', $clientId)
                ->update([
                    'score'            => $score,
                    'payment_score'    => $paymentScore,
                    'engagement_score' => $engagementScore,
                    'trend'            => $trend,
                    'prev_score'       => $prevScore,
                    'breakdown'        => json_encode($breakdown),
                    'updated_at'       => date('Y-m-d H:i:s'),
                ]);
        } else {
            Capsule::table('mod_chs_scores')
                ->insert([
                    'client_id'        => $clientId,
                    'score'            => $score,
                    'payment_score'    => $paymentScore,
                    'engagement_score' => $engagementScore,
                    'trend'            => $trend,
                    'prev_score'       => $prevScore,
                    'breakdown'        => json_encode($breakdown),
                    'updated_at'       => date('Y-m-d H:i:s'),
                ]);
        }

        // Save history snapshot (limit to one per client per day)
        $today = date('Y-m-d');
        $historyExists = Capsule::table('mod_chs_snapshots')
            ->where('client_id', $clientId)
            ->where('date', $today)
            ->exists();

        if ($historyExists) {
            Capsule::table('mod_chs_snapshots')
                ->where('client_id', $clientId)
                ->where('date', $today)
                ->update(['score' => $score]);
        } else {
            Capsule::table('mod_chs_snapshots')
                ->insert([
                    'client_id' => $clientId,
                    'score'     => $score,
                    'date'      => $today,
                ]);
        }
    }

    /**
     * Get client list with scores, filtered and paginated.
     *
     * @param int $page
     * @param int $limit
     * @param string $search
     * @param string $statusFilter
     * @return array
     */
    public function getScores(int $page, int $limit, string $search = '', string $statusFilter = '', string $sort = '', string $dir = '', int $groupId = 0, int $profileId = 0): array
    {
        $offset = ($page - 1) * $limit;

        $query = Capsule::table('tblclients')
            ->leftJoin('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id as client_id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'tblclients.email',
                'tblclients.status as client_status',
                'mod_chs_scores.score',
                'mod_chs_scores.trend',
                'mod_chs_scores.breakdown',
                'mod_chs_scores.updated_at',
            ]);

        $query->where('tblclients.status', '!=', 'Closed');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('tblclients.firstname', 'like', "%{$search}%")
                    ->orWhere('tblclients.lastname', 'like', "%{$search}%")
                    ->orWhere('tblclients.companyname', 'like', "%{$search}%")
                    ->orWhere('tblclients.email', 'like', "%{$search}%")
                    ->orWhere('tblclients.id', '=', $search);
            });
        }

        if ($groupId > 0) {
            $query->where('tblclients.groupid', $groupId);
        }

        if ($profileId > 0) {
            $query->where(function ($q) use ($profileId) {
                // 1. Client-specific assignment matches profileId
                $q->whereIn('tblclients.id', function ($sub) use ($profileId) {
                    $sub->select('client_id')->from('mod_chs_profile_assignments')
                        ->whereNotNull('client_id')
                        ->where('profile_id', $profileId);
                })
                // 2. Or Client Group assignment matches profileId (and client has no client-specific assignment)
                ->orWhere(function ($q2) use ($profileId) {
                    $q2->whereIn('tblclients.groupid', function ($sub) use ($profileId) {
                        $sub->select('group_id')->from('mod_chs_profile_assignments')
                            ->whereNotNull('group_id')
                            ->where('profile_id', $profileId);
                    })
                    ->whereNotIn('tblclients.id', function ($sub) {
                        $sub->select('client_id')->from('mod_chs_profile_assignments')->whereNotNull('client_id');
                    });
                })
                // 3. Or Product-specific assignment matches profileId (and client has no client-specific or group assignment)
                ->orWhere(function ($q3) use ($profileId) {
                    $q3->whereIn('tblclients.id', function ($sub) use ($profileId) {
                        $sub->select('userid')->from('tblhosting')
                            ->where('domainstatus', 'Active')
                            ->whereIn('packageid', function ($sub2) use ($profileId) {
                                $sub2->select('product_id')->from('mod_chs_profile_assignments')
                                    ->whereNotNull('product_id')
                                    ->where('profile_id', $profileId);
                            });
                    })
                    ->whereNotIn('tblclients.id', function ($sub) {
                        $sub->select('client_id')->from('mod_chs_profile_assignments')->whereNotNull('client_id');
                    })
                    ->whereNotIn('tblclients.groupid', function ($sub) {
                        $sub->select('group_id')->from('mod_chs_profile_assignments')->whereNotNull('group_id');
                    });
                });

                // 4. Or default profile matches profileId (and client has none of the above)
                $defaultProfileId = Capsule::table('mod_chs_profiles')->where('is_default', 1)->value('id') ?: 1;
                if ($profileId == $defaultProfileId) {
                    $q->orWhere(function ($q4) {
                        $q4->whereNotIn('tblclients.id', function ($sub) {
                            $sub->select('client_id')->from('mod_chs_profile_assignments')->whereNotNull('client_id');
                        })
                        ->whereNotIn('tblclients.groupid', function ($sub) {
                            $sub->select('group_id')->from('mod_chs_profile_assignments')->whereNotNull('group_id');
                        })
                        ->whereNotIn('tblclients.id', function ($sub) {
                            $sub->select('userid')->from('tblhosting')
                                ->where('domainstatus', 'Active')
                                ->whereIn('packageid', function ($sub2) {
                                    $sub2->select('product_id')->from('mod_chs_profile_assignments')->whereNotNull('product_id');
                                });
                        });
                    });
                }
            });
        }

        if (!empty($statusFilter)) {
            switch ($statusFilter) {
                case 'healthy':
                    $query->where('mod_chs_scores.score', '>=', 80);
                    break;
                case 'warning':
                    $query->where('mod_chs_scores.score', '>=', 50)
                          ->where('mod_chs_scores.score', '<', 80);
                    break;
                case 'critical':
                    $query->where('mod_chs_scores.score', '<', 50);
                    break;
                case 'unevaluated':
                    $query->whereNull('mod_chs_scores.score');
                    break;
            }
        }

        $totalCount = $query->count();

        $mrrSql = "((SELECT COALESCE(SUM(
                      CASE WHEN billingcycle = 'Monthly' THEN amount
                           WHEN billingcycle = 'Quarterly' THEN amount / 3
                           WHEN billingcycle = 'Semi-Annually' THEN amount / 6
                           WHEN billingcycle = 'Annually' THEN amount / 12
                           WHEN billingcycle = 'Biennially' THEN amount / 24
                           WHEN billingcycle = 'Triennially' THEN amount / 36
                           ELSE 0 END
                    ), 0) FROM tblhosting WHERE userid = tblclients.id AND domainstatus = 'Active') +
                   (SELECT COALESCE(SUM(recurringamount / registrationperiod / 12), 0) FROM tbldomains WHERE userid = tblclients.id AND status = 'Active'))";

        $severitySql = "CASE 
                            WHEN mod_chs_scores.score >= 80 THEN 0
                            WHEN mod_chs_scores.score >= 60 THEN 1
                            WHEN mod_chs_scores.score >= 35 THEN 2
                            WHEN mod_chs_scores.score IS NULL THEN 0
                            ELSE 3 
                        END";

        $sortDir = (strtolower($dir) === 'asc') ? 'asc' : 'desc';
        if (!empty($sort)) {
            switch ($sort) {
                case 'client_id':
                    $query->orderBy('tblclients.id', $sortDir);
                    break;
                case 'name':
                    $query->orderBy('tblclients.firstname', $sortDir)->orderBy('tblclients.lastname', $sortDir);
                    break;
                case 'score':
                    $query->orderByRaw('CASE WHEN mod_chs_scores.score IS NULL THEN 1 ELSE 0 END ASC')
                          ->orderBy('mod_chs_scores.score', $sortDir);
                    break;
                case 'mrr':
                    $query->orderByRaw("{$mrrSql} {$sortDir}");
                    break;
                default:
                    $query->orderByRaw("({$severitySql} * {$mrrSql}) {$sortDir}");
                    break;
            }
        } else {
            // Default sort: highest priority score (Tier Severity * MRR) first
            $query->orderByRaw("({$severitySql} * {$mrrSql}) desc");
        }

        $records = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        $tiers = Capsule::table('mod_chs_tiers')
            ->orderBy('min_score', 'desc')
            ->get()
            ->toArray();

        $items = array_map(function ($item) use ($tiers) {
            $arr = (array)$item;
            if ($arr['breakdown']) {
                $arr['breakdown'] = json_decode($arr['breakdown'], true);
            }
            
            // Resolve tier name and color
            $arr['tier_name'] = 'Unevaluated';
            $arr['tier_color'] = '#6b7280';
            if ($arr['score'] !== null) {
                foreach ($tiers as $t) {
                    if ($arr['score'] >= $t->min_score && $arr['score'] <= $t->max_score) {
                        $arr['tier_name'] = $t->name;
                        $arr['tier_color'] = $t->badge_color;
                        break;
                    }
                }
            }
            return $arr;
        }, $records);

        return [
            'items' => $items,
            'total' => $totalCount,
        ];
    }

    /**
     * Get aggregate statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        $totalClients = Capsule::table('tblclients')
            ->where('status', '!=', 'Closed')
            ->count();

        $stats = Capsule::table('mod_chs_scores')
            ->selectRaw('
                COUNT(CASE WHEN score >= 80 THEN 1 END) as healthy,
                COUNT(CASE WHEN score >= 60 AND score < 80 THEN 1 END) as warning,
                COUNT(CASE WHEN score < 60 THEN 1 END) as critical,
                AVG(score) as average
            ')
            ->first();

        $healthy = (int)($stats->healthy ?? 0);
        $warning = (int)($stats->warning ?? 0);
        $critical = (int)($stats->critical ?? 0);
        $averageScore = $stats->average !== null ? round((float)$stats->average, 1) : null;

        $unevaluated = $totalClients - ($healthy + $warning + $critical);
        $unevaluated = max(0, $unevaluated);

        return [
            'total_clients' => $totalClients,
            'healthy'       => $healthy,
            'warning'       => $warning,
            'critical'      => $critical,
            'unevaluated'   => $unevaluated,
            'average_score' => $averageScore,
        ];
    }

    /**
     * Get history chart data for a specific client.
     *
     * @param int $clientId
     * @param int $days
     * @return array
     */
    public function getHistoryForClient(int $clientId, int $days = 30): array
    {
        $history = Capsule::table('mod_chs_snapshots')
            ->where('client_id', $clientId)
            ->where('date', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->orderBy('date', 'asc')
            ->get()
            ->toArray();

        return array_map(function ($item) {
            return (array)$item;
        }, $history);
    }

    /**
     * Get top healthy or top critical clients.
     *
     * @param int $limit
     * @param string $order 'desc' for healthy, 'asc' for critical
     * @return array
     */
    public function getTopClients(int $limit, string $order = 'desc'): array
    {
        $records = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id as client_id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'mod_chs_scores.score',
                'mod_chs_scores.trend',
            ])
            ->where('tblclients.status', '!=', 'Closed')
            ->orderBy('mod_chs_scores.score', $order)
            ->limit($limit)
            ->get()
            ->toArray();

        return array_map(function ($item) {
            return (array)$item;
        }, $records);
    }

    /**
     * Fetch aggregated client metrics for a batch of client IDs.
     *
     * @param array $clientIds
     * @param int $lookbackDays
     * @return array Associative array of client metrics indexed by client ID
     */
    private function getClientMetricsBatch(array $clientIds, int $lookbackDays): array
    {
        if (empty($clientIds)) {
            return [];
        }

        // 1. Fetch signup datecreated & lastlogin from tblclients
        $clients = Capsule::table('tblclients')
            ->whereIn('id', $clientIds)
            ->select('id', 'datecreated', 'lastlogin')
            ->get()
            ->keyBy('id')
            ->toArray();

        // 2. Fetch Average Days Late (90 days)
        $avgDaysLate = [];
        $date90 = date('Y-m-d', strtotime('-90 days'));
        $paidInvoices = Capsule::table('tblinvoices')
            ->whereIn('userid', $clientIds)
            ->where('status', 'Paid')
            ->where('datepaid', '>=', $date90)
            ->select('userid', 'duedate', 'datepaid')
            ->get();

        $invoiceDelays = [];
        foreach ($paidInvoices as $inv) {
            $uid = $inv->userid;
            $due = strtotime($inv->duedate);
            $paid = strtotime($inv->datepaid);
            $delay = 0.0;
            if ($paid > $due) {
                $delay = (float)(($paid - $due) / 86400.0);
            }
            $invoiceDelays[$uid][] = $delay;
        }

        // 3. Fetch Failed Gateway Payments (90 days)
        $failedPayments = [];
        $date90Time = date('Y-m-d H:i:s', strtotime('-90 days'));
        $clientInvoices = Capsule::table('tblinvoices')
            ->whereIn('userid', $clientIds)
            ->select('id', 'userid')
            ->get()
            ->groupBy('userid');

        // 4. Fetch Overdue Invoice Count (current)
        $overdueInvoices = Capsule::table('tblinvoices')
            ->whereIn('userid', $clientIds)
            ->where('status', 'Unpaid')
            ->where('duedate', '<', date('Y-m-d'))
            ->selectRaw('userid, COUNT(*) as count')
            ->groupBy('userid')
            ->pluck('count', 'userid')
            ->toArray();

        // 5. Fetch Refund/Chargeback Flag (12 months)
        $date12Months = date('Y-m-d', strtotime('-365 days'));
        $refundedInvoices = Capsule::table('tblinvoices')
            ->whereIn('userid', $clientIds)
            ->where('status', 'Refunded')
            ->where('datepaid', '>=', $date12Months)
            ->selectRaw('userid, COUNT(*) as count')
            ->groupBy('userid')
            ->pluck('count', 'userid')
            ->toArray();

        // 6. Fetch Login Frequency (90 days)
        $logins = Capsule::table('tblactivitylog')
            ->whereIn('userid', $clientIds)
            ->where('date', '>=', $date90Time)
            ->where(function($q) {
                $q->where('description', 'like', '%Login%')
                  ->orWhere('description', 'like', '%Logged In%')
                  ->orWhere('description', 'like', '%authenticated%');
            })
            ->selectRaw('userid, COUNT(*) as count')
            ->groupBy('userid')
            ->pluck('count', 'userid')
            ->toArray();

        // 7. Fetch Downgrades / Partial Cancellations (12 months)
        $date12MonthsTime = date('Y-m-d H:i:s', strtotime('-365 days'));
        $downgrades = Capsule::table('tblcancelrequests')
            ->join('tblhosting', 'tblcancelrequests.relid', '=', 'tblhosting.id')
            ->whereIn('tblhosting.userid', $clientIds)
            ->where('tblcancelrequests.date', '>=', $date12MonthsTime)
            ->selectRaw('tblhosting.userid, COUNT(*) as count')
            ->groupBy('tblhosting.userid')
            ->pluck('count', 'userid')
            ->toArray();

        $metricsBatch = [];
        foreach ($clientIds as $clientId) {
            $clientObj = $clients[$clientId] ?? null;
            $dateCreated = $clientObj ? $clientObj->datecreated : '';
            $lastLogin = $clientObj ? $clientObj->lastlogin : '';

            // Compute Average Days Late
            $avgLate = 0.0;
            if (!empty($invoiceDelays[$clientId])) {
                $avgLate = (float)(array_sum($invoiceDelays[$clientId]) / count($invoiceDelays[$clientId]));
            }

            // Compute Failed Payment attempts
            $failedCount = 0;
            $invs = $clientInvoices->get($clientId);
            if ($invs && $invs->count() > 0) {
                $invIds = $invs->pluck('id')->toArray();
                foreach ($invIds as $invId) {
                    $failedCount += Capsule::table('tblgatewaylog')
                        ->where('date', '>=', $date90Time)
                        ->where('data', 'like', "%Invoice ID => {$invId}%")
                        ->whereIn('result', ['Error', 'Declined', 'Failed'])
                        ->count();
                }
            }

            $metricsBatch[$clientId] = [
                'client_id'                  => $clientId,
                'datecreated'                => $dateCreated,
                'lastlogin'                  => $lastLogin,
                'avg_days_late'              => $avgLate,
                'failed_payment_attempts'    => $failedCount,
                'overdue_invoice_count'      => (int)($overdueInvoices[$clientId] ?? 0),
                'refund_or_chargeback_count' => (int)($refundedInvoices[$clientId] ?? 0),
                'login_count_90_days'        => (int)($logins[$clientId] ?? 0),
                'downgrade_count_12_months'  => (int)($downgrades[$clientId] ?? 0),
                'usage_trend'                => null, // Treats telemetry as missing data dynamically
            ];
        }

        return $metricsBatch;
    }

    /**
     * Calculate a specific metric score contribution and explanation.
     *
     * @param string $key
     * @param array $metrics
     * @param array $rule
     * @param array $profileSettings
     * @return array Contains 'score' (float), 'explanation' (string), and 'applicable' (bool)
     */
    private function calculateMetric(string $key, array $metrics, array $rule, array $profileSettings): array
    {
        $score = 100.0;
        $explanation = '';
        $applicable = true;

        switch ($key) {
            case 'client_tenure':
                if (empty($metrics['datecreated'])) {
                    $explanation = "No registration date recorded.";
                    $applicable = false;
                    $score = 0.0;
                    break;
                }
                try {
                    $signUpDate = new \DateTime($metrics['datecreated']);
                    $now = new \DateTime();
                    $interval = $signUpDate->diff($now);
                    $years = $interval->y + ($interval->m / 12.0) + ($interval->d / 365.25);
                    
                    $ruleConfig = json_decode($rule['config'] ?? '{}', true);
                    $maxYears = (float)($ruleConfig['max_years'] ?? $profileSettings['max_years'] ?? 5.0);
                    if ($maxYears <= 0.0) {
                        $maxYears = 5.0;
                    }
                    
                    $score = min(100.0, max(0.0, ($years / $maxYears) * 100.0));
                    
                    $yearsInt = $interval->y;
                    $monthsInt = $interval->m;
                    $yearsFormatted = $yearsInt > 0 ? "{$yearsInt} year" . ($yearsInt > 1 ? "s" : "") : "";
                    $monthsFormatted = $monthsInt > 0 ? "{$monthsInt} month" . ($monthsInt > 1 ? "s" : "") : "";
                    $tenureStr = implode(" and ", array_filter([$yearsFormatted, $monthsFormatted])) ?: "less than a month";
                    
                    $explanation = "Client tenure of {$tenureStr} (Target: {$maxYears} years) scores " . round($score, 1) . "/100.";
                } catch (\Exception $e) {
                    $explanation = "Unable to calculate tenure details.";
                    $applicable = false;
                    $score = 0.0;
                }
                break;

            case 'unpaid_invoices':
                $unpaidCount = (int)($metrics['unpaid_invoices_count'] ?? 0);
                $ruleConfig = json_decode($rule['config'] ?? '{}', true);
                $deduction = (float)($ruleConfig['deduction_per_invoice'] ?? $profileSettings['deduction_per_unpaid'] ?? 20.0);
                $score = max(0.0, 100.0 - ($unpaidCount * $deduction));
                
                if ($unpaidCount === 0) {
                    $explanation = "No unpaid invoices (good standing) scores 100/100.";
                } else {
                    $explanation = "{$unpaidCount} unpaid invoice" . ($unpaidCount > 1 ? "s" : "") . " deducts " . ($unpaidCount * $deduction) . " points, scoring " . round($score, 1) . "/100.";
                }
                break;

            case 'overdue_invoices':
                $overdueCount = (int)($metrics['overdue_invoices_count'] ?? 0);
                $ruleConfig = json_decode($rule['config'] ?? '{}', true);
                $deduction = (float)($ruleConfig['deduction_per_invoice'] ?? $profileSettings['deduction_per_overdue'] ?? 33.3);
                $score = max(0.0, 100.0 - ($overdueCount * $deduction));
                
                if ($overdueCount === 0) {
                    $explanation = "No overdue invoices (good standing) scores 100/100.";
                } else {
                    $explanation = "{$overdueCount} overdue invoice" . ($overdueCount > 1 ? "s" : "") . " deducts " . ($overdueCount * $deduction) . " points, scoring " . round($score, 1) . "/100.";
                }
                break;

            case 'active_services':
                $activeCount = (int)($metrics['active_services_count'] ?? 0);
                $ruleConfig = json_decode($rule['config'] ?? '{}', true);
                $target = (int)($ruleConfig['target_services'] ?? $profileSettings['target_services'] ?? 3);
                if ($target <= 0) {
                    $target = 3;
                }
                
                $score = min(100.0, max(0.0, ($activeCount / $target) * 100.0));
                
                if ($activeCount === 0) {
                    $explanation = "No active services scores 0/100.";
                } else {
                    $explanation = "{$activeCount} active service" . ($activeCount > 1 ? "s" : "") . " (Target: {$target}) scores " . round($score, 1) . "/100.";
                }
                break;

            case 'cancelled_services':
                $cancelledCount = (int)($metrics['cancelled_services_count'] ?? 0);
                $ruleConfig = json_decode($rule['config'] ?? '{}', true);
                $deduction = (float)($ruleConfig['deduction_per_cancellation'] ?? $profileSettings['deduction_per_cancellation'] ?? 25.0);
                $score = max(0.0, 100.0 - ($cancelledCount * $deduction));
                
                $lookbackDays = (int)($ruleConfig['lookback_days'] ?? 180);
                if ($cancelledCount === 0) {
                    $explanation = "No service cancellations in the last {$lookbackDays} days scores 100/100.";
                } else {
                    $explanation = "{$cancelledCount} service cancellation" . ($cancelledCount > 1 ? "s" : "") . " in the last {$lookbackDays} days scores " . round($score, 1) . "/100.";
                }
                break;

            case 'open_tickets':
                $openCount = (int)($metrics['open_tickets_count'] ?? 0);
                $ruleConfig = json_decode($rule['config'] ?? '{}', true);
                $deduction = (float)($ruleConfig['deduction_per_ticket'] ?? $profileSettings['deduction_per_ticket'] ?? 25.0);
                $score = max(0.0, 100.0 - ($openCount * $deduction));
                
                if ($openCount === 0) {
                    $explanation = "No open support tickets scores 100/100.";
                } else {
                    $explanation = "{$openCount} open support ticket" . ($openCount > 1 ? "s" : "") . " scores " . round($score, 1) . "/100.";
                }
                break;
        }

        return [
            'score'       => $score,
            'explanation' => $explanation,
            'applicable'  => $applicable,
        ];
    }

    /**
     * Process alerts for a client based on score changes and send webhooks.
     */
    public function processAlertsForClient(int $clientId, int $score, int $prevScore)
    {
        $alertService = new \WHMCS\Module\Addon\ClientHealthScore\AlertService();
        $alertService->checkAndSendAlerts($clientId, $score, $prevScore);
    }

    /**
     * Get the tier name based on the client health score.
     */
    public function getTierForScore(int $score, int $profileId): string
    {
        $profileSettings = $this->getProfileSettings($profileId);
        $tiers = $profileSettings['tiers'] ?? null;
        if ($tiers) {
            foreach ($tiers as $tier) {
                if ($score >= $tier['min_score'] && $score <= $tier['max_score']) {
                    return $tier['name'];
                }
            }
        }
        $tierName = Capsule::table('mod_chs_tiers')
            ->where('min_score', '<=', $score)
            ->where('max_score', '>=', $score)
            ->value('name');
        return $tierName ?: 'Standard Client';
    }

    /**
     * Calculate monthly recurring revenue for a client.
     */
    public function getClientMRR(int $clientId): float
    {
        $hostingMrr = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->selectRaw("SUM(
                CASE WHEN billingcycle = 'Monthly' THEN amount
                     WHEN billingcycle = 'Quarterly' THEN amount / 3
                     WHEN billingcycle = 'Semi-Annually' THEN amount / 6
                     WHEN billingcycle = 'Annually' THEN amount / 12
                     WHEN billingcycle = 'Biennially' THEN amount / 24
                     WHEN billingcycle = 'Triennially' THEN amount / 36
                     ELSE 0 END
            ) as mrr")
            ->value('mrr') ?: 0.0;

        $domainMrr = Capsule::table('tbldomains')
            ->where('userid', $clientId)
            ->where('status', 'Active')
            ->selectRaw("SUM(recurringamount / registrationperiod / 12) as mrr")
            ->value('mrr') ?: 0.0;

        return (float)($hostingMrr + $domainMrr);
    }

    /**
     * Trigger alert with cooldown deduplication and dispatch webhooks.
     */
    private function triggerAlert(int $clientId, string $type, string $message, string $severity)
    {
        $cooldownHours = (int)$this->getSetting('alert_cooldown', 24);
        $cutoffTime = date('Y-m-d H:i:s', time() - ($cooldownHours * 3600));

        $exists = Capsule::table('mod_chs_alerts')
            ->where('client_id', $clientId)
            ->where('type', $type)
            ->where('created_at', '>=', $cutoffTime)
            ->exists();

        if ($exists) {
            Capsule::table('mod_chs_audit_logs')->insert([
                'client_id'    => $clientId,
                'action'       => 'alert_deduplicated',
                'level'        => 'info',
                'description'  => "Skipped duplicate alert of type {$type} (cooldown active). Message: {$message}",
                'performed_by' => 'system',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $alertId = Capsule::table('mod_chs_alerts')->insertGetId([
            'client_id'  => $clientId,
            'type'       => $type,
            'message'    => $message,
            'severity'   => $severity,
            'status'     => 'open',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Capsule::table('mod_chs_alert_history')->insert([
            'alert_id'     => $alertId,
            'action'       => 'created',
            'performed_by' => 'system',
            'notes'        => 'Auto-triggered by scoring calculator.',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Get the current score and previous score to pass to webhooks
        $scoreRecord = Capsule::table('mod_chs_scores')->where('client_id', $clientId)->first();
        $score = $scoreRecord ? (int)$scoreRecord->score : 100;
        $prevScore = $scoreRecord ? (int)$scoreRecord->prev_score : 100;

        $this->dispatchWebhooks($clientId, $type, $message, $severity, $score, $prevScore);
    }

    /**
     * Dispatch webhooks to configured integrations.
     */
    private function dispatchWebhooks(int $clientId, string $type, string $message, string $severity, int $score, int $prevScore)
    {
        $clientName = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->selectRaw("CONCAT(firstname, ' ', lastname) as name")
            ->value('name') ?? 'Client #' . $clientId;

        $profileId = $this->resolveProfileIdForClient($clientId);
        $currTier = $this->getTierForScore($score, $profileId);
        $prevTier = $this->getTierForScore($prevScore, $profileId);
        $delta = $score - $prevScore;
        $mrr = $this->getClientMRR($clientId);

        // Fetch risk drivers from score record
        $scoreRecord = Capsule::table('mod_chs_scores')->where('client_id', $clientId)->first();
        $drivers = [];
        if ($scoreRecord && $scoreRecord->breakdown) {
            $breakdown = json_decode($scoreRecord->breakdown, true);
            $drivers = $breakdown['risk_drivers'] ?? [];
        }
        $driverNames = array_map(function($d) { return $d['name'] . ' (' . $d['points'] . ')'; }, $drivers);

        $payloadData = [
            'client_id'     => $clientId,
            'client_name'   => $clientName,
            'previous_score'=> $prevScore,
            'current_score' => $score,
            'previous_tier' => $prevTier,
            'current_tier'  => $currTier,
            'delta'         => $delta,
            'mrr'           => $mrr,
            'risk_drivers'  => $driverNames,
            'timestamp'     => date('c')
        ];

        $payloads = [
            'slack' => [
                'setting' => 'webhook_slack_url',
                'body' => json_encode([
                    'text' => "⚠️ *[Client Health Score Alert]*\n*Client:* {$clientName} (ID: {$clientId})\n*Score:* {$prevScore} ➔ {$score} (Delta: " . ($delta > 0 ? "+{$delta}" : $delta) . ")\n*Tier:* {$prevTier} ➔ {$currTier}\n*MRR:* $" . number_format($mrr, 2) . "\n*Risk Drivers:* " . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "\n*Message:* {$message}"
                ]),
                'headers' => ['Content-Type: application/json']
            ],
            'discord' => [
                'setting' => 'webhook_discord_url',
                'body' => json_encode([
                    'content' => "⚠️ **[Client Health Score Alert]**\n**Client:** {$clientName} (ID: {$clientId})\n**Score:** {$prevScore} ➔ {$score} (Delta: " . ($delta > 0 ? "+{$delta}" : $delta) . ")\n**Tier:** {$prevTier} ➔ {$currTier}\n**MRR:** $" . number_format($mrr, 2) . "\n**Risk Drivers:** " . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "\n**Message:** {$message}"
                ]),
                'headers' => ['Content-Type: application/json']
            ],
            'teams' => [
                'setting' => 'webhook_teams_url',
                'body' => json_encode([
                    'text' => "⚠️ **[Client Health Score Alert]**\n**Client:** {$clientName} (ID: {$clientId})\n**Score:** {$prevScore} ➔ {$score} (Delta: " . ($delta > 0 ? "+{$delta}" : $delta) . ")\n**Tier:** {$prevTier} ➔ {$currTier}\n**MRR:** $" . number_format($mrr, 2) . "\n**Risk Drivers:** " . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "\n**Message:** {$message}"
                ]),
                'headers' => ['Content-Type: application/json']
            ],
            'generic' => [
                'setting' => 'webhook_generic_url',
                'body' => json_encode(array_merge($payloadData, [
                    'event'    => 'health_alert',
                    'message'  => $message,
                    'severity' => $severity
                ])),
                'headers' => ['Content-Type: application/json']
            ]
        ];

        foreach ($payloads as $provider => $data) {
            $url = $this->getSetting($data['setting']);
            if (empty($url)) {
                continue;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data['body']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $data['headers']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $responseBody = curl_exec($ch);
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            $status = ($responseCode >= 200 && $responseCode < 300) ? 'success' : 'failed';
            if ($err) {
                $responseBody = $err;
                $status = 'failed';
                
                Capsule::table('mod_chs_audit_logs')->insert([
                    'client_id'    => $clientId,
                    'action'       => 'webhook_failure',
                    'level'        => 'error',
                    'description'  => "Webhook dispatch failed to {$provider} URL: {$err}",
                    'performed_by' => 'system',
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }

            Capsule::table('mod_chs_webhook_logs')->insert([
                'client_id'     => $clientId,
                'event'         => $type,
                'url'           => $url,
                'payload'       => $data['body'],
                'response_code' => $responseCode,
                'response_body' => $responseBody ?: '',
                'status'        => $status,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Send Weekly Digest email if it is due.
     */
    public function sendWeeklyDigestIfDue()
    {
        $enabled = (int)$this->getSetting('digest_enabled', 1);
        if (!$enabled) {
            return;
        }

        $digestDay = $this->getSetting('digest_day', 'Monday');
        if (date('l') !== $digestDay) {
            return;
        }
        $lastDigest = Capsule::table('mod_chs_weekly_digest_logs')
            ->orderBy('sent_at', 'desc')
            ->value('sent_at');

        $isDue = false;
        if (!$lastDigest) {
            $isDue = true;
        } else {
            $days = (time() - strtotime($lastDigest)) / 86400;
            if ($days >= 6.0) {
                $isDue = true;
            }
        }

        if (!$isDue) {
            return;
        }

        try {
            $alertService = new \WHMCS\Module\Addon\ClientHealthScore\AlertService();
            $alertService->sendWeeklyDigest();
        } catch (\Exception $e) {
            logActivity("Client Health Weekly Digest generation failed: " . $e->getMessage());
        }
    }

    /**
     * Default client area action.
     *
     * @param array $vars
     * @return array
     */
    public function index($vars)
    {
        return [
            'pagetitle' => 'Client Health Score',
            'templatefile' => 'clientarea',
            'vars' => [
                // client area variables
            ],
        ];
    }
}
