<?php
$require_admin = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Check for overdue loans
checkOverdueLoans($pdo);

// Get statistics
$stats = [];

// Pending requests
$stmt = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'");
$stats['pending_requests'] = $stmt->fetchColumn();

// Active loans
$stmt = $pdo->query("SELECT COUNT(*) FROM loans WHERE status IN ('active', 'overdue')");
$stats['active_loans'] = $stmt->fetchColumn();

// Overdue items
$stmt = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'overdue'");
$stats['overdue_count'] = $stmt->fetchColumn();

// Total equipment
$stmt = $pdo->query("SELECT COUNT(*) FROM equipment WHERE is_active = 1");
$stats['total_equipment'] = $stmt->fetchColumn();

// Available equipment
$stmt = $pdo->query("SELECT COUNT(*) FROM equipment WHERE is_active = 1 AND quantity_available > 0");
$stats['available_equipment'] = $stmt->fetchColumn();

// Total students
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$stats['total_students'] = $stmt->fetchColumn();

// Students with restrictions
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND points_status = 'restricted'");
$stats['restricted_students'] = $stmt->fetchColumn();

// Get recent activity
$stmt = $pdo->query("
    SELECT 'request' as type, r.request_id as id, r.request_date as date,
           u.first_name, u.last_name, u.student_id, e.name as equipment_name
    FROM requests r
    JOIN users u ON r.user_id = u.user_id
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.status = 'pending'
    ORDER BY r.request_date DESC
    LIMIT 5
");
$recent_requests = $stmt->fetchAll();

// Get overdue loans
$stmt = $pdo->query("
    SELECT l.*, u.first_name, u.last_name, u.student_id, u.email, u.phone, e.name, e.code
    FROM loans l
    JOIN users u ON l.user_id = u.user_id
    JOIN equipment e ON l.equipment_id = e.equipment_id
    WHERE l.status = 'overdue'
    ORDER BY l.due_date ASC
    LIMIT 10
");
$overdue_loans = $stmt->fetchAll();

$page_title = 'Admin Dashboard';
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
            <a href="dashboard.php">Admin</a> / Dashboard
        </div>
        
        <div class="page-header">
            <h1 class="page-title">Admin Dashboard</h1>
            <p class="page-subtitle">Overview of system activity and status</p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" style="border-left-color: #ffc107;">
                <div class="stat-card-icon">🔔</div>
                <div class="stat-card-value"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-card-label">Pending Requests</div>
                <a href="requests.php" style="font-size: 12px; color: #666; margin-top: 5px; display: block;">View All →</a>
            </div>
            
            <div class="stat-card" style="border-left-color: #17a2b8;">
                <div class="stat-card-icon">📦</div>
                <div class="stat-card-value"><?php echo $stats['active_loans']; ?></div>
                <div class="stat-card-label">Active Loans</div>
                <a href="checkouts.php" style="font-size: 12px; color: #666; margin-top: 5px; display: block;">View All →</a>
            </div>
            
            <div class="stat-card" style="border-left-color: #dc3545;">
                <div class="stat-card-icon">⚠️</div>
                <div class="stat-card-value"><?php echo $stats['overdue_count']; ?></div>
                <div class="stat-card-label">Overdue Items</div>
                <a href="checkouts.php?filter=overdue" style="font-size: 12px; color: #666; margin-top: 5px; display: block;">View All →</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-icon">🏀</div>
                <div class="stat-card-value"><?php echo $stats['available_equipment']; ?>/<?php echo $stats['total_equipment']; ?></div>
                <div class="stat-card-label">Available Equipment</div>
                <a href="inventory.php" style="font-size: 12px; color: #666; margin-top: 5px; display: block;">Manage →</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-icon">👥</div>
                <div class="stat-card-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-card-label">Total Students</div>
                <a href="students.php" style="font-size: 12px; color: #666; margin-top: 5px; display: block;">View All →</a>
            </div>
            
            <div class="stat-card" style="border-left-color: #dc3545;">
                <div class="stat-card-icon">🚫</div>
                <div class="stat-card-value"><?php echo $stats['restricted_students']; ?></div>
                <div class="stat-card-label">Restricted Accounts</div>
                <a href="students.php?filter=restricted" style="font-size: 12px; color: #666; margin-top: 5px; display: block;">View All →</a>
            </div>
        </div>
        
        <!-- Recent Requests & Overdue -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
            <!-- Recent Requests -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Requests</h2>
                    <a href="requests.php" class="btn btn-sm btn-secondary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_requests)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✓</div>
                            <div class="empty-state-message">No pending requests</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Equipment</th>
                                        <th>Requested</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_requests as $req): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></strong><br>
                                                <small style="color: #666;"><?php echo htmlspecialchars($req['student_id']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($req['equipment_name']); ?></td>
                                            <td><?php echo formatDateTime($req['date']); ?></td>
                                            <td>
                                                <a href="requests.php?id=<?php echo $req['id']; ?>" class="btn btn-sm btn-primary">Review</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Overdue Items -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Overdue Items</h2>
                    <a href="checkouts.php?filter=overdue" class="btn btn-sm btn-secondary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($overdue_loans)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✓</div>
                            <div class="empty-state-message">No overdue items</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Equipment</th>
                                        <th>Days Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdue_loans as $loan): 
                                        $days_overdue = getDaysLate(date('Y-m-d'), $loan['due_date']);
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></strong><br>
                                                <small style="color: #666;"><?php echo htmlspecialchars($loan['student_id']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($loan['name']); ?><br><small><?php echo htmlspecialchars($loan['code']); ?></small></td>
                                            <td><span class="badge-danger"><?php echo $days_overdue; ?> days</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card" style="margin-top: 30px;">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <a href="inventory.php?action=add" class="btn btn-primary btn-block">
                        ➕ Add Equipment
                    </a>
                    <a href="requests.php" class="btn btn-secondary btn-block">
                        📋 Review Requests
                    </a>
                    <a href="returns.php" class="btn btn-secondary btn-block">
                        ↩️ Process Return
                    </a>
                    <a href="students.php" class="btn btn-secondary btn-block">
                        👥 Manage Students
                    </a>
                    <a href="reports.php" class="btn btn-secondary btn-block">
                        📊 View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
