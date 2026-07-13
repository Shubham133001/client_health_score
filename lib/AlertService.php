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
    public function checkAndSendAlerts(int $clientId, int $score, int $prevScore)
    {
        $clientController = new \WHMCS\Module\Addon\ClientHealthScore\Client\Controller();
        $profileId = $clientController->resolveProfileIdForClient($clientId);

        $prevTier = $clientController->getTierForScore($prevScore, $profileId);
        $currTier = $clientController->getTierForScore($score, $profileId);

        $tierSeverities = [
            'healthy'  => 0,
            'watch'    => 1,
            'at-risk'  => 2,
            'at_risk'  => 2,
            'critical' => 3
        ];

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
                    'text' => "⚠️ **[Client Health Score Alert]**\n**Client:** {$payload['client_name']} (ID: {$clientId})\n**Score:** {$payload['previous_score']} ➔ {$payload['current_score']} (Delta: " . ($payload['delta'] > 0 ? "+{$payload['delta']}" : $payload['delta']) . ")\n**Tier:** {$payload['previous_tier']} ➔ {$payload['current_tier']}\n**MRR:** $" . number_format($payload['mrr'], 2) . "\n**Risk Drivers:** " . (empty($driverNames) ? 'None' : implode(', ', $driverNames)) . "\n**Message:** {$message}"
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
}
