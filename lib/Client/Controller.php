<?php

namespace WHMCS\Module\Addon\ClientHealthScore\Client;

use WHMCS\Database\Capsule;

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
                ->where('is_enabled', 1)
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->toArray();
        }
        return $this->profileRulesCache[$profileId];
    }

    /**
     * Calculate health scores for a batch of clients.
     *
     * @param array $clientIds
     * @return array Array of scores indexed by client ID
     */
    public function calculateBatch(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        // Resolve profile IDs and find max lookback days across all resolved profiles
        $clientProfiles = [];
        $maxLookbackDays = 180;
        foreach ($clientIds as $clientId) {
            $profileId = $this->resolveProfileIdForClient($clientId);
            $clientProfiles[$clientId] = $profileId;

            $profileRules = $this->getRulesForProfile($profileId);
            foreach ($profileRules as $rule) {
                if ($rule['metric_key'] === 'cancelled_services') {
                    $config = json_decode($rule['config'] ?? '{}', true);
                    $days = (int)($config['lookback_days'] ?? 180);
                    if ($days > $maxLookbackDays) {
                        $maxLookbackDays = $days;
                    }
                }
            }
        }

        // Fetch metrics batch using maximum lookback days
        $metricsBatch = $this->getClientMetricsBatch($clientIds, $maxLookbackDays);
        $results = [];

        foreach ($clientIds as $clientId) {
            $metrics = $metricsBatch[$clientId] ?? [
                'client_id'                => $clientId,
                'datecreated'              => '',
                'unpaid_invoices_count'    => 0,
                'overdue_invoices_count'   => 0,
                'total_paid_revenue'       => 0.0,
                'active_services_count'    => 0,
                'cancelled_services_count' => 0,
                'open_tickets_count'       => 0,
            ];

            $profileId = $clientProfiles[$clientId];
            $rules = $this->getRulesForProfile($profileId);

            // Determine if New Account Dampening is applicable
            $isNewAccount = false;
            $signupDate = $metrics['datecreated'] ?? '';
            if ($signupDate) {
                $signupTs = strtotime($signupDate);
                if ($signupTs > 0) {
                    $ageDays = (time() - $signupTs) / 86400;
                    $threshold = (int)$this->getSetting('dampening_threshold', 30);
                    if ($ageDays < $threshold) {
                        $isNewAccount = true;
                    }
                }
            }

            // Normalization variables
            $totalWeight = 0.0;
            $applicableWeight = 0.0;
            $totalPaymentWeight = 0.0;
            $applicablePaymentWeight = 0.0;
            $totalEngagementWeight = 0.0;
            $applicableEngagementWeight = 0.0;

            $applicableRulesData = [];

            // Calculate each metric contribution and check applicability
            foreach ($rules as $rule) {
                $weight = abs((float)$rule['weight']);
                $totalWeight += $weight;
                $isPayment = $this->isPaymentMetric($rule['metric_key']);
                if ($isPayment) {
                    $totalPaymentWeight += $weight;
                } else {
                    $totalEngagementWeight += $weight;
                }

                $res = $this->calculateMetric($rule['metric_key'], $metrics, $rule);
                $isApplicable = $res['applicable'] ?? true;

                if ($isApplicable) {
                    $applicableWeight += $weight;
                    if ($isPayment) {
                        $applicablePaymentWeight += $weight;
                    } else {
                        $applicableEngagementWeight += $weight;
                    }

                    $applicableRulesData[] = [
                        'rule' => $rule,
                        'res' => $res,
                        'is_payment' => $isPayment
                    ];
                }
            }

            // Norm factors
            $scaleFactor = ($applicableWeight > 0) ? ($totalWeight / $applicableWeight) : 1.0;
            $scaleFactorPayment = ($applicablePaymentWeight > 0) ? ($totalPaymentWeight / $applicablePaymentWeight) : 1.0;
            $scaleFactorEngagement = ($applicableEngagementWeight > 0) ? ($totalEngagementWeight / $applicableEngagementWeight) : 1.0;

            $scoreAccumulator = 100.0;
            $paymentScoreAccumulator = 100.0;
            $engagementScoreAccumulator = 100.0;

            $breakdown = [];
            $riskDrivers = [];

            // Compute normalized values
            foreach ($applicableRulesData as $item) {
                $rule = $item['rule'];
                $res = $item['res'];
                $isPayment = $item['is_payment'];
                $change = (float)$res['change'];

                // Apply dampening to negative changes
                if ($isNewAccount && $change < 0) {
                    $dampeningMultiplier = (float)$this->getSetting('dampening_multiplier', 0.5);
                    $change = $change * $dampeningMultiplier;
                }

                // Apply overall normalized scaling
                $normalizedChange = $change * $scaleFactor;
                $scoreAccumulator += $normalizedChange;

                // Category-specific scaling
                if ($isPayment) {
                    $normChangeCategory = $change * $scaleFactorPayment;
                    $paymentScoreAccumulator += $normChangeCategory;
                } else {
                    $normChangeCategory = $change * $scaleFactorEngagement;
                    $engagementScoreAccumulator += $normChangeCategory;
                }

                $breakdown[$rule['metric_key']] = [
                    'name'        => $rule['metric_name'] ?? str_replace('_', ' ', $rule['metric_key']),
                    'points'      => round($normalizedChange, 2),
                    'explanation' => $res['explanation'] . ($scaleFactor != 1.0 ? " (normalized weight)" : ""),
                ];

                // Detect risk drivers (negative score contributors)
                if ($normalizedChange < 0) {
                    $riskDrivers[] = [
                        'key'         => $rule['metric_key'],
                        'name'        => $rule['metric_name'] ?? str_replace('_', ' ', $rule['metric_key']),
                        'points'      => round($normalizedChange, 2),
                        'explanation' => $res['explanation'],
                    ];
                }
            }

            // Sort risk drivers (lowest scoring first)
            usort($riskDrivers, function ($a, $b) {
                return $a['points'] <=> $b['points'];
            });
            $breakdown['risk_drivers'] = $riskDrivers;

            $finalScore = (int)max(0, min(100, round($scoreAccumulator)));
            $finalPaymentScore = (int)max(0, min(100, round($paymentScoreAccumulator)));
            $finalEngagementScore = (int)max(0, min(100, round($engagementScoreAccumulator)));

            // Fetch previous score for trend analysis
            $prevRecord = $this->getScoreForClient($clientId);
            $prevScore = $prevRecord ? (int)$prevRecord['score'] : $finalScore;

            if ($finalScore > $prevScore) {
                $trend = 'up';
            } elseif ($finalScore < $prevScore) {
                $trend = 'down';
            } else {
                $trend = 'stable';
            }

            // Save the score, category details and snapshot
            $this->saveScore(
                $clientId,
                $finalScore,
                $finalPaymentScore,
                $finalEngagementScore,
                $trend,
                $prevScore,
                $breakdown
            );

            // Handle potential alerts
            $this->processAlertsForClient($clientId, $finalScore, $prevScore);

            $results[$clientId] = $finalScore;
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
    public function getScores(int $page, int $limit, string $search = '', string $statusFilter = '', string $sort = '', string $dir = ''): array
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
                    $query->orderByRaw("
                       ((SELECT COALESCE(SUM(
                          CASE WHEN billingcycle = 'Monthly' THEN recurringamount
                               WHEN billingcycle = 'Quarterly' THEN recurringamount / 3
                               WHEN billingcycle = 'Semi-Annually' THEN recurringamount / 6
                               WHEN billingcycle = 'Annually' THEN recurringamount / 12
                               WHEN billingcycle = 'Biennially' THEN recurringamount / 24
                               WHEN billingcycle = 'Triennially' THEN recurringamount / 36
                               ELSE 0 END
                        ), 0) FROM tblhosting WHERE userid = tblclients.id AND domainstatus = 'Active') +
                       (SELECT COALESCE(SUM(recurringamount / registrationperiod / 12), 0) FROM tbldomains WHERE userid = tblclients.id AND status = 'Active')) {$sortDir}
                    ");
                    break;
                default:
                    $query->orderBy('tblclients.id', 'desc');
                    break;
            }
        } else {
            $query->orderBy('tblclients.id', 'desc');
        }

        $records = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        $items = array_map(function ($item) {
            $arr = (array)$item;
            if ($arr['breakdown']) {
                $arr['breakdown'] = json_decode($arr['breakdown'], true);
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
                COUNT(CASE WHEN score >= 50 AND score < 80 THEN 1 END) as warning,
                COUNT(CASE WHEN score < 50 THEN 1 END) as critical,
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

        // 1. Fetch signup datecreated from tblclients
        $clients = Capsule::table('tblclients')
            ->whereIn('id', $clientIds)
            ->select('id', 'datecreated')
            ->get()
            ->keyBy('id')
            ->toArray();

        // 2. Fetch unpaid invoices count
        $unpaidInvoices = Capsule::table('tblinvoices')
            ->whereIn('userid', $clientIds)
            ->where('status', 'Unpaid')
            ->selectRaw('userid, COUNT(*) as count')
            ->groupBy('userid')
            ->pluck('count', 'userid')
            ->toArray();

        // 3. Fetch overdue invoices count
        $overdueInvoices = Capsule::table('tblinvoices')
            ->whereIn('userid', $clientIds)
            ->where('status', 'Unpaid')
            ->where('duedate', '<', date('Y-m-d'))
            ->selectRaw('userid, COUNT(*) as count')
            ->groupBy('userid')
            ->pluck('count', 'userid')
            ->toArray();

        // 4. Fetch active services count
        $activeServices = Capsule::table('tblhosting')
            ->whereIn('userid', $clientIds)
            ->where('domainstatus', 'Active')
            ->selectRaw('userid, COUNT(*) as count')
            ->groupBy('userid')
            ->pluck('count', 'userid')
            ->toArray();

        // 5. Fetch cancelled services count in lookback period
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$lookbackDays} days"));
        $cancelledServices = Capsule::table('tblcancelrequests')
            ->join('tblhosting', 'tblcancelrequests.relid', '=', 'tblhosting.id')
            ->whereIn('tblhosting.userid', $clientIds)
            ->where('tblcancelrequests.date', '>=', $cutoffDate)
            ->selectRaw('tblhosting.userid, COUNT(*) as count')
            ->groupBy('tblhosting.userid')
            ->pluck('count', 'userid')
            ->toArray();

        // 6. Fetch open tickets count
        $openTickets = Capsule::table('tbltickets')
            ->whereIn('userid', $clientIds)
            ->whereIn('status', ['Open', 'Customer-Reply', 'In-Progress'])
            ->selectRaw('userid, COUNT(*) as count')
            ->groupBy('userid')
            ->pluck('count', 'userid')
            ->toArray();

        // 7. Fetch total paid revenue
        $totalPaidRevenue = Capsule::table('tblinvoices')
            ->whereIn('userid', $clientIds)
            ->where('status', 'Paid')
            ->selectRaw('userid, SUM(total) as total')
            ->groupBy('userid')
            ->pluck('total', 'userid')
            ->toArray();

        $metricsBatch = [];
        foreach ($clientIds as $clientId) {
            $clientObj = $clients[$clientId] ?? null;
            $dateCreated = $clientObj ? $clientObj->datecreated : '';

            $metricsBatch[$clientId] = [
                'client_id'                => $clientId,
                'datecreated'              => $dateCreated,
                'unpaid_invoices_count'    => (int)($unpaidInvoices[$clientId] ?? 0),
                'overdue_invoices_count'   => (int)($overdueInvoices[$clientId] ?? 0),
                'total_paid_revenue'       => (float)($totalPaidRevenue[$clientId] ?? 0.0),
                'active_services_count'    => (int)($activeServices[$clientId] ?? 0),
                'cancelled_services_count' => (int)($cancelledServices[$clientId] ?? 0),
                'open_tickets_count'       => (int)($openTickets[$clientId] ?? 0),
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
     * @return array Contains 'change' (float) and 'explanation' (string)
     */
    private function calculateMetric(string $key, array $metrics, array $rule): array
    {
        $weight = (float)$rule['weight'];
        $change = 0.0;
        $explanation = '';
        $applicable = true;

        switch ($key) {
            case 'client_tenure':
                if (empty($metrics['datecreated'])) {
                    $explanation = "No registration date recorded.";
                    $applicable = false;
                    break;
                }
                try {
                    $signUpDate = new \DateTime($metrics['datecreated']);
                    $now = new \DateTime();
                    $interval = $signUpDate->diff($now);

                    // Compute exact fractional years
                    $years = $interval->y + ($interval->m / 12.0) + ($interval->d / 365.25);
                    $points = $years * $weight;

                    $config = json_decode($rule['config'] ?? '{}', true);
                    $maxPoints = (int)($config['max_points'] ?? 20);

                    $change = min($points, $maxPoints);
                    
                    $yearsInt = $interval->y;
                    $monthsInt = $interval->m;
                    $yearsFormatted = $yearsInt > 0 ? "{$yearsInt} year" . ($yearsInt > 1 ? "s" : "") : "";
                    $monthsFormatted = $monthsInt > 0 ? "{$monthsInt} month" . ($monthsInt > 1 ? "s" : "") : "";
                    $tenureStr = implode(" and ", array_filter([$yearsFormatted, $monthsFormatted])) ?: "less than a month";

                    $pointsFormatted = round($change, 1);
                    $explanation = "Client tenure of {$tenureStr} adds +{$pointsFormatted} points.";
                } catch (\Exception $e) {
                    $explanation = "Unable to calculate tenure details.";
                }
                break;

            case 'unpaid_invoices':
                $unpaidCount = (int)($metrics['unpaid_invoices_count'] ?? 0);
                $change = $unpaidCount * $weight;
                if ($unpaidCount === 0) {
                    $explanation = "No unpaid invoices (good standing).";
                } else {
                    $explanation = "{$unpaidCount} unpaid invoice"
                        . ($unpaidCount > 1 ? "s" : "")
                        . " deducts " . abs(round($change, 2)) . " points.";
                }
                break;

            case 'overdue_invoices':
                $overdueCount = (int)($metrics['overdue_invoices_count'] ?? 0);
                $change = $overdueCount * $weight;
                if ($overdueCount === 0) {
                    $explanation = "No overdue invoices (good standing).";
                } else {
                    $explanation = "{$overdueCount} overdue invoice"
                        . ($overdueCount > 1 ? "s" : "")
                        . " deducts " . abs(round($change, 2)) . " points.";
                }
                break;

            case 'active_services':
                $activeCount = (int)($metrics['active_services_count'] ?? 0);
                $points = $activeCount * $weight;
                $config = json_decode($rule['config'] ?? '{}', true);
                $maxPoints = (int)($config['max_points'] ?? 30);
                $change = min($points, $maxPoints);

                if ($activeCount === 0) {
                    $explanation = "No active services.";
                } else {
                    $explanation = "{$activeCount} active service"
                        . ($activeCount > 1 ? "s" : "")
                        . " adds +{$change} points.";
                }
                break;

            case 'cancelled_services':
                $cancelledCount = (int)($metrics['cancelled_services_count'] ?? 0);
                $change = $cancelledCount * $weight; // weight is negative
                if ($cancelledCount === 0) {
                    $explanation = "No recent service cancellations.";
                } else {
                    $config = json_decode($rule['config'] ?? '{}', true);
                    $lookbackDays = (int)($config['lookback_days'] ?? 180);
                    $explanation = "{$cancelledCount} service cancellation"
                        . ($cancelledCount > 1 ? "s" : "")
                        . " in the last {$lookbackDays} days deducts " . abs(round($change, 2)) . " points.";
                }
                break;

            case 'open_tickets':
                $openCount = (int)($metrics['open_tickets_count'] ?? 0);
                $change = $openCount * $weight;
                if ($openCount === 0) {
                    $explanation = "No open support tickets (satisfied status).";
                } else {
                    $explanation = "{$openCount} open support ticket"
                        . ($openCount > 1 ? "s" : "")
                        . " deducts " . abs(round($change, 2)) . " points.";
                }
                break;
        }

        return [
            'change'      => $change,
            'explanation' => $explanation,
            'applicable'  => $applicable,
        ];
    }

    /**
     * Process alerts for a client based on score changes and send webhooks.
     */
    public function processAlertsForClient(int $clientId, int $score, int $prevScore)
    {
        $alertThreshold = (int)$this->getSetting('alert_threshold', 50);

        if ($score < $alertThreshold && $prevScore >= $alertThreshold) {
            $type = 'critical_health_drop';
            $message = "Client health score dropped to {$score} (previously {$prevScore}).";
            $severity = 'danger';
            $this->triggerAlert($clientId, $type, $message, $severity);
        } elseif ($score >= $alertThreshold && $prevScore < $alertThreshold) {
            $type = 'health_recovery';
            $message = "Client health score recovered to {$score} (previously {$prevScore}).";
            $severity = 'info';
            $this->triggerAlert($clientId, $type, $message, $severity);
        }
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

        $this->dispatchWebhooks($clientId, $type, $message, $severity);
    }

    /**
     * Dispatch webhooks to configured integrations.
     */
    private function dispatchWebhooks(int $clientId, string $type, string $message, string $severity)
    {
        $clientName = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->selectRaw("CONCAT(firstname, ' ', lastname) as name")
            ->value('name') ?? 'Client #' . $clientId;

        $payloads = [
            'slack' => [
                'setting' => 'webhook_slack_url',
                'body' => json_encode(['text' => "⚠️ [Client Health Score Alert] *{$clientName}* (Client ID: {$clientId}): {$message}"]),
                'headers' => ['Content-Type: application/json']
            ],
            'discord' => [
                'setting' => 'webhook_discord_url',
                'body' => json_encode(['content' => "⚠️ **[Client Health Score Alert]** **{$clientName}** (Client ID: {$clientId}): {$message}"]),
                'headers' => ['Content-Type: application/json']
            ],
            'teams' => [
                'setting' => 'webhook_teams_url',
                'body' => json_encode(['text' => "⚠️ **[Client Health Score Alert]** **{$clientName}** (Client ID: {$clientId}): {$message}"]),
                'headers' => ['Content-Type: application/json']
            ],
            'generic' => [
                'setting' => 'webhook_generic_url',
                'body' => json_encode([
                    'event'       => 'health_alert',
                    'client_id'   => $clientId,
                    'client_name' => $clientName,
                    'alert_type'  => $type,
                    'message'     => $message,
                    'severity'    => $severity,
                    'timestamp'   => date('c')
                ]),
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
        $lastDigest = Capsule::table('mod_chs_weekly_digest_logs')
            ->orderBy('sent_at', 'desc')
            ->value('sent_at');

        $isDue = false;
        if (!$lastDigest) {
            $isDue = true;
        } else {
            $days = (time() - strtotime($lastDigest)) / 86400;
            if ($days >= 7.0) {
                $isDue = true;
            }
        }

        if (!$isDue) {
            return;
        }

        try {
            $stats = $this->getStats();

            $atRisk = Capsule::select("
                SELECT c.id, c.firstname, c.lastname, c.companyname, s.score,
                   ((SELECT COALESCE(SUM(
                      CASE WHEN billingcycle = 'Monthly' THEN recurringamount
                           WHEN billingcycle = 'Quarterly' THEN recurringamount / 3
                           WHEN billingcycle = 'Semi-Annually' THEN recurringamount / 6
                           WHEN billingcycle = 'Annually' THEN recurringamount / 12
                           WHEN billingcycle = 'Biennially' THEN recurringamount / 24
                           WHEN billingcycle = 'Triennially' THEN recurringamount / 36
                           ELSE 0 END
                    ), 0) FROM tblhosting WHERE userid = c.id AND domainstatus = 'Active') +
                   (SELECT COALESCE(SUM(recurringamount / registrationperiod), 0) FROM tbldomains WHERE userid = c.id AND status = 'Active')) as mrr
                FROM tblclients c
                JOIN mod_chs_scores s ON c.id = s.client_id
                WHERE s.score < 50 AND c.status = 'Active'
                ORDER BY mrr DESC, s.score ASC
                LIMIT 5
            ");
            $atRiskList = array_map(function($item) { return (array)$item; }, $atRisk);

            $adminEmail = $this->getSetting('digest_recipient_email');
            if (empty($adminEmail)) {
                $adminEmail = Capsule::table('tblconfiguration')->where('setting', 'Email')->value('value');
            }

            if (empty($adminEmail)) {
                throw new \Exception("No admin recipient email configured.");
            }

            $subject = "WHMCS Client Health Weekly Digest - " . date('Y-m-d');
            $body = "<h2>Client Health Weekly Digest Report</h2>";
            $body .= "<p>Here is your weekly summary of client health scores and potential churn risks.</p>";
            $body .= "<h3>General Statistics</h3>";
            $body .= "<ul>";
            $body .= "  <li>Average Health Score: <strong>" . ($stats['average_score'] ?? 'N/A') . "/100</strong></li>";
            $body .= "  <li>Total Evaluated Clients: <strong>" . $stats['total_clients'] . "</strong></li>";
            $body .= "  <li>Healthy Clients (score >= 80): <strong style='color:#10b981;'>" . $stats['healthy'] . "</strong></li>";
            $body .= "  <li>Warning Clients (score 50-79): <strong style='color:#f59e0b;'>" . $stats['warning'] . "</strong></li>";
            $body .= "  <li>Critical Churn Risk Clients (score < 50): <strong style='color:#ef4444;'>" . $stats['critical'] . "</strong></li>";
            $body .= "</ul>";

            $body .= "<h3>Top At-Risk Clients by MRR</h3>";
            if (empty($atRiskList)) {
                $body .= "<p style='color:#10b981;'>No active clients currently have critical health scores.</p>";
            } else {
                $body .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px; width:100%; max-width:600px;'>";
                $body .= "  <tr style='background-color:#f2f2f2;'><th>Client Name</th><th>Health Score</th><th>MRR</th></tr>";
                foreach ($atRiskList as $c) {
                    $name = $c['firstname'] . ' ' . $c['lastname'];
                    if ($c['companyname']) {
                        $name .= ' (' . $c['companyname'] . ')';
                    }
                    $body .= "  <tr>";
                    $body .= "    <td><strong>" . htmlspecialchars($name) . "</strong></td>";
                    $body .= "    <td align='center'><span style='background-color:#ef4444; color:#fff; padding:2px 6px; border-radius:3px; font-weight:bold;'>" . $c['score'] . "</span></td>";
                    $body .= "    <td align='right'>$" . number_format($c['mrr'], 2) . "</td>";
                    $body .= "  </tr>";
                }
                $body .= "</table>";
            }

            $body .= "<p style='font-size:11px; color:#666; margin-top:20px;'>Generated by WHMCS Client Health Score Addon Module.</p>";

            // Send via WHMCS Local API
            $apiResult = localAPI('SendAdminEmail', [
                'customsubject' => $subject,
                'custommessage' => $body,
                'mergefields'   => [],
            ]);
            $sent = ($apiResult['result'] === 'success');

            Capsule::table('mod_chs_weekly_digest_logs')->insert([
                'sent_at'    => date('Y-m-d H:i:s'),
                'recipients' => $adminEmail,
                'stats'      => json_encode($stats),
                'status'     => $sent ? 'success' : 'failed',
            ]);
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
