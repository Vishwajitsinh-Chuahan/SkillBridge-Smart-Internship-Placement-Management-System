<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Company') {
    header('Location: ../auth/login.php');
    exit();
}

$company_id = $_SESSION['user_id'];

// Fetch company name from database
$company_name_query = $conn->query("SELECT name FROM companies WHERE user_id = $company_id");
$company_name = 'Company';
if ($company_name_query && $company_name_query->num_rows > 0) {
    $company_name = $company_name_query->fetch_assoc()['name'];
}

// Get date range filter
$date_range = isset($_GET['range']) ? $_GET['range'] : '30';
$date_filter = match($date_range) {
    '7' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
    '30' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
    '90' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
    'all' => '1970-01-01',
    default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
};

// Overall Statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT i.id) as total_internships,
        COUNT(DISTINCT a.id) as total_applications,
        COUNT(DISTINCT CASE WHEN a.status = 'shortlisted' THEN a.id END) as total_shortlisted,
        COUNT(DISTINCT CASE WHEN a.status = 'selected' THEN a.id END) as total_selected,
        COUNT(DISTINCT CASE WHEN i.status = 'active' THEN i.id END) as active_internships
    FROM internships i
    LEFT JOIN applications a ON i.id = a.internship_id
    WHERE i.company_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Applications by Status
$status_query = "
    SELECT 
        a.status,
        COUNT(*) as count
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.company_id = ? AND a.applied_at >= $date_filter
    GROUP BY a.status
";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$status_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Applications Over Time (Last 30 days)
$timeline_query = "
    SELECT 
        DATE(a.applied_at) as date,
        COUNT(*) as count
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.company_id = ? AND a.applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(a.applied_at)
    ORDER BY date ASC
