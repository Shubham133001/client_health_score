<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class SettingsRepository
{
    /**
     * Get a setting value by key. Returns default if not found or empty.
     */
    public function getSetting(string $key, $default = null)
    {
        try {
            $val = Capsule::table('mod_chs_settings')
                ->where('key', $key)
                ->value('value');
            return $val !== null ? $val : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Get profile specific settings by profile ID.
     */
    public function getProfileSettings(int $profileId): array
    {
        try {
            $profile = Capsule::table('mod_chs_profiles')->where('id', $profileId)->first();
            if ($profile && !empty($profile->settings)) {
                return json_decode($profile->settings, true) ?: [];
            }
        } catch (\Exception $e) {}
        return [];
    }

    /**
     * Load all scoring rules and weights for a profile.
     */
    public function getRulesForProfile(int $profileId): array
    {
        try {
            return Capsule::table('mod_chs_profile_rules')
                ->where('profile_id', $profileId)
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get the list of all tier thresholds.
     */
    public function getTiers(): array
    {
        try {
            return Capsule::table('mod_chs_tiers')
                ->orderBy('min_score', 'desc')
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get default settings for fallback.
     */
    public function getDefaults(): array
    {
        return [
            'payment_weight'       => 50.0,
            'engagement_weight'    => 50.0,
            'trend_lookback_days'  => 14,
            'alert_cooldown'       => 24,
            'alert_enable_tier'    => 1,
            'alert_enable_sudden'  => 1,
            'digest_enabled'       => 1,
            'digest_day'           => 'Monday',
            'digest_time'          => '09:00',
            'cron_batch_size'      => 100,
        ];
    }
}
