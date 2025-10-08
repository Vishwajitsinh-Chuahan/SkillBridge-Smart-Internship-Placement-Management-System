<?php
session_start();
require_once '../config/database.php';
require_once '../includes/profile_functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Student';

// Profile completion check
$can_apply = canApplyForInternships($user_id, $conn);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile_data = $profile_result->num_rows > 0 ? $profile_result->fetch_assoc() : null;

$profile_completion = calculateProfileCompletion($user_data, $profile_data);

// Get tab filter
$tab = $_GET['tab'] ?? 'current';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$location = trim($_GET['location'] ?? '');
$internship_type = $_GET['type'] ?? '';
$location_type = $_GET['location_type'] ?? '';
$duration = $_GET['duration'] ?? '';
$company_filter = $_GET['company'] ?? '';

// Base query for all tabs
$base_query = "
    SELECT 
        i.*,
        c.name as company_name,
        c.trust_level,
        c.logo_path,
        (SELECT COUNT(*) FROM applications WHERE internship_id = i.id) as application_count,
        (SELECT COUNT(*) FROM saved_internships WHERE internship_id = i.id AND student_id = ?) as is_saved,
        (SELECT COUNT(*) FROM applications WHERE internship_id = i.id AND student_id = ?) as has_applied
    FROM internships i
    JOIN companies c ON i.company_id = c.user_id
    WHERE i.status IN ('approved', 'active')
";

$params = [$user_id, $user_id];
$types = 'ii';

// Tab-specific conditions
if ($tab === 'current') {
    // Current: Can apply (deadline not passed)
    $base_query .= " AND i.application_deadline >= CURDATE()";
} elseif ($tab === 'active') {
    // Active: Deadline passed but internship ongoing
    $base_query .= " AND i.application_deadline < CURDATE()
                     AND i.start_date <= CURDATE()
                     AND (i.end_date IS NULL OR i.end_date >= CURDATE())";
} elseif ($tab === 'past') {
    // Past: Internship ended
    $base_query .= " AND i.end_date IS NOT NULL AND i.end_date < CURDATE()";
}

