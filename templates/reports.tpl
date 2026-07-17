<div class="client-health-score-container">
    <!-- Header Navigation Tabs -->
    <ul class="nav nav-tabs" style="margin-bottom: 20px;">
        <li><a href="{$moduleLink}"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li class="active"><a href="{$moduleLink}&action=reports"><i class="fa fa-bar-chart"></i> Reports</a></li>
        <li><a href="{$moduleLink}&action=settings"><i class="fa fa-cog"></i> Settings</a></li>
        <li><a href="{$moduleLink}&action=audit"><i class="fa fa-history"></i> Audit Log</a></li>
    </ul>

    <!-- Top Statistics Panels -->
    <div class="row" style="margin-bottom: 20px;">
        <!-- MRR by Health Tier -->
        <div class="col-md-4">
            <div class="panel panel-default" style="margin-bottom: 0; height: 180px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-money"></i> MRR by Health Tier</div>
                <div class="panel-body" style="padding: 15px;">
                    {foreach $mrrByTier as $tier}
                    <div style="margin-bottom: 8px; font-size: 13px;">
                        <span class="{$tier.color_class}" style="font-weight: bold;">{$tier.name} ({$tier.min}-{$tier.max}):</span> 
                        <span class="pull-right"><strong>${$tier.amount|number_format:2}</strong></span>
                    </div>
                    {/foreach}
                </div>
            </div>
        </div>

        <!-- Risk Movement Stats -->
        <div class="col-md-4">
            <div class="panel panel-default" style="margin-bottom: 0; height: 180px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-line-chart"></i> Risk Movement (Trend)</div>
                <div class="panel-body" style="padding: 15px;">
                    <div style="margin-bottom: 8px; font-size: 13px;">
                        <span class="text-success" style="font-weight: bold;"><i class="fa fa-arrow-up"></i> Improving:</span> 
                        <span class="pull-right"><strong>{$movementStats.up_count|default:0} clients</strong></span>
                    </div>
                    <div style="margin-bottom: 8px; font-size: 13px;">
                        <span class="text-danger" style="font-weight: bold;"><i class="fa fa-arrow-down"></i> Declining:</span> 
                        <span class="pull-right"><strong>{$movementStats.down_count|default:0} clients</strong></span>
                    </div>
                    <div style="font-size: 13px;">
                        <span class="text-muted" style="font-weight: bold;"><i class="fa fa-minus"></i> Stable:</span> 
                        <span class="pull-right"><strong>{$movementStats.stable_count|default:0} clients</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Root Cause Analysis (Top 3 Deductions) -->
        <div class="col-md-4">
            <div class="panel panel-default" style="margin-bottom: 0; height: 180px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-search-plus"></i> Root Cause Breakdown</div>
                <div class="panel-body" style="padding: 10px 15px;">
                    <table class="table table-condensed" style="font-size: 11px; margin-bottom: 0;">
                        <thead>
                            <tr><th>Risk Factor</th><th class="text-right">Total Deductions</th></tr>
                        </thead>
                        <tbody>
                            {foreach $rootCauses as $cause}
                                {if $cause@iteration <= 3}
                                <tr>
                                    <td><strong style="text-transform: capitalize;">{$cause.name}</strong></td>
                                    <td class="text-right text-danger" style="font-weight: bold;">-{$cause.total_deduction} pts</td>
                                </tr>
                                {/if}
                            {foreachelse}
                                <tr><td colspan="2" class="text-muted text-center">No deductions recorded.</td></tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Middle Section: Score Trend History -->
    <div class="panel panel-default" style="margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-history"></i> Average Health Score Trend (Last 30 Snapshots)</div>
        <div class="panel-body" style="padding: 10px 15px;">
            {if empty($trendHistory)}
                <p class="text-muted text-center" style="margin-bottom: 0; padding: 10px 0;">No history snapshot data available yet.</p>
            {else}
                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                    {foreach $trendHistory as $th}
                        <div style="flex: 1; min-width: 80px; border: 1px solid #ddd; padding: 6px; text-align: center; border-radius: 3px; background-color: #fcfcfc;">
                            <div style="font-size: 9px; color: #777;">{$th.date}</div>
                            <div style="font-size: 14px; font-weight: bold; color: #306599;">{$th.avg_score|round:1}</div>
                        </div>
                    {/foreach}
                </div>
            {/if}
        </div>
    </div>

    <div class="row">
        <!-- Left Side: High Churn Risk Report -->
        <div class="col-md-6">
            <div class="panel panel-danger" style="margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-heading" style="font-weight: bold; font-size: 13px;"><i class="fa fa-exclamation-triangle"></i> Top High Churn Risks (Score < {$watchMin})</div>
                <div class="panel-body" style="padding: 0;">
                    <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
                        <thead>
                            <tr style="background-color: #fcf8e3;">
                                <th width="80">Client ID</th>
                                <th>Client Name</th>
                                <th width="100" class="text-center">Score</th>
                                <th width="100" class="text-center">Trend</th>
                                <th width="80" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $churnRisks as $client}
                                <tr>
                                    <td><a href="clientssummary.php?userid={$client.id}">{$client.id}</a></td>
                                    <td>
                                        <strong>{$client.firstname} {$client.lastname}</strong>{if $client.companyname} ({$client.companyname}){/if}
                                        {if $client.is_overridden}
                                            <span class="label label-warning" style="font-size: 9px; padding: 1px 4px; margin-left: 5px;" title="Pinned to {$client.override_tier} by manual override"><i class="fa fa-anchor"></i> PINNED</span>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge" style="background-color: {$client.score_color}; font-size: 11px; padding: 3px 6px;">{$client.score}</span>
                                    </td>
                                    <td class="text-center">
                                        {if $client.trend == 'up'}
                                            <span class="text-success"><i class="fa fa-arrow-up"></i> Up</span>
                                        {elseif $client.trend == 'down'}
                                            <span class="text-danger"><i class="fa fa-arrow-down"></i> Down</span>
                                        {else}
                                            <span class="text-muted"><i class="fa fa-arrow-right"></i> Stable</span>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        <a href="{$moduleLink}&action=client&id={$client.id}" class="btn btn-default btn-xs"><i class="fa fa-search"></i> View</a>
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="5" class="text-center text-muted" style="padding: 15px 0;">No high churn risk clients found. Great job!</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
                {if $totalPagesChurn > 1}
                    <div class="panel-footer" style="background-color: #fff; border-top: 1px solid #ddd; padding: 6px 12px; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                        <span class="text-muted">Page <strong>{$pageChurn}</strong> of {$totalPagesChurn}</span>
                        <ul class="pagination pagination-sm" style="margin: 0; padding: 0;">
                            <li class="{if $pageChurn <= 1}disabled{/if}">
                                <a href="{if $pageChurn > 1}{$moduleLink}&action=reports&page_churn={$pageChurn-1}&page_vip={$pageVip}{else}#{/if}" style="padding: 2px 6px; font-size: 10px;">&laquo;</a>
                            </li>
                            {for $p=1 to $totalPagesChurn}
                                <li class="{if $pageChurn == $p}active{/if}">
                                    <a href="{$moduleLink}&action=reports&page_churn={$p}&page_vip={$pageVip}" style="padding: 2px 6px; font-size: 10px;">{$p}</a>
                                </li>
                            {/for}
                            <li class="{if $pageChurn >= $totalPagesChurn}disabled{/if}">
                                <a href="{if $pageChurn < $totalPagesChurn}{$moduleLink}&action=reports&page_churn={$pageChurn+1}&page_vip={$pageVip}{else}#{/if}" style="padding: 2px 6px; font-size: 10px;">&raquo;</a>
                            </li>
                        </ul>
                    </div>
                {/if}
            </div>
        </div>

        <!-- Right Side: VIP Customers Report -->
        <div class="col-md-6">
            <div class="panel panel-success" style="margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-heading" style="font-weight: bold; font-size: 13px;"><i class="fa fa-trophy"></i> Top VIP Customers (Score >= {$healthyMin})</div>
                <div class="panel-body" style="padding: 0;">
                    <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
                        <thead>
                            <tr style="background-color: #dff0d8;">
                                <th width="80">Client ID</th>
                                <th>Client Name</th>
                                <th width="100" class="text-center">Score</th>
                                <th width="100" class="text-center">Trend</th>
                                <th width="80" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $vipClients as $client}
                                <tr>
                                    <td><a href="clientssummary.php?userid={$client.id}">{$client.id}</a></td>
                                    <td>
                                        <strong>{$client.firstname} {$client.lastname}</strong>{if $client.companyname} ({$client.companyname}){/if}
                                        {if $client.is_overridden}
                                            <span class="label label-warning" style="font-size: 9px; padding: 1px 4px; margin-left: 5px;" title="Pinned to {$client.override_tier} by manual override"><i class="fa fa-anchor"></i> PINNED</span>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge" style="background-color: {$client.score_color}; font-size: 11px; padding: 3px 6px;">{$client.score}</span>
                                    </td>
                                    <td class="text-center">
                                        {if $client.trend == 'up'}
                                            <span class="text-success"><i class="fa fa-arrow-up"></i> Up</span>
                                        {elseif $client.trend == 'down'}
                                            <span class="text-danger"><i class="fa fa-arrow-down"></i> Down</span>
                                        {else}
                                            <span class="text-muted"><i class="fa fa-arrow-right"></i> Stable</span>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        <a href="{$moduleLink}&action=client&id={$client.id}" class="btn btn-default btn-xs"><i class="fa fa-search"></i> View</a>
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="5" class="text-center text-muted" style="padding: 15px 0;">No VIP clients evaluated yet.</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
                {if $totalPagesVip > 1}
                    <div class="panel-footer" style="background-color: #fff; border-top: 1px solid #ddd; padding: 6px 12px; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                        <span class="text-muted">Page <strong>{$pageVip}</strong> of {$totalPagesVip}</span>
                        <ul class="pagination pagination-sm" style="margin: 0; padding: 0;">
                            <li class="{if $pageVip <= 1}disabled{/if}">
                                <a href="{if $pageVip > 1}{$moduleLink}&action=reports&page_vip={$pageVip-1}&page_churn={$pageChurn}{else}#{/if}" style="padding: 2px 6px; font-size: 10px;">&laquo;</a>
                            </li>
                            {for $p=1 to $totalPagesVip}
                                <li class="{if $pageVip == $p}active{/if}">
                                    <a href="{$moduleLink}&action=reports&page_vip={$p}&page_churn={$pageChurn}" style="padding: 2px 6px; font-size: 10px;">{$p}</a>
                                </li>
                            {/for}
                            <li class="{if $pageVip >= $totalPagesVip}disabled{/if}">
                                <a href="{if $pageVip < $totalPagesVip}{$moduleLink}&action=reports&page_vip={$pageVip+1}&page_churn={$pageChurn}{else}#{/if}" style="padding: 2px 6px; font-size: 10px;">&raquo;</a>
                            </li>
                        </ul>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>
