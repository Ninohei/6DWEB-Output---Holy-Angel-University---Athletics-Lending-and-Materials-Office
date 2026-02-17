<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../student/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($student_id) || empty($password)) {
        $error = 'Please enter both Student ID and password';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT user_id, student_id, first_name, last_name, email, password, role, points, points_status, status
                FROM users 
                WHERE student_id = ?
            ");
            $stmt->execute([$student_id]);
            $user = $stmt->fetch();
            
            if ($user && $password === $user['password']) {
                // Check if account is suspended
                if ($user['status'] == 'suspended') {
                    $error = 'Your account is suspended. Please contact Athletics Office.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['student_id'] = $user['student_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['points'] = $user['points'];
                    $_SESSION['points_status'] = $user['points_status'];
                    $_SESSION['last_activity'] = time();
                    
                    // Update last login
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);
                    
                    // Redirect based on role
                    if ($user['role'] == 'admin') {
                        header('Location: ../admin/dashboard.php');
                    } else {
                        header('Location: ../student/dashboard.php');
                    }
                    exit;
                }
            } else {
                $error = 'Invalid Student ID or password';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), 
                        url('../assets/images/login-background.png'); 
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed; 
            padding: 20px;
        }
        
        .login-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 420px;
            width: 100%;
            overflow: hidden;
        }
        
        .login-header {
            background: white;
            padding: 40px 30px 30px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }
        
        .login-logo {
            width: 100%;
            max-width: 180px;
            margin: 0 auto 25px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 600;
            color: #800000;
            margin-bottom: 5px;
        }
        
        .login-subtitle {
            font-size: 14px;
            color: #666;
            font-style: italic;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .login-form input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .login-form input:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background: #800000;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            background: #660000;
        }
        
        .login-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
            font-size: 13px;
            color: #666;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .demo-accounts {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .demo-accounts strong {
            display: block;
            margin-bottom: 8px;
            color: #856404;
        }
        
        .demo-accounts p {
            margin: 5px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-logo">
                    <img src="../assets/images/logo.png" alt="HAU Logo">
                </div>
                <h1 class="login-title">Holy Angel University - Athletics Lending and Materials Office</h1>
            </div>
            
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert alert-error">Your session has expired. Please log in again.</div>
                <?php endif; ?>
                
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input 
                            type="text" 
                            id="student_id" 
                            name="student_id" 
                            placeholder="e.g., 2021-001234"
                            value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>"
                            required 
                            autofocus
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>
                    
                    <button type="submit" class="login-btn">Log In</button>
                </form>
                
                <div class="demo-accounts">
                    <strong>Demo Accounts:</strong>
                    <p><strong>Student:</strong> 2021-001234 / student123</p>
                    <p><strong>Admin:</strong> ADMIN001 / admin123</p>
                </div>
            </div>
            
            <div class="login-footer">
                &copy; <?php echo date('Y'); ?> Holy Angel University. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>
