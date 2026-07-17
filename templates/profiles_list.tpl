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

    <!-- 1. Scoring Profiles Management -->
    <div class="panel panel-default" style="margin-bottom: 20px;">
        <div class="panel-heading" style="font-weight: bold; background-color: #f5f5f5; display: flex; justify-content: space-between; align-items: center; padding: 10px 15px;">
            <span><i class="fa fa-sliders"></i> Scoring Profiles</span>
            <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#addProfileModal">
                <i class="fa fa-plus"></i> Add New Profile
            </button>
        </div>
        <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
            <thead>
                <tr style="background-color: #f9f9f9;">
                    <th>Profile Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th width="200" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                {foreach $profiles as $p}
                    <tr>
                        <td style="vertical-align: middle;">
                            <strong>{$p->name}</strong>
                        </td>
                        <td style="vertical-align: middle;">
                            {$p->description|default:'No description.'}
                        </td>
                        <td style="vertical-align: middle;">
                            {if $p->is_default}
                                <span class="label label-success">Global Default</span>
                            {else}
                                <span class="label label-default">Custom Profile</span>
                            {/if}
                        </td>
                        <td style="vertical-align: middle;">
                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: nowrap;">
                                <a href="{$moduleLink}&action=settings&sub=edit&id={$p->id}" class="btn btn-xs btn-default">
                                    <i class="fa fa-cog"></i> Configure
                                </a>
                                {if !$p->is_default}
                                    <a href="{$moduleLink}&action=settings&sub=delete&id={$p->id}" class="btn btn-xs btn-danger" onclick="return confirm('Are you sure you want to delete this scoring profile and all its assignments?');">
                                        <i class="fa fa-trash"></i> Delete
                                    </a>
                                {/if}
                            </div>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>

    <div class="row">
        <!-- Left Side: Profile Assignments -->
        <div class="col-md-7">
            <div class="panel panel-default" style="margin-bottom: 20px;">
                <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-exchange"></i> Profile Assignments Hierarchy</div>
                <table class="table table-striped" style="margin-bottom: 0; font-size: 12px;">
                    <thead>
                        <tr style="background-color: #f9f9f9;">
                            <th>Target Client / Group / Product</th>
                            <th>Assigned Scoring Profile</th>
                            <th width="80" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $assignments as $a}
                            <tr>
                                <td style="vertical-align: middle;"><strong>{$a.target_name}</strong></td>
                                <td style="vertical-align: middle;"><span class="label label-info">{$a.profile_name}</span></td>
                                <td style="vertical-align: middle;" class="text-center">
                                    <a href="{$moduleLink}&action=settings&sub=delete_assignment&id={$a.id}" class="btn btn-xs btn-danger" onclick="return confirm('Remove this assignment?');">
                                        <i class="fa fa-times"></i>
                                    </a>
                                </td>
                            </tr>
                        {/foreach}
                        {if empty($assignments)}
                            <tr>
                                <td colspan="3" class="text-center text-muted">No custom assignments defined. All clients use the global default profile.</td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Side: Add New Assignment Form -->
        <div class="col-md-5">
            <div class="panel panel-default" style="margin-bottom: 20px;">
                <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-plus-circle"></i> Create Profile Assignment</div>
                <div class="panel-body">
                    <form method="post" action="{$moduleLink}&action=settings&sub=add_assignment">
                        <div class="form-group">
                            <label style="font-weight: bold; font-size: 12px;">1. Select Scoring Profile</label>
                            <select name="profile_id" class="form-control input-sm" required>
                                {foreach $profiles as $p}
                                    <option value="{$p->id}">{$p->name}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-weight: bold; font-size: 12px;">2. Assignment Scope / Type</label>
                            <select name="type" id="assignmentTypeSelect" class="form-control input-sm" required onchange="toggleAssignmentFields()">
                                <option value="client">Specific Client (by ID)</option>
                                <option value="group">Client Group</option>
                                <option value="product">Product / Service</option>
                            </select>
                        </div>
                        <div class="form-group" id="clientAssignField">
                            <label style="font-weight: bold; font-size: 12px;">3. Enter WHMCS Client ID</label>
                            <input type="number" name="value" class="form-control input-sm" placeholder="e.g. 15" />
                        </div>
                        <div class="form-group" id="groupAssignField" style="display: none;">
                            <label style="font-weight: bold; font-size: 12px;">3. Select Client Group</label>
                            <select name="group_value" id="groupValueSelect" class="form-control input-sm" disabled>
                                {foreach $clientGroups as $cg}
                                    <option value="{$cg.id}">{$cg.groupname}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="form-group" id="productAssignField" style="display: none;">
                            <label style="font-weight: bold; font-size: 12px;">3. Select Product / Service</label>
                            <select name="product_value" id="productValueSelect" class="form-control input-sm" disabled>
                                {foreach $products as $pd}
                                    <option value="{$pd.id}">{$pd.name}</option>
                                {/foreach}
                            </select>
                        </div>
                        <button type="submit" class="btn btn-block btn-success btn-sm" style="font-weight: bold;">
                            <i class="fa fa-save"></i> Save Profile Assignment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Global Notification & Digest Settings -->
    <form method="post" action="{$moduleLink}&action=settings&sub=save_global">
        <div class="row">
            <!-- General Settings & Alerts -->
            <div class="col-md-6">
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-cogs"></i> Global Alert & Recalculation Settings</div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Cron Batch Recalculation Size</label>
                            <input type="number" name="global_settings[cron_batch_size]" class="form-control input-sm" value="{$settings.cron_batch_size|default:100}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Alert Cooldown (hours)</label>
                            <input type="number" name="global_settings[alert_cooldown]" class="form-control input-sm" value="{$settings.alert_cooldown|default:24}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Enable Tier Downgrade Alerts</label>
                            <select name="global_settings[alert_enable_tier]" class="form-control input-sm">
                                <option value="1" {if $settings.alert_enable_tier == '1'}selected{/if}>Yes</option>
                                <option value="0" {if $settings.alert_enable_tier == '0'}selected{/if}>No</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Enable Sudden Drop Alerts</label>
                            <select name="global_settings[alert_enable_sudden]" class="form-control input-sm">
                                <option value="1" {if $settings.alert_enable_sudden == '1'}selected{/if}>Yes (drop >= 20 pts)</option>
                                <option value="0" {if $settings.alert_enable_sudden == '0'}selected{/if}>No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Integrations & Weekly Digest Settings -->
            <div class="col-md-6">
                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-share-alt"></i> Webhook Notification Integrations</div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Slack Webhook URL</label>
                            <input type="url" name="global_settings[webhook_slack_url]" class="form-control input-sm" placeholder="https://hooks.slack.com/services/..." value="{$settings.webhook_slack_url|escape}" />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Discord Webhook URL</label>
                            <input type="url" name="global_settings[webhook_discord_url]" class="form-control input-sm" placeholder="https://discord.com/api/webhooks/..." value="{$settings.webhook_discord_url|escape}" />
                        </div>
                    </div>
                </div>

                <div class="panel panel-default" style="margin-bottom: 20px;">
                    <div class="panel-heading" style="font-weight: bold;"><i class="fa fa-envelope"></i> Weekly Digest Settings</div>
                    <div class="panel-body" style="padding: 15px;">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Enable Weekly Digest Email</label>
                            <select name="global_settings[digest_enabled]" class="form-control input-sm">
                                <option value="1" {if $settings.digest_enabled == '1'}selected{/if}>Enabled</option>
                                <option value="0" {if $settings.digest_enabled == '0'}selected{/if}>Disabled</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Digest Day of Week</label>
                            <select name="global_settings[digest_day]" class="form-control input-sm">
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
                            <input type="text" name="global_settings[digest_time]" class="form-control input-sm" placeholder="09:00" value="{$settings.digest_time|default:'09:00'}" required />
                        </div>
                        <div class="form-group" style="display: block; margin-bottom: 5px;">
                            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">Digest Recipients (comma-separated emails)</label>
                            <input type="text" name="global_settings[digest_recipients]" class="form-control input-sm" placeholder="admin@domain.com, manager@domain.com" value="{$settings.digest_recipients|escape}" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-right" style="margin-bottom: 30px;">
            <button type="submit" class="btn btn-primary" style="font-weight: bold; padding: 6px 20px;">
                <i class="fa fa-save"></i> Save Global Settings Configuration
            </button>
        </div>
    </form>

    <!-- Add Profile Modal -->
    <div class="modal fade" id="addProfileModal" tabindex="-1" role="dialog" aria-labelledby="addProfileModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="{$moduleLink}&action=settings&sub=add">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="addProfileModalLabel" style="font-weight: bold;"><i class="fa fa-plus-circle"></i> Add Scoring Profile</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label style="font-weight: bold; font-size: 12px;">Scoring Profile Name</label>
                            <input type="text" name="name" class="form-control input-sm" placeholder="e.g. VIP SaaS Clients" required />
                        </div>
                        <div class="form-group">
                            <label style="font-weight: bold; font-size: 12px;">Description</label>
                            <textarea name="description" class="form-control input-sm" rows="3" placeholder="Explain when this profile is applied..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" style="font-weight: bold;">Create Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function toggleAssignmentFields() {
    var type = document.getElementById('assignmentTypeSelect').value;
    var clientField = document.getElementById('clientAssignField');
    var groupField = document.getElementById('groupAssignField');
    var productField = document.getElementById('productAssignField');

    var clientInput = clientField.querySelector('input');
    var groupSelect = document.getElementById('groupValueSelect');
    var productSelect = document.getElementById('productValueSelect');

    if (type === 'client') {
        clientField.style.display = 'block';
        groupField.style.display = 'none';
        productField.style.display = 'none';

        clientInput.disabled = false;
        clientInput.name = 'value';
        groupSelect.disabled = true;
        productSelect.disabled = true;
    } else if (type === 'group') {
        clientField.style.display = 'none';
        groupField.style.display = 'block';
        productField.style.display = 'none';

        clientInput.disabled = true;
        groupSelect.disabled = false;
        groupSelect.name = 'value';
        productSelect.disabled = true;
    } else if (type === 'product') {
        clientField.style.display = 'none';
        groupField.style.display = 'none';
        productField.style.display = 'block';

        clientInput.disabled = true;
        groupSelect.disabled = true;
        productSelect.disabled = false;
        productSelect.name = 'value';
    }
}
</script>
