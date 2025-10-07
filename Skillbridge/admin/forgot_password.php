<?php
require_once '../config/config.php';
require_once '../includes/email_functions.php';

// Set timezone for SkillBridge - MATCH both MySQL and PHP timezones
$conn->query("SET time_zone = '+05:30'");
date_default_timezone_set('Asia/Kolkata'); // ✅ CRITICAL FIX: This was missing!

$page_title = 'Admin Password Recovery';
$message = '';
$message_type = '';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Admin') {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        // ✅ SAME AS COMPANY/STUDENT: Clean approach - check if admin email exists
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.email, u.full_name, u.status, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND r.role_name = 'Admin' AND u.status = 'active'
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // ✅ SAME AS COMPANY/STUDENT: Simple token generation
            $resetToken = bin2hex(random_bytes(32)); // 64 character token
            // ✅ FIXED: Use same approach as company/student but 2 hours for admin
            $expiryTime = date('Y-m-d H:i:s', strtotime('+2 hours'));
            
            // Store token in database
            $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $resetToken, $expiryTime, $user['id']);
            
            if ($updateStmt->execute()) {
                // ✅ SAME AS COMPANY/STUDENT: Send password reset email
                if (function_exists('sendAdminPasswordResetEmail')) {
                    $emailResult = sendAdminPasswordResetEmail($user['full_name'], $user['email'], $resetToken);
                    
                    if ($emailResult['success']) {
                        $message = 'Password reset instructions have been sent to your admin email address. Please check your inbox and follow the instructions to reset your password.';
                        $message_type = 'success';
                    } else {
                        $message = 'We encountered an issue sending the reset email. Please try again or contact system administrator.';
                        $message_type = 'error';
                        error_log("Failed to send admin password reset email to {$email}: " . $emailResult['message']);
                    }
                } else {
                    $message = 'Password reset instructions have been sent to your admin email address. Please check your inbox and follow the instructions to reset your password.';
                    $message_type = 'success';
                }
            } else {
                $message = 'An error occurred while processing your request. Please try again.';
                $message_type = 'error';
            }
        } else {
            // ✅ SAME AS COMPANY/STUDENT: Don't reveal if admin email exists or not (security best practice)
            $message = 'If an admin account with that email exists, you will receive password reset instructions shortly.';
            $message_type = 'success';
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
        .forgot-wrapper {
            width: 100%;
            max-width: 450px;
            max-height: 95vh;
            margin: 1rem;
            position: relative;
            z-index: 10;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .forgot-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
        }

        /* Header */
        .forgot-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .forgot-logo {
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

        .forgot-logo i {
            font-size: 2.5rem;
            color: white;
        }

        .forgot-header h1 {
            color: #1e293b;
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .forgot-header p {
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

        /* Button styling */
        .btn-reset {
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

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-reset:active {
            transform: translateY(0);
        }

        .btn-reset.loading {
            opacity: 0.8;
            cursor: not-allowed;
        }

        .btn-reset.loading i {
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

        /* Back to login */
        .back-to-login {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .back-to-login a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s ease;
        }

        .back-to-login a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        /* Admin info section */
        .admin-info {
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
            border: 1px solid #c084fc;
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            color: #581c87;
            font-size: 0.85rem;
        }

        .admin-info h4 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #4c1d95;
        }

        .admin-info ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .admin-info li {
            margin-bottom: 0.4rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .admin-info li::before {
            content: "🔐";
            margin-top: 0.1rem;
        }

        /* Security notice */
        .security-notice {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 1.25rem;
            border-radius: 12px;
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
        }

        .security-notice strong {
            display: block;
            margin-bottom: 0.5rem;
            color: #78350f;
            font-weight: 600;
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .forgot-wrapper {
                margin: 0.5rem;
                max-height: 98vh;
            }
            
            .forgot-container {
                padding: 2rem 1.5rem;
            }
            
            .forgot-header h1 {
                font-size: 1.5rem;
            }
            
            .form-control {
                padding: 0.875rem 1rem;
                font-size: 0.9rem;
            }
            
            .btn-reset {
                padding: 0.875rem 1.25rem;
                font-size: 1rem;
            }
        }

        @media (max-height: 700px) {
            .forgot-container {
                padding: 1.5rem;
            }
            
            .forgot-header {
                margin-bottom: 1.5rem;
            }
            
            .forgot-logo {
                width: 60px;
                height: 60px;
                margin-bottom: 1rem;
            }
            
            .forgot-logo i {
                font-size: 2rem;
            }
        }

        /* Custom scrollbar */
        .forgot-wrapper::-webkit-scrollbar {
            width: 6px;
        }

        .forgot-wrapper::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .forgot-wrapper::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.3);
            border-radius: 3px;
        }

        .forgot-wrapper::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>

    <div class="forgot-wrapper">
        <div class="forgot-container">
            <!-- Header -->
            <div class="forgot-header">
                <div class="forgot-logo">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Admin Password Recovery</h1>
                <p>Secure password reset for system administrators</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($message_type !== 'success'): ?>
                <!-- Reset Form -->
                <form method="POST" action="" id="adminForgotPasswordForm">
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-user-shield"></i> Admin Email Address
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="Enter your admin email address" 
                               required>
                    </div>

                    <button type="submit" class="btn-reset" id="resetBtn">
                        <i class="fas fa-paper-plane"></i>
                        Send Reset Instructions
                    </button>
                </form>
            <?php endif; ?>

            <!-- Back to Login -->
            <div class="back-to-login">
                <a href="<?php echo BASE_URL; ?>/admin/login.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Admin Login
                </a>
            </div>

            <!-- Admin Reset Instructions -->
            <div class="admin-info">
                <h4>
                    <i class="fas fa-shield-alt"></i>
                    Admin Reset Process:
                </h4>
                <ul>
                    <li>Enter your registered admin email address</li>
                    <li>Check your email for secure reset instructions</li>
                    <li>Reset link expires in 2 hours for security</li>
                    <li>All reset attempts are logged and monitored</li>
                    <li>Contact system administrator if issues persist</li>
                </ul>
            </div>

            <!-- Security Notice -->
            <div class="security-notice">
                <strong><i class="fas fa-exclamation-triangle"></i> Security Alert</strong>
                Admin password reset requests are logged and monitored. 
                Only authorized system administrators should use this feature.
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('adminForgotPasswordForm');
            const emailInput = document.getElementById('email');
            const resetBtn = document.getElementById('resetBtn');
            
            if (form) {
                // Auto-focus email input
                emailInput.focus();
                
                // Form submission handling
                form.addEventListener('submit', function(e) {
                    const email = emailInput.value.trim();
                    
                    if (!email) {
                        e.preventDefault();
                        alert('Please enter your admin email address');
                        emailInput.focus();
                        return false;
                    }
                    
                    if (!email.includes('@')) {
                        e.preventDefault();
                        alert('Please enter a valid email address');
                        emailInput.focus();
                        return false;
                    }
                    
                    // Add loading state
                    resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                    resetBtn.classList.add('loading');
                    resetBtn.disabled = true;
                    
                    // Re-enable if form submission fails
                    setTimeout(() => {
                        resetBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reset Instructions';
                        resetBtn.classList.remove('loading');
                        resetBtn.disabled = false;
                    }, 10000);
                });
            }
        });

        // Log admin password reset attempt for security monitoring
        console.log('Admin password reset page accessed at:', new Date().toISOString());
    </script>

</body>
</html>