// Apply filters
if (!empty($search)) {
    $search_term = "%$search%";
    $base_query .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.skills_required LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if (!empty($location)) {
    $location_term = "%$location%";
    $base_query .= " AND i.location LIKE ?";
    $params[] = $location_term;
    $types .= 's';
}

if (!empty($internship_type)) {
    $base_query .= " AND i.internship_type = ?";
    $params[] = $internship_type;
    $types .= 's';
}

if (!empty($location_type)) {
    $base_query .= " AND i.location_type = ?";
    $params[] = $location_type;
    $types .= 's';
}

if (!empty($duration)) {
    $base_query .= " AND i.duration = ?";
    $params[] = $duration;
    $types .= 's';
}

if (!empty($company_filter)) {
    $base_query .= " AND c.name LIKE ?";
    $company_term = "%$company_filter%";
    $params[] = $company_term;
    $types .= 's';
}

$base_query .= " ORDER BY i.created_at DESC";

// Execute query
$stmt = $conn->prepare($base_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get counts for each tab
$current_count = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status IN ('approved', 'active') AND application_deadline >= CURDATE()")->fetch_assoc()['count'];
$active_count = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status IN ('approved', 'active') AND application_deadline < CURDATE() AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())")->fetch_assoc()['count'];
$past_count = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status IN ('approved', 'active') AND end_date IS NOT NULL AND end_date < CURDATE()")->fetch_assoc()['count'];

// Get user initials
$user_initials = strtoupper(substr($full_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Internships - SkillBridge</title>
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
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        /* Tab Navigation */
        .tab-navigation {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            gap: 0.5rem;
        }

        .tab-btn {
            flex: 1;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #475569;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            border-color: #667eea;
            background: #f1f5f9;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }

        .tab-icon {
            font-size: 1.5rem;
        }

        .tab-label {
            font-size: 0.9rem;
        }

        .tab-count {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* Filters Card */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .filters-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
        }

        .filter-input {
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        /* Internships Grid */
        .internships-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .internship-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border: 2px solid transparent;
            position: relative;
        }

        .internship-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .card-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-upcoming { background: #dbeafe; color: #1e40af; }
        .badge-current { background: #d1fae5; color: #065f46; }
        .badge-active { background: #fef3c7; color: #92400e; }
        .badge-past { background: #e5e7eb; color: #374151; }

        .card-header {
            margin-bottom: 1rem;
        }

        .internship-title {
            font-size: 1.25rem;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .company-name {
            color: #667eea;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .meta-item i {
            color: #667eea;
        }

        .card-skills {
            margin-bottom: 1rem;
        }

        .skills-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .skill-tag {
            padding: 0.25rem 0.75rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .positions-info {
            font-size: 0.9rem;
            color: #64748b;
        }

        .positions-info strong {
            color: #1e293b;
            font-size: 1.1rem;
        }

        .positions-full {
            color: #ef4444;
            font-weight: 700;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-save {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-save:hover {
            background: #e2e8f0;
        }

        .btn-save.saved {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .btn-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-warning:hover {
            background: #fde68a;
        }

        .deadline-info {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .deadline-passed {
            color: #ef4444;
            font-weight: 600;
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

        @media (max-width: 1200px) {
            .internships-grid {
                grid-template-columns: 1fr;
            }
            .filters-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .filters-grid {
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
                    <a href="browse.php" class="nav-link active">
                        <i class="fas fa-search"></i>
                        Browse Internships
                    </a>
                    <a href="../applications/my-applications.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        My Applications
                    </a>
                    <a href="saved.php" class="nav-link">
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
                    <h1><i class="fas fa-search"></i> Browse Internships</h1>
                    <p>Discover opportunities that match your skills and interests</p>
                </div>
            </div>
        </div>

        <div class="content">
            <!-- Profile Warning -->
            <?php if (!$can_apply): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>
                        <strong>Profile Incomplete (<?php echo $profile_completion; ?>%):</strong> 
                        Complete your profile to apply for internships. 
                        <a href="../dashboard/profile.php" style="color: #92400e; text-decoration: underline; font-weight: 700;">
                            Complete Now →
                        </a>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <a href="?tab=current" class="tab-btn <?php echo $tab === 'current' ? 'active' : ''; ?>">
                    <div class="tab-icon">📝</div>
                    <div class="tab-label">Current</div>
                    <div class="tab-count"><?php echo $current_count; ?> opportunities</div>
                </a>
                <a href="?tab=active" class="tab-btn <?php echo $tab === 'active' ? 'active' : ''; ?>">
                    <div class="tab-icon">🏃</div>
                    <div class="tab-label">Active</div>
                    <div class="tab-count"><?php echo $active_count; ?> ongoing</div>
                </a>
                <a href="?tab=past" class="tab-btn <?php echo $tab === 'past' ? 'active' : ''; ?>">
                    <div class="tab-icon">📚</div>
                    <div class="tab-label">Past</div>
                    <div class="tab-count"><?php echo $past_count; ?> archived</div>
                </a>
            </div>

            <!-- Filters Card -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filter Internships
                </div>
                <form method="GET" action="browse.php">
                    <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <input type="text" name="search" class="filter-input" placeholder="Title, skills..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Location</label>
                            <input type="text" name="location" class="filter-input" placeholder="City, state..." value="<?php echo htmlspecialchars($location); ?>">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Type</label>
                            <select name="type" class="filter-input">
                                <option value="">All Types</option>
                                <option value="Internship" <?php echo $internship_type === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                <option value="Job" <?php echo $internship_type === 'Job' ? 'selected' : ''; ?>>Job</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Work Mode</label>
                            <select name="location_type" class="filter-input">
                                <option value="">All Modes</option>
                                <option value="On-site" <?php echo $location_type === 'On-site' ? 'selected' : ''; ?>>On-site</option>
                                <option value="Remote" <?php echo $location_type === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                                <option value="Hybrid" <?php echo $location_type === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Duration</label>
                            <select name="duration" class="filter-input">
                                <option value="">All Durations</option>
                                <option value="1 months" <?php echo $duration === '1 months' ? 'selected' : ''; ?>>1 month</option>
                                <option value="2 months" <?php echo $duration === '2 months' ? 'selected' : ''; ?>>2 months</option>
                                <option value="3 months" <?php echo $duration === '3 months' ? 'selected' : ''; ?>>3 months</option>
                                <option value="6 months" <?php echo $duration === '6 months' ? 'selected' : ''; ?>>6 months</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Company</label>
                            <input type="text" name="company" class="filter-input" placeholder="Company name..." value="<?php echo htmlspecialchars($company_filter); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="browse.php?tab=<?php echo $tab; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Internships Grid -->
            <?php if (count($internships) > 0): ?>
                <div class="internships-grid">
                    <?php foreach ($internships as $internship): 
                        $positions_left = $internship['positions_available'] - $internship['application_count'];
                        $is_full = $positions_left <= 0;
                        
                        // Determine badge
                        $badge_class = '';
                        $badge_text = '';
                        
                        if ($tab === 'current') {
                            if (strtotime($internship['start_date']) > strtotime('today')) {
                                $badge_class = 'badge-upcoming';
                                $badge_text = 'Upcoming';
                            } else {
                                $badge_class = 'badge-current';
                                $badge_text = 'Current';
                            }
                        } elseif ($tab === 'active') {
                            $badge_class = 'badge-active';
                            $badge_text = 'In Progress';
                        } else {
                            $badge_class = 'badge-past';
                            $badge_text = 'Completed';
                        }
                    ?>
                        <div class="internship-card">
                            <span class="card-badge <?php echo $badge_class; ?>">
                                <?php echo $badge_text; ?>
                            </span>

                            <div class="card-header">
                                <h3 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h3>
                                <div class="company-info">
                                    <i class="fas fa-building"></i>
                                    <span class="company-name"><?php echo htmlspecialchars($internship['company_name']); ?></span>
                                </div>
                            </div>

                            <div class="card-meta">
                                <span class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($internship['location']); ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-laptop-house"></i>
                                    <?php echo $internship['location_type']; ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo $internship['duration']; ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?php echo htmlspecialchars($internship['stipend']); ?>
                                </span>
                            </div>

                            <div class="card-skills">
                                <div class="skills-tags">
                                    <?php 
                                    $skills = explode(',', $internship['skills_required']);
                                    foreach (array_slice($skills, 0, 5) as $skill): 
                                    ?>
                                        <span class="skill-tag"><?php echo trim($skill); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($skills) > 5): ?>
                                        <span class="skill-tag">+<?php echo count($skills) - 5; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($tab === 'current'): ?>
                                <div class="deadline-info">
                                    <i class="fas fa-clock"></i>
                                    Apply by: <?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?>
                                </div>
                            <?php elseif ($tab === 'active'): ?>
                                <div class="deadline-info deadline-passed">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Deadline passed: <?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="deadline-info">
                                    <i class="fas fa-check-circle"></i>
                                    Ended: <?php echo date('M d, Y', strtotime($internship['end_date'])); ?>
                                </div>
                            <?php endif; ?>

                            <div class="card-footer">
                                <div class="positions-info">
                                    <?php if ($tab === 'current'): ?>
                                        <?php if ($is_full): ?>
                                            <span class="positions-full">
                                                <i class="fas fa-times-circle"></i> Positions Filled
                                            </span>
                                        <?php else: ?>
                                            <strong><?php echo $positions_left; ?></strong> / <?php echo $internship['positions_available']; ?> left
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php echo $internship['positions_available']; ?> positions
                                    <?php endif; ?>
                                </div>
                                <div class="card-actions">
                                    <?php if ($tab === 'current'): ?>
                                        <button onclick="toggleSave(<?php echo $internship['id']; ?>, this)" 
                                                class="btn btn-sm btn-save <?php echo $internship['is_saved'] > 0 ? 'saved' : ''; ?>"
                                                title="<?php echo $internship['is_saved'] > 0 ? 'Saved' : 'Save'; ?>">
                                            <i class="fas fa-bookmark"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="view-details.php?id=<?php echo $internship['id']; ?>" class="btn btn-sm btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($tab === 'current' && !$is_full && $internship['has_applied'] == 0): ?>
                                        <?php if ($can_apply): ?>
                                            <a href="../applications/apply.php?id=<?php echo $internship['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-paper-plane"></i> Apply
                                            </a>
                                        <?php else: ?>
                                            <a href="../dashboard/profile.php" class="btn btn-sm btn-warning" title="Complete your profile to apply">
                                                <i class="fas fa-exclamation-triangle"></i> Complete Profile
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Internships Found</h3>
                    <p>No internships match your criteria in this category. Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSave(internshipId, button) {
            const isSaved = button.classList.contains('saved');
            const action = isSaved ? 'unsave' : 'save';

            fetch('save-internship.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&internship_id=${internshipId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.classList.toggle('saved');
                    button.title = isSaved ? 'Save' : 'Saved';
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
