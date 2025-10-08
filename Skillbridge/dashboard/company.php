<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include configuration
require_once '../config/config.php';

// Page configuration
$page_title = 'Company Dashboard';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Company') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];
$full_name = $_SESSION['full_name'];

// Initialize company variables
$company_name = '';
$industry = '';
$company_size = '';
$address = '';
$website = '';
$description = '';
$status = '';

// Initialize statistics
$total_internships = 0;
$pending_internships = 0;
$active_internships = 0;
$total_applications_received = 0;
$pending_applications = 0;
$recent_internships = [];
$recent_applications = [];

// Fetch company information
try {
    // Get company profile data
    $stmt = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $company_result = $stmt->get_result();
    
    if ($company_result && $company_result->num_rows > 0) {
        $company_data = $company_result->fetch_assoc();
        $company_name = $company_data['name'] ?? '';
        $industry = $company_data['industry'] ?? '';
        $company_size = $company_data['company_size'] ?? '';
        $address = $company_data['address'] ?? '';
        $website = $company_data['website'] ?? '';
        $description = $company_data['description'] ?? '';
        $status = $company_data['status'] ?? '';
    }

    // Check if internships table exists and get company statistics
    $table_check = $conn->query("SHOW TABLES LIKE 'internships'");
    
    if ($table_check && $table_check->num_rows > 0) {
        // Total internships posted by company
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM internships WHERE company_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $total_internships = (int)$row['total'];
        }

        // Pending internships
        $stmt = $conn->prepare("SELECT COUNT(*) as pending FROM internships WHERE company_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $pending_internships = (int)$row['pending'];
        }

        // Active internships
        $stmt = $conn->prepare("SELECT COUNT(*) as active FROM internships WHERE company_id = ? AND status = 'active'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $active_internships = (int)$row['active'];
        }

        // Recent internships
        $stmt = $conn->prepare("SELECT * FROM internships WHERE company_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $recent_internships = $result->fetch_all(MYSQLI_ASSOC);
    }

    // Check if applications table exists and get application statistics
    $table_check = $conn->query("SHOW TABLES LIKE 'applications'");
    
    if ($table_check && $table_check->num_rows > 0) {
        // Total applications received for company internships
        $stmt = $conn->prepare("SELECT COUNT(*) as total_applications 
                               FROM applications a 
                               JOIN internships i ON a.internship_id = i.id 
                               WHERE i.company_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $total_applications_received = (int)$row['total_applications'];
        }

        // Pending applications
        $stmt = $conn->prepare("SELECT COUNT(*) as pending_apps 
                               FROM applications a 
                               JOIN internships i ON a.internship_id = i.id 
                               WHERE i.company_id = ? AND a.status = 'pending'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $pending_applications = (int)$row['pending_apps'];
        }

        // Recent applications with student info
        $stmt = $conn->prepare("SELECT a.*, i.title as internship_title, u.full_name as student_name 
                               FROM applications a 
                               JOIN internships i ON a.internship_id = i.id 
                               JOIN users u ON a.student_id = u.id 
                               WHERE i.company_id = ? 
                               ORDER BY a.applied_at DESC LIMIT 5");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $recent_applications = $result->fetch_all(MYSQLI_ASSOC);
    }

} catch (Exception $e) {
    error_log("Company dashboard error: " . $e->getMessage());
}

// Get company initials for avatar
$user_initials = strtoupper(substr($company_name ?: $full_name, 0, 1));
if (!empty($company_name) && strpos($company_name, ' ') !== false) {
    $name_parts = explode(' ', $company_name);
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
        .sidebar-logo i { color: var(--primary-color); }
        .nav-item.active { background: var(--gradient); }
        .card-icon.icon-primary { background: var(--gradient); }
        .btn-create { background: var(--gradient); }
        
        .company-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-active {
            background: #dcfce7;
            color: #166534;
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
                <i class="fas fa-building"></i>
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
                    <a href="<?php echo BASE_URL; ?>/dashboard/company.php" class="nav-item active">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard Overview
                    </a>
                </div>

                <!-- Internships Section -->
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
                    <a href="<?php echo BASE_URL; ?>/internships/analytics.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        Analytics
                    </a>
                </div>

                <!-- Applications Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Applications</div>
                    <a href="<?php echo BASE_URL; ?>/applications/view-applications.php" class="nav-item">
                        <i class="fas fa-inbox"></i>
                        Received Applications
                    </a>
                    <a href="<?php echo BASE_URL; ?>/applications/shortlisted.php" class="nav-item">
                        <i class="fas fa-star"></i>
                        Shortlisted Candidates
                    </a>
                    <a href="<?php echo BASE_URL; ?>/applications/interviews.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        Interview Schedule
                    </a>
                </div>

                <!-- Talent Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Talent Pool</div>
                    <a href="<?php echo BASE_URL; ?>/students/browse.php" class="nav-item">
                        <i class="fas fa-search"></i>
                        Browse Students
                    </a>
                    <a href="<?php echo BASE_URL; ?>/students/saved.php" class="nav-item">
                        <i class="fas fa-bookmark"></i>
                        Saved Profiles
                    </a>
                </div>

                <!-- Settings Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <a href="<?php echo BASE_URL; ?>/dashboard/profile.php" class="nav-item">
                        <i class="fas fa-user-edit"></i>
                        Company Profile
                    </a>
                    <a href="<?php echo BASE_URL; ?>/dashboard/notifications.php" class="nav-item">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </a>
                    <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- Company Profile -->
        <div class="user-profile">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo htmlspecialchars($user_initials); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($company_name ?: $full_name); ?></h4>
                    <p><?php echo ucfirst($industry ?: 'Company'); ?></p>
                    <?php if ($status === 'pending'): ?>
                        <span class="company-status status-pending">Pending Approval</span>
                    <?php elseif ($status === 'active'): ?>
                        <span class="company-status status-active">Active</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="dashboard-main">
        <!-- Mobile Toggle - Hidden on desktop -->
        <button class="mobile-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1 class="page-title">Company Dashboard</h1>
                <p style="color: #64748b; margin: 0.5rem 0 0 0;">Welcome back! Manage your internships and discover top talent.</p>
            </div>
            <div class="dashboard-actions">
                <a href="<?php echo BASE_URL; ?>/internships/create.php" class="btn-create">
                    <i class="fas fa-plus-circle"></i> Post Internship
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-icon icon-primary">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="card-title">Total Internships</div>
                <div class="card-value"><?php echo number_format($total_internships); ?></div>
                <div class="card-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    Internships posted
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-icon icon-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-title">Pending Approvals</div>
                <div class="card-value"><?php echo number_format($pending_internships); ?></div>
                <div class="card-trend trend-neutral">
                    <i class="fas fa-minus"></i>
                    Awaiting admin approval
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-icon icon-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-title">Active Internships</div>
                <div class="card-value"><?php echo number_format($active_internships); ?></div>
                <div class="card-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    Currently live
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-icon icon-info">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="card-title">Applications Received</div>
                <div class="card-value"><?php echo number_format($total_applications_received); ?></div>
                <div class="card-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    Total applications
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem; width: 100%; box-sizing: border-box;">
            <!-- Recent Internships -->
            <div class="activity-card">
                <div class="activity-header">
                    <h3 class="activity-title">Recent Internships</h3>
                    <a href="<?php echo BASE_URL; ?>/internships/manage.php" class="view-all-link">View All</a>
                </div>
                <div class="activity-list">
                    <?php if (!empty($recent_internships)): ?>
                        <?php foreach ($recent_internships as $internship): ?>
                            <div class="activity-item">
                                <div class="activity-icon icon-primary">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($internship['title']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($internship['location'] ?? 'Remote'); ?> • 
                                        Posted <?php echo date('M d, Y', strtotime($internship['created_at'])); ?>
                                    </p>
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem;">
                                    <?php
                                    $status_class = '';
                                    switch($internship['status']) {
                                        case 'active': $status_class = 'badge-confirmed'; break;
                                        case 'pending': $status_class = 'badge-draft'; break;
                                        case 'closed': $status_class = 'badge-cancelled'; break;
                                        default: $status_class = 'badge-published';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($internship['status']); ?>
                                    </span>
                                    <span class="activity-time"><?php echo date('M d', strtotime($internship['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: #64748b;">
                            <i class="fas fa-briefcase" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p>No internships posted yet</p>
                            <a href="<?php echo BASE_URL; ?>/internships/post.php" class="btn-create" style="margin-top: 1rem; display: inline-block;">Post Your First Internship</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Applications -->
            <div class="activity-card">
                <div class="activity-header">
                    <h3 class="activity-title">Recent Applications</h3>
                    <a href="<?php echo BASE_URL; ?>/applications/view-application.php" class="view-all-link">View All</a>
                </div>
                <div class="activity-list">
                    <?php if (!empty($recent_applications)): ?>
                        <?php foreach ($recent_applications as $application): ?>
                            <div class="activity-item">
                                <div class="activity-icon icon-success">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($application['student_name']); ?></h4>
                                    <p>
                                        Applied for <?php echo htmlspecialchars($application['internship_title']); ?> • 
                                        <?php echo date('M d', strtotime($application['applied_at'])); ?>
                                    </p>
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem;">
                                    <?php
                                    $status_class = '';
                                    switch($application['status']) {
                                        case 'accepted': $status_class = 'badge-confirmed'; break;
                                        case 'pending': $status_class = 'badge-draft'; break;
                                        case 'rejected': $status_class = 'badge-cancelled'; break;
                                        default: $status_class = 'badge-published';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                    <span class="activity-time"><?php echo date('M d', strtotime($application['applied_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: #64748b;">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p>No applications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions for Companies -->
        <div style="margin-top: 2rem; width: 100%;">
            <h3 style="margin-bottom: 1rem; color: #1e293b;">Quick Actions</h3>
            <div class="quick-actions">
                <a href="<?php echo BASE_URL; ?>/internships/create.php" class="action-card">
                    <i class="fas fa-plus-circle"></i>
                    <h4>Post Internship</h4>
                    <p>Create new internship opportunities</p>
                </a>
                <a href="<?php echo BASE_URL; ?>/applications/view-applications.php" class="action-card">
                    <i class="fas fa-inbox"></i>
                    <h4>Review Applications</h4>
                    <p>View and manage student applications</p>
                </a>
                <a href="<?php echo BASE_URL; ?>/students/browse.php" class="action-card">
                    <i class="fas fa-search"></i>
                    <h4>Browse Students</h4>
                    <p>Discover talented candidates</p>
                </a>
                <a href="<?php echo BASE_URL; ?>/dashboard/profile.php" class="action-card">
                    <i class="fas fa-building"></i>
                    <h4>Company Profile</h4>
                    <p>Update your company information</p>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Function to set active navigation item
function setActiveNavItem() {
    const currentPath = window.location.pathname;
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.classList.remove('active');
        
        const href = item.getAttribute('href');
        if (href && currentPath.includes(href.split('/').pop())) {
            item.classList.add('active');
        }
    });
}

// Handle click events for navigation items
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            e.preventDefault();
            
            const href = this.getAttribute('href');
            if (href && href !== '#') {
                setTimeout(() => {
                    window.location.href = href;
                }, 100);
            }
        });
    });
    
    setActiveNavItem();
});

// Sidebar toggle function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('mobile-open');
}

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
</script>

</body>
</html>