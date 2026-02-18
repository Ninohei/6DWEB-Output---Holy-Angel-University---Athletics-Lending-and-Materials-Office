<?php
$require_admin = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Get pending requests
$stmt = $pdo->query("
    SELECT r.*, u.first_name, u.last_name, u.student_id, u.points, u.points_status,
           e.name, e.code, e.image, e.quantity_available, e.min_points_required
    FROM requests r
    JOIN users u ON r.user_id = u.user_id
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.status = 'pending'
    ORDER BY r.request_date ASC
");
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pending Requests - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Pending Requests (<?php echo count($requests); ?>)</h1>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (empty($requests)): ?>
            <div class="card"><div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">✓</div>
                    <div class="empty-state-message">No pending requests</div>
                </div>
            </div></div>
        <?php else: ?>
            <div class="card"><div class="card-body">
                <table class="table">
                    <thead><tr>
                        <th>Student</th><th>Equipment</th><th>Dates</th><th>Points</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></strong><br>
                                <small><?php echo $r['student_id']; ?></small></td>
                            <td><?php echo htmlspecialchars($r['name']); ?><br><small><?php echo $r['code']; ?></small></td>
                            <td>Pickup: <?php echo formatDate($r['pickup_date']); ?><br>
                                Return: <?php echo formatDate($r['expected_return_date']); ?></td>
                            <td><span class="points-badge status-<?php echo $r['points_status']; ?>"><?php echo $r['points']; ?> pts</span></td>
                            <td>
                                <form method="POST" action="../actions/approve-request.php" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this request?')">Approve</button>
                                </form>
                                <button class="btn btn-sm btn-danger" onclick="rejectRequest(<?php echo $r['request_id']; ?>)">Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div>
        <?php endif; ?>
    </div>
    
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Request</h3>
                <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <form method="POST" action="../actions/approve-request.php">
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="reject_request_id">
                    <input type="hidden" name="action" value="reject">
                    <div class="form-group">
                        <label class="form-label required">Rejection Reason</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    function rejectRequest(id) {
        document.getElementById('reject_request_id').value = id;
        document.getElementById('rejectModal').classList.add('show');
    }
    </script>
</body>
</html>
