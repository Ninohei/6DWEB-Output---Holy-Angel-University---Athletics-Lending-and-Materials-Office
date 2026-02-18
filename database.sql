-- ===================================================================
-- HAU ATHLETICS EQUIPMENT PORTAL - DATABASE SCHEMA
-- ===================================================================

DROP DATABASE IF EXISTS hau_athletics_portal;
CREATE DATABASE hau_athletics_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hau_athletics_portal;

-- ===================================================================
-- 1. USERS TABLE
-- ===================================================================
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    department VARCHAR(100),
    year_level ENUM('1', '2', '3', '4', 'Graduate'),
    role ENUM('student', 'admin') DEFAULT 'student',
    points INT DEFAULT 100,
    points_status ENUM('good', 'warning', 'restricted') DEFAULT 'good',
    status ENUM('active', 'suspended') DEFAULT 'active',
    suspended_until DATE NULL,
    suspension_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_points (points),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ===================================================================
-- 2. CATEGORIES TABLE
-- ===================================================================
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- ===================================================================
-- 3. EQUIPMENT TABLE
-- ===================================================================
CREATE TABLE equipment (
    equipment_id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    brand VARCHAR(50),
    size_info VARCHAR(50),
    image VARCHAR(255) DEFAULT 'default.png',
    quantity_total INT NOT NULL DEFAULT 1,
    quantity_available INT NOT NULL DEFAULT 1,
    location VARCHAR(100) NOT NULL,
    condition_status ENUM('excellent', 'good', 'fair', 'maintenance') DEFAULT 'good',
    max_borrow_days INT DEFAULT 7,
    max_renewals INT DEFAULT 2,
    min_points_required INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    INDEX idx_code (code),
    INDEX idx_category (category_id),
    INDEX idx_active (is_active, quantity_available)
) ENGINE=InnoDB;

-- ===================================================================
-- 4. REQUESTS TABLE
-- ===================================================================
CREATE TABLE requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    equipment_id INT NOT NULL,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    pickup_date DATE NOT NULL,
    expected_return_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed') DEFAULT 'pending',
    approved_by INT NULL,
    approval_date DATETIME NULL,
    rejection_reason TEXT NULL,
    student_notes TEXT,
    admin_notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id),
    INDEX idx_user (user_id, status),
    INDEX idx_equipment (equipment_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ===================================================================
-- 5. LOANS TABLE
-- ===================================================================
CREATE TABLE loans (
    loan_id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NULL,
    user_id INT NOT NULL,
    equipment_id INT NOT NULL,
    checkout_date DATETIME NOT NULL,
    due_date DATETIME NOT NULL,
    return_date DATETIME NULL,
    status ENUM('active', 'overdue', 'returned', 'returned_late') DEFAULT 'active',
    renewal_count INT DEFAULT 0,
    days_overdue INT DEFAULT 0,
    condition_on_checkout ENUM('excellent', 'good', 'fair') NOT NULL,
    condition_on_return ENUM('excellent', 'good', 'fair', 'damaged') NULL,
    checked_out_by INT NOT NULL,
    returned_to INT NULL,
    checkout_notes TEXT,
    return_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(request_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id),
    FOREIGN KEY (checked_out_by) REFERENCES users(user_id),
    FOREIGN KEY (returned_to) REFERENCES users(user_id),
    INDEX idx_user (user_id, status),
    INDEX idx_equipment (equipment_id, status),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB;

-- ===================================================================
-- 6. POINTS HISTORY TABLE
-- ===================================================================
CREATE TABLE points_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    loan_id INT NULL,
    points_change INT NOT NULL,
    points_after INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    action_type ENUM('reward', 'penalty', 'adjustment', 'reset') NOT NULL,
    days_late INT NULL,
    damage_type VARCHAR(100) NULL,
    processed_by INT NULL,
    processed_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id),
    FOREIGN KEY (processed_by) REFERENCES users(user_id),
    INDEX idx_user (user_id),
    INDEX idx_date (processed_date)
) ENGINE=InnoDB;

