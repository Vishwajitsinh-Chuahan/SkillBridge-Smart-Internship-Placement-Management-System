<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Student';

// Get filter parameter
$status_filter = $_GET['status'] ?? '';

// Fetch all applications
$query = "
    SELECT 
        a.*,
        i.title as internship_title,
        i.location,
        i.location_type,
        i.duration,
        i.stipend,
        i.status as internship_status,
        c.name as company_name,
        c.trust_level
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    JOIN companies c ON i.company_id = c.user_id
    WHERE a.student_id = ?
";

$params = [$user_id];
$types = 'i';

// Apply status filter
if (!empty($status_filter)) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'reviewed' => 0,
    'shortlisted' => 0,
    'selected' => 0,
    'rejected' => 0
];

$stmt = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM applications 
    WHERE student_id = $user_id 
    GROUP BY status
");

while ($row = $stmt->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}

// Status badge helper function
function getStatusBadge($status) {
    $badges = [
        'pending' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'text' => 'Pending', 'icon' => 'clock'],
        'reviewed' => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'text' => 'Reviewed', 'icon' => 'eye'],
        'shortlisted' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'text' => 'Shortlisted', 'icon' => 'star'],
        'selected' => ['color' => '#059669', 'bg' => '#d1fae5', 'text' => 'Selected', 'icon' => 'check-circle'],
        'rejected' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'text' => 'Rejected', 'icon' => 'times-circle']
    ];
    return $badges[$status] ?? $badges['pending'];
}

// Get user initials
$user_initials = strtoupper(substr($full_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - SkillBridge</title>
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

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background-color: #2d3748;
            color: #cbd5e1;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(203, 213, 225, 0.1);
            flex-shrink: 0;
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #ffffff;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .sidebar-header .logo i {
            color: #60a5fa;
            font-size: 2rem;
        }

        .sidebar-scroll-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-bottom: 1rem;
        }

        .sidebar-scroll-container::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-scroll-container::-webkit-scrollbar-thumb {
            background: rgba(203, 213, 225, 0.2);
            border-radius: 3px;
        }

        .nav-section {
            padding: 1rem 0;
        }

        .nav-section-title {
            padding: 1rem 1.5rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
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
            width: 22px;
            margin-right: 1rem;
            font-size: 1rem;
            color: #94a3b8;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #60a5fa;
        }

        .user-profile {
            flex-shrink: 0;
            padding: 1.5rem;
            background-color: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(203, 213, 225, 0.1);
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
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
            flex-shrink: 0;
        }

        .user-details h4 {
            color: #ffffff;
            font-size: 0.95rem;
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

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: #f8fafc;
        }

        .header {
            background: white;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-card.total { border-color: #8b5cf6; }
        .stat-card.pending { border-color: #3b82f6; }
        .stat-card.shortlisted { border-color: #f59e0b; }
        .stat-card.selected { border-color: #10b981; }

        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .stat-card.total h3 { color: #8b5cf6; }
        .stat-card.pending h3 { color: #3b82f6; }
        .stat-card.shortlisted h3 { color: #f59e0b; }
        .stat-card.selected h3 { color: #10b981; }

        .stat-card p {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Tabs */
        .filter-tabs {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #475569;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-tab:hover {
            border-color: #667eea;
            background: #f1f5f9;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }

        /* Applications List */
        .applications-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .application-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .application-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .app-title {
            font-size: 1.25rem;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .app-company {
            color: #667eea;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .app-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .app-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .app-meta i {
            color: #667eea;
        }

        .app-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.85rem;
            color: #64748b;
        }

        .app-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
            background: white;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard/student.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </a>
        </div>

        <div class="sidebar-scroll-container">
            <nav>
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="../dashboard/student.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="../dashboard/profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        My Profile
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Internships</div>
                    <a href="../internships/browse.php" class="nav-link">
                        <i class="fas fa-search"></i>
                        Browse Internships
                    </a>
                    <a href="my-applications.php" class="nav-link active">
                        <i class="fas fa-file-alt"></i>
                        My Applications
                    </a>
                    <a href="../internships/saved.php" class="nav-link">
                        <i class="fas fa-bookmark"></i>
                        Saved Internships
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Resources</div>
                    <a href="../resources/resume-builder.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        Resume Builder
                    </a>
                    <a href="../resources/interview-prep.php" class="nav-link">
                        <i class="fas fa-user-tie"></i>
                        Interview Prep
                    </a>
                    <a href="../resources/career-tips.php" class="nav-link">
                        <i class="fas fa-lightbulb"></i>
                        Career Tips
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <a href="../settings/notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        Notifications
                         <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="../auth/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </nav>
        </div>

        <div class="user-profile">
            <div class="user-info">
                <div class="user-avatar"><?php echo $user_initials; ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($full_name); ?></h4>
                    <p>Student</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-file-alt"></i> My Applications</h1>
                    <p>Track the status of all your internship applications</p>
                </div>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Applications</p>
                </div>
                <div class="stat-card pending">
                    <h3><?php echo $stats['pending'] + $stats['reviewed']; ?></h3>
                    <p>Under Review</p>
                </div>
                <div class="stat-card shortlisted">
                    <h3><?php echo $stats['shortlisted']; ?></h3>
                    <p>Shortlisted</p>
                </div>
                <div class="stat-card selected">
                    <h3><?php echo $stats['selected']; ?></h3>
                    <p>Selected</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="my-applications.php" class="filter-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Applications
                </a>
                <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                </a>
                <a href="?status=reviewed" class="filter-tab <?php echo $status_filter === 'reviewed' ? 'active' : ''; ?>">
                    <i class="fas fa-eye"></i> Reviewed
                </a>
                <a href="?status=shortlisted" class="filter-tab <?php echo $status_filter === 'shortlisted' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i> Shortlisted
                </a>
                <a href="?status=selected" class="filter-tab <?php echo $status_filter === 'selected' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Selected
                </a>
                <a href="?status=rejected" class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Rejected
                </a>
            </div>

            <!-- Applications List -->
            <?php if (count($applications) > 0): ?>
                <div class="applications-list">
                    <?php foreach ($applications as $app): 
                        $badge = getStatusBadge($app['status']);
                    ?>
                        <div class="application-card">
                            <div class="app-header">
                                <div>
                                    <h3 class="app-title"><?php echo htmlspecialchars($app['internship_title']); ?></h3>
                                    <p class="app-company">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($app['company_name']); ?>
                                    </p>
                                </div>
                                <span class="status-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                                    <i class="fas fa-<?php echo $badge['icon']; ?>"></i>
                                    <?php echo $badge['text']; ?>
                                </span>
                            </div>

                            <div class="app-meta">
                                <span>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($app['location']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-laptop-house"></i>
                                    <?php echo $app['location_type']; ?>
                                </span>
                                <span>
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo $app['duration']; ?>
                                </span>
                                <span>
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?php echo htmlspecialchars($app['stipend']); ?>
                                </span>
                            </div>

                            <div class="app-footer">
                                <div class="app-date">
                                    <i class="fas fa-clock"></i>
                                    Applied on <?php echo date('M d, Y', strtotime($app['applied_at'])); ?>
                                </div>
                                <a href="../internships/view-details.php?id=<?php echo $app['internship_id']; ?>" class="btn btn-view">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Applications Found</h3>
                    <p>You haven't applied to any internships yet<?php echo !empty($status_filter) ? ' with this status' : ''; ?>.</p>
                    <a href="../internships/browse.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Internships
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
