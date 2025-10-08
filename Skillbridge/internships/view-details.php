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

$can_apply = canApplyForInternships($user_id, $conn);

// Get profile completion
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile_data = $stmt->get_result()->fetch_assoc();

$profile_completion = calculateProfileCompletion($user_data, $profile_data);
// Fetch internship details
$query = "
    SELECT 
        i.*,
        c.name as company_name,
        c.trust_level,
        c.logo_path,
        c.industry,
        c.website,
        c.company_size,
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

$can_apply = canApplyForInternships($user_id, $conn);

// Get profile completion
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile_data = $stmt->get_result()->fetch_assoc();

$profile_completion = calculateProfileCompletion($user_data, $profile_data);

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

        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
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

        .company-info {
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
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .trust-badge.new { background: #dbeafe; color: #1e40af; }
        .trust-badge.verified { background: #d1fae5; color: #065f46; }
        .trust-badge.trusted { background: #fef3c7; color: #92400e; }

        .header-actions {
            display: flex;
            gap: 0.75rem;
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

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
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

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .meta-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-value i {
            color: #667eea;
        }

        /* Content Sections */
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.25rem;
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

        .section-content p {
            margin-bottom: 1rem;
        }

        .section-content ul,
        .section-content ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .section-content li {
            margin-bottom: 0.5rem;
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

        .company-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .detail-item {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 0.95rem;
            color: #1e293b;
            font-weight: 600;
        }

        /* Sticky Apply Bar */
        .sticky-apply-bar {
            position: fixed;
            bottom: 0;
            left: 280px;
            right: 0;
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        .positions-info {
            font-size: 1rem;
            color: #64748b;
        }

        .positions-info strong {
            color: #1e293b;
            font-size: 1.25rem;
        }

        .positions-full {
            color: #ef4444;
            font-weight: 700;
        }

        @media (max-width: 1024px) {
            .meta-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .company-details-grid {
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
            <a href="browse.php" class="back-btn" title="Back to Browse">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="header-info">
                <h1>Internship Details</h1>
                <p>Review all information before applying</p>
            </div>
        </div>

        <div class="content">
            <?php if (!$can_apply): ?>
            <div class="alert" style="background: #fef3c7; border-left: 4px solid #f59e0b; color: #92400e; padding: 1rem 1.5rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
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
            <?php if ($internship['has_applied'] > 0): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Already Applied!</strong> You have already submitted your application for this internship.</span>
                </div>
            <?php endif; ?>

            <?php if ($is_full): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><strong>All Positions Filled!</strong> This internship is no longer accepting applications.</span>
                </div>
            <?php endif; ?>

            <!-- Internship Header -->
            <div class="internship-header">
                <div class="header-top">
                    <div>
                        <h1 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h1>
                        <div class="company-info">
                            <span class="company-name">
                                <i class="fas fa-building"></i>
                                <?php echo htmlspecialchars($internship['company_name']); ?>
                            </span>
                            <span class="trust-badge <?php echo $internship['trust_level']; ?>">
                                <?php echo ucfirst($internship['trust_level']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button onclick="toggleSave(<?php echo $internship['id']; ?>, this)" 
                                class="btn btn-save <?php echo $internship['is_saved'] > 0 ? 'saved' : ''; ?>">
                            <i class="fas fa-bookmark"></i>
                            <?php echo $internship['is_saved'] > 0 ? 'Saved' : 'Save'; ?>
                        </button>
                          <?php if (!$is_full && $internship['has_applied'] == 0): ?>
                            <?php if ($can_apply): ?>
                                <a href="../applications/apply.php?id=<?php echo $internship['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </a>
                            <?php else: ?>
                                <a href="../dashboard/profile.php" class="btn" style="background: #fef3c7; color: #92400e;">
                                    <i class="fas fa-exclamation-triangle"></i> Complete Profile First
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Location</span>
                        <span class="meta-value">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($internship['location']); ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Work Mode</span>
                        <span class="meta-value">
                            <i class="fas fa-laptop-house"></i>
                            <?php echo $internship['location_type']; ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Duration</span>
                        <span class="meta-value">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo $internship['duration']; ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Stipend</span>
                        <span class="meta-value">
                            <i class="fas fa-money-bill-wave"></i>
                            <?php echo htmlspecialchars($internship['stipend']); ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Type</span>
                        <span class="meta-value">
                            <i class="fas fa-briefcase"></i>
                            <?php echo $internship['internship_type']; ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Positions</span>
                        <span class="meta-value">
                            <i class="fas fa-users"></i>
                            <?php echo $internship['positions_available']; ?> openings
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Deadline</span>
                        <span class="meta-value">
                            <i class="fas fa-clock"></i>
                            <?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Posted</span>
                        <span class="meta-value">
                            <i class="fas fa-calendar-plus"></i>
                            <?php echo date('M d, Y', strtotime($internship['created_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-align-left"></i>
                    About the Internship
                </h2>
                <div class="section-content">
                    <?php echo nl2br(htmlspecialchars($internship['description'])); ?>
                </div>
            </div>

            <!-- Requirements -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-clipboard-check"></i>
                    Requirements & Eligibility
                </h2>
                <div class="section-content">
                    <?php echo nl2br(htmlspecialchars($internship['requirements'])); ?>
                </div>
            </div>

            <!-- Skills Required -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-tools"></i>
                    Skills Required
                </h2>
                <div class="skills-grid">
                    <?php 
                    $skills = explode(',', $internship['skills_required']);
                    foreach ($skills as $skill): 
                    ?>
                        <span class="skill-tag"><?php echo trim($skill); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Company Details -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-building"></i>
                    About <?php echo htmlspecialchars($internship['company_name']); ?>
                </h2>
                <div class="company-details-grid">
                    <?php if (!empty($internship['industry'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Industry</div>
                        <div class="detail-value"><?php echo htmlspecialchars($internship['industry']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($internship['company_size'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Company Size</div>
                        <div class="detail-value"><?php echo htmlspecialchars($internship['company_size']); ?> employees</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($internship['website'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Website</div>
                        <div class="detail-value">
                            <a href="<?php echo htmlspecialchars($internship['website']); ?>" target="_blank" style="color: #667eea; text-decoration: none;">
                                Visit Website <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="height: 100px;"></div>
        </div>
    </div>

    <!-- Sticky Apply Bar -->
    <?php if (!$is_full && $internship['has_applied'] == 0): ?>
    <div class="sticky-apply-bar">
        <div class="positions-info">
            <strong><?php echo $positions_left; ?></strong> / <?php echo $internship['positions_available']; ?> positions remaining
        </div>
        <?php if ($can_apply): ?>
        <a href="../applications/apply.php?id=<?php echo $internship['id']; ?>" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1rem;">
            <i class="fas fa-paper-plane"></i> Apply for this Internship
        </a>
    <?php else: ?>
        <a href="../dashboard/profile.php" class="btn" style="background: #fef3c7; color: #92400e; padding: 1rem 2rem; font-size: 1rem;">
            <i class="fas fa-exclamation-triangle"></i> Complete Profile to Apply (<?php echo $profile_completion; ?>%)
        </a>
    <?php endif; ?>
    </div>
    <?php endif; ?>

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
