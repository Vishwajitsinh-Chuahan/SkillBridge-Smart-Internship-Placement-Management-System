<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
requireAdmin();

$page_title = 'User Details';
$admin_name = $_SESSION['full_name'];

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header("Location: users.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_user') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $status = $_POST['status'];
        
        if (!empty($full_name) && !empty($email)) {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $full_name, $email, $phone, $status, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "User details updated successfully.";
                error_log("Admin {$admin_name} updated user ID: $user_id");
            } else {
                $error_message = "Failed to update user details.";
            }
        } else {
            $error_message = "Full name and email are required.";
        }
    }
}

// ✅ CORRECTED QUERY: Only use existing columns from your database
$user_query = "
    SELECT u.id, u.username, u.full_name, u.email, u.phone, u.status, u.created_at, u.updated_at,
           r.role_name, r.id as role_id,
           CASE 
               WHEN r.role_name = 'Company' THEN c.name
               ELSE NULL
           END as company_name,
           CASE 
               WHEN r.role_name = 'Company' THEN c.industry
               ELSE NULL
           END as company_industry,
           CASE 
               WHEN r.role_name = 'Company' THEN c.company_size
               ELSE NULL
           END as company_size,
           CASE 
               WHEN r.role_name = 'Company' THEN c.website
               ELSE NULL
           END as company_website,
           CASE 
               WHEN r.role_name = 'Company' THEN c.address
               ELSE NULL
           END as company_address,
           CASE 
               WHEN r.role_name = 'Student' THEN sp.course
               ELSE NULL
           END as student_course,
           CASE 
               WHEN r.role_name = 'Student' THEN sp.graduation_year
               ELSE NULL
           END as student_graduation_year,
           CASE 
               WHEN r.role_name = 'Student' THEN sp.university
               ELSE NULL
           END as student_university,
           CASE 
               WHEN r.role_name = 'Student' THEN sp.cgpa
               ELSE NULL
           END as student_cgpa,
           CASE 
               WHEN r.role_name = 'Student' THEN sp.skills
               ELSE NULL
           END as student_skills,
           CASE 
               WHEN r.role_name = 'Student' THEN sp.resume_path
               ELSE NULL
           END as student_resume,
           CASE 
               WHEN r.role_name = 'Student' THEN sp.year_of_study
               ELSE NULL
           END as student_year_of_study,
           CASE 
                WHEN r.role_name = 'Student' THEN sp.github
                ELSE NULL
           END as student_github,
           CASE 
                WHEN r.role_name = 'Student' THEN sp.linkedin
                ELSE NULL
           END as student_linkedin,
           CASE 
               WHEN r.role_name = 'Student' THEN sp.bio
               ELSE NULL
           END as student_bio
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN companies c ON u.id = c.user_id AND r.role_name = 'Company'
    LEFT JOIN student_profiles sp ON u.id = sp.user_id AND r.role_name = 'Student'
    WHERE u.id = ?
";

