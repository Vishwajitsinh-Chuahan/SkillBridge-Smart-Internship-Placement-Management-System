<?php
require_once '../config/config.php';
require_once '../includes/email_functions.php';

$page_title = 'Company Registration';
$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
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

// Handle registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $industry = trim($_POST['industry']);
    $website = trim($_POST['website']);
    $company_size = $_POST['company_size'];
    $address = trim($_POST['address']);
    $description = trim($_POST['description']);

    // Validation
    if (empty($username) || empty($company_name) || empty($contact_person) ||
        empty($email) || empty($phone) || empty($password) || empty($industry)) {
        $error = 'All required fields must be filled.';
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
        
        // Get Company role ID first
        $role_stmt = $conn->prepare("SELECT id FROM roles WHERE role_name = 'Company'");
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $role_data = $role_result->fetch_assoc();
        $company_role_id = $role_data['id'];
        
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
            if ($row['role_id'] == $company_role_id) {
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
                $error = 'Username already exists for Company role. Please choose a different username or login to your existing Company account.';
            } elseif ($conflictType === 'email') {
                $error = 'Email already exists for Company role. Please use a different email or login to your existing Company account.';
            } else {
                $error = 'Username or email already exists for Company role. Please choose different credentials or login to your existing Company account.';
            }
        } else {
            
            // ✅ REGISTRATION ALLOWED - Different role or no existing account
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $conn->begin_transaction();

            try {
                // Insert user
                $stmt = $conn->prepare("INSERT INTO users (role_id, username, email, password, phone, full_name, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, 'pending', 0)");
                $stmt->bind_param("isssss", $company_role_id, $username, $email, $hashed_password, $phone, $contact_person);

                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;

                    // Insert company profile
                    $company_stmt = $conn->prepare("INSERT INTO companies (user_id, name, email, phone, website, address, industry, company_size, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $company_stmt->bind_param("issssssss", $user_id, $company_name, $email, $phone, $website, $address, $industry, $company_size, $description);
                    $company_stmt->execute();

                    // Send welcome email
                    if (function_exists('sendWelcomeEmail')) {
                        $emailResult = sendWelcomeEmail($contact_person, $email, 'Company');
                        
                        if ($emailResult['success']) {
                            $conn->commit();
                            
                            // Customize message based on existing roles
                            if (!empty($existingRoles)) {
                                $success = 'Company account created successfully! You now have multiple accounts on SkillBridge with different roles. Welcome email sent. Your account is pending approval.';
                                error_log("User {$email} now has multiple roles. Existing roles: " . implode(',', $existingRoles) . ", New role: Company ({$company_role_id})");
                            } else {
                                $success = 'Company account created! Welcome email sent. Your account is pending approval.';
                            }
                        } else {
                            $conn->commit();
                            $success = 'Company account created! Your account is pending approval.';
                            error_log("Failed to send welcome email to {$email}");
                        }
                    } else {
                        $conn->commit();
                        
                        // Customize success message for multiple roles
                        if (!empty($existingRoles)) {
                            $success = 'Company account created successfully! You now have access to both Student and Company features with the same credentials. Your account is pending approval.';
                            error_log("User {$email} registered with multiple roles. Existing roles: " . implode(',', $existingRoles) . ", New role: Company");
                        } else {
                            $success = 'Company account created! Your account is pending approval.';
                        }
                    }
                } else {
                    throw new Exception('Failed to create company account: ' . $stmt->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Registration failed: ' . $e->getMessage();
                error_log("Company registration failed for {$email}: " . $e->getMessage());
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            min-height: 100vh;
        }
        .register-container {
            min-height: 100vh;
            display: flex;
        }
        .register-left {
            flex: 0 0 45%;
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
        }
        .register-right {
            flex: 1;
            background: white;
            padding: 3rem;
            overflow-y: auto;
            max-height: 100vh;
        }
        .back-home {
            position: absolute;
            top: 2rem;
            left: 2rem;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-home:hover {
            background: rgba(255,255,255,0.25);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }
        .brand-section {
            margin-bottom: 3rem;
        }
        .brand-logo {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        .brand-logo i {
            font-size: 3rem;
            background: rgba(255,255,255,0.2);
            padding: 1rem;
            border-radius: 50%;
            backdrop-filter: blur(10px);
        }
        .brand-subtitle {
            font-size: 1.3rem;
            font-weight: 400;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        .welcome-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            font-family: 'Poppins', sans-serif;
        }
        .welcome-text {
            font-size: 1rem;
            opacity: 0.85;
            line-height: 1.6;
            max-width: 400px;
            margin: 0 auto 3rem auto;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            width: 100%;
            max-width: 450px;
            margin-bottom: 2rem;
        }
        .feature-card {
            background: rgba(255,255,255,0.15);
            padding: 1.5rem;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            transition: all 0.3s ease;
            color: white;
        }
        .feature-card:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-5px);
        }
        .feature-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: white;
            opacity: 0.9;
        }
        .feature-card h5 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
            color: white;
        }
        .feature-card p {
            font-size: 0.85rem;
            opacity: 0.8;
            line-height: 1.4;
            margin: 0;
            color: white;
        }
        .cta-section {
            background: rgba(255,255,255,0.1);
            padding: 1.5rem;
            border-radius: 16px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.1);
            max-width: 400px;
        }
        .cta-section h5 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
            color: white;
        }
        .cta-section p {
            font-size: 0.9rem;
            opacity: 0.85;
            margin: 0;
            color: white;
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
        }
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        .form-control:focus {
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .auth-btn {
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37,99,235,0.3);
            color: white;
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            padding: 0.5rem;
            cursor: pointer;
        }
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
            .feature-grid {
                grid-template-columns: 1fr;
                max-width: 100%;
            }
            .back-home {
                position: relative;
                top: auto;
                left: auto;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Fixed Left Side -->
        <div class="register-left">
            <a href="<?php echo BASE_URL; ?>/index.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
            
            <div class="brand-section">
                <div class="brand-logo">
                    <i class="fas fa-building"></i>
                    <span>SkillBridge</span>
                </div>
                <p class="brand-subtitle">Partner with Top Talent</p>
                
                <h3 class="welcome-title">Find the Best Students</h3>
                <p class="welcome-text">
                    Register your company to access talented students, post internship opportunities, and build your future workforce.
                </p>
            </div>
            
            <!-- Fixed Feature Cards -->
            <div class="feature-grid">
                <div class="feature-card">
                    <i class="fas fa-briefcase"></i>
                    <h5>Post Internships</h5>
                    <p>Create and manage internship listings easily</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-users"></i>
                    <h5>Access Talent Pool</h5>
                    <p>Browse qualified student profiles</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-bar"></i>
                    <h5>Track Applications</h5>
                    <p>Monitor and manage applications efficiently</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-handshake"></i>
                    <h5>Build Partnerships</h5>
                    <p>Connect with universities and institutions</p>
                </div>
            </div>
            
            <div class="cta-section">
                <h5>Ready to find great talent?</h5>
                <p>Complete the registration form to get started</p>
            </div>
        </div>

        <!-- Right Side - Registration Form -->
        <div class="register-right">
            <div class="register-header">
                <h1>Company Registration</h1>
                <p>Create your company account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <div class="text-center">
                    <a href="../auth/login.php" class="btn auth-btn">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
            <?php else: ?>

            <!-- Bootstrap Form -->
            <form method="POST" action="" class="row g-3">
                <!-- Company Name - Full Width -->
                <div class="col-12">
                    <label for="company_name" class="form-label">
                        <i class="fas fa-building"></i> Company Name *
                    </label>
                    <input type="text" id="company_name" name="company_name" class="form-control"
                           value="<?php echo isset($company_name) ? htmlspecialchars($company_name) : ''; ?>"
                           placeholder="Enter company name" required>
                </div>

                <!-- Contact Person & Username -->
                <div class="col-md-6">
                    <label for="contact_person" class="form-label">
                        <i class="fas fa-user"></i> Contact Person *
                    </label>
                    <input type="text" id="contact_person" name="contact_person" class="form-control"
                           value="<?php echo isset($contact_person) ? htmlspecialchars($contact_person) : ''; ?>"
                           placeholder="Your full name" required>
                </div>
                <div class="col-md-6">
                    <label for="username" class="form-label">
                        <i class="fas fa-at"></i> Username *
                    </label>
                    <input type="text" id="username" name="username" class="form-control"
                           value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                           placeholder="Choose username" required>
                </div>

                <!-- Email & Phone -->
                <div class="col-md-6">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> Email Address *
                    </label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                           placeholder="company@example.com" required>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">
                        <i class="fas fa-phone"></i> Phone Number *
                    </label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>"
                           placeholder="10-digit number" maxlength="10" pattern="\d{10}" required>
                </div>

                <!-- Industry & Company Size -->
                <div class="col-md-6">
                    <label for="industry" class="form-label">
                        <i class="fas fa-industry"></i> Industry *
                    </label>
                    <input type="text" id="industry" name="industry" class="form-control"
                           value="<?php echo isset($industry) ? htmlspecialchars($industry) : ''; ?>"
                           placeholder="e.g., Technology, Healthcare" required>
                </div>
                <div class="col-md-6">
                    <label for="company_size" class="form-label">
                        <i class="fas fa-users"></i> Company Size *
                    </label>
                    <select id="company_size" name="company_size" class="form-select" required>
                        <option value="">Select size</option>
                        <option value="Startup">Startup (1-10)</option>
                        <option value="Small">Small (11-50)</option>
                        <option value="Medium">Medium (51-250)</option>
                        <option value="Large">Large (251-1000)</option>
                        <option value="Enterprise">Enterprise (1000+)</option>
                    </select>
                </div>

                <!-- Website -->
                <div class="col-12">
                    <label for="website" class="form-label">
                        <i class="fas fa-globe"></i> Company Website
                    </label>
                    <input type="url" id="website" name="website" class="form-control"
                           value="<?php echo isset($website) ? htmlspecialchars($website) : ''; ?>"
                           placeholder="https://www.company.com">
                    <div class="form-text">Optional but recommended</div>
                </div>

                <!-- Address -->
                <div class="col-12">
                    <label for="address" class="form-label">
                        <i class="fas fa-map-marker-alt"></i> Company Address
                    </label>
                    <textarea id="address" name="address" class="form-control" rows="3"
                              placeholder="Enter complete address"><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label for="description" class="form-label">
                        <i class="fas fa-info-circle"></i> Company Description
                    </label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Brief description about your company"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    <div class="form-text">This will be shown to students</div>
                </div>

                <!-- Password & Confirm Password -->
                <div class="col-md-6">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password *
                    </label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Create password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                    <div class="form-text">Minimum 6 characters</div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password *
                    </label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                               placeholder="Confirm password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye" id="confirm_password-icon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="col-12">
                    <button type="submit" class="btn auth-btn w-100">
                        <i class="fas fa-building"></i> Create Company Account
                    </button>
                </div>

                <div class="col-12 text-center">
                    <small class="text-muted">
                        By creating an account, you'll get access to talented students and recruitment tools. Your account will be reviewed for approval.
                    </small>
                </div>
            </form>

            <?php endif; ?>

            <div class="text-center mt-4">
                <p class="mb-2">
                    Already have an account? 
                    <a href="../auth/login.php" class="text-decoration-none">Sign in here</a>
                </p>
                <p class="mb-0">
                    Looking to register as a student? 
                    <a href="../auth/register.php" class="text-decoration-none">Register as Student</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        // Phone number validation
        document.getElementById('phone')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0,10);
        });
    </script>
</body>
</html>
