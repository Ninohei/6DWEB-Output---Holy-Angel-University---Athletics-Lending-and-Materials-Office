<?php
$require_student = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Get completed loans
$stmt = $pdo->prepare("
    SELECT l.*, e.name, e.code, e.image
    FROM loans l
    JOIN equipment e ON l.equipment_id = e.equipment_id
    WHERE l.user_id = ? AND l.status IN ('returned', 'returned_late')
    ORDER BY l.return_date DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$past_loans = $stmt->fetchAll();

// Get rejected requests
$stmt = $pdo->prepare("
    SELECT r.*, e.name, e.code, e.image
    FROM requests r
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.user_id = ? AND r.status = 'rejected'
    ORDER BY r.request_date DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$rejected_requests = $stmt->fetchAll();

$page_title = 'Borrowing History';
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
        
        <div class="page-header">
            <h1 class="page-title">Borrowing History</h1>
            <p class="page-subtitle">View your past equipment loans and requests</p>
        </div>
        
        <!-- Past Loans -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Past Loans</h2>
            </div>
            <div class="card-body">
                <?php if (empty($past_loans)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📜</div>
                        <div class="empty-state-message">No borrowing history yet</div>
                        <div class="empty-state-description">Your completed loans will appear here</div>
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
                                    <th>Return Date</th>
                                    <th>Days Late</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($past_loans as $loan): 
                                    $days_late = $loan['days_overdue'];
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <img src="../assets/images/equipment/<?php echo $loan['image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($loan['name']); ?>"
                                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"
                                                     onerror="this.src='../assets/images/default.png'">
                                                <span><?php echo htmlspecialchars($loan['name']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($loan['code']); ?></td>
                                        <td><?php echo formatDate($loan['checkout_date']); ?></td>
                                        <td><?php echo formatDate($loan['due_date']); ?></td>
                                        <td><?php echo formatDate($loan['return_date']); ?></td>
                                        <td>
                                            <?php if ($days_late > 0): ?>
                                                <span class="badge-danger"><?php echo $days_late; ?> days</span>
                                            <?php else: ?>
                                                <span class="badge-success">On Time ✓</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($loan['status'] == 'returned'): ?>
                                                <span class="badge-success">Returned</span>
                                            <?php else: ?>
                                                <span class="badge-warning">Late Return</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($past_loans) >= 50): ?>
                        <div style="text-align: center; margin-top: 15px; color: #666; font-size: 13px;">
                            Showing most recent 50 loans
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Rejected Requests -->
        <?php if (!empty($rejected_requests)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Rejected Requests</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Requested</th>
                                <th>Rejection Date</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rejected_requests as $request): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="../assets/images/equipment/<?php echo $request['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($request['name']); ?>"
                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"
                                                 onerror="this.src='../assets/images/default.png'">
                                            <span><?php echo htmlspecialchars($request['name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo formatDateTime($request['request_date']); ?></td>
                                    <td><?php echo formatDateTime($request['approval_date']); ?></td>
                                    <td><?php echo htmlspecialchars($request['rejection_reason'] ?: 'No reason provided'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Summary Stats -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Statistics</h2>
            </div>
            <div class="card-body">
                <?php
                // Calculate stats
                $total_loans = count($past_loans);
                $on_time_returns = 0;
                $late_returns = 0;
                foreach ($past_loans as $loan) {
                    if ($loan['days_overdue'] == 0) {
                        $on_time_returns++;
                    } else {
                        $late_returns++;
                    }
                }
                $on_time_percentage = $total_loans > 0 ? round(($on_time_returns / $total_loans) * 100) : 0;
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 36px; font-weight: bold; color: var(--hau-maroon);"><?php echo $total_loans; ?></div>
                        <div style="font-size: 14px; color: #666;">Total Loans</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #d4edda; border-radius: 6px;">
                        <div style="font-size: 36px; font-weight: bold; color: #28a745;"><?php echo $on_time_returns; ?></div>
                        <div style="font-size: 14px; color: #155724;">On-Time Returns</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: 6px;">
                        <div style="font-size: 36px; font-weight: bold; color: #dc3545;"><?php echo $late_returns; ?></div>
                        <div style="font-size: 14px; color: #721c24;">Late Returns</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 6px;">
                        <div style="font-size: 36px; font-weight: bold; color: #856404;"><?php echo $on_time_percentage; ?>%</div>
                        <div style="font-size: 14px; color: #856404;">On-Time Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
