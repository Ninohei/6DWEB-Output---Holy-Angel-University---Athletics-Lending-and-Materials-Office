<?php
$require_admin = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$filter = $_GET['filter'] ?? 'all';
$where = $filter == 'overdue' ? "AND l.status = 'overdue'" : "AND l.status IN ('active', 'overdue')";

$stmt = $pdo->query("
    SELECT l.*, u.first_name, u.last_name, u.student_id, u.email,
           e.name, e.code, e.image, e.max_renewals
    FROM loans l
    JOIN users u ON l.user_id = u.user_id
    JOIN equipment e ON l.equipment_id = e.equipment_id
    WHERE 1=1 $where
    ORDER BY l.due_date ASC
");
$loans = $stmt->fetchAll();

// Get approved requests ready for checkout
$stmt = $pdo->query("
    SELECT r.*, u.first_name, u.last_name, u.student_id,
           e.name, e.code
    FROM requests r
    JOIN users u ON r.user_id = u.user_id
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.status = 'approved'
    ORDER BY r.pickup_date ASC
");
$ready_for_checkout = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Active Loans - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Active Loans & Checkouts</h1>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($ready_for_checkout)): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Ready for Checkout (<?php echo count($ready_for_checkout); ?>)</h2></div>
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>Student</th><th>Equipment</th><th>Pickup Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($ready_for_checkout as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?><br><small><?php echo $r['student_id']; ?></small></td>
                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo formatDate($r['pickup_date']); ?></td>
                            <td>
                                <form method="POST" action="../actions/checkout-equipment.php">
                                    <input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>">
                                    <select name="condition_on_checkout" class="form-control" style="display:inline;width:auto;margin-right:10px;">
                                        <option value="excellent">Excellent</option>
                                        <option value="good" selected>Good</option>
                                        <option value="fair">Fair</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-success">Checkout</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Active Loans (<?php echo count($loans); ?>)</h2>
                <div>
                    <a href="checkouts.php" class="btn btn-sm <?php echo $filter=='all'?'btn-primary':'btn-secondary'; ?>">All</a>
                    <a href="checkouts.php?filter=overdue" class="btn btn-sm <?php echo $filter=='overdue'?'btn-danger':'btn-secondary'; ?>">Overdue</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($loans)): ?>
                    <div class="empty-state"><div class="empty-state-message">No active loans</div></div>
                <?php else: ?>
                <table class="table">
                    <thead><tr><th>Student</th><th>Equipment</th><th>Due Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($loans as $l): 
                            $days_remaining = getDaysRemaining($l['due_date']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($l['first_name'].' '.$l['last_name']); ?><br><small><?php echo $l['student_id']; ?></small></td>
                            <td><?php echo htmlspecialchars($l['name']); ?><br><small><?php echo $l['code']; ?></small></td>
                            <td><?php echo formatDate($l['due_date']); ?></td>
                            <td>
                                <?php if ($days_remaining < 0): ?>
                                    <span class="badge-danger">OVERDUE <?php echo abs($days_remaining); ?> days</span>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
