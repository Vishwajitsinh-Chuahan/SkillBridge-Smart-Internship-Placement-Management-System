<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Company') {
    header('Location: ../auth/login.php');
    exit();
}

$company_id = $_SESSION['user_id'];
// Fetch company name from database for display
$company_name_query = $conn->query("SELECT name FROM companies WHERE user_id = $company_id");
$company_name = 'Company'; // Default
if ($company_name_query && $company_name_query->num_rows > 0) {
    $company_name = $company_name_query->fetch_assoc()['name'];
}


// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = (int)$_POST['application_id'];
    $new_status = $_POST['status'];
    
    // Verify this application belongs to this company's internship AND fetch company name
    $verify_query = "
        SELECT 
            a.student_id, 
            i.title, 
            a.status as old_status,
            c.name as company_name
        FROM applications a 
        JOIN internships i ON a.internship_id = i.id
        JOIN companies c ON i.company_id = c.user_id
        WHERE a.id = ? AND i.company_id = ?
    ";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("ii", $application_id, $company_id);
    $stmt->execute();
    $verify_result = $stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $app_data = $verify_result->fetch_assoc();
        
        // Use company name from database
        $company_display_name = $app_data['company_name'];
        
        // Update application status
        $update_stmt = $conn->prepare("UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $application_id);
        
        if ($update_stmt->execute()) {
            // Create notification for student with COMPANY NAME
            $status_messages = [
            'pending' => "Your application status has been updated to Pending by {$company_display_name}.",
            'reviewed' => "Your application for {$app_data['title']} has been reviewed by {$company_display_name}.",
            'shortlisted' => "Congratulations! You have been shortlisted for {$app_data['title']} at {$company_display_name}.",
            'interview' => "Your interview has been scheduled for {$app_data['title']} at {$company_display_name}. Check your notifications for details.",
            'selected' => "🎉 Congratulations! You have been selected for {$app_data['title']} at {$company_display_name}!",
            'rejected' => "Unfortunately, your application for {$app_data['title']} at {$company_display_name} was not successful this time."
            ];
            
            $notification_title = "Application Status Updated";
            $notification_message = $status_messages[$new_status] ?? "Your application status has been updated.";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (student_id, application_id, type, title, message) VALUES (?, ?, 'status_update', ?, ?)");
            $notif_stmt->bind_param("iiss", $app_data['student_id'], $application_id, $notification_title, $notification_message);
            $notif_stmt->execute();
            
            $_SESSION['success'] = "Application status updated successfully and student notified.";
        } else {
            $_SESSION['error'] = "Failed to update application status.";
        }
    }
    
    header('Location: view-applications.php' . (!empty($_GET['tab']) ? '?tab=' . $_GET['tab'] : '') . (!empty($_GET['internship']) ? '&internship=' . $_GET['internship'] : ''));
    exit();
}

// Get filter parameters
$tab = $_GET['tab'] ?? 'all';
$internship_filter = isset($_GET['internship']) ? (int)$_GET['internship'] : 0;
$search = trim($_GET['search'] ?? '');

// Fetch company's internships for filter dropdown
$internships_query = "SELECT id, title FROM internships WHERE company_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($internships_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company_internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build applications query
$query = "
    SELECT 
        a.*,
        i.title as internship_title,
        i.location,
        i.duration,
        i.stipend,
        u.full_name as student_name,
        u.email as student_email,
        sp.university,
        sp.course,
        sp.cgpa,
        sp.year_of_study,
        sp.resume_path
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    JOIN users u ON a.student_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE i.company_id = ?
";

$params = [$company_id];
$types = 'i';

// Apply tab filter
if ($tab !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $tab;
    $types .= 's';
}

// Apply internship filter
if ($internship_filter > 0) {
    $query .= " AND i.id = ?";
    $params[] = $internship_filter;
    $types .= 'i';
}

// Apply search filter
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR sp.university LIKE ? OR sp.course LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

$query .= " ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics for tabs - UPDATED WITH INTERVIEW
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN a.status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
        SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
        SUM(CASE WHEN a.status = 'interview' THEN 1 ELSE 0 END) as interview,
        SUM(CASE WHEN a.status = 'selected' THEN 1 ELSE 0 END) as selected,
        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.company_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Status badge helper function - UPDATED WITH INTERVIEW
function getStatusBadge($status) {
    $badges = [
        'pending' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'text' => 'PENDING'],
        'reviewed' => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'text' => 'REVIEWED'],
        'shortlisted' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'text' => 'SHORTLISTED'],
        'interview' => ['color' => '#06b6d4', 'bg' => '#cffafe', 'text' => 'INTERVIEW'],
        'selected' => ['color' => '#059669', 'bg' => '#d1fae5', 'text' => 'SELECTED'],
        'rejected' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'text' => 'REJECTED']
    ];
    return $badges[$status] ?? $badges['pending'];
}

