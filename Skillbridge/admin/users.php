<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
requireAdmin();

$page_title = 'User Management';
$admin_name = $_SESSION['full_name'];

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $user_ids = $_POST['user_ids'] ?? [];
    
    if (!empty($user_ids) && in_array($action, ['activate', 'deactivate', 'delete'])) {
        $user_ids = array_map('intval', $user_ids);
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        
        if ($action === 'activate') {
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
            if ($stmt->execute()) {
                $success_message = count($user_ids) . " users activated successfully.";
            }
        } elseif ($action === 'deactivate') {
            $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
            if ($stmt->execute()) {
                $success_message = count($user_ids) . " users deactivated successfully.";
            }
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
            if ($stmt->execute()) {
                $success_message = count($user_ids) . " users deleted successfully.";
            }
        }
        
        error_log("Admin {$admin_name} performed bulk {$action} on users: " . implode(',', $user_ids));
    }
}

// Get filter parameters
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_types = '';

if ($role_filter !== 'all') {
    $where_conditions[] = "r.role_name = ?";
    $params[] = ucfirst($role_filter);
    $param_types .= 's';
}

if ($status_filter !== 'all') {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

// ✅ CORRECTED SEARCH: Only use existing database columns
if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR c.name LIKE ? OR sp.course LIKE ? OR sp.university LIKE ? OR sp.bio LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sssssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ✅ CORRECTED QUERY: Only use existing columns from your database
$users_query = "
    SELECT u.id, u.username, u.full_name, u.email, u.phone, u.status, u.created_at,
           r.role_name, r.id as role_id,
           CASE 
               WHEN r.role_name = 'Company' THEN c.name
               ELSE NULL
           END as company_name,
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
               WHEN r.role_name = 'Student' THEN sp.bio
               ELSE NULL
           END as student_bio
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN companies c ON u.id = c.user_id AND r.role_name = 'Company'
    LEFT JOIN student_profiles sp ON u.id = sp.user_id AND r.role_name = 'Student'
    $where_clause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($users_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN companies c ON u.id = c.user_id AND r.role_name = 'Company'
    LEFT JOIN student_profiles sp ON u.id = sp.user_id AND r.role_name = 'Student'
    $where_clause
";

$count_params = array_slice($params, 0, -2); // Remove LIMIT and OFFSET params
$count_param_types = substr($param_types, 0, -2);

$count_stmt = $conn->prepare($count_query);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_param_types, ...$count_params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Get statistics for tabs
$stats_query = "
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN r.role_name = 'Student' THEN 1 ELSE 0 END) as student_count,
        SUM(CASE WHEN r.role_name = 'Company' THEN 1 ELSE 0 END) as company_count,
        SUM(CASE WHEN r.role_name = 'Admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN u.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN u.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN u.status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
    FROM users u 
    JOIN roles r ON u.role_id = r.id
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
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

        /* Sidebar - Same as other admin pages */
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

        /* Filter Tabs */
        .filter-section {
            background: #ffffff;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            background: #f8fafc;
            color: #64748b;
            text-decoration: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
        }

        .filter-tab:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .filter-tab.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .filter-tab-badge {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .filter-tab.active .filter-tab-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* Search and Actions */
        .search-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-box {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .search-input {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            width: 300px;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: #ffffff;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: none;
        }

        .bulk-actions.show {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bulk-info {
            color: #64748b;
            font-size: 0.875rem;
        }

        .bulk-buttons {
            display: flex;
            gap: 0.5rem;
        }

        /* Success Message */
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

        /* Users Table */
        .users-section {
            padding: 2rem;
        }

        .users-table-container {
            background: #ffffff;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            font-size: 0.875rem;
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .users-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #64748b;
            font-size: 0.875rem;
        }

        .users-table tr:hover {
            background-color: #f8fafc;
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
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

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-inactive {
            background-color: #f1f5f9;
            color: #64748b;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
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

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Resume Link Styling */
        .resume-link {
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .resume-link:hover {
            color: #1d4ed8;
        }

        .student-info {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            text-decoration: none;
            color: #64748b;
            border-radius: 0.375rem;
        }

        .pagination a:hover {
            background: #f8fafc;
        }

        .pagination .current {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filter-tabs {
                flex-direction: column;
            }
            
            .search-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                width: 100%;
            }
            
            .users-table-container {
                overflow-x: auto;
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
                <h1>User Management</h1>
                <p>Manage all user accounts, roles, and permissions</p>
            </div>
        </header>

        <!-- Filter Tabs -->
        <div class="filter-section">
            <div class="filter-tabs">
                <a href="?role=all&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $role_filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    All Users
                    <span class="filter-tab-badge"><?php echo $stats['total_count']; ?></span>
                </a>
                <a href="?role=student&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $role_filter === 'student' ? 'active' : ''; ?>">
                    <i class="fas fa-graduation-cap"></i>
                    Students Only
                    <span class="filter-tab-badge"><?php echo $stats['student_count']; ?></span>
                </a>
                <a href="?role=company&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $role_filter === 'company' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    Companies Only
                    <span class="filter-tab-badge"><?php echo $stats['company_count']; ?></span>
                </a>
                <a href="?role=<?php echo $role_filter; ?>&status=active&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    Approved Users
                    <span class="filter-tab-badge"><?php echo $stats['active_count']; ?></span>
                </a>
                <a href="?role=<?php echo $role_filter; ?>&status=rejected&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i>
                    Rejected Users
                    <span class="filter-tab-badge"><?php echo $stats['rejected_count']; ?></span>
                </a>
                <a href="?role=<?php echo $role_filter; ?>&status=pending&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    Pending Users
                    <span class="filter-tab-badge"><?php echo $stats['pending_count']; ?></span>
                </a>
            </div>

            <div class="search-actions">
                <form class="search-box" method="GET">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_filter); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="text" name="search" placeholder="Search users, email, company, course..." 
                           class="search-input" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>" class="btn btn-primary">
                            <i class="fas fa-times"></i>
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-actions" id="bulkActions">
            <div class="bulk-info">
                <span id="selectedCount">0</span> users selected
            </div>
            <div class="bulk-buttons">
                <button onclick="performBulkAction('activate')" class="btn btn-success">
                    <i class="fas fa-check"></i>
                    Activate Selected
                </button>
                <button onclick="performBulkAction('deactivate')" class="btn btn-primary">
                    <i class="fas fa-pause"></i>
                    Deactivate Selected
                </button>
                <button onclick="performBulkAction('delete')" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Delete Selected
                </button>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="users-section">
            <div class="users-table-container">
                <form id="bulkForm" method="POST">
                    <input type="hidden" name="bulk_action" id="bulkActionInput">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <!-- <th>Educational/Company Info</th> -->
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_result && $users_result->num_rows > 0): ?>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" 
                                                   class="user-checkbox" onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="user-avatar-small">
                                                    <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: #1e293b;">
                                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                                        <?php if ($user['company_name']): ?>
                                                            <span style="font-weight: 400; color: #64748b;">
                                                                (<?php echo htmlspecialchars($user['company_name']); ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div style="font-size: 0.8rem; color: #64748b;">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?php echo strtolower($user['role_name']); ?>">
                                                <?php echo htmlspecialchars($user['role_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                                                                <td>
                                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="user-details.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                    View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: #64748b;">
                                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                        <div style="font-size: 1.1rem; margin-bottom: 0.5rem;">No users found</div>
                                        <div>Try adjusting your search or filter criteria</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-left"></i>
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">
                            Next
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedCount.textContent = checkboxes.length;
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
        }

        function performBulkAction(action) {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Please select at least one user.');
                return;
            }
            
            let message = '';
            switch(action) {
                case 'activate':
                    message = `Are you sure you want to activate ${checkboxes.length} user(s)?`;
                    break;
                case 'deactivate':
                    message = `Are you sure you want to deactivate ${checkboxes.length} user(s)?`;
                    break;
                case 'delete':
                    message = `Are you sure you want to DELETE ${checkboxes.length} user(s)? This action cannot be undone.`;
                    break;
            }
            
            if (confirm(message)) {
                document.getElementById('bulkActionInput').value = action;
                document.getElementById('bulkForm').submit();
            }
        }
    </script>

</body>
</html>
