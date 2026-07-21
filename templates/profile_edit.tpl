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
            <!-- Error message data element to pass to JS -->
            <div id="chs-error-message-data" style="display: none;" data-message="{$message|escape}"></div>
        {else}
            <!-- Success message data element to pass to JS -->
            <div id="chs-success-message-data" style="display: none;" data-message="{$message|escape}"></div>
        {/if}
    {/if}

    <a href="{$moduleLink}&action=settings" class="btn btn-default btn-xs" style="margin-bottom: 15px;">
        <i class="fa fa-arrow-left"></i> Back to Scoring Profiles List
    </a>

    <form method="post" action="{$moduleLink}&action=save_settings&id={$profile->id}">
        <!-- Profile Details Panel -->
        <div class="panel panel-default" style="margin-bottom: 20px;">
            <div class="panel-heading" style="font-weight: bold;">
                <i class="fa fa-id-card"></i> Scoring Profile Metadata
                <i class="fa fa-info-circle text-muted" data-toggle="tooltip" data-placement="top" title="General metadata for identifying this scoring profile." style="cursor: help; margin-left: 5px;"></i>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label style="font-weight: bold; font-size: 12px;">Profile Name</label>
                    <input type="text" name="name" class="form-control input-sm" value="{$profile->name}" required {if $profile->is_default}readonly{/if} />
                    {if $profile->is_default}<span class="help-block text-muted" style="font-size: 11px;">The default profile name is locked.</span>{/if}
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-weight: bold; font-size: 12px;">Description</label>
                    <textarea name="description" class="form-control input-sm" rows="2" placeholder="Explain when this profile is applied...">{$profile->description}</textarea>
                </div>
            </div>
        </div>

        <!-- Component Weights & Dampening Configuration -->
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;">
                        <i class="fa fa-balance-scale"></i> Component Weights & Alerts
                        <i class="fa fa-info-circle text-muted" data-toggle="tooltip" data-placement="top" title="Configure how much each main component (Payment vs Engagement) influences the final health score, and set custom warning thresholds." style="cursor: help; margin-left: 5px;"></i>
                    </div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Payment Component Weight (%)</label>
                            <input type="number" step="0.1" name="payment_weight" class="form-control input-sm" value="{$profileSettings.payment_weight|default:50.0}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Engagement Component Weight (%)</label>
                            <input type="number" step="0.1" name="engagement_weight" class="form-control input-sm" value="{$profileSettings.engagement_weight|default:50.0}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Trend Lookback Window (days)</label>
                            <input type="number" name="trend_lookback_days" class="form-control input-sm" value="{$profileSettings.trend_lookback_days|default:14}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Profile Alert Threshold</label>
                            <input type="number" name="alert_threshold" class="form-control input-sm" value="{$profileSettings.alert_threshold|default:50.0}" required />
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;">
                        <i class="fa fa-clock-o"></i> New-Account Dampening Settings
                        <i class="fa fa-info-circle text-muted" data-toggle="tooltip" data-placement="top" title="Scale metric weight configurations for newly registered clients to prevent false-negative alarms from lack of historical logs." style="cursor: help; margin-left: 5px;"></i>
                    </div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label class="checkbox-inline" style="font-weight: bold;">
                                <input type="checkbox" name="dampening_enabled" value="1" {if $profileSettings.dampening_enabled}checked{/if} /> Enable Dampening for New Clients
                            </label>
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Dampening Threshold (days active)</label>
                            <input type="number" name="dampening_threshold" class="form-control input-sm" value="{$profileSettings.dampening_threshold|default:60}" required />
                            <span class="help-block text-muted" style="font-size: 10px;">Clients active fewer than these days will have active service counts scaled.</span>
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Dampening Multiplier</label>
                            <input type="number" step="0.1" name="dampening_multiplier" class="form-control input-sm" value="{$profileSettings.dampening_multiplier|default:1.5}" required />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric Weights & Configuration -->
        <div class="panel panel-default" style="margin-bottom: 20px;">
            <div class="panel-heading" style="font-weight: bold; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <span><i class="fa fa-sliders"></i> Metric Weights & Configuration</span>
                <span class="text-muted" style="font-size: 12px; font-weight: normal; color: #555;">
                    <i class="fa fa-info-circle text-info"></i> Active weights in <strong>each section</strong> must sum to exactly <strong>100%</strong>
                </span>
            </div>
            <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
                <thead>
                    <tr style="background-color: #f9f9f9;">
                        <th>Metric Code</th>
                        <th>Signal Type</th>
                        <th>Status</th>
                        <th width="150">Score Weight</th>
                        <th>Parameters Configuration</th>
                    </tr>
                </thead>
                <tbody>
                    {assign var="currentType" value=""}
                    {foreach $rules as $rule}
                        {assign var="configData" value=$rule.config|json_decode:true}
                        {assign var="isPayment" value=($rule.metric_key == 'avg_days_late' || $rule.metric_key == 'failed_payment_attempts' || $rule.metric_key == 'overdue_invoice_count')}
                        
                        {if $isPayment && $currentType != 'payment'}
                            <tr style="background-color: #f0f9ff; font-weight: bold;">
                                <td colspan="5" style="padding: 12px 15px; color: #0369a1; font-size: 13px; border-bottom: 2px solid #bae6fd;">
                                    <i class="fa fa-credit-card"></i> Payment Signals (Billing & Payment History)
                                    <span class="pull-right" style="font-size: 12px; font-weight: normal; color: #333;">
                                        Active Weight Sum: <span id="payment-sum-badge" class="badge" style="background-color: #0284c7; color: white; font-weight: bold;">100%</span>
                                    </span>
                                </td>
                            </tr>
                            {assign var="currentType" value="payment"}
                        {elseif !$isPayment && $currentType != 'engagement'}
                            <tr style="background-color: #f5f3ff; font-weight: bold;">
                                <td colspan="5" style="padding: 12px 15px; color: #6d28d9; font-size: 13px; border-bottom: 2px solid #ddd6fe; border-top: 1px solid #e2e8f0;">
                                    <i class="fa fa-users"></i> Engagement Signals (Activity & Service Usage)
                                    <span class="pull-right" style="font-size: 12px; font-weight: normal; color: #333;">
                                        Active Weight Sum: <span id="engagement-sum-badge" class="badge" style="background-color: #7c3aed; color: white; font-weight: bold;">100%</span>
                                    </span>
                                </td>
                            </tr>
                            {assign var="currentType" value="engagement"}
                        {/if}
                        <tr>
                            <td style="vertical-align: middle; padding-left: 20px;">
                                <strong style="text-transform: capitalize;">{str_replace('_', ' ', $rule.metric_key)}</strong>
                                <br/><small class="text-muted" style="font-size: 10px;">Key: {$rule.metric_key}</small>
                            </td>
                            <td style="vertical-align: middle;">
                                {if $isPayment}
                                    <span class="label" style="background-color: #0284c7; font-size: 10px; padding: 3px 8px; font-weight: bold; border-radius: 4px;">Payment Signal</span>
                                {else}
                                    <span class="label" style="background-color: #7c3aed; font-size: 10px; padding: 3px 8px; font-weight: bold; border-radius: 4px;">Engagement Signal</span>
                                {/if}
                            </td>
                            <td style="vertical-align: middle;">
                                <label class="checkbox-inline" style="font-weight: bold;">
                                    <input type="checkbox" class="chs-metric-status" data-section="{if $isPayment}payment{else}engagement{/if}" data-key="{$rule.metric_key}" name="enabled[{$rule.metric_key}]" value="1" {if $rule.is_enabled}checked{/if} /> Active
                                </label>
                            </td>
                            <td style="vertical-align: middle;">
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" class="form-control text-center chs-metric-weight" data-section="{if $isPayment}payment{else}engagement{/if}" data-key="{$rule.metric_key}" name="weights[{$rule.metric_key}]" value="{$rule.weight}" required />
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
                    <div class="panel-heading" style="font-weight: bold;">
                        <i class="fa fa-trophy"></i> Loyalty Tier Score Thresholds
                        <i class="fa fa-info-circle text-muted" data-toggle="tooltip" data-placement="top" title="Define customer tier classifications (e.g. VIP, Standard) based on final health score ranges." style="cursor: help; margin-left: 5px;"></i>
                    </div>
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
                    <div class="panel-heading" style="font-weight: bold;">
                        <i class="fa fa-flag"></i> Health Score Status Bands
                        <i class="fa fa-info-circle text-muted" data-toggle="tooltip" data-placement="top" title="Define the operational health states (e.g. Healthy, Watch, At-Risk) based on final health score ranges." style="cursor: help; margin-left: 5px;"></i>
                    </div>
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

        <div class="text-right" style="margin-bottom: 30px;">
            <button type="submit" class="btn btn-primary" style="font-weight: bold; padding: 6px 20px;">
                <i class="fa fa-save"></i> Save Scoring Profile Settings
            </button>
        </div>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var saveButton = $('button[type="submit"]');
    var toastTimeout = null;
    
    function getOrCreateToastContainer() {
        var toastContainer = $('#chs-toast-container');
        if (toastContainer.length === 0) {
            toastContainer = $('<div id="chs-toast-container" style="position: fixed !important; top: 20px !important; right: 20px !important; z-index: 99999 !important; pointer-events: none; width: 350px;"></div>');
            $('body').append(toastContainer);
        }
        return toastContainer;
    }

    function showSuccessToast(message) {
        var container = getOrCreateToastContainer();
        var toast = $('#chs-success-toast');
        if (toast.length === 0) {
            toast = $('<div id="chs-success-toast" class="alert alert-success" style="pointer-events: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-left: 4px solid #5cb85c; margin-bottom: 0; transition: all 0.3s ease; opacity: 0; transform: translateY(-20px);">' +
                '<div style="display: flex; align-items: flex-start; gap: 8px;">' +
                    '<i class="fa fa-check" style="margin-top: 2px;"></i>' +
                    '<div style="flex: 1;">' +
                        '<strong style="display: block; margin-bottom: 3px;">Success</strong>' +
                        '<div class="toast-body-content" style="font-size: 11px; line-height: 1.4;"></div>' +
                    '</div>' +
                '</div>' +
            '</div>');
            container.append(toast);
        }
        
        toast.find('.toast-body-content').html(message);
        
        setTimeout(function() {
            toast.css({
                'opacity': '1',
                'transform': 'translateY(0)'
            });
        }, 50);
        
        setTimeout(function() {
            toast.css({
                'opacity': '0',
                'transform': 'translateY(-20px)'
            });
        }, 5000);
    }

    function showErrorToast(message) {
        var container = getOrCreateToastContainer();
        var toast = $('#chs-error-toast');
        if (toast.length === 0) {
            toast = $('<div id="chs-error-toast" class="alert alert-danger" style="pointer-events: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-left: 4px solid #d9534f; margin-bottom: 0; transition: all 0.3s ease; opacity: 0; transform: translateY(-20px);">' +
                '<div style="display: flex; align-items: flex-start; gap: 8px;">' +
                    '<i class="fa fa-exclamation-triangle" style="margin-top: 2px;"></i>' +
                    '<div style="flex: 1;">' +
                        '<strong style="display: block; margin-bottom: 3px;">Error</strong>' +
                        '<div class="toast-body-content" style="font-size: 11px; line-height: 1.4;"></div>' +
                    '</div>' +
                '</div>' +
            '</div>');
            container.append(toast);
        }
        
        toast.find('.toast-body-content').html(message);
        
        setTimeout(function() {
            toast.css({
                'opacity': '1',
                'transform': 'translateY(0)'
            });
        }, 50);
        
        setTimeout(function() {
            toast.css({
                'opacity': '0',
                'transform': 'translateY(-20px)'
            });
        }, 5000);
    }

    function showValidationToast(messages) {
        var container = getOrCreateToastContainer();
        var toast = $('#chs-validation-toast');
        if (toast.length === 0) {
            toast = $('<div id="chs-validation-toast" class="alert alert-danger" style="pointer-events: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-left: 4px solid #d9534f; margin-bottom: 0; transition: all 0.3s ease; opacity: 0; transform: translateY(-20px);">' +
                '<div style="display: flex; align-items: flex-start; gap: 8px;">' +
                    '<i class="fa fa-exclamation-triangle" style="margin-top: 2px;"></i>' +
                    '<div style="flex: 1;">' +
                        '<strong style="display: block; margin-bottom: 3px;">Validation Error</strong>' +
                        '<div class="toast-body-content" style="font-size: 11px; line-height: 1.4;"></div>' +
                    '</div>' +
                '</div>' +
            '</div>');
            container.append(toast);
        }
        
        toast.find('.toast-body-content').html(messages.join('<br/>'));
        
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
        
        // Trigger show animation
        setTimeout(function() {
            toast.css({
                'opacity': '1',
                'transform': 'translateY(0)'
            });
        }, 50);
    }
    
    function hideValidationToast() {
        var toast = $('#chs-validation-toast');
        if (toast.length > 0) {
            toast.css({
                'opacity': '0',
                'transform': 'translateY(-20px)'
            });
        }
    }

    function updateBadges() {
        // Clear previous validation styling when inputs are changed
        $('input[name="payment_weight"], input[name="engagement_weight"]').css({ 'border-color': '', 'box-shadow': '' });
        $('.chs-metric-weight').css({ 'border-color': '', 'box-shadow': '' });

        var paymentSum = 0;
        var engagementSum = 0;

        $('.chs-metric-weight').each(function() {
            var key = $(this).data('key');
            var section = $(this).data('section');
            var weightVal = parseFloat($(this).val()) || 0;
            
            // Find corresponding status checkbox
            var checkbox = $('.chs-metric-status[data-key="' + key + '"]');
            var isEnabled = checkbox.is(':checked');

            if (isEnabled) {
                if (section === 'payment') {
                    paymentSum += weightVal;
                } else if (section === 'engagement') {
                    engagementSum += weightVal;
                }
            }
        });

        // Round to 2 decimal places to avoid float representation issues in JS
        paymentSum = Math.round(paymentSum * 100) / 100;
        engagementSum = Math.round(engagementSum * 100) / 100;

        // Update UI badges
        var payBadge = $('#payment-sum-badge');
        payBadge.text(paymentSum.toFixed(2) + '%');
        if (Math.abs(paymentSum - 100) > 0.01) {
            payBadge.css('background-color', '#d9534f'); // Red danger
        } else {
            payBadge.css('background-color', '#0284c7'); // Default theme color
        }

        var engBadge = $('#engagement-sum-badge');
        engBadge.text(engagementSum.toFixed(2) + '%');
        if (Math.abs(engagementSum - 100) > 0.01) {
            engBadge.css('background-color', '#d9534f'); // Red danger
        } else {
            engBadge.css('background-color', '#7c3aed'); // Default theme color
        }
    }

    // Form submission validation check
    $('.client-health-score-container form').first().on('submit', function(e) {
        // Clear previous styling
        $('input[name="payment_weight"], input[name="engagement_weight"]').css({ 'border-color': '', 'box-shadow': '' });
        $('.chs-metric-weight').css({ 'border-color': '', 'box-shadow': '' });

        var paymentSum = 0;
        var engagementSum = 0;

        $('.chs-metric-weight').each(function() {
            var key = $(this).data('key');
            var section = $(this).data('section');
            var weightVal = parseFloat($(this).val()) || 0;
            
            var checkbox = $('.chs-metric-status[data-key="' + key + '"]');
            var isEnabled = checkbox.is(':checked');

            if (isEnabled) {
                if (section === 'payment') {
                    paymentSum += weightVal;
                } else if (section === 'engagement') {
                    engagementSum += weightVal;
                }
            }
        });

        paymentSum = Math.round(paymentSum * 100) / 100;
        engagementSum = Math.round(engagementSum * 100) / 100;

        // Overall component weights
        var compPaymentWeight = parseFloat($('input[name="payment_weight"]').val()) || 0;
        var compEngagementWeight = parseFloat($('input[name="engagement_weight"]').val()) || 0;
        var compTotalSum = Math.round((compPaymentWeight + compEngagementWeight) * 100) / 100;

        var hasError = false;
        var errorMsgs = [];
        
        if (Math.abs(compTotalSum - 100) > 0.01) {
            hasError = true;
            errorMsgs.push('Component weights (Payment + Engagement) sum to ' + compTotalSum.toFixed(1) + '% (must be 100%).');
            $('input[name="payment_weight"], input[name="engagement_weight"]').css({ 'border-color': '#d9534f', 'box-shadow': '0 0 4px rgba(217,83,79,0.5)' });
        }
        if (Math.abs(paymentSum - 100) > 0.01) {
            hasError = true;
            errorMsgs.push('Active Payment Signals sum to ' + paymentSum.toFixed(2) + '% (must be 100%).');
            $('.chs-metric-weight[data-section="payment"]').each(function() {
                var key = $(this).data('key');
                var isEnabled = $('.chs-metric-status[data-key="' + key + '"]').is(':checked');
                if (isEnabled) {
                    $(this).css({ 'border-color': '#d9534f', 'box-shadow': '0 0 4px rgba(217,83,79,0.5)' });
                }
            });
        }
        if (Math.abs(engagementSum - 100) > 0.01) {
            hasError = true;
            errorMsgs.push('Active Engagement Signals sum to ' + engagementSum.toFixed(2) + '% (must be 100%).');
            $('.chs-metric-weight[data-section="engagement"]').each(function() {
                var key = $(this).data('key');
                var isEnabled = $('.chs-metric-status[data-key="' + key + '"]').is(':checked');
                if (isEnabled) {
                    $(this).css({ 'border-color': '#d9534f', 'box-shadow': '0 0 4px rgba(217,83,79,0.5)' });
                }
            });
        }

        if (hasError) {
            e.preventDefault(); // Stop form submission
            showValidationToast(errorMsgs);
            
            // Auto hide after 5 seconds (5000ms)
            toastTimeout = setTimeout(function() {
                hideValidationToast();
            }, 5000);
        }
    });

    // Bind event listeners to status checkboxes and weight fields for live badge totals
    $(document).on('change', '.chs-metric-status', updateBadges);
    $(document).on('input change', '.chs-metric-weight, input[name="payment_weight"], input[name="engagement_weight"]', updateBadges);

    // Initialize tooltips
    if ($.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Success toast trigger if data-element is present
    var successData = $('#chs-success-message-data');
    if (successData.length > 0) {
        showSuccessToast(successData.data('message'));
    }

    // Error toast trigger if data-element is present
    var errorData = $('#chs-error-message-data');
    if (errorData.length > 0) {
        showErrorToast(errorData.data('message'));
    }

    // Initial run to show correct badge totals on load
    updateBadges();
});
</script>
