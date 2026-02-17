<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';

// Check authentication - admin only
$require_admin = true;
require_once '../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ../admin/returns.php');
    exit;
}

$loan_id = (int)($_POST['loan_id'] ?? 0);
$condition_on_return = sanitize($_POST['condition_on_return'] ?? '');
$return_notes = sanitize($_POST['return_notes'] ?? '');
$admin_id = $_SESSION['user_id'];

// Validate inputs
if (empty($loan_id) || empty($condition_on_return)) {
    $_SESSION['error'] = 'All required fields must be filled';
    header('Location: ../admin/returns.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get loan details
    $stmt = $pdo->prepare("
        SELECT l.*, e.code, e.name, e.equipment_id, u.email, u.first_name, u.last_name
        FROM loans l
        JOIN equipment e ON l.equipment_id = e.equipment_id
        JOIN users u ON l.user_id = u.user_id
        WHERE l.loan_id = ? AND l.status IN ('active', 'overdue')
    ");
    $stmt->execute([$loan_id]);
    $loan = $stmt->fetch();
    
    if (!$loan) {
        throw new Exception('Loan not found or already returned');
    }
    
    $return_date = date('Y-m-d H:i:s');
    $days_late = getDaysLate($return_date, $loan['due_date']);
    
    // Determine return status
    $return_status = ($days_late > 0) ? 'returned_late' : 'returned';
    
    // Update loan record
    $stmt = $pdo->prepare("
        UPDATE loans 
        SET return_date = ?,
            status = ?,
            days_overdue = ?,
            condition_on_return = ?,
            returned_to = ?,
            return_notes = ?
        WHERE loan_id = ?
    ");
    $stmt->execute([
        $return_date,
        $return_status,
        $days_late,
        $condition_on_return,
        $admin_id,
        $return_notes,
        $loan_id
    ]);
    
    // Update equipment quantity
    $stmt = $pdo->prepare("
        UPDATE equipment 
        SET quantity_available = quantity_available + 1 
        WHERE equipment_id = ?
    ");
    $stmt->execute([$loan['equipment_id']]);
    
    // Calculate points change
    $points_result = calculateReturnPoints($days_late, $loan['condition_on_checkout'], $condition_on_return);
    $points_change = $points_result['points_change'];
    $reason = $points_result['reason'];
    $action_type = ($points_change < 0) ? 'penalty' : 'reward';
    
    // Apply points change
    $new_points = updateUserPoints(
        $pdo,
        $loan['user_id'],
        $points_change,
        $reason,
        $action_type,
        $loan_id,
        $days_late,
        $admin_id
    );
    
    if ($new_points === false) {
        throw new Exception('Failed to update points');
    }
    
    $pdo->commit();
    
    // Prepare success message
    $points_msg = ($points_change > 0) ? "+{$points_change}" : $points_change;
    $_SESSION['success'] = "Equipment returned successfully. Points: {$points_msg}. Student's new balance: {$new_points}";
    
    // Send email notification
    $email_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #800000; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .points { font-size: 24px; font-weight: bold; color: " . ($points_change >= 0 ? '#28a745' : '#dc3545') . "; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Equipment Returned - HAU Athletics</h2>
                </div>
                <div class='content'>
                    <p>Dear {$loan['first_name']},</p>
                    <p>Your equipment <strong>{$loan['name']}</strong> (Code: {$loan['code']}) has been returned.</p>
                    <p><strong>Return Status:</strong> " . ucfirst(str_replace('_', ' ', $return_status)) . "</p>
                    " . ($days_late > 0 ? "<p><strong>Days Late:</strong> {$days_late}</p>" : "") . "
                    <p><strong>Points Change:</strong> <span class='points'>{$points_msg}</span></p>
                    <p><strong>Current Points:</strong> {$new_points}</p>
                    <p><strong>Reason:</strong> {$reason}</p>
                    <p>Thank you for using HAU Athletics Equipment Portal.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Holy Angel University - Athletics Department</p>
                </div>
            </div>
        </body>
        </html>
    ";
    
    sendEmail($loan['email'], 'Equipment Returned - HAU Athletics', $email_message);
    
    header('Location: ../admin/returns.php');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Return process failed: " . $e->getMessage());
    $_SESSION['error'] = 'Return failed: ' . $e->getMessage();
    header('Location: ../admin/returns.php');
}
exit;
?>
