<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ClientHealthScore\Client\Controller as ClientController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class ClientHealthService
{
    private $controller;

    public function __construct()
    {
        $this->controller = new ClientController();
    }

    /**
     * Recalculate health score for a single client.
     */
    public function recalculateClient(int $clientId): int
    {
        return $this->controller->calculateForClient($clientId);
    }

    /**
     * Recalculate health scores for all clients in batches.
     */
    public function recalculateAll(int $batchSize = 100): int
    {
        return $this->controller->calculateAll($batchSize);
    }

    /**
     * Get the health score record for a single client.
     */
    public function getClientHealth(int $clientId): ?array
    {
        return $this->controller->getScoreForClient($clientId);
    }

    /**
     * Get aggregate statistics for the dashboard summary.
     */
    public function getDashboardSummary(): array
    {
        return $this->controller->getStats();
    }

    /**
     * Get highest-value at-risk or critical clients ordered by MRR desc.
     */
    public function getTopAtRiskByMrr(int $limit = 10): array
    {
        $watchMin = 60;
        try {
            $watchMin = Capsule::table('mod_chs_score_bands')
                ->where('profile_id', 1)
                ->where('name', 'Watch')
                ->value('min_score') ?? 60;
        } catch (\Exception $e) {}

        $atRisk = Capsule::select("
            SELECT c.id, c.firstname, c.lastname, c.companyname, s.score, s.trend,
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
            WHERE s.score < " . (int)$watchMin . " AND c.status = 'Active'
            ORDER BY mrr DESC, s.score ASC
            LIMIT " . (int)$limit . "
        ");
        
        return array_map(function($item) {
            return (array)$item;
        }, $atRisk);
    }
}
