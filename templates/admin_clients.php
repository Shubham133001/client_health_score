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
    .chs-btn-link {
        color: #4f46e5 !important;
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        font-weight: 600;
    }
    .chs-btn-link:hover {
        text-decoration: underline;
    }
    
    .chs-filter-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
    }
    .chs-filter-form {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    .chs-filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .chs-input, .chs-select {
        padding: 8px 12px;
        font-size: 13px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background-color: #ffffff;
        color: #374151;
        min-width: 180px;
    }
    .chs-input:focus, .chs-select:focus {
        border-color: #4f46e5;
        outline: none;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }
    
    .chs-table-card {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
        overflow: hidden;
    }
    .chs-table {
        width: 100%;
        border-collapse: collapse;
    }
    .chs-table th {
        background: #f9fafb;
        padding: 12px 20px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        border-bottom: 1px solid #e5e7eb;
    }
    .chs-table td {
        padding: 14px 20px;
        font-size: 13px;
        border-bottom: 1px solid #e5e7eb;
        color: #374151;
        vertical-align: middle;
    }
    .chs-table tr.chs-clickable-row:hover td {
        background-color: #f9fafb;
    }
    
    .chs-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .chs-badge-healthy { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .chs-badge-warning { background-color: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .chs-badge-critical { background-color: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
    .chs-badge-unevaluated { background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
    
    .chs-trend-up { color: #10b981; font-weight: bold; }
    .chs-trend-down { color: #ef4444; font-weight: bold; }
    .chs-trend-stable { color: #6b7280; }
    
    /* Breakdown Detail Panel */
    .chs-detail-row td {
        padding: 0 !important;
        background: #f9fafb;
    }
    .chs-breakdown-wrapper {
        padding: 15px 25px;
        border-bottom: 1px solid #e5e7eb;
        display: none;
    }
    .chs-breakdown-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 15px;
    }
    .chs-breakdown-item {
        background: #ffffff;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        font-size: 12px;
    }
    .chs-breakdown-item-header {
        display: flex;
        justify-content: space-between;
        font-weight: bold;
        margin-bottom: 6px;
    }
    
    /* Pagination Styles */
    .chs-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: #ffffff;
        border-top: 1px solid #e5e7eb;
    }
    .chs-pagination-nav {
        display: flex;
        gap: 5px;
    }
    .chs-pagination-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #ffffff;
        color: #374151;
        text-decoration: none;
        transition: all 0.2s;
    }
    .chs-pagination-btn:hover:not(.disabled) {
        background-color: #f9fafb;
    }
    .chs-pagination-btn.active {
        background-color: #4f46e5;
        color: #ffffff;
        border-color: #4f46e5;
    }
    .chs-pagination-btn.disabled {
        color: #9ca3af;
        border-color: #e5e7eb;
        cursor: not-allowed;
    }
</style>

<div class="chs-wrapper">
    <!-- Top Header Navigation -->
    <div class="chs-header">
        <div class="chs-brand">
            <span style="font-size: 24px;">📊</span>
            <h2>Client Health Score</h2>
        </div>
        <div class="chs-nav">
            <a href="<?php echo $moduleLink; ?>&action=dashboard" class="chs-btn chs-btn-secondary">Dashboard</a>
            <a href="<?php echo $moduleLink; ?>&action=clients" class="chs-btn chs-btn-primary">Client Scores</a>
            <a href="<?php echo $moduleLink; ?>&action=rules" class="chs-btn chs-btn-secondary">Scoring Rules</a>
        </div>
    </div>

    <!-- Filters Panel -->
    <div class="chs-filter-card">
        <form method="get" action="addonmodules.php" class="chs-filter-form">
            <input type="hidden" name="module" value="client_health_score">
            <input type="hidden" name="action" value="clients">
            
            <div class="chs-filter-group">
                <input type="text" name="search" class="chs-input" placeholder="Search by ID, name, company, email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="chs-filter-group">
                <select name="status" class="chs-select">
                    <option value="">All Health Categories</option>
                    <option value="healthy" <?php echo $statusFilter === 'healthy' ? 'selected' : ''; ?>>Healthy (80+)</option>
                    <option value="warning" <?php echo $statusFilter === 'warning' ? 'selected' : ''; ?>>Warning (50-79)</option>
                    <option value="critical" <?php echo $statusFilter === 'critical' ? 'selected' : ''; ?>>Critical (<50)</option>
                    <option value="unevaluated" <?php echo $statusFilter === 'unevaluated' ? 'selected' : ''; ?>>Unevaluated</option>
                </select>
            </div>
            
            <button type="submit" class="chs-btn chs-btn-primary">Filter</button>
            <?php if (!empty($search) || !empty($statusFilter)): ?>
                <a href="<?php echo $moduleLink; ?>&action=clients" class="chs-btn chs-btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Scores Grid Table -->
    <div class="chs-table-card">
        <table class="chs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client Name</th>
                    <th>Email</th>
                    <th>Score</th>
                    <th>Trend</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #9ca3af; padding: 30px;">No clients matching your query.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clients as $c): ?>
                        <?php
                        $score = $c['score'] !== null ? (int)$c['score'] : null;
                        
                        if ($score === null) {
                            $badgeClass = 'chs-badge-unevaluated';
                            $scoreText = 'N/A';
                        } else {
                            $badgeClass = $score >= 80 ? 'chs-badge-healthy' : ($score >= 50 ? 'chs-badge-warning' : 'chs-badge-critical');
                            $scoreText = $score . '/100';
                        }
                        
                        $trendIcon = '&mdash;';
                        if ($c['trend'] === 'up') {
                            $trendIcon = '<span class="chs-trend-up">&uarr;</span>';
                        } elseif ($c['trend'] === 'down') {
                            $trendIcon = '<span class="chs-trend-down">&darr;</span>';
                        } elseif ($c['trend'] === 'stable') {
                            $trendIcon = '<span class="chs-trend-stable">&rarr;</span>';
                        }
                        
                        $displayName = trim($c['firstname'] . ' ' . $c['lastname']);
                        if ($c['companyname']) {
                            $displayName .= ' (' . $c['companyname'] . ')';
                        }
                        ?>
                        <!-- Main Client Row -->
                        <tr class="chs-clickable-row">
                            <td>#<?php echo $c['client_id']; ?></td>
                            <td>
                                <a href="clientssummary.php?userid=<?php echo $c['client_id']; ?>" style="font-weight: 600; color: #374151;">
                                    <?php echo htmlspecialchars($displayName); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($c['email']); ?></td>
                            <td>
                                <span class="chs-badge <?php echo $badgeClass; ?>" onclick="toggleBreakdown(<?php echo $c['client_id']; ?>)">
                                    <?php echo $scoreText; ?>
                                </span>
                            </td>
                            <td style="text-align: center;"><?php echo $trendIcon; ?></td>
                            <td><?php echo !empty($c['updated_at']) ? date('Y-m-d H:i', strtotime($c['updated_at'])) : 'N/A'; ?></td>
                            <td>
                                <a href="clientssummary.php?userid=<?php echo $c['client_id']; ?>" class="chs-btn-link">View Summary</a>
                            </td>
                        </tr>

                        <!-- Collapsible Score Breakdown Row -->
                        <tr id="breakdown-row-<?php echo $c['client_id']; ?>" class="chs-detail-row">
                            <td colspan="7">
                                <div id="breakdown-wrapper-<?php echo $c['client_id']; ?>" class="chs-breakdown-wrapper">
                                    <h4 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #4b5563;">Score Audit Breakdown</h4>
                                    <?php if (empty($c['breakdown'])): ?>
                                        <div style="font-style: italic; color: #9ca3af; font-size: 12px;">No calculation details logged yet. Run recalculation.</div>
                                    <?php else: ?>
                                        <div class="chs-breakdown-grid">
                                            <?php foreach ($c['breakdown'] as $ruleKey => $item): ?>
                                                <?php if (isset($item['points']) && $item['points'] != 0): ?>
                                                    <?php $isPositive = $item['points'] > 0; ?>
                                                    <div class="chs-breakdown-item">
                                                        <div class="chs-breakdown-item-header">
                                                            <span><?php echo htmlspecialchars($item['name']); ?></span>
                                                            <span style="color: <?php echo $isPositive ? '#10b981' : '#ef4444'; ?>;">
                                                                <?php echo $isPositive ? '+' : ''; ?><?php echo $item['points']; ?>
                                                            </span>
                                                        </div>
                                                        <div style="color: #6b7280; font-size: 11px;"><?php echo htmlspecialchars($item['explanation']); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <div class="chs-pagination">
            <div style="font-size: 13px; color: #6b7280;">
                Showing <strong><?php echo min($total, ($page - 1) * 20 + 1); ?></strong> to <strong><?php echo min($total, $page * 20); ?></strong> of <strong><?php echo $total; ?></strong> clients
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="chs-pagination-nav">
                    <!-- Previous Button -->
                    <a href="<?php echo $page > 1 ? $moduleLink . '&action=clients&page=' . ($page - 1) . '&search=' . urlencode($search) . '&status=' . $statusFilter : '#'; ?>" class="chs-pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">&larr;</a>
                    
                    <!-- Page Number Buttons (Show dynamic ranges if pages are high) -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<a href="' . $moduleLink . '&action=clients&page=1&search=' . urlencode($search) . '&status=' . $statusFilter . '" class="chs-pagination-btn">1</a>';
                        if ($startPage > 2) {
                            echo '<span style="padding: 0 4px; align-self: flex-end;">...</span>';
                        }
                    }
                    
                    for ($p = $startPage; $p <= $endPage; $p++) {
                        $activeClass = $p === $page ? 'active' : '';
                        echo '<a href="' . $moduleLink . '&action=clients&page=' . $p . '&search=' . urlencode($search) . '&status=' . $statusFilter . '" class="chs-pagination-btn ' . $activeClass . '">' . $p . '</a>';
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span style="padding: 0 4px; align-self: flex-end;">...</span>';
                        }
                        echo '<a href="' . $moduleLink . '&action=clients&page=' . $totalPages . '&search=' . urlencode($search) . '&status=' . $statusFilter . '" class="chs-pagination-btn">' . $totalPages . '</a>';
                    }
                    ?>
                    
                    <!-- Next Button -->
                    <a href="<?php echo $page < $totalPages ? $moduleLink . '&action=clients&page=' . ($page + 1) . '&search=' . urlencode($search) . '&status=' . $statusFilter : '#'; ?>" class="chs-pagination-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">&rarr;</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function toggleBreakdown(clientId) {
        var wrapper = document.getElementById('breakdown-wrapper-' + clientId);
        if (!wrapper) return;
        
        var isVisible = wrapper.style.display === 'block';
        
        // Hide all others to keep layout neat
        var allWrappers = document.querySelectorAll('.chs-breakdown-wrapper');
        allWrappers.forEach(function(el) {
            el.style.display = 'none';
        });
        
        if (!isVisible) {
            wrapper.style.display = 'block';
        }
    }
</script>
