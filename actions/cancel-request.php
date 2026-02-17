<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ../student/dashboard.php');
    exit;
}

$request_id = (int)($_POST['request_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($request_id <= 0) {
    $_SESSION['error'] = 'Invalid request';
    header('Location: ../student/my-equipment.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Verify ownership and pending status
    $stmt = $pdo->prepare("
        SELECT r.*, e.quantity_available 
        FROM requests r
        JOIN equipment e ON r.equipment_id = e.equipment_id
        WHERE r.request_id = ? AND r.user_id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$request_id, $user_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        throw new Exception('Request not found or cannot be cancelled');
    }
    
    // Update request status
    $stmt = $pdo->prepare("UPDATE requests SET status = 'cancelled' WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    $pdo->commit();
    
    $_SESSION['success'] = 'Request cancelled successfully';
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Cancel request failed: " . $e->getMessage());
    $_SESSION['error'] = 'Cancellation failed: ' . $e->getMessage();
}

header('Location: ../student/my-equipment.php');
exit;
?>
