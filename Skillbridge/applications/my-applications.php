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

// Get filter parameters
$tab = $_GET['tab'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build applications query with interview details
$query = "
    SELECT 
        a.*,
        i.title as internship_title,
        i.location,
        i.duration,
        i.stipend,
        i.internship_type,
        c.name as company_name,
        c.logo_path,
        int_schedule.interview_datetime,
        int_schedule.interview_mode,
        int_schedule.interview_location
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    JOIN companies c ON i.company_id = c.user_id
    LEFT JOIN interviews int_schedule ON a.id = int_schedule.application_id AND int_schedule.status = 'scheduled'
    WHERE a.student_id = ?
";

$params = [$user_id];
$types = 'i';

// Apply tab filter
if ($tab !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $tab;
    $types .= 's';
}

// Apply search filter
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (i.title LIKE ? OR c.name LIKE ? OR i.location LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

$query .= " ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics - UPDATED WITH INTERVIEW STATUS
$stats = [
    'total' => 0,
    'pending' => 0,
    'reviewed' => 0,
    'shortlisted' => 0,
    'interview' => 0,
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

// Status badge helper function - UPDATED WITH INTERVIEW
function getStatusBadge($status) {
    $badges = [
        'pending' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'text' => 'Pending', 'icon' => 'clock'],
        'reviewed' => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'text' => 'Reviewed', 'icon' => 'eye'],
        'shortlisted' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'text' => 'Shortlisted', 'icon' => 'star'],
        'interview' => ['color' => '#06b6d4', 'bg' => '#cffafe', 'text' => 'Interview Scheduled', 'icon' => 'calendar-check'],
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
            background: #f8fafc;
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
        }

        .user-details p {
            color: #94a3b8;
            font-size: 0.8rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: #f8fafc;
        }

        /* Header */
        .header {
            background: white;
            padding: 2rem 2.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid #e5e7eb;
        }

        .header h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .header p {
            color: #64748b;
            font-size: 1rem;
        }

        /* Stats Bar */
        .stats-bar {
            background: white;
            padding: 2rem 2.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 2rem;
            overflow-x: auto;
        }

        .stat-item {
            text-align: center;
            min-width: 120px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-item.total .stat-value { color: #667eea; }
        .stat-item.pending .stat-value { color: #3b82f6; }
        .stat-item.reviewed .stat-value { color: #8b5cf6; }
        .stat-item.shortlisted .stat-value { color: #f59e0b; }
        .stat-item.interview .stat-value { color: #06b6d4; }
        .stat-item.selected .stat-value { color: #10b981; }
        .stat-item.rejected .stat-value { color: #e91515ff; }

        .stat-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            padding: 0 2.5rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
        }

        .tab-button {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .tab-button:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-badge {
            background: #e5e7eb;
            color: #4b5563;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .tab-button.active .tab-badge {
            background: #667eea;
            color: white;
        }

        /* Content */
        .content {
            padding: 2.5rem;
        }

        .applications-grid {
            display: grid;
            gap: 1.5rem;
        }

        .application-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .application-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .application-card:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
            transform: translateY(-4px);
        }

        .application-card:hover::before {
            opacity: 1;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
        }

        .card-title h3 {
            font-size: 1.5rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .card-title .company {
            color: #667eea;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .card-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 12px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .meta-item i {
            color: #667eea;
            font-size: 1.1rem;
            width: 24px;
        }

        .meta-item .label {
            font-size: 0.8rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .meta-item .value {
            font-size: 0.95rem;
            color: #1f2937;
            font-weight: 600;
        }

        /* Interview Info Box */
        .interview-info {
            background: linear-gradient(135deg, #cffafe, #e0f2fe);
            border: 2px solid #06b6d4;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .interview-info h4 {
            color: #0e7490;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .interview-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .interview-detail {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }

        .interview-detail .label {
            font-size: 0.75rem;
            color: #0e7490;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .interview-detail .value {
            font-size: 0.95rem;
            color: #1f2937;
            font-weight: 600;
        }

        .card-footer {
            display: flex;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 2px solid #f3f4f6;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            flex: 1;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: 16px;
            border: 2px dashed #e5e7eb;
        }

        .empty-state i {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #4b5563;
            margin-bottom: 0.75rem;
        }

        .empty-state p {
            color: #9ca3af;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .sidebar {
                display: none;
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
                    <div class="nav-section-title">Settings</div>
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
            <h1><i class="fas fa-file-alt"></i> My Applications</h1>
            <p>Track the status of all your internship applications</p>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item total">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-item pending">
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-item reviewed">
                <div class="stat-value"><?php echo $stats['reviewed']; ?></div>
                <div class="stat-label">Under Review</div>
            </div>
            <div class="stat-item shortlisted">
                <div class="stat-value"><?php echo $stats['shortlisted']; ?></div>
                <div class="stat-label">Shortlisted</div>
            </div>
            <div class="stat-item interview">
                <div class="stat-value"><?php echo $stats['interview']; ?></div>
                <div class="stat-label">Interview</div>
            </div>
            <div class="stat-item selected">
                <div class="stat-value"><?php echo $stats['selected']; ?></div>
                <div class="stat-label">Selected</div>
            </div>
             <div class="stat-item rejected">
                <div class="stat-value"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-container">
            <a href="?tab=all" class="tab-button <?php echo $tab === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All Applications
                <span class="tab-badge"><?php echo $stats['total']; ?></span>
            </a>
            <a href="?tab=pending" class="tab-button <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Pending
                <span class="tab-badge"><?php echo $stats['pending']; ?></span>
            </a>
            <a href="?tab=reviewed" class="tab-button <?php echo $tab === 'reviewed' ? 'active' : ''; ?>">
                <i class="fas fa-eye"></i> Reviewed
                <span class="tab-badge"><?php echo $stats['reviewed']; ?></span>
            </a>
            <a href="?tab=shortlisted" class="tab-button <?php echo $tab === 'shortlisted' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Shortlisted
                <span class="tab-badge"><?php echo $stats['shortlisted']; ?></span>
            </a>
            <a href="?tab=interview" class="tab-button <?php echo $tab === 'interview' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Interview
                <span class="tab-badge"><?php echo $stats['interview']; ?></span>
            </a>
            <a href="?tab=selected" class="tab-button <?php echo $tab === 'selected' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Selected
                <span class="tab-badge"><?php echo $stats['selected']; ?></span>
            </a>
            <a href="?tab=rejected" class="tab-button <?php echo $tab === 'rejected' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Rejected
                <span class="tab-badge"><?php echo $stats['rejected']; ?></span>
            </a>
        </div>

        <!-- Applications Content -->
        <div class="content">
            <?php if (count($applications) > 0): ?>
                <div class="applications-grid">
                    <?php foreach ($applications as $app): 
                        $badge = getStatusBadge($app['status']);
                    ?>
                        <div class="application-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3><?php echo htmlspecialchars($app['internship_title']); ?></h3>
                                    <div class="company">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($app['company_name']); ?>
                                    </div>
                                </div>
                                <div class="status-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                                    <i class="fas fa-<?php echo $badge['icon']; ?>"></i>
                                    <?php echo $badge['text']; ?>
                                </div>
                            </div>

                            <div class="card-meta">
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div>
                                        <div class="label">Location</div>
                                        <div class="value"><?php echo htmlspecialchars($app['location']); ?></div>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <div>
                                        <div class="label">Duration</div>
                                        <div class="value"><?php echo htmlspecialchars($app['duration']); ?></div>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>
                                        <div class="label">Stipend</div>
                                        <div class="value"><?php echo htmlspecialchars($app['stipend']); ?></div>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <div>
                                        <div class="label">Applied On</div>
                                        <div class="value"><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($app['status'] === 'interview' && !empty($app['interview_datetime'])): ?>
                                <div class="interview-info">
                                    <h4><i class="fas fa-calendar-check"></i> Interview Scheduled</h4>
                                    <div class="interview-details">
                                        <div class="interview-detail">
                                            <div class="label">Date & Time</div>
                                            <div class="value"><?php echo date('M d, Y - g:i A', strtotime($app['interview_datetime'])); ?></div>
                                        </div>
                                        <div class="interview-detail">
                                            <div class="label">Mode</div>
                                            <div class="value"><?php echo ucfirst($app['interview_mode']); ?></div>
                                        </div>
                                        <div class="interview-detail">
                                            <div class="label"><?php echo $app['interview_mode'] === 'online' ? 'Meeting Link' : 'Location'; ?></div>
                                            <div class="value"><?php echo htmlspecialchars($app['interview_location']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="card-footer">
                                <a href="../internships/view-details.php?id=<?php echo $app['internship_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Internship Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Applications Found</h3>
                    <p>You haven't applied to any internships yet.</p>
                    <a href="../internships/browse.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Internships
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
