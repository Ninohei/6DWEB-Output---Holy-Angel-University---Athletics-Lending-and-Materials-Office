<?php
$require_admin = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Most borrowed equipment
$stmt = $pdo->query("
    SELECT e.name, e.code, COUNT(l.loan_id) as borrow_count
    FROM equipment e
    LEFT JOIN loans l ON e.equipment_id = l.equipment_id
    GROUP BY e.equipment_id
    ORDER BY borrow_count DESC
    LIMIT 10
");
$most_borrowed = $stmt->fetchAll();

// Students with lowest points
$stmt = $pdo->query("
    SELECT first_name, last_name, student_id, points, points_status
    FROM users
    WHERE role = 'student'
    ORDER BY points ASC
    LIMIT 10
");
$lowest_points = $stmt->fetchAll();

// Monthly stats
$stmt = $pdo->query("
    SELECT DATE_FORMAT(checkout_date, '%Y-%m') as month,
           COUNT(*) as total_loans,
           SUM(CASE WHEN status = 'returned_late' THEN 1 ELSE 0 END) as late_returns
    FROM loans
    WHERE checkout_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month DESC
");
$monthly_stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports & Analytics - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Reports & Analytics</h1>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Most Borrowed Equipment</h2></div>
                <div class="card-body">
                    <table class="table">
                        <thead><tr><th>Equipment</th><th>Times Borrowed</th></tr></thead>
                        <tbody>
                            <?php foreach ($most_borrowed as $e): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($e['name']); ?><br><small><?php echo $e['code']; ?></small></td>
                                <td><strong><?php echo $e['borrow_count']; ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h2 class="card-title">Students with Lowest Points</h2></div>
                <div class="card-body">
                    <table class="table">
                        <thead><tr><th>Student</th><th>Points</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($lowest_points as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?><br><small><?php echo $s['student_id']; ?></small></td>
                                <td><strong><?php echo $s['points']; ?></strong></td>
                                <td><span class="points-badge status-<?php echo $s['points_status']; ?>"><?php echo ucfirst($s['points_status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card" style="margin-top:30px;">
            <div class="card-header"><h2 class="card-title">Monthly Statistics (Last 6 Months)</h2></div>
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>Month</th><th>Total Loans</th><th>Late Returns</th><th>On-Time %</th></tr></thead>
                    <tbody>
                        <?php foreach ($monthly_stats as $m): 
                            $on_time = $m['total_loans'] - $m['late_returns'];
                            $on_time_pct = $m['total_loans'] > 0 ? round(($on_time / $m['total_loans']) * 100) : 0;
                        ?>
                        <tr>
                            <td><?php echo $m['month']; ?></td>
                            <td><?php echo $m['total_loans']; ?></td>
                            <td><?php echo $m['late_returns']; ?></td>
                            <td><span class="badge-<?php echo $on_time_pct >= 80 ? 'success' : 'warning'; ?>"><?php echo $on_time_pct; ?>%</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
