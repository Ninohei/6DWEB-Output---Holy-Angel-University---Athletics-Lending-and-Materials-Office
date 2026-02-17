<?php
// ===================================================================
// DISCIPLINE POINTS SYSTEM CONFIGURATION
// ===================================================================

// Starting Points
define('POINTS_START', 100);
define('POINTS_MAX', 100);
define('POINTS_MIN', 0);

// Status Thresholds
define('POINTS_GOOD_MIN', 70);
define('POINTS_WARNING_MIN', 40);
define('POINTS_RESTRICTED_MAX', 39);

// Penalties (Negative Values)
define('PENALTY_LATE_1DAY', -5);
define('PENALTY_LATE_2_3DAYS', -10);
define('PENALTY_LATE_4_7DAYS', -15);
define('PENALTY_LATE_OVER7DAYS', -25);
define('PENALTY_DAMAGE_MINOR', -15);
define('PENALTY_DAMAGE_MAJOR', -30);
define('PENALTY_LOST', -50);
define('PENALTY_NO_SHOW', -10);
define('PENALTY_REPEATED_VIOLATION', -10);

// Rewards (Positive Values)
define('REWARD_ON_TIME', 2);
define('REWARD_STREAK_5', 5);
define('REWARD_CLEAN_SEMESTER', 10);
define('REWARD_BETTER_CONDITION', 3);

/**
 * Calculate points status based on current points
 * @param int $points Current points
 * @return string Status: good, warning, or restricted
 */
function calculatePointsStatus($points) {
    if ($points >= POINTS_GOOD_MIN) return 'good';
    if ($points >= POINTS_WARNING_MIN) return 'warning';
    return 'restricted';
}

/**
 * Calculate penalty for late return
 * @param int $days_late Number of days late
 * @return int Penalty points (negative)
 */
function calculateLatePenalty($days_late) {
    if ($days_late <= 0) return 0;
    if ($days_late == 1) return PENALTY_LATE_1DAY;
    if ($days_late <= 3) return PENALTY_LATE_2_3DAYS;
    if ($days_late <= 7) return PENALTY_LATE_4_7DAYS;
    return PENALTY_LATE_OVER7DAYS;
}

/**
 * Get user-friendly message for points status
 * @param string $status Points status
 * @return string Status message
 */
function getPointsStatusMessage($status) {
    $messages = [
        'good' => 'Good Standing - Full borrowing privileges',
        'warning' => 'Warning - Limited to 1 equipment, no renewals',
        'restricted' => 'Restricted - Please visit Athletics Office'
    ];
    return $messages[$status] ?? '';
}

/**
 * Get CSS color class for points status
 * @param string $status Points status
 * @return string CSS class name
 */
function getPointsStatusColor($status) {
    $colors = [
        'good' => 'success',
        'warning' => 'warning',
        'restricted' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

/**
 * Get icon for points status
 * @param string $status Points status
 * @return string HTML icon
 */
function getPointsStatusIcon($status) {
    $icons = [
        'good' => '✓',
        'warning' => '⚠',
        'restricted' => '✕'
    ];
    return $icons[$status] ?? '●';
}

/**
 * Check if user can borrow with current points
 * @param int $points Current points
 * @param int $min_required Minimum points required for equipment
 * @return bool
 */
function canBorrowWithPoints($points, $min_required = 0) {
    return $points >= $min_required && $points >= POINTS_RESTRICTED_MAX + 1;
}

/**
 * Get maximum active loans based on points status
 * @param string $status Points status
 * @return int Maximum loans allowed
 */
function getMaxActiveLoans($status) {
    $limits = [
        'good' => 3,
        'warning' => 1,
        'restricted' => 0
    ];
    return $limits[$status] ?? 0;
}

/**
 * Calculate points for equipment return
 * @param int $days_late Days late
 * @param string $condition_checkout Condition at checkout
 * @param string $condition_return Condition at return
 * @return array ['points_change' => int, 'reason' => string]
 */
function calculateReturnPoints($days_late, $condition_checkout, $condition_return) {
    $points_change = 0;
    $reasons = [];
    
    // Late penalty or on-time reward
    if ($days_late > 0) {
        $penalty = calculateLatePenalty($days_late);
        $points_change += $penalty;
        $reasons[] = "Late return: {$days_late} day(s)";
    } else {
        $points_change += REWARD_ON_TIME;
        $reasons[] = "On-time return";
    }
    
    // Damage penalty
    if ($condition_return == 'damaged') {
        $points_change += PENALTY_DAMAGE_MINOR;
        $reasons[] = "Equipment damaged";
    }
    
    // Better condition reward
    $condition_values = ['fair' => 1, 'good' => 2, 'excellent' => 3];
    $checkout_val = $condition_values[$condition_checkout] ?? 2;
    $return_val = $condition_values[$condition_return] ?? 2;
    
    if ($return_val > $checkout_val) {
        $points_change += REWARD_BETTER_CONDITION;
        $reasons[] = "Returned in better condition";
    }
    
    return [
        'points_change' => $points_change,
        'reason' => implode(' | ', $reasons)
    ];
}
?>
