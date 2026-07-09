<div class="clientsummaryactions" style="margin-bottom: 15px;">
    <div class="panel panel-default" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div class="panel-heading" style="font-weight: bold; background-color: #306599; color: #fff; border-color: #306599; border-radius: 3px 3px 0 0;">
            <i class="fa fa-heartbeat"></i> Client Health Overview
        </div>
        <div class="panel-body" style="padding: 12px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px;">
            {if $success}
                <div class="alert alert-success" style="padding: 6px; font-size: 11px; margin-bottom: 10px;">
                    <i class="fa fa-check"></i> Score recalculated successfully.
                </div>
            {/if}

            {if $scoreRecord}
                <!-- Score Gauge -->
                <div style="text-align: center; margin-bottom: 10px;">
                    <div style="font-size: 26px; font-weight: bold; line-height: 1; color: {if $scoreRecord.score >= 80}#10b981{elseif $scoreRecord.score >= 50}#f59e0b{else}#ef4444{/if};">
                        {$scoreRecord.score}/100
                    </div>
                    <div class="progress" style="height: 6px; margin: 8px auto 4px auto; background-color: #eee; width: 85%;">
                        <div class="progress-bar" role="progressbar" style="width: {$scoreRecord.score}%; background-color: {if $scoreRecord.score >= 80}#10b981{elseif $scoreRecord.score >= 50}#f59e0b{else}#ef4444{/if};"></div>
                    </div>
                </div>

                <!-- Trend & Category Scores -->
                <table class="table table-condensed" style="font-size: 11px; margin-bottom: 8px; border-bottom: 1px solid #eee;">
                    <tr>
                        <td><strong>Trend:</strong></td>
                        <td class="text-right">
                            {if $scoreRecord.trend == 'up'}
                                <span class="text-success" style="font-weight: bold;"><i class="fa fa-arrow-up"></i> Improving</span>
                            {elseif $scoreRecord.trend == 'down'}
                                <span class="text-danger" style="font-weight: bold;"><i class="fa fa-arrow-down"></i> Declining</span>
                            {else}
                                <span class="text-muted"><i class="fa fa-arrow-right"></i> Stable</span>
                            {/if}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Payment Score:</strong></td>
                        <td class="text-right"><strong style="color: {if $scoreRecord.payment_score >= 80}#10b981{elseif $scoreRecord.payment_score >= 50}#f59e0b{else}#ef4444{/if};">{$scoreRecord.payment_score}/100</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Engagement Score:</strong></td>
                        <td class="text-right"><strong style="color: {if $scoreRecord.engagement_score >= 80}#10b981{elseif $scoreRecord.engagement_score >= 50}#f59e0b{else}#ef4444{/if};">{$scoreRecord.engagement_score}/100</strong></td>
                    </tr>
                </table>

                <!-- Risk Drivers -->
                <div style="margin-bottom: 10px;">
                    <div style="font-weight: bold; font-size: 10px; text-transform: uppercase; color: #777; margin-bottom: 4px;">Top Risk Drivers</div>
                    {if !empty($breakdown.risk_drivers)}
                        <ul style="padding-left: 12px; margin-bottom: 0; font-size: 10.5px; color: #ef4444;">
                            {foreach from=$breakdown.risk_drivers item=driver}
                                <li style="margin-bottom: 3px;" title="{$driver.explanation}">
                                    <strong>{$driver.points} pts:</strong> {$driver.name}
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <div class="text-muted" style="font-size: 10.5px; font-style: italic;">No critical risk drivers.</div>
                    {/if}
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 5px; margin-top: 10px;">
                    <a href="clientssummary.php?userid={$scoreRecord.client_id}&chs_recalculate=1" class="btn btn-default btn-xs" style="flex: 1; font-weight: bold;">
                        <i class="fa fa-refresh"></i> Recalc Now
                    </a>
                    <a href="addonmodules.php?module=client_health_score&action=client&id={$scoreRecord.client_id}" class="btn btn-primary btn-xs" style="flex: 1; font-weight: bold; color: #fff;">
                        <i class="fa fa-search"></i> Full Profile
                    </a>
                </div>
            {else}
                <div class="text-muted text-center" style="padding: 10px 0; font-size: 11px;">No Health Score Calculated</div>
                <a href="clientssummary.php?userid={$smarty.get.userid}&chs_recalculate=1" class="btn btn-primary btn-xs btn-block" style="font-weight: bold; color: #fff; margin-top: 10px;">
                    <i class="fa fa-refresh"></i> Run Calculation Now
                </a>
            {/if}
        </div>
    </div>
</div>
