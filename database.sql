-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2026 at 05:10 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hau_athletics_portal`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_can_user_borrow` (IN `p_user_id` INT, IN `p_equipment_id` INT)   proc_main: BEGIN
    DECLARE v_points INT;
    DECLARE v_points_status VARCHAR(20);
    DECLARE v_user_status VARCHAR(20);

    DECLARE v_min_points INT;
    DECLARE v_qty INT;
    DECLARE v_active_count INT;

    -- Defaults
    SET v_active_count = 0;

    -- User info
    SELECT points, points_status, status
    INTO v_points, v_points_status, v_user_status
    FROM users
    WHERE user_id = p_user_id
    LIMIT 1;

    IF v_points IS NULL THEN
        SELECT 0 AS can_borrow, 'User not found' AS reason;
        LEAVE proc_main;
    END IF;

    IF v_user_status = 'suspended' THEN
        SELECT 0 AS can_borrow, 'Account suspended' AS reason;
        LEAVE proc_main;
    END IF;

    -- Restricted points threshold (matches POINTS_RESTRICTED_MAX = 39)
    IF v_points <= 39 THEN
        SELECT 0 AS can_borrow, 'Insufficient discipline points to borrow equipment' AS reason;
        LEAVE proc_main;
    END IF;

    -- Equipment info
    SELECT min_points_required, quantity_available
    INTO v_min_points, v_qty
    FROM equipment
    WHERE equipment_id = p_equipment_id AND is_active = 1
    LIMIT 1;

    IF v_min_points IS NULL THEN
        SELECT 0 AS can_borrow, 'Equipment not found' AS reason;
        LEAVE proc_main;
    END IF;

    IF v_qty < 1 THEN
        SELECT 0 AS can_borrow, 'Equipment not available' AS reason;
        LEAVE proc_main;
    END IF;

    IF v_points < v_min_points THEN
        SELECT 0 AS can_borrow, CONCAT('Insufficient points (need ', v_min_points, ')') AS reason;
        LEAVE proc_main;
    END IF;

    -- Status limits
    IF v_points_status = 'restricted' THEN
        SELECT 0 AS can_borrow, 'Account restricted - visit Athletics Office' AS reason;
        LEAVE proc_main;
    END IF;

    IF v_points_status = 'warning' THEN
        SELECT COUNT(*) INTO v_active_count
        FROM loans
        WHERE user_id = p_user_id AND status IN ('active', 'overdue');

        IF v_active_count >= 1 THEN
            SELECT 0 AS can_borrow, 'Warning status - maximum 1 active loan' AS reason;
            LEAVE proc_main;
        END IF;
    END IF;

    SELECT 1 AS can_borrow, '' AS reason;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_checkout_equipment` (IN `p_request_id` INT, IN `p_admin_id` INT, IN `p_condition_on_checkout` VARCHAR(20), IN `p_checkout_notes` TEXT)   proc_co: BEGIN
    DECLARE v_user_id INT;
    DECLARE v_equipment_id INT;
    DECLARE v_expected DATE;
    DECLARE v_email VARCHAR(100);
    DECLARE v_first_name VARCHAR(50);
    DECLARE v_equipment_name VARCHAR(150);
    DECLARE v_qty INT;
    DECLARE v_checkout DATETIME;
    DECLARE v_due DATETIME;

    -- Must be approved
    SELECT r.user_id, r.equipment_id, r.expected_return_date, u.email, u.first_name, e.name
    INTO v_user_id, v_equipment_id, v_expected, v_email, v_first_name, v_equipment_name
    FROM requests r
    JOIN users u ON r.user_id = u.user_id
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.request_id = p_request_id AND r.status = 'approved'
    LIMIT 1;

    IF v_user_id IS NULL THEN
        SELECT 0 AS ok, 'Request not found or not approved' AS message;
        LEAVE proc_co;
    END IF;

    SET v_checkout = NOW();
    SET v_due = STR_TO_DATE(CONCAT(DATE_FORMAT(v_expected, '%Y-%m-%d'), ' 23:59:59'), '%Y-%m-%d %H:%i:%s');

    INSERT INTO loans
    (request_id, user_id, equipment_id, checkout_date, due_date, condition_on_checkout, checked_out_by, checkout_notes)
    VALUES
    (p_request_id, v_user_id, v_equipment_id, v_checkout, v_due, p_condition_on_checkout, p_admin_id, p_checkout_notes);

    -- Mirror existing PHP behavior: decrease availability again on checkout with row lock
    SELECT quantity_available INTO v_qty
    FROM equipment
    WHERE equipment_id = v_equipment_id
    FOR UPDATE;

    IF v_qty IS NULL THEN
        SELECT 0 AS ok, 'Equipment not found' AS message;
        LEAVE proc_co;
    END IF;

    IF v_qty <= 0 THEN
        SELECT 0 AS ok, 'Equipment is no longer available' AS message;
        LEAVE proc_co;
    END IF;

    UPDATE equipment
    SET quantity_available = quantity_available - 1
    WHERE equipment_id = v_equipment_id;

    UPDATE requests SET status = 'completed' WHERE request_id = p_request_id;

    SELECT 1 AS ok, 'OK' AS message,
           LAST_INSERT_ID() AS loan_id,
           v_email AS email, v_first_name AS first_name, v_equipment_name AS equipment_name,
           v_checkout AS checkout_date, v_due AS due_date;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_request` (IN `p_user_id` INT, IN `p_equipment_id` INT, IN `p_pickup_date` DATE, IN `p_expected_return_date` DATE, IN `p_student_notes` TEXT)   proc_req: BEGIN
    DECLARE v_points INT;
    DECLARE v_points_status VARCHAR(20);
    DECLARE v_user_status VARCHAR(20);

    DECLARE v_min_points INT;
    DECLARE v_qty INT;
    DECLARE v_dup INT DEFAULT 0;
    DECLARE v_active_count INT DEFAULT 0;

    -- Duplicate pending request
    SELECT COUNT(*) INTO v_dup
    FROM requests
    WHERE user_id = p_user_id AND equipment_id = p_equipment_id AND status = 'pending';

    IF v_dup > 0 THEN
        SELECT 0 AS ok, 'You already have a pending request for this equipment' AS message, NULL AS request_id;
        LEAVE proc_req;
    END IF;

    -- User info
    SELECT points, points_status, status
    INTO v_points, v_points_status, v_user_status
    FROM users
    WHERE user_id = p_user_id
    LIMIT 1;

    IF v_points IS NULL THEN
        SELECT 0 AS ok, 'User not found' AS message, NULL AS request_id;
        LEAVE proc_req;
    END IF;

    IF v_user_status = 'suspended' THEN
        SELECT 0 AS ok, 'Account suspended' AS message, NULL AS request_id;
        LEAVE proc_req;
    END IF;

    IF v_points <= 39 THEN
        SELECT 0 AS ok, 'Insufficient discipline points to borrow equipment' AS message, NULL AS request_id;
        LEAVE proc_req;
    END IF;

    -- Equipment info
    SELECT min_points_required, quantity_available
    INTO v_min_points, v_qty
    FROM equipment
    WHERE equipment_id = p_equipment_id AND is_active = 1
    LIMIT 1;

    IF v_min_points IS NULL THEN
        SELECT 0 AS ok, 'Equipment not found' AS message, NULL AS request_id;
        LEAVE proc_req;
    END IF;

    IF v_qty < 1 THEN
        SELECT 0 AS ok, 'Equipment not available' AS message, NULL AS request_id;
        LEAVE proc_req;
    END IF;

    IF v_points < v_min_points THEN
        SELECT 0 AS ok, CONCAT('Insufficient points (need ', v_min_points, ')') AS message, NULL AS request_id;
        LEAVE proc_req;
    END IF;

    IF v_points_status = 'restricted' THEN
        SELECT 0 AS ok, 'Account restricted - visit Athletics Office' AS message, NULL AS request_id;
        LEAVE proc_req;
    END IF;

    IF v_points_status = 'warning' THEN
        SELECT COUNT(*) INTO v_active_count
        FROM loans
        WHERE user_id = p_user_id AND status IN ('active','overdue');

        IF v_active_count >= 1 THEN
            SELECT 0 AS ok, 'Warning status - maximum 1 active loan' AS message, NULL AS request_id;
            LEAVE proc_req;
        END IF;
    END IF;

    INSERT INTO requests
    (user_id, equipment_id, pickup_date, expected_return_date, student_notes)
    VALUES
    (p_user_id, p_equipment_id, p_pickup_date, p_expected_return_date, p_student_notes);

    SELECT 1 AS ok, 'Request submitted successfully! Please wait for admin approval.' AS message, LAST_INSERT_ID() AS request_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_request` (IN `p_request_id` INT, IN `p_action` VARCHAR(10), IN `p_admin_id` INT, IN `p_admin_notes` TEXT, IN `p_rejection_reason` TEXT)   proc_proc: BEGIN
    DECLARE v_user_id INT;
    DECLARE v_equipment_id INT;
    DECLARE v_qty INT;
    DECLARE v_email VARCHAR(100);
    DECLARE v_first_name VARCHAR(50);
    DECLARE v_equipment_name VARCHAR(150);
    DECLARE v_pickup DATE;
    DECLARE v_expected DATE;

    DECLARE v_points INT;
    DECLARE v_points_status VARCHAR(20);
    DECLARE v_user_status VARCHAR(20);
    DECLARE v_min_points INT;
    DECLARE v_active_count INT DEFAULT 0;

    -- Load request (must be pending)
    SELECT r.user_id, r.equipment_id, e.quantity_available, u.email, u.first_name, e.name, r.pickup_date, r.expected_return_date
    INTO v_user_id, v_equipment_id, v_qty, v_email, v_first_name, v_equipment_name, v_pickup, v_expected
    FROM requests r
    JOIN users u ON r.user_id = u.user_id
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.request_id = p_request_id AND r.status = 'pending'
    LIMIT 1;

    IF v_user_id IS NULL THEN
        SELECT 0 AS ok, 'Request not found or already processed' AS message;
        LEAVE proc_proc;
    END IF;

    IF p_action = 'approve' THEN
        IF v_qty < 1 THEN
            SELECT 0 AS ok, 'Equipment not available' AS message;
            LEAVE proc_proc;
        END IF;

        -- Eligibility check (same reasons as PHP)
        SELECT points, points_status, status
        INTO v_points, v_points_status, v_user_status
        FROM users
        WHERE user_id = v_user_id
        LIMIT 1;

        IF v_user_status = 'suspended' THEN
            SELECT 0 AS ok, 'Account suspended' AS message;
            LEAVE proc_proc;
        END IF;

        IF v_points <= 39 THEN
            SELECT 0 AS ok, 'Insufficient discipline points to borrow equipment' AS message;
            LEAVE proc_proc;
        END IF;

        SELECT min_points_required INTO v_min_points
        FROM equipment
        WHERE equipment_id = v_equipment_id
        LIMIT 1;

        IF v_points < v_min_points THEN
            SELECT 0 AS ok, CONCAT('Insufficient points (need ', v_min_points, ')') AS message;
            LEAVE proc_proc;
        END IF;

        IF v_points_status = 'restricted' THEN
            SELECT 0 AS ok, 'Account restricted - visit Athletics Office' AS message;
            LEAVE proc_proc;
        END IF;

        IF v_points_status = 'warning' THEN
            SELECT COUNT(*) INTO v_active_count
            FROM loans
            WHERE user_id = v_user_id AND status IN ('active','overdue');

            IF v_active_count >= 1 THEN
                SELECT 0 AS ok, 'Warning status - maximum 1 active loan' AS message;
                LEAVE proc_proc;
            END IF;
        END IF;

        UPDATE requests
        SET status = 'approved',
            approved_by = p_admin_id,
            approval_date = NOW(),
            admin_notes = p_admin_notes
        WHERE request_id = p_request_id;

        UPDATE equipment
        SET quantity_available = quantity_available - 1
        WHERE equipment_id = v_equipment_id AND quantity_available > 0;

        IF ROW_COUNT() = 0 THEN
            SELECT 0 AS ok, 'Failed to reserve equipment' AS message;
            LEAVE proc_proc;
        END IF;

        SELECT 1 AS ok, 'approved' AS message,
               v_email AS email, v_first_name AS first_name, v_equipment_name AS equipment_name,
               v_pickup AS pickup_date, v_expected AS expected_return_date;

    ELSEIF p_action = 'reject' THEN
        IF p_rejection_reason IS NULL OR LENGTH(TRIM(p_rejection_reason)) = 0 THEN
            SELECT 0 AS ok, 'Rejection reason is required' AS message;
            LEAVE proc_proc;
        END IF;

        UPDATE requests
        SET status = 'rejected',
            approved_by = p_admin_id,
            approval_date = NOW(),
            rejection_reason = p_rejection_reason,
            admin_notes = p_admin_notes
        WHERE request_id = p_request_id;

        SELECT 1 AS ok, 'rejected' AS message,
               v_email AS email, v_first_name AS first_name, v_equipment_name AS equipment_name,
               p_rejection_reason AS rejection_reason;
    ELSE
        SELECT 0 AS ok, 'Invalid action' AS message;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_return_equipment_update` (IN `p_loan_id` INT, IN `p_admin_id` INT, IN `p_return_status` VARCHAR(20), IN `p_days_late` INT, IN `p_condition_on_return` VARCHAR(20), IN `p_return_notes` TEXT)   proc_ret: BEGIN
    DECLARE v_equipment_id INT;
    DECLARE v_user_id INT;
    DECLARE v_due DATETIME;
    DECLARE v_condition_checkout VARCHAR(20);
    DECLARE v_code VARCHAR(50);
    DECLARE v_eq_name VARCHAR(150);
    DECLARE v_email VARCHAR(100);
    DECLARE v_first_name VARCHAR(50);
    DECLARE v_last_name VARCHAR(50);

    SELECT l.equipment_id, l.user_id, l.due_date, l.condition_on_checkout,
           e.code, e.name, u.email, u.first_name, u.last_name
    INTO v_equipment_id, v_user_id, v_due, v_condition_checkout,
         v_code, v_eq_name, v_email, v_first_name, v_last_name
    FROM loans l
    JOIN equipment e ON l.equipment_id = e.equipment_id
    JOIN users u ON l.user_id = u.user_id
    WHERE l.loan_id = p_loan_id AND l.status IN ('active','overdue')
    LIMIT 1;

    IF v_user_id IS NULL THEN
        SELECT 0 AS ok, 'Loan not found or already returned' AS message;
        LEAVE proc_ret;
    END IF;

    UPDATE loans
    SET return_date = NOW(),
        status = p_return_status,
        days_overdue = p_days_late,
        condition_on_return = p_condition_on_return,
        returned_to = p_admin_id,
        return_notes = p_return_notes
    WHERE loan_id = p_loan_id;

    UPDATE equipment
    SET quantity_available = quantity_available + 1
    WHERE equipment_id = v_equipment_id;

    SELECT 1 AS ok, 'OK' AS message,
           v_user_id AS user_id,
           v_equipment_id AS equipment_id,
           v_due AS due_date,
           v_condition_checkout AS condition_on_checkout,
           v_code AS code,
           v_eq_name AS name,
           v_email AS email,
           v_first_name AS first_name,
           v_last_name AS last_name;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_user_points` (IN `p_user_id` INT, IN `p_points_change` INT, IN `p_reason` VARCHAR(255), IN `p_action_type` VARCHAR(20), IN `p_loan_id` INT, IN `p_days_late` INT, IN `p_processed_by` INT)   proc_points: BEGIN
    DECLARE v_current_points INT;
    DECLARE v_new_points INT;
    DECLARE v_new_status VARCHAR(20);

    -- Get current points
    SELECT points INTO v_current_points
    FROM users
    WHERE user_id = p_user_id
    LIMIT 1;

    IF v_current_points IS NULL THEN
        SELECT 0 AS ok, 'User not found' AS message, NULL AS new_points;
        LEAVE proc_points;
    END IF;

    -- Cap between 0 and 100 (matches points_system.php constants)
    SET v_new_points = v_current_points + p_points_change;
    IF v_new_points < 0 THEN SET v_new_points = 0; END IF;
    IF v_new_points > 100 THEN SET v_new_points = 100; END IF;

    -- Determine status
    IF v_new_points <= 39 THEN
        SET v_new_status = 'restricted';
    ELSEIF v_new_points <= 69 THEN
        SET v_new_status = 'warning';
    ELSE
        SET v_new_status = 'good';
    END IF;

    -- Update user
    UPDATE users
    SET points = v_new_points
    WHERE user_id = p_user_id;

    -- Auto-suspend if restricted
    IF v_new_status = 'restricted' THEN
        UPDATE users
        SET status = 'suspended',
            suspension_reason = 'Points dropped below 40'
        WHERE user_id = p_user_id;
    END IF;

    -- Remove suspension if improved (previously restricted)
    IF v_new_status <> 'restricted' AND v_current_points <= 39 THEN
        UPDATE users
        SET status = 'active',
            suspended_until = NULL,
            suspension_reason = NULL
        WHERE user_id = p_user_id;
    END IF;

    -- History
    INSERT INTO points_history
    (user_id, loan_id, points_change, points_after, reason, action_type, days_late, processed_by)
    VALUES
    (p_user_id, p_loan_id, p_points_change, v_new_points, p_reason, p_action_type, p_days_late, p_processed_by);

    SELECT 1 AS ok, 'OK' AS message, v_new_points AS new_points;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `description`, `icon`, `display_order`, `is_active`) VALUES
(1, 'Ball Sports', 'Basketballs, Volleyballs, Soccer Balls', '🏀', 1, 1),
(2, 'Racket Sports', 'Tennis, Badminton, Table Tennis', '🎾', 2, 1),
(3, 'Fitness Equipment', 'Yoga Mats, Dumbbells, Jump Ropes', '💪', 3, 1),
(4, 'Outdoor Activities', 'Frisbees, Cones, Portable Goals', '🏃', 4, 1),
(5, 'Training Gear', 'Stopwatches, Whistles, Markers', '⏱️', 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('password_reset','overdue') NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `equipment_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `size_info` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT 'default.png',
  `quantity_total` int(11) NOT NULL DEFAULT 1,
  `quantity_available` int(11) NOT NULL DEFAULT 1,
  `location` varchar(100) NOT NULL,
  `condition_status` enum('excellent','good','fair','maintenance') DEFAULT 'good',
  `max_borrow_days` int(11) DEFAULT 7,
  `max_renewals` int(11) DEFAULT 2,
  `min_points_required` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`equipment_id`, `code`, `name`, `category_id`, `description`, `brand`, `size_info`, `image`, `quantity_total`, `quantity_available`, `location`, `condition_status`, `max_borrow_days`, `max_renewals`, `min_points_required`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'BB-001', 'Basketball', 1, 'Standard basketball for PE classes', 'Molten', 'Size 7', 'basketball.jpg', 8, 6, 'Main Gym', 'good', 7, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(2, 'SB-001', 'Soccer Ball', 1, 'Size 5 soccer ball for outdoor games', 'Adidas', 'Size 5', 'soccer_ball.jpg', 6, 5, 'Sports Field', 'good', 7, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(3, 'VB-001', 'Volleyball', 1, 'Indoor volleyball for school matches', 'Mikasa', 'Official', 'volleyball.jpg', 6, 5, 'Volleyball Court', 'good', 7, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(4, 'TN-001', 'Tennis Racket', 2, 'Lightweight tennis racket', 'Wilson', 'Adult', 'tennis_racket.jpg', 6, 5, 'Tennis Court', 'good', 5, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(5, 'BD-001', 'Badminton Racket Set', 2, '2 rackets with shuttlecocks', 'Yonex', 'Standard', 'badminton_set.jpg', 6, 6, 'Indoor Court', 'good', 5, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(6, 'TT-001', 'Table Tennis Paddle Set', 2, '2 paddles with 3 balls', 'Butterfly', 'Standard', 'table_tennis_set.jpg', 5, 5, 'Recreation Room', 'good', 3, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(7, 'YM-001', 'Yoga Mat', 3, 'Non-slip mat for exercises', 'Manduka', '6mm', 'yoga_mat.jpg', 12, 12, 'Fitness Center', 'good', 14, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(8, 'DB-001', 'Dumbbell Set', 3, '5kg pair dumbbells', 'CAP', '5kg', 'dumbbell_set.jpg', 6, 6, 'Weight Room', 'good', 3, 2, 50, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(9, 'JR-001', 'Jump Rope', 3, 'Adjustable jump rope', 'Nike', 'Adjustable', 'jump_rope.jpg', 10, 10, 'Fitness Center', 'good', 7, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(10, 'FR-001', 'Frisbee', 4, 'Ultimate frisbee disc', 'Discraft', '175g', 'frisbee.jpg', 10, 10, 'Equipment Room', 'good', 7, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(11, 'CN-001', 'Training Cones', 4, 'Set of 10 cones', 'Sklz', '9-inch', 'training_cones.jpg', 5, 5, 'Equipment Room', 'good', 7, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(12, 'PG-001', 'Portable Goal Set', 4, 'Small soccer goal', 'Decathlon', '4x2 ft', 'portable_goal.jpg', 2, 2, 'Storage Area', 'good', 7, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(13, 'SW-001', 'Stopwatch', 5, 'Digital stopwatch', 'Casio', 'Digital', 'stopwatch.jpg', 6, 6, 'Athletics Office', 'good', 3, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57'),
(14, 'WH-001', 'Whistle', 5, 'Referee whistle', 'Fox', 'Classic', 'whistle.jpg', 8, 8, 'Athletics Office', 'good', 3, 2, 0, 1, NULL, '2026-03-27 04:08:57', '2026-03-27 04:08:57');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `favorite_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `added_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `loan_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `checkout_date` datetime NOT NULL,
  `due_date` datetime NOT NULL,
  `return_date` datetime DEFAULT NULL,
  `status` enum('active','overdue','returned','returned_late') DEFAULT 'active',
  `renewal_count` int(11) DEFAULT 0,
  `days_overdue` int(11) DEFAULT 0,
  `condition_on_checkout` enum('excellent','good','fair') NOT NULL,
  `condition_on_return` enum('excellent','good','fair','damaged') DEFAULT NULL,
  `checked_out_by` int(11) NOT NULL,
  `returned_to` int(11) DEFAULT NULL,
  `checkout_notes` text DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `points_history`
--

CREATE TABLE `points_history` (
  `history_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `points_change` int(11) NOT NULL,
  `points_after` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `action_type` enum('reward','penalty','adjustment','reset') NOT NULL,
  `days_late` int(11) DEFAULT NULL,
  `damage_type` varchar(100) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_date` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `request_date` datetime DEFAULT current_timestamp(),
  `pickup_date` date NOT NULL,
  `expected_return_date` date NOT NULL,
  `status` enum('pending','approved','rejected','cancelled','completed') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `student_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `role` enum('student','admin') DEFAULT 'student',
  `points` int(11) DEFAULT 100,
  `status` enum('active','suspended') DEFAULT 'active',
  `points_status` enum('good','warning','restricted') GENERATED ALWAYS AS (case when `points` >= 70 then 'good' when `points` >= 40 then 'warning' else 'restricted' end) VIRTUAL,
  `is_archived` tinyint(1) DEFAULT 0,
  `suspended_until` date DEFAULT NULL,
  `suspension_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `student_id`, `first_name`, `last_name`, `email`, `password`, `phone`, `department`, `enrollment_date`, `role`, `points`, `status`, `is_archived`, `suspended_until`, `suspension_reason`, `created_at`, `last_login`) VALUES
(1, 'ADMIN001', 'Athletics', 'Department', 'athletics@hau.edu.ph', '$2y$10$Yb1R1sSG6mLSFIe4DvXR/.R5s84aHAzLIgibFdrM7JZLW2BxsDDfG', NULL, NULL, NULL, 'admin', 100, 'active', 0, NULL, NULL, '2026-03-27 04:08:57', NULL),
(2, '20000000', 'Juan', 'Dela Cruz', 'jdelacruz@hau.edu.ph', '$2y$10$M/HVhR16zEvCSDbjjEpxI.pBoEH77WN58htMubeRQaqtQl2OAv9Ym', NULL, NULL, '2024-06-15', 'student', 85, 'active', 0, NULL, NULL, '2026-03-27 04:08:57', '2026-03-27 04:10:16'),
(3, '20610432', 'Maria', 'Santos', 'msantos@hau.edu.ph', '$2y$10$M/HVhR16zEvCSDbjjEpxI.pBoEH77WN58htMubeRQaqtQl2OAv9Ym', NULL, NULL, '2023-06-15', 'student', 55, 'active', 0, NULL, NULL, '2026-03-27 04:08:57', NULL),
(4, '20618765', 'Pedro', 'Reyes', 'preyes@hau.edu.ph', '$2y$10$M/HVhR16zEvCSDbjjEpxI.pBoEH77WN58htMubeRQaqtQl2OAv9Ym', NULL, NULL, '2022-06-15', 'student', 35, 'suspended', 0, NULL, NULL, '2026-03-27 04:08:57', NULL),
(5, '20624591', 'Ana', 'Garcia', 'agarcia@hau.edu.ph', '$2y$10$M/HVhR16zEvCSDbjjEpxI.pBoEH77WN58htMubeRQaqtQl2OAv9Ym', NULL, NULL, '2025-06-15', 'student', 92, 'active', 0, NULL, NULL, '2026-03-27 04:08:57', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_type` (`user_id`,`type`),
  ADD KEY `idx_related` (`related_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`equipment_id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_active` (`is_active`,`quantity_available`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`equipment_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`loan_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `checked_out_by` (`checked_out_by`),
  ADD KEY `returned_to` (`returned_to`),
  ADD KEY `idx_user` (`user_id`,`status`),
  ADD KEY `idx_equipment` (`equipment_id`,`status`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_date` (`due_date`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_token` (`token_hash`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_used` (`used_at`);

--
-- Indexes for table `points_history`
--
ALTER TABLE `points_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_date` (`processed_date`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_user` (`user_id`,`status`),
  ADD KEY `idx_equipment` (`equipment_id`,`status`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_points` (`points`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `equipment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `points_history`
--
ALTER TABLE `points_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`) ON DELETE CASCADE;

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`),
  ADD CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `loans_ibfk_3` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`),
  ADD CONSTRAINT `loans_ibfk_4` FOREIGN KEY (`checked_out_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `loans_ibfk_5` FOREIGN KEY (`returned_to`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `points_history`
--
ALTER TABLE `points_history`
  ADD CONSTRAINT `points_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `points_history_ibfk_2` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`),
  ADD CONSTRAINT `points_history_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`),
  ADD CONSTRAINT `requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
