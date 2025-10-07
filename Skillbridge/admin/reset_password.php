<?php
require_once '../config/config.php';

// Set timezone for SkillBridge
$conn->query("SET time_zone = '+05:30'");

$page_title = 'Admin Password Reset';
$error = '';
$success = '';
$validToken = false;
$user = null;

// Check if token parameter exists
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Validate token - Only for Admin users
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.email, u.full_name, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.reset_token = ? AND u.reset_token_expires > NOW() 
        AND r.role_name = 'Admin' AND u.status = 'active'
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $validToken = true;
    } else {
        $error = 'This admin reset link has expired or is invalid. Please request a new password reset.';
    }
} else {
    $error = 'Invalid reset link. Please request a new password reset.';
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
        
        // Update password and clear reset token for admin
        $updateStmt = $conn->prepare("
            UPDATE users SET 
                password = ?, 
                reset_token = NULL, 
                reset_token_expires = NULL 
            WHERE reset_token = ? AND id = ?
        ");
        $updateStmt->bind_param("ssi", $hashedPassword, $token, $user['id']);
        
        if ($updateStmt->execute()) {
            // Log admin password reset for security
            error_log("Admin password reset completed for: " . $user['email'] . " from IP: " . $_SERVER['REMOTE_ADDR'] . " at " . date('Y-m-d H:i:s'));
            
            $success = 'Your admin password has been successfully reset! You can now login with your new password.';
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
    <title><?php echo htmlspecialchars($page_title); ?> - SkillBridge</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background particles */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(120, 119, 198, 0.2) 0%, transparent 50%);
            animation: float 15s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }

        /* Main container */
        .reset-wrapper {
            width: 100%;
            max-width: 450px;
            max-height: 95vh;
            margin: 1rem;
            position: relative;
            z-index: 10;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
        }

        /* Header */
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .reset-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .reset-logo i {
            font-size: 2.5rem;
            color: white;
        }

        .reset-header h1 {
            color: #1e293b;
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .reset-header p {
            color: #64748b;
            font-size: 1rem;
            line-height: 1.5;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            color: #374151;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-label i {
            margin-right: 0.5rem;
            color: #667eea;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        .strength-weak { color: #ef4444; }
        .strength-medium { color: #f59e0b; }
        .strength-strong { color: #10b981; }

        /* Button styling */
        .btn-update {
            width: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-update:active {
            transform: translateY(0);
        }

        .btn-update.loading {
            opacity: 0.8;
            cursor: not-allowed;
        }

        .btn-update.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alert messages */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #34d399;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #f87171;
        }

        /* Login link */
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 2rem;
            border: 2px solid #667eea;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .login-link a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-1px);
        }

        /* Admin requirements */
        .admin-requirements {
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
            border: 1px solid #c084fc;
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            color: #581c87;
            font-size: 0.85rem;
        }

        .admin-requirements h4 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #4c1d95;
        }

        .admin-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .admin-requirements li {
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-requirements li::before {
            content: "🔐";
        }

        /* User info display */
        .user-info {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #1e40af;
            font-size: 0.9rem;
        }

        .user-info strong {
            color: #1e3a8a;
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .reset-wrapper {
                margin: 0.5rem;
                max-height: 98vh;
            }
            
            .reset-container {
                padding: 2rem 1.5rem;
            }
            
            .reset-header h1 {
                font-size: 1.5rem;
            }
            
            .form-control {
                padding: 0.875rem 1rem;
                font-size: 0.9rem;
            }
            
            .btn-update {
                padding: 0.875rem 1.25rem;
                font-size: 1rem;
            }
        }

        /* Custom scrollbar */
        .reset-wrapper::-webkit-scrollbar {
            width: 6px;
        }

        .reset-wrapper::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .reset-wrapper::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.3);
            border-radius: 3px;
        }
    </style>
</head>
<body>

    <div class="reset-wrapper">
        <div class="reset-container">
            <!-- Header -->
            <div class="reset-header">
                <div class="reset-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Admin Password Reset</h1>
                <p>Create a secure new password for your admin account</p>
            </div>

            <!-- User Info -->
            <?php if ($validToken && $user): ?>
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <strong>Resetting password for:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                </div>
            <?php endif; ?>

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
                <div class="login-link">
                    <a href="<?php echo BASE_URL; ?>/admin/login.php">
                        <i class="fas fa-sign-in-alt"></i>
                        Login to Admin Panel
                    </a>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
                <div class="login-link">
                    <a href="<?php echo BASE_URL; ?>/admin/forgot_password.php">
                        <i class="fas fa-key"></i>
                        Request New Reset Link
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($validToken && !$success): ?>
                <!-- Reset Form -->
                <form method="POST" action="" id="adminResetPasswordForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> New Admin Password
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Enter new secure password (minimum 6 characters)" 
                               required>
                        <div id="password-strength" class="password-strength"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i> Confirm New Password
                        </label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control" 
                               placeholder="Confirm your new password" 
                               required>
                    </div>

                    <button type="submit" class="btn-update" id="updateBtn">
                        <i class="fas fa-shield-alt"></i>
                        Update Admin Password
                    </button>
                </form>

                <!-- Admin Password Requirements -->
                <div class="admin-requirements">
                    <h4>
                        <i class="fas fa-security"></i>
                        Admin Password Requirements:
                    </h4>
                    <ul>
                        <li>Minimum 6 characters long (8+ recommended)</li>
                        <li>Include uppercase and lowercase letters</li>
                        <li>Add numbers and special characters</li>
                        <li>Avoid common words or personal information</li>
                        <li>Use unique password not used elsewhere</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('adminResetPasswordForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const updateBtn = document.getElementById('updateBtn');
            const strengthIndicator = document.getElementById('password-strength');
            
            if (form) {
                // Auto-focus password input
                passwordInput.focus();
                
                // Password strength checker
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    let message = '';
                    
                    if (password.length >= 6) strength++;
                    if (password.match(/[a-z]/)) strength++;
                    if (password.match(/[A-Z]/)) strength++;
                    if (password.match(/[0-9]/)) strength++;
                    if (password.match(/[^a-zA-Z0-9]/)) strength++;
                    
                    if (password.length < 6) {
                        message = 'Too short (minimum 6 characters)';
                        strengthIndicator.className = 'password-strength strength-weak';
                    } else if (strength < 3) {
                        message = 'Weak password - Add more character types';
                        strengthIndicator.className = 'password-strength strength-weak';
                    } else if (strength < 4) {
                        message = 'Medium strength password';
                        strengthIndicator.className = 'password-strength strength-medium';
                    } else {
                        message = 'Strong admin password';
                        strengthIndicator.className = 'password-strength strength-strong';
                    }
                    
                    strengthIndicator.textContent = message;
                });
                
                // Form submission handling
                form.addEventListener('submit', function(e) {
                    const newPassword = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (!newPassword || !confirmPassword) {
                        e.preventDefault();
                        alert('Please fill in all fields');
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Admin password must be at least 6 characters long');
                        passwordInput.focus();
                        return false;
                    }
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match');
                        confirmPasswordInput.focus();
                        return false;
                    }
                    
                    // Add loading state
                    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                    updateBtn.classList.add('loading');
                    updateBtn.disabled = true;
                    
                    // Re-enable if form submission fails
                    setTimeout(() => {
                        updateBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Update Admin Password';
                        updateBtn.classList.remove('loading');
                        updateBtn.disabled = false;
                    }, 10000);
                });
            }
        });

        // Log admin password reset attempt for security monitoring
        console.log('Admin password reset page accessed at:', new Date().toISOString());
    </script>

</body>
</html>
