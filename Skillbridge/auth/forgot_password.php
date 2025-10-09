<?php
// Force IST timezone for this script
date_default_timezone_set('Asia/Kolkata');

require_once '../config/database.php';
require_once '../includes/email_functions.php';

// Set database timezone to IST for this connection
$conn->query("SET time_zone = '+05:30'");

$page_title = 'Password Recovery';
$message = '';
$message_type = '';

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    
    if (empty($username)) {
        $message = 'Please enter your username.';
        $message_type = 'error';
    } else {
        // Check if username exists - Fetch by USERNAME (unique identifier)
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.email, u.full_name, u.status, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.username = ? AND u.status != 'suspended'
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate secure reset token
            $resetToken = bin2hex(random_bytes(32)); // 64 character token
            $expiryTime = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 hour expiry
            
            // Store token in database
            $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $resetToken, $expiryTime, $user['id']);
            
            if ($updateStmt->execute()) {
                // Send password reset email
                if (function_exists('sendPasswordResetEmail')) {
                    $emailResult = sendPasswordResetEmail(
                        $user['full_name'], 
                        $user['email'], 
                        $resetToken, 
                        $user['role_name'],
                        $user['username']  // Pass username to email function
                    );
                    
                    if ($emailResult['success']) {
                        $message = 'Password reset instructions have been sent to your email address (' . substr($user['email'], 0, 3) . '***' . substr($user['email'], strpos($user['email'], '@')) . '). Please check your inbox and follow the instructions.';
                        $message_type = 'success';
                    } else {
                        $message = 'We encountered an issue sending the reset email. Please try again or contact support.';
                        $message_type = 'error';
                        error_log("Failed to send password reset email to {$user['email']} for username {$username}: " . $emailResult['message']);
                    }
                } else {
                    $message = 'Password reset instructions have been sent to your email address.';
                    $message_type = 'success';
                }
            } else {
                $message = 'An error occurred while processing your request. Please try again.';
                $message_type = 'error';
            }
        } else {
            // Don't reveal if username exists or not (security best practice)
            $message = 'If an account with that username exists, you will receive password reset instructions shortly at your registered email address.';
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
            margin: 0;
            padding: 0;
        }

        .forgot-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Left Side - Brand & Security Info */
        .forgot-left {
            flex: 1;
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            color: white;
            padding: 3rem;
        }

        .forgot-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.08)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.12)"/><circle cx="90" cy="90" r="2" fill="rgba(255,255,255,0.06)"/><circle cx="10" cy="60" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s linear infinite;
            opacity: 0.3;
        }

        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
            100% { transform: translateY(0px) rotate(360deg); }
        }

        .brand-logo {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 1rem;
            z-index: 2;
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-logo i {
            font-size: 3rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.8rem;
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .brand-subtitle {
            font-size: 1.3rem;
            font-weight: 400;
            opacity: 0.9;
            margin-bottom: 2.5rem;
            z-index: 2;
            position: relative;
            text-align: center;
        }

        .security-content {
            z-index: 2;
            position: relative;
            text-align: center;
            max-width: 400px;
        }

        .security-message {
            margin-bottom: 2.5rem;
        }

        .security-message h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            font-family: 'Poppins', sans-serif;
        }

        .security-message p {
            font-size: 1rem;
            opacity: 0.85;
            line-height: 1.6;
        }

        .security-features {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .security-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.08);
            padding: 1.2rem;
            border-radius: 12px;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .security-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .security-item i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.8rem;
            border-radius: 10px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .security-item-content h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            font-family: 'Poppins', sans-serif;
        }

        .security-item-content p {
            font-size: 0.85rem;
            opacity: 0.8;
            line-height: 1.4;
            margin: 0;
        }

        .security-note {
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .security-note i {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
        }

        .security-note h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .security-note p {
            font-size: 0.9rem;
            opacity: 0.85;
            margin: 0;
        }

        .back-home {
            position: absolute;
            top: 2rem;
            left: 2rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            transition: all 0.2s ease;
            z-index: 3;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .back-home:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-1px);
            text-decoration: none;
        }

        /* Right Side - Reset Form */
        .forgot-right {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
        }

        .forgot-form-container {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 2;
        }

        .forgot-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .forgot-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .forgot-header p {
            color: #64748b;
            font-size: 1.1rem;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.5rem;
            display: block;
        }

        .auth-btn {
            width: 100%;
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        .auth-btn:active {
            transform: translateY(0);
        }

        .btn-outline-primary {
            background: transparent;
            color: #2563eb;
            border: 2px solid #2563eb;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline-primary:hover {
            background: #2563eb;
            color: white;
            transform: translateY(-1px);
            text-decoration: none;
        }

        .auth-footer {
            text-align: center;
        }

        .auth-footer p {
            margin-bottom: 1rem;
            color: #64748b;
        }

        .auth-footer a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .auth-footer a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .success-actions {
            text-align: center;
            margin-top: 1.5rem;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: start;
            gap: 0.75rem;
        }

        .info-box i {
            color: #2563eb;
            margin-top: 2px;
        }

        .info-box p {
            color: #1e40af;
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.5;
        }

        /* Decorative Elements */
        .forgot-right::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(5, 150, 105, 0.1));
            border-radius: 50%;
            z-index: 1;
        }

        .forgot-right::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(5, 150, 105, 0.08));
            border-radius: 50%;
            z-index: 1;
        }

        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .auth-btn {
            background: #94a3b8;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .forgot-container {
                flex-direction: column;
            }

            .forgot-left, .forgot-right {
                flex: 0 0 auto;
                padding: 2rem 1rem;
            }

            .brand-logo {
                font-size: 2rem;
            }

            .forgot-header h1 {
                font-size: 2rem;
            }

            .security-note {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <!-- Left Side - Brand & Security Info -->
        <div class="forgot-left">
            <a href="../index.php" class="back-home">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
            
            <div class="brand-logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </div>
            
            <p class="brand-subtitle">Secure Password Recovery</p>
            
            <div class="security-content">
                <div class="security-message">
                    <h3>Forgot your password?</h3>
                    <p>No worries! Enter your username and we'll send password reset instructions to your registered email address.</p>
                </div>
                
                <div class="security-features">
                    <div class="security-item">
                        <i class="fas fa-shield-alt"></i>
                        <div class="security-item-content">
                            <h4>Secure Process</h4>
                            <p>Your reset link is encrypted and expires in 1 hour</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-user-check"></i>
                        <div class="security-item-content">
                            <h4>Username Based</h4>
                            <p>Reset password using your unique username</p>
                        </div>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-user-shield"></i>
                        <div class="security-item-content">
                            <h4>Privacy Protected</h4>
                            <p>We never share your email or personal information</p>
                        </div>
                    </div>
                </div>
                
                <div class="security-note">
                    <i class="fas fa-info-circle"></i>
                    <h4>Need Help?</h4>
                    <p>If you don't receive the email, check your spam folder or contact support@skillbridge.com</p>
                </div>
            </div>
        </div>

        <!-- Right Side - Reset Form -->
        <div class="forgot-right">
            <div class="forgot-form-container">
                <div class="forgot-header">
                    <h1>Reset Password</h1>
                    <p>Enter your username to continue</p>
                </div>

                <div class="info-box">
                    <i class="fas fa-lightbulb"></i>
                    <p><strong>Forgot your username?</strong> Your username is the one you created during registration. If you've forgotten it, please contact support.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <div><?php echo htmlspecialchars($message); ?></div>
                    </div>
                    
                    <?php if ($message_type === 'success'): ?>
                        <div class="success-actions">
                            <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">
                                📧 Check your email inbox and spam folder
                            </p>
                            <a href="login.php" class="btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!$message || $message_type === 'error'): ?>
                <form method="POST" action="" id="forgotForm">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" 
                               placeholder="Enter your username" 
                               required 
                               autocomplete="username">
                        <small class="form-text">We'll send reset instructions to your registered email</small>
                    </div>
                    
                    <button type="submit" class="auth-btn" id="resetBtn">
                        <i class="fas fa-paper-plane"></i>
                        Send Reset Instructions
                    </button>
                </form>
                <?php endif; ?>

                <div class="auth-footer">
                    <p>
                        Remember your password? 
                        <a href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Sign in here
                        </a>
                    </p>
                    <p>
                        Need an account? 
                        <a href="register.php">
                            <i class="fas fa-user-plus"></i> Register as Student
                        </a>
                    </p>
                    <p>
                        Are you a company? 
                        <a href="../companies/register.php">
                            <i class="fas fa-building"></i> Register as Company
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add loading state on form submit
        document.getElementById('forgotForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('resetBtn');
            const container = document.querySelector('.forgot-form-container');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Instructions...';
            container.classList.add('loading');
        });

        // Auto-focus on username input
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.focus();
            }
        });

        // Enter key handling
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.getElementById('forgotForm')) {
                document.getElementById('forgotForm').submit();
            }
        });
    </script>
</body>
</html>
