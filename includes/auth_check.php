<?php
// ===================================================================
// AUTHENTICATION CHECK
// Include this file at the top of protected pages
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    header('Location: ' . SITE_URL . '/auth/login.php?timeout=1');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Check if admin-only page
if (isset($require_admin) && $require_admin === true) {
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/student/dashboard.php');
        exit;
    }
}

// Check if student-only page
if (isset($require_student) && $require_student === true) {
    if ($_SESSION['role'] !== 'student') {
        header('Location: ' . SITE_URL . '/admin/dashboard.php');
        exit;
    }
}
?>
