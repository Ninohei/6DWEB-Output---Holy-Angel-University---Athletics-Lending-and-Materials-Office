<?php
$require_student = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$page_title = 'Contact Us';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php
include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Contact Us</h1>
            <div class="page-subtitle">Athletics Department — Equipment Lending</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p style="margin-bottom:12px;">If you need help with requests, returns, or account restrictions, you can contact the Athletics Department.</p>
            <div class="form-row">
                <div>
                    <div class="form-label">Email</div>
                    <div class="card" style="margin:0;">
                        <div class="card-body" style="padding:12px 14px;">
                            athletics@hau.edu.ph
                        </div>
                    </div>
                </div>
                <div>
                    <div class="form-label">Office</div>
                    <div class="card" style="margin:0;">
                        <div class="card-body" style="padding:12px 14px;">
                            Sports Complex / Athletics Office
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:14px;" class="alert alert-info">
                <strong>Tip:</strong> Include your Student ID and the equipment code when sending a message.
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
