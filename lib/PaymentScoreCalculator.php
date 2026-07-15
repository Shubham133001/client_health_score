<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class PaymentScoreCalculator
{
    /**
     * Calculate payment score based on signals and weights.
     */
    public static function calculate(array $paymentSignals, array $weights, bool $hasRefundOrChargeback = false): array
    {
        $availableSignals = [];
        foreach ($paymentSignals as $key => $signal) {
            if ($signal['available'] && $key !== 'refund_or_chargeback') {
                $availableSignals[$key] = true;
            }
        }

        // Normalize weights dynamically based on available signals
        $normalizedWeights = WeightResolver::normalizeAvailableWeights($weights, $availableSignals);

        $score = 0.0;
        $breakdown = [];

        foreach ($paymentSignals as $key => $signal) {
            if ($key === 'refund_or_chargeback') {
                continue;
            }

            $weightUsed = $normalizedWeights[$key] ?? 0.0;
            // Resolve score band details
            $resolved = ScoreBandResolver::resolve($key, $signal['value']);

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

        // Apply Refund/Chargeback deduction
        if ($hasRefundOrChargeback) {
            $score -= 30.0;
        }

        $score = max(0.0, min(100.0, $score));

        return [
            'score'     => $score,
            'breakdown' => $breakdown,
        ];
    }
}
