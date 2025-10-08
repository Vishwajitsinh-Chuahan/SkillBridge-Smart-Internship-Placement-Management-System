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

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$location = trim($_GET['location'] ?? '');
$internship_type = $_GET['type'] ?? '';
$location_type = $_GET['location_type'] ?? '';
$duration = $_GET['duration'] ?? '';
$company_filter = $_GET['company'] ?? '';

// Build query with filters
$query = "
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
    AND i.application_deadline >= CURDATE()
";

$params = [$user_id, $user_id];
$types = 'ii';

// Apply search filter
if (!empty($search)) {
    $query .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.skills_required LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Apply location filter
if (!empty($location)) {
    $query .= " AND i.location LIKE ?";
    $params[] = "%{$location}%";
    $types .= 's';
}

// Apply internship type filter
if (!empty($internship_type)) {
    $query .= " AND i.internship_type = ?";
    $params[] = $internship_type;
    $types .= 's';
}

// Apply location type filter
if (!empty($location_type)) {
    $query .= " AND i.location_type = ?";
    $params[] = $location_type;
    $types .= 's';
}

// Apply duration filter
if (!empty($duration)) {
    $query .= " AND i.duration LIKE ?";
    $params[] = "%{$duration}%";
    $types .= 's';
}

// Apply company filter
if (!empty($company_filter)) {
    $query .= " AND c.name LIKE ?";
    $params[] = "%{$company_filter}%";
    $types .= 's';
}

$query .= " ORDER BY i.created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_active = count($internships);

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

        /* Filters Card */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .filters-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
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

        .filter-actions {
            display: flex;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
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

        /* Stats Bar */
        .stats-bar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats-bar h3 {
            color: #1e293b;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stats-bar h3 i {
            color: #667eea;
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

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .internship-title {
            font-size: 1.25rem;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .company-name {
            color: #667eea;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .trust-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .trust-badge.new { background: #dbeafe; color: #1e40af; }
        .trust-badge.verified { background: #d1fae5; color: #065f46; }
        .trust-badge.trusted { background: #fef3c7; color: #92400e; }

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

        .card-description {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
            font-size: 0.85rem;
            color: #64748b;
        }

        .positions-info strong {
            color: #1e293b;
        }

        .positions-full {
            color: #ef4444;
            font-weight: 700;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-save {
            background: #f1f5f9;
            color: #475569;
            border: none;
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

        .applied-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
                    <h1><i class="fas fa-search"></i> Browse Internships</h1>
                    <p>Discover opportunities that match your skills and interests</p>
                </div>
            </div>
        </div>

        <div class="content">
             <?php if (!$can_apply): ?>
            <div class="alert" style="background: #fef3c7; border-left: 4px solid #f59e0b; color: #92400e; margin-bottom: 2rem; padding: 1rem 1.5rem; border-radius: 10px; display: flex; align-items: center; gap: 0.75rem;">
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
            <!-- Filters Card -->
            <div class="filters-card">
                <div class="filters-header">
                    <i class="fas fa-filter"></i>
                    Filter Internships
                </div>
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Search Keywords</label>
                            <input type="text" name="search" class="filter-input" placeholder="Job title, skills..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-map-marker-alt"></i> Location</label>
                            <input type="text" name="location" class="filter-input" placeholder="City, state..." value="<?php echo htmlspecialchars($location); ?>">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-briefcase"></i> Type</label>
                            <select name="type" class="filter-input">
                                <option value="">All Types</option>
                                <option value="Internship" <?php echo $internship_type === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                <option value="Job" <?php echo $internship_type === 'Job' ? 'selected' : ''; ?>>Job</option>
                                <option value="Both" <?php echo $internship_type === 'Both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-laptop-house"></i> Work Mode</label>
                            <select name="location_type" class="filter-input">
                                <option value="">All Modes</option>
                                <option value="On-site" <?php echo $location_type === 'On-site' ? 'selected' : ''; ?>>On-site</option>
                                <option value="Remote" <?php echo $location_type === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                                <option value="Hybrid" <?php echo $location_type === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Duration</label>
                            <select name="duration" class="filter-input">
                                <option value="">All Durations</option>
                                <option value="1" <?php echo $duration === '1' ? 'selected' : ''; ?>>1 month</option>
                                <option value="2" <?php echo $duration === '2' ? 'selected' : ''; ?>>2 months</option>
                                <option value="3" <?php echo $duration === '3' ? 'selected' : ''; ?>>3 months</option>
                                <option value="6" <?php echo $duration === '6' ? 'selected' : ''; ?>>6 months</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-building"></i> Company</label>
                            <input type="text" name="company" class="filter-input" placeholder="Company name..." value="<?php echo htmlspecialchars($company_filter); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="browse.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Clear All
                        </a>
                    </div>
                </form>
            </div>

            <!-- Stats Bar -->
            <div class="stats-bar">
                <h3>
                    <i class="fas fa-briefcase"></i>
                    <?php echo $total_active; ?> Active Internship<?php echo $total_active !== 1 ? 's' : ''; ?> Found
                </h3>
            </div>

            <!-- Internships Grid -->
            <?php if (count($internships) > 0): ?>
                <div class="internships-grid">
                    <?php foreach ($internships as $internship): 
                        $positions_left = $internship['positions_available'] - $internship['application_count'];
                        $is_full = $positions_left <= 0;
                    ?>
                        <div class="internship-card">
                            <?php if ($internship['has_applied'] > 0): ?>
                                <div class="applied-badge">
                                    <i class="fas fa-check-circle"></i> Applied
                                </div>
                            <?php endif; ?>

                            <div class="card-header">
                                <div style="flex: 1;">
                                    <h3 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h3>
                                    <p class="company-name">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($internship['company_name']); ?>
                                    </p>
                                </div>
                                <span class="trust-badge <?php echo $internship['trust_level']; ?>">
                                    <?php echo ucfirst($internship['trust_level']); ?>
                                </span>
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

                            <div class="card-description">
                                <?php echo htmlspecialchars(substr($internship['description'], 0, 150)) . '...'; ?>
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

                            <div class="card-footer">
                                <div class="positions-info">
                                    <?php if ($is_full): ?>
                                        <span class="positions-full">
                                            <i class="fas fa-times-circle"></i> Positions Filled
                                        </span>
                                    <?php else: ?>
                                        <strong><?php echo $positions_left; ?></strong> / <?php echo $internship['positions_available']; ?> left
                                    <?php endif; ?>
                                </div>
                                <div class="card-actions">
                                    <button onclick="toggleSave(<?php echo $internship['id']; ?>, this)" 
                                            class="btn btn-sm btn-save <?php echo $internship['is_saved'] > 0 ? 'saved' : ''; ?>"
                                            title="<?php echo $internship['is_saved'] > 0 ? 'Saved' : 'Save'; ?>">
                                        <i class="fas fa-bookmark"></i>
                                    </button>
                                    <a href="view-details.php?id=<?php echo $internship['id']; ?>" class="btn btn-sm btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if (!$is_full && $internship['has_applied'] == 0): ?>
                                        <?php if ($can_apply): ?>
                                            <a href="../applications/apply.php?id=<?php echo $internship['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-paper-plane"></i> Apply
                                            </a>
                                        <?php else: ?>
                                            <a href="../dashboard/profile.php" class="btn btn-sm" style="background: #fef3c7; color: #92400e;" title="Complete your profile to apply">
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
                    <p>Try adjusting your search filters to see more results</p>
                    <a href="browse.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
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
