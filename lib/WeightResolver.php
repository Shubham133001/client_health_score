<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class WeightResolver
{
    /**
     * Normalize weights proportionally when some sub-factor signals are missing.
     */
    public static function normalizeAvailableWeights(array $weights, array $availableSignals): array
    {
        $sum = 0.0;
        foreach ($weights as $key => $weight) {
            if (!empty($availableSignals[$key])) {
                $sum += (float)$weight;
            }
        }

        $normalized = [];
        foreach ($weights as $key => $weight) {
            if (!empty($availableSignals[$key])) {
                $normalized[$key] = ($sum > 0) ? (((float)$weight / $sum) * 100.0) : 0.0;
            } else {
                $normalized[$key] = 0.0;
            }
        }

        return $normalized;
    }
}
