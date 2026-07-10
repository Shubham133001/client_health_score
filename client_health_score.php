<?php
/**
 * WHMCS Client Health Score Addon Module
 *
 * This module aggregates billing, support, and service indicators to calculate
 * a health score (0-100) and identify churn risks and VIP customers.
 *
 * @copyright Copyright (c) 2026
 * @license MIT
 */

use WHMCS\Module\Addon\ClientHealthScore\Admin\AdminDispatcher;
use WHMCS\Module\Addon\ClientHealthScore\Client\ClientDispatcher;
use WHMCS\Module\Addon\ClientHealthScore\Admin\Controller as AdminController;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Resilient PSR-4 autoloader fallback to ensure compatibility across WHMCS setups
spl_autoload_register(function ($class) {
    $prefix = 'WHMCS\\Module\\Addon\\ClientHealthScore\\';
    $baseDir = __DIR__ . '/lib/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Addon module configuration parameters.
 *
 * @return array
 */
function client_health_score_config()
{
    return [
        'name'        => 'Client Health Score',
        'description' => 'Calculates a health score (0-100) for clients based on billing status, active services, tenure, and support history.',
        'author'      => 'Senior WHMCS Development Partner',
        'language'    => 'english',
        'version'     => '1.0.0',
        'fields'      => [] // Custom rules engine dashboard is used instead of standard config fields
    ];
}

/**
 * Activation hook - sets up database schemas and defaults.
 *
 * @return array
 */
function client_health_score_activate()
{
    try {
        $db = Capsule::connection();

        // Disable foreign keys check to drop cleanly
        $db->statement("SET FOREIGN_KEY_CHECKS = 0");

        // Drop old tables from previous setups if they exist
        $db->statement("DROP TABLE IF EXISTS `mod_client_health_history`");
        $db->statement("DROP TABLE IF EXISTS `mod_client_health_rules`");
        $db->statement("DROP TABLE IF EXISTS `mod_client_health_scores`");

        // Drop new tables if they exist to force a clean test slate
        $db->statement("DROP TABLE IF EXISTS `mod_chs_weekly_digest_logs`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_recalculations`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_audit_logs`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_score_bands`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_tiers`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_settings`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_webhook_logs`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_alert_history`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_alerts`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_snapshots`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_scores`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_profile_assignments`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_profile_rules`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_profiles`");

        $db->statement("SET FOREIGN_KEY_CHECKS = 1");

        // 1. Scoring Profiles Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_profiles` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `settings` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_profiles_default` (`is_default`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Scoring Profile Rules Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_profile_rules` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `profile_id` INT UNSIGNED NOT NULL,
                `metric_key` VARCHAR(50) NOT NULL,
                `weight` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `config` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_profile_rule` (`profile_id`, `metric_key`),
                CONSTRAINT `fk_profile_rules_profile` FOREIGN KEY (`profile_id`) REFERENCES `mod_chs_profiles` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. Profile Assignments Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_profile_assignments` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT(10) NULL,
                `group_id` INT(10) NULL,
                `product_id` INT(10) NULL,
                `profile_id` INT UNSIGNED NOT NULL,
                `assigned_by` VARCHAR(50) NOT NULL DEFAULT 'system',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_client_profile` (`client_id`),
                UNIQUE KEY `uk_group_profile` (`group_id`),
                UNIQUE KEY `uk_product_profile` (`product_id`),
                CONSTRAINT `fk_profile_assign_profile` FOREIGN KEY (`profile_id`) REFERENCES `mod_chs_profiles` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 4. Current Health Scores Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_scores` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT(10) NOT NULL,
                `score` INT NOT NULL DEFAULT 100,
                `payment_score` INT NOT NULL DEFAULT 100,
                `engagement_score` INT NOT NULL DEFAULT 100,
                `trend` VARCHAR(10) NOT NULL DEFAULT 'stable',
                `prev_score` INT NOT NULL DEFAULT 100,
                `breakdown` JSON NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_scores_client` (`client_id`),
                INDEX `idx_scores_score` (`score`),
                CONSTRAINT `fk_scores_client` FOREIGN KEY (`client_id`) REFERENCES `tblclients` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 5. Daily Score Snapshots Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_snapshots` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT(10) NOT NULL,
                `score` INT NOT NULL,
                `date` DATE NOT NULL,
                UNIQUE KEY `uk_snapshots_client_date` (`client_id`, `date`),
                INDEX `idx_snapshots_date_score` (`date`, `score`),
                CONSTRAINT `fk_snapshots_client` FOREIGN KEY (`client_id`) REFERENCES `tblclients` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 6. Alerts Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_alerts` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT(10) NOT NULL,
                `type` VARCHAR(50) NOT NULL,
                `message` TEXT NOT NULL,
                `severity` VARCHAR(15) NOT NULL,
                `status` VARCHAR(15) NOT NULL DEFAULT 'open',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_alerts_status_severity` (`status`, `severity`),
                INDEX `idx_alerts_client` (`client_id`),
                CONSTRAINT `fk_alerts_client` FOREIGN KEY (`client_id`) REFERENCES `tblclients` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 7. Alert History Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_alert_history` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `alert_id` INT UNSIGNED NOT NULL,
                `action` VARCHAR(50) NOT NULL,
                `performed_by` VARCHAR(100) NOT NULL DEFAULT 'system',
                `notes` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_alert_history_alert` (`alert_id`),
                CONSTRAINT `fk_alert_history_alert` FOREIGN KEY (`alert_id`) REFERENCES `mod_chs_alerts` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 8. Webhook Logs Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_webhook_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT(10) NOT NULL,
                `event` VARCHAR(50) NOT NULL,
                `url` VARCHAR(255) NOT NULL,
                `payload` JSON NOT NULL,
                `response_code` INT NULL,
                `response_body` TEXT NULL,
                `status` VARCHAR(15) NOT NULL DEFAULT 'pending',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_webhook_logs_status_event` (`status`, `event`),
                INDEX `idx_webhook_logs_client` (`client_id`),
                CONSTRAINT `fk_webhook_logs_client` FOREIGN KEY (`client_id`) REFERENCES `tblclients` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 9. Settings Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_settings` (
                `key` VARCHAR(100) PRIMARY KEY,
                `value` TEXT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 10. Tier Configuration Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_tiers` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL,
                `min_score` INT NOT NULL,
                `max_score` INT NOT NULL,
                `badge_color` VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_tiers_name` (`name`),
                INDEX `idx_tiers_score` (`min_score`, `max_score`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 11. Score Bands Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_score_bands` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL,
                `min_score` INT NOT NULL,
                `max_score` INT NOT NULL,
                `severity_level` VARCHAR(15) NOT NULL DEFAULT 'info',
                `badge_color` VARCHAR(20) NOT NULL DEFAULT '#6b7280',
                UNIQUE KEY `uk_bands_name` (`name`),
                INDEX `idx_bands_score` (`min_score`, `max_score`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 12. Audit Logs Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_audit_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT(10) NULL,
                `action` VARCHAR(100) NOT NULL,
                `level` VARCHAR(15) NOT NULL DEFAULT 'info',
                `description` TEXT NOT NULL,
                `performed_by` VARCHAR(100) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_audit_logs_client` (`client_id`),
                CONSTRAINT `fk_audit_logs_client` FOREIGN KEY (`client_id`) REFERENCES `tblclients` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 13. Manual Recalculations Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_recalculations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `total_clients` INT NOT NULL DEFAULT 0,
                `processed_clients` INT NOT NULL DEFAULT 0,
                `started_at` TIMESTAMP NULL,
                `completed_at` TIMESTAMP NULL,
                `triggered_by` VARCHAR(100) NOT NULL,
                INDEX `idx_recalcs_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 14. Weekly Digest Logs Table
        $db->statement("
            CREATE TABLE IF NOT EXISTS `mod_chs_weekly_digest_logs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `recipients` TEXT NOT NULL,
                `stats` JSON NOT NULL,
                `status` VARCHAR(15) NOT NULL DEFAULT 'success',
                INDEX `idx_digests_sent` (`sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed initial configuration profiles, scoring rules, bands, and tiers.
        $profileId = Capsule::table('mod_chs_profiles')->insertGetId([
            'name'        => 'Default Scoring Profile',
            'description' => 'Standard health calculation weights for general clients.',
            'is_default'  => 1,
        ]);

        $rules = [
            [
                'profile_id' => $profileId,
                'metric_key' => 'client_tenure',
                'weight'     => 2.00,
                'is_enabled' => 1,
                'config'     => json_encode(['max_points' => 20]),
            ],
            [
                'profile_id' => $profileId,
                'metric_key' => 'unpaid_invoices',
                'weight'     => -5.00,
                'is_enabled' => 1,
                'config'     => json_encode([]),
            ],
            [
                'profile_id' => $profileId,
                'metric_key' => 'overdue_invoices',
                'weight'     => -10.00,
                'is_enabled' => 1,
                'config'     => json_encode([]),
            ],
            [
                'profile_id' => $profileId,
                'metric_key' => 'active_services',
                'weight'     => 5.00,
                'is_enabled' => 1,
                'config'     => json_encode(['max_points' => 30]),
            ],
            [
                'profile_id' => $profileId,
                'metric_key' => 'cancelled_services',
                'weight'     => -15.00,
                'is_enabled' => 1,
                'config'     => json_encode(['lookback_days' => 180]),
            ],
            [
                'profile_id' => $profileId,
                'metric_key' => 'open_tickets',
                'weight'     => -5.00,
                'is_enabled' => 1,
                'config'     => json_encode([]),
            ],
        ];
        Capsule::table('mod_chs_profile_rules')->insert($rules);

        $bands = [
            [
                'name'           => 'Healthy',
                'min_score'      => 80,
                'max_score'      => 100,
                'severity_level' => 'info',
                'badge_color'    => '#10b981',
            ],
            [
                'name'           => 'Warning',
                'min_score'      => 50,
                'max_score'      => 79,
                'severity_level' => 'warning',
                'badge_color'    => '#f59e0b',
            ],
            [
                'name'           => 'Critical',
                'min_score'      => 0,
                'max_score'      => 49,
                'severity_level' => 'danger',
                'badge_color'    => '#ef4444',
            ],
        ];
        Capsule::table('mod_chs_score_bands')->insert($bands);

        $tiers = [
            [
                'name'        => 'Platinum VIP',
                'min_score'   => 95,
                'max_score'   => 100,
                'badge_color' => '#4f46e5',
            ],
            [
                'name'        => 'Gold Client',
                'min_score'   => 85,
                'max_score'   => 94,
                'badge_color' => '#eab308',
            ],
            [
                'name'        => 'Silver Client',
                'min_score'   => 70,
                'max_score'   => 84,
                'badge_color' => '#94a3b8',
            ],
            [
                'name'        => 'Bronze Client',
                'min_score'   => 50,
                'max_score'   => 69,
                'badge_color' => '#b45309',
            ],
            [
                'name'        => 'Standard Client',
                'min_score'   => 0,
                'max_score'   => 49,
                'badge_color' => '#6b7280',
            ],
        ];
        Capsule::table('mod_chs_tiers')->insert($tiers);

        return [
            'status'      => 'success',
            'description' => 'Client Health Score addon module activated successfully. Database tables created.'
        ];
    } catch (\Exception $e) {
        return [
            'status'      => 'error',
            'description' => 'Activation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Deactivation hook - drops custom database tables.
 *
 * @return array
 */
function client_health_score_deactivate()
{
    try {
        $db = Capsule::connection();

        // Foreign keys check disable to prevent drop errors
        $db->statement("SET FOREIGN_KEY_CHECKS = 0");

        $db->statement("DROP TABLE IF EXISTS `mod_chs_weekly_digest_logs`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_recalculations`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_audit_logs`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_score_bands`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_tiers`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_settings`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_webhook_logs`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_alert_history`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_alerts`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_snapshots`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_scores`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_profile_assignments`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_profile_rules`");
        $db->statement("DROP TABLE IF EXISTS `mod_chs_profiles`");

        $db->statement("SET FOREIGN_KEY_CHECKS = 1");

        return [
            'status'      => 'success',
            'description' => 'Client Health Score addon module deactivated. All tables dropped.'
        ];
    } catch (\Exception $e) {
        return [
            'status'      => 'error',
            'description' => 'Deactivation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Module upgrade handler.
 *
 * @param array $vars
 * @return void
 */
function client_health_score_upgrade($vars)
{
    // Handle database migration upgrades in future releases
}

/**
 * Admin area page router.
 *
 * @param array $vars
 * @return void
 */
function client_health_score_output($vars)
{
    // Dispatch request and handle output
    try {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        
        $dispatcher = new AdminDispatcher();
        $output = $dispatcher->dispatch($action, $vars);
        
        echo $output;
    } catch (\Exception $e) {
        echo "<div class='alert alert-danger'>Critical Addon Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

/**
 * Client area page router.
 *
 * @param array $vars
 * @return array
 */
function client_health_score_clientarea($vars)
{
    try {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        
        $dispatcher = new ClientDispatcher();
        return $dispatcher->dispatch($action, $vars);
    } catch (\Exception $e) {
        return [];
    }
}