// Get user initials
$user_initials = strtoupper(substr($company_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Applications - SkillBridge</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 245px;
            height: 100vh;
            background: #3d4b5c;
            color: #fff;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }

        .logo {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo i {
            color: #4a90e2;
            font-size: 1.75rem;
        }

        .logo h2 {
            font-size: 1.4rem;
            font-weight: 600;
        }

        .nav-menu {
            padding: 0.5rem 0;
        }

        .nav-section {
            margin-bottom: 0.5rem;
        }

        .nav-section-title {
            padding: 1rem 1.25rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.85rem 1.25rem;
            color: #e5e7eb;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(74, 144, 226, 0.1);
            color: #fff;
        }

        .nav-link.active {
            background: #4a90e2;
            color: #fff;
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.85rem;
            font-size: 1rem;
        }

        .user-profile {
            padding: 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #4a90e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .user-details h4 {
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .user-details p {
            color: #9ca3af;
            font-size: 0.75rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 245px;
            min-height: 100vh;
            background: #fff;
        }

        .page-header {
            background: #fff;
            padding: 1.75rem 2.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .page-header h1 {
            font-size: 1.75rem;
            color: #1f2937;
            margin-bottom: 0.35rem;
            font-weight: 600;
        }

        .page-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .content-wrapper {
            padding: 0;
        }

        /* Alerts */
        .alert {
            padding: 1rem 2.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
            margin: 0;
        }

        .alert-success {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }

        /* Tabs Navigation */
        .tabs-container {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 2.5rem;
        }

        .tabs-nav {
            display: flex;
            gap: 0.25rem;
        }

        .tab-btn {
            padding: 1rem 1.5rem;
            border: none;
            background: transparent;
            color: #6b7280;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 3px solid transparent;
        }

        .tab-btn:hover {
            color: #4a90e2;
            background: #f9fafb;
        }

        .tab-btn.active {
            color: #4a90e2;
            border-bottom-color: #4a90e2;
        }

        .tab-count {
            background: #e5e7eb;
            color: #4b5563;
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .tab-btn.active .tab-count {
            background: #4a90e2;
            color: #fff;
        }

        /* Filters Bar */
        .filters-bar {
            background: #f9fafb;
            padding: 1.25rem 2.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 280px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .filter-select {
            padding: 0.65rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fff;
            min-width: 220px;
        }

        .btn {
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #4a90e2;
            color: white;
        }

        .btn-primary:hover {
            background: #3a7bc8;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #4b5563;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-sm {
            padding: 0.45rem 0.85rem;
            font-size: 0.85rem;
        }

        /* Table Container */
        .table-container {
            padding: 2.5rem;
        }

        .table-wrapper {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f9fafb;
        }

        th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #e5e7eb;
        }

        tbody tr {
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        td {
            padding: 1rem 1.25rem;
            font-size: 0.9rem;
            color: #374151;
        }

        .student-cell {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .student-avatar {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #4a90e2, #357abd);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .student-info {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .student-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .student-email {
            color: #6b7280;
            font-size: 0.8rem;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            display: inline-block;
        }

        .actions-cell {
            display: flex;
            gap: 0.5rem;
        }

        .btn-view {
            background: #4a90e2;
            color: white;
        }

        .btn-view:hover {
            background: #3a7bc8;
        }

        .btn-status {
            background: #f59e0b;
            color: white;
        }

        .btn-status:hover {
            background: #d97706;
        }

        .btn-download {
            background: #10b981;
            color: white;
        }

        .btn-download:hover {
            background: #059669;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.35rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #9ca3af;
        }

        /* Status Update Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            font-size: 1.35rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #4a90e2;
        }

        .modal-footer {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .info-box {
            background: #fef3c7;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
        }

        .info-box p {
            color: #92400e;
            font-size: 0.85rem;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2>SkillBridge</h2>
        </div>

        <nav class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <a href="../dashboard/company.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    Dashboard
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Internships</div>
                <a href="../internships/create.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    Post New Internship
                </a>
                <a href="../internships/manage.php" class="nav-link">
                    <i class="fas fa-list"></i>
                    Manage Internships
                </a>
                <a href="view-applications.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    View Applications
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>

        <div class="user-profile">
            <div class="user-info">
                <div class="user-avatar"><?php echo $user_initials; ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($company_name); ?></h4>
                    <p>Company</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>Applications Received</h1>
            <p>Review and manage student applications for your internships</p>
        </div>

        <!-- Success/Error Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs-nav">
                <a href="?tab=all<?php echo $internship_filter > 0 ? '&internship='.$internship_filter : ''; ?>" 
                   class="tab-btn <?php echo $tab === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    All Applications
                    <span class="tab-count"><?php echo $stats['total']; ?></span>
                </a>
                <a href="?tab=pending<?php echo $internship_filter > 0 ? '&internship='.$internship_filter : ''; ?>" 
                   class="tab-btn <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    Pending
                    <span class="tab-count"><?php echo $stats['pending']; ?></span>
                </a>
                <a href="?tab=reviewed<?php echo $internship_filter > 0 ? '&internship='.$internship_filter : ''; ?>" 
                   class="tab-btn <?php echo $tab === 'reviewed' ? 'active' : ''; ?>">
                    <i class="fas fa-eye"></i>
                    Reviewed
                    <span class="tab-count"><?php echo $stats['reviewed']; ?></span>
                </a>
                <a href="?tab=shortlisted<?php echo $internship_filter > 0 ? '&internship='.$internship_filter : ''; ?>" 
                   class="tab-btn <?php echo $tab === 'shortlisted' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    Shortlisted
                    <span class="tab-count"><?php echo $stats['shortlisted']; ?></span>
                </a>
                <a href="?tab=interview<?php echo $internship_filter > 0 ? '&internship='.$internship_filter : ''; ?>" 
                   class="tab-btn <?php echo $tab === 'interview' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    Interview
                    <span class="tab-count"><?php echo $stats['interview']; ?></span>
                </a>
                <a href="?tab=selected<?php echo $internship_filter > 0 ? '&internship='.$internship_filter : ''; ?>" 
                   class="tab-btn <?php echo $tab === 'selected' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    Selected
                    <span class="tab-count"><?php echo $stats['selected']; ?></span>
                </a>
                <a href="?tab=rejected<?php echo $internship_filter > 0 ? '&internship='.$internship_filter : ''; ?>" 
                   class="tab-btn <?php echo $tab === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i>
                    Rejected
                    <span class="tab-count"><?php echo $stats['rejected']; ?></span>
                </a>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <form method="GET" action="view-applications.php" style="display: flex; gap: 1rem; align-items: center; flex: 1;">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="Search students, email, university, course..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <select name="internship" class="filter-select">
                    <option value="0">All Internships</option>
                    <?php foreach ($company_internships as $int): ?>
                        <option value="<?php echo $int['id']; ?>" <?php echo $internship_filter == $int['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($int['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="view-applications.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <?php if (count($applications) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Internship</th>
                                <th>University</th>
                                <th>Course</th>
                                <th>CGPA</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): 
                                $badge = getStatusBadge($app['status']);
                                $student_initials = strtoupper(substr($app['student_name'], 0, 2));
                            ?>
                                <tr>
                                    <td>
                                        <div class="student-cell">
                                            <div class="student-avatar"><?php echo $student_initials; ?></div>
                                            <div class="student-info">
                                                <div class="student-name"><?php echo htmlspecialchars($app['student_name']); ?></div>
                                                <div class="student-email"><?php echo htmlspecialchars($app['student_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['internship_title']); ?></td>
                                    <td><?php echo htmlspecialchars($app['university'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['course'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($app['cgpa'] ?? 0, 2); ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                                            <?php echo $badge['text']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                    <td>
                                        <div class="actions-cell">
                                            <a href="student-details.php?id=<?php echo $app['student_id']; ?>&app_id=<?php echo $app['id']; ?>" 
                                               class="btn btn-sm btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (!empty($app['resume_path'])): ?>
                                                <a href="../uploads/resumes/<?php echo basename($app['resume_path']); ?>" 
                                                target="_blank" 
                                                class="btn btn-sm btn-download" title="Download Resume">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>

                                            <button onclick="openStatusModal(<?php echo $app['id']; ?>, '<?php echo $app['status']; ?>', '<?php echo htmlspecialchars($app['student_name']); ?>')" 
                                                    class="btn btn-sm btn-status" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Applications Found</h3>
                    <p>No applications match your current filters. Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Application Status</h3>
                <p id="modalStudentName" style="color: #6b7280; font-size: 0.9rem;"></p>
            </div>
            <form method="POST" action="view-applications.php<?php echo !empty($_GET['tab']) ? '?tab='.$_GET['tab'] : ''; echo !empty($_GET['internship']) ? '&internship='.$_GET['internship'] : ''; ?>">
                <input type="hidden" name="application_id" id="modalApplicationId">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Select New Status</label>
                        <select name="status" id="modalStatus" class="form-input" required>
                            <option value="pending">Pending</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="shortlisted">Shortlisted</option>
                            <option value="selected">Selected</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="info-box">
                        <p>
                            <i class="fas fa-info-circle"></i>
                            The student will be automatically notified about this status change.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeStatusModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-check"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStatusModal(applicationId, currentStatus, studentName) {
            document.getElementById('modalApplicationId').value = applicationId;
            document.getElementById('modalStatus').value = currentStatus;
            document.getElementById('modalStudentName').textContent = 'Updating status for: ' + studentName;
            document.getElementById('statusModal').classList.add('active');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html>
