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
    echo "Cron Job Finished.\n";
} catch (\Exception $e) {
    echo "Cron Execution Error: " . $e->getMessage() . "\n";
    logActivity("Client Health Score CLI Cron failed: " . $e->getMessage());
}
