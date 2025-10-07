<?php

require_once '../config/config.php';

$page_title = 'Login';
$error = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // Role-based redirect for existing sessions
    switch ($_SESSION['role']) {
        case 'Admin':
            header("Location: " . BASE_URL . "/admin/index.php");
            break;
        case 'Company':
            header("Location: " . BASE_URL . "/dashboard/company.php");
            break;
        default:
            header("Location: " . BASE_URL . "/dashboard/student.php");
    }
    exit();
}

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']); // Can be username or email
    $password = $_POST['password'];
    
    if (empty($login) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        // Check user credentials with role information (ALL STATUSES)
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.email, u.password, u.status, u.full_name, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE (u.username = ? OR u.email = ?)
        ");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Password is correct, now check account status
                switch ($user['status']) {
                    case 'active':
                        // Login successful - create session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role_name'];
                        $_SESSION['full_name'] = $user['full_name'];
                        
                        // Role-based redirect for SkillBridge
                        switch ($user['role_name']) {
                            case 'Admin':
                                header("Location: " . BASE_URL . "/admin/index.php");
                                break;
                            case 'Company':
                                header("Location: " . BASE_URL . "/dashboard/company.php");
                                break;
                            default: // Student
                                header("Location: " . BASE_URL . "/dashboard/student.php");
                        }
                        exit();
                        break;
                        
                    case 'pending':
                        if ($user['role_name'] === 'Company') {
                            $error = 'Your company account is pending approval. Our admin team will review your registration and notify you once approved. This usually takes 1-2 business days.';
                        } else {
                            $error = 'Your account is pending activation. Please check your email for verification instructions or contact support.';
                        }
                        break;
                        
                    case 'suspended':
                        $error = 'Your account has been suspended. Please contact our support team at support@skillbridge.com for assistance.';
                        break;
                        
                    case 'inactive':
                        $error = 'Your account is currently inactive. Please reactivate your account or contact support if you need assistance.';
                        break;
                        
                    default:
                        $error = 'Your account status is currently under review. Please contact support for more information.';
                        break;
                }
            } else {
                $error = 'Invalid username/email or password.';
            }
        } else {
            $error = 'Invalid username/email or password.';
        }
        $stmt->close();
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
        /* Keep all your existing CSS styles here - no changes needed */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
        }

        .login-container {
            display: flex;
            height: 100vh;
            position: relative;
        }

        /* Left Side - Brand & Welcome - SkillBridge Theme */
        .login-left {
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

        .login-left::before {
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

        .welcome-content {
            z-index: 2;
            position: relative;
            text-align: center;
            max-width: 420px;
        }

        .welcome-message {
            margin-bottom: 2.5rem;
        }

        .welcome-message h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            font-family: 'Poppins', sans-serif;
        }

        .welcome-message p {
            font-size: 1rem;
            opacity: 0.85;
            line-height: 1.6;
        }

        .login-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2.5rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
            font-weight: 500;
        }

        .login-benefits {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1rem;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.08);
            padding: 1rem 1.2rem;
            border-radius: 12px;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .benefit-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .benefit-item i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.6rem;
            border-radius: 10px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
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
        }

        /* Right Side - Login Form */
        .login-right {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
        }

        .login-form-container {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 2;
        }

        .login-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .login-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .login-header p {
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

        .password-input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            background: #f1f5f9;
            color: #374151;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            accent-color: #2563eb;
        }

        .form-check-label {
            color: #64748b;
            font-size: 0.95rem;
            cursor: pointer;
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

        .alert-warning {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fed7aa;
        }

        /* Support Contact Box for Pending Companies */
        .support-info {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid #2563eb;
        }

        .support-info h6 {
            color: #374151;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .support-info p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
        }

        .support-info a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }

        .support-info a:hover {
            text-decoration: underline;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }

            .login-left {
                flex: none;
                height: 40vh;
                padding: 2rem 1rem;
            }

            .login-right {
                flex: none;
                height: 60vh;
                padding: 2rem 1rem;
                overflow-y: auto;
            }

            .brand-logo {
                font-size: 2rem;
                margin-bottom: 0.8rem;
            }

            .brand-logo i {
                font-size: 2.2rem;
                padding: 0.6rem;
            }

            .brand-subtitle {
                font-size: 1.1rem;
                margin-bottom: 1.5rem;
            }

            .welcome-message {
                margin-bottom: 1.5rem;
            }

            .welcome-message h3 {
                font-size: 1.2rem;
            }

            .welcome-message p {
                font-size: 0.9rem;
            }

            .login-stats {
                padding: 1rem;
                margin-bottom: 1.5rem;
            }

            .stat-number {
                font-size: 1.4rem;
            }

            .stat-label {
                font-size: 0.75rem;
            }

            .login-benefits {
                display: none; /* Hide on mobile to save space */
            }

            .back-home {
                top: 1rem;
                left: 1rem;
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
            }

            .login-header h1 {
                font-size: 2rem;
            }

            .login-form-container {
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .login-left {
                height: 35vh;
                padding: 1.5rem 1rem;
            }

            .login-right {
                height: 65vh;
                padding: 1.5rem 1rem;
            }

            .brand-logo {
                font-size: 1.8rem;
            }

            .login-header h1 {
                font-size: 1.8rem;
            }

            .form-control {
                padding: 0.9rem 1rem;
            }

            .login-stats {
                flex-direction: column;
                gap: 1rem;
            }

            .stat-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .stat-number {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side - Brand & Welcome - SkillBridge Theme -->
        <div class="login-left">
            <a href="<?php echo BASE_URL; ?>/index.php" class="back-home">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
            
            <div class="brand-logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </div>
            
            <p class="brand-subtitle">Welcome Back to SkillBridge</p>
            
            <div class="welcome-content">
                <div class="welcome-message">
                    <h3>Sign in to your account</h3>
                    <p>Access your dashboard, explore internships, connect with companies, and advance your career journey.</p>
                </div>
                
                <div class="login-stats">
                    <div class="stat-item">
                        <div class="stat-number">2,500+</div>
                        <div class="stat-label">Active Students</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">800+</div>
                        <div class="stat-label">Internships Posted</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">150+</div>
                        <div class="stat-label">Partner Companies</div>
                    </div>
                </div>
                
                <div class="login-benefits">
                    <div class="benefit-item">
                        <i class="fas fa-search"></i>
                        <span>Browse Internship Opportunities</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Manage Your Applications</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Track Your Progress</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-handshake"></i>
                        <span>Connect with Companies</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-form-container">
                <div class="login-header">
                    <h1>Welcome Back!</h1>
                    <p>Sign in to access your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php echo htmlspecialchars($error); ?>
                            
                            <?php if (strpos($error, 'pending approval') !== false): ?>
                                <div class="support-info">
                                    <h6><i class="fas fa-info-circle"></i> Need Help?</h6>
                                    <p>Contact our support team at <a href="mailto:support@skillbridge.com">support@skillbridge.com</a> or call <a href="tel:+15551234567">+1 (555) 123-4567</a> for assistance.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="login" class="form-label">
                            <i class="fas fa-user"></i> Username or Email
                        </label>
                        <input type="text" id="login" name="login" class="form-control"
                               value="<?php echo isset($login) ? htmlspecialchars($login) : ''; ?>" 
                               placeholder="Enter your username or email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" id="remember" name="remember" class="form-check-input">
                        <label for="remember" class="form-check-label">Remember me</label>
                    </div>
                    
                    <button type="submit" class="auth-btn" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign In
                    </button>
                </form>

                <div class="auth-footer">
                    <p>
                        <a href="forgot_password.php">
                            <i class="fas fa-key"></i> Forgot your password?
                        </a>
                    </p>
                    <p>
                        Don't have an account? 
                        <a href="register.php">
                            <i class="fas fa-user-plus"></i> Create one here
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
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const container = document.querySelector('.login-form-container');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
            container.classList.add('loading');
        });

        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('login').focus();
        });

        // Enter key handling
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>
