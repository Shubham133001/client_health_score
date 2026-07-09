<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$score = isset($scoreRecord['score']) ? (int)$scoreRecord['score'] : null;
$trend = $scoreRecord['trend'] ?? 'stable';
$breakdown = $scoreRecord['breakdown'] ?? [];

if ($score === null) {
    $scoreBadgeColor = 'background: #9ca3af; color: #ffffff;'; // Gray (N/A)
    $statusText = 'Unevaluated';
    $borderLeftColor = '#9ca3af';
} elseif ($score >= 80) {
    $scoreBadgeColor = 'background: #10b981; color: #ffffff;'; // Green
    $statusText = 'Healthy';
    $borderLeftColor = '#10b981';
} elseif ($score >= 50) {
    $scoreBadgeColor = 'background: #f59e0b; color: #ffffff;'; // Orange
    $statusText = 'Warning';
    $borderLeftColor = '#f59e0b';
} else {
    $scoreBadgeColor = 'background: #ef4444; color: #ffffff;'; // Red
    $statusText = 'Critical';
    $borderLeftColor = '#ef4444';
}

$trendIcon = '';
if ($trend === 'up') {
    $trendIcon = '<span style="color: #10b981; font-weight: bold; margin-left: 5px;">&uarr;</span>';
} elseif ($trend === 'down') {
    $trendIcon = '<span style="color: #ef4444; font-weight: bold; margin-left: 5px;">&darr;</span>';
} else {
    $trendIcon = '<span style="color: #6b7280; font-weight: bold; margin-left: 5px;">&rarr;</span>';
}
?>

<div class="clientsummarybox" style="margin-bottom: 15px; border-left: 4px solid <?php echo $borderLeftColor; ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 4px;">
    <div class="title" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background-color: #f3f4f6; border-bottom: 1px solid #e5e7eb;">
        <span style="font-weight: bold; font-size: 13px; color: #374151;">Client Health Score</span>
        <span style="font-size: 11px; color: #9ca3af;">Updated: <?php echo !empty($scoreRecord['updated_at']) ? date('M j, Y H:i', strtotime($scoreRecord['updated_at'])) : 'N/A'; ?></span>
    </div>
    <div style="padding: 15px; background: #ffffff; display: flex; align-items: flex-start; gap: 15px;">
        <div style="<?php echo $scoreBadgeColor; ?> width: 48px; height: 48px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 18px; font-weight: bold; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
            <?php echo $score !== null ? $score : 'N/A'; ?>
        </div>
        <div style="flex-grow: 1;">
            <div style="font-size: 13px; font-weight: bold; color: #111827; margin-bottom: 6px;">
                Status: <?php echo $statusText; ?> <?php echo $trendIcon; ?>
            </div>
            <div style="font-size: 11px; color: #4b5563; line-height: 1.5;">
                <?php if (!empty($breakdown)): ?>
                    <div style="display: grid; grid-template-columns: auto auto; gap: 4px 12px; max-width: 250px;">
                        <?php foreach ($breakdown as $key => $item): ?>
                            <?php if (isset($item['points']) && $item['points'] != 0): ?>
                                <span style="color: #6b7280;"><?php echo htmlspecialchars($item['name']); ?>:</span>
                                <span style="font-weight: bold; text-align: right; color: <?php echo $item['points'] > 0 ? '#10b981' : '#ef4444'; ?>;">
                                    <?php echo $item['points'] > 0 ? '+' : ''; ?><?php echo $item['points']; ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <span style="color: #9ca3af; font-style: italic;">No breakdown recorded. Trigger a recalculation.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
