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

// Autoloader registration fallback for hooks environment
// spl_autoload_register(function ($class) {
//     $prefix = 'WHMCS\\Module\\Addon\\ClientHealthScore\\';
//     $baseDir = __DIR__ . '/lib/';
//     $len = strlen($prefix);
//     if (strncmp($prefix, $class, $len) !== 0) {
//         return;
//     }
//     $relativeClass = substr($class, $len);
//     $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
//     if (file_exists($file)) {
//         require_once $file;
//     }
// });

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

// --- 4. Service Status Hooks ---
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

// --- 5. Daily Cron Job Hook ---
add_hook('DailyCronJob', 1, function ($vars) {
    try {
        $clientController = new ClientController();
        // Process in batches of 200 to prevent memory pressure during cron execution
        $processedCount = $clientController->calculateAll(200);
        logActivity("Client Health Score Daily Cron completed. Recalculated scores for {$processedCount} clients.");
    } catch (\Exception $e) {
        logActivity("Client Health Score Daily Cron failed: " . $e->getMessage());
    }
});