-- ===================================================================
-- 7. FAVORITES TABLE
-- ===================================================================
CREATE TABLE favorites (
    favorite_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    equipment_id INT NOT NULL,
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, equipment_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ===================================================================
-- 8. SETTINGS TABLE
-- ===================================================================
CREATE TABLE settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===================================================================
-- SEED USERS
-- ===================================================================
INSERT INTO users (student_id, first_name, last_name, email, password, role, points, points_status, status) VALUES
('ADMIN001', 'Athletics', 'Department', 'athletics@hau.edu.ph', 'admin123', 'admin', 100, 'good', 'active'),
('2021-001234', 'Juan', 'Dela Cruz', 'jdelacruz@hau.edu.ph', 'student123', 'student', 85, 'good', 'active'),
('2022-005678', 'Maria', 'Santos', 'msantos@hau.edu.ph', 'student123', 'student', 55, 'warning', 'active'),
('2021-009012', 'Pedro', 'Reyes', 'preyes@hau.edu.ph', 'student123', 'student', 35, 'restricted', 'suspended'),
('2023-002345', 'Ana', 'Garcia', 'agarcia@hau.edu.ph', 'student123', 'student', 92, 'good', 'active');

-- ===================================================================
-- SEED CATEGORIES
-- ===================================================================
INSERT INTO categories (name, description, icon, display_order) VALUES
('Ball Sports', 'Basketballs, Volleyballs, Soccer Balls', '🏀', 1),
('Racket Sports', 'Tennis, Badminton, Table Tennis', '🎾', 2),
('Fitness Equipment', 'Yoga Mats, Dumbbells, Jump Ropes', '💪', 3),
('Outdoor Activities', 'Frisbees, Cones, Portable Goals', '🏃', 4),
('Training Gear', 'Stopwatches, Whistles, Markers', '⏱️', 5);

-- ===================================================================
-- SEED EQUIPMENT WITH IMAGE FILENAMES
-- ===================================================================
INSERT INTO equipment 
(code, name, category_id, description, brand, size_info, image, quantity_total, quantity_available, location, condition_status, max_borrow_days, min_points_required)
VALUES
('BB-001', 'Basketball', 1, 'Standard basketball for PE classes', 'Molten', 'Size 7', 'basketball.jpg', 8, 6, 'Main Gym', 'good', 7, 0),
('SB-001', 'Soccer Ball', 1, 'Size 5 soccer ball for outdoor games', 'Adidas', 'Size 5', 'soccer_ball.jpg', 6, 5, 'Sports Field', 'good', 7, 0),
('VB-001', 'Volleyball', 1, 'Indoor volleyball for school matches', 'Mikasa', 'Official', 'volleyball.jpg', 6, 5, 'Volleyball Court', 'good', 7, 0),
('TN-001', 'Tennis Racket', 2, 'Lightweight tennis racket', 'Wilson', 'Adult', 'tennis_racket.jpg', 6, 5, 'Tennis Court', 'good', 5, 0),
('BD-001', 'Badminton Racket Set', 2, '2 rackets with shuttlecocks', 'Yonex', 'Standard', 'badminton_set.jpg', 6, 6, 'Indoor Court', 'good', 5, 0),
('TT-001', 'Table Tennis Paddle Set', 2, '2 paddles with 3 balls', 'Butterfly', 'Standard', 'table_tennis_set.jpg', 5, 5, 'Recreation Room', 'good', 3, 0),
('YM-001', 'Yoga Mat', 3, 'Non-slip mat for exercises', 'Manduka', '6mm', 'yoga_mat.jpg', 12, 12, 'Fitness Center', 'good', 14, 0),
('DB-001', 'Dumbbell Set', 3, '5kg pair dumbbells', 'CAP', '5kg', 'dumbbell_set.jpg', 6, 6, 'Weight Room', 'good', 3, 50),
('JR-001', 'Jump Rope', 3, 'Adjustable jump rope', 'Nike', 'Adjustable', 'jump_rope.jpg', 10, 10, 'Fitness Center', 'good', 7, 0),
('FR-001', 'Frisbee', 4, 'Ultimate frisbee disc', 'Discraft', '175g', 'frisbee.jpg', 10, 10, 'Equipment Room', 'good', 7, 0),
('CN-001', 'Training Cones', 4, 'Set of 10 cones', 'Sklz', '9-inch', 'training_cones.jpg', 5, 5, 'Equipment Room', 'good', 7, 0),
('PG-001', 'Portable Goal Set', 4, 'Small soccer goal', 'Decathlon', '4x2 ft', 'portable_goal.jpg', 2, 2, 'Storage Area', 'good', 7, 0),
('SW-001', 'Stopwatch', 5, 'Digital stopwatch', 'Casio', 'Digital', 'stopwatch.jpg', 6, 6, 'Athletics Office', 'good', 3, 0),
('WH-001', 'Whistle', 5, 'Referee whistle', 'Fox', 'Classic', 'whistle.jpg', 8, 8, 'Athletics Office', 'good', 3, 0);
