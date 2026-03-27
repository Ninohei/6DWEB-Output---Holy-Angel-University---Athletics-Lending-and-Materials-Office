<?php
$require_student = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Latest requests
$stmt = $pdo->prepare("
    SELECT r.request_id, r.status, r.request_date, e.name AS equipment_name, e.code AS equipment_code
    FROM requests r
    JOIN equipment e ON e.equipment_id = r.equipment_id
    WHERE r.user_id = ?
    ORDER BY r.request_date DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll();

// Latest point changes
$stmt = $pdo->prepare("
        SELECT points_change, reason, action_type, processed_date
    FROM points_history
    WHERE user_id = ?
    ORDER BY processed_date DESC
    LIMIT 8
");
$stmt->execute([$user_id]);
$points = $stmt->fetchAll();

$page_title = 'Notifications';
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
            <h1 class="page-title">Notifications</h1>
            <div class="page-subtitle">Recent activity on your account</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Request Updates</div>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔔</div>
                    <div class="empty-state-message">No updates yet</div>
                    <div class="empty-state-description">Your request activity will appear here.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Equipment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                                <?php
                                    $status = strtolower($r['status'] ?? '');
                                    $badge = 'badge-secondary';
                                    if ($status === 'approved' || $status === 'returned') $badge = 'badge-success';
                                    elseif ($status === 'pending') $badge = 'badge-warning';
                                    elseif ($status === 'rejected' || $status === 'cancelled') $badge = 'badge-danger';
                                ?>
                                <tr>
                                    <td><?php echo formatDateTime($r['request_date']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($r['equipment_code']); ?></strong> — <?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                    <td><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Point Changes</div>
        </div>
        <div class="card-body">
            <?php if (empty($points)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">⭐</div>
                    <div class="empty-state-message">No point changes yet</div>
                    <div class="empty-state-description">When points change, they will show here.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Change</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($points as $p): ?>
                                <?php
                                    $chg = (int)($p['points_change'] ?? 0);
                                    $badge = $chg >= 0 ? 'badge-success' : 'badge-danger';
                                ?>
                                <tr>
                                    <td><?php echo formatDateTime($p['processed_date']); ?></td>
                                    <td><span class="badge <?php echo $badge; ?>"><?php echo $chg >= 0 ? '+' : ''; echo $chg; ?></span></td>
                                    <td><?php echo htmlspecialchars($p['reason'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
