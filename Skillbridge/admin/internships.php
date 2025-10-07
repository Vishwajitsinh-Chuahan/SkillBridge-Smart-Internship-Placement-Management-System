<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'] ?? 'Admin';

$success_message = '';
$error_message = '';

// ✅ Handle Status Change
if (isset($_POST['change_status'])) {
    $internship_id = (int)$_POST['internship_id'];
    $new_status = $_POST['new_status'];
    
    $allowed_statuses = ['pending', 'approved', 'active', 'rejected', 'closed'];
    if (in_array($new_status, $allowed_statuses)) {
        $stmt = $conn->prepare("UPDATE internships SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_status, $internship_id);
        
        if ($stmt->execute()) {
            $success_message = "Internship status updated to " . ucfirst($new_status) . " successfully!";
            
            // Log action
            error_log("Admin {$admin_name} changed internship ID {$internship_id} status to {$new_status}");
        } else {
            $error_message = "Failed to update status.";
        }
    }
}

// ✅ Handle Delete
if (isset($_POST['delete_internship'])) {
    $internship_id = (int)$_POST['internship_id'];
    
    // First delete related applications
    $conn->query("DELETE FROM applications WHERE internship_id = $internship_id");
    
    // Then delete internship
    $stmt = $conn->prepare("DELETE FROM internships WHERE id = ?");
    $stmt->bind_param("i", $internship_id);
    
    if ($stmt->execute()) {
        $success_message = "Internship deleted successfully!";
        error_log("Admin {$admin_name} deleted internship ID {$internship_id}");
    } else {
        $error_message = "Failed to delete internship.";
    }
}

// ✅ Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$company_filter = $_GET['company'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Build query with filters
$query = "
    SELECT 
        i.id,
        i.title,
        i.internship_type,
        i.location,
        i.location_type,
        i.positions_available,
        i.status,
        i.created_at,
        i.application_deadline,
        c.name as company_name,
        c.trust_level,
        u.full_name as contact_person,
        COUNT(DISTINCT a.id) as application_count
    FROM internships i
    JOIN companies c ON i.company_id = c.user_id
    JOIN users u ON i.company_id = u.id
    LEFT JOIN applications a ON i.id = a.internship_id
    WHERE 1=1
";

$params = [];
$types = '';

// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND i.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search_query)) {
    $query .= " AND (i.title LIKE ? OR c.name LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($company_filter)) {
    $query .= " AND c.name LIKE ?";
    $params[] = "%{$company_filter}%";
    $types .= 's';
}

