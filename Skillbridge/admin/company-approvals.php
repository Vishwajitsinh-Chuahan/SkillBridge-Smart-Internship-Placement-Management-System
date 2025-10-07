<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/email_functions.php';
requireAdmin();

$page_title = 'Company Approvals';
$admin_name = $_SESSION['full_name'];

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['company_id'])) {
        $company_id = (int)$_POST['company_id'];
        $action = $_POST['action'];
        $reason = trim($_POST['reason'] ?? '');
        
        if ($action === 'approve') {
            // Update companies table
            $stmt = $conn->prepare("UPDATE companies SET status = 'approved', updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $company_id);
            
            if ($stmt->execute()) {
                // Also update user status to active
                $user_stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $user_stmt->bind_param("i", $company_id);
                $user_stmt->execute();
                
                // Send approval email
                sendCompanyApprovalEmail($company_id, 'approved');
                $success_message = "Company has been approved successfully!";
                
                // Log admin action
                error_log("Admin {$_SESSION['full_name']} approved company ID: $company_id");
            }
            
        } elseif ($action === 'reject') {
            // Update companies table with rejection
            $stmt = $conn->prepare("UPDATE companies SET status = 'rejected', updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $company_id);
            
            if ($stmt->execute()) {
                // Also update user status to rejected
                $user_stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                $user_stmt->bind_param("i", $company_id);
                $user_stmt->execute();
                
                // Send rejection email
                sendCompanyApprovalEmail($company_id, 'rejected', $reason);
                $success_message = "Company has been rejected.";
                
                // Log admin action
                error_log("Admin {$_SESSION['full_name']} rejected company ID: $company_id. Reason: $reason");
            }
        }
    }
}

// ✅ NEW: Tab filtering logic
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
$valid_tabs = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($tab, $valid_tabs)) {
    $tab = 'pending';
}

// ✅ NEW: Build query based on selected tab
$where_condition = '';
switch ($tab) {
    case 'pending':
        $where_condition = "AND (u.status = 'pending' OR c.status = 'pending')";
        break;
    case 'approved':
        $where_condition = "AND c.status = 'approved' AND u.status = 'active'";
        break;
    case 'rejected':
        $where_condition = "AND (c.status = 'rejected' OR u.status = 'rejected')";
        break;
    case 'all':
        $where_condition = "";
        break;
}

// ✅ UPDATED: Query companies with tab filtering
$companies_query = "
    SELECT u.id, u.full_name, u.email, u.phone, u.created_at, u.status as user_status,
           c.name as company_name, c.industry, c.company_size, c.website, c.address, c.status as company_status
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN companies c ON u.id = c.user_id
    WHERE r.role_name = 'Company' $where_condition
    ORDER BY u.created_at DESC
";
$companies_result = $conn->query($companies_query);