$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();
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
            background-color: #f8fafc;
            color: #334155;
        }

        /* Sidebar - Same as other pages */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background-color: #334155;
            color: #cbd5e1;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(203, 213, 225, 0.1);
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #ffffff;
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .sidebar-header .logo i {
            color: #3b82f6;
            font-size: 1.5rem;
        }

        .nav-section {
            padding: 0.5rem 0;
        }

        .nav-section-title {
            padding: 0.75rem 1rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background-color: rgba(59, 130, 246, 0.1);
            color: #ffffff;
            border-left-color: #3b82f6;
        }

        .nav-link.active {
            background-color: rgba(59, 130, 246, 0.15);
            color: #ffffff;
            border-left-color: #3b82f6;
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #3b82f6;
        }

        .user-profile {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background-color: rgba(0, 0, 0, 0.1);
            border-top: 1px solid rgba(203, 213, 225, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-details h4 {
            color: #ffffff;
            font-size: 0.9rem;
            margin-bottom: 0.125rem;
        }

        .user-details p {
            color: #94a3b8;
            font-size: 0.8rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 240px;
            min-height: 100vh;
            background-color: #f8fafc;
        }

        .header {
            background: #ffffff;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            color: #1e293b;
            font-size: 1.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .header-title p {
            color: #64748b;
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #475569;
            color: white;
        }

        /* Messages */
        .success-message {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #34d399;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .error-message {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #f87171;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* User Details */
        .user-details-section {
            padding: 2rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .detail-card {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .card-icon.user {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .card-icon.info {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .card-icon.company {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .card-icon.student {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .card-title {
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .detail-item {
            margin-bottom: 1.5rem;
        }

        .detail-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            color: #1e293b;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .detail-value.empty {
            color: #94a3b8;
            font-style: italic;
        }

        .large-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-inactive {
            background-color: #f1f5f9;
            color: #64748b;
        }

        .role-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }

        .role-student {
            background-color: #eff6ff;
            color: #1d4ed8;
        }

        .role-company {
            background-color: #f0fdf4;
            color: #166534;
        }

        .role-admin {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* Resume and Document Links */
        .resume-section {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1px solid #93c5fd;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .resume-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .resume-link:hover {
            background: #2563eb;
            color: white;
            transform: translateY(-1px);
        }

        /* Form Styles */
        .form-section {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            background-color: white;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                padding: 1rem;
            }
            
            .user-details-section {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">DASHBOARD</div>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard Overview
                </a>
                <a href="company-approvals.php" class="nav-link">
                    <i class="fas fa-building"></i>
                    Company Approvals
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="users.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    User Management
                </a>
                <a href="internships.php" class="nav-link">
                    <i class="fas fa-briefcase"></i>
                    Internship Posts
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">SETTINGS</div>
                <a href="analytics.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Analytics
                </a>
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>
        
        <!-- User Profile -->
        <div class="user-profile">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-title">
                <h1>User Details</h1>
                <p>View and manage user account information</p>
            </div>
            <div class="header-actions">
                <a href="users.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Users
                </a>
            </div>
        </header>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- User Details -->
        <div class="user-details-section">
            <div class="details-grid">
                <!-- Basic Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <div class="card-icon user">
                            <i class="fas fa-user"></i>
                        </div>
                        <h3 class="card-title">Basic Information</h3>
                    </div>
                    
                    <div class="large-avatar">
                        <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Full Name</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Username</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value">
                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: #3b82f6; text-decoration: none;">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </a>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value <?php echo empty($user['phone']) ? 'empty' : ''; ?>">
                            <?php if ($user['phone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>" style="color: #3b82f6; text-decoration: none;">
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <div class="card-icon info">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h3 class="card-title">Account Information</h3>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">User Role</div>
                        <div class="detail-value">
                            <span class="role-badge role-<?php echo strtolower($user['role_name']); ?>">
                                <?php echo htmlspecialchars($user['role_name']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Account Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                <i class="fas fa-<?php echo $user['status'] === 'active' ? 'check' : ($user['status'] === 'rejected' ? 'times' : 'clock'); ?>"></i>
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Registration Date</div>
                        <div class="detail-value">
                            <?php echo date('F j, Y \a\t g:i A', strtotime($user['created_at'])); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Last Updated</div>
                        <div class="detail-value">
                            <?php echo $user['updated_at'] ? date('F j, Y \a\t g:i A', strtotime($user['updated_at'])) : 'Never'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Role-Specific Information -->
            <?php if ($user['role_name'] === 'Company' && $user['company_name']): ?>
            <div class="detail-card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <div class="card-icon company">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="card-title">Company Information</h3>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div class="detail-item">
                        <div class="detail-label">Company Name</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($user['company_name']); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Industry</div>
                        <div class="detail-value <?php echo empty($user['company_industry']) ? 'empty' : ''; ?>">
                            <?php echo htmlspecialchars($user['company_industry'] ?: 'Not specified'); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Company Size</div>
                        <div class="detail-value <?php echo empty($user['company_size']) ? 'empty' : ''; ?>">
                            <?php echo htmlspecialchars($user['company_size'] ?: 'Not specified'); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Website</div>
                        <div class="detail-value <?php echo empty($user['company_website']) ? 'empty' : ''; ?>">
                            <?php if ($user['company_website']): ?>
                                <a href="<?php echo htmlspecialchars($user['company_website']); ?>" target="_blank" style="color: #3b82f6; text-decoration: none;">
                                    <?php echo htmlspecialchars($user['company_website']); ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 0.5rem; font-size: 0.8rem;"></i>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($user['company_address']): ?>
                <div class="detail-item" style="margin-top: 1.5rem;">
                    <div class="detail-label">Address</div>
                    <div class="detail-value">
                        <?php echo nl2br(htmlspecialchars($user['company_address'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ✅ CORRECTED: Student Educational Information using existing database columns -->
            <?php if ($user['role_name'] === 'Student'): ?>
            <div class="detail-card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <div class="card-icon student">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3 class="card-title">Student Educational Details</h3>
                </div>
                
                <?php if ($user['student_resume']): ?>
                    <div class="resume-section">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <div>
                                <h4 style="color: #1d4ed8; margin-bottom: 0.5rem;">📄 Resume Document</h4>
                                <p style="color: #64748b; font-size: 0.875rem;">View and download student resume</p>
                            </div>
                            <a href="../uploads/resumes/<?php echo htmlspecialchars($user['student_resume']); ?>" 
                               target="_blank" class="resume-link">
                                <i class="fas fa-file-pdf"></i>
                                View Resume
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php if ($user['student_course']): ?>
                    <div class="detail-item">
                        <div class="detail-label">Course of Study</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($user['student_course']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['student_university']): ?>
                    <div class="detail-item">
                        <div class="detail-label">University/Institution</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($user['student_university']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['student_graduation_year']): ?>
                    <div class="detail-item">
                        <div class="detail-label">Graduation Year</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($user['student_graduation_year']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['student_cgpa']): ?>
                    <div class="detail-item">
                        <div class="detail-label">CGPA/GPA</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($user['student_cgpa']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['student_year_of_study']): ?>
                    <div class="detail-item">
                        <div class="detail-label">Current Year of Study</div>
                        <div class="detail-value">
                            Year <?php echo htmlspecialchars($user['student_year_of_study']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['student_skills']): ?>
                    <div class="detail-item">
                        <div class="detail-label">Skills & Competencies</div>
                        <div class="detail-value">
                            <?php echo nl2br(htmlspecialchars($user['student_skills'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($user['student_github'])): ?>
<div class="detail-item">
    <div class="detail-label">GitHub Profile</div>
    <div class="detail-value">
        <a href="<?php echo htmlspecialchars($user['student_github']); ?>" target="_blank">
            <i class="fab fa-github"></i> <?php echo htmlspecialchars($user['student_github']); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ✅ LinkedIn Link -->
<?php if (!empty($user['student_linkedin'])): ?>
<div class="detail-item">
    <div class="detail-label">LinkedIn Profile</div>
    <div class="detail-value">
        <a href="<?php echo htmlspecialchars($user['student_linkedin']); ?>" target="_blank">
            <i class="fab fa-linkedin"></i> <?php echo htmlspecialchars($user['student_linkedin']); ?>
        </a>
    </div>
</div>
<?php endif; ?>

                <?php if ($user['student_bio']): ?>
                <div class="detail-item" style="margin-top: 1.5rem;">
                    <div class="detail-label">Student Bio</div>
                    <div class="detail-value">
                        <?php echo nl2br(htmlspecialchars($user['student_bio'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$user['student_skills'] && !$user['student_course'] && !$user['student_bio'] && !$user['student_resume']): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <div>No educational details provided yet</div>
                        <div style="font-size: 0.875rem; margin-top: 0.5rem;">Student hasn't completed their profile</div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Edit User Form -->
            <div class="form-section">
                <div class="card-header">
                    <div class="card-icon user">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h3 class="card-title">Edit User Information</h3>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_user">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Account Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="rejected" <?php echo $user['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
