<div class="row">
    <div class="col-sm-6 bordered-right">
        <div class="item">
            <div class="data color-blue" style="font-size: 28px; font-weight: bold; line-height: 1;">
                {if $average_score !== null}{$average_score}/100{else}N/A{/if}
            </div>
            <div class="note">Average Health Score</div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="item">
            <div class="data color-green" style="font-size: 28px; font-weight: bold; line-height: 1;">
                {$healthy}
            </div>
            <div class="note">Healthy Clients (80-100)</div>
        </div>
    </div>
    <div class="col-sm-6 bordered-right bordered-top">
        <div class="item">
            <div class="data color-orange" style="font-size: 28px; font-weight: bold; line-height: 1;">
                {$warning}
            </div>
            <div class="note">Warning Status (50-79)</div>
        </div>
    </div>
    <div class="col-sm-6 bordered-top">
        <div class="item">
            <div class="data color-pink" style="font-size: 28px; font-weight: bold; line-height: 1;">
                {$critical}
            </div>
            <div class="note">Critical Risk (0-49)</div>
        </div>
    </div>
</div>
<div class="widget-footer" style="padding: 10px 15px; background-color: #f8f9fa; border-top: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 4px 4px;">
    <span class="text-muted" style="font-size: 11px;">Total Evaluated: {$total_clients} clients</span>
    <a href="{$moduleLink}" class="btn btn-default btn-xs"><i class="fa fa-dashboard"></i> View Health Dashboard</a>
</div>
