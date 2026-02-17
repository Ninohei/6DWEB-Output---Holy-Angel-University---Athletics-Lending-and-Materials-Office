<?php
// ===================================================================
// HELPER FUNCTIONS
// ===================================================================

/**
 * Sanitize user input
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 * @param string $datetime Datetime string
 * @param string $format Datetime format
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = 'M d, Y g:i A') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Get days remaining until due date
 * @param string $due_date Due date
 * @return int Days remaining (negative if overdue)
 */
function getDaysRemaining($due_date) {
    $now = new DateTime();
    $due = new DateTime($due_date);
    $interval = $now->diff($due);
    
    if ($now > $due) {
        return -$interval->days;
    }
    return $interval->days;
}

/**
 * Get days late for a return
 * @param string $return_date Return date
 * @param string $due_date Due date
 * @return int Days late (0 if not late)
 */
function getDaysLate($return_date, $due_date) {
    $return = new DateTime($return_date);
    $due = new DateTime($due_date);
    
    if ($return <= $due) return 0;
    
    $interval = $due->diff($return);
    return $interval->days;
}

/**
 * Update user points and create history entry
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $points_change Points to add/subtract
 * @param string $reason Reason for change
 * @param string $action_type Type: reward, penalty, adjustment, reset
 * @param int|null $loan_id Related loan ID
 * @param int|null $days_late Days late (for penalties)
 * @param int|null $processed_by Admin user ID
 * @return int|bool New points total or false on failure
 */
