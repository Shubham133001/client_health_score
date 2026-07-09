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
    
    .chs-alert-success {
        background-color: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 13px;
        font-weight: 600;
    }
    
    .chs-rules-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    .chs-rule-card {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .chs-rule-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }
    .chs-rule-title h3 {
        margin: 0 0 4px 0;
        font-size: 15px;
        font-weight: 700;
        color: #111827;
    }
    .chs-rule-key {
        font-size: 11px;
        color: #9ca3af;
        font-family: monospace;
        background-color: #f3f4f6;
        padding: 2px 6px;
        border-radius: 4px;
    }
    .chs-rule-body {
        margin-bottom: 15px;
    }
    .chs-form-group {
        margin-bottom: 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .chs-form-group label {
        font-size: 11px;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
    }
    .chs-input {
        padding: 8px 12px;
        font-size: 13px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        width: 100%;
        background-color: #ffffff;
        color: #374151;
        box-sizing: border-box;
    }
    .chs-input:focus {
        border-color: #4f46e5;
        outline: none;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }
    
    /* Toggle switch styling */
    .chs-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }
    .chs-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .chs-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 24px;
    }
    .chs-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    input:checked + .chs-slider {
        background-color: #4f46e5;
    }
    input:checked + .chs-slider:before {
        transform: translateX(20px);
    }
    
    .chs-submit-bar {
        background: #ffffff;
        border-radius: 8px;
        padding: 15px 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
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
            <a href="<?php echo $moduleLink; ?>&action=clients" class="chs-btn chs-btn-secondary">Client Scores</a>
            <a href="<?php echo $moduleLink; ?>&action=rules" class="chs-btn chs-btn-primary">Scoring Rules</a>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if (!empty($message)): ?>
        <div class="chs-alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Rules Configuration Form -->
    <form method="post" action="<?php echo $moduleLink; ?>&action=save_rules">
        <div class="chs-rules-container">
            <?php foreach ($rules as $r): ?>
                <?php
                $key = $r['metric_key'];
                $config = json_decode($r['config'] ?? '{}', true);
                ?>
                <div class="chs-rule-card">
                    <div>
                        <div class="chs-rule-header">
                            <div class="chs-rule-title">
                                <h3><?php echo htmlspecialchars($r['metric_name']); ?></h3>
                                <span class="chs-rule-key"><?php echo htmlspecialchars($key); ?></span>
                            </div>
                            <label class="chs-switch">
                                <input type="checkbox" name="enabled[<?php echo $key; ?>]" value="1" <?php echo $r['is_enabled'] ? 'checked' : ''; ?>>
                                <span class="chs-slider"></span>
                            </label>
                        </div>
                        
                        <div class="chs-rule-body">
                            <!-- Weight Input -->
                            <div class="chs-form-group">
                                <label>Points Weighting (addition/deduction)</label>
                                <input type="number" name="weights[<?php echo $key; ?>]" step="0.5" class="chs-input" value="<?php echo (float)$r['weight']; ?>" required>
                            </div>
                            
                            <!-- Tenure Config -->
                            <?php if ($key === 'client_tenure'): ?>
                                <div class="chs-form-group">
                                    <label>Tenure Max Points Cap</label>
                                    <input type="number" name="configs[<?php echo $key; ?>][max_points]" class="chs-input" value="<?php echo isset($config['max_points']) ? (int)$config['max_points'] : 20; ?>" min="1" required>
                                </div>
                            
                            <!-- Active Services Config -->
                            <?php elseif ($key === 'active_services'): ?>
                                <div class="chs-form-group">
                                    <label>Services Max Points Cap</label>
                                    <input type="number" name="configs[<?php echo $key; ?>][max_points]" class="chs-input" value="<?php echo isset($config['max_points']) ? (int)$config['max_points'] : 30; ?>" min="1" required>
                                </div>
                            
                            <!-- Cancelled Services Config -->
                            <?php elseif ($key === 'cancelled_services'): ?>
                                <div class="chs-form-group">
                                    <label>Lookback Horizon Timeframe (days)</label>
                                    <input type="number" name="configs[<?php echo $key; ?>][lookback_days]" class="chs-input" value="<?php echo isset($config['lookback_days']) ? (int)$config['lookback_days'] : 180; ?>" min="1" required>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Form Submit Bar -->
        <div class="chs-submit-bar">
            <button type="submit" class="chs-btn chs-btn-primary">Save Rules Configuration</button>
        </div>
    </form>
</div>
