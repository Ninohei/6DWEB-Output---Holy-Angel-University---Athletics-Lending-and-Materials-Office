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
    header('Location: ../admin/checkouts.php');
    exit;
}

$request_id = (int)($_POST['request_id'] ?? 0);
$condition_on_checkout = sanitize($_POST['condition_on_checkout'] ?? 'good');
$checkout_notes = sanitize($_POST['checkout_notes'] ?? '');
$admin_id = $_SESSION['user_id'];

if ($request_id <= 0) {
    $_SESSION['error'] = 'Invalid request';
    header('Location: ../admin/checkouts.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get approved request details
    $stmt = $pdo->prepare("
        SELECT r.*, e.max_borrow_days, e.name as equipment_name, u.email, u.first_name
        FROM requests r
        JOIN equipment e ON r.equipment_id = e.equipment_id
        JOIN users u ON r.user_id = u.user_id
        WHERE r.request_id = ? AND r.status = 'approved'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        throw new Exception('Request not found or not approved');
    }
    
    $checkout_date = date('Y-m-d H:i:s');
    $due_date = date('Y-m-d H:i:s', strtotime($request['expected_return_date'] . ' 23:59:59'));
    
    // Create loan record
    $stmt = $pdo->prepare("
        INSERT INTO loans 
        (request_id, user_id, equipment_id, checkout_date, due_date, 
         condition_on_checkout, checked_out_by, checkout_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $request_id,
        $request['user_id'],
        $request['equipment_id'],
        $checkout_date,
        $due_date,
        $condition_on_checkout,
        $admin_id,
        $checkout_notes
    ]);
    
    // Update request status
    $stmt = $pdo->prepare("UPDATE requests SET status = 'completed' WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    $pdo->commit();
    
    // Send email notification
    $message = "
        <h3>Equipment Checked Out</h3>
        <p>Dear {$request['first_name']},</p>
        <p>You have successfully checked out <strong>{$request['equipment_name']}</strong>.</p>
        <p><strong>Checkout Date:</strong> " . formatDateTime($checkout_date) . "</p>
        <p><strong>Due Date:</strong> " . formatDate($due_date) . "</p>
        <p><strong>⚠️ Important:</strong> Please return this equipment on time to maintain your discipline points.</p>
        <p>Late returns will result in point penalties.</p>
        <p>Thank you!</p>
    ";
    sendEmail($request['email'], 'Equipment Checked Out - HAU Athletics', $message);
    
    $_SESSION['success'] = 'Equipment checked out successfully!';
    header('Location: ../admin/checkouts.php');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Checkout failed: " . $e->getMessage());
    $_SESSION['error'] = 'Checkout failed: ' . $e->getMessage();
    header('Location: ../admin/checkouts.php');
}
exit;
?>
