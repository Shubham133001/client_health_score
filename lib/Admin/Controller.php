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
            $message = $this->saveSettings();
            $action = 'settings';
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
                return $this->settingsPage($vars, $message);
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
        return $this->settingsPage($vars);
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = $this->saveSettings();
        }
        return $this->settingsPage($vars, $message);
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

            // Resolve Tier
            $tierName = $clientController->getTierForScore($score, $profileId);
            if (!empty($filterTier) && strtolower($tierName) !== strtolower($filterTier)) {
                continue;
            }

            $item['resolved_profile_id'] = $profileId;
            $item['mrr'] = $mrr;
            $item['tier'] = $tierName;
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

        // Calculate MRR at Risk
        $mrrHosting = Capsule::table('tblhosting')
            ->where('domainstatus', 'Active')
            ->whereIn('userid', function ($q) {
                $q->select('client_id')->from('mod_chs_scores')->where('score', '<', 60);
            })
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
            ->whereIn('userid', function ($q) {
                $q->select('client_id')->from('mod_chs_scores')->where('score', '<', 60);
            })
            ->sum(Capsule::raw('recurringamount / registrationperiod / 12')) ?: 0.00;

        $stats['mrr_at_risk'] = round($mrrHosting + $mrrDomains, 2);

        // Fetch distributions for charts
        $stats['tiers_dist'] = (array)Capsule::table('mod_chs_scores')
            ->selectRaw('
                SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) as platinum,
                SUM(CASE WHEN score >= 60 AND score < 80 THEN 1 ELSE 0 END) as gold,
                SUM(CASE WHEN score >= 35 AND score < 60 THEN 1 ELSE 0 END) as silver,
                SUM(CASE WHEN score < 35 THEN 1 ELSE 0 END) as standard
            ')
            ->first();

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

        // Fetch current score
        $scoreRecord = $clientController->getScoreForClient($clientId);

        // If no score exists yet, trigger an on-demand calculation
        if (!$scoreRecord) {
            $clientController->calculateForClient($clientId);
            $scoreRecord = $clientController->getScoreForClient($clientId);
        }

        // Resolve tier name and color
        $tiers = Capsule::table('mod_chs_tiers')
            ->orderBy('min_score', 'desc')
            ->get()
            ->toArray();

        $tierName = 'Unevaluated';
        $tierColor = '#6b7280';
        if ($scoreRecord && isset($scoreRecord['score'])) {
            foreach ($tiers as $t) {
                if ($scoreRecord['score'] >= $t->min_score && $scoreRecord['score'] <= $t->max_score) {
                    $tierName = $t->name;
                    $tierColor = $t->badge_color;
                    break;
                }
            }
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

        return self::renderTemplate('client', [
            'moduleLink'      => $vars['modulelink'],
            'client'          => $client,
            'scoreRecord'     => $scoreRecord,
            'breakdown'       => $breakdown,
            'history'         => $history,
            'alerts'          => $alerts,
            'unpaidInvoices'  => $unpaidInvoices,
            'overdueInvoices' => $overdueInvoices,
            'openTickets'     => $openTickets,
            'activeServices'  => $activeServices,
            'success'         => isset($_REQUEST['success']),
            'tierName'        => $tierName,
            'tierColor'       => $tierColor,
        ]);
    }

    /**
     * Render the Settings/Configuration Page.
     */
    private function settingsPage(array $vars, string $message = ''): string
    {
        $rules = Capsule::table('mod_chs_profile_rules')
            ->where('profile_id', 1)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        $tiers = Capsule::table('mod_chs_tiers')
            ->orderBy('min_score', 'desc')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
        $bands = Capsule::table('mod_chs_score_bands')
            ->orderBy('min_score', 'desc')
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

        return self::renderTemplate('settings', [
            'moduleLink' => $vars['modulelink'],
            'rules'      => $rules,
            'tiers'      => $tiers,
            'bands'      => $bands,
            'settings'   => $settings,
            'message'    => $message,
        ]);
    }

    /**
     * Write an audit log entry.
     */
    private function logAudit(string $action, string $description, string $level = 'info')
    {
        $adminId = (int)($_SESSION['adminid'] ?? 0);
        $username = 'system';
        if ($adminId > 0) {
            $username = Capsule::table('tbladmins')
                ->where('id', $adminId)
                ->value('username') ?? 'admin_' . $adminId;
        }

        Capsule::table('mod_chs_audit_logs')->insert([
            'client_id'    => null,
            'action'       => $action,
            'level'        => $level,
            'description'  => $description,
            'performed_by' => $username,
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Save settings, rules, tiers, and bands configuration.
     */
    private function saveSettings(): string
    {
        // 1. Save rules weights & statuses
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

            // Fetch old rule details for audit logging
            $oldRule = Capsule::table('mod_chs_profile_rules')
                ->where('profile_id', 1)
                ->where('metric_key', $key)
                ->first();

            $hasChanged = !$oldRule 
                || (float)$oldRule->weight !== $weightVal 
                || (int)$oldRule->is_enabled !== $isEnabled 
                || $oldRule->config !== json_encode($config);

            if ($hasChanged) {
                Capsule::table('mod_chs_profile_rules')
                    ->where('profile_id', 1)
                    ->where('metric_key', $key)
                    ->update([
                        'weight'     => $weightVal,
                        'is_enabled' => $isEnabled,
                        'config'     => json_encode($config),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                $oldW = $oldRule ? $oldRule->weight : 0;
                $oldE = $oldRule ? $oldRule->is_enabled : 0;
                $this->logAudit(
                    'update_scoring_rule',
                    "Updated rule {$key}: weight {$oldW} -> {$weightVal}, status {$oldE} -> {$isEnabled}"
                );
            }
        }

        // 2. Save Tiers
        $tiersPost = $_POST['tiers'] ?? [];
        foreach ($tiersPost as $id => $tierData) {
            $oldTier = Capsule::table('mod_chs_tiers')->where('id', $id)->first();
            $minVal = (int)$tierData['min'];
            $maxVal = (int)$tierData['max'];
            $colorVal = $tierData['color'];

            $hasChanged = !$oldTier 
                || (int)$oldTier->min_score !== $minVal 
                || (int)$oldTier->max_score !== $maxVal 
                || $oldTier->badge_color !== $colorVal;

            if ($hasChanged) {
                Capsule::table('mod_chs_tiers')
                    ->where('id', $id)
                    ->update([
                        'min_score'   => $minVal,
                        'max_score'   => $maxVal,
                        'badge_color' => $colorVal,
                    ]);

                $tierName = $oldTier ? $oldTier->name : 'Tier #' . $id;
                $this->logAudit(
                    'update_tier_threshold',
                    "Updated tier {$tierName}: min {$oldTier->min_score} -> {$minVal}, max {$oldTier->max_score} -> {$maxVal}"
                );
            }
        }

        // 3. Save Score Bands
        $bandsPost = $_POST['bands'] ?? [];
        foreach ($bandsPost as $id => $bandData) {
            $oldBand = Capsule::table('mod_chs_score_bands')->where('id', $id)->first();
            $minVal = (int)$bandData['min'];
            $maxVal = (int)$bandData['max'];
            $colorVal = $bandData['color'];

            $hasChanged = !$oldBand 
                || (int)$oldBand->min_score !== $minVal 
                || (int)$oldBand->max_score !== $maxVal 
                || $oldBand->badge_color !== $colorVal;

            if ($hasChanged) {
                Capsule::table('mod_chs_score_bands')
                    ->where('id', $id)
                    ->update([
                        'min_score'   => $minVal,
                        'max_score'   => $maxVal,
                        'badge_color' => $colorVal,
                    ]);

                $bandName = $oldBand ? $oldBand->name : 'Band #' . $id;
                $this->logAudit(
                    'update_health_band',
                    "Updated health band {$bandName}: min {$oldBand->min_score} -> {$minVal}, max {$oldBand->max_score} -> {$maxVal}"
                );
            }
        }

        // 4. Save General, Alert, Webhook & Digest Settings
        $postedSettings = $_POST['settings'] ?? [];
        foreach ($postedSettings as $key => $value) {
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

        return "Settings configuration updated successfully.";
    }

    /**
     * Render the reports page.
     */
    private function reportsPage(array $vars): string
    {
        // 1. VIP Clients list (score >= 80)
        $vipClients = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'mod_chs_scores.score',
                'mod_chs_scores.trend',
            ])
            ->where('tblclients.status', '!=', 'Closed')
            ->where('mod_chs_scores.score', '>=', 80)
            ->orderBy('mod_chs_scores.score', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        // 2. High Churn Risk Clients list (score < 50)
        $churnRisks = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'mod_chs_scores.score',
                'mod_chs_scores.trend',
            ])
            ->where('tblclients.status', '!=', 'Closed')
            ->where('mod_chs_scores.score', '<', 50)
            ->orderBy('mod_chs_scores.score', 'asc')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        // 3. Compute MRR by Tier
        $mrrByTier = [
            'healthy'  => 0.0,
            'warning'  => 0.0,
            'critical' => 0.0,
        ];
        
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
            
            if ($score >= 80) {
                $mrrByTier['healthy'] += $clientMrr;
            } elseif ($score >= 50) {
                $mrrByTier['warning'] += $clientMrr;
            } else {
                $mrrByTier['critical'] += $clientMrr;
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
        ]);
    }

    /**
     * Render the audit log page.
     */
    private function auditPage(array $vars): string
    {
        // Fetch audit logs
        $audits = Capsule::table('mod_chs_audit_logs')
            ->leftJoin('tblclients', 'mod_chs_audit_logs.client_id', '=', 'tblclients.id')
            ->select([
                'mod_chs_audit_logs.*',
                'tblclients.firstname',
                'tblclients.lastname',
            ])
            ->orderBy('mod_chs_audit_logs.id', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        // Fetch recalculations history
        $recalculations = Capsule::table('mod_chs_recalculations')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();

        return self::renderTemplate('audit-log', [
            'moduleLink'     => $vars['modulelink'],
            'audits'         => $audits,
            'recalculations' => $recalculations,
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
