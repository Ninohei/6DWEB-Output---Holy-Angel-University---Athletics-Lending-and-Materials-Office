<?php
// HELPER FUNCTIONS

// Load points system constants + helpers (POINTS_MIN/MAX, calculatePointsStatus, etc.)
require_once __DIR__ . '/../config/points_system.php';

/**
 * Sanitize user input
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}


/**
 * Validate password strength.
 * Rules:
 * - at least 8 characters
 * - at least 1 uppercase letter
 * - at least 1 lowercase letter
 * - at least 1 number
 *
 * @param string $password
 * @return array List of validation error messages
 */
function validatePasswordStrength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least 1 uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least 1 lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least 1 number.';
    }

    return $errors;
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

    if ($return <= $due) {

        return 0;

    }
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
        $stmt = $pdo->prepare("CALL sp_update_user_points(?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $points_change,
            $reason,
            $action_type,
            $loan_id,
            $days_late,
            $processed_by
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$result || (int)$result['ok'] !== 1) {
            return false;
        }

        return (int)$result['new_points'];

    } catch (Exception $e) {
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
    $stmt = $pdo->prepare("CALL sp_can_user_borrow(?, ?)");
    $stmt->execute([$user_id, $equipment_id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$row) {
        return ['can_borrow' => false, 'reason' => ''];
    }

    return [
        'can_borrow' => ((int)$row['can_borrow'] === 1),
        'reason' => (string)$row['reason']
    ];
}


/**
 * Send email notification
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @return bool Success status
 */
function sendEmail($to, $subject, $message) {
    // Backward-compatible wrapper. Email is enabled ONLY for:
    // 1) overdue notifications
    // 2) password reset (handled elsewhere via includes/mailer.php)
    require_once __DIR__ . '/mailer.php';

    // Use recipient email as name if we don't have a name
    return sendMail($to, $to, $subject, $message);
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



// DATABASE SAFETY HELPERS
/**
 * Ensures tables needed for password reset + email logs exist.
 * This prevents fatal errors if the database was imported from an older SQL file.
 */
function ensurePasswordResetTables($pdo) {
    try {
        // password_resets
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                reset_id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_token (token_hash),
                INDEX idx_expires (expires_at),
                INDEX idx_used (used_at)
            ) ENGINE=InnoDB
        ");

        // email_logs (prevents repeated emails)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                log_id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                type ENUM('password_reset','overdue') NOT NULL,
                related_id INT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_user_type (user_id, type),
                INDEX idx_related (related_id),
                INDEX idx_sent_at (sent_at)
            ) ENGINE=InnoDB
        ");
    } catch (Exception $e) {
        // silent fail (do not break site)
        return;
    }
}


/**
 * Compute year level from enrollment date.
 * 0-11 months = Year 1, 1-1.99 years = Year 2, 2-2.99 years = Year 3,
 * 3-3.99 years = Year 4, 4+ years = Graduate.
 *
 * @param string|null $enrollment_date
 * @return string
 */
function getYearLevelFromEnrollmentDate($enrollment_date) {
    if (empty($enrollment_date)) {
        return 'Not specified';
    }

    try {
        $start = new DateTime($enrollment_date);
        $today = new DateTime('today');
    } catch (Exception $e) {
        return 'Not specified';
    }

    if ($start > $today) {
        return '1';
    }

    $years = (int)$start->diff($today)->y;
    if ($years >= 4) {
        return 'Graduate';
    }

    return (string)($years + 1);
}

/**
 * Returns the numeric sort value for the computed year level.
 * Year 1-4 => 1-4, Graduate => 5.
 *
 * @param string|null $enrollment_date
 * @return int
 */
function getYearLevelSortValue($enrollment_date) {
    $level = getYearLevelFromEnrollmentDate($enrollment_date);
    return $level === 'Graduate' ? 5 : (int)$level;
}

/**
 * Automatically archive students who have reached four years from enrollment date.
 *
 * @param PDO $pdo
 * @return void
 */
function autoArchiveExpiredStudents($pdo) {
    try {
        $pdo->exec("
            UPDATE users
            SET is_archived = 1
            WHERE role = 'student'
              AND COALESCE(is_archived, 0) = 0
              AND enrollment_date IS NOT NULL
              AND enrollment_date <= DATE_SUB(CURDATE(), INTERVAL 4 YEAR)
        ");
    } catch (Exception $e) {
        // Do not break the site if auto-archive cannot run.
    }
}

?>
