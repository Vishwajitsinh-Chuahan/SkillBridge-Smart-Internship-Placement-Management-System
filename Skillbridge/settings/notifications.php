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
$filter = $_GET['filter'] ?? 'all';

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    header('Location: notifications.php?filter=' . $filter);
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE student_id = $user_id");
    $_SESSION['success'] = 'All notifications marked as read.';
    header('Location: notifications.php');
    exit();
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $_SESSION['success'] = 'Notification deleted.';
    header('Location: notifications.php?filter=' . $filter);
    exit();
}

// Fetch notifications
$query = "
    SELECT 
        n.*,
        i.title as internship_title,
        c.name as company_name,
        a.status as application_status
    FROM notifications n
    JOIN applications a ON n.application_id = a.id
    JOIN internships i ON a.internship_id = i.id
    JOIN companies c ON i.company_id = c.user_id
    WHERE n.student_id = ?
";

$params = [$user_id];
$types = 'i';

// Apply filter
if ($filter === 'unread') {
    $query .= " AND n.is_read = 0";
} elseif ($filter === 'read') {
    $query .= " AND n.is_read = 1";
}

$query .= " ORDER BY n.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$unread_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE student_id = $user_id AND is_read = 0")->fetch_assoc()['count'];
$total_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE student_id = $user_id")->fetch_assoc()['count'];

// Notification type helper
function getNotificationIcon($type) {
    $icons = [
        'status_update' => 'sync-alt',
        'interview_scheduled' => 'calendar-check',
        'general_message' => 'envelope',
        'deadline_reminder' => 'clock'
    ];
    return $icons[$type] ?? 'bell';
}

function getNotificationColor($type) {
    $colors = [
        'status_update' => '#3b82f6',
        'interview_scheduled' => '#f59e0b',
        'general_message' => '#8b5cf6',
        'deadline_reminder' => '#ef4444'
    ];
    return $colors[$type] ?? '#64748b';
}

// Get user initials
$user_initials = strtoupper(substr($full_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SkillBridge</title>
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

        /* Sidebar - Same as other pages */
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
            position: relative;
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

        .notification-badge {
            position: absolute;
            right: 1rem;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
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

        .header-actions {
            display: flex;
            gap: 1rem;
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

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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

        .stat-card.unread { border-color: #ef4444; }
        .stat-card.total { border-color: #8b5cf6; }

        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .stat-card.unread h3 { color: #ef4444; }
        .stat-card.total h3 { color: #8b5cf6; }

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

        /* Notifications List */
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .notification-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid;
            display: flex;
            gap: 1rem;
            position: relative;
        }

        .notification-item.unread {
            background: #f8fafc;
            border-left-color: #667eea;
        }

        .notification-item.read {
            border-left-color: #e2e8f0;
            opacity: 0.8;
        }

        .notification-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.25rem;
            color: white;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .notification-meta {
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-message {
            color: #475569;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }

        .notification-details {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .notification-details span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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

        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-danger:hover {
            background: #fecaca;
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
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            background: #667eea;
            border-radius: 50%;
            position: absolute;
            top: 1rem;
            right: 1rem;
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
                    <a href="../applications/my-applications.php" class="nav-link">
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
                    <a href="notifications.php" class="nav-link active">
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
                    <h1><i class="fas fa-bell"></i> Notifications</h1>
                    <p>Stay updated with your application status and messages</p>
                </div>
                <div class="header-actions">
                    <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-primary">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </a>
                    <?php endif; ?>
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
                <div class="stat-card unread">
                    <h3><?php echo $unread_count; ?></h3>
                    <p>Unread Notifications</p>
                </div>
                <div class="stat-card total">
                    <h3><?php echo $total_count; ?></h3>
                    <p>Total Notifications</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="notifications.php" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Notifications
                </a>
                <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> Unread
                </a>
                <a href="?filter=read" class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open"></i> Read
                </a>
            </div>

            <!-- Notifications List -->
            <?php if (count($notifications) > 0): ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notif): 
                        $icon = getNotificationIcon($notif['type']);
                        $color = getNotificationColor($notif['type']);
                    ?>
                        <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>">
                            <?php if (!$notif['is_read']): ?>
                                <div class="unread-dot"></div>
                            <?php endif; ?>
                            
                            <div class="notification-icon" style="background: <?php echo $color; ?>;">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-header">
                                    <div>
                                        <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                        <div class="notification-meta">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('M d, Y \a\t g:i A', strtotime($notif['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if (!$notif['is_read']): ?>
                                            <a href="?mark_read=<?php echo $notif['id']; ?>&filter=<?php echo $filter; ?>" class="btn btn-sm btn-secondary" title="Mark as read">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $notif['id']; ?>&filter=<?php echo $filter; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this notification?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="notification-message">
                                    <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                                </div>
                                
                                <div class="notification-details">
                                    <span>
                                        <i class="fas fa-briefcase"></i>
                                        <?php echo htmlspecialchars($notif['internship_title']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($notif['company_name']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No Notifications</h3>
                    <p>You don't have any notifications<?php echo $filter !== 'all' ? ' in this category' : ' yet'; ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
