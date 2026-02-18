<?php
$require_student = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Get active loans
$stmt = $pdo->prepare("
    SELECT l.*, e.name, e.code, e.image, e.location, e.max_renewals
    FROM loans l
    JOIN equipment e ON l.equipment_id = e.equipment_id
    WHERE l.user_id = ? AND l.status IN ('active', 'overdue')
    ORDER BY l.due_date ASC
");
$stmt->execute([$user_id]);
$active_loans = $stmt->fetchAll();

// Get pending requests
$stmt = $pdo->prepare("
    SELECT r.*, e.name, e.code, e.image, e.location
    FROM requests r
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.user_id = ? AND r.status = 'pending'
    ORDER BY r.request_date DESC
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetchAll();

// Get approved requests
$stmt = $pdo->prepare("
    SELECT r.*, e.name, e.code, e.image, e.location
    FROM requests r
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.user_id = ? AND r.status = 'approved'
    ORDER BY r.approval_date DESC
");
$stmt->execute([$user_id]);
$approved_requests = $stmt->fetchAll();

$page_title = 'My Equipment';
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
        <a href="dashboard.php">Home</a> / <?php echo $page_title; ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h1 class="page-title">My Equipment</h1>
        <p class="page-subtitle">Manage your active loans and pending requests</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
        <div class="stat-card">
            <div class="stat-card-value"><?php echo count($active_loans); ?></div>
            <div class="stat-card-label">Active Loans</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value"><?php echo count($pending_requests); ?></div>
            <div class="stat-card-label">Pending Requests</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value"><?php echo count($approved_requests); ?></div>
            <div class="stat-card-label">Approved (Ready to Pickup)</div>
        </div>
    </div>

    <!-- Active Loans -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Active Loans</h2>
        </div>
        <div class="card-body">
            <?php if (empty($active_loans)): ?>
                <div class="empty-state">
                    <div class="empty-state-message">No active loans</div>
                    <div class="empty-state-description">You don't have any equipment checked out right now</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Code</th>
                            <th>Location</th>
                            <th>Checkout Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Renewals</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($active_loans as $loan):
                            $days_remaining = getDaysRemaining($loan['due_date']);
                            $can_renew = ($loan['renewal_count'] < $loan['max_renewals']) && ($days_remaining >= 0);
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
                                <td><?php echo htmlspecialchars($loan['location']); ?></td>
                                <td><?php echo formatDate($loan['checkout_date']); ?></td>
                                <td><?php echo formatDate($loan['due_date']); ?></td>
                                <td>
                                    <?php if ($days_remaining < 0): ?>
                                        <span class="badge-danger">OVERDUE <?php echo abs($days_remaining); ?> days</span>
                                    <?php elseif ($days_remaining == 0): ?>
                                        <span class="badge-danger">DUE TODAY</span>
                                    <?php elseif ($days_remaining <= 2): ?>
                                        <span class="badge-warning"><?php echo $days_remaining; ?> days left</span>
                                    <?php else: ?>
                                        <span class="badge-success"><?php echo $days_remaining; ?> days left</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $loan['renewal_count']; ?> / <?php echo $loan['max_renewals']; ?>
                                    <?php if ($can_renew && $_SESSION['points_status'] == 'good'): ?>
                                        <button class="btn btn-sm btn-secondary" style="margin-left: 10px;" disabled>
                                            Renew
                                        </button>
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

    <!-- Approved Requests -->
    <?php if (!empty($approved_requests)): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Approved - Ready for Pickup</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-success">
                <strong>Great News!</strong> Your requests have been approved. Please visit the Athletics Office on your scheduled pickup date.
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Code</th>
                        <th>Pickup Date</th>
                        <th>Return Date</th>
                        <th>Approved On</th>
                        <th>Location</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($approved_requests as $request): ?>
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
                            <td><?php echo htmlspecialchars($request['code']); ?></td>
                            <td><?php echo formatDate($request['pickup_date']); ?></td>
                            <td><?php echo formatDate($request['expected_return_date']); ?></td>
                            <td><?php echo formatDateTime($request['approval_date']); ?></td>
                            <td><?php echo htmlspecialchars($request['location']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pending Requests -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Pending Requests</h2>
        </div>
        <div class="card-body">
            <?php if (empty($pending_requests)): ?>
                <div class="empty-state">
                    <div class="empty-state-message">No pending requests</div>
                    <div class="empty-state-description">Browse equipment to submit a new request</div>
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
                            <th>Requested</th>
                            <th>Pickup Date</th>
                            <th>Return Date</th>
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
                                <td><?php echo htmlspecialchars($request['code']); ?></td>
                                <td><?php echo formatDateTime($request['request_date']); ?></td>
                                <td><?php echo formatDate($request['pickup_date']); ?></td>
                                <td><?php echo formatDate($request['expected_return_date']); ?></td>
                                <td><span class="badge-warning">Pending Review</span></td>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>