// ✅ NEW: Get statistics for tabs
$stats_query = "
    SELECT 
        COUNT(CASE WHEN c.status = 'pending' OR u.status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN c.status = 'approved' AND u.status = 'active' THEN 1 END) as approved_count,
        COUNT(CASE WHEN c.status = 'rejected' OR u.status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(*) as total_count
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN companies c ON u.id = c.user_id
    WHERE r.role_name = 'Company'
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Email function (existing)
function sendCompanyApprovalEmail($company_id, $status, $reason = '') {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT u.full_name, u.email, c.name as company_name 
        FROM users u 
        LEFT JOIN companies c ON u.id = c.user_id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    
    if ($company) {
        if (function_exists('sendCompanyStatusEmail')) {
            return sendCompanyStatusEmail($company['full_name'], $company['email'], $company['company_name'], $status, $reason);
        }
    }
    return false;
}
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

        /* Sidebar - Exact match to admin dashboard */
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

        /* ✅ NEW: Tab Navigation */
        .tab-navigation {
            background: #ffffff;
            padding: 0 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .tabs {
            display: flex;
            gap: 0;
        }

        .tab {
            padding: 1rem 1.5rem;
            background: #f8fafc;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
            position: relative;
        }

        .tab:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .tab.active {
            background: #ffffff;
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        .tab-badge {
            background: #3b82f6;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            margin-left: 0.5rem;
        }

        .tab.active .tab-badge {
            background: #1d4ed8;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        /* Company List */
        .company-list-section {
            padding: 0 2rem 2rem;
        }

        .section-header {
            background: #ffffff;
            border-radius: 0.75rem 0.75rem 0 0;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-title {
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #3b82f6;
        }

        .companies-table-container {
            background: #ffffff;
            border-radius: 0 0 0.75rem 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .companies-table {
            width: 100%;
            border-collapse: collapse;
        }

        .companies-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            font-size: 0.875rem;
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .companies-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #64748b;
            font-size: 0.875rem;
        }

        .companies-table tr:hover {
            background-color: #f8fafc;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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

        .btn-view {
            background: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
            color: white;
        }

        .btn-approve {
            background: #10b981;
            color: white;
        }

        .btn-approve:hover {
            background: #059669;
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
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
            
            .stats-grid {
                padding: 1rem;
                grid-template-columns: 1fr;
            }
            
            .companies-table-container {
                overflow-x: auto;
            }

            .tabs {
                flex-wrap: wrap;
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
                <a href="company-approvals.php" class="nav-link active">
                    <i class="fas fa-building"></i>
                    Company Approvals
                    <?php if ($stats['pending_count'] > 0): ?>
                        <span style="background: #ef4444; color: white; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 10px; margin-left: auto;">
                            <?php echo $stats['pending_count']; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
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
                <h1>Company Approvals</h1>
                <p>Review and manage company registrations</p>
            </div>
        </header>

        <!-- ✅ NEW: Tab Navigation -->
        <div class="tab-navigation">
            <div class="tabs">
                <a href="?tab=pending" class="tab <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    Pending
                    <span class="tab-badge"><?php echo $stats['pending_count']; ?></span>
                </a>
                <a href="?tab=approved" class="tab <?php echo $tab === 'approved' ? 'active' : ''; ?>">
                    <i class="fas fa-check"></i>
                    Approved
                    <span class="tab-badge"><?php echo $stats['approved_count']; ?></span>
                </a>
                <a href="?tab=rejected" class="tab <?php echo $tab === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times"></i>
                    Rejected
                    <span class="tab-badge"><?php echo $stats['rejected_count']; ?></span>
                </a>
                <a href="?tab=all" class="tab <?php echo $tab === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    All Companies
                    <span class="tab-badge"><?php echo $stats['total_count']; ?></span>
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Company List -->
        <div class="company-list-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-list"></i>
                    <?php
                    switch($tab) {
                        case 'pending': echo 'Pending Company Approvals'; break;
                        case 'approved': echo 'Approved Companies'; break; 
                        case 'rejected': echo 'Rejected Companies'; break;
                        case 'all': echo 'All Companies'; break;
                    }
                    ?>
                </div>
            </div>
            
            <div class="companies-table-container">
                <?php if ($companies_result && $companies_result->num_rows > 0): ?>
                    <table class="companies-table">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Industry</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($company = $companies_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($company['company_name'] ?: 'Not Provided'); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($company['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($company['email']); ?></td>
                                    <td><?php echo htmlspecialchars($company['industry'] ?: 'Not Specified'); ?></td>
                                    <td><?php echo htmlspecialchars($company['company_size'] ?: 'Not Specified'); ?></td>
                                    <td>
                                        <?php 
                                        $status = $company['company_status'] ?: $company['user_status'];
                                        $status_class = $status === 'active' ? 'approved' : $status;
                                        ?>
                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($status === 'active' ? 'approved' : $status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="company-details.php?id=<?php echo $company['id']; ?>" class="btn btn-view">
                                                <i class="fas fa-eye"></i>
                                                View
                                            </a>
                                            
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Companies Found</h3>
                        <p>
                            <?php
                            switch($tab) {
                                case 'pending': echo 'No pending company approvals at the moment.'; break;
                                case 'approved': echo 'No approved companies yet.'; break;
                                case 'rejected': echo 'No rejected companies.'; break;
                                case 'all': echo 'No companies registered yet.'; break;
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="approveForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="company_id" id="approve_company_id">
    </form>

    <form id="rejectForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="company_id" id="reject_company_id">
        <input type="hidden" name="reason" id="reject_reason">
    </form>

    <script>
        function approveCompany(companyId) {
            if (confirm('Are you sure you want to approve this company?')) {
                document.getElementById('approve_company_id').value = companyId;
                document.getElementById('approveForm').submit();
            }
        }

        function rejectCompany(companyId) {
            const reason = prompt('Please provide a reason for rejection:');
            if (reason && reason.trim()) {
                document.getElementById('reject_company_id').value = companyId;
                document.getElementById('reject_reason').value = reason.trim();
                document.getElementById('rejectForm').submit();
            }
        }
    </script>

</body>
</html>
