<?php
session_start();

// Redirect based on authentication status
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // User is logged in, redirect to appropriate dashboard
    if ($_SESSION['role'] == 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
} else {
    // User not logged in, redirect to login page
    header('Location: auth/login.php');
}
exit;
?>
