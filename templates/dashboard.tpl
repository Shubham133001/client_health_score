<div class="client-health-score-container">
    <!-- Header Navigation Tabs -->
    <ul class="nav nav-tabs" style="margin-bottom: 20px;">
        <li class="active"><a href="{$moduleLink}"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="{$moduleLink}&action=reports"><i class="fa fa-bar-chart"></i> Reports</a></li>
        <li><a href="{$moduleLink}&action=settings"><i class="fa fa-cog"></i> Settings</a></li>
        <li><a href="{$moduleLink}&action=audit"><i class="fa fa-history"></i> Audit Log</a></li>
    </ul>

    <!-- Top Summary Statistics Cards -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-default text-center" style="margin-bottom: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-body" style="padding: 15px;">
                    <div style="font-size: 24px; font-weight: bold; color: #306599;">{if $stats.average_score}{$stats.average_score}{else}N/A{/if}</div>
                    <div class="text-muted" style="font-size: 11px; font-weight: bold; text-transform: uppercase;">Avg Health</div>
                </div>
            </div>
        </div>
        {foreach $bands as $band}
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-default text-center" style="margin-bottom: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-body" style="padding: 15px;">
                    <div style="font-size: 24px; font-weight: bold; color: {$band.badge_color};">{$stats.bands_count[$band.slug]|default:0}</div>
                    <div class="text-muted" style="font-size: 11px; font-weight: bold; text-transform: uppercase;">{$band.name}</div>
                </div>
            </div>
        </div>
        {/foreach}
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-default text-center" style="margin-bottom: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-body" style="padding: 15px;">
                    <div style="font-size: 24px; font-weight: bold; color: #e11d48;">${$stats.mrr_at_risk|number_format:2}</div>
                    <div class="text-muted" style="font-size: 11px; font-weight: bold; text-transform: uppercase;">MRR at Risk</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-default text-center" style="margin-bottom: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-body" style="padding: 15px;">
                    <button class="btn btn-primary btn-sm btn-block" id="btnRecalculateAll" style="font-weight: bold; margin-top: 5px;">
                        <i class="fa fa-refresh"></i> Recalc All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recalculation Progress Overlay -->
    <div class="panel panel-info" id="recalcProgressPanel" style="display: none; margin-bottom: 20px;">
        <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-cog fa-spin"></i> Batch Recalculating Client Scores...</div>
        <div class="panel-body">
            <div class="progress" style="height: 20px; margin-bottom: 10px;">
                <div id="recalcProgressBar" class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%; line-height: 20px; font-weight: bold;">0%</div>
            </div>
            <span id="recalcProgressText" style="font-size: 12px; font-weight: 500;">Initializing...</span>
        </div>
    </div>

    <!-- Health Status Distribution Visual bar -->
    <div class="panel panel-default" style="margin-bottom: 20px;">
        <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-users"></i> Client Health Status Distribution</div>
        <div class="panel-body" style="padding: 12px;">
            {assign var="totalDist" value=$stats.total_clients|default:1}
            <div class="progress" style="height: 24px; margin-bottom: 0;">
                {if $stats.tiers_dist.platinum}
                <div class="progress-bar" style="background-color: #10b981; width: {($stats.tiers_dist.platinum / $totalDist) * 100}%; line-height: 24px; font-weight: bold;" title="Healthy">
                    Healthy ({$stats.tiers_dist.platinum})
                </div>
                {/if}
                {if $stats.tiers_dist.gold}
                <div class="progress-bar" style="background-color: #f59e0b; width: {($stats.tiers_dist.gold / $totalDist) * 100}%; line-height: 24px; font-weight: bold;" title="Watch">
                    Watch ({$stats.tiers_dist.gold})
                </div>
                {/if}
                {if $stats.tiers_dist.silver}
                <div class="progress-bar" style="background-color: #f0ad4e; width: {($stats.tiers_dist.silver / $totalDist) * 100}%; line-height: 24px; font-weight: bold;" title="At-Risk">
                    At-Risk ({$stats.tiers_dist.silver})
                </div>
                {/if}
                {if $stats.tiers_dist.standard}
                <div class="progress-bar" style="background-color: #ef4444; width: {($stats.tiers_dist.standard / $totalDist) * 100}%; line-height: 24px; font-weight: bold;" title="Critical">
                    Critical ({$stats.tiers_dist.standard})
                </div>
                {/if}
            </div>
        </div>
    </div>

    </div>

    <!-- Recent Score Movements Side-by-Side -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-6">
            <div class="panel panel-danger" style="margin-bottom: 0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-heading" style="font-weight: bold; background-color: #f2dede; color: #a94442; border-color: #ebccd1;"><i class="fa fa-arrow-down"></i> Recent Score Drops</div>
                <table class="table table-condensed table-striped" style="font-size: 11px; margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Client ID</th>
                            <th>Name</th>
                            <th class="text-center">Previous</th>
                            <th class="text-center">New Score</th>
                            <th class="text-center">Drop</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $recentDrops as $drop}
                            <tr>
                                <td>{$drop.id}</td>
                                <td><a href="clientssummary.php?userid={$drop.id}"><strong>{$drop.firstname} {$drop.lastname}</strong></a></td>
                                <td class="text-center">{$drop.prev_score}</td>
                                <td class="text-center"><span class="badge" style="background-color: #ef4444; font-size: 10px; padding: 2px 5px;">{$drop.score}</span></td>
                                <td class="text-center text-danger" style="font-weight: bold; font-size: 11px;">-{$drop.prev_score - $drop.score}</td>
                            </tr>
                        {foreachelse}
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding: 10px 0; font-style: italic;">No recent score drops.</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-success" style="margin-bottom: 0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div class="panel-heading" style="font-weight: bold; background-color: #dff0d8; color: #3c763d; border-color: #d6e9c6;"><i class="fa fa-arrow-up"></i> Recent Improvements</div>
                <table class="table table-condensed table-striped" style="font-size: 11px; margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Client ID</th>
                            <th>Name</th>
                            <th class="text-center">Previous</th>
                            <th class="text-center">New Score</th>
                            <th class="text-center">Increase</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $recentImprovements as $imp}
                            <tr>
                                <td>{$imp.id}</td>
                                <td><a href="clientssummary.php?userid={$imp.id}"><strong>{$imp.firstname} {$imp.lastname}</strong></a></td>
                                <td class="text-center">{$imp.prev_score}</td>
                                <td class="text-center"><span class="badge" style="background-color: #10b981; font-size: 10px; padding: 2px 5px;">{$imp.score}</span></td>
                                <td class="text-center text-success" style="font-weight: bold; font-size: 11px;">+{$imp.score - $imp.prev_score}</td>
                            </tr>
                        {foreachelse}
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding: 10px 0; font-style: italic;">No recent improvements.</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Client Grid Table -->
    <div class="panel panel-default">
        <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5; display: flex; justify-content: space-between; align-items: center; padding: 8px 15px;">
            <span><i class="fa fa-list"></i> Evaluated Client Health List</span>
        </div>
        <div class="panel-body" style="padding: 15px; border-bottom: 1px solid #ddd;">
            <!-- Filters form -->
            <form method="get" action="addonmodules.php" class="form-inline" style="display: flow-root;">
                <input type="hidden" name="module" value="client_health_score" />
                <div class="form-group" style="margin-right: 10px;">
                    <label for="search" class="sr-only">Search</label>
                    <input type="text" name="search" id="search" class="form-control input-sm" placeholder="Client ID, name, email..." value="{$search}" style="width: 200px;" />
                </div>
                <div class="form-group" style="margin-right: 10px;">
                    <label for="status" class="sr-only">Health Status</label>
                    <select name="status" id="status" class="form-control input-sm">
                        <option value="">All Health Statuses</option>
                        {foreach $bands as $band}
                            <option value="{$band.slug}" {if $statusFilter == $band.slug}selected{/if}>{$band.name} ({$band.min_score}-{$band.max_score})</option>
                        {/foreach}
                        <option value="unevaluated" {if $statusFilter == 'unevaluated'}selected{/if}>Unevaluated</option>
                    </select>
                </div>
                <div class="form-group" style="margin-right: 10px;">
                    <select name="group_id" class="form-control input-sm">
                        <option value="">All Client Groups</option>
                        {foreach $clientGroups as $group}
                            <option value="{$group.id}" {if $groupIdFilter == $group.id}selected{/if}>{$group.groupname}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group" style="margin-right: 10px;">
                    <select name="profile_id" class="form-control input-sm">
                        <option value="">All Scoring Profiles</option>
                        {foreach $scoringProfiles as $prof}
                            <option value="{$prof.id}" {if $profileIdFilter == $prof.id}selected{/if}>{$prof.name}</option>
                        {/foreach}
                    </select>
                </div>
                <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-filter"></i> Apply Filters</button>
                <a href="{$moduleLink}" class="btn btn-link btn-sm" style="color: #666; font-size: 12px;">Reset Filters</a>
                <a href="{$moduleLink}&action=export_csv&search={$search}&status={$statusFilter}&group_id={$groupIdFilter}&profile_id={$profileIdFilter}" class="btn btn-success btn-sm pull-right" style="font-weight: bold;"><i class="fa fa-download"></i> Export CSV</a>
            </form>
        </div>

        <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
            <thead>
                <tr style="background-color: #f9f9f9;">
                    <th width="80"><a href="{$moduleLink}&sort=client_id&dir={if $sort == 'client_id' && $dir == 'desc'}asc{else}desc{/if}&search={$search}&status={$statusFilter}">Client ID {if $sort == 'client_id'}{if $dir == 'asc'}▲{else}▼{/if}{/if}</a></th>
                    <th><a href="{$moduleLink}&sort=name&dir={if $sort == 'name' && $dir == 'desc'}asc{else}desc{/if}&search={$search}&status={$statusFilter}">Name {if $sort == 'name'}{if $dir == 'asc'}▲{else}▼{/if}{/if}</a></th>
                    <th>Company Name</th>
                    <th>Email Address</th>
                    <th width="100" class="text-center"><a href="{$moduleLink}&sort=score&dir={if $sort == 'score' && $dir == 'desc'}asc{else}desc{/if}&search={$search}&status={$statusFilter}">Health Score {if $sort == 'score'}{if $dir == 'asc'}▲{else}▼{/if}{/if}</a></th>
                    <th width="100" class="text-center">Health Tier</th>
                    <th width="80" class="text-center">Trend</th>
                    <th width="90" class="text-right"><a href="{$moduleLink}&sort=mrr&dir={if $sort == 'mrr' && $dir == 'desc'}asc{else}desc{/if}&search={$search}&status={$statusFilter}">MRR {if $sort == 'mrr'}{if $dir == 'asc'}▲{else}▼{/if}{/if}</a></th>
                    <th width="90" class="text-center">Payment Score</th>
                    <th width="90" class="text-center">Engagement Score</th>
                    <th width="140">Last Recalculated</th>
                    <th width="70" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                {foreach $clients as $client}
                    <tr>
                        <td style="vertical-align: middle;"><a href="clientssummary.php?userid={$client.client_id}">{$client.client_id}</a></td>
                        <td style="vertical-align: middle;"><strong>{$client.firstname} {$client.lastname}</strong></td>
                        <td style="vertical-align: middle;">{$client.companyname|default:'-'}</td>
                        <td style="vertical-align: middle;"><a href="mailto:{$client.email}">{$client.email}</a></td>
                        <td class="text-center" style="vertical-align: middle;">
                            {if $client.score !== null}
                                <span class="badge" style="background-color: {$client.status_band_color|default:'#6b7280'}; font-weight: bold; padding: 4px 8px; font-size: 12px; display: inline-block;" title="{$client.status_band_name}">
                                    {$client.score}
                                </span>
                            {else}
                                <span class="text-muted" style="font-size: 11px;">Unevaluated</span>
                            {/if}
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            {if $client.score !== null}
                                <span class="label" style="background-color: {$client.tier_color|default:'#6b7280'}; text-transform: uppercase; font-size: 10px; display: inline-block;">
                                    {$client.tier_name}
                                </span>
                                {if $client.is_overridden}
                                    <div style="font-size: 9px; font-weight: bold; color: #f59e0b; margin-top: 2px;">
                                        <i class="fa fa-anchor" title="Manual Override Active (Pinned to {$client.override_tier})"></i> PINNED
                                    </div>
                                {/if}
                            {else}
                                -
                            {/if}
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            {if $client.trend == 'up'}
                                <span class="text-success" title="Improving"><i class="fa fa-arrow-up"></i> Up</span>
                            {elseif $client.trend == 'down'}
                                <span class="text-danger" title="Declining"><i class="fa fa-arrow-down"></i> Down</span>
                            {elseif $client.trend == 'stable'}
                                <span class="text-muted" title="Stable"><i class="fa fa-arrow-right"></i> Stable</span>
                            {else}
                                -
                            {/if}
                        </td>
                        <td class="text-right" style="vertical-align: middle; font-weight: bold;">
                            ${$client.mrr|number_format:2}
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            {if $client.payment_score !== null}
                                <span style="font-weight: bold;">{$client.payment_score}</span>/100
                            {else}
                                -
                            {/if}
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            {if $client.engagement_score !== null}
                                <span style="font-weight: bold;">{$client.engagement_score}</span>/100
                            {else}
                                -
                            {/if}
                        </td>
                        <td style="vertical-align: middle;">{if $client.updated_at}{$client.updated_at}{else}Never{/if}</td>
                        <td class="text-center" style="vertical-align: middle;">
                            <a href="{$moduleLink}&action=client&id={$client.client_id}" class="btn btn-default btn-xs" style="font-weight: bold;"><i class="fa fa-search"></i> View</a>
                        </td>
                    </tr>
                {foreachelse}
                    <tr>
                        <td colspan="12" class="text-center text-muted" style="padding: 20px 0;">No client health scores found.</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>

        <!-- Table Footer Pagination -->
        {if $totalPages > 1}
            <div class="panel-footer" style="background-color: #fff; border-top: 1px solid #ddd; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted" style="font-size: 12px;">Page <strong>{$page}</strong> of {$totalPages} (Total Records: {$total})</span>
                <ul class="pagination pagination-sm" style="margin: 0;">
                    <li class="{if $page <= 1}disabled{/if}">
                        <a href="{if $page > 1}{$moduleLink}&page={$page-1}&search={$search}&status={$statusFilter}{else}#{/if}">&laquo; Prev</a>
                    </li>
                    {for $p=1 to $totalPages}
                        <li class="{if $page == $p}active{/if}">
                            <a href="{$moduleLink}&page={$p}&search={$search}&status={$statusFilter}">{$p}</a>
                        </li>
                    {/for}
                    <li class="{if $page >= $totalPages}disabled{/if}">
                        <a href="{if $page < $totalPages}{$moduleLink}&page={$page+1}&search={$search}&status={$statusFilter}{else}#{/if}">Next &raquo;</a>
                    </li>
                </ul>
            </div>
        {/if}
    </div>
