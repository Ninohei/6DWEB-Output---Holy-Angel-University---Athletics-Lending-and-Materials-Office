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
    header('Location: ../admin/requests.php');
    exit;
}

$request_id = (int)($_POST['request_id'] ?? 0);
$action = sanitize($_POST['action'] ?? ''); // 'approve' or 'reject'
$admin_notes = sanitize($_POST['admin_notes'] ?? '');
$rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
$admin_id = $_SESSION['user_id'];

if ($request_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = 'Invalid request';
    header('Location: ../admin/requests.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get request details
    $stmt = $pdo->prepare("
        SELECT r.*, u.email, u.first_name, e.name as equipment_name, e.quantity_available
        FROM requests r
        JOIN users u ON r.user_id = u.user_id
        JOIN equipment e ON r.equipment_id = e.equipment_id
        WHERE r.request_id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        throw new Exception('Request not found or already processed');
    }
    
    if ($action == 'approve') {
        // Check equipment availability
        if ($request['quantity_available'] < 1) {
            throw new Exception('Equipment not available');
        }
        
        // Check if user can borrow
        $check = canUserBorrow($pdo, $request['user_id'], $request['equipment_id']);
        if (!$check['can_borrow']) {
            throw new Exception($check['reason']);
        }
        
        // Update request
        $stmt = $pdo->prepare("
            UPDATE requests 
            SET status = 'approved',
                approved_by = ?,
                approval_date = NOW(),
                admin_notes = ?
            WHERE request_id = ?
        ");
        $stmt->execute([$admin_id, $admin_notes, $request_id]);
        
        // Reserve equipment (decrease available quantity)
        $stmt = $pdo->prepare("
            UPDATE equipment 
            SET quantity_available = quantity_available - 1
            WHERE equipment_id = ? AND quantity_available > 0
        ");
        $stmt->execute([$request['equipment_id']]);
        
        if ($stmt->rowCount() == 0) {
            throw new Exception('Failed to reserve equipment');
        }
        
        $pdo->commit();
        
        // Send email notification
        $message = "
            <h3>Request Approved</h3>
            <p>Dear {$request['first_name']},</p>
            <p>Your request for <strong>{$request['equipment_name']}</strong> has been approved!</p>
            <p><strong>Pickup Date:</strong> " . formatDate($request['pickup_date']) . "</p>
            <p><strong>Expected Return Date:</strong> " . formatDate($request['expected_return_date']) . "</p>
            <p>Please visit the Athletics Office on your pickup date with your student ID.</p>
            <p>Thank you for using HAU Athletics Equipment Portal.</p>
        ";
        sendEmail($request['email'], 'Request Approved - HAU Athletics', $message);
        
        $_SESSION['success'] = 'Request approved successfully!';
        
    } else { // reject
        if (empty($rejection_reason)) {
            throw new Exception('Rejection reason is required');
        }
        
        // Update request
        $stmt = $pdo->prepare("
            UPDATE requests 
            SET status = 'rejected',
                approved_by = ?,
                approval_date = NOW(),
                rejection_reason = ?,
                admin_notes = ?
            WHERE request_id = ?
        ");
        $stmt->execute([$admin_id, $rejection_reason, $admin_notes, $request_id]);
        
        $pdo->commit();
        
        // Send email notification
        $message = "
            <h3>Request Not Approved</h3>
            <p>Dear {$request['first_name']},</p>
            <p>We regret to inform you that your request for <strong>{$request['equipment_name']}</strong> could not be approved at this time.</p>
            <p><strong>Reason:</strong> {$rejection_reason}</p>
            <p>If you have questions, please contact the Athletics Office.</p>
            <p>Thank you for your understanding.</p>
        ";
        sendEmail($request['email'], 'Request Update - HAU Athletics', $message);
        
        $_SESSION['success'] = 'Request rejected with reason provided to student.';
    }
    
    header('Location: ../admin/requests.php');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Request approval failed: " . $e->getMessage());
    $_SESSION['error'] = 'Action failed: ' . $e->getMessage();
    header('Location: ../admin/requests.php');
}
exit;
?>
