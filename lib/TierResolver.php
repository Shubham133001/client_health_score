<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

use WHMCS\Database\Capsule;

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
        $tiers = [];

        // Try to load tiers from scoring profile configuration or addon settings
        try {
            $profile = Capsule::table('mod_chs_profiles')->where('id', $profileId)->first();
            if ($profile && !empty($profile->settings)) {
                $profileSettings = json_decode($profile->settings, true) ?: [];
                if (!empty($profileSettings['tiers'])) {
                    foreach ($profileSettings['tiers'] as $t) {
                        $tiers[] = [
                            'tier' => strtolower(str_replace(' ', '_', $t['name'])),
                            'label' => $t['name'],
                            'color' => $t['badge_color'] ?? $t['color'] ?? '#6b7280',
                            'score_min' => (int)$t['min_score'],
                            'score_max' => (int)$t['max_score'],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {}

        // Fallback to table mod_chs_tiers if profile is empty or not set
        if (empty($tiers)) {
            try {
                $tiersDb = Capsule::table('mod_chs_tiers')->orderBy('min_score', 'desc')->get();
                if ($tiersDb->isNotEmpty()) {
                    foreach ($tiersDb as $t) {
                        $tiers[] = [
                            'tier' => strtolower(str_replace(' ', '_', $t->name)),
                            'label' => $t->name,
                            'color' => $t->badge_color,
                            'score_min' => (int)$t->min_score,
                            'score_max' => (int)$t->max_score,
                        ];
                    }
                }
            } catch (\Exception $e) {}
        }

        // Hardcoded default fallback if table query failed
        if (empty($tiers)) {
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
                    'color' => '#ef4444',
                    'score_min' => 0,
                    'score_max' => 34,
                ]
            ];
        }

        // Ensure score is clamped between 0 and 100
        $clampedScore = max(0.0, min(100.0, $score));

        foreach ($tiers as $t) {
            if ($clampedScore >= $t['score_min'] && $clampedScore <= $t['score_max']) {
                return $t;
            }
        }

        // Fallback to the lowest tier
        return end($tiers);
    }
}