";
$stmt = $conn->prepare($timeline_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$timeline_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top Performing Internships
$top_internships_query = "
    SELECT 
        i.title,
        i.location,
        i.created_at,
        COUNT(a.id) as application_count,
        COUNT(CASE WHEN a.status = 'shortlisted' THEN 1 END) as shortlisted_count
    FROM internships i
    LEFT JOIN applications a ON i.id = a.internship_id
    WHERE i.company_id = ?
    GROUP BY i.id
    ORDER BY application_count DESC
    LIMIT 5
";
$stmt = $conn->prepare($top_internships_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$top_internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Response Rate
$response_query = "
    SELECT 
        COUNT(CASE WHEN a.status != 'pending' THEN 1 END) as responded,
        COUNT(*) as total
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.company_id = ?
";
$stmt = $conn->prepare($response_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$response_data = $stmt->get_result()->fetch_assoc();
$response_rate = $response_data['total'] > 0 ? round(($response_data['responded'] / $response_data['total']) * 100) : 0;

// Conversion Rate (Shortlisted to Selected)
$conversion_query = "
    SELECT 
        COUNT(CASE WHEN a.status = 'shortlisted' THEN 1 END) as shortlisted,
        COUNT(CASE WHEN a.status = 'selected' THEN 1 END) as selected
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.company_id = ?
";
$stmt = $conn->prepare($conversion_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$conversion_data = $stmt->get_result()->fetch_assoc();
$conversion_rate = $conversion_data['shortlisted'] > 0 ? round(($conversion_data['selected'] / $conversion_data['shortlisted']) * 100) : 0;

// Get user initials
$user_initials = strtoupper(substr($company_name, 0, 2));

// Prepare data for charts
$status_labels = [];
$status_counts = [];
foreach ($status_data as $item) {
    $status_labels[] = ucfirst($item['status']);
    $status_counts[] = $item['count'];
}

$timeline_labels = [];
$timeline_counts = [];
foreach ($timeline_data as $item) {
    $timeline_labels[] = date('M d', strtotime($item['date']));
    $timeline_counts[] = $item['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - SkillBridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Sidebar - Same as student.php */
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

        /* Header */
        .header {
            background: white;
            padding: 2rem 2.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid #e5e7eb;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-title h1 i {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-title p {
            color: #64748b;
            font-size: 1rem;
        }

        .filter-select {
            padding: 0.75rem 1.25rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            background: #fff;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        /* Content */
        .content {
            padding: 2.5rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.75rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-card.total { border-color: #3b82f6; }
        .stat-card.applications { border-color: #8b5cf6; }
        .stat-card.shortlisted { border-color: #f59e0b; }
        .stat-card.selected { border-color: #10b981; }
        .stat-card.active { border-color: #ef4444; }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .stat-card.total .stat-icon {
            background: #dbeafe;
            color: #3b82f6;
        }

        .stat-card.applications .stat-icon {
            background: #ede9fe;
            color: #8b5cf6;
        }

        .stat-card.shortlisted .stat-icon {
            background: #fef3c7;
            color: #f59e0b;
        }

        .stat-card.selected .stat-icon {
            background: #d1fae5;
            color: #10b981;
        }

        .stat-card.active .stat-icon {
            background: #fee2e2;
            color: #ef4444;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card.total .stat-value { color: #3b82f6; }
        .stat-card.applications .stat-value { color: #8b5cf6; }
        .stat-card.shortlisted .stat-value { color: #f59e0b; }
        .stat-card.selected .stat-value { color: #10b981; }
        .stat-card.active .stat-value { color: #ef4444; }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .chart-card h3 {
            font-size: 1.25rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chart-card h3 i {
            color: #667eea;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Performance Card */
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .performance-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .performance-card h3 {
            font-size: 1.25rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .performance-item:last-child {
            border-bottom: none;
        }

        .metric-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 1rem auto;
        }

        .metric-circle.success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .metric-circle.warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .metric-label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.5rem;
            text-align: center;
        }

        /* Top Internships */
        .top-internships {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .top-internships h3 {
            font-size: 1.25rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .internship-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .internship-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .internship-info h4 {
            font-size: 1rem;
            color: #1e293b;
            margin-bottom: 0.35rem;
            font-weight: 600;
        }

        .internship-info p {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .internship-stats {
            display: flex;
            gap: 2rem;
            text-align: center;
        }

        .stat-num {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-text {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .charts-grid,
            .performance-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .sidebar {
                display: none;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard/company.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </a>
        </div>

        <div class="sidebar-scroll-container">
            <nav>
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="../dashboard/company.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        Dashboard
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Internships</div>
                    <a href="create.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        Post New Internship
                    </a>
                    <a href="manage.php" class="nav-link">
                        <i class="fas fa-list"></i>
                        Manage Internships
                    </a>
                    <a href="analytics.php" class="nav-link active">
                        <i class="fas fa-chart-bar"></i>
                        Analytics
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Applications</div>
                    <a href="../applications/view-applications.php" class="nav-link">
                        <i class="fas fa-inbox"></i>
                        Received Applications
                    </a>
                    <a href="../applications/shortlisted.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        Shortlisted Candidates
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
                    <h4><?php echo htmlspecialchars($company_name); ?></h4>
                    <p>Company</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <div class="header-title">
                    <h1><i class="fas fa-chart-bar"></i> Analytics Dashboard</h1>
                    <p>Track your internship performance and insights</p>
                </div>
                <form method="GET" action="">
                    <select name="range" class="filter-select" onchange="this.form.submit()">
                        <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="all" <?php echo $date_range == 'all' ? 'selected' : ''; ?>>All Time</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="content">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_internships']; ?></div>
                    <div class="stat-label">Total Internships</div>
                </div>

                <div class="stat-card applications">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Applications</div>
                </div>

                <div class="stat-card shortlisted">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_shortlisted']; ?></div>
                    <div class="stat-label">Shortlisted</div>
                </div>

                <div class="stat-card selected">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_selected']; ?></div>
                    <div class="stat-label">Selected</div>
                </div>

                <div class="stat-card active">
                    <div class="stat-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['active_internships']; ?></div>
                    <div class="stat-label">Active Posts</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <!-- Applications Over Time -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-line"></i> Applications Timeline</h3>
                    <div class="chart-container">
                        <canvas id="timelineChart"></canvas>
                    </div>
                </div>

                <!-- Applications by Status -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-pie"></i> Application Status Distribution</h3>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="performance-grid">
                <div class="performance-card">
                    <h3><i class="fas fa-tachometer-alt"></i> Response Rate</h3>
                    <div class="metric-circle success">
                        <?php echo $response_rate; ?>%
                    </div>
                    <div class="metric-label">Applications Reviewed</div>
                </div>

                <div class="performance-card">
                    <h3><i class="fas fa-percentage"></i> Conversion Rate</h3>
                    <div class="metric-circle warning">
                        <?php echo $conversion_rate; ?>%
                    </div>
                    <div class="metric-label">Shortlisted to Selected</div>
                </div>
            </div>

            <!-- Top Performing Internships -->
            <div class="top-internships">
                <h3><i class="fas fa-trophy"></i> Top Performing Internships</h3>
                <?php if (!empty($top_internships)): ?>
                    <?php foreach ($top_internships as $internship): ?>
                        <div class="internship-item">
                            <div class="internship-info">
                                <h4><?php echo htmlspecialchars($internship['title']); ?></h4>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($internship['location']); ?> • Posted <?php echo date('M d, Y', strtotime($internship['created_at'])); ?></p>
                            </div>
                            <div class="internship-stats">
                                <div>
                                    <div class="stat-num"><?php echo $internship['application_count']; ?></div>
                                    <div class="stat-text">Applications</div>
                                </div>
                                <div>
                                    <div class="stat-num"><?php echo $internship['shortlisted_count']; ?></div>
                                    <div class="stat-text">Shortlisted</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #9ca3af; padding: 2rem;">No data available yet</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Applications Timeline Chart
        const timelineCtx = document.getElementById('timelineChart').getContext('2d');
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($timeline_labels); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode($timeline_counts); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: [
                        '#3b82f6',
                        '#8b5cf6',
                        '#f59e0b',
                        '#10b981',
                        '#ef4444',
                        '#6366f1'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
