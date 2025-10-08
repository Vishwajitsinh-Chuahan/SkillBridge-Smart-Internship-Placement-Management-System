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
$internship_id = (int)($_GET['id'] ?? 0);

if ($internship_id <= 0) {
    header('Location: browse.php');
    exit();
}

// Fetch internship details with company info
$query = "
    SELECT 
        i.*,
        c.name as company_name,
        c.trust_level,
        c.logo_path,
        c.industry,
        c.website,
        c.company_size,
        c.description as company_description,
        c.address as company_address,
        c.founded_year,
        (SELECT COUNT(*) FROM applications WHERE internship_id = i.id) as application_count,
        (SELECT COUNT(*) FROM saved_internships WHERE internship_id = i.id AND student_id = ?) as is_saved,
        (SELECT COUNT(*) FROM applications WHERE internship_id = i.id AND student_id = ?) as has_applied
    FROM internships i
    JOIN companies c ON i.company_id = c.user_id
    WHERE i.id = ?
    AND i.status IN ('approved', 'active')
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $user_id, $internship_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Internship not found or no longer available.';
    header('Location: browse.php');
    exit();
}

$internship = $result->fetch_assoc();
$positions_left = $internship['positions_available'] - $internship['application_count'];
$is_full = $positions_left <= 0;

// Check if deadline passed and if internship is ongoing
$is_expired = strtotime($internship['application_deadline']) < strtotime('today') || 
              !in_array($internship['status'], ['approved', 'active']);

$is_ongoing = strtotime($internship['application_deadline']) < strtotime('today') && 
              strtotime($internship['start_date']) <= strtotime('today') &&
              (empty($internship['end_date']) || strtotime($internship['end_date']) >= strtotime('today'));

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