function updateUserPoints($pdo, $user_id, $points_change, $reason, $action_type, $loan_id = null, $days_late = null, $processed_by = null) {
    try {
        $pdo->beginTransaction();
        
        // Get current points
        $stmt = $pdo->prepare("SELECT points FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_points = $stmt->fetchColumn();
        
        if ($current_points === false) {
            throw new Exception("User not found");
        }
        
        // Calculate new points (cap between 0 and POINTS_MAX)
        $new_points = max(POINTS_MIN, min(POINTS_MAX, $current_points + $points_change));
        
        // Update user points
        $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE user_id = ?");
        $stmt->execute([$new_points, $user_id]);
        
        // Update points status
        $new_status = calculatePointsStatus($new_points);
        $stmt = $pdo->prepare("UPDATE users SET points_status = ? WHERE user_id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        // Auto-suspend if restricted
        if ($new_status == 'restricted') {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET status = 'suspended', 
                    suspension_reason = 'Points dropped below 40' 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
        }
        
        // Remove suspension if improved
        if ($new_status != 'restricted' && $current_points <= POINTS_RESTRICTED_MAX) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET status = 'active', 
                    suspended_until = NULL, 
                    suspension_reason = NULL 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
        }
        
        // Record in points history
        $stmt = $pdo->prepare("
            INSERT INTO points_history 
            (user_id, loan_id, points_change, points_after, reason, action_type, days_late, processed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $loan_id,
            $points_change,
            $new_points,
            $reason,
            $action_type,
            $days_late,
            $processed_by
        ]);
        
        $pdo->commit();
        return $new_points;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Points update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Check for overdue loans and apply penalties
 * @param PDO $pdo Database connection
 */
function checkOverdueLoans($pdo) {
    try {
        // Find overdue loans
        $stmt = $pdo->query("
            SELECT loan_id, user_id, equipment_id, due_date 
            FROM loans 
            WHERE status = 'active' AND due_date < NOW()
        ");
        
        while ($loan = $stmt->fetch()) {
            $days_overdue = getDaysLate(date('Y-m-d H:i:s'), $loan['due_date']);
            
            // Update loan status
            $update = $pdo->prepare("
                UPDATE loans 
                SET status = 'overdue', days_overdue = ? 
                WHERE loan_id = ?
            ");
            $update->execute([$days_overdue, $loan['loan_id']]);
            
            // Auto-penalize if 7+ days overdue (only once)
            if ($days_overdue >= 7) {
                $check = $pdo->prepare("
                    SELECT COUNT(*) FROM points_history 
                    WHERE loan_id = ? AND reason LIKE '%7+ days overdue%'
                ");
                $check->execute([$loan['loan_id']]);
                
                if ($check->fetchColumn() == 0) {
                    updateUserPoints(
                        $pdo,
                        $loan['user_id'],
                        PENALTY_LATE_OVER7DAYS,
                        'Equipment overdue 7+ days - Auto penalty',
                        'penalty',
                        $loan['loan_id'],
                        $days_overdue,
                        null
                    );
                    
                    // Suspend user
                    $pdo->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ?")
                        ->execute([$loan['user_id']]);
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Overdue check failed: " . $e->getMessage());
    }
}

/**
 * Get count of active loans for user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return int Active loans count
 */
function getActiveLoansCount($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM loans 
        WHERE user_id = ? AND status IN ('active', 'overdue')
    ");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Check if user can borrow equipment
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $equipment_id Equipment ID
 * @return array ['can_borrow' => bool, 'reason' => string]
 */
function canUserBorrow($pdo, $user_id, $equipment_id) {
    // Get user info
    $stmt = $pdo->prepare("SELECT points, points_status, status FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['can_borrow' => false, 'reason' => 'User not found'];
    }
    
    if ($user['status'] == 'suspended') {
        return ['can_borrow' => false, 'reason' => 'Account suspended'];
    }
    
    // Get equipment info
    $stmt = $pdo->prepare("
        SELECT min_points_required, quantity_available 
        FROM equipment 
        WHERE equipment_id = ? AND is_active = 1
    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch();
    
    if (!$equipment) {
        return ['can_borrow' => false, 'reason' => 'Equipment not found'];
    }
    
    if ($equipment['quantity_available'] < 1) {
        return ['can_borrow' => false, 'reason' => 'Equipment not available'];
    }
    
    if ($user['points'] < $equipment['min_points_required']) {
        return ['can_borrow' => false, 'reason' => 'Insufficient points (need ' . $equipment['min_points_required'] . ')'];
    }
    
    // Check points status limits
    if ($user['points_status'] == 'restricted') {
        return ['can_borrow' => false, 'reason' => 'Account restricted - visit Athletics Office'];
    }
    
    if ($user['points_status'] == 'warning') {
        $active_count = getActiveLoansCount($pdo, $user_id);
        if ($active_count >= 1) {
            return ['can_borrow' => false, 'reason' => 'Warning status - maximum 1 active loan'];
        }
    }
    
    return ['can_borrow' => true, 'reason' => ''];
}

/**
 * Send email notification
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @return bool Success status
 */
function sendEmail($to, $subject, $message) {
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // In production, use PHPMailer or similar
    return mail($to, $subject, $message, $headers);
}

/**
 * Upload equipment image
 * @param array $file $_FILES array element
 * @return array ['success' => bool, 'filename' => string, 'message' => string]
 */
function uploadEquipmentImage($file) {
    $target_dir = __DIR__ . "/../assets/images/equipment/";
    
    // Validate file exists
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }
    
    // Validate file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Only JPG and PNG files allowed'];
    }
    
    // Validate file size (2MB)
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size must be less than 2MB'];
    }
    
    // Validate image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'message' => 'File is not a valid image'];
    }
    
    // Generate unique filename
    $new_filename = uniqid('equip_', true) . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Create directory if doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => $new_filename, 'message' => 'Upload successful'];
    }
    
    return ['success' => false, 'message' => 'Upload failed'];
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool Valid or not
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get pending requests count for admin
 * @param PDO $pdo Database connection
 * @return int Count of pending requests
 */
function getPendingRequestsCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'");
    return (int)$stmt->fetchColumn();
}

/**
 * Redirect with message
 * @param string $url Redirect URL
 * @param string $message Message
 * @param string $type Message type: success, error, warning, info
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit;
}

/**
 * Get user initials for avatar
 * @param string $first_name First name
 * @param string $last_name Last name
 * @return string Initials
 */
function getUserInitials($first_name, $last_name) {
    return strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

/**
 * Check if equipment is favorited by user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $equipment_id Equipment ID
 * @return bool Is favorited
 */
function isFavorited($pdo, $user_id, $equipment_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM favorites 
        WHERE user_id = ? AND equipment_id = ?
    ");
    $stmt->execute([$user_id, $equipment_id]);
    return $stmt->fetchColumn() > 0;
}
?>
