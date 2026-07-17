<div style="margin-bottom: 20px; background: #fff; border: 1px solid #ddd; border-left: 5px solid {$scoreColor|default:'#6b7280'}; border-radius: 4px; padding: 15px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
    {if $success}
        <div class="alert alert-success" style="padding: 6px 12px; font-size: 11px; margin-bottom: 10px; border-radius: 4px;">
            <i class="fa fa-check"></i> Score recalculated successfully.
        </div>
    {/if}

    {if $scoreRecord}
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <!-- Column 1: Score Badge and Status -->
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: {$scoreColor|default:'#6b7280'}; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    {$scoreRecord.score}
                </div>
                <div>
                    <div style="font-size: 10px; text-transform: uppercase; color: #777; font-weight: bold; letter-spacing: 0.5px;">Client Health Status</div>
                    <div style="font-size: 15px; font-weight: bold; color: {$scoreColor|default:'#6b7280'}; text-transform: uppercase; display: flex; align-items: center; gap: 5px; margin-top: 2px;">
                        {if $isOverridden}
                            <i class="fa fa-anchor" title="Pinned by override" style="color: #f59e0b;"></i>
                            <span class="label label-warning" style="font-size: 10px; padding: 2px 5px;">PINNED: {$overrideTier}</span>
                        {else}
                            {$scoreRecord.status_band_name|default:'Unknown'}
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Column 2: Trend & Category Scores -->
            <div style="display: flex; gap: 30px; align-items: center; border-left: 1px solid #eee; border-right: 1px solid #eee; padding: 0 30px; flex-grow: 1;">
                <div>
                    <div style="font-size: 10px; text-transform: uppercase; color: #777; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 2px;">Trend</div>
                    <div style="font-size: 13px; margin-top: 3px;">
                        {if $scoreRecord.trend == 'up'}
                            <span class="text-success" style="font-weight: bold;"><i class="fa fa-arrow-up"></i> Improving</span>
                        {elseif $scoreRecord.trend == 'down'}
                            <span class="text-danger" style="font-weight: bold;"><i class="fa fa-arrow-down"></i> Declining</span>
                        {else}
                            <span class="text-muted" style="font-weight: bold;"><i class="fa fa-arrow-right"></i> Stable</span>
                        {/if}
                    </div>
                </div>
                <div>
                    <div style="font-size: 10px; text-transform: uppercase; color: #777; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 2px;">Payment Score</div>
                    <div style="font-size: 14px; font-weight: bold; color: {$paymentScoreColor|default:'#6b7280'}; margin-top: 3px;">
                        {$scoreRecord.payment_score}/100
                    </div>
                </div>
                <div>
                    <div style="font-size: 10px; text-transform: uppercase; color: #777; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 2px;">Engagement Score</div>
                    <div style="font-size: 14px; font-weight: bold; color: {$engagementScoreColor|default:'#6b7280'}; margin-top: 3px;">
                        {$scoreRecord.engagement_score}/100
                    </div>
                </div>
            </div>

            <!-- Column 3: Top Risk Drivers -->
            <div style="flex-grow: 1; max-width: 280px; min-width: 200px;">
                <div style="font-size: 10px; text-transform: uppercase; color: #777; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 4px;">Top Risk Drivers</div>
                {if !empty($breakdown.risk_drivers)}
                    <div style="font-size: 11px; color: #ef4444; max-height: 38px; overflow-y: auto; line-height: 1.3;">
                        {foreach from=$breakdown.risk_drivers item=driver name=drivers}
                            {if $smarty.foreach.drivers.iteration <= 2}
                                <div style="text-overflow: ellipsis; white-space: nowrap; overflow: hidden;" title="{$driver.explanation}">
                                    <i class="fa fa-warning" style="font-size: 10px;"></i> <strong>-{$driver.points} pts:</strong> {$driver.name}
                                </div>
                            {/if}
                        {/foreach}
                    </div>
                {else}
                    <div style="font-size: 11px; color: #10b981; font-style: italic; margin-top: 3px;">
                        <i class="fa fa-check-circle"></i> No critical risk drivers
                    </div>
                {/if}
            </div>

            <!-- Column 4: Quick Buttons -->
            <div style="display: flex; gap: 8px;">
                <a href="clientssummary.php?userid={$scoreRecord.client_id}&chs_recalculate=1" class="btn btn-default btn-sm" style="font-weight: bold; display: flex; align-items: center; gap: 5px;">
                    <i class="fa fa-refresh"></i> Recalculate
                </a>
                <a href="addonmodules.php?module=client_health_score&action=client&id={$scoreRecord.client_id}" class="btn btn-primary btn-sm" style="font-weight: bold; color: #fff; background-color: #306599; border-color: #306599; display: flex; align-items: center; gap: 5px;">
                    <i class="fa fa-search"></i> Full Profile
                </a>
            </div>
        </div>
    {else}
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <div style="font-size: 13px; font-weight: bold; color: #777; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-info-circle" style="font-size: 16px; color: #306599;"></i> No health score calculated for this client yet.
            </div>
            <a href="clientssummary.php?userid={$smarty.get.userid}&chs_recalculate=1" class="btn btn-primary btn-sm" style="font-weight: bold; color: #fff; background-color: #306599; border-color: #306599;">
                <i class="fa fa-refresh"></i> Run Calculation Now
            </a>
        </div>
    {/if}
</div>
