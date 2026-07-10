<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class ScoreBandResolver
{
    /**
     * Resolve score for a sub-factor signal based on lookup bands and profiles.
     */
    public static function resolve(string $signalName, $rawValue, int $profileId = 1, bool $isNewAccount = false, float $dampMultiplier = 1.5): array
    {
        $score = 100;
        $band = '0';
        $label = 'Healthy';

        switch ($signalName) {
            case 'avg_days_late':
                $val = (float)$rawValue;
                $rawVal = $val;
                if ($val == 0) {
                    $score = 100; $band = '0 days'; $label = 'On Time';
                } elseif ($val <= 3) {
                    $score = 80; $band = '1-3 days'; $label = 'Slightly Late';
                } elseif ($val <= 7) {
                    $score = 50; $band = '4-7 days'; $label = 'Moderately Late';
                } elseif ($val <= 14) {
                    $score = 20; $band = '8-14 days'; $label = 'Highly Late';
                } else {
                    $score = 0; $band = '15+ days'; $label = 'Critically Late';
                }
                break;

            case 'failed_payment_attempts':
                $val = (int)$rawValue;
                $rawVal = $val;
                if ($val == 0) {
                    $score = 100; $band = '0'; $label = 'None';
                } elseif ($val == 1) {
                    $score = 80; $band = '1'; $label = 'Single Failure';
                } elseif ($val == 2) {
                    $score = 50; $band = '2'; $label = 'Multiple Failures';
                } elseif ($val == 3) {
                    $score = 20; $band = '3'; $label = 'High Failures';
                } else {
                    $score = 0; $band = '4+'; $label = 'Critical Failures';
                }
                break;

            case 'overdue_invoice_count':
                $val = (int)$rawValue;
                $rawVal = $val;
                if ($val == 0) {
                    $score = 100; $band = '0'; $label = 'None';
                } elseif ($val == 1) {
                    $score = 50; $band = '1'; $label = 'Single Overdue';
                } elseif ($val == 2) {
                    $score = 20; $band = '2'; $label = 'Multiple Overdue';
                } else {
                    $score = 0; $band = '3+'; $label = 'Critical Overdue';
                }
                break;

            case 'login_recency_days':
                $val = (int)$rawValue;
                $rawVal = $val;
                
                // New Account Dampening for login recency
                $b0_3 = 3;
                $b4_10 = 10;
                $b11_30 = 30;
                $b31_60 = 60;
                
                if ($isNewAccount && $dampMultiplier > 0) {
                    $b0_3 = (int)round(3 * $dampMultiplier);
                    $b4_10 = (int)round(10 * $dampMultiplier);
                    $b11_30 = (int)round(30 * $dampMultiplier);
                    $b31_60 = (int)round(60 * $dampMultiplier);
                }

                if ($val <= $b0_3) {
                    $score = 100; $band = "0-{$b0_3} days"; $label = 'Very Active';
                } elseif ($val <= $b4_10) {
                    $score = 80; $band = ($b0_3 + 1) . "-{$b4_10} days"; $label = 'Active';
                } elseif ($val <= $b11_30) {
                    $score = 50; $band = ($b4_10 + 1) . "-{$b11_30} days"; $label = 'Inactive';
                } elseif ($val <= $b31_60) {
                    $score = 20; $band = ($b11_30 + 1) . "-{$b31_60} days"; $label = 'Highly Inactive';
                } else {
                    $score = 0; $band = ($b31_60 + 1) . '+ days'; $label = 'Dormant';
                }
                break;

            case 'login_count_90_days':
                $val = (int)$rawValue;
                $rawVal = $val;
                
                // Dampen the frequency target for new accounts (divide targets by dampMultiplier)
                $b11 = 11;
                $b6 = 6;
                $b3 = 3;
                
                if ($isNewAccount && $dampMultiplier > 0) {
                    $b11 = (int)max(1, round(11 / $dampMultiplier));
                    $b6 = (int)max(1, round(6 / $dampMultiplier));
                    $b3 = (int)max(1, round(3 / $dampMultiplier));
                }

                if ($val >= $b11) {
                    $score = 100; $band = "{$b11}+ logins"; $label = 'High Frequency';
                } elseif ($val >= $b6) {
                    $score = 80; $band = "{$b6}-" . ($b11 - 1) . " logins"; $label = 'Good Frequency';
                } elseif ($val >= $b3) {
                    $score = 50; $band = "{$b3}-" . ($b6 - 1) . " logins"; $label = 'Moderate Frequency';
                } elseif ($val >= 1) {
                    $score = 20; $band = "1-" . ($b3 - 1) . " logins"; $label = 'Low Frequency';
                } else {
                    $score = 0; $band = '0 logins'; $label = 'No Logins';
                }
                break;

            case 'downgrade_count_12_months':
                $val = (int)$rawValue;
                $rawVal = $val;
                if ($val == 0) {
                    $score = 100; $band = '0'; $label = 'None';
                } elseif ($val == 1) {
                    $score = 50; $band = '1'; $label = 'Single Downgrade';
                } elseif ($val == 2) {
                    $score = 20; $band = '2'; $label = 'Multiple Downgrades';
                } else {
                    $score = 0; $band = '3+'; $label = 'Critical Downgrades';
                }
                break;

            case 'usage_trend':
                $val = strtolower(trim((string)$rawValue));
                $rawVal = $val;
                if ($val === 'rising') {
                    $score = 100; $band = 'rising'; $label = 'Growing Usage';
                } elseif ($val === 'stable') {
                    $score = 80; $band = 'stable'; $label = 'Stable Usage';
                } elseif ($val === 'down_low') {
                    $score = 50; $band = 'down 10-30%'; $label = 'Slight Decline';
                } elseif ($val === 'down_med') {
                    $score = 20; $band = 'down 30-60%'; $label = 'Moderate Decline';
                } else {
                    $score = 0; $band = 'down 60%+'; $label = 'Critical Decline';
                }
                break;
        }

        return [
            'score'     => (float)$score,
            'band'      => $band,
            'label'     => $label,
            'raw_value' => $rawVal,
        ];
    }
}
