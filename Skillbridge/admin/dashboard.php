<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
requireAdmin();

$page_title = 'Admin Dashboard';
$admin_name = $_SESSION['full_name'];
$admin_id = $_SESSION['user_id'];

// Handle profile update
$profile_success = '';
$profile_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    if (!empty($full_name) && !empty($email)) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $email, $phone, $admin_id);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $admin_name = $full_name;
            $profile_success = 'Profile updated successfully!';
        } else {
            $profile_error = 'Failed to update profile.';
        }
    } else {
        $profile_error = 'Please fill in all required fields.';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if (password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $admin_id);
                    
                    if ($stmt->execute()) {
                        $profile_success = 'Password changed successfully!';
                    } else {
                        $profile_error = 'Failed to change password.';
                    }
                } else {
                    $profile_error = 'Current password is incorrect.';
                }
            } else {
                $profile_error = 'New password must be at least 6 characters long.';
            }
        } else {
            $profile_error = 'New passwords do not match.';
        }
    } else {
        $profile_error = 'Please fill in all password fields.';
    }
}

// Get admin details
$stmt = $conn->prepare("
    SELECT u.*, r.role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_details = $stmt->get_result()->fetch_assoc();

// Get dashboard statistics
$stats = [];

// Total Students
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.role_name = 'Student'
");
$stats['total_students'] = $result->fetch_assoc()['count'];

// Total Companies  
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.role_name = 'Company'
");
$stats['total_companies'] = $result->fetch_assoc()['count'];

// Pending Approvals
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.status = 'pending' AND r.role_name = 'Company'
");
$stats['pending_approvals'] = $result->fetch_assoc()['count'];

// Active Users
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.status = 'active' AND r.role_name != 'Admin'
");
$stats['active_users'] = $result->fetch_assoc()['count'];
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

        /* Sidebar - Exact match to your theme */
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

        /* Navigation */
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

        /* User Profile in Sidebar */
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

        /* Header */
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
            align-items: center;
            gap: 1rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s ease;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 2rem;
        }

        /* Stats Grid - Matching your exact card style */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-content h3 {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            color: #1e293b;
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
            color: #10b981;
        }

        .stat-trend i {
            font-size: 0.75rem;
        }

        /* Welcome Message */
        .welcome-card {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .welcome-card h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .welcome-card p {
            opacity: 0.9;
            font-size: 1rem;
            position: relative;
            z-index: 2;
        }

        /* Profile Section */
        .profile-header {
            background: linear-gradient(135deg, #3b82f6, #10b981);
            color: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .profile-header h2 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .profile-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* Profile Content */
        .profile-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-section {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            color: #1e293b;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .section-title i {
            color: #3b82f6;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-control:disabled {
            background-color: #f9fafb;
            color: #6b7280;
        }

        /* Account Details */
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: #374151;
        }

        .detail-value {
            color: #6b7280;
            font-weight: 400;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Tabs */
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .tab-button.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .header {
                padding: 1rem;
            }
            
            .dashboard-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-content {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
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
                <a href="#" class="nav-link active" onclick="showTab('dashboard')">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard Overview
                </a>
                <a href="#" class="nav-link" onclick="showTab('profile')">
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="company-approvals.php" class="nav-link">
                    <i class="fas fa-building"></i>
                    Company Approvals
                    <?php if ($stats['pending_approvals'] > 0): ?>
                        <span style="background: #ef4444; color: white; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 10px; margin-left: auto;">
                            <?php echo $stats['pending_approvals']; ?>
                        </span>
                    <?php endif; ?>
                </a>
                 <a href="internship-approvals.php" class="nav-link">
                    <i class="fas fa-clock"></i>
                    Internship Approvals
                </a>
                <a href="users.php" class="nav-link">
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
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($admin_name); ?>! Monitor and manage the platform.</p>
            </div>
            <div class="header-actions">
                <button onclick="showTab('profile')" class="btn-secondary">
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </button>
                <a href="company-approvals.php" class="btn-primary">
                    <i class="fas fa-tasks"></i>
                    Review Approvals
                </a>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            
            <!-- Dashboard Tab -->
            <div id="dashboard-tab" class="tab-content active">
                <!-- Welcome Card -->
                <div class="welcome-card">
                    <h2>🎉 Welcome to SkillBridge Admin Panel!</h2>
                    <p>Your admin dashboard is ready. Start managing users, approving companies, and monitoring platform activity.</p>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon blue">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="stat-content">
                                <h3>TOTAL STUDENTS</h3>
                                <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                                <div class="stat-trend">
                                    <i class="fas fa-arrow-up"></i>
                                    Registered users
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon orange">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h3>PENDING APPROVALS</h3>
                                <div class="stat-number"><?php echo $stats['pending_approvals']; ?></div>
                                <div class="stat-trend">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Awaiting review
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon green">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-content">
                                <h3>TOTAL COMPANIES</h3>
                                <div class="stat-number"><?php echo $stats['total_companies']; ?></div>
                                <div class="stat-trend">
                                    <i class="fas fa-check-circle"></i>
                                    Active companies
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon purple">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <h3>ACTIVE USERS</h3>
                                <div class="stat-number"><?php echo $stats['active_users']; ?></div>
                                <div class="stat-trend">
                                    <i class="fas fa-check-circle"></i>
                                    Total active users
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Management Tab -->
            <div id="profile-tab" class="tab-content">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                    </div>
                    <h2><?php echo htmlspecialchars($admin_details['full_name']); ?></h2>
                    <p>System Administrator</p>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($profile_success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $profile_success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($profile_error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $profile_error; ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Content -->
                <div class="profile-content">
                    <!-- Admin Information Section -->
                    <div class="profile-section">
                        <div class="section-title">
                            <i class="fas fa-user"></i>
                            ADMIN INFORMATION
                        </div>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin_details['full_name']); ?>" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($admin_details['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($admin_details['phone'] ?? ''); ?>" 
                                           placeholder="Enter phone number">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($admin_details['username']); ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($admin_details['role_name']); ?>" disabled>
                                </div>
                            </div>

                            <button type="submit" name="update_profile" class="btn-primary">
                                <i class="fas fa-save"></i>
                                Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Account Details Section -->
                    <div class="profile-section">
                        <div class="section-title">
                            <i class="fas fa-id-card"></i>
                            ACCOUNT DETAILS
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Username</span>
                            <span class="detail-value"><?php echo htmlspecialchars($admin_details['username']); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($admin_details['email']); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Role</span>
                            <span class="detail-value"><?php echo htmlspecialchars($admin_details['role_name']); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Status</span>
                            <span class="status-badge status-<?php echo $admin_details['status']; ?>">
                                <?php echo ucfirst($admin_details['status']); ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Member Since</span>
                            <span class="detail-value">
                                <?php echo date('M d, Y', strtotime($admin_details['created_at'] ?? 'now')); ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Last Login</span>
                            <span class="detail-value">
                                <?php 
                                if (isset($admin_details['last_login']) && $admin_details['last_login']) {
                                    echo date('M d, Y H:i', strtotime($admin_details['last_login']));
                                } else {
                                    echo 'Not recorded';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Change Password Section -->
                <div class="profile-section">
                    <div class="section-title">
                        <i class="fas fa-lock"></i>
                        CHANGE PASSWORD
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" 
                                   placeholder="Enter current password" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" 
                                       placeholder="Enter new password" required>
                                <small style="color: #6b7280; font-size: 0.75rem;">Minimum 6 characters required</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Confirm new password" required>
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

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all nav links
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to clicked nav link
            event.target.closest('.nav-link').classList.add('active');
        }

        // Form validation for password change
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.querySelector('form[method="POST"]');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = document.querySelector('input[name="new_password"]').value;
                    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('New password must be at least 6 characters long!');
                        return false;
                    }
                });
            }
        });
    </script>

</body>
</html>