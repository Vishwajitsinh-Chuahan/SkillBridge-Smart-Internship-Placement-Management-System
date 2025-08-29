<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include configuration
require_once '../config/config.php';

// Page configuration
$page_title = 'Student Dashboard';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];
$full_name = $_SESSION['full_name'];

// Initialize variables with default values
$total_applications = 0;
$pending_applications = 0;
$accepted_applications = 0;
$profile_completion = 75; // Percentage
$recent_applications = [];
$recommended_internships = [];

// Fetch student statistics with error handling
try {
    // Check if connection exists
    if (!$conn) {
        throw new Exception("Database connection not available");
    }

    // Check if applications table exists
    $sql = "SHOW TABLES LIKE 'applications'";
    $table_check = $conn->query($sql);
    
    if ($table_check && $table_check->num_rows > 0) {
        // Total applications
        $sql = "SELECT COUNT(*) as total_applications FROM applications WHERE student_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $total_applications = (int)$row['total_applications'];
                }
            }
            $stmt->close();
        }

        // Pending applications
        $sql = "SELECT COUNT(*) as pending_applications FROM applications WHERE student_id = ? AND status = 'pending'";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $pending_applications = (int)$row['pending_applications'];
                }
            }
            $stmt->close();
        }

        // Accepted applications
        $sql = "SELECT COUNT(*) as accepted_applications FROM applications WHERE student_id = ? AND status = 'accepted'";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $accepted_applications = (int)$row['accepted_applications'];
                }
            }
            $stmt->close();
        }

        // Recent applications
        $sql = "SELECT a.*, i.title, i.location, i.stipend, c.name as company_name FROM applications a 
                JOIN internships i ON a.internship_id = i.id 
                JOIN companies c ON i.company_id = c.id 
                WHERE a.student_id = ? ORDER BY a.applied_at DESC LIMIT 5";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $recent_applications = $result->fetch_all(MYSQLI_ASSOC);
            }
            $stmt->close();
        }
    }

    // Recommended internships (open internships user hasn't applied to)
    $sql = "SELECT i.id, i.title, i.location, i.stipend, i.remote, i.duration_months, c.name as company_name 
            FROM internships i 
            JOIN companies c ON i.company_id = c.id 
            WHERE i.status = 'open' AND i.application_deadline > NOW() 
            AND i.id NOT IN (SELECT COALESCE(internship_id, 0) FROM applications WHERE student_id = ?) 
            ORDER BY i.created_at DESC LIMIT 4";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $recommended_internships = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }

} catch (Exception $e) {
    error_log("Student dashboard error: " . $e->getMessage());
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
        .sidebar-logo i { color: var(--primary-color); }
        .nav-item.active { background: var(--gradient); }
        .card-icon.icon-primary { background: var(--gradient); }
        .btn-create { background: var(--gradient); }
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
                    <a href="<?php echo BASE_URL; ?>/dashboard/student.php" class="nav-item active">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard Overview
                    </a>
                </div>

                <!-- Internships Section -->
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

                <!-- Applications Section -->
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

                <!-- Profile Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Profile</div>
                    <a href="<?php echo BASE_URL; ?>/dashboard/profile.php" class="nav-item">
                        <i class="fas fa-user-edit"></i>
                        Profile Settings
                    </a>
                    <!-- <a href="<?php echo BASE_URL; ?>/profile/resume.php" class="nav-item">
                        <i class="fas fa-file-upload"></i>
                        Upload Resume
                    </a>
                    <a href="<?php echo BASE_URL; ?>/profile/skills.php" class="nav-item">
                        <i class="fas fa-cogs"></i>
                        Skills & Certifications
                    </a> -->
                </div>

                <!-- Settings Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <a href="<?php echo BASE_URL; ?>/dashboard/notifications.php" class="nav-item">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </a>
                    <!-- <a href="<?php echo BASE_URL; ?>/dashboard/preferences.php" class="nav-item">
                        <i class="fas fa-sliders-h"></i>
                        Preferences
                    </a> -->
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
                    <p>Student</p>
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
                <h1 class="page-title">Student Dashboard</h1>
                <p style="color: #64748b; margin: 0.5rem 0 0 0;">Welcome back, <?php echo htmlspecialchars($full_name); ?>! Explore internships and track your applications.</p>
            </div>
            <div class="dashboard-actions">
                <a href="<?php echo BASE_URL; ?>/internships/browse.php" class="btn-create">
                    <i class="fas fa-search"></i> Find Internships
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-icon icon-primary">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="card-title">Total Applications</div>
                <div class="card-value"><?php echo number_format($total_applications); ?></div>
                <div class="card-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    Applications submitted
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-icon icon-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-title">Pending Review</div>
                <div class="card-value"><?php echo number_format($pending_applications); ?></div>
                <div class="card-trend trend-neutral">
                    <i class="fas fa-minus"></i>
                    Awaiting response
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-icon icon-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-title">Offers Received</div>
                <div class="card-value"><?php echo number_format($accepted_applications); ?></div>
                <div class="card-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    Successful applications
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-icon icon-info">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="card-title">Profile Completion</div>
                <div class="card-value"><?php echo $profile_completion; ?>%</div>
                <div class="card-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    Complete your profile
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem; width: 100%; box-sizing: border-box;">
            <!-- Recent Applications -->
            <div class="activity-card">
                <div class="activity-header">
                    <h3 class="activity-title">Recent Applications</h3>
                    <a href="<?php echo BASE_URL; ?>/applications/my-applications.php" class="view-all-link">View All</a>
                </div>
                <div class="activity-list">
                    <?php if (!empty($recent_applications)): ?>
                        <?php foreach ($recent_applications as $application): ?>
                            <div class="activity-item">
                                <div class="activity-icon icon-primary">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($application['title']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($application['company_name']); ?> • 
                                        <?php echo htmlspecialchars($application['location']); ?> • 
                                        Applied <?php echo date('M d, Y', strtotime($application['applied_at'])); ?>
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
                        <div style="text-align: center; padding: 3rem; color: #64748b;">
                            <i class="fas fa-file-plus" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p>No applications yet</p>
                            <a href="<?php echo BASE_URL; ?>/internships/browse.php" class="btn-create" style="margin-top: 1rem; display: inline-block;">Find Internships</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Progress -->
            <div class="activity-card">
                <div class="activity-header">
                    <h3 class="activity-title">Profile Completion</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <div style="background: #f1f5f9; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="font-weight: 600; color: #334155;">Progress</span>
                            <span style="font-weight: 600; color: #2563eb;"><?php echo $profile_completion; ?>%</span>
                        </div>
                        <div style="background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: linear-gradient(135deg, #2563eb, #059669); height: 100%; width: <?php echo $profile_completion; ?>%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    
                    <div style="space-y: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0;">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i>
                            <span style="color: #374151;">Basic Information</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0;">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i>
                            <span style="color: #374151;">Education Details</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0;">
                            <i class="fas fa-circle" style="color: #e5e7eb;"></i>
                            <span style="color: #64748b;">Skills & Certifications</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0;">
                            <i class="fas fa-circle" style="color: #e5e7eb;"></i>
                            <span style="color: #64748b;">Resume Upload</span>
                        </div>
                    </div>
                    
                    <a href="<?php echo BASE_URL; ?>/profile/edit.php" style="display: block; background: linear-gradient(135deg, #2563eb, #059669); color: white; text-align: center; padding: 0.75rem 1rem; border-radius: 8px; text-decoration: none; margin-top: 1rem; font-weight: 600;">
                        Complete Profile
                    </a>
                </div>
            </div>
        </div>

        <!-- Recommended Internships -->
        <?php if (!empty($recommended_internships)): ?>
        <div class="activity-card" style="margin-top: 2rem;">
            <div class="activity-header">
                <h3 class="activity-title">Recommended Internships</h3>
                <a href="<?php echo BASE_URL; ?>/internships/browse.php" class="view-all-link">Browse All</a>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($recommended_internships as $internship): ?>
                        <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; transition: all 0.2s ease; cursor: pointer;" 
                             onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#2563eb'"
                             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'; this.style.borderColor='#e2e8f0'"
                             onclick="window.location.href='<?php echo BASE_URL; ?>/internships/view.php?id=<?php echo $internship['id']; ?>'">
                            
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #2563eb, #059669); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-briefcase" style="color: white; font-size: 1.2rem;"></i>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 600; color: #2563eb; font-size: 1.1rem;">
                                        <?php echo $internship['stipend'] > 0 ? '₹' . number_format($internship['stipend']) : 'Unpaid'; ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #64748b;">
                                        <?php echo $internship['duration_months']; ?> months
                                    </div>
                                </div>
                            </div>
                            
                            <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; color: #1e293b; font-weight: 600;">
                                <?php echo htmlspecialchars($internship['title']); ?>
                            </h4>
                            
                            <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                    <i class="fas fa-building"></i>
                                    <?php echo htmlspecialchars($internship['company_name']); ?>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo $internship['remote'] ? 'Remote' : htmlspecialchars($internship['location']); ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <button style="flex: 1; background: linear-gradient(135deg, #2563eb, #059669); color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;"
                                        onmouseover="this.style.transform='translateY(-1px)'"
                                        onmouseout="this.style.transform='translateY(0)'">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </button>
                                <button style="background: white; color: #64748b; border: 2px solid #e2e8f0; padding: 0.75rem; border-radius: 8px; cursor: pointer; transition: all 0.2s ease;"
                                        onmouseover="this.style.borderColor='#2563eb'; this.style.color='#2563eb'"
                                        onmouseout="this.style.borderColor='#e2e8f0'; this.style.color='#64748b'">
                                    <i class="fas fa-bookmark"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions for Students -->
        <div style="margin-top: 2rem; width: 100%;">
            <h3 style="margin-bottom: 1rem; color: #1e293b;">Quick Actions</h3>
            <div class="quick-actions">
                <a href="<?php echo BASE_URL; ?>/internships/browse.php" class="action-card">
                    <i class="fas fa-search"></i>
                    <h4>Find Internships</h4>
                    <p>Explore thousands of internship opportunities</p>
                </a>
                <a href="<?php echo BASE_URL; ?>/applications/my-applications.php" class="action-card">
                    <i class="fas fa-file-alt"></i>
                    <h4>My Applications</h4>
                    <p>Track your internship applications</p>
                </a>
                <a href="<?php echo BASE_URL; ?>profile.php" class="action-card">
                    <i class="fas fa-user-edit"></i>
                    <h4>Complete Profile</h4>
                    <p>Boost your chances with a complete profile</p>   
                </a>
                <a href="<?php echo BASE_URL; ?>/profile/resume.php" class="action-card">
                    <i class="fas fa-file-upload"></i>
                    <h4>Upload Resume</h4>
                    <p>Share your resume with potential employers</p>
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
