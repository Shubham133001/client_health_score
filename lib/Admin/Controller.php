<?php

namespace WHMCS\Module\Addon\ClientHealthScore\Admin;

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class Controller
{
    /**
     * Dispatch the request.
     *
     * @param string $action
     * @param array $vars Addon parameters
     * @return string HTML output or JSON string
     */
    public function execute(string $action, array $vars): string
    {
        // Route AJAX requests
        if ($action === 'ajax_recalculate') {
            return $this->ajaxRecalculate();
        }

        $message = '';
        if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $profileId = (int)($_REQUEST['id'] ?? 1);
            $message = $this->saveProfileSettings($profileId);
            return $this->editProfilePage($vars, $profileId, $message);
        }

        if ($action === 'recalculate_client' && isset($_REQUEST['id'])) {
            $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();
            $clientController->calculateForClient((int)$_REQUEST['id']);
            header("Location: " . $vars['modulelink'] . "&action=client&id=" . (int)$_REQUEST['id'] . "&success=1");
            exit;
        }

        switch ($action) {
            case 'client':
                return $this->clientPage($vars);
            case 'settings':
                return $this->settings($vars);
            case 'reports':
                return $this->reportsPage($vars);
            case 'audit':
                return $this->auditPage($vars);
            case 'dashboard':
            default:
                return $this->dashboardPage($vars);
        }
    }

    /**
     * Enforce role-based permission access checks for addon modules.
     */
    private function hasPermission(string $action): bool
    {
        $adminId = (int)($_SESSION['adminid'] ?? 0);
        if ($adminId <= 0) {
            return false;
        }

        // Fetch admin's role ID
        $roleId = Capsule::table('tbladmins')
            ->where('id', $adminId)
            ->value('roleid');

        if ($roleId == 1) {
            // Full Administrator always has access
            return true;
        }

        switch ($action) {
            case 'manage_rules':
            case 'manage_settings':
            case 'view_audit':
                return ($roleId == 1); // Restrict to Full Admins
            case 'recalculate':
            case 'view':
            default:
                return in_array($roleId, [1, 2, 3]); // Full Admin, Support, Billing
        }
    }

    public function dashboard($vars)
    {
        if (!$this->hasPermission('view')) {
            return '<div class="alert alert-danger">You do not have permission to view the health dashboard.</div>';
        }
        return $this->dashboardPage($vars);
    }

    public function client($vars)
    {
        if (!$this->hasPermission('view')) {
            return '<div class="alert alert-danger">You do not have permission to view this page.</div>';
        }
        return $this->clientPage($vars);
    }

    public function settings($vars)
    {
        if (!$this->hasPermission('manage_settings')) {
            return '<div class="alert alert-danger">You do not have permission to view settings.</div>';
        }
        
        $sub = $_REQUEST['sub'] ?? '';
        $message = '';

        if ($sub === 'add') {
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if ($name !== '') {
                // Insert profile
                $settingsJson = json_encode([
                    'payment_weight'       => 50.0,
                    'engagement_weight'    => 50.0,
                    'dampening_enabled'    => true,
                    'dampening_threshold'  => 60,
                    'dampening_multiplier' => 1.5,
                    'trend_lookback_days'  => 14,
                    'alert_threshold'      => 50.0,
                ]);
                $newProfileId = Capsule::table('mod_chs_profiles')->insertGetId([
                    'name'        => $name,
                    'description' => $desc,
                    'is_default'  => 0,
                    'settings'    => $settingsJson,
                ]);
                
                // Copy default rules, tiers, bands from profile 1
                $defaultRules = Capsule::table('mod_chs_profile_rules')->where('profile_id', 1)->get();
                foreach ($defaultRules as $r) {
                    Capsule::table('mod_chs_profile_rules')->insert([
                        'profile_id' => $newProfileId,
                        'metric_key' => $r->metric_key,
                        'weight'     => $r->weight,
                        'is_enabled' => $r->is_enabled,
                        'config'     => $r->config,
                    ]);
                }
                
                $defaultTiers = Capsule::table('mod_chs_tiers')->where('profile_id', 1)->get();
                foreach ($defaultTiers as $t) {
                    Capsule::table('mod_chs_tiers')->insert([
                        'profile_id'  => $newProfileId,
                        'name'        => $t->name,
                        'min_score'   => $t->min_score,
                        'max_score'   => $t->max_score,
                        'badge_color' => $t->badge_color,
                    ]);
                }
                
                $defaultBands = Capsule::table('mod_chs_score_bands')->where('profile_id', 1)->get();
                foreach ($defaultBands as $b) {
                    Capsule::table('mod_chs_score_bands')->insert([
                        'profile_id'     => $newProfileId,
                        'name'           => $b->name,
                        'min_score'      => $b->min_score,
                        'max_score'      => $b->max_score,
                        'severity_level' => $b->severity_level,
                        'badge_color'    => $b->badge_color,
                    ]);
                }
                
                $this->logAudit('add_profile', "Created new scoring profile: {$name}");
                $message = "Scoring profile created successfully.";
            }
            $sub = '';
        } elseif ($sub === 'delete') {
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id > 1) {
                // Delete assignments, rules, tiers, bands and profile itself
                Capsule::table('mod_chs_profile_assignments')->where('profile_id', $id)->delete();
                Capsule::table('mod_chs_profile_rules')->where('profile_id', $id)->delete();
                Capsule::table('mod_chs_tiers')->where('profile_id', $id)->delete();
                Capsule::table('mod_chs_score_bands')->where('profile_id', $id)->delete();
                Capsule::table('mod_chs_profiles')->where('id', $id)->delete();
                
                $this->logAudit('delete_profile', "Deleted scoring profile ID: {$id}");
                $message = "Scoring profile deleted successfully.";
            }
            $sub = '';
        } elseif ($sub === 'add_assignment') {
            $profileId = (int)($_POST['profile_id'] ?? 0);
            $type = $_POST['type'] ?? '';
            $value = (int)($_POST['value'] ?? 0);
            
            if ($profileId > 0 && $value > 0) {
                // Validate if target exists
                $exists = false;
                if ($type === 'client') {
                    $exists = Capsule::table('tblclients')->where('id', $value)->exists();
                    if (!$exists) {
                        $message = "Error: Client ID {$value} does not exist.";
                    }
                } elseif ($type === 'group') {
                    $exists = Capsule::table('tblclientgroups')->where('id', $value)->exists();
                    if (!$exists) {
                        $message = "Error: Client Group ID {$value} does not exist.";
                    }
                } elseif ($type === 'product') {
                    $exists = Capsule::table('tblproducts')->where('id', $value)->exists();
                    if (!$exists) {
                        $message = "Error: Product ID {$value} does not exist.";
                    }
                }

                if ($exists) {
                    $assignmentData = ['profile_id' => $profileId, 'assigned_by' => 'admin'];
                    if ($type === 'client') {
                        $assignmentData['client_id'] = $value;
                        Capsule::table('mod_chs_profile_assignments')->where('client_id', $value)->delete();
                    } elseif ($type === 'group') {
                        $assignmentData['group_id'] = $value;
                        Capsule::table('mod_chs_profile_assignments')->where('group_id', $value)->delete();
                    } elseif ($type === 'product') {
                        $assignmentData['product_id'] = $value;
                        Capsule::table('mod_chs_profile_assignments')->where('product_id', $value)->delete();
                    }
                    
                    Capsule::table('mod_chs_profile_assignments')->insert($assignmentData);
                    $this->logAudit('add_profile_assignment', "Assigned profile ID {$profileId} to {$type} ID {$value}");
                    $message = "Profile assignment added successfully.";
                }
            } else {
                $message = "Error: Invalid profile or target selection.";
            }
            $sub = '';
        } elseif ($sub === 'delete_assignment') {
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id > 0) {
                Capsule::table('mod_chs_profile_assignments')->where('id', $id)->delete();
                $this->logAudit('delete_profile_assignment', "Deleted profile assignment ID: {$id}");
                $message = "Profile assignment removed successfully.";
            }
            $sub = '';
        } elseif ($sub === 'save_global' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $postedGlobalSettings = $_POST['global_settings'] ?? [];
            foreach ($postedGlobalSettings as $key => $value) {
                Capsule::table('mod_chs_settings')
                    ->where('key', $key)
                    ->update(['value' => $value]);
            }
            $this->logAudit('update_global_settings', "Updated global addon settings");
            $message = "Global settings saved successfully.";
            $sub = '';
        }

        if ($sub === 'edit') {
            return $this->editProfilePage($vars, (int)($_REQUEST['id'] ?? 1), $message);
        }

        return $this->profilesListPage($vars, $message);
    }

    public function reports($vars)
    {
        if (!$this->hasPermission('view')) {
            return '<div class="alert alert-danger">You do not have permission to view reports.</div>';
        }
        return $this->reportsPage($vars);
    }

    public function audit($vars)
    {
        if (!$this->hasPermission('view_audit')) {
            return '<div class="alert alert-danger">You do not have permission to view audit logs.</div>';
        }
        return $this->auditPage($vars);
    }

    public function ajax_recalculate($vars)
    {
        if (!$this->hasPermission('recalculate')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        return $this->ajaxRecalculate();
    }

    public function save_settings($vars)
    {
        if (!$this->hasPermission('manage_settings')) {
            return '<div class="alert alert-danger">You do not have permission to save settings.</div>';
        }
        $message = '';
        $profileId = (int)($_REQUEST['id'] ?? 1);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = $this->saveProfileSettings($profileId);
        }
        return $this->editProfilePage($vars, $profileId, $message);
    }

    public function recalculate_client($vars)
    {
        if (!$this->hasPermission('recalculate')) {
            return '<div class="alert alert-danger">You do not have permission to trigger recalculation.</div>';
        }
        if (isset($_REQUEST['id'])) {
            $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();
            $clientController->calculateForClient((int)$_REQUEST['id']);
            header("Location: " . $vars['modulelink'] . "&action=client&id=" . (int)$_REQUEST['id'] . "&success=1");
            exit;
        }
        return '<p>Invalid Client ID specified.</p>';
    }

    public function export_csv($vars)
    {
        if (!$this->hasPermission('view')) {
            return '<div class="alert alert-danger">You do not have permission to export client health data.</div>';
        }

        $query = Capsule::table('tblclients')
            ->leftJoin('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'tblclients.email',
                'tblclients.groupid',
                'mod_chs_scores.score',
                'mod_chs_scores.payment_score',
                'mod_chs_scores.engagement_score',
                'mod_chs_scores.trend',
                'mod_chs_scores.breakdown',
                'mod_chs_scores.updated_at',
            ])
            ->where('tblclients.status', '!=', 'Closed');

        // Apply filters:
        // 1. Group Filter
        if (!empty($_REQUEST['group_id'])) {
            $query->where('tblclients.groupid', (int)$_REQUEST['group_id']);
        }

        // 2. Score Range Filter
        if (isset($_REQUEST['min_score']) && $_REQUEST['min_score'] !== '') {
            $query->where('mod_chs_scores.score', '>=', (int)$_REQUEST['min_score']);
        }
        if (isset($_REQUEST['max_score']) && $_REQUEST['max_score'] !== '') {
            $query->where('mod_chs_scores.score', '<=', (int)$_REQUEST['max_score']);
        }

        // 3. Date Range Filter (Client SignUp Date)
        if (!empty($_REQUEST['start_date'])) {
            $query->where('tblclients.datecreated', '>=', $_REQUEST['start_date']);
        }
        if (!empty($_REQUEST['end_date'])) {
            $query->where('tblclients.datecreated', '<=', $_REQUEST['end_date'] . ' 23:59:59');
        }

        $records = $query->get()->map(function ($item) {
            return (array)$item;
        })->toArray();

        $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();

        $now = date('Y-m-d');
        $overrides = Capsule::table('mod_chs_manual_overrides')
            ->where(function($q) use ($now) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $now);
            })
            ->get()
            ->keyBy('client_id');

        // 4. Tier, Product Profile & Minimum MRR Filters in PHP
        $filtered = [];
        $filterTier = trim((string)($_REQUEST['tier'] ?? ''));
        $filterProfile = (int)($_REQUEST['profile_id'] ?? 0);
        $minMrr = (float)($_REQUEST['min_mrr'] ?? 0.0);

        foreach ($records as $item) {
            $clientId = (int)$item['id'];
            $score = $item['score'] !== null ? (int)$item['score'] : 100;

            // Resolve profile ID
            $profileId = $clientController->resolveProfileIdForClient($clientId);
            if ($filterProfile > 0 && $profileId !== $filterProfile) {
                continue;
            }

            // Calculate MRR
            $mrr = $clientController->getClientMRR($clientId);
            if ($mrr < $minMrr) {
                continue;
            }

            // Resolve Tier (and check if overridden)
            $isOverridden = false;
            if (isset($overrides[$clientId])) {
                $tierName = $overrides[$clientId]->tier;
                $isOverridden = true;
            } else {
                $tierName = $clientController->getTierForScore($score, $profileId);
            }

            if (!empty($filterTier) && strtolower($tierName) !== strtolower($filterTier)) {
                continue;
            }

            $item['resolved_profile_id'] = $profileId;
            $item['mrr'] = $mrr;
            $item['tier'] = $isOverridden ? 'PINNED: ' . $tierName : $tierName;
            $filtered[] = $item;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=client-health-scores-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Client ID',
            'Client Name',
            'Company',
            'Email',
            'Score',
            'Tier',
            'Payment Score',
            'Engagement Score',
            'MRR',
            'Trend',
            'Last Calculated',
            'Risk Drivers'
        ]);

        foreach ($filtered as $item) {
            // Extract Risk Drivers
            $drivers = [];
            if ($item['breakdown']) {
                $b = json_decode($item['breakdown'], true);
                $rawDrivers = $b['risk_drivers'] ?? [];
                $drivers = array_map(function($d) {
                    return $d['name'] . ' (' . $d['points'] . ')';
                }, $rawDrivers);
            }

            fputcsv($output, [
                $item['id'],
                $item['firstname'] . ' ' . $item['lastname'],
                $item['companyname'] ?: '-',
                $item['email'],
                $item['score'] !== null ? $item['score'] : 'Unevaluated',
                $item['tier'],
                $item['payment_score'] !== null ? $item['payment_score'] : 100,
                $item['engagement_score'] !== null ? $item['engagement_score'] : 100,
                '$' . number_format($item['mrr'], 2),
                strtoupper($item['trend'] ?: 'stable'),
                $item['updated_at'] ?: 'Never',
                empty($drivers) ? 'None' : implode('; ', $drivers)
            ]);
        }

        fclose($output);
        exit;
    }

    public function export_pdf($vars)
    {
        // Stub action: prepares the routing and UI architecture for future PDF generation library integration (e.g. TCPDF or Dompdf)
        if (!$this->hasPermission('view')) {
            return '<div class="alert alert-danger">You do not have permission to export data.</div>';
        }
        return '<div style="margin: 20px;" class="alert alert-info"><h4><i class="fa fa-info-circle"></i> PDF Export Planned</h4><p>PDF Export capabilities are planned for a future update. Please use the CSV Export function in the interim.</p></div>';
    }

    /**
     * Render the main dashboard view.
     */
    /**
     * Render the main dashboard view.
     */
    private function dashboardPage(array $vars): string
    {
        $page = (int)($_REQUEST['page'] ?? 1);
        $page = max(1, $page);
        $limit = 15;

        $search = trim((string)($_REQUEST['search'] ?? ''));
        $statusFilter = trim((string)($_REQUEST['status'] ?? ''));
        $sort = trim((string)($_REQUEST['sort'] ?? ''));
        $dir = trim((string)($_REQUEST['dir'] ?? ''));
        $groupIdFilter = (int)($_REQUEST['group_id'] ?? 0);
        $profileIdFilter = (int)($_REQUEST['profile_id'] ?? 0);

        $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();

        // Fetch paginated scores
        $scoresData = $clientController->getScores($page, $limit, $search, $statusFilter, $sort, $dir, $groupIdFilter, $profileIdFilter);
        $totalPages = (int)ceil($scoresData['total'] / $limit);
        $totalPages = max(1, $totalPages);

        // Fetch stats
        $stats = $clientController->getStats();

        // Load bands to dynamically determine the Watch minimum threshold
        $bandsList = [];
        $bandsSeq = [];
        try {
            $bandsDb = Capsule::table('mod_chs_score_bands')
                ->where('profile_id', 1)
                ->orderBy('min_score', 'desc')
                ->get();
            foreach ($bandsDb as $b) {
                $arr = (array)$b;
                $slug = strtolower(str_replace([' ', '-'], '_', $b->name));
                $arr['slug'] = $slug;
                $bandsList[$slug] = $arr;
                $bandsSeq[] = $arr;
            }
        } catch (\Exception $e) {}

        $watchMin = $bandsList['watch']['min_score'] ?? 60;

        // Fetch overrides and scores to dynamically determine at-risk client IDs (Watch, At-Risk, Critical)
        $allScores = Capsule::table('mod_chs_scores')->get();
        $now = date('Y-m-d');
        $overrides = Capsule::table('mod_chs_manual_overrides')
            ->where(function($q) use ($now) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $now);
            })
            ->get()
            ->keyBy('client_id');

        // Load profile bands
        $profiles = Capsule::table('mod_chs_profiles')->get();
        $bandsByProfile = [];
        foreach ($profiles as $p) {
            $bandsByProfile[$p->id] = Capsule::table('mod_chs_score_bands')
                ->where('profile_id', $p->id)
                ->orderBy('min_score', 'desc')
                ->get()
                ->toArray();
        }
        $defaultBands = $bandsByProfile[1] ?? [];
        $atRiskClientIds = [];

        foreach ($allScores as $s) {
            $clientId = (int)$s->client_id;
            $statusName = 'Healthy';

            if (isset($overrides[$clientId])) {
                $statusName = $overrides[$clientId]->tier;
            } else {
                $profileId = $clientController->resolveProfileIdForClient($clientId);
                $bands = $bandsByProfile[$profileId] ?? $defaultBands;
                foreach ($bands as $b) {
                    if ($s->score >= $b->min_score && $s->score <= $b->max_score) {
                        $statusName = $b->name;
                        break;
                    }
                }
            }

            if (strcasecmp($statusName, 'Healthy') !== 0) {
                $atRiskClientIds[] = $clientId;
            }
        }

        if (empty($atRiskClientIds)) {
            $atRiskClientIds = [0];
        }

        // Calculate MRR at Risk using the effective at-risk client IDs
        $mrrHosting = Capsule::table('tblhosting')
            ->where('domainstatus', 'Active')
            ->whereIn('userid', $atRiskClientIds)
            ->selectRaw("SUM(
                CASE 
                    WHEN billingcycle = 'Monthly' THEN amount
                    WHEN billingcycle = 'Quarterly' THEN amount / 3
                    WHEN billingcycle = 'Semi-Annually' THEN amount / 6
                    WHEN billingcycle = 'Annually' THEN amount / 12
                    WHEN billingcycle = 'Biennially' THEN amount / 24
                    WHEN billingcycle = 'Triennially' THEN amount / 36
                    ELSE 0
                END
            ) as mrr")
            ->value('mrr') ?: 0.00;

        $mrrDomains = Capsule::table('tbldomains')
            ->where('status', 'Active')
            ->whereIn('userid', $atRiskClientIds)
            ->sum(Capsule::raw('recurringamount / registrationperiod / 12')) ?: 0.00;

        $stats['mrr_at_risk'] = round($mrrHosting + $mrrDomains, 2);

        // Fetch dynamic bands for distribution bar chart
        $bandsRanges = [];
        try {
            $bandsDb = Capsule::table('mod_chs_score_bands')
                ->where('profile_id', 1)
                ->orderBy('min_score', 'desc')
                ->get()
                ->toArray();
            $keys = ['platinum', 'gold', 'silver', 'standard'];
            foreach ($keys as $i => $key) {
                $b = $bandsDb[$i] ?? null;
                if ($b) {
                    $bandsRanges[$key] = [
                        'min' => (int)$b->min_score,
                        'max' => (int)$b->max_score,
                    ];
                } else {
                    $fallbacks = [
                        'platinum' => ['min' => 80, 'max' => 100],
                        'gold'     => ['min' => 60, 'max' => 79],
                        'silver'   => ['min' => 35, 'max' => 59],
                        'standard' => ['min' => 0,  'max' => 34],
                    ];
                    $bandsRanges[$key] = $fallbacks[$key];
                }
            }
        } catch (\Exception $e) {
            $bandsRanges = [
                'platinum' => ['min' => 80, 'max' => 100],
                'gold'     => ['min' => 60, 'max' => 79],
                'silver'   => ['min' => 35, 'max' => 59],
                'standard' => ['min' => 0,  'max' => 34],
            ];
        }

        // Fetch distributions for charts using the overridden counts directly
        $stats['tiers_dist'] = [
            'platinum' => $stats['bands_count']['healthy'] ?? 0,
            'gold'     => $stats['bands_count']['watch'] ?? 0,
            'silver'   => $stats['bands_count']['at_risk'] ?? 0,
            'standard' => $stats['bands_count']['critical'] ?? 0,
        ];

        // Fetch recent drops
        $recentDrops = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'mod_chs_scores.score',
                'mod_chs_scores.prev_score',
            ])
            ->where('tblclients.status', '!=', 'Closed')
            ->whereRaw('mod_chs_scores.score < mod_chs_scores.prev_score')
            ->orderByRaw('(mod_chs_scores.prev_score - mod_chs_scores.score) DESC')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        // Fetch recent improvements
        $recentImprovements = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'mod_chs_scores.score',
                'mod_chs_scores.prev_score',
            ])
            ->where('tblclients.status', '!=', 'Closed')
            ->whereRaw('mod_chs_scores.score > mod_chs_scores.prev_score')
            ->orderByRaw('(mod_chs_scores.score - mod_chs_scores.prev_score) DESC')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        $clientGroups = Capsule::table('tblclientgroups')
            ->select('id', 'groupname')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        $scoringProfiles = Capsule::table('mod_chs_profiles')
            ->select('id', 'name')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        return self::renderTemplate('dashboard', [
            'moduleLink'         => $vars['modulelink'],
            'stats'              => $stats,
            'clients'            => $scoresData['items'],
            'total'              => $scoresData['total'],
            'page'               => $page,
            'totalPages'         => $totalPages,
            'search'             => $search,
            'statusFilter'       => $statusFilter,
            'sort'               => $sort,
            'dir'                => $dir,
            'recentDrops'        => $recentDrops,
            'recentImprovements' => $recentImprovements,
            'clientGroups'       => $clientGroups,
            'scoringProfiles'    => $scoringProfiles,
            'groupIdFilter'      => $groupIdFilter,
            'profileIdFilter'    => $profileIdFilter,
            'bandsList'          => $bandsList,
            'bands'              => $bandsSeq,
        ]);
    }

    /**
     * Render the Client Details Profile Page.
     */
    private function clientPage(array $vars): string
    {
        $clientId = (int)($_REQUEST['id'] ?? 0);
        if ($clientId <= 0) {
            header("Location: " . $vars['modulelink']);
            exit;
        }

        // Fetch client details from WHMCS tblclients
        $client = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->first();

        if (!$client) {
            return "<div class='alert alert-danger'>Client not found.</div>";
        }
        $client = (object)$client;

        $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();

        $sub = $_REQUEST['sub'] ?? '';
        
        if ($sub === 'save_override' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $tier = trim($_POST['tier'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $expiry = trim($_POST['expiry_date'] ?? '');
            $expiryVal = $expiry !== '' ? $expiry : null;

            if ($tier !== '' && $reason !== '') {
                $oldOverride = Capsule::table('mod_chs_manual_overrides')->where('client_id', $clientId)->first();
                $oldVal = $oldOverride ? "Tier: {$oldOverride->tier}, Reason: {$oldOverride->reason}, Expiry: {$oldOverride->expiry_date}" : "None";

                Capsule::table('mod_chs_manual_overrides')->updateOrInsert(
                    ['client_id' => $clientId],
                    [
                        'tier'        => $tier,
                        'reason'      => $reason,
                        'expiry_date' => $expiryVal,
                        'created_by'  => $_SESSION['adminusername'] ?? 'admin',
                    ]
                );

                $newVal = "Tier: {$tier}, Reason: {$reason}, Expiry: " . ($expiryVal ?: 'Never');
                $this->logAudit('save_manual_override', "Client ID {$clientId}: Set override to {$tier}. Old: {$oldVal}. New: {$newVal}", 'info', $clientId);

                header("Location: " . $vars['modulelink'] . "&action=client&id=" . $clientId . "&success_override=1");
                exit;
            }
        } elseif ($sub === 'delete_override') {
            $oldOverride = Capsule::table('mod_chs_manual_overrides')->where('client_id', $clientId)->first();
            if ($oldOverride) {
                Capsule::table('mod_chs_manual_overrides')->where('client_id', $clientId)->delete();
                $this->logAudit('delete_manual_override', "Client ID {$clientId}: Removed manual override (was Pinned: {$oldOverride->tier})", 'info', $clientId);
            }
            header("Location: " . $vars['modulelink'] . "&action=client&id=" . $clientId . "&success_override_remove=1");
            exit;
        }

        // Fetch current score
        $scoreRecord = $clientController->getScoreForClient($clientId);

        // If no score exists yet, trigger an on-demand calculation
        if (!$scoreRecord) {
            $clientController->calculateForClient($clientId);
            $scoreRecord = $clientController->getScoreForClient($clientId);
        }

        $profileId = $clientController->resolveProfileIdForClient($clientId);

        // Fetch manual override
        $now = date('Y-m-d');
        $override = Capsule::table('mod_chs_manual_overrides')
            ->where('client_id', $clientId)
            ->where(function($q) use ($now) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $now);
            })
            ->first();

        // Resolve tier name and color
        $tiers = Capsule::table('mod_chs_tiers')
            ->where('profile_id', $profileId)
            ->orderBy('min_score', 'desc')
            ->get()
            ->toArray();
        if (empty($tiers) && $profileId !== 1) {
            $tiers = Capsule::table('mod_chs_tiers')
                ->where('profile_id', 1)
                ->orderBy('min_score', 'desc')
                ->get()
                ->toArray();
        }

        $bandsDb = Capsule::table('mod_chs_score_bands')
            ->where('profile_id', $profileId)
            ->orderBy('min_score', 'desc')
            ->get()
            ->toArray();
        if (empty($bandsDb) && $profileId !== 1) {
            $bandsDb = Capsule::table('mod_chs_score_bands')
                ->where('profile_id', 1)
                ->orderBy('min_score', 'desc')
                ->get()
                ->toArray();
        }

        $tierName = 'Unevaluated';
        $tierColor = '#6b7280';
        $statusBandName = 'Unevaluated';
        $statusBandColor = '#6b7280';

        if ($scoreRecord && isset($scoreRecord['score'])) {
            $scoreVal = $scoreRecord['score'];
            foreach ($tiers as $t) {
                if ($scoreVal >= $t->min_score && $scoreVal <= $t->max_score) {
                    $tierName = $t->name;
                    $tierColor = $t->badge_color;
                    break;
                }
            }
            foreach ($bandsDb as $b) {
                if ($scoreVal >= $b->min_score && $scoreVal <= $b->max_score) {
                    $statusBandName = $b->name;
                    $statusBandColor = $b->badge_color;
                    break;
                }
            }
        }

        // Apply manual override if active
        if ($override) {
            $overrideColor = '#6b7280';
            foreach ($bandsDb as $b) {
                if (strcasecmp($b->name, $override->tier) === 0) {
                    $overrideColor = $b->badge_color;
                    break;
                }
            }
            $statusBandName = 'PINNED: ' . $override->tier;
            $statusBandColor = $overrideColor;
        }

        // Parse breakdown data
        $breakdown = [];
        if (!empty($scoreRecord['breakdown'])) {
            $breakdown = is_string($scoreRecord['breakdown']) 
                ? json_decode($scoreRecord['breakdown'], true) 
                : $scoreRecord['breakdown'];
        }

        // Fetch 30 days history snapshots
        $history = $clientController->getHistoryForClient($clientId, 30);

        // Fetch alert history
        $alerts = Capsule::table('mod_chs_alerts')
            ->where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        // Calculate breakdown parameters
        $unpaidInvoices = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->where('status', 'Unpaid')
            ->count();

        $overdueInvoices = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->where('status', 'Unpaid')
            ->where('duedate', '<', date('Y-m-d'))
            ->count();

        $openTickets = Capsule::table('tbltickets')
            ->where('userid', $clientId)
            ->whereIn('status', ['Open', 'Customer-Reply', 'In-Progress'])
            ->count();

        $activeServices = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->count();

        $bands = Capsule::table('mod_chs_score_bands')
            ->where('profile_id', $profileId)
            ->orderBy('min_score', 'desc')
            ->get()
            ->map(function ($item) {  
                return (array)$item;
            })
            ->toArray();
        if (empty($bands) && $profileId !== 1) {
            $bands = Capsule::table('mod_chs_score_bands')
                ->where('profile_id', 1)
                ->orderBy('min_score', 'desc')
                ->get()
                ->map(function ($item) {  
                    return (array)$item;
                })
                ->toArray();
        }

        return self::renderTemplate('client', [
            'moduleLink'             => $vars['modulelink'],
            'client'                 => $client,
            'scoreRecord'            => $scoreRecord,
            'breakdown'              => $breakdown,
            'history'                => $history,
            'alerts'                 => $alerts,
            'unpaidInvoices'         => $unpaidInvoices,
            'overdueInvoices'        => $overdueInvoices,
            'openTickets'            => $openTickets,
            'activeServices'         => $activeServices,
            'success'                => isset($_REQUEST['success']),
            'success_override'       => isset($_REQUEST['success_override']),
            'success_override_rem'   => isset($_REQUEST['success_override_remove']),
            'tierName'               => $tierName,
            'tierColor'              => $tierColor,
            'statusBandName'         => $statusBandName,
            'statusBandColor'        => $statusBandColor,
            'bands'                  => $bands,
            'override'               => $override ? (array)$override : null,
        ]);
    }

    /**
     * Render the list of Scoring Profiles & Assignments.
     */
    private function profilesListPage(array $vars, string $message = ''): string
    {
        $profiles = Capsule::table('mod_chs_profiles')->get()->toArray();
        $assignmentsRaw = Capsule::table('mod_chs_profile_assignments')->get()->toArray();
        
        $assignments = [];
        foreach ($assignmentsRaw as $a) {
            $arr = (array)$a;
            $arr['profile_name'] = Capsule::table('mod_chs_profiles')->where('id', $a->profile_id)->value('name') ?: 'Unknown';
            if ($a->client_id) {
                $clientName = Capsule::table('tblclients')->where('id', $a->client_id)->selectRaw("CONCAT(firstname, ' ', lastname) as name")->value('name');
                $arr['target_name'] = "Client: " . ($clientName ?: 'Unknown') . " (ID: {$a->client_id})";
            } elseif ($a->group_id) {
                $groupName = Capsule::table('tblclientgroups')->where('id', $a->group_id)->value('groupname');
                $arr['target_name'] = "Group: " . ($groupName ?: 'Unknown');
            } elseif ($a->product_id) {
                $productName = Capsule::table('tblproducts')->where('id', $a->product_id)->value('name');
                $arr['target_name'] = "Product: " . ($productName ?: 'Unknown');
            } else {
                $arr['target_name'] = "Global Default";
            }
            $assignments[] = $arr;
        }

        $clientGroups = Capsule::table('tblclientgroups')
            ->orderBy('groupname', 'asc')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        $products = Capsule::table('tblproducts')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        $settingsRaw = Capsule::table('mod_chs_settings')->get();
        $settings = [];
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = $s->value;
        }

        return self::renderTemplate('profiles_list', [
            'moduleLink'   => $vars['modulelink'],
            'profiles'     => $profiles,
            'assignments'  => $assignments,
            'clientGroups' => $clientGroups,
            'products'     => $products,
            'settings'     => $settings,
            'message'      => $message,
        ]);
    }

    /**
     * Render the single Scoring Profile Edit Page.
     */
    private function editProfilePage(array $vars, int $profileId, string $message = ''): string
    {
        $profile = Capsule::table('mod_chs_profiles')->where('id', $profileId)->first();
        if (!$profile) {
            return '<div class="alert alert-danger">Profile not found.</div>';
        }

        // Auto-initialize missing rules, tiers, and bands from default profile (profile_id = 1)
        try {
            $rulesCount = Capsule::table('mod_chs_profile_rules')->where('profile_id', $profileId)->count();
            if ($rulesCount === 0 && $profileId !== 1) {
                $defaultRules = Capsule::table('mod_chs_profile_rules')->where('profile_id', 1)->get();
                foreach ($defaultRules as $r) {
                    Capsule::table('mod_chs_profile_rules')->insert([
                        'profile_id' => $profileId,
                        'metric_key' => $r->metric_key,
                        'weight'     => $r->weight,
                        'is_enabled' => $r->is_enabled,
                        'config'     => $r->config,
                    ]);
                }
            }

            $tiersCount = Capsule::table('mod_chs_tiers')->where('profile_id', $profileId)->count();
            if ($tiersCount === 0 && $profileId !== 1) {
                $defaultTiers = Capsule::table('mod_chs_tiers')->where('profile_id', 1)->get();
                foreach ($defaultTiers as $t) {
                    Capsule::table('mod_chs_tiers')->insert([
                        'profile_id'  => $profileId,
                        'name'        => $t->name,
                        'min_score'   => $t->min_score,
                        'max_score'   => $t->max_score,
                        'badge_color' => $t->badge_color,
                    ]);
                }
            }

            $bandsCount = Capsule::table('mod_chs_score_bands')->where('profile_id', $profileId)->count();
            if ($bandsCount === 0 && $profileId !== 1) {
                $defaultBands = Capsule::table('mod_chs_score_bands')->where('profile_id', 1)->get();
                foreach ($defaultBands as $b) {
                    Capsule::table('mod_chs_score_bands')->insert([
                        'profile_id'     => $profileId,
                        'name'           => $b->name,
                        'min_score'      => $b->min_score,
                        'max_score'      => $b->max_score,
                        'severity_level' => $b->severity_level,
                        'badge_color'    => $b->badge_color,
                    ]);
                }
            }
        } catch (\Exception $e) {
            logActivity("Client Health Score: Failed to auto-initialize profile ID {$profileId} details: " . $e->getMessage());
        }

        $rules = Capsule::table('mod_chs_profile_rules')
            ->where('profile_id', $profileId)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        $tiers = Capsule::table('mod_chs_tiers')
            ->where('profile_id', $profileId)
            ->orderBy('min_score', 'desc')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        $bands = Capsule::table('mod_chs_score_bands')
            ->where('profile_id', $profileId)
            ->orderBy('min_score', 'desc')
            ->get()
            ->map(function ($item) {  
                return (array)$item;
            })
            ->toArray();

        $profileSettings = json_decode($profile->settings, true) ?: [];

        return self::renderTemplate('profile_edit', [
            'moduleLink'      => $vars['modulelink'],
            'profile'         => $profile,
            'profileSettings' => $profileSettings,
            'rules'           => $rules,
            'tiers'           => $tiers,
            'bands'           => $bands,
            'message'         => $message,
        ]);
    }

    /**
     * Write an audit log entry.
     */
    private function logAudit(string $action, string $description, string $level = 'info', ?int $clientId = null)
    {
        $adminId = (int)($_SESSION['adminid'] ?? 0);
        $username = 'system';
        if ($adminId > 0) {
            $username = Capsule::table('tbladmins')
                ->where('id', $adminId)
                ->value('username') ?? 'admin_' . $adminId;
        }

        Capsule::table('mod_chs_audit_logs')->insert([
            'client_id'    => $clientId,
            'action'       => $action,
            'level'        => $level,
            'description'  => $description,
            'performed_by' => $username,
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Save settings, rules, tiers, and bands configuration for a specific profile.
     */
    private function saveProfileSettings(int $profileId): string
    {
        $profile = Capsule::table('mod_chs_profiles')->where('id', $profileId)->first();
        if (!$profile) {
            return "Profile not found.";
        }

        // 1. Update Profile Metadata & General settings
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        $paymentWeight = (float)($_POST['payment_weight'] ?? 50.0);
        $engagementWeight = (float)($_POST['engagement_weight'] ?? 50.0);
        $trendLookback = (int)($_POST['trend_lookback_days'] ?? 14);
        $alertThreshold = (float)($_POST['alert_threshold'] ?? 50.0);
        
        $dampeningEnabled = isset($_POST['dampening_enabled']) ? 1 : 0;
        $dampeningThreshold = (int)($_POST['dampening_threshold'] ?? 60);
        $dampeningMultiplier = (float)($_POST['dampening_multiplier'] ?? 1.5);
        
        $settingsJson = json_encode([
            'payment_weight'       => $paymentWeight,
            'engagement_weight'    => $engagementWeight,
            'dampening_enabled'    => $dampeningEnabled,
            'dampening_threshold'  => $dampeningThreshold,
            'dampening_multiplier' => $dampeningMultiplier,
            'trend_lookback_days'  => $trendLookback,
            'alert_threshold'      => $alertThreshold,
        ]);
        
        Capsule::table('mod_chs_profiles')
            ->where('id', $profileId)
            ->update([
                'name'        => $name !== '' ? $name : $profile->name,
                'description' => $description,
                'settings'    => $settingsJson,
            ]);

        // 2. Save weights for this profile
        $ruleWeights = $_POST['weights'] ?? [];
        $ruleStatus = $_POST['enabled'] ?? [];
        $ruleConfigs = $_POST['configs'] ?? [];

        foreach ($ruleWeights as $key => $weight) {
            $isEnabled = isset($ruleStatus[$key]) ? 1 : 0;
            $weightVal = (float)$weight;
            $config = $ruleConfigs[$key] ?? [];

            if (isset($config['max_points'])) {
                $config['max_points'] = (int)$config['max_points'];
            }
            if (isset($config['lookback_days'])) {
                $config['lookback_days'] = (int)$config['lookback_days'];
            }

            Capsule::table('mod_chs_profile_rules')
                ->where('profile_id', $profileId)
                ->where('metric_key', $key)
                ->update([
                    'weight'     => $weightVal,
                    'is_enabled' => $isEnabled,
                    'config'     => json_encode($config),
                ]);
        }

        // 3. Save Tiers for this profile
        $tiersPost = $_POST['tiers'] ?? [];
        foreach ($tiersPost as $id => $tierData) {
            $minVal = (int)$tierData['min'];
            $maxVal = (int)$tierData['max'];
            $colorVal = $tierData['color'];

            Capsule::table('mod_chs_tiers')
                ->where('id', $id)
                ->where('profile_id', $profileId)
                ->update([
                    'min_score'   => $minVal,
                    'max_score'   => $maxVal,
                    'badge_color' => $colorVal,
                ]);
        }

        // 4. Save Score Bands for this profile
        $bandsPost = $_POST['bands'] ?? [];
        foreach ($bandsPost as $id => $bandData) {
            $minVal = (int)$bandData['min'];
            $maxVal = (int)$bandData['max'];
            $colorVal = $bandData['color'];

            Capsule::table('mod_chs_score_bands')
                ->where('id', $id)
                ->where('profile_id', $profileId)
                ->update([
                    'min_score'   => $minVal,
                    'max_score'   => $maxVal,
                    'badge_color' => $colorVal,
                ]);
        }

        // 5. Save Global Settings
        $postedGlobalSettings = $_POST['global_settings'] ?? [];
        foreach ($postedGlobalSettings as $key => $value) {
            $oldSettingValue = Capsule::table('mod_chs_settings')
                ->where('key', $key)
                ->value('value');
            
            if ($oldSettingValue !== $value) {
                Capsule::table('mod_chs_settings')
                    ->updateOrInsert(
                        ['key' => $key],
                        ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
                    );

                $this->logAudit(
                    'update_setting',
                    "Updated setting {$key} to " . ($value === '' ? '(empty)' : $value)
                );
            }
        }

        $this->logAudit('update_profile_settings', "Updated scoring profile ID: {$profileId} settings");
        return "Scoring profile settings saved successfully.";
    }

    /**
     * Render the reports page.
     */
    private function reportsPage(array $vars): string
    {
        $bands = [];
        try {
            $bandsDb = Capsule::table('mod_chs_score_bands')
                ->where('profile_id', 1)
                ->orderBy('min_score', 'desc')
                ->get();
            foreach ($bandsDb as $b) {
                $arr = (array)$b;
                $arr['slug'] = strtolower(str_replace([' ', '-'], '_', $b->name));
                $bands[] = $arr;
            }
        } catch (\Exception $e) {}

        // Find thresholds dynamically
        $healthyMin = 80;
        if (!empty($bands)) {
            $healthyMin = (int)$bands[0]['min_score'];
        }

        $watchMin = 60;
        foreach ($bands as $b) {
            if ($b['slug'] === 'watch') {
                $watchMin = (int)$b['min_score'];
                break;
            }
        }

        $pageChurn = max(1, (int)($_REQUEST['page_churn'] ?? 1));
        $pageVip = max(1, (int)($_REQUEST['page_vip'] ?? 1));
        $limit = 10;

        $offsetChurn = ($pageChurn - 1) * $limit;
        $offsetVip = ($pageVip - 1) * $limit;

        $now = date('Y-m-d');

        // VIP list: calculated score >= healthyMin AND not overridden to something else, OR overridden to Healthy
        $vipCount = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->leftJoin('mod_chs_manual_overrides as mo', function($join) use ($now) {
                $join->on('tblclients.id', '=', 'mo.client_id')
                     ->where(function($q) use ($now) {
                         $q->whereNull('mo.expiry_date')
                           ->orWhere('mo.expiry_date', '>=', $now);
                     });
            })
            ->where('tblclients.status', '!=', 'Closed')
            ->where(function($q) use ($healthyMin) {
                $q->where(function($q1) use ($healthyMin) {
                    $q1->whereNull('mo.id')
                       ->where('mod_chs_scores.score', '>=', $healthyMin);
                })->orWhere('mo.tier', '=', 'Healthy');
            })
            ->count();

        // Churn Risk list: calculated score < watchMin AND not overridden to something else, OR overridden to At-Risk or Critical
        $churnCount = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->leftJoin('mod_chs_manual_overrides as mo', function($join) use ($now) {
                $join->on('tblclients.id', '=', 'mo.client_id')
                     ->where(function($q) use ($now) {
                         $q->whereNull('mo.expiry_date')
                           ->orWhere('mo.expiry_date', '>=', $now);
                     });
            })
            ->where('tblclients.status', '!=', 'Closed')
            ->where(function($q) use ($watchMin) {
                $q->where(function($q1) use ($watchMin) {
                    $q1->whereNull('mo.id')
                       ->where('mod_chs_scores.score', '<', $watchMin);
                })->orWhereIn('mo.tier', ['At-Risk', 'Critical']);
            })
            ->count();

        $totalPagesChurn = (int)ceil($churnCount / $limit);
        $totalPagesChurn = max(1, $totalPagesChurn);

        $totalPagesVip = (int)ceil($vipCount / $limit);
        $totalPagesVip = max(1, $totalPagesVip);

        // 1. VIP Clients list (score >= healthyMin OR overridden to Healthy)
        $vipClientsRaw = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->leftJoin('mod_chs_manual_overrides as mo', function($join) use ($now) {
                $join->on('tblclients.id', '=', 'mo.client_id')
                     ->where(function($q) use ($now) {
                         $q->whereNull('mo.expiry_date')
                           ->orWhere('mo.expiry_date', '>=', $now);
                     });
            })
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'mod_chs_scores.score',
                'mod_chs_scores.trend',
            ])
            ->where('tblclients.status', '!=', 'Closed')
            ->where(function($q) use ($healthyMin) {
                $q->where(function($q1) use ($healthyMin) {
                    $q1->whereNull('mo.id')
                       ->where('mod_chs_scores.score', '>=', $healthyMin);
                })->orWhere('mo.tier', '=', 'Healthy');
            })
            ->orderBy('mod_chs_scores.score', 'desc')
            ->offset($offsetVip)
            ->limit($limit)
            ->get()
            ->toArray();

        // 2. High Churn Risk Clients list (score < watchMin OR overridden to At-Risk/Critical)
        $churnRisksRaw = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->leftJoin('mod_chs_manual_overrides as mo', function($join) use ($now) {
                $join->on('tblclients.id', '=', 'mo.client_id')
                     ->where(function($q) use ($now) {
                         $q->whereNull('mo.expiry_date')
                           ->orWhere('mo.expiry_date', '>=', $now);
                     });
            })
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'mod_chs_scores.score',
                'mod_chs_scores.trend',
            ])
            ->where('tblclients.status', '!=', 'Closed')
            ->where(function($q) use ($watchMin) {
                $q->where(function($q1) use ($watchMin) {
                    $q1->whereNull('mo.id')
                       ->where('mod_chs_scores.score', '<', $watchMin);
                })->orWhereIn('mo.tier', ['At-Risk', 'Critical']);
            })
            ->orderBy('mod_chs_scores.score', 'asc')
            ->offset($offsetChurn)
            ->limit($limit)
            ->get()
            ->toArray();

        // Helper to resolve band name and color for scores
        $resolveBandInfo = function($score) use ($bands) {
            foreach ($bands as $b) {
                if ($score >= $b['min_score'] && $score <= $b['max_score']) {
                    return [
                        'name' => $b['name'],
                        'color' => $b['badge_color']
                    ];
                }
            }
            return [
                'name' => 'Unevaluated',
                'color' => '#6b7280'
            ];
        };

        $now = date('Y-m-d');
        $overrides = Capsule::table('mod_chs_manual_overrides')
            ->where(function($q) use ($now) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $now);
            })
            ->get()
            ->keyBy('client_id')
            ->toArray();

        $vipClients = array_map(function ($item) use ($resolveBandInfo, $overrides, $bands) {
            $arr = (array)$item;
            $info = $resolveBandInfo($arr['score']);
            $arr['score_color'] = $info['color'];
            $clientId = (int)$arr['id'];
            if (isset($overrides[$clientId])) {
                $ov = $overrides[$clientId];
                $arr['is_overridden'] = true;
                $arr['override_tier'] = $ov->tier;
                
                // Override score color color to match pinned tier
                foreach ($bands as $b) {
                    if (strcasecmp($b['name'], $ov->tier) === 0) {
                        $arr['score_color'] = $b['badge_color'];
                        break;
                    }
                }
            } else {
                $arr['is_overridden'] = false;
            }
            return $arr;
        }, $vipClientsRaw);

        $churnRisks = array_map(function ($item) use ($resolveBandInfo, $overrides, $bands) {
            $arr = (array)$item;
            $info = $resolveBandInfo($arr['score']);
            $arr['score_color'] = $info['color'];
            $clientId = (int)$arr['id'];
            if (isset($overrides[$clientId])) {
                $ov = $overrides[$clientId];
                $arr['is_overridden'] = true;
                $arr['override_tier'] = $ov->tier;
                
                // Override score color color to match pinned tier
                foreach ($bands as $b) {
                    if (strcasecmp($b['name'], $ov->tier) === 0) {
                        $arr['score_color'] = $b['badge_color'];
                        break;
                    }
                }
            } else {
                $arr['is_overridden'] = false;
            }
            return $arr;
        }, $churnRisksRaw);

        // 3. Compute MRR by Tier dynamically
        $mrrByTier = [];
        foreach ($bands as $b) {
            $mrrByTier[$b['slug']] = [
                'name' => $b['name'],
                'min' => $b['min_score'],
                'max' => $b['max_score'],
                'color_class' => ($b['slug'] === 'healthy' ? 'text-success' : ($b['slug'] === 'watch' ? 'text-warning' : 'text-danger')),
                'amount' => 0.0,
            ];
        }
        
        $allActiveClientsWithScores = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select('tblclients.id', 'mod_chs_scores.score')
            ->where('tblclients.status', 'Active')
            ->get()
            ->toArray();
            
        foreach ($allActiveClientsWithScores as $c) {
            $c = (array)$c;
            $clientId = $c['id'];
            $score = $c['score'];
            
            $mrrH = Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->where('domainstatus', 'Active')
                ->selectRaw("SUM(
                    CASE 
                        WHEN billingcycle = 'Monthly' THEN amount
                        WHEN billingcycle = 'Quarterly' THEN amount / 3
                        WHEN billingcycle = 'Semi-Annually' THEN amount / 6
                        WHEN billingcycle = 'Annually' THEN amount / 12
                        WHEN billingcycle = 'Biennially' THEN amount / 24
                        WHEN billingcycle = 'Triennially' THEN amount / 36
                        ELSE 0
                    END
                ) as mrr")
                ->value('mrr') ?: 0.00;
                
            $mrrD = Capsule::table('tbldomains')
                ->where('userid', $clientId)
                ->where('status', 'Active')
                ->sum(Capsule::raw('recurringamount / registrationperiod / 12')) ?: 0.00;
                
            $clientMrr = (float)($mrrH + $mrrD);
            
            $targetSlug = '';
            if (isset($overrides[$clientId])) {
                $targetSlug = strtolower(str_replace([' ', '-'], '_', $overrides[$clientId]->tier));
            } else {
                foreach ($bands as $b) {
                    if ($score >= $b['min_score'] && $score <= $b['max_score']) {
                        $targetSlug = $b['slug'];
                        break;
                    }
                }
            }

            if ($targetSlug !== '' && isset($mrrByTier[$targetSlug])) {
                $mrrByTier[$targetSlug]['amount'] += $clientMrr;
            }
        }

        // 4. Risk Movement Stats
        $movementStats = Capsule::table('mod_chs_scores')
            ->selectRaw('
                COUNT(CASE WHEN score > prev_score THEN 1 END) as up_count,
                COUNT(CASE WHEN score < prev_score THEN 1 END) as down_count,
                COUNT(CASE WHEN score = prev_score THEN 1 END) as stable_count
            ')
            ->first();
        $movementStats = (array)$movementStats;

        // 5. Root Cause (deductions summary)
        $deductions = [];
        $scoresWithBreakdown = Capsule::table('mod_chs_scores')
            ->whereNotNull('breakdown')
            ->pluck('breakdown');
            
        foreach ($scoresWithBreakdown as $bJson) {
            $b = json_decode($bJson, true);
            if (empty($b)) continue;
            foreach ($b as $key => $metric) {
                if ($key === 'risk_drivers') continue;
                $points = (float)($metric['points'] ?? 0.0);
                if ($points < 0) {
                    $deductions[$key] = ($deductions[$key] ?? 0.0) + abs($points);
                }
            }
        }
        
        arsort($deductions);
        $rootCauses = [];
        foreach ($deductions as $key => $totalDeduction) {
            $rootCauses[] = [
                'metric_key' => $key,
                'name' => str_replace('_', ' ', $key),
                'total_deduction' => round($totalDeduction, 1)
            ];
        }

        // 6. Score Trend History
        $trendHistory = Capsule::table('mod_chs_snapshots')
            ->selectRaw('date, AVG(score) as avg_score')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->limit(30)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        return self::renderTemplate('reports', [
            'moduleLink'         => $vars['modulelink'],
            'vipClients'         => $vipClients,
            'churnRisks'         => $churnRisks,
            'mrrByTier'          => $mrrByTier,
            'movementStats'      => $movementStats,
            'rootCauses'         => $rootCauses,
            'trendHistory'       => $trendHistory,
            'healthyMin'         => $healthyMin,
            'watchMin'           => $watchMin,
            'pageChurn'          => $pageChurn,
            'totalPagesChurn'    => $totalPagesChurn,
            'totalChurn'         => $churnCount,
            'pageVip'            => $pageVip,
            'totalPagesVip'      => $totalPagesVip,
            'totalVip'           => $vipCount,
        ]);
    }

    /**
     * Render the audit log page.
     */
    private function auditPage(array $vars): string
    {
        $page = (int)($_REQUEST['page'] ?? 1);
        $page = max(1, $page);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Fetch audit logs count
        $totalAudits = Capsule::table('mod_chs_audit_logs')->count();
        $totalPages = (int)ceil($totalAudits / $limit);
        $totalPages = max(1, $totalPages);

        // Fetch audit logs
        $audits = Capsule::table('mod_chs_audit_logs')
            ->leftJoin('tblclients', 'mod_chs_audit_logs.client_id', '=', 'tblclients.id')
            ->select([
                'mod_chs_audit_logs.*',
                'tblclients.firstname',
                'tblclients.lastname',
            ])
            ->orderBy('mod_chs_audit_logs.id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        // Fetch recalculations history
        $recalculations = Capsule::table('mod_chs_recalculations')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        return self::renderTemplate('audit-log', [
            'moduleLink'     => $vars['modulelink'],
            'audits'         => $audits,
            'recalculations' => $recalculations,
            'page'           => $page,
            'totalPages'     => $totalPages,
            'total'          => $totalAudits,
        ]);
    }

    /**
     * Run AJAX batch recalculation chunk.
     */
    private function ajaxRecalculate(): string
    {
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-Type: application/json');

        try {
            $offset = (int)($_REQUEST['offset'] ?? 0);
            $limit = 100;

            $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();
            $clientIds = $clientController->getClientIdsPaginated($offset, $limit);
            if (empty($clientIds)) {
                echo json_encode([
                    'success'   => true,
                    'done'      => true,
                    'processed' => 0,
                    'total'     => 0,
                ]);
                exit;
            }

            // If offset is 0, initialize the recalculation log
            $adminId = (int)($_SESSION['adminid'] ?? 0);
            $username = 'system';
            if ($adminId > 0) {
                $username = Capsule::table('tbladmins')
                    ->where('id', $adminId)
                    ->value('username') ?? 'admin_' . $adminId;
            }

            if ($offset === 0) {
                $stats = $clientController->getStats();
                $totalClients = $stats['total_clients'];

                $recalcId = Capsule::table('mod_chs_recalculations')->insertGetId([
                    'status'            => 'processing',
                    'total_clients'     => $totalClients,
                    'processed_clients' => 0,
                    'started_at'        => date('Y-m-d H:i:s'),
                    'triggered_by'      => $username,
                ]);
                $_SESSION['chs_recalc_id'] = $recalcId;
            }

            // Recalculate
            $clientController->calculateBatch($clientIds);

            // Total
            $stats = $clientController->getStats();
            $totalClients = $stats['total_clients'];

            $processedSoFar = $offset + count($clientIds);
            $done = $processedSoFar >= $totalClients;

            // Update recalculation progress
            $recalcId = (int)($_SESSION['chs_recalc_id'] ?? 0);
            if ($recalcId > 0) {
                $updateData = [
                    'processed_clients' => min($processedSoFar, $totalClients),
                ];
                if ($done) {
                    $updateData['status'] = 'completed';
                    $updateData['completed_at'] = date('Y-m-d H:i:s');
                    unset($_SESSION['chs_recalc_id']);

                    $this->logAudit(
                        'manual_recalculation',
                        "Completed manual recalculation for {$processedSoFar} clients.",
                        'info'
                    );
                }

                Capsule::table('mod_chs_recalculations')
                    ->where('id', $recalcId)
                    ->update($updateData);
            }

            echo json_encode([
                'success'     => true,
                'done'        => $done,
                'processed'   => count($clientIds),
                'next_offset' => $processedSoFar,
                'total'       => $totalClients,
            ]);
            exit;
        } catch (\Exception $e) {
            $recalcId = (int)($_SESSION['chs_recalc_id'] ?? 0);
            if ($recalcId > 0) {
                Capsule::table('mod_chs_recalculations')
                    ->where('id', $recalcId)
                    ->update([
                        'status'       => 'failed',
                        'completed_at' => date('Y-m-d H:i:s'),
                    ]);
                unset($_SESSION['chs_recalc_id']);
            }

            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
            exit;
        }
    }

    /**
     * Render a Smarty template file, injecting variables, and return the generated HTML.
     * Fallback to legacy PHP templates if needed.
     *
     * @param string $templateName Name of template file (without extension)
     * @param array $variables Associative array of variables to extract/assign
     * @return string Rendered HTML content
     */
    public static function renderTemplate(string $templateName, array $variables = []): string
    {
        $baseDir = dirname(dirname(__DIR__));
        $tplFile = $baseDir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $templateName . '.tpl';
        $phpFile = $baseDir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $templateName . '.php';

        // Check if new TPL file exists
        if (file_exists($tplFile)) {
            try {
                $smarty = new \Smarty();
                
                // Configure writeable WHMCS compile directories
                $compileDir = ROOTDIR . DIRECTORY_SEPARATOR . 'templates_c';
                if (is_writable($compileDir)) {
                    $smarty->setCompileDir($compileDir);
                    $smarty->setCacheDir($compileDir);
                }
                
                // Assign variables
                foreach ($variables as $key => $val) {
                    $smarty->assign($key, $val);
                }
                
                return $smarty->fetch($tplFile);
            } catch (\Exception $e) {
                return "<div class='alert alert-danger'>Smarty Render Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // Fallback to legacy PHP view template
        if (file_exists($phpFile)) {
            extract($variables);
            ob_start();
            include $phpFile;
            return ob_get_clean();
        }

        return "<div class='alert alert-danger'>Template file not found: " . htmlspecialchars($templateName) . "</div>";
    }

}
