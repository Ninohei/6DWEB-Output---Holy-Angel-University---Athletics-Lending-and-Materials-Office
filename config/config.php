<?php
// ===================================================================
// GENERAL CONFIGURATION
// ===================================================================

// Site Settings
define('SITE_NAME', 'Athletics Lending and Materials Office');
define('SITE_TAGLINE', 'University Borrowing Service');
define('SITE_URL', 'http://localhost/hau-athletics-portal');

// University Branding
define('UNIVERSITY_NAME', 'Holy Angel University');
define('UNIVERSITY_LOGO', 'assets/images/logo.png');

// File Upload Settings
define('MAX_FILE_SIZE', 2097152); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('UPLOAD_PATH', __DIR__ . '/../assets/images/equipment/');

// Session Settings
define('SESSION_LIFETIME', 7200); // 2 hours

// Email Settings
define('SMTP_HOST', 'smtp.hau.edu.ph');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@hau.edu.ph');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'noreply@hau.edu.ph');
define('SMTP_FROM_NAME', 'HAU Athletics');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Timezone
date_default_timezone_set('Asia/Manila');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
