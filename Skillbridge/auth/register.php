<?php
require_once '../config/config.php';
require_once '../includes/email_functions.php';

$page_title = 'Student Registration';
$error = '';
$success = '';

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

// Process registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $student_id = trim($_POST['student_id']);
    $course = trim($_POST['course']);
    $graduation_year = $_POST['graduation_year'];
    
    // Validation
    if (empty($username) || empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($student_id) || empty($course)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^\d{10}$/', $phone)) {
        $error = 'Please enter a valid 10-digit phone number.';
    } else {
        
        // ✅ NEW ROLE-BASED REGISTRATION LOGIC
        
        // Get Student role ID first
        $role_stmt = $conn->prepare("SELECT id FROM roles WHERE role_name = 'Student'");
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $role_data = $role_result->fetch_assoc();
        $student_role_id = $role_data['id'];
        
        // Check existing users with same username OR email
        $stmt = $conn->prepare("SELECT id, role_id, username, email FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $canRegister = true;
        $existingRoles = [];
        $conflictType = '';
        
        while ($row = $result->fetch_assoc()) {
            $existingRoles[] = $row['role_id'];
            
            // Check if SAME ROLE exists with SAME username or email
            if ($row['role_id'] == $student_role_id) {
                if ($row['username'] === $username) {
                    $canRegister = false;
                    $conflictType = 'username';
                    break;
                } elseif ($row['email'] === $email) {
                    $canRegister = false;
                    $conflictType = 'email';
                    break;
                }
            }
        }
        
        if (!$canRegister) {
            if ($conflictType === 'username') {
                $error = 'Username already exists for Student role. Please choose a different username or login to your existing Student account.';
            } elseif ($conflictType === 'email') {
                $error = 'Email already exists for Student role. Please use a different email or login to your existing Student account.';
            } else {
                $error = 'Username or email already exists for Student role. Please choose different credentials or login to your existing Student account.';
            }
        } else {
            
            // ✅ REGISTRATION ALLOWED - Different role or no existing account
            
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert user
                $stmt = $conn->prepare("INSERT INTO users (role_id, username, email, password, phone, full_name, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, 'active', 0)");
                $stmt->bind_param("isssss", $student_role_id, $username, $email, $hashed_password, $phone, $full_name);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    
                    // Insert student profile
                    $profile_stmt = $conn->prepare("INSERT INTO student_profiles (user_id, student_id, course, graduation_year) VALUES (?, ?, ?, ?)");
                    $profile_stmt->bind_param("issi", $user_id, $student_id, $course, $graduation_year);
                    $profile_stmt->execute();
                    
                    // Send welcome email (if function exists)
                    if (function_exists('sendWelcomeEmail')) {
                        $emailResult = sendWelcomeEmail($full_name, $email, 'Student');
                        
                        if ($emailResult['success']) {
                            $conn->commit();
                            
                            // Customize message based on existing roles
                            if (!empty($existingRoles)) {
                                $success = 'Student account created successfully! You now have multiple accounts on SkillBridge with different roles. A welcome email has been sent. You can login and start exploring internship opportunities.';
                                error_log("User {$email} now has multiple roles. Existing roles: " . implode(',', $existingRoles) . ", New role: Student ({$student_role_id})");
                            } else {
                                $success = 'Student account created successfully! A welcome email has been sent. You can now login and start exploring internship opportunities.';
                            }
                        } else {
                            $conn->commit();
                            $success = 'Student account created successfully! However, we couldn\'t send the welcome email. You can still login to your account.';
                            error_log("Failed to send welcome email to {$email}: " . $emailResult['message']);
                        }
                    } else {
                        $conn->commit();
                        
                        // Customize success message for multiple roles
                        if (!empty($existingRoles)) {
                            $success = 'Student account created successfully! You now have access to both Student and Company features with the same credentials. You can login and start exploring internship opportunities.';
                            error_log("User {$email} registered with multiple roles. Existing roles: " . implode(',', $existingRoles) . ", New role: Student");
                        } else {
                            $success = 'Student account created successfully! You can now login and start exploring internship opportunities.';
                        }
                    }
                } else {
                    throw new Exception('Failed to create student account: ' . $stmt->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Registration failed: ' . $e->getMessage();
                error_log("Student registration failed for {$email}: " . $e->getMessage());
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
            height: 100vh;
            overflow-x: hidden;
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
        }

        .register-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Left Side - Brand & Welcome */
        .register-left {
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

        .register-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.08)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.12)"/><circle cx="90" cy="90" r="2" fill="rgba(255,255,255,0.06)"/><circle cx="10" cy="60" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 25s linear infinite;
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

        .register-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            text-align: center;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-5px);
        }

        .feature-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
        }

        .feature-card h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .feature-card p {
            font-size: 0.85rem;
            opacity: 0.8;
            line-height: 1.4;
        }

        .register-cta {
            background: rgba(255, 255, 255, 0.08);
            padding: 1.5rem;
            border-radius: 16px;
            backdrop-filter: blur(5px);
            text-align: center;
        }

        .register-cta h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .register-cta p {
            font-size: 0.9rem;
            opacity: 0.85;
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

        /* Right Side - Registration Form */
        .register-right {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
            overflow-y: auto;
            max-height: 100vh;
        }

        .register-form-container {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 2;
        }

        .register-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .register-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .register-header p {
            color: #64748b;
            font-size: 1.1rem;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group-half {
            display: flex;
            gap: 1rem;
        }

        .form-group-half .form-group {
            flex: 1;
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

        .form-select {
            cursor: pointer;
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
            margin-bottom: 1.5rem;
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
            margin-top: 2rem;
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
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
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

        .success-actions .auth-btn {
            display: inline-flex;
            width: auto;
            margin: 0 0 1rem 0;
            padding: 0.75rem 2rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .register-container {
                flex-direction: column;
            }

            .register-left {
                flex: none;
                min-height: 40vh;
                padding: 2rem 1rem;
            }

            .register-right {
                flex: none;
                min-height: 60vh;
                padding: 2rem 1rem;
                max-height: none;
            }

            .form-group-half {
                flex-direction: column;
                gap: 0;
            }

            .form-group-half .form-group {
                margin-bottom: 1rem;
            }
        }
        
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Left Side - Brand & Features -->
        <div class="register-left">
            <a href="<?php echo BASE_URL; ?>/index.php" class="back-home">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
            
            <div class="brand-logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </div>
            
            <p class="brand-subtitle">Join the Student Community</p>
            
            <div class="welcome-content">
                <div class="welcome-message">
                    <h3>Start Your Career Journey</h3>
                    <p>Create your student account to discover amazing internship opportunities and connect with top companies.</p>
                </div>
                
                <div class="register-features">
                    <div class="feature-card">
                        <i class="fas fa-search"></i>
                        <h4>Find Internships</h4>
                        <p>Discover opportunities matching your skills</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-file-alt"></i>
                        <h4>Easy Applications</h4>
                        <p>Apply to multiple positions with one click</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-chart-line"></i>
                        <h4>Skill Assessment</h4>
                        <p>Showcase your abilities with certifications</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-handshake"></i>
                        <h4>Career Growth</h4>
                        <p>Get mentored by industry professionals</p>
                    </div>
                </div>
                
                <div class="register-cta">
                    <h4>Ready to launch your career?</h4>
                    <p>Complete the registration form to get started</p>
                </div>
            </div>
        </div>

        <!-- Right Side - Registration Form -->
        <div class="register-right">
            <div class="register-form-container">
                <div class="register-header">
                    <h1>Student Registration</h1>
                    <p>Create your student account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div class="success-actions">
                        <a href="login.php" class="auth-btn">
                            <i class="fas fa-sign-in-alt"></i>
                            Go to Login
                        </a>
                        <p style="font-size: 14px; color: #6b7280; margin: 15px 0;">
                            📧 Check your email for welcome message!
                        </p>
                    </div>
                <?php else: ?>

                <form method="POST" action="" id="registerForm">
                    <div class="form-group">
                        <label for="full_name" class="form-label">
                            <i class="fas fa-user"></i> Full Name
                        </label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" 
                               placeholder="Enter your full name" required>
                    </div>

                    <div class="form-group-half">
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-at"></i> Username
                            </label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" 
                                   placeholder="Choose username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="student_id" class="form-label">
                                <i class="fas fa-id-card"></i> Student ID
                            </label>
                            <input type="text" id="student_id" name="student_id" class="form-control" 
                                   value="<?php echo isset($student_id) ? htmlspecialchars($student_id) : ''; ?>" 
                                   placeholder="Your student ID" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                               placeholder="Enter your email address" required>
                    </div>

                    <div class="form-group-half">
                        <div class="form-group">
                            <label for="phone" class="form-label">
                                <i class="fas fa-phone"></i> Phone
                            </label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>" 
                                   placeholder="10-digit mobile number" 
                                   maxlength="10" minlength="10" pattern="\d{10}" required>
                        </div>

                        <div class="form-group">
                            <label for="graduation_year" class="form-label">
                                <i class="fas fa-calendar"></i> Graduation Year
                            </label>
                            <select id="graduation_year" name="graduation_year" class="form-control form-select" required>
                                <option value="">Select year</option>
                                <?php
                                $current_year = date('Y');
                                for ($year = $current_year; $year <= $current_year + 6; $year++) {
                                    $selected = (isset($graduation_year) && $graduation_year == $year) ? 'selected' : '';
                                    echo "<option value=\"$year\" $selected>$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="course" class="form-label">
                            <i class="fas fa-book"></i> Course/Major
                        </label>
                        <input type="text" id="course" name="course" class="form-control" 
                               value="<?php echo isset($course) ? htmlspecialchars($course) : ''; ?>" 
                               placeholder="e.g., Computer Science, Mechanical Engineering" required>
                    </div>
                    
                    <div class="form-group-half">
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <div class="password-input-wrapper">
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Create password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="password-icon"></i>
                                </button>
                            </div>
                            <small class="form-text">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock"></i> Confirm
                            </label>
                            <div class="password-input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                       placeholder="Confirm password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password-icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="auth-btn" id="registerBtn">
                        <i class="fas fa-user-plus"></i>
                        Create Student Account
                    </button>
                    
                    <p style="font-size: 13px; color: #6b7280; text-align: center; margin-top: 15px;">
                        By creating an account, you'll get access to exclusive internship opportunities and career resources.
                    </p>
                </form>
                
                <?php endif; ?>

                <div class="auth-footer">
                    <p>
                        Already have an account? 
                        <a href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Sign in here
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
        document.getElementById('registerForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            const container = document.querySelector('.register-form-container');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            container.classList.add('loading');
        });

        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('full_name');
            if (nameInput) {
                nameInput.focus();
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
                this.style.borderColor = '#e5e7eb';
                this.style.boxShadow = 'none';
            }
        });

        // Phone number validation
        document.getElementById('phone')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0,10);
        });
        
    </script>
</body>
</html>
