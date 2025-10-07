<?php
require_once '../config/config.php';

$page_title = 'Admin Login';
$error = '';
$debug_info = '';

// Enable debug mode (set to false in production)
$debug_mode = false;

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Admin') {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}

// Handle admin login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Debug: Check if roles table has Admin role
        if ($debug_mode) {
            $role_check = $conn->query("SELECT id FROM roles WHERE role_name = 'Admin'");
            if ($role_check->num_rows === 0) {
                $debug_info .= "❌ Admin role not found in roles table. ";
            } else {
                $debug_info .= "✅ Admin role exists. ";
            }
        }
        
        // Query for Admin role users with detailed debugging
        $stmt = $conn->prepare("
            SELECT u.id, u.password, u.full_name, u.status, u.email, r.role_name, r.id as role_id
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND r.role_name = 'Admin'
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($debug_mode) {
            $debug_info .= "Found " . $result->num_rows . " admin user(s) with email: " . htmlspecialchars($email) . ". ";
        }
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            if ($debug_mode) {
                $debug_info .= "User status: " . $admin['status'] . ". ";
            }
            
            if ($admin['status'] !== 'active') {
                $error = "Admin account status is '" . $admin['status'] . "'. Please contact system administrator.";
            } elseif (password_verify($password, $admin['password'])) {
                // ✅ REMOVED: last_login update query (column doesn't exist)
                // No longer updating last_login since column doesn't exist
                
                // Successful admin login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['role'] = 'Admin';
                $_SESSION['full_name'] = $admin['full_name'];
                $_SESSION['last_activity'] = time();
                
                // Log admin access for security
                error_log("Admin login: " . $admin['full_name'] . " from IP: " . $_SERVER['REMOTE_ADDR'] . " at " . date('Y-m-d H:i:s'));
                
                // Redirect to admin dashboard
                header("Location: " . BASE_URL . "/admin/dashboard.php");
                exit();
            } else {
                $error = "Password verification failed. Please check your password.";
                if ($debug_mode) {
                    $debug_info .= "Password hash in DB: " . substr($admin['password'], 0, 20) . "... ";
                }
            }
        } else {
            $error = "No admin user found with this email address.";
            
            // Additional debugging - check if user exists with different role
            if ($debug_mode) {
                $check_stmt = $conn->prepare("SELECT u.email, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ?");
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    while ($row = $check_result->fetch_assoc()) {
                        $debug_info .= "Found user with role: " . $row['role_name'] . ". ";
                    }
                } else {
                    $debug_info .= "No user found with this email address in any role. ";
                }
            }
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

        /* Main login container - FIXED for scrolling */
        .login-wrapper {
            width: 100%;
            max-width: 450px;
            max-height: 95vh;
            margin: 1rem;
            position: relative;
            z-index: 10;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
        }

        /* Header */
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
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

        .login-logo i {
            font-size: 2.5rem;
            color: white;
        }

        .login-header h1 {
            color: #1e293b;
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            color: #64748b;
            font-size: 1rem;
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
        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Forgot password link */
        .forgot-password {
            text-align: right;
            margin-top: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s ease;
        }

        .forgot-password a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        /* Alert messages */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #f87171;
        }

        .alert-debug {
            background: linear-gradient(135deg, #fef3c7, #fcd34d);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        /* Info sections */
        .admin-access-info {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1.5rem;
        }

        .admin-access-info h4 {
            color: #1e40af;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-access-info ul {
            list-style: none;
            padding: 0;
            margin: 0;
            color: #1e40af;
        }

        .admin-access-info li {
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-access-info li::before {
            content: "✓";
            color: #10b981;
            font-weight: bold;
        }

        /* Security notice */
        .security-notice {
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
            border: 1px solid #c084fc;
            color: #581c87;
            padding: 1.25rem;
            border-radius: 12px;
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
        }

        .security-notice strong {
            display: block;
            margin-bottom: 0.5rem;
            color: #4c1d95;
            font-weight: 600;
        }

        /* Loading state */
        .btn-login.loading {
            opacity: 0.8;
            cursor: not-allowed;
        }

        .btn-login.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .login-wrapper {
                margin: 0.5rem;
                max-height: 98vh;
            }
            
            .login-container {
                padding: 2rem 1.5rem;
            }
            
            .login-header h1 {
                font-size: 1.5rem;
            }
            
            .form-control {
                padding: 0.875rem 1rem;
                font-size: 0.9rem;
            }
            
            .btn-login {
                padding: 0.875rem 1.25rem;
                font-size: 1rem;
            }
        }

        @media (max-height: 700px) {
            .login-container {
                padding: 1.5rem;
            }
            
            .login-header {
                margin-bottom: 1.5rem;
            }
            
            .login-logo {
                width: 60px;
                height: 60px;
                margin-bottom: 1rem;
            }
            
            .login-logo i {
                font-size: 2rem;
            }
        }

        /* Custom scrollbar for webkit browsers */
        .login-wrapper::-webkit-scrollbar {
            width: 6px;
        }

        .login-wrapper::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .login-wrapper::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.3);
            border-radius: 3px;
        }

        .login-wrapper::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-container">
            <!-- Header -->
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Admin Access Portal</h1>
                <p>SkillBridge System Administration</p>
            </div>

            <!-- Debug Information -->
            <?php if ($debug_mode && $debug_info): ?>
                <div class="alert alert-debug">
                    <i class="fas fa-bug"></i>
                    <strong>Debug Info:</strong><br>
                    <?php echo $debug_info; ?>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" id="adminLoginForm">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-user-shield"></i> Admin Email
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="Enter your admin email address" 
                           required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Enter your admin password" 
                           required>
                </div>

                <!-- Forgot Password Link -->
                <div class="forgot-password">
                    <a href="forgot_password.php">
                        <i class="fas fa-key"></i>
                        Forgot Password?
                    </a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Access Admin Dashboard
                </button>
            </form>

            <!-- Admin Access Information -->
            <div class="admin-access-info">
                <h4>
                    <i class="fas fa-info-circle"></i>
                    Admin Access Includes:
                </h4>
                <ul>
                    <li>Company registration approvals</li>
                    <li>Job posting management</li>
                    <li>User role administration</li>
                    <li>System analytics and reports</li>
                    <li>Platform configuration</li>
                </ul>
            </div>

            <!-- Security Notice -->
            <div class="security-notice">
                <strong><i class="fas fa-shield-alt"></i> Security Notice</strong>
                This is a restricted access area for authorized administrators only. 
                All login attempts are logged and monitored for security purposes.
            </div>
        </div>
    </div>

    <script>
        // Enhanced security and user experience
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('adminLoginForm');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const loginBtn = document.getElementById('loginBtn');
            
            // Auto-focus email input
            emailInput.focus();
            
            // Enhanced form validation
            form.addEventListener('submit', function(e) {
                const email = emailInput.value.trim();
                const password = passwordInput.value;
                
                if (!email || !password) {
                    e.preventDefault();
                    alert('Please fill in all fields');
                    return false;
                }
                
                if (!email.includes('@')) {
                    e.preventDefault();
                    alert('Please enter a valid email address');
                    emailInput.focus();
                    return false;
                }
                
                // Add loading state
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                
                // Re-enable if form submission fails (fallback)
                setTimeout(() => {
                    loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Access Admin Dashboard';
                    loginBtn.classList.remove('loading');
                    loginBtn.disabled = false;
                }, 10000);
            });
            
            // Enter key handling
            passwordInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    form.submit();
                }
            });

            // Security: Clear form on page visibility change
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    // Optional: Clear sensitive fields when tab becomes hidden
                    // passwordInput.value = '';
                }
            });
        });

        // Log admin page access attempt (for security monitoring)
        console.log('Admin login page accessed at:', new Date().toISOString());
    </script>

</body>
</html>
