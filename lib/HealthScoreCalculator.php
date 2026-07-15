<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class HealthScoreCalculator
{
    /**
     * Orchestrate calculation for payment & engagement components and produce final health summary.
     */
    public static function calculate(int $clientId, array $collectedSignals, array $dbWeights, array $profileSettings, bool $isNewAccount = false, float $dampMultiplier = 1.5): array
    {
        // Extract weights for Payment
        $payWeights = [
            'avg_days_late'           => $dbWeights['avg_days_late'] ?? 40.0,
            'failed_payment_attempts' => $dbWeights['failed_payment_attempts'] ?? 30.0,
            'overdue_invoice_count'   => $dbWeights['overdue_invoice_count'] ?? 30.0,
        ];

        // Extract weights for Engagement
        $engWeights = [
            'login_recency_days'        => $dbWeights['login_recency_days'] ?? 35.0,
            'login_count_90_days'       => $dbWeights['login_count_90_days'] ?? 25.0,
            'downgrade_count_12_months' => $dbWeights['downgrade_count_12_months'] ?? 20.0,
            'usage_trend'               => $dbWeights['usage_trend'] ?? 20.0,
        ];

        $hasRefund = !empty($collectedSignals['payment']['refund_or_chargeback']['value']);

        // Calculate Component Scores
        $payResult = PaymentScoreCalculator::calculate($collectedSignals['payment'], $payWeights, $hasRefund);
        $engResult = EngagementScoreCalculator::calculate($collectedSignals['engagement'], $engWeights, $isNewAccount, $dampMultiplier);

        // Apply Component Weights
        $payCompWeight = (float)($profileSettings['payment_weight'] ?? 50.0);
        $engCompWeight = (float)($profileSettings['engagement_weight'] ?? 50.0);
        if ($payCompWeight <= 0.0 && $engCompWeight <= 0.0) {
            $payCompWeight = 50.0;
            $engCompWeight = 50.0;
        }

        $finalScoreFloat = ($payResult['score'] * $payCompWeight + $engResult['score'] * $engCompWeight) / ($payCompWeight + $engCompWeight);
        $finalScoreFloat = max(0.0, min(100.0, $finalScoreFloat));

        $internalScoreRounded = round($finalScoreFloat, 2);
        $displayScore = (int)round($internalScoreRounded);

        // Resolve Tier
        $resolvedTier = TierResolver::resolve($internalScoreRounded);
        $tier = $resolvedTier['tier'];

        // Detect risk drivers (sub-factor score <= 50)
        $drivers = [];

        // 1. Payment risk drivers
        foreach ($payResult['breakdown'] as $b) {
            if ($b['signal'] !== 'refund_or_chargeback' && $b['score'] <= 50.0) {
                $drivers[] = [
                    'key'         => $b['signal'],
                    'name'        => str_replace('_', ' ', $b['signal']),
                    'score'       => $b['score'],
                    'weight'      => $b['weight'],
                    'points'      => round($b['score'] - 100.0, 2),
                    'explanation' => self::getDriverMessage($b['signal']),
                ];
            }
        }

        // 2. Engagement risk drivers
        foreach ($engResult['breakdown'] as $b) {
            if ($b['score'] <= 50.0) {
                $drivers[] = [
                    'key'         => $b['signal'],
                    'name'        => str_replace('_', ' ', $b['signal']),
                    'score'       => $b['score'],
                    'weight'      => $b['weight'],
                    'points'      => round($b['score'] - 100.0, 2),
                    'explanation' => self::getDriverMessage($b['signal']),
                ];
            }
        }

        if ($hasRefund) {
            $drivers[] = [
                'key'         => 'refund_or_chargeback',
                'name'        => 'refund or chargeback flag',
                'score'       => -30.0,
                'weight'      => 0.0,
                'points'      => -30.0,
                'explanation' => self::getDriverMessage('refund_or_chargeback'),
            ];
        }

        // Sort by lowest score first. If scores are equal, sort by highest weight.
        usort($drivers, function ($a, $b) {
            if ($a['score'] != $b['score']) {
                return $a['score'] <=> $b['score'];
            }
            return $b['weight'] <=> $a['weight'];
        });

        $driverExplanations = [];
        foreach ($drivers as $d) {
            $driverExplanations[] = $d['explanation'];
        }

        return [
            'client_id'        => $clientId,
            'final_score'      => $internalScoreRounded,
            'display_score'    => $displayScore,
            'tier'             => $tier,
            'payment_score'    => $payResult['score'],
            'engagement_score' => $engResult['score'],
            'drivers'          => $driverExplanations,
            'breakdown'        => [
                'payment'    => $payResult['breakdown'],
                'engagement' => $engResult['breakdown'],
            ],
            'risk_drivers_sorted' => $drivers,
        ];
    }

    private static function getDriverMessage(string $key): string
    {
        $messages = [
            'avg_days_late'             => 'Average payment delay increased',
            'failed_payment_attempts'   => 'Failed payment attempts increased',
            'overdue_invoice_count'     => 'Current overdue invoices found',
            'login_recency_days'        => 'No recent client-area login',
            'login_count_90_days'       => 'Login frequency dropped',
            'usage_trend'               => 'Usage trend declined',
            'downgrade_count_12_months' => 'Downgrade or cancellation activity found',
            'refund_or_chargeback'      => 'Refund or chargeback detected',
        ];
        return $messages[$key] ?? str_replace('_', ' ', $key) . ' is affecting score';
    }
}
