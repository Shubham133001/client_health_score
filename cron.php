<?php
/**
 * CLI Cron execution entrypoint for Client Health Score calculations.
 *
 * Usage: php -q modules/addons/client_health_score/cron.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run via CLI.\n");
}

// Locate WHMCS root directory (traverse up 4 directories from addon path)
$whmcsRoot = dirname(dirname(dirname(__DIR__)));
$initFile = $whmcsRoot . DIRECTORY_SEPARATOR . 'init.php';

if (!file_exists($initFile)) {
    die("Error: WHMCS init.php not found at: {$initFile}\n");
}

require_once $initFile;

use WHMCS\Module\Addon\ClientHealthScore\Client\Controller as ClientController;
use WHMCS\Database\Capsule;

try {
    echo "Client Health Score Cron Job Started...\n";
    
    // Load batch size from settings
    $batchSize = (int)Capsule::table('mod_chs_settings')->where('key', 'cron_batch_size')->value('value') ?: 100;
    
    $clientController = new ClientController();
    $processedCount = $clientController->calculateAll($batchSize);
    
    echo "Successfully recalculated health scores for {$processedCount} clients.\n";

    // Prune audit logs to keep only the latest 20 records
    $auditLogsCount = Capsule::table('mod_chs_audit_logs')->count();
    if ($auditLogsCount > 20) {
        $cutoffId = Capsule::table('mod_chs_audit_logs')
            ->orderBy('id', 'desc')
            ->skip(19)
            ->value('id');
        if ($cutoffId) {
            Capsule::table('mod_chs_audit_logs')
                ->where('id', '<', $cutoffId)
                ->delete();
            echo "Pruned audit logs. Kept the latest 20 records.\n";
        }
    }

    // Prune batch recalculations history to keep only the latest 20 records
    $recalcCount = Capsule::table('mod_chs_recalculations')->count();
    if ($recalcCount > 20) {
        $cutoffId = Capsule::table('mod_chs_recalculations')
            ->orderBy('id', 'desc')
            ->skip(19)
            ->value('id');
        if ($cutoffId) {
            Capsule::table('mod_chs_recalculations')
                ->where('id', '<', $cutoffId)
                ->delete();
            echo "Pruned batch recalculations history. Kept the latest 20 records.\n";
        }
    }

    echo "Cron Job Finished.\n";
} catch (\Exception $e) {
    echo "Cron Execution Error: " . $e->getMessage() . "\n";
    logActivity("Client Health Score CLI Cron failed: " . $e->getMessage());
}
