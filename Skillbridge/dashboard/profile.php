<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include configuration
require_once '../config/config.php';

// Page configuration
$page_title = 'Profile Settings';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Initialize variables
$success = '';
$error = '';
$profile_data = [];

// Fetch user profile data
try {
    // Get basic user info
    $stmt = $conn->prepare("SELECT username, email, full_name, phone, status, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    
    // Fetch profile data based on role
    if ($role === 'Student') {
        // Get student profile data
        $stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile_result = $stmt->get_result();
        
        if ($profile_result && $profile_result->num_rows > 0) {
            $profile_data = $profile_result->fetch_assoc();
        } else {
            $profile_data = [
                'university' => '',
                'course' => '',
                'graduation_year' => '',
                'cgpa' => '',
                'skills' => '',
                'bio' => '',
                'year_of_study' => '',
                'resume_path' => ''
            ];
        }
    } else if ($role === 'Company') {
        // Get company profile data with correct column names
        $stmt = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile_result = $stmt->get_result();
        
        if ($profile_result && $profile_result->num_rows > 0) {
            $company_data = $profile_result->fetch_assoc();
            $profile_data = [
                'name' => $company_data['name'] ?? '',
                'email' => $company_data['email'] ?? '', 
                'phone' => $company_data['phone'] ?? '',
                'industry' => $company_data['industry'] ?? '',
                'website' => $company_data['website'] ?? '',
                'address' => $company_data['address'] ?? '',
                'company_size' => $company_data['company_size'] ?? '',
                'description' => $company_data['description'] ?? '',
                'logo_path' => $company_data['logo_path'] ?? '',
                'status' => $company_data['status'] ?? ''
            ];
        } else {
            $profile_data = [
                'name' => '',
                'email' => '',
                'phone' => '',
                'industry' => '',
                'website' => '',
                'address' => '',
                'company_size' => '',
                'description' => '',
                'logo_path' => '',
                'status' => ''
            ];
        }
    }
    
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $user_data = ['username' => '', 'email' => '', 'full_name' => '', 'phone' => '', 'status' => 'active', 'created_at' => date('Y-m-d H:i:s')];
    $profile_data = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        try {
            // Update basic user info first
            $full_name_new = trim($_POST['full_name']);
            $phone_user = trim($_POST['phone_user']);
            
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssi", $full_name_new, $phone_user, $user_id);
            $stmt->execute();
            
            // Update session
            $_SESSION['full_name'] = $full_name_new;
            
            if ($role === 'Student') {
                // Handle student profile update
                $university = trim($_POST['university'] ?? '');
                $course = trim($_POST['course'] ?? '');
                $graduation_year = (int)($_POST['graduation_year'] ?? 0);
                $cgpa = (float)($_POST['cgpa'] ?? 0);
                $skills = trim($_POST['skills'] ?? '');
                $bio = trim($_POST['bio'] ?? '');
                $year_of_study = (int)($_POST['year_of_study'] ?? 0);
                
                $resume_path = $profile_data['resume_path'] ?? '';
                
                // Handle resume upload
                if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/resumes/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $tmp_name = $_FILES['resume']['tmp_name'];
                    $filename = basename($_FILES['resume']['name']);
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if ($ext === 'pdf') {
                        if ($_FILES['resume']['size'] <= 5 * 1024 * 1024) {
                            $new_filename = $user_id . '_resume_' . time() . '.pdf';
                            $destination = $upload_dir . $new_filename;
                            if (move_uploaded_file($tmp_name, $destination)) {
                                $resume_path = 'uploads/resumes/' . $new_filename;
                            } else {
                                $error = 'Failed to upload resume.';
                            }
                        } else {
                            $error = 'Resume file size must be less than 5MB.';
                        }
                    } else {
                        $error = 'Only PDF files are allowed for resume.';
                    }
                }
                
                // Check if student profile exists
                $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $exists = $stmt->get_result()->num_rows > 0;
                
                if ($exists) {
                    $stmt = $conn->prepare("UPDATE student_profiles SET university=?, course=?, graduation_year=?, cgpa=?, skills=?, bio=?, year_of_study=?, resume_path=? WHERE user_id=?");
                    $stmt->bind_param("ssidssisi", $university, $course, $graduation_year, $cgpa, $skills, $bio, $year_of_study, $resume_path, $user_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO student_profiles (user_id, university, course, graduation_year, cgpa, skills, bio, year_of_study, resume_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issidssiss", $user_id, $university, $course, $graduation_year, $cgpa, $skills, $bio, $year_of_study, $resume_path);
                }
                
                if ($stmt->execute()) {
                    $success = 'Student profile updated successfully! 🎓';
                    $profile_data = [
                        'university' => $university,
                        'course' => $course,
                        'graduation_year' => $graduation_year,
                        'cgpa' => $cgpa,
                        'skills' => $skills,
                        'bio' => $bio,
                        'year_of_study' => $year_of_study,
                        'resume_path' => $resume_path
                    ];
                } else {
                    $error = 'Failed to update student profile.';
                }
                $stmt->close();
                
            } else if ($role === 'Company') {
                // Handle company profile update with correct column names
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $industry = trim($_POST['industry'] ?? '');
                $website = trim($_POST['website'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $company_size = trim($_POST['company_size'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                // Check if company profile exists
                $stmt = $conn->prepare("SELECT id FROM companies WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $exists = $stmt->get_result()->num_rows > 0;
                
                if ($exists) {
                    $stmt = $conn->prepare("UPDATE companies SET name=?, email=?, phone=?, industry=?, website=?, address=?, company_size=?, description=? WHERE user_id=?");
                    $stmt->bind_param("ssssssssi", $name, $email, $phone, $industry, $website, $address, $company_size, $description, $user_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO companies (user_id, name, email, phone, industry, website, address, company_size, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                    $stmt->bind_param("issssssss", $user_id, $name, $email, $phone, $industry, $website, $address, $company_size, $description);
                }
                
                if ($stmt->execute()) {
                    $success = 'Company profile updated successfully! 🏢';
                    $profile_data = [
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'industry' => $industry,
                        'website' => $website,
                        'address' => $address,
                        'company_size' => $company_size,
                        'description' => $description
                    ];
                } else {
                    $error = 'Failed to update company profile.';
                }
                $stmt->close();
            }
            
            // Update user data array
            $user_data['full_name'] = $full_name_new;
            $user_data['phone'] = $phone_user;
            
        } catch (Exception $e) {
            $error = 'Something went wrong while updating your profile.';
            error_log("Profile update error: " . $e->getMessage());
        }
    }
    
    if (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            try {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_pass = $result->fetch_assoc();
                
                if (password_verify($current_password, $user_pass['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = 'Password changed successfully! 🔒';
                    } else {
                        $error = 'Failed to change password. Please try again.';
                    }
                } else {
                    $error = 'Current password is incorrect. Please check and try again.';
                }
            } catch (Exception $e) {
                $error = 'An error occurred while changing your password.';
                error_log("Password change error: " . $e->getMessage());
            }
        }
    }
}

// Get user initials for avatar
$user_initials = strtoupper(substr($full_name, 0, 1));
if (strpos($full_name, ' ') !== false) {
    $name_parts = explode(' ', $full_name);
    $user_initials = strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - SkillBridge</title>
    
    <!-- CSS Files -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/dashboard.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- SkillBridge Colors -->
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #059669;
            --gradient: linear-gradient(135deg, #2563eb 0%, #059669 100%);
        }
        
        .profile-container {
            max-width: none;
            margin: 0;
            width: 100%;
            padding: 0;
        }

        .uni-header {
            background: var(--gradient);
            color: white;
            padding: 2.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            width: 100%;
            box-sizing: border-box;
        }

        .uni-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        .student-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
            position: relative;
            z-index: 1;
        }

        .uni-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            font-weight: 600;
        }

        .uni-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            width: 100%;
            box-sizing: border-box;
        }

        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            width: 100%;
            box-sizing: border-box;
        }

        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .card-title {
            margin: 0;
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .btn-primary {
            background: var(--gradient);
            color: white;
            padding: 0.8rem 1.8rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            box-sizing: border-box;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .account-info {
            display: grid;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.85rem;
        }

        .info-value {
            color: #1e293b;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-active {
            color: #10b981;
            font-weight: 600;
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.3rem;
            border-radius: 4px;
        }

        .password-toggle:hover {
            color: #374151;
            background: #f3f4f6;
        }

        .resume-section {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 2px dashed #cbd5e1;
            text-align: center;
            margin-bottom: 1rem;
        }

        .current-resume {
            background: #ecfdf5;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
            color: #065f46;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-logo i { color: var(--primary-color); }
        .nav-item.active { background: var(--gradient); }

        .dashboard-main {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 2rem;
            min-height: 100vh;
            box-sizing: border-box;
        }

        input:invalid {
            border-color: #e5e7eb !important;
            box-shadow: none !important;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .dashboard-main {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .uni-header {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .student-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
            
            .uni-header h1 {
                font-size: 1.5rem;
            }
            
            .profile-card {
                padding: 1.5rem;
            }
            
            .dashboard-main {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .dashboard-main {
                padding: 0.5rem;
            }
            
            .uni-header {
                padding: 1rem;
                border-radius: 10px;
            }
            
            .profile-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar" id="sidebar">
        <!-- Logo -->
        <div class="sidebar-header">
            <a href="<?php echo BASE_URL; ?>" class="sidebar-logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </a>
        </div>

        <!-- Scrollable Navigation Container -->
        <div class="sidebar-scroll-container">
            <!-- Navigation -->
            <nav class="sidebar-nav">
                <!-- Dashboard Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="<?php echo BASE_URL; ?>/dashboard/<?php echo strtolower($role); ?>.php" class="nav-item">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard Overview
                    </a>
                </div>

                <?php if ($role === 'Student'): ?>
                    <!-- Student Navigation -->
                    <div class="nav-section">
                        <div class="nav-section-title">Internships</div>
                        <a href="<?php echo BASE_URL; ?>/internships/browse.php" class="nav-item">
                            <i class="fas fa-search"></i>
                            Browse Internships
                        </a>
                        <a href="<?php echo BASE_URL; ?>/internships/categories.php" class="nav-item">
                            <i class="fas fa-tags"></i>
                            Categories
                        </a>
                        <a href="<?php echo BASE_URL; ?>/internships/saved.php" class="nav-item">
                            <i class="fas fa-bookmark"></i>
                            Saved Internships
                        </a>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">My Applications</div>
                        <a href="<?php echo BASE_URL; ?>/applications/my-applications.php" class="nav-item">
                            <i class="fas fa-file-alt"></i>
                            All Applications
                        </a>
                        <a href="<?php echo BASE_URL; ?>/applications/pending.php" class="nav-item">
                            <i class="fas fa-clock"></i>
                            Pending Review
                        </a>
                        <a href="<?php echo BASE_URL; ?>/applications/interviews.php" class="nav-item">
                            <i class="fas fa-users"></i>
                            Interviews
                        </a>
                        <a href="<?php echo BASE_URL; ?>/applications/offers.php" class="nav-item">
                            <i class="fas fa-trophy"></i>
                            Offers Received
                        </a>
                    </div>
                <?php elseif ($role === 'Company'): ?>
                    <!-- Company Navigation -->
                    <div class="nav-section">
                        <div class="nav-section-title">Internships</div>
                        <a href="<?php echo BASE_URL; ?>/internships/create.php" class="nav-item">
                            <i class="fas fa-plus-circle"></i>
                            Post New Internship
                        </a>
                        <a href="<?php echo BASE_URL; ?>/internships/manage.php" class="nav-item">
                            <i class="fas fa-list"></i>
                            Manage Internships
                        </a>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">Applications</div>
                        <a href="<?php echo BASE_URL; ?>/applications/received.php" class="nav-item">
                            <i class="fas fa-inbox"></i>
                            Received Applications
                        </a>
                        <a href="<?php echo BASE_URL; ?>/applications/shortlisted.php" class="nav-item">
                            <i class="fas fa-star"></i>
                            Shortlisted Candidates
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Settings Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <a href="<?php echo BASE_URL; ?>/dashboard/profile.php" class="nav-item active">
                        <i class="fas fa-user-edit"></i>
                        Profile Settings
                    </a>
                    <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- User Profile -->
        <div class="user-profile">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo htmlspecialchars($user_initials); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($full_name); ?></h4>
                    <p><?php echo ucfirst($role); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="dashboard-main">
        <!-- Mobile Toggle -->
        <button class="mobile-toggle" onclick="toggleSidebar()" style="display: none;">
            <i class="fas fa-bars"></i>
        </button>

        <div class="profile-container">
            <!-- SkillBridge Profile Header -->
            <div class="uni-header">
                <div class="student-avatar">
                    <?php echo htmlspecialchars($user_initials); ?>
                </div>
                <h1><?php echo htmlspecialchars($full_name); ?></h1>
                <p><?php echo ucfirst($role); ?> Profile Settings</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Profile Grid -->
            <div class="profile-grid">
                <!-- Profile Information Form -->
                <div class="profile-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i>
                            <?php echo $role === 'Student' ? 'Student Information' : 'Company Information'; ?>
                        </h3>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <!-- Common Fields -->
                        <div class="form-group form-group-full">
                            <label for="full_name" class="form-label">
                                <?php echo $role === 'Student' ? 'Full Name' : 'Contact Person Name'; ?>
                            </label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" 
                                   placeholder="<?php echo $role === 'Student' ? 'Enter your full name' : 'Enter contact person name'; ?>" required>
                        </div>
                        
                        <div class="form-group form-group-full">
                            <label for="phone_user" class="form-label">Phone Number</label>
                            <input type="text" id="phone_user" name="phone_user" class="form-control" 
                                   maxlength="10" minlength="10" pattern="\d{10}"
                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" 
                                   placeholder="Enter 10-digit mobile number" required
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10);"
                                   inputmode="numeric">
                            <small class="form-text" id="phoneHelp" style="color:#dc2626;display:none;">
                                Phone number must be exactly 10 digits.
                            </small>
                        </div>

                        <?php if ($role === 'Student'): ?>
                            <!-- Student Specific Fields -->
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="university" class="form-label">College/University</label>
                                    <input type="text" id="university" name="university" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['university'] ?? ''); ?>" 
                                           placeholder="Enter your college name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="course" class="form-label">Course/Branch</label>
                                    <input type="text" id="course" name="course" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['course'] ?? ''); ?>" 
                                           placeholder="e.g., Computer Science, MBA">
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="year_of_study" class="form-label">Current Year</label>
                                    <select id="year_of_study" name="year_of_study" class="form-control">
                                        <option value="">Select Year</option>
                                        <option value="1" <?php echo (isset($profile_data['year_of_study']) && $profile_data['year_of_study'] == 1) ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2" <?php echo (isset($profile_data['year_of_study']) && $profile_data['year_of_study'] == 2) ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3" <?php echo (isset($profile_data['year_of_study']) && $profile_data['year_of_study'] == 3) ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4" <?php echo (isset($profile_data['year_of_study']) && $profile_data['year_of_study'] == 4) ? 'selected' : ''; ?>>4th Year</option>
                                        <option value="5" <?php echo (isset($profile_data['year_of_study']) && $profile_data['year_of_study'] == 5) ? 'selected' : ''; ?>>5th Year</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="graduation_year" class="form-label">Expected Graduation</label>
                                    <input type="number" id="graduation_year" name="graduation_year" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['graduation_year'] ?? ''); ?>" 
                                           placeholder="2025" min="2024" max="2030">
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="cgpa" class="form-label">CGPA</label>
                                    <input type="number" id="cgpa" name="cgpa" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['cgpa'] ?? ''); ?>" 
                                           placeholder="8.5" step="0.01" min="0" max="10">
                                </div>
                                
                                <div class="form-group">
                                    <label for="skills" class="form-label">Skills</label>
                                    <input type="text" id="skills" name="skills" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['skills'] ?? ''); ?>" 
                                           placeholder="e.g., Python, JavaScript, React">
                                </div>
                            </div>
                            
                            <!-- Resume Upload -->
                            <div class="form-group form-group-full">
                                <label for="resume" class="form-label">Resume (PDF only)</label>
                                
                                <?php if (!empty($profile_data['resume_path'])): ?>
                                    <div class="current-resume">
                                        <i class="fas fa-file-pdf"></i>
                                        <span>Current Resume: <?php echo basename($profile_data['resume_path']); ?></span>
                                        <a href="<?php echo BASE_URL . '/' . $profile_data['resume_path']; ?>" target="_blank" style="margin-left: auto; color: #059669;">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="resume-section">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #64748b; margin-bottom: 0.5rem;"></i>
                                    <p style="margin: 0; color: #64748b;">Upload new resume (PDF, max 5MB)</p>
                                    <input type="file" id="resume" name="resume" class="form-control" accept=".pdf" style="margin-top: 0.5rem;">
                                </div>
                            </div>
                            
                            <div class="form-group form-group-full">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea id="bio" name="bio" class="form-control" rows="4" 
                                          placeholder="Tell us about yourself, your interests, and career goals..."><?php echo htmlspecialchars($profile_data['bio'] ?? ''); ?></textarea>
                            </div>

                        <?php elseif ($role === 'Company'): ?>
                            <!-- Company Specific Fields with correct column names -->
                            <div class="form-group form-group-full">
                                <label for="name" class="form-label">Company Name *</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($profile_data['name'] ?? ''); ?>" 
                                       placeholder="Enter your company name" required>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="email" class="form-label">Company Email *</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['email'] ?? ''); ?>" 
                                           placeholder="contact@company.com" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone" class="form-label">Company Phone *</label>
                                    <input type="text" id="phone" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['phone'] ?? ''); ?>" 
                                           placeholder="8692241416" required maxlength="15">
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="industry" class="form-label">Industry *</label>
                                    <input type="text" id="industry" name="industry" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile_data['industry'] ?? ''); ?>" 
                                           placeholder="e.g., Agentic AI solutions" required>
                                    <small style="color: #64748b; font-size: 0.75rem;">Enter your business industry</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_size" class="form-label">Company Size</label>
                                    <select id="company_size" name="company_size" class="form-control">
                                        <option value="">Select Size</option>
                                        <option value="Small" <?php echo (isset($profile_data['company_size']) && $profile_data['company_size'] == 'Small') ? 'selected' : ''; ?>>Small (1-50 employees)</option>
                                        <option value="Medium" <?php echo (isset($profile_data['company_size']) && $profile_data['company_size'] == 'Medium') ? 'selected' : ''; ?>>Medium (51-200 employees)</option>
                                        <option value="Large" <?php echo (isset($profile_data['company_size']) && $profile_data['company_size'] == 'Large') ? 'selected' : ''; ?>>Large (201+ employees)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group form-group-full">
                                <label for="website" class="form-label">Website URL</label>
                                <input type="url" id="website" name="website" class="form-control" 
                                       value="<?php echo htmlspecialchars($profile_data['website'] ?? ''); ?>" 
                                       placeholder="http://www.dynatech.com">
                                <small style="color: #64748b; font-size: 0.75rem;">Include http:// or https://</small>
                            </div>
                            
                            <div class="form-group form-group-full">
                                <label for="address" class="form-label">Company Address *</label>
                                <textarea id="address" name="address" class="form-control" rows="3" 
                                          placeholder="Enter complete company address including city, state, and pincode" required><?php echo htmlspecialchars($profile_data['address'] ?? ''); ?></textarea>
                                <small style="color: #64748b; font-size: 0.75rem;">This address will be visible to students</small>
                            </div>
                            
                            <div class="form-group form-group-full">
                                <label for="description" class="form-label">Company Description</label>
                                <textarea id="description" name="description" class="form-control" rows="4" 
                                          placeholder="Describe your company, its mission, services, and what makes it a great place for internships..."><?php echo htmlspecialchars($profile_data['description'] ?? ''); ?></textarea>
                                <small style="color: #64748b; font-size: 0.75rem;">This will be visible to students browsing internships</small>
                            </div>

                            <!-- Company Status Display (Read-only) -->
                            <?php if (!empty($profile_data['status'])): ?>
                                <div class="form-group form-group-full">
                                    <label class="form-label">Company Status</label>
                                    <div style="padding: 0.75rem; background: <?php echo $profile_data['status'] === 'active' ? '#ecfdf5' : '#fef3c7'; ?>; border-radius: 8px; border: 1px solid <?php echo $profile_data['status'] === 'active' ? '#a7f3d0' : '#fed7aa'; ?>;">
                                        <span style="color: <?php echo $profile_data['status'] === 'active' ? '#065f46' : '#92400e'; ?>; font-weight: 600;">
                                            <i class="fas fa-<?php echo $profile_data['status'] === 'active' ? 'check-circle' : 'clock'; ?>"></i>
                                            <?php echo ucfirst($profile_data['status']); ?>
                                        </span>
                                        <?php if ($profile_data['status'] === 'pending'): ?>
                                            <small style="display: block; margin-top: 0.5rem; color: #92400e;">
                                                Your company profile is under review by our admin team.
                                            </small>
                                        <?php elseif ($profile_data['status'] === 'approved'): ?>
                                            <small style="display: block; margin-top: 0.5rem; color: #065f46;">
                                                Your company is approved and can post internships.
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <button type="submit" name="update_profile" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Account Information & Password Change -->
                <div>
                    <!-- Account Information -->
                    <div class="profile-card" style="margin-bottom: 2rem;">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-id-card"></i>
                                Account Details
                            </h3>
                        </div>
                        
                        <div class="account-info">
                            <div class="info-item">
                                <span class="info-label">Username</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['username']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Role</span>
                                <span class="info-value"><?php echo ucfirst($role); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value status-active">
                                    <i class="fas fa-check-circle"></i> 
                                    <?php echo ucfirst($user_data['status']); ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($user_data['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="profile-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-lock"></i>
                                Change Password
                            </h3>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="current_password" name="current_password" 
                                           class="form-control" placeholder="Enter current password" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye" id="current_password-icon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="new_password" name="new_password" 
                                           class="form-control" placeholder="Enter new password" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye" id="new_password-icon"></i>
                                    </button>
                                </div>
                                <small style="color: #64748b; font-size: 0.75rem;">Minimum 6 characters required</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           class="form-control" placeholder="Confirm new password" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye" id="confirm_password-icon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn-primary">
                                <i class="fas fa-key"></i>
                                Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password visibility toggle
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

// Sidebar toggle function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('mobile-open');
}

// Phone validation
document.getElementById('phone_user').addEventListener('input', function() {
    document.getElementById('phoneHelp').style.display = 
        (this.value.length === 0 || this.value.length === 10) ? "none" : "block";
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.mobile-toggle');
    
    if (window.innerWidth <= 1024 && 
        !sidebar.contains(event.target) && 
        !toggle.contains(event.target)) {
        sidebar.classList.remove('mobile-open');
    }
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});
</script>

</body>
</html>
