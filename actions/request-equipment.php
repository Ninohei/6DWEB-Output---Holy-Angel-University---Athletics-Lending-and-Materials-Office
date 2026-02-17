<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ../student/browse.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$equipment_id = (int)($_POST['equipment_id'] ?? 0);
$pickup_date = sanitize($_POST['pickup_date'] ?? '');
$expected_return_date = sanitize($_POST['expected_return_date'] ?? '');
$student_notes = sanitize($_POST['student_notes'] ?? '');

// Validate inputs
if (empty($equipment_id) || empty($pickup_date) || empty($expected_return_date)) {
    $_SESSION['error'] = 'All fields are required';
    header('Location: ../student/details.php?id=' . $equipment_id);
    exit;
}

// Validate dates
$pickup = new DateTime($pickup_date);
$return = new DateTime($expected_return_date);
$today = new DateTime();
$today->setTime(0, 0, 0);

if ($pickup < $today) {
    $_SESSION['error'] = 'Pickup date cannot be in the past';
    header('Location: ../student/details.php?id=' . $equipment_id);
    exit;
}

if ($return <= $pickup) {
    $_SESSION['error'] = 'Return date must be after pickup date';
    header('Location: ../student/details.php?id=' . $equipment_id);
    exit;
}

// Check if user can borrow
$check = canUserBorrow($pdo, $user_id, $equipment_id);
if (!$check['can_borrow']) {
    $_SESSION['error'] = $check['reason'];
    header('Location: ../student/details.php?id=' . $equipment_id);
    exit;
}

// Check for duplicate pending requests
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM requests 
    WHERE user_id = ? AND equipment_id = ? AND status = 'pending'
");
$stmt->execute([$user_id, $equipment_id]);
if ($stmt->fetchColumn() > 0) {
    $_SESSION['error'] = 'You already have a pending request for this equipment';
    header('Location: ../student/details.php?id=' . $equipment_id);
    exit;
}

try {
    // Create request
    $stmt = $pdo->prepare("
        INSERT INTO requests 
        (user_id, equipment_id, pickup_date, expected_return_date, student_notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $equipment_id,
        $pickup_date,
        $expected_return_date,
        $student_notes
    ]);
    
    // Send email notification to admins
    $stmt = $pdo->prepare("
        SELECT e.name, u.first_name, u.last_name 
        FROM equipment e, users u 
        WHERE e.equipment_id = ? AND u.user_id = ?
    ");
    $stmt->execute([$equipment_id, $user_id]);
    $info = $stmt->fetch();
    
    $_SESSION['success'] = 'Request submitted successfully! Please wait for admin approval.';
    header('Location: ../student/my-equipment.php');
    
} catch (PDOException $e) {
    error_log("Request creation failed: " . $e->getMessage());
    $_SESSION['error'] = 'Request failed. Please try again.';
    header('Location: ../student/details.php?id=' . $equipment_id);
}
exit;
?>
