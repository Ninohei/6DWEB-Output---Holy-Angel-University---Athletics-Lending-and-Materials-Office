<?php
$require_student = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

checkOverdueLoans($pdo);

// Get user stats
$stmt = $pdo->prepare("
    SELECT COUNT(*) as active_loans
    FROM loans 
    WHERE user_id = ? AND status IN ('active', 'overdue')
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Get active loans
$stmt = $pdo->prepare("
    SELECT l.*, e.name, e.code, e.image, e.location
    FROM loans l
    JOIN equipment e ON l.equipment_id = e.equipment_id
    WHERE l.user_id = ? AND l.status IN ('active', 'overdue')
    ORDER BY l.due_date ASC
");
$stmt->execute([$user_id]);
$active_loans = $stmt->fetchAll();

// Get pending requests
$stmt = $pdo->prepare("
    SELECT r.*, e.name, e.code, e.image
    FROM requests r
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.user_id = ? AND r.status = 'pending'
    ORDER BY r.request_date DESC
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetchAll();

// Get points info
$stmt = $pdo->prepare("SELECT points, points_status FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

$page_title = 'Dashboard';
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
<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>

<div class="main-content">
    <div class="breadcrumb">
        <a href="dashboard.php">Home</a> / Dashboard
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h1 class="page-title">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
        <p class="page-subtitle">Manage your equipment loans and browse available items</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-value"><?php echo $stats['active_loans']; ?></div>
            <div class="stat-card-label">Active Loans</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-value"><?php echo count($pending_requests); ?></div>
            <div class="stat-card-label">Pending Requests</div>
        </div>

        <div class="stat-card" style="border-left-color: <?php echo $user_info['points'] >= 70 ? '#28a745' : ($user_info['points'] >= 40 ? '#ffc107' : '#dc3545'); ?>;">
            <div class="stat-card-value"><?php echo $user_info['points']; ?></div>
            <div class="stat-card-label">Discipline Points</div>
        </div>
    </div>

    <!-- Points Status Banner -->
    <?php if ($user_info['points_status'] == 'warning'): ?>
        <div class="alert alert-warning">
            <strong>Warning:</strong> Your points are below 70. You are limited to 1 active loan and cannot renew equipment.
        </div>
    <?php elseif ($user_info['points_status'] == 'restricted'): ?>
        <div class="alert alert-danger">
            <strong>Account Restricted:</strong> Your points are below 40. Please visit the Athletics Office before you can borrow equipment.
        </div>
    <?php endif; ?>

    <!-- Active Loans -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Active Loans</h2>
            <a href="my-equipment.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($active_loans)): ?>
                <div class="empty-state">
                    <div class="empty-state-message">No active loans</div>
                    <div class="empty-state-description">Browse equipment to make your first request</div>
                    <div style="margin-top: 20px;">
                        <a href="browse.php" class="btn btn-primary">Browse Equipment</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Code</th>
                            <th>Checkout Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($active_loans as $loan):
                            $days_remaining = getDaysRemaining($loan['due_date']);
                        ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="../assets/images/equipment/<?php echo $loan['image']; ?>"
                                             alt="<?php echo htmlspecialchars($loan['name']); ?>"
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                             onerror="this.src='../assets/images/default.png'">
                                        <strong><?php echo htmlspecialchars($loan['name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($loan['code']); ?></td>
                                <td><?php echo formatDate($loan['checkout_date']); ?></td>
                                <td><?php echo formatDate($loan['due_date']); ?></td>
                                <td>
                                    <?php if ($days_remaining < 0): ?>
                                        <span class="badge-danger">Overdue <?php echo abs($days_remaining); ?> days</span>
                                    <?php elseif ($days_remaining <= 2): ?>
                                        <span class="badge-warning"><?php echo $days_remaining; ?> days left</span>
                                    <?php else: ?>
                                        <span class="badge-success"><?php echo $days_remaining; ?> days left</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pending Requests -->
    <?php if (!empty($pending_requests)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Pending Requests</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Requested Date</th>
                            <th>Pickup Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending_requests as $request): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="../assets/images/equipment/<?php echo $request['image']; ?>"
                                             alt="<?php echo htmlspecialchars($request['name']); ?>"
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                             onerror="this.src='../assets/images/default.png'">
                                        <strong><?php echo htmlspecialchars($request['name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo formatDateTime($request['request_date']); ?></td>
                                <td><?php echo formatDate($request['pickup_date']); ?></td>
                                <td><span class="badge-warning">Pending Approval</span></td>
                                <td>
                                    <form method="POST" action="../actions/cancel-request.php" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Cancel this request?')">
                                            Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Quick Actions</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="browse.php" class="btn btn-primary btn-block">Browse Equipment</a>
                <a href="my-equipment.php" class="btn btn-secondary btn-block">My Equipment</a>
                <a href="history.php" class="btn btn-secondary btn-block">View History</a>
                <a href="account.php" class="btn btn-secondary btn-block">My Account</a>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>