</div>

<!-- AJAX Recalculation Javascript -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    var isRecalculating = false;
    
    $('#btnRecalculateAll').click(function(e) {
        e.preventDefault();
        if (isRecalculating) return;
        
        if (confirm("Are you sure you want to recalculate health scores for ALL clients? This may take several minutes on large systems.")) {
            isRecalculating = true;
            $('#btnRecalculateAll').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            $('#recalcProgressPanel').slideDown();
            $('#recalcProgressBar').css('width', '0%').text('0%');
            $('#recalcProgressText').text('Initializing recalculation batch...');
            
            runRecalcChunk(0);
        }
    });
    
    function runRecalcChunk(offset) {
        $.ajax({
            url: 'addonmodules.php?module=client_health_score&action=ajax_recalculate',
            type: 'GET',
            data: { offset: offset },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.done || response.total === 0) {
                        $('#recalcProgressBar').css('width', '100%').text('100%').addClass('progress-bar-success');
                        $('#recalcProgressText').html('<strong class="text-success"><i class="fa fa-check"></i> Complete!</strong> All health scores updated successfully.');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        var percent = Math.round((response.next_offset / response.total) * 100);
                        $('#recalcProgressBar').css('width', percent + '%').text(percent + '%');
                        $('#recalcProgressText').text('Processed ' + response.next_offset + ' of ' + response.total + ' clients...');
                        runRecalcChunk(response.next_offset);
                    }
                } else {
                    $('#recalcProgressText').html('<strong class="text-danger"><i class="fa fa-exclamation-triangle"></i> Error:</strong> ' + response.error);
                    $('#btnRecalculateAll').prop('disabled', false).html('<i class="fa fa-refresh"></i> Recalc All');
                    isRecalculating = false;
                }
            },
            error: function(xhr, status, error) {
                $('#recalcProgressText').html('<strong class="text-danger"><i class="fa fa-exclamation-triangle"></i> connection error:</strong> ' + error);
                $('#btnRecalculateAll').prop('disabled', false).html('<i class="fa fa-refresh"></i> Recalc All');
                isRecalculating = false;
            }
        });
    }
});
</script>
