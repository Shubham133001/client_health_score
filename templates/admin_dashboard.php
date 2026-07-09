<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
?>
<style>
    .chs-wrapper {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #1f2937;
        margin: 15px 0;
    }
    .chs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        background: #ffffff;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .chs-brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .chs-brand h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #4f46e5;
    }
    .chs-nav {
        display: flex;
        gap: 8px;
    }
    .chs-btn {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s;
        border: 1px solid transparent;
        cursor: pointer;
    }
    .chs-btn-primary {
        background-color: #4f46e5;
        color: #ffffff !important;
    }
    .chs-btn-primary:hover {
        background-color: #4338ca;
    }
    .chs-btn-secondary {
        background-color: #ffffff;
        color: #374151 !important;
        border-color: #d1d5db;
    }
    .chs-btn-secondary:hover {
        background-color: #f9fafb;
    }
    .chs-btn-danger {
        background-color: #ef4444;
        color: #ffffff !important;
    }
    .chs-btn-danger:hover {
        background-color: #dc2626;
    }
    .chs-btn-link {
        color: #4f46e5 !important;
        background: none;
        border: none;
        padding: 0 8px;
    }
    .chs-btn-link:hover {
        text-decoration: underline;
    }
    .chs-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 25px;
    }
    .chs-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
        position: relative;
        overflow: hidden;
    }
    .chs-card-title {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .chs-card-value {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        line-height: 1;
    }
    .chs-card-desc {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 6px;
    }
    .chs-card-border-healthy { border-top: 4px solid #10b981; }
    .chs-card-border-warning { border-top: 4px solid #f59e0b; }
    .chs-card-border-critical { border-top: 4px solid #ef4444; }
    .chs-card-border-primary { border-top: 4px solid #4f46e5; }
    
    .chs-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    @media (max-width: 768px) {
        .chs-columns { grid-template-columns: 1fr; }
    }
    .chs-table-card {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
    }
    .chs-table-card-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chs-table-card-header h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #111827;
    }
    .chs-table {
        width: 100%;
        border-collapse: collapse;
    }
    .chs-table th {
        background: #f9fafb;
        padding: 10px 20px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        border-bottom: 1px solid #e5e7eb;
    }
    .chs-table td {
        padding: 12px 20px;
        font-size: 13px;
        border-bottom: 1px solid #e5e7eb;
        color: #374151;
        vertical-align: middle;
    }
    .chs-table tr:last-child td {
        border-bottom: none;
    }
    .chs-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 12px;
    }
    .chs-badge-healthy { background-color: #ecfdf5; color: #065f46; }
    .chs-badge-warning { background-color: #fffbeb; color: #92400e; }
    .chs-badge-critical { background-color: #fef2f2; color: #991b1b; }
    
    .chs-trend-up { color: #10b981; font-weight: bold; }
    .chs-trend-down { color: #ef4444; font-weight: bold; }
    .chs-trend-stable { color: #6b7280; }
    
    /* Progress Bar Styles */
    .chs-progress-container {
        background: #ffffff;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
        display: none;
    }
    .chs-progress-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 600;
    }
    .chs-progress-track {
        height: 12px;
        background-color: #e5e7eb;
        border-radius: 6px;
        overflow: hidden;
    }
    .chs-progress-bar {
        height: 100%;
        background-color: #4f46e5;
        width: 0%;
        transition: width 0.1s ease;
    }
</style>

<div class="chs-wrapper">
    <!-- Top Header Navigation -->
    <div class="chs-header">
        <div class="chs-brand">
            <span style="font-size: 24px;"></span>
            <h2>Client Health Score</h2>
        </div>
        <div class="chs-nav">
            <a href="<?php echo $moduleLink; ?>&action=dashboard" class="chs-btn chs-btn-primary">Dashboard</a>
            <a href="<?php echo $moduleLink; ?>&action=clients" class="chs-btn chs-btn-secondary">Client Scores</a>
            <a href="<?php echo $moduleLink; ?>&action=rules" class="chs-btn chs-btn-secondary">Scoring Rules</a>
            <button onclick="startRecalculation()" class="chs-btn chs-btn-danger">🔄 Recalculate All</button>
        </div>
    </div>

    <!-- AJAX Progress Area -->
    <div id="recalcProgress" class="chs-progress-container">
        <div class="chs-progress-header">
            <span id="recalcStatus">Initializing health score calculations...</span>
            <span id="recalcPercent">0%</span>
        </div>
        <div class="chs-progress-track">
            <div id="recalcBar" class="chs-progress-bar"></div>
        </div>
    </div>

    <!-- Quick Stats Cards Grid -->
    <div class="chs-stats-grid">
        <div class="chs-card chs-card-border-primary">
            <div class="chs-card-title">Average Health</div>
            <div class="chs-card-value"><?php echo $stats['average_score'] !== null ? $stats['average_score'] . '/100' : 'N/A'; ?></div>
            <div class="chs-card-desc">Across all evaluated clients</div>
        </div>
        <div class="chs-card chs-card-border-healthy">
            <div class="chs-card-title">Healthy (80-100)</div>
            <div class="chs-card-value"><?php echo $stats['healthy']; ?></div>
            <div class="chs-card-desc"><?php echo $stats['total_clients'] > 0 ? round(($stats['healthy'] / $stats['total_clients']) * 100, 1) : 0; ?>% of total base</div>
        </div>
        <div class="chs-card chs-card-border-warning">
            <div class="chs-card-title">Warning (50-79)</div>
            <div class="chs-card-value"><?php echo $stats['warning']; ?></div>
            <div class="chs-card-desc"><?php echo $stats['total_clients'] > 0 ? round(($stats['warning'] / $stats['total_clients']) * 100, 1) : 0; ?>% of total base</div>
        </div>
        <div class="chs-card chs-card-border-critical">
            <div class="chs-card-title">Critical (0-49)</div>
            <div class="chs-card-value"><?php echo $stats['critical']; ?></div>
            <div class="chs-card-desc"><?php echo $stats['total_clients'] > 0 ? round(($stats['critical'] / $stats['total_clients']) * 100, 1) : 0; ?>% churn risk segment</div>
        </div>
        <div class="chs-card">
            <div class="chs-card-title">Unevaluated</div>
            <div class="chs-card-value"><?php echo $stats['unevaluated']; ?></div>
            <div class="chs-card-desc">Clients without calculations</div>
        </div>
    </div>

    <!-- Main Columns -->
    <div class="chs-columns">
        <!-- Top At-Risk Clients (Critical) -->
        <div class="chs-table-card">
            <div class="chs-table-card-header">
                <h3>At-Risk Clients (Lowest Scores)</h3>
                <a href="<?php echo $moduleLink; ?>&action=clients&status=critical" class="chs-btn-link">View All</a>
            </div>
            <table class="chs-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Score</th>
                        <th>Trend</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topCritical)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #9ca3af; padding: 25px;">No critical clients found. Yay!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topCritical as $c): ?>
                            <?php
                            $score = (int)$c['score'];
                            $badgeClass = $score >= 80 ? 'chs-badge-healthy' : ($score >= 50 ? 'chs-badge-warning' : 'chs-badge-critical');
                            $trendIcon = $c['trend'] === 'up' ? '<span class="chs-trend-up">&uarr;</span>' : ($c['trend'] === 'down' ? '<span class="chs-trend-down">&darr;</span>' : '<span class="chs-trend-stable">&rarr;</span>');
                            $displayName = trim($c['firstname'] . ' ' . $c['lastname']);
                            if ($c['companyname']) {
                                $displayName .= ' (' . $c['companyname'] . ')';
                            }
                            ?>
                            <tr>
                                <td>
                                    <a href="clientssummary.php?userid=<?php echo $c['client_id']; ?>" style="font-weight: 600; color: #374151;">
                                        #<?php echo $c['client_id']; ?> - <?php echo htmlspecialchars($displayName); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="chs-badge <?php echo $badgeClass; ?>"><?php echo $score; ?>/100</span>
                                </td>
                                <td><?php echo $trendIcon; ?></td>
                                <td>
                                    <a href="clientssummary.php?userid=<?php echo $c['client_id']; ?>" class="chs-btn-link" style="padding: 0;">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Top VIP Clients (Healthy) -->
        <div class="chs-table-card">
            <div class="chs-table-card-header">
                <h3>VIP Clients (Highest Scores)</h3>
                <a href="<?php echo $moduleLink; ?>&action=clients&status=healthy" class="chs-btn-link">View All</a>
            </div>
            <table class="chs-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Score</th>
                        <th>Trend</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topHealthy)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #9ca3af; padding: 25px;">No healthy clients found. Run calculation.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topHealthy as $c): ?>
                            <?php
                            $score = (int)$c['score'];
                            $badgeClass = $score >= 80 ? 'chs-badge-healthy' : ($score >= 50 ? 'chs-badge-warning' : 'chs-badge-critical');
                            $trendIcon = $c['trend'] === 'up' ? '<span class="chs-trend-up">&uarr;</span>' : ($c['trend'] === 'down' ? '<span class="chs-trend-down">&darr;</span>' : '<span class="chs-trend-stable">&rarr;</span>');
                            $displayName = trim($c['firstname'] . ' ' . $c['lastname']);
                            if ($c['companyname']) {
                                $displayName .= ' (' . $c['companyname'] . ')';
                            }
                            ?>
                            <tr>
                                <td>
                                    <a href="clientssummary.php?userid=<?php echo $c['client_id']; ?>" style="font-weight: 600; color: #374151;">
                                        #<?php echo $c['client_id']; ?> - <?php echo htmlspecialchars($displayName); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="chs-badge <?php echo $badgeClass; ?>"><?php echo $score; ?>/100</span>
                                </td>
                                <td><?php echo $trendIcon; ?></td>
                                <td>
                                    <a href="clientssummary.php?userid=<?php echo $c['client_id']; ?>" class="chs-btn-link" style="padding: 0;">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    var isRecalculating = false;

    function startRecalculation() {
        if (isRecalculating) return;
        
        if (!confirm("Are you sure you want to recalculate health scores for all clients? For large databases, this runs in safe paginated background chunks.")) {
            return;
        }

        isRecalculating = true;
        document.getElementById('recalcProgress').style.display = 'block';
        document.getElementById('recalcStatus').innerText = "Starting batch calculations...";
        document.getElementById('recalcPercent').innerText = "0%";
        document.getElementById('recalcBar').style.width = "0%";
        
        processChunk(0);
    }

    function processChunk(offset) {
        var url = "<?php echo $moduleLink; ?>&action=ajax_recalculate&offset=" + offset;
        
        fetch(url)
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    if (data.total === 0) {
                        document.getElementById('recalcStatus').innerText = "No clients available for calculation.";
                        isRecalculating = false;
                        return;
                    }

                    var progress = Math.min(100, Math.round((data.next_offset / data.total) * 100));
                    document.getElementById('recalcBar').style.width = progress + "%";
                    document.getElementById('recalcPercent').innerText = progress + "%";
                    document.getElementById('recalcStatus').innerText = "Processed " + data.next_offset + " of " + data.total + " clients...";

                    if (data.done) {
                        document.getElementById('recalcStatus').innerHTML = "<span style='color: #10b981; font-weight: bold;'>Recalculation completed successfully!</span>";
                        isRecalculating = false;
                        // Reload dashboard statistics after 1.5 seconds
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Process next chunk
                        processChunk(data.next_offset);
                    }
                } else {
                    document.getElementById('recalcStatus').innerHTML = "<span style='color: #ef4444; font-weight: bold;'>Error: " + data.error + "</span>";
                    isRecalculating = false;
                }
            })
            .catch(function(error) {
                document.getElementById('recalcStatus').innerHTML = "<span style='color: #ef4444; font-weight: bold;'>Network Error: Failed to fetch.</span>";
                isRecalculating = false;
            });
    }
</script>
