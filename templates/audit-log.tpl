<div class="client-health-score-container">
    <!-- Header Navigation Tabs -->
    <ul class="nav nav-tabs" style="margin-bottom: 20px;">
        <li><a href="{$moduleLink}"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="{$moduleLink}&action=reports"><i class="fa fa-bar-chart"></i> Reports</a></li>
        <li><a href="{$moduleLink}&action=settings"><i class="fa fa-cog"></i> Settings</a></li>
        <li class="active"><a href="{$moduleLink}&action=audit"><i class="fa fa-history"></i> Audit Log</a></li>
    </ul>

    <!-- Row 1: Batch Recalculations History -->
    <div class="panel panel-default" style="margin-bottom: 20px;">
        <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-refresh"></i> Batch Recalculations History</div>
        <div class="panel-body" style="padding: 0;">
            <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
                <thead>
                    <tr style="background-color: #f9f9f9;">
                        <th width="80">Batch ID</th>
                        <th width="120">Status</th>
                        <th width="120" class="text-center">Clients count</th>
                        <th>Started At</th>
                        <th>Completed At</th>
                        <th>Triggered By</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $recalculations as $run}
                        <tr>
                            <td>#{$run.id}</td>
                            <td>
                                {if $run.status == 'completed'}
                                    <span class="label label-success">Completed</span>
                                {elseif $run.status == 'processing'}
                                    <span class="label label-warning">Processing</span>
                                {elseif $run.status == 'failed'}
                                    <span class="label label-danger">Failed</span>
                                {else}
                                    <span class="label label-default">{$run.status}</span>
                                {/if}
                            </td>
                            <td class="text-center">{$run.processed_clients} / {$run.total_clients}</td>
                            <td>{$run.started_at|default:'-'}</td>
                            <td>{$run.completed_at|default:'-'}</td>
                            <td><strong>{$run.triggered_by}</strong></td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding: 15px 0;">No batch recalculations logged yet.</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Row 2: Security & Configuration Audits -->
    <div class="panel panel-default" style="margin-bottom: 20px;">
        <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5;"><i class="fa fa-shield"></i> Security & Operations Audit Trail</div>
        <div class="panel-body" style="padding: 0;">
            <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
                <thead>
                    <tr style="background-color: #f9f9f9;">
                        <th width="100">Client ID</th>
                        <th width="180">Action Triggered</th>
                        <th>Description</th>
                        <th width="150">Performed By</th>
                        <th width="160">Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $audits as $log}
                        <tr>
                            <td>
                                {if $log.client_id}
                                    <a href="clientssummary.php?userid={$log.client_id}">#{$log.client_id}</a>
                                    {if $log.firstname} ({$log.firstname} {$log.lastname}){/if}
                                {else}
                                    -
                                {/if}
                            </td>
                            <td><span class="label label-default" style="font-size: 10px; text-transform: uppercase;">{$log.action}</span></td>
                            <td>{$log.description}</td>
                            <td><strong>{$log.performed_by}</strong></td>
                            <td>{$log.created_at}</td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding: 15px 0;">No audit trail events logged yet.</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        {if $totalPages > 1}
            <div class="panel-footer" style="background-color: #fff; border-top: 1px solid #ddd; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted" style="font-size: 12px;">Page <strong>{$page}</strong> of {$totalPages} (Total Records: {$total})</span>
                <ul class="pagination pagination-sm" style="margin: 0;">
                    <li class="{if $page <= 1}disabled{/if}">
                        <a href="{if $page > 1}{$moduleLink}&action=audit&page={$page-1}{else}#{/if}">&laquo; Prev</a>
                    </li>
                    {for $p=1 to $totalPages}
                        <li class="{if $page == $p}active{/if}">
                            <a href="{$moduleLink}&action=audit&page={$p}">{$p}</a>
                        </li>
                    {/for}
                    <li class="{if $page >= $totalPages}disabled{/if}">
                        <a href="{if $page < $totalPages}{$moduleLink}&action=audit&page={$page+1}{else}#{/if}">Next &raquo;</a>
                    </li>
                </ul>
            </div>
        {/if}
    </div>
</div>
