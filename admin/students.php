<?php
$require_admin = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$filter = $_GET['filter'] ?? 'all';
$where = $filter == 'restricted' ? "AND points_status = 'restricted'" : '';

$stmt = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM loans WHERE user_id = u.user_id AND status IN ('active','overdue')) as active_loans
    FROM users u
    WHERE role = 'student' $where
    ORDER BY u.last_name, u.first_name
");
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Student Accounts (<?php echo count($students); ?>)</h1>
            <div>
                <a href="students.php" class="btn btn-sm <?php echo $filter=='all'?'btn-primary':'btn-secondary'; ?>">All</a>
                <a href="students.php?filter=restricted" class="btn btn-sm <?php echo $filter=='restricted'?'btn-danger':'btn-secondary'; ?>">Restricted</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>Student</th><th>Department</th><th>Points</th><th>Status</th><th>Active Loans</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></strong><br>
                                <small><?php echo $s['student_id']; ?> | <?php echo $s['email']; ?></small></td>
                            <td><?php echo htmlspecialchars($s['department']?:'—'); ?><br><small>Year <?php echo $s['year_level']; ?></small></td>
                            <td><span class="points-badge status-<?php echo $s['points_status']; ?>"><?php echo $s['points']; ?> pts</span></td>
                            <td><?php echo $s['status']=='suspended'?'<span class="badge-danger">Suspended</span>':'<span class="badge-success">Active</span>'; ?></td>
                            <td><?php echo $s['active_loans']; ?></td>
                            <td><button class="btn btn-sm btn-secondary" onclick="alert('Manage student ID: <?php echo $s['user_id']; ?>')">Manage</button></td>
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
