<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect = (($_SESSION['role'] ?? '') === 'admin') ? '../admin/settings.php' : '../student/settings.php';
    header('Location: ' . $redirect);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$phone = trim($_POST['phone'] ?? '');

$redirect = (($_SESSION['role'] ?? '') === 'admin') ? '../admin/settings.php' : '../student/settings.php';

if ($user_id <= 0) {
    $_SESSION['error'] = 'Invalid session. Please log in again.';
    header('Location: ../auth/login.php');
    exit;
}

// Allow blank to clear phone, otherwise validate simple format
if ($phone !== '') {
    // Keep digits, +, spaces, dash only
    if (!preg_match('/^[0-9\s\-\+]{7,20}$/', $phone)) {
        $_SESSION['error'] = 'Please enter a valid contact number.';
        header('Location: ' . $redirect);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE user_id = ?");
    $stmt->execute([$phone !== '' ? $phone : null, $user_id]);

    $_SESSION['success'] = 'Contact number updated successfully.';
    header('Location: ' . $redirect);
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: ' . $redirect);
    exit;
}
?>