// Get trust level display
function getTrustLevelDisplay($trust_level) {
    $levels = [
        'new' => ['text' => 'Recently Joined', 'icon' => 'fa-star', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
        'verified' => ['text' => 'Verified Company', 'icon' => 'fa-shield-check', 'color' => '#10b981', 'bg' => '#d1fae5'],
        'trusted' => ['text' => 'Top Recruiter', 'icon' => 'fa-trophy', 'color' => '#f59e0b', 'bg' => '#fef3c7']
    ];
    return $levels[$trust_level] ?? $levels['new'];
}

$trust_display = getTrustLevelDisplay($internship['trust_level']);

// Get company statistics
$company_stats = $conn->query("
    SELECT 
        COUNT(*) as total_postings,
        (SELECT COUNT(*) FROM applications a JOIN internships i ON a.internship_id = i.id WHERE i.company_id = {$internship['company_id']} AND a.status = 'selected') as total_hires
    FROM internships 
    WHERE company_id = {$internship['company_id']}
")->fetch_assoc();

// Get user initials
$user_initials = strtoupper(substr($full_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($internship['title']); ?> - SkillBridge</title>
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
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-btn {
            padding: 0.75rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .back-btn:hover {
            background: #e2e8f0;
        }

        .header-info {
            flex: 1;
        }

        .header-info h1 {
            font-size: 1.75rem;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .header-info p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .content {
            padding: 2rem;
            max-width: 1200px;
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

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .alert-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }

        /* Internship Header Card */
        .internship-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
        }

        .internship-title {
            font-size: 2rem;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .company-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .company-name {
            color: #667eea;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .trust-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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

        .btn-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-warning:hover {
            background: #fde68a;
        }

        .btn-disabled {
            background: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
        }

        .header-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            padding: 1.5rem 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .meta-icon {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 1.1rem;
        }

        .meta-content {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
        }

        .meta-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 600;
        }

        /* Content Sections */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #667eea;
        }

        .section-content {
            color: #475569;
            line-height: 1.8;
            font-size: 0.95rem;
        }

        .skills-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .skill-tag {
            padding: 0.5rem 1rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .requirements-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .requirements-list li {
            display: flex;
            align-items: start;
            gap: 0.75rem;
            color: #475569;
            line-height: 1.6;
        }

        .requirements-list li i {
            color: #10b981;
            margin-top: 0.25rem;
        }

        /* Company Card */
        .company-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .company-card-title {
            font-size: 1.25rem;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .company-detail {
            display: flex;
            align-items: start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .company-detail:last-child {
            border-bottom: none;
        }

        .company-detail-icon {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            flex-shrink: 0;
        }

        .company-detail-content {
            flex: 1;
        }

        .company-detail-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .company-detail-value {
            font-size: 0.95rem;
            color: #1e293b;
            font-weight: 600;
        }

        .company-description {
            color: #475569;
            line-height: 1.8;
            font-size: 0.9rem;
            padding: 1rem 0;
            border-top: 1px solid #f1f5f9;
            margin-top: 1rem;
        }

        .company-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .content-grid {
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
            <a href="browse.php" class="back-btn" title="Back to Browse">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="header-info">
                <h1>Internship Details</h1>
                <p>View complete information about this opportunity</p>
            </div>
        </div>

        <div class="content">
            <!-- Profile Warning -->
            <?php if (!$can_apply): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>
                        <strong>Profile Incomplete (<?php echo $profile_completion; ?>%):</strong> 
                        You must complete your profile to apply for this internship. 
                        <a href="../dashboard/profile.php" style="color: #92400e; text-decoration: underline; font-weight: 700;">
                            Complete Profile →
                        </a>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Already Applied Alert -->
            <?php if ($internship['has_applied'] > 0): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><strong>You have already applied</strong> for this internship. Check your application status in My Applications.</span>
                </div>
            <?php endif; ?>

            <!-- Ongoing Internship Alert -->
            <?php if ($is_ongoing): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span><strong>This internship is currently in progress.</strong> The application deadline has passed, but you can view details for future reference.</span>
                </div>
            <?php endif; ?>

            <!-- Internship Header -->
            <div class="internship-header">
                <div class="header-top">
                    <div>
                        <h2 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h2>
                        <div class="company-info-header">
                            <div class="company-name">
                                <i class="fas fa-building"></i>
                                <?php echo htmlspecialchars($internship['company_name']); ?>
                            </div>
                            <span class="trust-badge" style="background: <?php echo $trust_display['bg']; ?>; color: <?php echo $trust_display['color']; ?>;">
                                <i class="fas <?php echo $trust_display['icon']; ?>"></i>
                                <?php echo $trust_display['text']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button onclick="toggleSave(<?php echo $internship['id']; ?>, this)" 
                                class="btn btn-save <?php echo $internship['is_saved'] > 0 ? 'saved' : ''; ?>">
                            <i class="fas fa-bookmark"></i>
                            <?php echo $internship['is_saved'] > 0 ? 'Saved' : 'Save'; ?>
                        </button>
                        
                        <?php if (!$is_full && !$is_expired && $internship['has_applied'] == 0): ?>
                            <?php if ($can_apply): ?>
                                <a href="../applications/apply.php?id=<?php echo $internship['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </a>
                            <?php else: ?>
                                <a href="../dashboard/profile.php" class="btn btn-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Complete Profile First
                                </a>
                            <?php endif; ?>
                        <?php elseif ($is_full): ?>
                            <button class="btn btn-disabled" disabled>
                                <i class="fas fa-times-circle"></i> Positions Filled
                            </button>
                        <?php elseif ($is_expired || $is_ongoing): ?>
                            <button class="btn btn-disabled" disabled>
                                <i class="fas fa-clock"></i> Application Closed
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="header-meta">
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Location</span>
                            <span class="meta-value"><?php echo htmlspecialchars($internship['location']); ?></span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-laptop-house"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Work Mode</span>
                            <span class="meta-value"><?php echo $internship['location_type']; ?></span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Duration</span>
                            <span class="meta-value"><?php echo $internship['duration']; ?></span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Stipend</span>
                            <span class="meta-value"><?php echo htmlspecialchars($internship['stipend']); ?></span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Positions</span>
                            <span class="meta-value"><?php echo $positions_left; ?> / <?php echo $internship['positions_available']; ?> left</span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Deadline</span>
                            <span class="meta-value"><?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?></span>
                        </div>
                    </div>

                     <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Start Date</span>
                            <span class="meta-value"><?php echo date('M d, Y', strtotime($internship['start_date'])); ?></span>
                        </div>
                    </div>
                     <div class="meta-item">
                        <div class="meta-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">End Date</span>
                            <span class="meta-value"><?php echo date('M d, Y', strtotime($internship['end_date'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Left Column -->
                <div>
                    <!-- Description -->
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Description
                        </h3>
                        <div class="section-content">
                            <?php echo nl2br(htmlspecialchars($internship['description'])); ?>
                        </div>
                    </div>

                    <!-- Skills Required -->
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-code"></i>
                            Skills Required
                        </h3>
                        <div class="skills-grid">
                            <?php 
                            $skills = explode(',', $internship['skills_required']);
                            foreach ($skills as $skill): 
                            ?>
                                <span class="skill-tag"><?php echo trim($skill); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Requirements -->
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-list-check"></i>
                            Requirements
                        </h3>
                        <ul class="requirements-list">
                            <?php 
                            $requirements = explode("\n", $internship['requirements']);
                            foreach ($requirements as $req): 
                                if (trim($req)):
                            ?>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span><?php echo htmlspecialchars($req); ?></span>
                                </li>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </ul>
                    </div>
                </div>

                <!-- Right Column - Company Info -->
                <div>
                    <div class="company-card">
                        <h3 class="company-card-title">
                            <i class="fas fa-building"></i>
                            About Company
                        </h3>

                        <div class="company-detail">
                            <div class="company-detail-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <div class="company-detail-content">
                                <div class="company-detail-label">Industry</div>
                                <div class="company-detail-value"><?php echo htmlspecialchars($internship['industry'] ?? 'Not specified'); ?></div>
                            </div>
                        </div>

                        <div class="company-detail">
                            <div class="company-detail-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="company-detail-content">
                                <div class="company-detail-label">Company Size</div>
                                <div class="company-detail-value"><?php echo htmlspecialchars($internship['company_size'] ?? 'Not specified'); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($internship['company_address'])): ?>
                        <div class="company-detail">
                            <div class="company-detail-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="company-detail-content">
                                <div class="company-detail-label">Address</div>
                                <div class="company-detail-value"><?php echo htmlspecialchars($internship['company_address']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($internship['founded_year'])): ?>
                        <div class="company-detail">
                            <div class="company-detail-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="company-detail-content">
                                <div class="company-detail-label">Founded</div>
                                <div class="company-detail-value"><?php echo $internship['founded_year']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($internship['website'])): ?>
                        <div class="company-detail">
                            <div class="company-detail-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div class="company-detail-content">
                                <div class="company-detail-label">Website</div>
                                <div class="company-detail-value">
                                    <a href="<?php echo htmlspecialchars($internship['website']); ?>" target="_blank" style="color: #667eea; text-decoration: none;">
                                        Visit Website <i class="fas fa-external-link-alt" style="font-size: 0.8rem;"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($internship['company_description'])): ?>
                        <div class="company-description">
                            <div class="company-detail-label" style="margin-bottom: 0.5rem;">About</div>
                            <?php echo nl2br(htmlspecialchars($internship['company_description'])); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Company Stats -->
                        <div class="company-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $company_stats['total_postings']; ?></div>
                                <div class="stat-label">Total Postings</div>
                            </div>
                            <!-- <div class="stat-item">
                                <div class="stat-value"><?php echo $company_stats['total_hires']; ?></div>
                                <div class="stat-label">Total Hires</div>
                            </div> -->
                        </div>
                    </div>
                </div>
            </div>
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
                    button.innerHTML = isSaved ? '<i class="fas fa-bookmark"></i> Save' : '<i class="fas fa-bookmark"></i> Saved';
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
