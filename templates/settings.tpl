<div class="client-health-score-container">
    <!-- Header Navigation Tabs -->
    <ul class="nav nav-tabs" style="margin-bottom: 20px;">
        <li><a href="{$moduleLink}"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="{$moduleLink}&action=reports"><i class="fa fa-bar-chart"></i> Reports</a></li>
        <li class="active"><a href="{$moduleLink}&action=settings"><i class="fa fa-cog"></i> Settings</a></li>
        <li><a href="{$moduleLink}&action=audit"><i class="fa fa-history"></i> Audit Log</a></li>
    </ul>

    {if $message}
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <i class="fa fa-check"></i> {$message}
        </div>
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

        <!-- Form Submit Button -->
        <div class="text-right" style="margin-bottom: 30px;">
            <button type="submit" class="btn btn-primary" style="font-weight: bold; padding: 6px 20px;">
                <i class="fa fa-save"></i> Save Settings Configuration
            </button>
        </div>
    </form>
</div>
