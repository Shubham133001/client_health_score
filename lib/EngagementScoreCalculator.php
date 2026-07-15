<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class EngagementScoreCalculator
{
    /**
     * Calculate engagement score based on signals, weights, and dampening options.
     */
    public static function calculate(array $engagementSignals, array $weights, bool $isNewAccount = false, float $dampMultiplier = 1.5): array
    {
        $availableSignals = [];
        foreach ($engagementSignals as $key => $signal) {
            if ($signal['available']) {
                $availableSignals[$key] = true;
            }
        }

        // Normalize weights dynamically based on available signals
        $normalizedWeights = WeightResolver::normalizeAvailableWeights($weights, $availableSignals);

        $score = 0.0;
        $breakdown = [];

        foreach ($engagementSignals as $key => $signal) {
            $weightUsed = $normalizedWeights[$key] ?? 0.0;
            // Resolve score band details with potential new-account dampening
            $resolved = ScoreBandResolver::resolve($key, $signal['value'], 1, $isNewAccount, $dampMultiplier);

            if ($signal['available'] && $weightUsed > 0) {
                $score += $resolved['score'] * ($weightUsed / 100.0);
            }

            $breakdown[] = [
                'signal'         => $key,
                'raw_value'      => $resolved['raw_value'],
                'score'          => $resolved['score'],
                'weight'         => $weightUsed,
                'weighted_score' => $resolved['score'] * ($weightUsed / 100.0),
                'band_label'     => $resolved['band'] . ' (' . $resolved['label'] . ')',
            ];
        }

        $score = max(0.0, min(100.0, $score));

        return [
            'score'     => $score,
            'breakdown' => $breakdown,
        ];
    }
}
