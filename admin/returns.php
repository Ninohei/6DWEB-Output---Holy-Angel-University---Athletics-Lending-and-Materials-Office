<?php
$require_admin = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$stmt = $pdo->query("
    SELECT l.*, u.first_name, u.last_name, u.student_id,
           e.name, e.code, e.image
    FROM loans l
    JOIN users u ON l.user_id = u.user_id
    JOIN equipment e ON l.equipment_id = e.equipment_id
    WHERE l.status IN ('active', 'overdue')
    ORDER BY l.due_date ASC
");
$active_loans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Process Returns - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Process Returns</h1>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header"><h2 class="card-title">Items to Return (<?php echo count($active_loans); ?>)</h2></div>
            <div class="card-body">
                <?php if (empty($active_loans)): ?>
                    <div class="empty-state"><div class="empty-state-message">No items to return</div></div>
                <?php else: ?>
                <table class="table">
                    <thead><tr><th>Student</th><th>Equipment</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($active_loans as $l): 
                            $days_remaining = getDaysRemaining($l['due_date']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($l['first_name'].' '.$l['last_name']); ?><br><small><?php echo $l['student_id']; ?></small></td>
                            <td><?php echo htmlspecialchars($l['name']); ?><br><small><?php echo $l['code']; ?></small></td>
                            <td><?php echo formatDate($l['due_date']); ?></td>
                            <td>
                                <?php if ($days_remaining < 0): ?>
                                    <span class="badge-danger">OVERDUE <?php echo abs($days_remaining); ?> days</span>
                                <?php else: ?>
                                    <span class="badge-success">On Time</span>
                                <?php endif; ?>
                            </td>
                            <td><button class="btn btn-sm btn-primary" onclick="processReturn(<?php echo $l['loan_id']; ?>, '<?php echo htmlspecialchars($l['name']); ?>')">Process Return</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="modal" id="returnModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Process Return: <span id="return_equipment_name"></span></h3>
                <button class="modal-close" onclick="closeModal('returnModal')">&times;</button>
            </div>
            <form method="POST" action="../actions/return-equipment.php">
                <div class="modal-body">
                    <input type="hidden" name="loan_id" id="return_loan_id">
                    <div class="form-group">
                        <label class="form-label required">Condition on Return</label>
                        <select name="condition_on_return" class="form-control" required>
                            <option value="excellent">Excellent - Perfect condition</option>
                            <option value="good" selected>Good - Normal wear</option>
                            <option value="fair">Fair - Some wear</option>
                            <option value="damaged">Damaged - Needs repair</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Return Notes</label>
                        <textarea name="return_notes" class="form-control" rows="3" placeholder="Any issues or observations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('returnModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Complete Return</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    function processReturn(loanId, equipmentName) {
        document.getElementById('return_loan_id').value = loanId;
        document.getElementById('return_equipment_name').textContent = equipmentName;
        document.getElementById('returnModal').classList.add('show');
    }
    </script>
</body>
</html>
