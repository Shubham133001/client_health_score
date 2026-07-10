<div class="client-health-score-container">
    <!-- Breadcrumb back -->
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="{$moduleLink}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
        <a href="{$moduleLink}&action=recalculate_client&id={$client->id}" class="btn btn-primary btn-sm"><i class="fa fa-refresh"></i> Recalculate On-Demand</a>
    </div>

    {if $success}
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <i class="fa fa-check"></i> Health score recalculated successfully.
        </div>
    {/if}

    <!-- Client Header File Panel -->
    <div class="panel panel-default" style="margin-bottom: 20px;">
        <div class="panel-body" style="padding: 20px; background-color: #f8f9fa;">
            <div class="row">
                <div class="col-sm-8">
                    <h3 style="margin-top: 0; margin-bottom: 5px; font-weight: bold; color: #306599;">
                        {$client->firstname} {$client->lastname} 
                        {if $client->companyname}<span style="color: #666; font-size: 16px; font-weight: normal;">({$client->companyname})</span>{/if}
                    </h3>
                    <div style="font-size: 13px; margin-bottom: 15px;">
                        <span style="margin-right: 15px;"><strong>Client ID:</strong> {$client->id}</span>
                        <span style="margin-right: 15px;"><strong>Email:</strong> <a href="mailto:{$client->email}">{$client->email}</a></span>
                        <span style="margin-right: 15px;"><strong>Status:</strong> <span class="label label-{if $client->status == 'Active'}success{else}default{/if}">{$client->status}</span></span>
                        <span><strong>Score Band:</strong> <span class="label" style="background-color: {if $scoreRecord.score >= 80}#10b981{elseif $scoreRecord.score >= 50}#f59e0b{else}#ef4444{/if};">{if $scoreRecord.score >= 80}Healthy{elseif $scoreRecord.score >= 50}Warning{else}Critical{/if}</span></span>
                    </div>
                    <a href="clientssummary.php?userid={$client->id}" class="btn btn-default btn-xs" target="_blank">
                        <i class="fa fa-user"></i> Go to WHMCS Client Profile
                    </a>
                </div>
                <!-- Circular health score SVG indicator -->
                <div class="col-sm-4 text-center">
                    <div style="display: inline-block; position: relative; width: 120px; height: 120px;">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <!-- Background Track -->
                            <circle cx="60" cy="60" r="50" fill="none" stroke="#e9ecef" stroke-width="10" />
                            <!-- Colored Ring -->
                            <circle cx="60" cy="60" r="50" fill="none" 
                                    stroke="{if $scoreRecord.score >= 80}#10b981{elseif $scoreRecord.score >= 50}#f59e0b{else}#ef4444{/if}" 
                                    stroke-width="10" 
                                    stroke-dasharray="314" 
                                    stroke-dashoffset="{314 - (314 * $scoreRecord.score / 100)}" 
                                    stroke-linecap="round" 
                                    transform="rotate(-90 60 60)" 
                                    style="transition: stroke-dashoffset 0.8s ease;" />
                            <!-- Central Score Text -->
                            <text x="60" y="68" fill="#333" font-size="28" font-weight="bold" text-anchor="middle">
                                {$scoreRecord.score}
                            </text>
                        </svg>
                    </div>
                    <div style="font-weight: bold; margin-top: 5px; font-size: 13px;">
                        Trend: 
                        {if $scoreRecord.trend == 'up'}
                            <span class="text-success"><i class="fa fa-arrow-up"></i> Improving</span>
                        {elseif $scoreRecord.trend == 'down'}
                            <span class="text-danger"><i class="fa fa-arrow-down"></i> Declining</span>
                        {else}
                            <span class="text-muted"><i class="fa fa-arrow-right"></i> Stable</span>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Side: Risk Drivers & Deductions breakdown -->
        <div class="col-md-7">
            <div class="panel panel-default" style="margin-bottom: 20px;">
                <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-list-ul"></i> Scoring Deductions & Drivers</div>
                <div class="panel-body" style="padding: 0;">
                    <table class="table table-striped" style="margin-bottom: 0;">
                        <thead>
                            <tr style="background-color: #f9f9f9;">
                                <th>Metric / Signal</th>
                                <th>Explanation / Details</th>
                                <th class="text-center" width="120">Impact</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if $breakdown}
                                {foreach $breakdown as $key => $metric}
                                    {if $key !== 'risk_drivers'}
                                    <tr>
                                        <td>
                                            <strong style="text-transform: capitalize;">{$metric.name|default:str_replace('_', ' ', $key)}</strong>
                                        </td>
                                        <td>
                                            {$metric.explanation}
                                        </td>
                                        <td class="text-center">
                                            {if $metric.points < 0}
                                                <span class="text-danger" style="font-weight: bold;">{$metric.points}</span>
                                            {elseif $metric.points > 0}
                                                <span class="text-success" style="font-weight: bold;">+{$metric.points}</span>
                                            {else}
                                                <span class="text-muted">0</span>
                                            {/if}
                                        </td>
                                    </tr>
                                    {/if}
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="4" class="text-center text-muted" style="padding: 15px 0;">No metric breakdown available.</td>
                                }
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SVG History Chart -->
            <div class="panel panel-default" style="margin-bottom: 20px;">
                <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-line-chart"></i> 30-Day Historical Trend</div>
                <div class="panel-body text-center" style="padding: 20px;">
                    {if $history}
                        <!-- SVG line chart container -->
                        <div style="width: 100%; max-width: 600px; margin: 0 auto;">
                            <svg id="historyChartSvg" width="100%" height="160" style="background-color: #fafafa; border: 1px solid #e9ecef; border-radius: 4px; padding: 10px;"></svg>
                        </div>
                        {if count($history) == 1}
                            <div style="margin-top: 10px; font-size: 11px; color: #777;">
                                <i class="fa fa-info-circle"></i> Initial health snapshot recorded today. Trend lines will form as scores are recalculated daily.
                            </div>
                        {/if}
                    {else}
                        <div class="text-muted" style="padding: 30px 0;">No historic snapshots available for this client yet.</div>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Right Side: Details Cards -->
        <div class="col-md-5">
            <!-- Payment Signals -->
            <div class="panel panel-default" style="margin-bottom: 20px;">
                <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-credit-card"></i> Payment & Invoice Signals</div>
                <div class="panel-body">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                        <span>Unpaid Invoices:</span>
                        <strong class="{if $unpaidInvoices > 0}text-warning{/if}">{$unpaidInvoices}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                        <span>Overdue Invoices:</span>
                        <strong class="{if $overdueInvoices > 0}text-danger{/if}">{$overdueInvoices}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Active Services:</span>
                        <strong>{$activeServices}</strong>
                    </div>
                </div>
            </div>

            <!-- Engagement Signals -->
            <div class="panel panel-default" style="margin-bottom: 20px;">
                <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-ticket"></i> Support & Engagement Signals</div>
                <div class="panel-body">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                        <span>Active Support Tickets:</span>
                        <strong class="{if $openTickets > 0}text-warning{/if}">{$openTickets}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Last Score Update:</span>
                        <span class="text-muted">{$scoreRecord.updated_at|default:'Never'}</span>
                    </div>
                </div>
            </div>

            <!-- Alert History -->
            <div class="panel panel-default" style="margin-bottom: 20px;">
                <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-bell"></i> Alert History</div>
                <div class="panel-body" style="padding: 0;">
                    {if $alerts}
                        <table class="table table-striped" style="margin-bottom: 0; font-size: 12px;">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $alerts as $alert}
                                    <tr>
                                        <td><span class="label label-{if $alert.severity == 'danger'}danger{else}info{/if}">{$alert.type}</span></td>
                                        <td>{$alert.message}</td>
                                        <td class="text-nowrap">{$alert.created_at|date_format:"%Y-%m-%d"}</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    {else}
                        <div style="padding: 15px; text-align: center;" class="text-muted">No alert history recorded.</div>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Render Line Chart script in SVG -->
{if $history}
<script type="text/javascript">
jQuery(document).ready(function($) {
    var history = {$history|json_encode};
    var svg = document.getElementById('historyChartSvg');
    if (!svg) return;

    var width = svg.clientWidth || 550;
    var height = 140;
    svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

    var padding = 20;
    var chartWidth = width - (padding * 2);
    var chartHeight = height - (padding * 2);

    // Compute coordinates
    var pointsCount = history.length;
    var xStep = chartWidth / (pointsCount > 1 ? pointsCount - 1 : 1);
    
    var pathPoints = [];
    var circlesHtml = '';

    for (var i = 0; i < pointsCount; i++) {
        var x = padding + (i * xStep);
        var scoreVal = parseInt(history[i].score);
        // Map 0-100 to chartHeight - 0
        var y = padding + chartHeight - (chartHeight * scoreVal / 100);
        pathPoints.push(x + ',' + y);

        // Hover circle points
        var dotColor = scoreVal >= 80 ? '#10b981' : (scoreVal >= 50 ? '#f59e0b' : '#ef4444');
        circlesHtml += '<circle cx="' + x + '" cy="' + y + '" r="4" fill="' + dotColor + '" stroke="#fff" stroke-width="1.5"><title>Date: ' + history[i].date + ' | Score: ' + scoreVal + '</title></circle>';
    }

    var polylineHtml = '<polyline points="' + pathPoints.join(' ') + '" fill="none" stroke="#306599" stroke-width="2.5" />';
    
    // Draw background grid lines (horizontal 50 and 80)
    var gridLinesHtml = '';
    var y80 = padding + chartHeight - (chartHeight * 80 / 100);
    var y50 = padding + chartHeight - (chartHeight * 50 / 100);
    gridLinesHtml += '<line x1="' + padding + '" y1="' + y80 + '" x2="' + (width - padding) + '" y2="' + y80 + '" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3" />';
    gridLinesHtml += '<line x1="' + padding + '" y1="' + y50 + '" x2="' + (width - padding) + '" y2="' + y50 + '" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3" />';
    
    svg.innerHTML = gridLinesHtml + polylineHtml + circlesHtml;
});
</script>
{/if}
