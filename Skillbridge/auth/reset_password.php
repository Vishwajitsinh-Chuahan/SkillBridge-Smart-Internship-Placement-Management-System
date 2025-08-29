<?php
require_once '../config/config.php';

$page_title = 'Reset Password';
$error = '';
$success = '';
$validToken = false;
$user = null;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = 'Invalid or missing reset token.';
} else {
    $token = trim($_GET['token']);
    
    // Verify token and check expiry - Modified for SkillBridge status logic
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.email, u.full_name, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.reset_token = ? AND u.reset_token_expires > NOW() AND u.status != 'suspended'
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $validToken = true;
    } else {
        $error = 'This reset link has expired or is invalid. Please request a new password reset.';
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    // Validation
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please enter and confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password, clear reset token, and activate account if pending
        $updateStmt = $conn->prepare("
            UPDATE users 
            SET password = ?, 
                reset_token = NULL, 
                reset_token_expires = NULL,
                status = CASE 
                    WHEN status = 'pending' AND role_id = (SELECT id FROM roles WHERE role_name = 'Student') THEN 'active'
                    ELSE status 
                END
            WHERE reset_token = ?
        ");
        $updateStmt->bind_param("ss", $hashedPassword, $token);
        
        if ($updateStmt->execute()) {
            $success = 'Your password has been successfully reset! You can now login with your new password.';
            $validToken = false; // Prevent further submissions
        } else {
            $error = 'An error occurred while resetting your password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SkillBridge</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
        }

        /* Enhanced background particles */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.08)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.12)"/><circle cx="90" cy="10" r="1" fill="rgba(255,255,255,0.06)"/><circle cx="10" cy="60" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="70" cy="70" r="1" fill="rgba(255,255,255,0.08)"/></svg>') repeat;
            animation: float 25s linear infinite;
            opacity: 0.4;
            z-index: -1;
        }

        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
            100% { transform: translateY(0px) rotate(360deg); }
        }

        .reset-container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            position: relative;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .reset-header {
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            color: white;
            text-align: center;
            padding: 3rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .reset-header::before {
            content: '';
            position: absolute;
            top: -100%;
            left: -100%;
            width: 300%;
            height: 300%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: backgroundMove 20s linear infinite;
            opacity: 0.5;
        }

        @keyframes backgroundMove {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-30px, -30px) rotate(360deg); }
        }

        .reset-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            font-family: 'Poppins', sans-serif;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .reset-header h1 i {
            background: rgba(255, 255, 255, 0.25);
            padding: 1rem;
            border-radius: 16px;
            backdrop-filter: blur(15px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .reset-header p {
            opacity: 0.95;
            font-size: 1.1rem;
            position: relative;
            z-index: 2;
            font-weight: 400;
        }

        .reset-body {
            padding: 3rem 2.5rem;
            background: linear-gradient(180deg, #fafafa 0%, #ffffff 100%);
        }

        .brand-link {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            color: rgba(255, 255, 255, 0.95);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.7rem 1.3rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            z-index: 3;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            font-size: 0.95rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .brand-link:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            text-decoration: none;
        }

        .brand-link i {
            font-size: 1.1rem;
        }

        /* Enhanced Form Styling */
        .form-group {
            margin-bottom: 2rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 600;
            color: #2d3748;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: #2563eb;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 1.2rem 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #ffffff;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12), 0 4px 12px rgba(0, 0, 0, 0.05);
            transform: translateY(-1px);
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 0.8rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 1rem;
        }

        .password-toggle:hover {
            background: #f1f5f9;
            color: #374151;
            transform: translateY(-50%) scale(1.1);
        }

        .form-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .form-text::before {
            content: "💡";
            font-size: 0.8rem;
        }

        /* Enhanced Button Styling */
        .auth-btn {
            width: 100%;
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            color: white;
            border: none;
            padding: 1.3rem 2rem;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.7rem;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .auth-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .auth-btn:hover::before {
            left: 100%;
        }

        .auth-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(37, 99, 235, 0.4);
        }

        .auth-btn:active {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.35);
            color: white;
            text-decoration: none;
        }

        /* Enhanced Alert Styling */
        .alert {
            padding: 1.5rem;
            border-radius: 14px;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            line-height: 1.5;
        }

        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: currentColor;
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fde8e8 100%);
            color: #dc2626;
            border: 1px solid #fecaca;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            border: 1px solid #bbf7d0;
            box-shadow: 0 4px 12px rgba(22, 101, 52, 0.1);
        }

        .alert i {
            font-size: 1.2rem;
            margin-top: 0.1rem;
            flex-shrink: 0;
        }

        /* Enhanced User Info Styling */
        .user-info {
            background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
            padding: 1.5rem;
            border-radius: 14px;
            margin-bottom: 2rem;
            border-left: 4px solid #2563eb;
            position: relative;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
            overflow: hidden;
        }

        .user-info::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20px;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .user-info p {
            margin: 0;
            color: #1e40af;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .user-info i {
            background: rgba(37, 99, 235, 0.2);
            padding: 0.6rem;
            border-radius: 10px;
            font-size: 1rem;
            color: #1d4ed8;
        }

        .user-info strong {
            color: #1e3a8a;
            font-weight: 700;
        }

        .reset-footer {
            text-align: center;
            padding-top: 2rem;
            border-top: 2px solid #f1f5f9;
            margin-top: 1rem;
        }

        .reset-footer p {
            margin: 0;
            color: #64748b;
            font-size: 1rem;
        }

        .reset-footer a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .reset-footer a:hover {
            color: #1d4ed8;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .success-actions {
            text-align: center;
            margin-top: 2rem;
        }

        /* Loading state */
        .loading {
            opacity: 0.8;
            pointer-events: none;
        }

        .loading .auth-btn {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
        }

        /* Enhanced Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .reset-container {
                max-width: 100%;
                border-radius: 16px;
            }

            .reset-header {
                padding: 2.5rem 1.5rem;
            }

            .reset-header h1 {
                font-size: 1.8rem;
            }

            .reset-body {
                padding: 2.5rem 1.5rem;
            }

            .brand-link {
                position: static;
                display: inline-flex;
                margin-bottom: 1.5rem;
            }

            .form-control {
                padding: 1.1rem 1.3rem;
            }
        }

        @media (max-width: 480px) {
            .reset-header {
                padding: 2rem 1rem;
            }

            .reset-header h1 {
                font-size: 1.6rem;
                flex-direction: column;
                gap: 0.8rem;
            }

            .reset-body {
                padding: 2rem 1rem;
            }

            .form-control {
                padding: 1rem 1.2rem;
            }

            .auth-btn {
                padding: 1.2rem 1.5rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <a href="<?php echo BASE_URL; ?>/index.php" class="brand-link">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </a>
            
            <h1>
                <i class="fas fa-lock"></i>
                Set New Password
            </h1>
            <p>Create a secure password for your SkillBridge account</p>
        </div>

        <div class="reset-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
                
                <?php if (strpos($error, 'expired') !== false || strpos($error, 'invalid') !== false): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="forgot_password.php" class="btn-primary">
                            <i class="fas fa-redo"></i> Request New Reset Link
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
                <div class="success-actions">
                    <a href="login.php" class="auth-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        Login Now
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($validToken && !$success): ?>
                <div class="user-info">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        Resetting password for: <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                        <?php if ($user['role_name']): ?>
                            <span style="background: rgba(37,99,235,0.1); color: #1e40af; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem; margin-left: 0.5rem;">
                                <?php echo htmlspecialchars($user['role_name']); ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <form method="POST" action="" id="resetForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Enter your new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                        <small class="form-text">Minimum 6 characters required for security</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i> Confirm New Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm your new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="auth-btn" id="resetBtn">
                        <i class="fas fa-save"></i>
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="reset-footer">
                <p>
                    Remember your password? 
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i> Sign in here
                    </a>
                </p>
                <p style="margin-top: 0.5rem;">
                    Need help? 
                    <a href="<?php echo BASE_URL; ?>/help/contact.php">
                        <i class="fas fa-envelope"></i> Contact Support
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Add loading state on form submit
        document.getElementById('resetForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('resetBtn');
            const container = document.querySelector('.reset-body');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting Password...';
            container.classList.add('loading');
        });

        // Auto-focus on password input
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                passwordInput.focus();
            }
        });

        // Password match validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>
