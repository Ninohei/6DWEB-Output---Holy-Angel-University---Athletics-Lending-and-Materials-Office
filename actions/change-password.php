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
    // Redirect back depending on role
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: ../admin/settings.php');
    } else {
        header('Location: ../student/settings.php');
    }
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$current_password = (string)($_POST['current_password'] ?? '');
$new_password = (string)($_POST['new_password'] ?? '');
$confirm_password = (string)($_POST['confirm_password'] ?? '');

$redirect = (($_SESSION['role'] ?? '') === 'admin') ? '../admin/settings.php' : '../student/settings.php';

if ($user_id <= 0) {
    $_SESSION['error'] = 'Invalid session. Please log in again.';
    header('Location: ../auth/login.php');
    exit;
}

if (trim($current_password) === '' || trim($new_password) === '' || trim($confirm_password) === '') {
    $_SESSION['error'] = 'Please fill in all fields.';
    header('Location: ' . $redirect);
    exit;
}

if ($new_password !== $confirm_password) {
    $_SESSION['error'] = 'New password and confirmation do not match.';
    header('Location: ' . $redirect);
    exit;
}

if ($current_password === $new_password) {
    $_SESSION['error'] = 'New password must be different from your current password.';
    header('Location: ' . $redirect);
    exit;
}

$password_errors = validatePasswordStrength($new_password);
if (!empty($password_errors)) {
    $_SESSION['error'] = implode(' ', $password_errors);
    header('Location: ' . $redirect);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row) {
        $_SESSION['error'] = 'User not found.';
        header('Location: ' . $redirect);
        exit;
    }

    $stored = (string)$row['password'];

    // Accept either plain or hashed stored passwords
    $ok = false;
    if ($current_password === $stored) {
        $ok = true;
    } elseif (password_verify($current_password, $stored)) {
        $ok = true;
    }

    if (!$ok) {
        $_SESSION['error'] = 'Current password is incorrect.';
        header('Location: ' . $redirect);
        exit;
    }

    // Store as hash (login supports both)
    $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $up = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $up->execute([$new_hashed, $user_id]);

    $_SESSION['success'] = 'Password updated successfully.';
    header('Location: ' . $redirect);
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: ' . $redirect);
    exit;
}
?>