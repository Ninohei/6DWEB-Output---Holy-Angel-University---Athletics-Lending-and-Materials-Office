<?php
$require_student = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($equipment_id <= 0) {
    header('Location: browse.php');
    exit;
}

// Get equipment details
$stmt = $pdo->prepare("
    SELECT e.*, c.name as category_name, c.icon as category_icon
    FROM equipment e
    JOIN categories c ON e.category_id = c.category_id
    WHERE e.equipment_id = ? AND e.is_active = 1
");
$stmt->execute([$equipment_id]);
$equipment = $stmt->fetch();

if (!$equipment) {
    $_SESSION['error'] = 'Equipment not found';
    header('Location: browse.php');
    exit;
}

// Check if user can borrow
$borrow_check = canUserBorrow($pdo, $user_id, $equipment_id);

// Check for existing pending request
$stmt = $pdo->prepare("
    SELECT * FROM requests 
    WHERE user_id = ? AND equipment_id = ? AND status = 'pending'
");
$stmt->execute([$user_id, $equipment_id]);
$pending_request = $stmt->fetch();

$page_title = htmlspecialchars($equipment['name']);
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
            <a href="dashboard.php">Home</a> / <a href="browse.php">Browse</a> / <?php echo $page_title; ?>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
            <!-- Equipment Details -->
            <div class="card">
                <div class="card-body">
                    <img src="../assets/images/equipment/<?php echo htmlspecialchars($equipment['image']); ?>" 
                         alt="<?php echo htmlspecialchars($equipment['name']); ?>"
                         style="width: 100%; height: 400px; object-fit: cover; border-radius: 6px; margin-bottom: 20px;"
                         onerror="this.src='../assets/images/default.png'">
                    
                    <h1 style="font-size: 28px; margin-bottom: 10px;"><?php echo htmlspecialchars($equipment['name']); ?></h1>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <span class="badge-info"><?php echo $equipment['category_icon']; ?> <?php echo $equipment['category_name']; ?></span>
                        <span class="badge-secondary">Code: <?php echo htmlspecialchars($equipment['code']); ?></span>
                    </div>
                    
                    <div style="margin: 20px 0;">
                        <?php if ($equipment['quantity_available'] > 0): ?>
                            <span class="status-badge available">
                                ✓ Available: <?php echo $equipment['quantity_available']; ?>/<?php echo $equipment['quantity_total']; ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge unavailable">✗ Currently Unavailable</span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 style="margin-top: 25px; margin-bottom: 10px;">Description</h3>
                    <p style="line-height: 1.6; color: #666;">
                        <?php echo nl2br(htmlspecialchars($equipment['description'] ?: 'No description available.')); ?>
                    </p>
                    
                    <h3 style="margin-top: 25px; margin-bottom: 10px;">Specifications</h3>
                    <table class="table">
                        <tr>
                            <td><strong>Brand:</strong></td>
                            <td><?php echo htmlspecialchars($equipment['brand'] ?: 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Size/Info:</strong></td>
                            <td><?php echo htmlspecialchars($equipment['size_info'] ?: 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Location:</strong></td>
                            <td>📍 <?php echo htmlspecialchars($equipment['location']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Condition:</strong></td>
                            <td><?php echo ucfirst($equipment['condition_status']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Max Borrow Days:</strong></td>
                            <td><?php echo $equipment['max_borrow_days']; ?> days</td>
                        </tr>
                        <tr>
                            <td><strong>Max Renewals:</strong></td>
                            <td><?php echo $equipment['max_renewals']; ?> times</td>
                        </tr>
                        <?php if ($equipment['min_points_required'] > 0): ?>
                        <tr>
                            <td><strong>Min. Points Required:</strong></td>
                            <td><?php echo $equipment['min_points_required']; ?> points</td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <!-- Request Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Request to Borrow</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($pending_request): ?>
                            <div class="alert alert-info">
                                <strong>Pending Request</strong><br>
                                You already have a pending request for this equipment.<br>
                                Requested on: <?php echo formatDateTime($pending_request['request_date']); ?>
                            </div>
                            <a href="my-equipment.php" class="btn btn-secondary btn-block">View My Requests</a>
                        
                        <?php elseif (!$borrow_check['can_borrow']): ?>
                            <div class="alert alert-error">
                                <strong>Cannot Borrow</strong><br>
                                <?php echo htmlspecialchars($borrow_check['reason']); ?>
                            </div>
                            <?php if ($_SESSION['points_status'] == 'restricted'): ?>
                                <p style="margin-top: 15px; font-size: 14px;">
                                    Please visit the Athletics Office to resolve your account status.
                                </p>
                            <?php endif; ?>
                        
                        <?php else: ?>
                            <form method="POST" action="../actions/request-equipment.php" data-validate>
                                <input type="hidden" name="equipment_id" value="<?php echo $equipment_id; ?>">
                                
                                <div class="form-group">
                                    <label class="form-label required">Pickup Date</label>
                                    <input type="date" 
                                           id="pickup-date" 
                                           name="pickup_date" 
                                           class="form-control" 
                                           data-max-days="<?php echo $equipment['max_borrow_days']; ?>"
                                           required>
                                    <span class="form-text">When will you pick up the equipment?</span>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Expected Return Date</label>
                                    <input type="date" 
                                           id="return-date" 
                                           name="expected_return_date" 
                                           class="form-control" 
                                           required>
                                    <span class="form-text">Maximum: <?php echo $equipment['max_borrow_days']; ?> days</span>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Purpose/Notes (Optional)</label>
                                    <textarea name="student_notes" 
                                              class="form-control" 
                                              rows="3" 
                                              placeholder="e.g., For basketball practice, PE class, etc."></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="agreement-checkbox" required>
                                        <label for="agreement-checkbox">
                                            I agree to return this equipment on time and in good condition
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">
                                    Submit Request
                                </button>
                            </form>
                            
                            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 13px;">
                                <strong>📋 What happens next:</strong>
                                <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                                    <li>Your request will be reviewed by admin</li>
                                    <li>You'll receive approval/rejection notification</li>
                                    <li>Pick up equipment on your scheduled date</li>
                                    <li>Return on time to maintain your points</li>
                                </ol>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Your Points Status -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Your Status</h3>
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; padding: 15px;">
                            <div style="font-size: 48px; font-weight: bold; color: var(--hau-maroon);">
                                <?php echo $_SESSION['points']; ?>
                            </div>
                            <div style="font-size: 14px; color: #666; margin-bottom: 10px;">Discipline Points</div>
                            <span class="points-badge <?php echo 'status-' . $_SESSION['points_status']; ?>">
                                <?php echo getPointsStatusMessage($_SESSION['points_status']); ?>
                            </span>
                        </div>
                        <a href="account.php" class="btn btn-secondary btn-sm btn-block" style="margin-top: 15px;">
                            View Points History
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="browse.php" class="btn btn-secondary">← Back to Browse</a>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
