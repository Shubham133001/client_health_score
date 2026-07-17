<div class="client-health-score-container">
    <!-- Header Navigation Tabs -->
    <ul class="nav nav-tabs" style="margin-bottom: 20px;">
        <li><a href="{$moduleLink}"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="{$moduleLink}&action=reports"><i class="fa fa-bar-chart"></i> Reports</a></li>
        <li class="active"><a href="{$moduleLink}&action=settings"><i class="fa fa-cog"></i> Settings</a></li>
        <li><a href="{$moduleLink}&action=audit"><i class="fa fa-history"></i> Audit Log</a></li>
    </ul>

    {if $message}
        {if strpos($message, 'Error') === 0}
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <i class="fa fa-exclamation-triangle"></i> {$message}
            </div>
        {else}
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <i class="fa fa-check"></i> {$message}
            </div>
        {/if}
    {/if}

    <form method="post" action="{$moduleLink}&action=save_settings">
        <!-- Panel 1: Scoring Rules Weights & Config -->
        <div class="panel panel-default" style="margin-bottom: 20px;">
            <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-sliders"></i> Metric Weights & Configuration</div>
            <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
                <thead>
                    <tr style="background-color: #f9f9f9;">
                        <th>Metric Code</th>
                        <th>Status</th>
                        <th width="150">Score Weight</th>
                        <th>Parameters Configuration</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $rules as $rule}
                        {assign var="configData" value=$rule.config|json_decode:true}
                        <tr>
                            <td style="vertical-align: middle;">
                                <strong style="text-transform: capitalize;">{str_replace('_', ' ', $rule.metric_key)}</strong>
                                <br/><small class="text-muted" style="font-size: 10px;">Key: {$rule.metric_key}</small>
                            </td>
                            <td style="vertical-align: middle;">
                                <label class="checkbox-inline" style="font-weight: bold;">
                                    <input type="checkbox" name="enabled[{$rule.metric_key}]" value="1" {if $rule.is_enabled}checked{/if} /> Active
                                </label>
                            </td>
                            <td style="vertical-align: middle;">
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="weights[{$rule.metric_key}]" class="form-control text-center" value="{$rule.weight}" required />
                                </div>
                            </td>
                            <td style="vertical-align: middle;">
                                {if $rule.metric_key == 'client_tenure' || $rule.metric_key == 'active_services'}
                                    <div class="form-inline">
                                        <div class="form-group form-group-sm">
                                            <label style="font-size: 11px; margin-right: 5px;">Max Points Cap:</label>
                                            <input type="number" name="configs[{$rule.metric_key}][max_points]" class="form-control input-sm" value="{$configData.max_points|default:20}" style="width: 70px;" />
                                        </div>
                                    </div>
                                {elseif $rule.metric_key == 'cancelled_services'}
                                    <div class="form-inline">
                                        <div class="form-group form-group-sm">
                                            <label style="font-size: 11px; margin-right: 5px;">Lookback Days:</label>
                                            <input type="number" name="configs[{$rule.metric_key}][lookback_days]" class="form-control input-sm" value="{$configData.lookback_days|default:180}" style="width: 80px;" />
                                        </div>
                                    </div>
                                {else}
                                    <span class="text-muted" style="font-size: 11px;">Standard subtraction rules. No config parameters.</span>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

        <div class="row">
            <!-- Left Side: Tiers Configuration -->
            <div class="col-md-6">
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-trophy"></i> Loyalty Tier Score Thresholds</div>
                    <div class="panel-body" style="padding: 10px 0 0 0;">
                        <table class="table table-striped" style="margin-bottom: 0;">
                            <thead>
                                <tr style="background-color: #f9f9f9;">
                                    <th>Tier Level</th>
                                    <th width="100">Min Score</th>
                                    <th width="100">Max Score</th>
                                    <th width="120">Color Hex</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $tiers as $tier}
                                    <tr>
                                        <td style="vertical-align: middle;">
                                            <span class="label" style="background-color: {$tier.badge_color}; padding: 3px 6px;">{$tier.name}</span>
                                        </td>
                                        <td>
                                            <input type="number" name="tiers[{$tier.id}][min]" class="form-control input-sm text-center" value="{$tier.min_score}" required />
                                        </td>
                                        <td>
                                            <input type="number" name="tiers[{$tier.id}][max]" class="form-control input-sm text-center" value="{$tier.max_score}" required />
                                        </td>
                                        <td>
                                            <input type="text" name="tiers[{$tier.id}][color]" class="form-control input-sm text-center" value="{$tier.badge_color}" required />
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Side: Score Status Bands Configuration -->
            <div class="col-md-6">
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-flag"></i> Health Score Status Bands</div>
                    <div class="panel-body" style="padding: 10px 0 0 0;">
                        <table class="table table-striped" style="margin-bottom: 0;">
                            <thead>
                                <tr style="background-color: #f9f9f9;">
                                    <th>Status Band</th>
                                    <th width="100">Min Score</th>
                                    <th width="100">Max Score</th>
                                    <th width="120">Color Hex</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $bands as $band}
                                    <tr>
                                        <td style="vertical-align: middle;">
                                            <span class="label" style="background-color: {$band.badge_color}; padding: 3px 6px;">{$band.name}</span>
                                        </td>
                                        <td>
                                            <input type="number" name="bands[{$band.id}][min]" class="form-control input-sm text-center" value="{$band.min_score}" required />
                                        </td>
                                        <td>
                                            <input type="number" name="bands[{$band.id}][max]" class="form-control input-sm text-center" value="{$band.max_score}" required />
                                        </td>
                                        <td>
                                            <input type="text" name="bands[{$band.id}][color]" class="form-control input-sm text-center" value="{$band.badge_color}" required />
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Panels: General settings, Webhooks, Alerts & Digest -->
        <div class="row">
            <!-- General Settings & Alerts -->
            <div class="col-md-6">
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-cogs"></i> General & Alert Settings</div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Payment Weight (%)</label>
                            <input type="number" step="0.1" name="settings[payment_weight]" class="form-control input-sm" value="{$settings.payment_weight|default:50.0}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Engagement Weight (%)</label>
                            <input type="number" step="0.1" name="settings[engagement_weight]" class="form-control input-sm" value="{$settings.engagement_weight|default:50.0}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Trend Lookback Window (days)</label>
                            <input type="number" name="settings[trend_lookback_days]" class="form-control input-sm" value="{$settings.trend_lookback_days|default:14}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Cron Batch Recalculation Size</label>
                            <input type="number" name="settings[cron_batch_size]" class="form-control input-sm" value="{$settings.cron_batch_size|default:100}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Alert Cooldown (hours)</label>
                            <input type="number" name="settings[alert_cooldown]" class="form-control input-sm" value="{$settings.alert_cooldown|default:24}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Enable Tier Downgrade Alerts</label>
                            <select name="settings[alert_enable_tier]" class="form-control input-sm">
                                <option value="1" {if $settings.alert_enable_tier == '1'}selected{/if}>Yes</option>
                                <option value="0" {if $settings.alert_enable_tier == '0'}selected{/if}>No</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Enable Sudden Drop Alerts</label>
                            <select name="settings[alert_enable_sudden]" class="form-control input-sm">
                                <option value="1" {if $settings.alert_enable_sudden == '1'}selected{/if}>Yes (drop >= 20 pts)</option>
                                <option value="0" {if $settings.alert_enable_sudden == '0'}selected{/if}>No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Integrations -->
            <div class="col-md-6">
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-share-alt"></i> Webhook Notification Integrations</div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Slack Webhook URL</label>
                            <input type="url" name="settings[webhook_slack_url]" class="form-control input-sm" placeholder="https://hooks.slack.com/services/..." value="{$settings.webhook_slack_url|escape}" />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Discord Webhook URL</label>
                            <input type="url" name="settings[webhook_discord_url]" class="form-control input-sm" placeholder="https://discord.com/api/webhooks/..." value="{$settings.webhook_discord_url|escape}" />
                        </div>
                       <!-- <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Microsoft Teams Webhook URL</label>
                            <input type="url" name="settings[webhook_teams_url]" class="form-control input-sm" placeholder="https://outlook.office.com/webhook/..." value="{$settings.webhook_teams_url|escape}" />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Generic JSON Webhook URL</label>
                            <input type="url" name="settings[webhook_generic_url]" class="form-control input-sm" placeholder="https://yourdomain.com/endpoint" value="{$settings.webhook_generic_url|escape}" />
                        </div> -->
                    </div>
                </div>

                <!-- Weekly Digest Preferences -->
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-envelope"></i> Weekly Digest Settings</div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Enable Weekly Digest Email</label>
                            <select name="settings[digest_enabled]" class="form-control input-sm">
                                <option value="1" {if $settings.digest_enabled == '1'}selected{/if}>Enabled</option>
                                <option value="0" {if $settings.digest_enabled == '0'}selected{/if}>Disabled</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Digest Day of Week</label>
                            <select name="settings[digest_day]" class="form-control input-sm">
                                <option value="Monday" {if $settings.digest_day == 'Monday'}selected{/if}>Monday</option>
                                <option value="Tuesday" {if $settings.digest_day == 'Tuesday'}selected{/if}>Tuesday</option>
                                <option value="Wednesday" {if $settings.digest_day == 'Wednesday'}selected{/if}>Wednesday</option>
                                <option value="Thursday" {if $settings.digest_day == 'Thursday'}selected{/if}>Thursday</option>
                                <option value="Friday" {if $settings.digest_day == 'Friday'}selected{/if}>Friday</option>
                                <option value="Saturday" {if $settings.digest_day == 'Saturday'}selected{/if}>Saturday</option>
                                <option value="Sunday" {if $settings.digest_day == 'Sunday'}selected{/if}>Sunday</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Digest Time (24h format)</label>
                            <input type="text" name="settings[digest_time]" class="form-control input-sm" placeholder="09:00" value="{$settings.digest_time|default:'09:00'}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Digest Recipients (comma-separated emails)</label>
                            <input type="text" name="settings[digest_recipients]" class="form-control input-sm" placeholder="admin@domain.com, manager@domain.com" value="{$settings.digest_recipients|escape}" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Submit Button -->
        <div class="text-right" style="margin-bottom: 30px;">
            <button type="submit" class="btn btn-primary" style="font-weight: bold; padding: 6px 20px;">
                <i class="fa fa-save"></i> Save Settings Configuration
            </button>
        </div>
    </form>
</div>
