<?php
$require_admin = false;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Load current user contact info
$stmt = $pdo->prepare("SELECT phone FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$_SESSION['user_id']]);
$me = $stmt->fetch();
$current_phone = $me['phone'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/nav.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Settings</h1>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card" style="max-width: 520px; margin-bottom: 16px;">
            <div class="card-body">
                <h3 style="margin-top:0;">Change Password</h3>
                <form action="../actions/change-password.php" method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Password must be at least 8 characters and include at least 1 uppercase letter, 1 lowercase letter, and 1 number." required>
                        <small class="text-muted">Minimum 8 characters with at least 1 uppercase letter, 1 lowercase letter, and 1 number.</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Password must be at least 8 characters and include at least 1 uppercase letter, 1 lowercase letter, and 1 number." required>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>

        <div class="card" style="max-width: 520px;">
            <div class="card-body">
                <h3 style="margin-top:0;">Update Contact Number</h3>
                <form action="../actions/update-phone.php" method="POST">
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($current_phone); ?>" placeholder="e.g. 09xx xxx xxxx">
                        <small class="text-muted">You can leave it blank to remove your contact number.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Contact Number</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
