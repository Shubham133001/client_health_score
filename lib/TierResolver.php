<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class TierResolver
{
    /**
     * Resolve the health tier for a given score.
     */
    public static function resolve(float $score, int $profileId = 1): array
    {
        $tiers = [
            [
                'tier' => 'healthy',
                'label' => 'Healthy',
                'color' => '#10b981',
                'score_min' => 80,
                'score_max' => 100,
            ],
            [
                'tier' => 'watch',
                'label' => 'Watch',
                'color' => '#f59e0b',
                'score_min' => 60,
                'score_max' => 79,
            ],
            [
                'tier' => 'at_risk',
                'label' => 'At-Risk',
                'color' => '#f0ad4e',
                'score_min' => 35,
                'score_max' => 59,
            ],
            [
                'tier' => 'critical',
                'label' => 'Critical',
                'color' => '#d9534f',
                'score_min' => 0,
                'score_max' => 34,
            ]
        ];

        // Ensure score is clamped between 0 and 100
        $clampedScore = max(0.0, min(100.0, $score));

        foreach ($tiers as $t) {
            if ($clampedScore >= $t['score_min'] && $clampedScore <= $t['score_max']) {
                return $t;
            }
        }

        return $tiers[3]; // Fallback to Critical
    }
}