if (!empty($type_filter)) {
    $query .= " AND i.internship_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

$query .= " GROUP BY i.id ORDER BY i.created_at DESC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$internships = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total'] = count($conn->query("SELECT id FROM internships")->fetch_all());
$stats['pending'] = count($conn->query("SELECT id FROM internships WHERE status = 'pending'")->fetch_all());
$stats['approved'] = count($conn->query("SELECT id FROM internships WHERE status = 'approved'")->fetch_all());
$stats['active'] = count($conn->query("SELECT id FROM internships WHERE status = 'active'")->fetch_all());
$stats['rejected'] = count($conn->query("SELECT id FROM internships WHERE status = 'rejected'")->fetch_all());
$stats['closed'] = count($conn->query("SELECT id FROM internships WHERE status = 'closed'")->fetch_all());

// Get user initials for avatar
$admin_initials = strtoupper(substr($admin_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Internships - SkillBridge Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        /* ✅ EXACT SIDEBAR FROM dashboard.php */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 4px 0 15px rgba(0,0,0,0.2);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }

        .logo {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo i {
            font-size: 2rem;
            color: #60a5fa;
        }

        .logo h2 {
            font-size: 1.5rem;
            color: white;
            font-weight: 700;
        }

        .nav-section {
            padding: 1rem 0;
        }

        .nav-section-title {
            padding: 1rem 1.5rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 0.95rem;
        }

        .nav-item:hover {
            background: rgba(96, 165, 250, 0.1);
            color: #60a5fa;
            border-left-color: #60a5fa;
        }

        .nav-item.active {
            background: rgba(96, 165, 250, 0.15);
            color: #60a5fa;
            border-left-color: #60a5fa;
            font-weight: 600;
        }

        .nav-item i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1.1rem;
        }

        .user-section {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            background: rgba(0,0,0,0.3);
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }

        .user-info .user-name {
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-info .user-role {
            color: #94a3b8;
            font-size: 0.8rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f8fafc;
        }

        .header {
            background: white;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #64748b;
            font-size: 1rem;
        }

        .content {
            padding: 2rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid;
        }

        .stat-card.total { border-color: #3b82f6; }
        .stat-card.pending { border-color: #f59e0b; }
        .stat-card.approved { border-color: #10b981; }
        .stat-card.active { border-color: #06b6d4; }
        .stat-card.rejected { border-color: #ef4444; }
        .stat-card.closed { border-color: #6b7280; }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card.total h3 { color: #3b82f6; }
        .stat-card.pending h3 { color: #f59e0b; }
        .stat-card.approved h3 { color: #10b981; }
        .stat-card.active h3 { color: #06b6d4; }
        .stat-card.rejected h3 { color: #ef4444; }
        .stat-card.closed h3 { color: #6b7280; }

        .stat-card p {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Filters */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr) auto;
            gap: 1rem;
            align-items: end;
        }

        .filter-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .filter-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Internships Table */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 1.25rem;
            color: #1e293b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 1rem;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.9rem;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .internship-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .company-name {
            color: #667eea;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge.pending { background: #fef3c7; color: #92400e; }
        .badge.approved { background: #d1fae5; color: #065f46; }
        .badge.active { background: #cffafe; color: #164e63; }
        .badge.rejected { background: #fee2e2; color: #991b1b; }
        .badge.closed { background: #e5e7eb; color: #374151; }

        .trust-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .trust-badge.new { background: #dbeafe; color: #1e40af; }
        .trust-badge.verified { background: #d1fae5; color: #065f46; }
        .trust-badge.trusted { background: #fef3c7; color: #92400e; }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        .btn-info {
            background: #0ea5e9;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-sm:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 16px;
            max-width: 400px;
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            font-size: 1.3rem;
            color: #1e293b;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #64748b;
        }
        /* ✅ FIXED SIDEBAR - Wider and No Gap */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px; /* ✅ Increased from 240px to 280px */
    height: 100vh;
    background-color: #2d3748; /* ✅ Darker solid color to match */
    color: #cbd5e1;
    z-index: 1000;
    display: flex;
    flex-direction: column;
}

/* Sidebar Header */
.sidebar-header {
    padding: 2rem 1.5rem; /* ✅ Increased padding */
    border-bottom: 1px solid rgba(203, 213, 225, 0.1);
    flex-shrink: 0;
    background-color: #2d3748; /* ✅ Solid background */
}

.sidebar-header .logo {
    display: flex;
    align-items: center;
    gap: 1rem; /* ✅ Increased gap */
    color: #ffffff;
    text-decoration: none;
    font-size: 1.5rem; /* ✅ Larger font */
    font-weight: 700;
}

.sidebar-header .logo i {
    color: #60a5fa;
    font-size: 2rem; /* ✅ Larger icon */
}

/* ✅ Scrollable Container for Navigation */
.sidebar-scroll-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding-bottom: 1rem;
    background-color: #2d3748; /* ✅ No gradient, solid color */
}

.sidebar-scroll-container::-webkit-scrollbar {
    width: 6px;
}

.sidebar-scroll-container::-webkit-scrollbar-track {
    background: rgba(203, 213, 225, 0.05);
}

.sidebar-scroll-container::-webkit-scrollbar-thumb {
    background: rgba(203, 213, 225, 0.2);
    border-radius: 3px;
}

.sidebar-scroll-container::-webkit-scrollbar-thumb:hover {
    background: rgba(203, 213, 225, 0.3);
}

/* Navigation */
.nav-section {
    padding: 1rem 0; /* ✅ More spacing */
}

.nav-section-title {
    padding: 1rem 1.5rem 0.75rem; /* ✅ Increased padding */
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem; /* ✅ Increased padding */
    color: #cbd5e1;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem; /* ✅ Slightly larger text */
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.nav-link:hover {
    background-color: rgba(96, 165, 250, 0.1);
    color: #ffffff;
    border-left-color: #60a5fa;
}

.nav-link.active {
    background-color: rgba(96, 165, 250, 0.15);
    color: #ffffff;
    border-left-color: #60a5fa;
}

.nav-link i {
    width: 22px; /* ✅ Slightly larger */
    margin-right: 1rem; /* ✅ More spacing */
    font-size: 1rem;
    color: #94a3b8;
}

.nav-link:hover i,
.nav-link.active i {
    color: #60a5fa;
}

/* ✅ User Profile Fixed at Bottom - NO GAP */
.user-profile {
    flex-shrink: 0;
    padding: 1.5rem; /* ✅ Increased padding */
    background-color: rgba(0, 0, 0, 0.2); /* ✅ Darker background */
    border-top: 1px solid rgba(203, 213, 225, 0.1);
    margin-top: auto;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem; /* ✅ More spacing */
}

.user-avatar {
    width: 48px; /* ✅ Larger avatar */
    height: 48px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-details h4 {
    color: #ffffff;
    font-size: 0.95rem; /* ✅ Slightly larger */
    font-weight: 600;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-details p {
    color: #94a3b8;
    font-size: 0.8rem;
    margin: 0;
}

/* ✅ Adjust Main Content for Wider Sidebar */
.main-content {
    margin-left: 280px; /* ✅ Changed from 260px to 280px */
    min-height: 100vh;
    background: #f8fafc;
}


    </style>
</head>
<body>
  <!-- ✅ FIXED SIDEBAR FROM dashboard.php -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-graduation-cap"></i>
            SkillBridge
        </a>
    </div>

    <!-- ✅ Scrollable Navigation Wrapper -->
    <div class="sidebar-scroll-container">
        <nav class="sidebar-nav">
            <!-- Dashboard Section -->
            <div class="nav-section">
                <div class="nav-section-title">DASHBOARD</div>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard Overview
                </a>
               
            </div>

            <!-- Management Section -->
            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="company-approvals.php" class="nav-link">
                    <i class="fas fa-building"></i>
                    Company Approvals
                </a>
                <a href="internship-approvals.php" class="nav-link">
                    <i class="fas fa-clock"></i>
                    Internship Approvals
                </a>
                <a href="internships.php" class="nav-link active">
                    <i class="fas fa-briefcase"></i>
                    All Internships
                </a>
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    User Management
                </a>
            </div>

            <!-- Settings Section -->
            <div class="nav-section">
                <div class="nav-section-title">SETTINGS</div>
                <a href="analytics.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    Analytics
                </a>
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- ✅ User Profile Fixed at Bottom -->
    <div class="user-profile">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo $admin_initials; ?>
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
        <div class="header">
            <h1><i class="fas fa-briefcase"></i> All Internships</h1>
            <p>View, manage, and monitor all internship postings on the platform</p>
        </div>

        <div class="content">
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total</p>
                </div>
                <div class="stat-card pending">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card approved">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
                <div class="stat-card active">
                    <h3><?php echo $stats['active']; ?></h3>
                    <p>Active</p>
                </div>
                <div class="stat-card rejected">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
                <div class="stat-card closed">
                    <h3><?php echo $stats['closed']; ?></h3>
                    <p>Closed</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status" class="filter-input">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Type</label>
                            <select name="type" class="filter-input">
                                <option value="" <?php echo empty($type_filter) ? 'selected' : ''; ?>>All Types</option>
                                <option value="Internship" <?php echo $type_filter === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                <option value="Job" <?php echo $type_filter === 'Job' ? 'selected' : ''; ?>>Job</option>
                                <option value="Both" <?php echo $type_filter === 'Both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Search Title</label>
                            <input type="text" name="search" class="filter-input" placeholder="Search internship..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>

                        <div class="filter-group">
                            <label>Company</label>
                            <input type="text" name="company" class="filter-input" placeholder="Company name..." value="<?php echo htmlspecialchars($company_filter); ?>">
                        </div>

                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Internships Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>All Internships (<?php echo count($internships); ?>)</h3>
                </div>

                <?php if (count($internships) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Internship Details</th>
                                <th>Company</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Deadline</th>
                                <th>Applications</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internships as $internship): ?>
                                <tr>
                                    <td>
                                        <div class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></div>
                                        <small style="color: #94a3b8;">Posted: <?php echo date('M d, Y', strtotime($internship['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="company-name"><?php echo htmlspecialchars($internship['company_name']); ?></div>
                                        <span class="trust-badge <?php echo $internship['trust_level']; ?>">
                                            <?php echo ucfirst($internship['trust_level']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $internship['internship_type']; ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($internship['location']); ?></div>
                                        <small style="color: #94a3b8;"><?php echo $internship['location_type']; ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?></td>
                                    <td><strong><?php echo $internship['application_count']; ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo $internship['status']; ?>">
                                            <?php echo ucfirst($internship['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view-internship-details.php?id=<?php echo $internship['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button onclick="openStatusModal(<?php echo $internship['id']; ?>, '<?php echo $internship['status']; ?>')" class="btn btn-sm btn-warning" title="Change Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="confirmDelete(<?php echo $internship['id']; ?>)" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-briefcase"></i>
                        <h3>No Internships Found</h3>
                        <p>Try adjusting your filters to see more results.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Status</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="internship_id" id="status_internship_id">
                <div class="filter-group">
                    <label>New Status</label>
                    <select name="new_status" id="new_status" class="filter-input" required>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="active">Active</option>
                        <option value="rejected">Rejected</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="change_status" class="btn btn-primary">
                        <i class="fas fa-check"></i> Update Status
                    </button>
                    <button type="button" class="btn btn-danger" onclick="closeStatusModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Form (Hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="internship_id" id="delete_internship_id">
        <input type="hidden" name="delete_internship" value="1">
    </form>

    <script>
        function openStatusModal(internshipId, currentStatus) {
            document.getElementById('status_internship_id').value = internshipId;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        function confirmDelete(internshipId) {
            if (confirm('Are you sure you want to delete this internship? This will also delete all related applications.')) {
                document.getElementById('delete_internship_id').value = internshipId;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
