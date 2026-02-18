<?php
$require_student = true;
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/points_system.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get points history
$stmt = $pdo->prepare("
    SELECT ph.*, l.loan_id, e.name as equipment_name
    FROM points_history ph
    LEFT JOIN loans l ON ph.loan_id = l.loan_id
    LEFT JOIN equipment e ON l.equipment_id = e.equipment_id
    WHERE ph.user_id = ?
    ORDER BY ph.processed_date DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$points_history = $stmt->fetchAll();

$page_title = 'My Account';
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
        
        <div class="page-header">
            <h1 class="page-title">My Account</h1>
            <p class="page-subtitle">View your profile and points information</p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
            <!-- Profile Card -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Profile Information</h2>
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div class="user-avatar" style="width: 100px; height: 100px; font-size: 36px; margin: 0 auto 15px;">
                                <?php echo getUserInitials($user['first_name'], $user['last_name']); ?>
                            </div>
                            <h3 style="margin: 0;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                            <p style="color: #666; font-size: 14px; margin: 5px 0;">
                                <?php echo ucfirst($user['role']); ?>
                            </p>
                        </div>
                        
                        <table style="width: 100%; font-size: 14px;">
                            <tr>
                                <td style="padding: 8px 0; color: #666;"><strong>Student ID:</strong></td>
                                <td style="padding: 8px 0;"><?php echo htmlspecialchars($user['student_id']); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;"><strong>Email:</strong></td>
                                <td style="padding: 8px 0;"><?php echo htmlspecialchars($user['email']); ?></td>
                            </tr>
                            <?php if ($user['phone']): ?>
                            <tr>
                                <td style="padding: 8px 0; color: #666;"><strong>Phone:</strong></td>
                                <td style="padding: 8px 0;"><?php echo htmlspecialchars($user['phone']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding: 8px 0; color: #666;"><strong>Department:</strong></td>
                                <td style="padding: 8px 0;"><?php echo htmlspecialchars($user['department'] ?: 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;"><strong>Year Level:</strong></td>
                                <td style="padding: 8px 0;"><?php echo htmlspecialchars($user['year_level'] ?: 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;"><strong>Member Since:</strong></td>
                                <td style="padding: 8px 0;"><?php echo formatDate($user['created_at']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Points Status Card -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h2 class="card-title">Discipline Points</h2>
                    </div>
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 72px; font-weight: bold; color: var(--hau-maroon); margin: 20px 0;">
                            <?php echo $user['points']; ?>
                        </div>
                        
                        <div class="points-badge <?php echo 'status-' . $user['points_status']; ?>" style="font-size: 14px;">
                            <?php echo getPointsStatusIcon($user['points_status']); ?>
                            <?php echo ucfirst($user['points_status']); ?> Standing
                        </div>
                        
                        <p style="margin-top: 15px; font-size: 13px; color: #666; line-height: 1.6;">
                            <?php echo getPointsStatusMessage($user['points_status']); ?>
                        </p>
                        
                        <!-- Points Progress Bar -->
                        <div style="margin-top: 20px;">
                            <div style="background: #e9ecef; height: 10px; border-radius: 5px; overflow: hidden;">
                                <div style="background: <?php 
                                    echo $user['points'] >= 70 ? '#28a745' : ($user['points'] >= 40 ? '#ffc107' : '#dc3545'); 
                                ?>; height: 100%; width: <?php echo $user['points']; ?>%; transition: width 0.3s;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 11px; color: #999;">
                                <span>0</span>
                                <span>40</span>
                                <span>70</span>
                                <span>100</span>
                            </div>
                        </div>
                        
                        <?php if ($user['status'] == 'suspended'): ?>
                            <div class="alert alert-danger" style="margin-top: 20px; text-align: left;">
                                <strong>Account Suspended</strong><br>
                                <?php if ($user['suspension_reason']): ?>
                                    <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($user['suspension_reason']); ?></p>
                                <?php endif; ?>
                                <?php if ($user['suspended_until']): ?>
                                    <p style="margin: 5px 0 0 0;">Until: <?php echo formatDate($user['suspended_until']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Points History -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Points History</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($points_history)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📊</div>
                            <div class="empty-state-message">No points history yet</div>
                            <div class="empty-state-description">Your points changes will be recorded here</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Equipment</th>
                                        <th>Reason</th>
                                        <th>Change</th>
                                        <th>Balance</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($points_history as $history): ?>
                                        <tr>
                                            <td><?php echo formatDateTime($history['processed_date']); ?></td>
                                            <td>
                                                <?php if ($history['equipment_name']): ?>
                                                    <?php echo htmlspecialchars($history['equipment_name']); ?>
                                                <?php else: ?>
                                                    <span style="color: #999;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($history['reason']); ?></td>
                                            <td>
                                                <span style="color: <?php echo $history['points_change'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-weight: 600;">
                                                    <?php echo $history['points_change'] > 0 ? '+' : ''; ?><?php echo $history['points_change']; ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo $history['points_after']; ?></strong></td>
                                            <td>
                                                <?php
                                                $type_badges = [
                                                    'reward' => 'badge-success',
                                                    'penalty' => 'badge-danger',
                                                    'adjustment' => 'badge-warning',
                                                    'reset' => 'badge-info'
                                                ];
                                                $badge_class = $type_badges[$history['action_type']] ?? 'badge-secondary';
                                                ?>
                                                <span class="<?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($history['action_type']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($points_history) >= 50): ?>
                            <div style="text-align: center; margin-top: 15px; color: #666; font-size: 13px;">
                                Showing most recent 50 transactions
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="card" style="margin-top: 30px;">
            <div class="card-header">
                <h2 class="card-title">Understanding Points</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div>
                        <h3 style="color: #28a745; margin-bottom: 10px;">✓ How to Earn Points</h3>
                        <ul style="line-height: 2;">
                            <li>Return equipment on time: <strong>+2 points</strong></li>
                            <li>5 consecutive on-time returns: <strong>+5 bonus</strong></li>
                            <li>Clean semester (no violations): <strong>+10 points</strong></li>
                            <li>Return in better condition: <strong>+3 points</strong></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 style="color: #dc3545; margin-bottom: 10px;">✗ Point Penalties</h3>
                        <ul style="line-height: 2;">
                            <li>1 day late: <strong>-5 points</strong></li>
                            <li>2-3 days late: <strong>-10 points</strong></li>
                            <li>4-7 days late: <strong>-15 points</strong></li>
                            <li>Over 7 days late: <strong>-25 points</strong></li>
                            <li>Equipment damaged: <strong>-15 to -30 points</strong></li>
                        </ul>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <strong>💡 Tip:</strong> Keep your points above 70 to maintain full borrowing privileges and avoid restrictions!
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
