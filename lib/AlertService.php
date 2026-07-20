<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class AlertService
{
    /**
     * Get settings value.
     */
    private function getSetting(string $key, $default = null)
    {
        $val = Capsule::table('mod_chs_settings')
            ->where('key', $key)
            ->value('value');
        return $val !== null ? $val : $default;
    }

    /**
     * Compare scores and trigger alerts if needed.
     */
    public function checkAndSendAlerts(int $clientId, $currentResult, $previousResult = null)
    {
        $score = 100;
        if (is_array($currentResult)) {
            $score = (int)($currentResult['display_score'] ?? $currentResult['score'] ?? 100);
        } else {
            $score = (int)$currentResult;
        }

        $prevScore = 100;
        if (is_array($previousResult)) {
            $prevScore = (int)($previousResult['display_score'] ?? $previousResult['score'] ?? 100);
        } elseif ($previousResult !== null) {
            $prevScore = (int)$previousResult;
        } else {
            $prevScore = (int)Capsule::table('mod_chs_scores')
                ->where('client_id', $clientId)
                ->value('score') ?? $score;
        }

        $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();
        $profileId = $clientController->resolveProfileIdForClient($clientId);

        $prevTier = $clientController->getTierForScore($prevScore, $profileId);
        $currTier = $clientController->getTierForScore($score, $profileId);

        $tierSeverities = [];
        try {
            $tiersDb = Capsule::table('mod_chs_tiers')
                ->where('profile_id', $profileId)
                ->orderBy('min_score', 'desc')
                ->get();
            if ($tiersDb->isEmpty() && $profileId !== 1) {
                $tiersDb = Capsule::table('mod_chs_tiers')
                    ->where('profile_id', 1)
                    ->orderBy('min_score', 'desc')
                    ->get();
            }
            foreach ($tiersDb as $index => $t) {
                $key = strtolower(str_replace([' ', '-'], '_', $t->name));
                $tierSeverities[$key] = $index;
            }
        } catch (\Exception $e) {}

        if (empty($tierSeverities)) {
            $tierSeverities = [
                'healthy'  => 0,
                'watch'    => 1,
                'at-risk'  => 2,
                'at_risk'  => 2,
                'critical' => 3
            ];
        }

        $prevSeverity = $tierSeverities[strtolower(str_replace(' ', '_', $prevTier))] ?? 0;
        $currSeverity = $tierSeverities[strtolower(str_replace(' ', '_', $currTier))] ?? 0;

        // 1. Tier Downgrade Alert
        $enableTierAlerts = (int)$this->getSetting('alert_enable_tier', 1);
        if ($enableTierAlerts && ($currSeverity > $prevSeverity)) {
            $clientName = Capsule::table('tblclients')
                ->where('id', $clientId)
                ->selectRaw("CONCAT(firstname, ' ', lastname) as name")
                ->value('name') ?? 'Client #' . $clientId;
            
            $payload = [
                'event' => 'client_health_tier_downgraded',
                'client_id' => $clientId,
                'client_name' => $clientName,
                'previous_score' => $prevScore,
                'current_score' => $score,
                'previous_tier' => $prevTier,
                'current_tier' => $currTier,
                'delta' => $score - $prevScore,
                'mrr' => $clientController->getClientMRR($clientId),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->sendTierDowngradeAlert($clientId, $payload);
        }

        // 2. Sudden Drop Alert
        $enableSuddenAlerts = (int)$this->getSetting('alert_enable_sudden', 1);
        $delta = $score - $prevScore;
        if ($enableSuddenAlerts && ($delta <= -20)) {
            $clientName = Capsule::table('tblclients')
                ->where('id', $clientId)
                ->selectRaw("CONCAT(firstname, ' ', lastname) as name")
                ->value('name') ?? 'Client #' . $clientId;

            $payload = [
                'event' => 'client_health_score_dropped',
                'client_id' => $clientId,
                'client_name' => $clientName,
                'previous_score' => $prevScore,
                'current_score' => $score,
                'previous_tier' => $prevTier,
                'current_tier' => $currTier,
                'delta' => $delta,
                'mrr' => $clientController->getClientMRR($clientId),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->sendSuddenDropAlert($clientId, $payload);
        }
    }

    public function sendTierDowngradeAlert(int $clientId, array $payload)
    {
        $message = "Client Health Alert: {$payload['client_name']} downgraded from {$payload['previous_tier']} (score {$payload['previous_score']}) to {$payload['current_tier']} (score {$payload['current_score']}).";
        $this->triggerAlert($clientId, 'tier_downgrade', $message, 'warning', $payload);
    }

    public function sendSuddenDropAlert(int $clientId, array $payload)
    {
        $message = "Client Health Alert: {$payload['client_name']} score dropped from {$payload['previous_score']} to {$payload['current_score']} (Delta: {$payload['delta']}).";
        $this->triggerAlert($clientId, 'sudden_drop', $message, 'danger', $payload);
    }

    private function triggerAlert(int $clientId, string $type, string $message, string $severity, array $payload)
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
            'notes'        => 'Auto-triggered by Alert Service.',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Send internal WHMCS admin notification
        $this->sendAdminNotification($type, $message);

        // Send Email Alert
        $this->sendEmailAlert($clientId, $type, $message, $payload);

        // Send Webhooks
        $this->dispatchWebhooks($clientId, $type, $message, $severity, $payload);
    }

    private function sendAdminNotification(string $type, string $message)
    {
        try {
            localAPI('TriggerNotificationEvent', [
                'notification_identifier' => 'client_health.' . $type,
                'title' => 'Client Health Alert',
                'message' => $message,
                'status' => 'Attention',
                'statusStyle' => 'warning',
            ]);
        } catch (\Exception $e) {
            logActivity("Client Health Score: Failed to trigger admin notification event: " . $e->getMessage());
        }
    }

    private function sendEmailAlert(int $clientId, string $type, string $message, array $payload)
    {
        $recipients = $this->getSetting('digest_recipients');
        if (empty($recipients)) {
            $recipients = Capsule::table('tblconfiguration')->where('setting', 'Email')->value('value');
        }

        if (empty($recipients)) {
            return;
        }

        $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();
        $mrr = $clientController->getClientMRR($clientId);

        $scoreRecord = Capsule::table('mod_chs_scores')->where('client_id', $clientId)->first();
        $driverNames = [];
        if ($scoreRecord && $scoreRecord->breakdown) {
            $breakdown = json_decode($scoreRecord->breakdown, true);
            $drivers = $breakdown['risk_drivers'] ?? [];
            $driverNames = array_map(function($d) { return $d['name'] . ' (' . $d['points'] . ')'; }, $drivers);
        }

        $systemUrl = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value'), '/');
        $profileUrl = $systemUrl . '/admin/clientssummary.php?userid=' . $clientId;

        $subject = "Client Health Alert: " . $payload['client_name'] . " (" . strtoupper(str_replace('_', ' ', $type)) . ")";
        $body = "<div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;'>";
        $body .= "<h2>Client Health Alert Triggered</h2>";
        $body .= "<p><strong>Message:</strong> {$message}</p>";
        $body .= "<ul>";
        $body .= "  <li>Client Name: <strong>{$payload['client_name']}</strong> (ID: {$clientId})</li>";
        $body .= "  <li>Current Score: <strong>{$payload['current_score']}/100</strong> (Previous: {$payload['previous_score']}/100)</li>";
        $body .= "  <li>Current Tier: <strong>{$payload['current_tier']}</strong> (Previous: {$payload['previous_tier']})</li>";
        $body .= "  <li>MRR: <strong>$" . number_format($mrr, 2) . "</strong></li>";
        $body .= "  <li>Main Risk Drivers: <strong>" . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "</strong></li>";
        $body .= "</ul>";
        $body .= "<p><a href='{$profileUrl}'>View Client Profile</a></p>";
        $body .= "</div>";

        $emails = array_map('trim', explode(',', $recipients));
        foreach ($emails as $email) {
            if (empty($email)) {
                continue;
            }
            localAPI('SendAdminEmail', [
                'customsubject' => $subject,
                'custommessage' => $body,
                'mergefields'   => [],
                'email'         => $email
            ]);
        }
    }

    private function dispatchWebhooks(int $clientId, string $type, string $message, string $severity, array $payload)
    {
        $scoreRecord = Capsule::table('mod_chs_scores')->where('client_id', $clientId)->first();
        $driverNames = [];
        if ($scoreRecord && $scoreRecord->breakdown) {
            $breakdown = json_decode($scoreRecord->breakdown, true);
            $drivers = $breakdown['risk_drivers'] ?? [];
            $driverNames = array_map(function($d) { return $d['name'] . ' (' . $d['points'] . ')'; }, $drivers);
        }

        $payloadData = [
            'event'          => $payload['event'],
            'client_id'      => $clientId,
            'client_name'    => $payload['client_name'],
            'previous_score' => $payload['previous_score'],
            'current_score'  => $payload['current_score'],
            'previous_tier'  => $payload['previous_tier'],
            'current_tier'   => $payload['current_tier'],
            'delta'          => $payload['delta'],
            'mrr'            => $payload['mrr'],
            'drivers'        => $driverNames,
            'created_at'     => date('c')
        ];

        $payloads = [
            'slack' => [
                'setting' => 'webhook_slack_url',
                'body' => json_encode([
                    'text' => "⚠️ *[Client Health Score Alert]*\n*Client:* {$payload['client_name']} (ID: {$clientId})\n*Score:* {$payload['previous_score']} ➔ {$payload['current_score']} (Delta: " . ($payload['delta'] > 0 ? "+{$payload['delta']}" : $payload['delta']) . ")\n*Tier:* {$payload['previous_tier']} ➔ {$payload['current_tier']}\n*MRR:* $" . number_format($payload['mrr'], 2) . "\n*Risk Drivers:* " . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "\n*Message:* {$message}"
                ]),
                'headers' => ['Content-Type: application/json']
            ],
            'discord' => [
                'setting' => 'webhook_discord_url',
                'body' => json_encode([
                    'content' => "⚠️ **[Client Health Score Alert]**\n**Client:** {$payload['client_name']} (ID: {$clientId})\n**Score:** {$payload['previous_score']} ➔ {$payload['current_score']} (Delta: " . ($payload['delta'] > 0 ? "+{$payload['delta']}" : $payload['delta']) . ")\n**Tier:** {$payload['previous_tier']} ➔ {$payload['current_tier']}\n**MRR:** $" . number_format($payload['mrr'], 2) . "\n**Risk Drivers:** " . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "\n**Message:** {$message}"
                ]),
                'headers' => ['Content-Type: application/json']
            ],
            'teams' => [
                'setting' => 'webhook_teams_url',
                'body' => json_encode([
                    'text' => "⚠️ **[Client Health Score Alert]**\n**Client:** {$payload['client_name']} (ID: {$clientId})\n**Score:** {$payload['previous_score']} ➔ {$payload['current_score']} (Delta: " . ($payload['delta'] > 0 ? "+{$payload['delta']}" : $payload['delta']) . ")\n**Tier:** {$payload['previous_tier']} ➔ {$payload['current_tier']}\n**MRR:** $" . number_format($payload['mrr'], 2) . "\n**Risk Drivers:** " . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "\n**Message:** {$message}",
                    'type' => 'message',
                    'attachments' => [
                        [
                            'contentType' => 'application/vnd.microsoft.card.adaptive',
                            'content' => [
                                'type' => 'AdaptiveCard',
                                'version' => '1.2',
                                'body' => [
                                    [
                                        'type' => 'TextBlock',
                                        'text' => "⚠️ **[Client Health Score Alert]**",
                                        'weight' => 'bolder',
                                        'size' => 'medium'
                                    ],
                                    [
                                        'type' => 'TextBlock',
                                        'text' => "**Client:** {$payload['client_name']} (ID: {$clientId})\n\n**Score:** {$payload['previous_score']} ➔ {$payload['current_score']} (Delta: " . ($payload['delta'] > 0 ? "+{$payload['delta']}" : $payload['delta']) . ")\n\n**Tier:** {$payload['previous_tier']} ➔ {$payload['current_tier']}\n\n**MRR:** $" . number_format($payload['mrr'], 2) . "\n\n**Risk Drivers:** " . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "\n\n**Message:** {$message}",
                                        'wrap' => true
                                    ]
                                ],
                                '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json'
                            ]
                        ]
                    ]
                ]),
                'headers' => ['Content-Type: application/json']
            ],
            'generic' => [
                'setting' => 'webhook_generic_url',
                'body' => json_encode($payloadData),
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
     * Send Weekly Digest Report immediately.
     */
    public function sendWeeklyDigest()
    {
        $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();
        
        $stats = $clientController->getStats();

        $downgrades = Capsule::table('mod_chs_scores')
            ->whereRaw('score < prev_score')
            ->count();

        $upgrades = Capsule::table('mod_chs_scores')
            ->whereRaw('score > prev_score')
            ->count();

        $biggestDrops = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'mod_chs_scores.score',
                'mod_chs_scores.prev_score',
                Capsule::raw('(mod_chs_scores.prev_score - mod_chs_scores.score) as delta')
            ])
            ->whereRaw('mod_chs_scores.score < mod_chs_scores.prev_score')
            ->orderBy('delta', 'desc')
            ->limit(5)
            ->get()
            ->map(function($item) { return (array)$item; })
            ->toArray();

        $biggestImprovements = Capsule::table('tblclients')
            ->join('mod_chs_scores', 'tblclients.id', '=', 'mod_chs_scores.client_id')
            ->select([
                'tblclients.id',
                'tblclients.firstname',
                'tblclients.lastname',
                'tblclients.companyname',
                'mod_chs_scores.score',
                'mod_chs_scores.prev_score',
                Capsule::raw('(mod_chs_scores.score - mod_chs_scores.prev_score) as delta')
            ])
            ->whereRaw('mod_chs_scores.score > mod_chs_scores.prev_score')
            ->orderBy('delta', 'desc')
            ->limit(5)
            ->get()
            ->map(function($item) { return (array)$item; })
            ->toArray();

        $atRisk = Capsule::select("
            SELECT c.id, c.firstname, c.lastname, c.companyname, s.score,
               ((SELECT COALESCE(SUM(
                  CASE WHEN billingcycle = 'Monthly' THEN amount
                       WHEN billingcycle = 'Quarterly' THEN amount / 3
                       WHEN billingcycle = 'Semi-Annually' THEN amount / 6
                       WHEN billingcycle = 'Annually' THEN amount / 12
                       WHEN billingcycle = 'Biennially' THEN amount / 24
                       WHEN billingcycle = 'Triennially' THEN amount / 36
                       ELSE 0 END
                ), 0) FROM tblhosting WHERE userid = c.id AND domainstatus = 'Active') +
               (SELECT COALESCE(SUM(recurringamount / registrationperiod / 12), 0) FROM tbldomains WHERE userid = c.id AND status = 'Active')) as mrr
            FROM tblclients c
            JOIN mod_chs_scores s ON c.id = s.client_id
            WHERE s.score >= 35 AND s.score < 60 AND c.status = 'Active'
            ORDER BY mrr DESC, s.score ASC
            LIMIT 5
        ");
        $atRiskList = array_map(function($item) { return (array)$item; }, $atRisk);

        $critical = Capsule::select("
            SELECT c.id, c.firstname, c.lastname, c.companyname, s.score,
               ((SELECT COALESCE(SUM(
                  CASE WHEN billingcycle = 'Monthly' THEN amount
                       WHEN billingcycle = 'Quarterly' THEN amount / 3
                       WHEN billingcycle = 'Semi-Annually' THEN amount / 6
                       WHEN billingcycle = 'Annually' THEN amount / 12
                       WHEN billingcycle = 'Biennially' THEN amount / 24
                       WHEN billingcycle = 'Triennially' THEN amount / 36
                       ELSE 0 END
                ), 0) FROM tblhosting WHERE userid = c.id AND domainstatus = 'Active') +
               (SELECT COALESCE(SUM(recurringamount / registrationperiod / 12), 0) FROM tbldomains WHERE userid = c.id AND status = 'Active')) as mrr
            FROM tblclients c
            JOIN mod_chs_scores s ON c.id = s.client_id
            WHERE s.score < 35 AND c.status = 'Active'
            ORDER BY mrr DESC, s.score ASC
            LIMIT 5
        ");
        $criticalList = array_map(function($item) { return (array)$item; }, $critical);

        $adminEmail = $this->getSetting('digest_recipients');
        if (empty($adminEmail)) {
            $adminEmail = Capsule::table('tblconfiguration')->where('setting', 'Email')->value('value');
        }

        if (empty($adminEmail)) {
            throw new \Exception("No admin recipient email configured.");
        }

        $subject = "WHMCS Client Health Weekly Digest - " . date('Y-m-d');
        
        $body = "<div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;'>";
        $body .= "<h2>Client Health Weekly Digest Report</h2>";
        $body .= "<p>Here is your weekly summary of client health scores, trend changes, and potential churn risks.</p>";
        
        $bandsCount = $stats['bands_count'] ?? [];
        $healthyCount = $bandsCount['healthy'] ?? 0;
        $watchCount = $bandsCount['watch'] ?? 0;
        $atRiskCount = $bandsCount['at_risk'] ?? 0;
        $criticalCount = $bandsCount['critical'] ?? 0;
        $unevaluatedCount = $stats['unevaluated'] ?? 0;

        $body .= "<h3>General Statistics</h3>";
        $body .= "<ul>";
        $body .= "  <li>Average Health Score: <strong>" . ($stats['average_score'] ?? 'N/A') . "/100</strong></li>";
        $body .= "  <li>Total Evaluated Clients: <strong>" . $stats['total_clients'] . "</strong></li>";
        $body .= "  <li>Healthy Clients (score >= 80): <strong style='color:#10b981;'>" . $healthyCount . "</strong></li>";
        $body .= "  <li>Watch Clients (score 60-79): <strong style='color:#f59e0b;'>" . $watchCount . "</strong></li>";
        $body .= "  <li>At-Risk Clients (score 35-59): <strong style='color:#f0ad4e;'>" . $atRiskCount . "</strong></li>";
        $body .= "  <li>Critical Clients (score < 35): <strong style='color:#ef4444;'>" . $criticalCount . "</strong></li>";
        if ($unevaluatedCount > 0) {
            $body .= "  <li>Unevaluated Clients: <strong>" . $unevaluatedCount . "</strong></li>";
        }
        $body .= "  <li>Newly Downgraded this week: <strong>{$downgrades}</strong></li>";
        $body .= "  <li>Newly Improved this week: <strong>{$upgrades}</strong></li>";
        $body .= "</ul>";

        $body .= "<h3>Top Critical Clients by MRR (Score &lt; 35)</h3>";
        if (empty($criticalList)) {
            $body .= "<p style='color:#10b981;'>No critical clients found.</p>";
        } else {
            $body .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px; width:100%;'>";
            $body .= "  <tr style='background-color:#f2f2f2;'><th>Client Name</th><th>Health Score</th><th>MRR</th></tr>";
            foreach ($criticalList as $c) {
                $name = $c['firstname'] . ' ' . $c['lastname'];
                if ($c['companyname']) { $name .= ' (' . $c['companyname'] . ')'; }
                $body .= "  <tr><td><strong>" . htmlspecialchars($name) . "</strong></td><td align='center'>" . $c['score'] . "</td><td align='right'>$" . number_format($c['mrr'], 2) . "</td></tr>";
            }
            $body .= "</table>";
        }

        $body .= "<h3>Top At-Risk Clients by MRR (Score 35-59)</h3>";
        if (empty($atRiskList)) {
            $body .= "<p style='color:#10b981;'>No at-risk clients found.</p>";
        } else {
            $body .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px; width:100%;'>";
            $body .= "  <tr style='background-color:#f2f2f2;'><th>Client Name</th><th>Health Score</th><th>MRR</th></tr>";
            foreach ($atRiskList as $c) {
                $name = $c['firstname'] . ' ' . $c['lastname'];
                if ($c['companyname']) { $name .= ' (' . $c['companyname'] . ')'; }
                $body .= "  <tr><td><strong>" . htmlspecialchars($name) . "</strong></td><td align='center'>" . $c['score'] . "</td><td align='right'>$" . number_format($c['mrr'], 2) . "</td></tr>";
            }
            $body .= "</table>";
        }

        $body .= "<h3>Biggest Score Drops</h3>";
        if (empty($biggestDrops)) {
            $body .= "<p>No client score drops recorded.</p>";
        } else {
            $body .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px; width:100%;'>";
            $body .= "  <tr style='background-color:#f2f2f2;'><th>Client Name</th><th>Previous Score</th><th>Current Score</th><th>Drop</th></tr>";
            foreach ($biggestDrops as $c) {
                $name = $c['firstname'] . ' ' . $c['lastname'];
                if ($c['companyname']) { $name .= ' (' . $c['companyname'] . ')'; }
                $body .= "  <tr><td>" . htmlspecialchars($name) . "</td><td align='center'>{$c['prev_score']}</td><td align='center'>{$c['score']}</td><td align='center' style='color:#ef4444; font-weight:bold;'>-{$c['delta']}</td></tr>";
            }
            $body .= "</table>";
        }

        $body .= "<h3>Biggest Score Improvements</h3>";
        if (empty($biggestImprovements)) {
            $body .= "<p>No client score improvements recorded.</p>";
        } else {
            $body .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px; width:100%;'>";
            $body .= "  <tr style='background-color:#f2f2f2;'><th>Client Name</th><th>Previous Score</th><th>Current Score</th><th>Improvement</th></tr>";
            foreach ($biggestImprovements as $c) {
                $name = $c['firstname'] . ' ' . $c['lastname'];
                if ($c['companyname']) { $name .= ' (' . $c['companyname'] . ')'; }
                $body .= "  <tr><td>" . htmlspecialchars($name) . "</td><td align='center'>{$c['prev_score']}</td><td align='center'>{$c['score']}</td><td align='center' style='color:#10b981; font-weight:bold;'>+{$c['delta']}</td></tr>";
            }
            $body .= "</table>";
        }

        $body .= "<p style='font-size:11px; color:#666; margin-top:20px;'>Generated by WHMCS Client Health Score Addon.</p>";
        $body .= "</div>";

        $emails = array_map('trim', explode(',', $adminEmail));
        $success = true;
        foreach ($emails as $email) {
            if (empty($email)) {
                continue;
            }
            $apiResult = localAPI('SendAdminEmail', [
                'customsubject' => $subject,
                'custommessage' => $body,
                'mergefields'   => [],
                'email'         => $email
            ]);
            if ($apiResult['result'] !== 'success') {
                $success = false;
                Capsule::table('mod_chs_audit_logs')->insert([
                    'action'       => 'weekly_digest_failure',
                    'level'        => 'error',
                    'description'  => "Failed to send weekly digest email to {$email}. API Response: " . json_encode($apiResult),
                    'performed_by' => 'system',
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        Capsule::table('mod_chs_weekly_digest_logs')->insert([
            'sent_at'    => date('Y-m-d H:i:s'),
            'recipients' => $adminEmail,
            'stats'      => json_encode($stats),
            'status'     => $success ? 'success' : 'failed',
        ]);
    }
}
