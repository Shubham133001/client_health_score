<?php
/**
 * WHMCS Client Health Score Hook Registrations
 *
 * Captures lifecycle events (billing, tickets, services, and daily crons)
 * to automatically trigger health score recalculations.
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ClientHealthScore\Client\Controller as ClientController;
use WHMCS\Module\Addon\ClientHealthScore\Admin\Controller as AdminController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Trigger quick recalculation for a single client safely.
 *
 * @param int $clientId
 * @return void
 */
function client_health_score_trigger_recalc(int $clientId)
{
    if ($clientId <= 0) {
        return;
    }

    try {
        $clientController = new ClientController();
        $clientController->calculateForClient($clientId);
    } catch (\Exception $e) {
        logActivity("Client Health Score Recalculation failed for Client ID {$clientId}: " . $e->getMessage());
    }
}

// --- 1. Admin Area Client Summary Page Widget Hook ---
add_hook('AdminAreaClientSummaryPage', 1, function ($vars) {
    $clientId = (int)$vars['userid'];
    if ($clientId <= 0) {
        return '';
    }

    // Handle manual recalculation trigger
    if (isset($_REQUEST['chs_recalculate'])) {
        try {
            $clientController = new ClientController();
            $clientController->calculateForClient($clientId);
            header("Location: clientssummary.php?userid=" . $clientId . "&chs_success=1");
            exit;
        } catch (\Exception $e) {
            // Fall through
        }
    }

    try {
        $clientController = new ClientController();
        $scoreRecord = $clientController->getScoreForClient($clientId);

        // If no score exists yet, trigger a fast on-demand calculation
        if (!$scoreRecord) {
            $clientController->calculateForClient($clientId);
            $scoreRecord = $clientController->getScoreForClient($clientId);
        }

        $breakdown = [];
        if (!empty($scoreRecord['breakdown'])) {
            $breakdown = is_string($scoreRecord['breakdown']) 
                ? json_decode($scoreRecord['breakdown'], true) 
                : $scoreRecord['breakdown'];
        }

        return AdminController::renderTemplate('client_summary_widget', [
            'scoreRecord' => $scoreRecord,
            'breakdown'   => $breakdown,
            'success'     => isset($_REQUEST['chs_success']),
        ]);
    } catch (\Exception $e) {
        return "<div class='alert alert-danger'>Health Score Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
});

// --- 2. Invoice / Billing Hooks ---
add_hook('InvoicePaid', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    $userId = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('InvoicePaymentReceived', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    $userId = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('InvoiceCancelled', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    $userId = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('InvoicePaymentReminder', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    $userId = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('InvoiceRefunded', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    $userId = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('InvoiceCreation', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    $userId = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('InvoiceChangeGateway', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    $userId = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('InvoicePaymentFailed', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    $userId = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

// --- 3. Support Ticket Hooks ---
add_hook('TicketOpen', 1, function ($vars) {
    $ticketId = (int)$vars['ticketid'];
    $userId = Capsule::table('tbltickets')->where('id', $ticketId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('TicketClose', 1, function ($vars) {
    $ticketId = (int)$vars['ticketid'];
    $userId = Capsule::table('tbltickets')->where('id', $ticketId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

// --- 4. Service / Module Status Hooks ---
add_hook('ServiceCreate', 1, function ($vars) {
    $serviceId = (int)$vars['serviceid'];
    $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('ServiceEdit', 1, function ($vars) {
    $serviceId = (int)$vars['serviceid'];
    $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('ServiceDelete', 1, function ($vars) {
    $serviceId = (int)$vars['serviceid'];
    $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

add_hook('AfterModuleCreate', 1, function ($vars) {
    $serviceId = (int)($vars['params']['serviceid'] ?? 0);
    if ($serviceId > 0) {
        $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
        client_health_score_trigger_recalc((int)$userId);
    }
});

add_hook('AfterModuleSuspend', 1, function ($vars) {
    $serviceId = (int)($vars['params']['serviceid'] ?? 0);
    if ($serviceId > 0) {
        $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
        client_health_score_trigger_recalc((int)$userId);
    }
});

add_hook('AfterModuleUnsuspend', 1, function ($vars) {
    $serviceId = (int)($vars['params']['serviceid'] ?? 0);
    if ($serviceId > 0) {
        $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
        client_health_score_trigger_recalc((int)$userId);
    }
});

add_hook('PreModuleTerminate', 1, function ($vars) {
    $serviceId = (int)($vars['params']['serviceid'] ?? 0);
    if ($serviceId > 0) {
        $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
        client_health_score_trigger_recalc((int)$userId);
    }
});

add_hook('AfterModuleTerminate', 1, function ($vars) {
    $serviceId = (int)($vars['params']['serviceid'] ?? 0);
    if ($serviceId > 0) {
        $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
        client_health_score_trigger_recalc((int)$userId);
    }
});

add_hook('CancellationRequest', 1, function ($vars) {
    $serviceId = (int)$vars['serviceid'];
    $userId = Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
    client_health_score_trigger_recalc((int)$userId);
});

// --- 5. Client Hooks ---
add_hook('ClientAreaPage', 1, function ($vars) {
    $clientId = (int)($_SESSION['uid'] ?? 0);
    if ($clientId > 0) {
        client_health_score_trigger_recalc($clientId);
    }
});

add_hook('ClientLogin', 1, function ($vars) {
    $clientId = (int)$vars['userid'];
    client_health_score_trigger_recalc($clientId);
});

add_hook('ClientLogout', 1, function ($vars) {
    $clientId = (int)$vars['userid'];
    client_health_score_trigger_recalc($clientId);
});

add_hook('ClientEdit', 1, function ($vars) {
    $clientId = (int)$vars['userid'];
    client_health_score_trigger_recalc($clientId);
});

add_hook('ClientDelete', 1, function ($vars) {
    $clientId = (int)$vars['userid'];
    // DB cascades will delete score, but we trigger a recalc audit trail entry
    try {
        Capsule::table('mod_chs_audit_logs')->insert([
            'client_id'    => null,
            'action'       => 'client_deleted',
            'level'        => 'info',
            'description'  => "Client ID {$clientId} was deleted. Cascade dropped scores.",
            'performed_by' => 'system',
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    } catch (\Exception $e) {}
});

// --- 6. Admin Hooks ---
add_hook('AdminAreaHeadOutput', 1, function ($vars) {
    return '';
});

add_hook('AdminHomeWidgets', 1, function ($vars) {
    // Handled via WHMCS autoloading for abstract widgets in modules/widgets/
});

// --- 7. Daily Cron Job Hook ---
add_hook('DailyCronJob', 1, function ($vars) {
    try {
        $clientController = new ClientController();
        // Process in batches dynamically set in mod_chs_settings
        $batchSize = (int)Capsule::table('mod_chs_settings')->where('key', 'cron_batch_size')->value('value') ?: 200;
        $processedCount = $clientController->calculateAll($batchSize);
        logActivity("Client Health Score Daily Cron completed. Recalculated scores for {$processedCount} clients.");
    } catch (\Exception $e) {
        logActivity("Client Health Score Daily Cron failed: " . $e->getMessage());
    }
});
