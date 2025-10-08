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

// Handle unsave action
if (isset($_GET['unsave'])) {
    $unsave_id = (int)$_GET['unsave'];
    $stmt = $conn->prepare("DELETE FROM saved_internships WHERE student_id = ? AND internship_id = ?");
    $stmt->bind_param("ii", $user_id, $unsave_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Internship removed from saved list.';
    }
    header('Location: saved.php');
    exit();
}

// Fetch all saved internships
$query = "
    SELECT 
        i.*,
        c.name as company_name,
        c.trust_level,
        c.logo_path,
        si.saved_at,
        (SELECT COUNT(*) FROM applications WHERE internship_id = i.id) as application_count,
        (SELECT COUNT(*) FROM applications WHERE internship_id = i.id AND student_id = ?) as has_applied
    FROM saved_internships si
    JOIN internships i ON si.internship_id = i.id
    JOIN companies c ON i.company_id = c.user_id
    WHERE si.student_id = ?
    ORDER BY si.saved_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$saved_internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ FIXED: Statistics calculation
$total_saved = count($saved_internships);
$active_count = 0;
$expired_count = 0;

foreach ($saved_internships as $internship) {
    // ✅ Active: status is approved/active AND deadline not passed
    if (in_array($internship['status'], ['approved', 'active']) && 
        strtotime($internship['application_deadline']) >= strtotime('today')) {
        $active_count++;
    } else {
        $expired_count++;
    }
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

// Get user initials
$user_initials = strtoupper(substr($full_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Internships - SkillBridge</title>
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
            grid-template-columns: repeat(3, 1fr);
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
        .stat-card.active { border-color: #10b981; }
        .stat-card.expired { border-color: #ef4444; }

        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .stat-card.total h3 { color: #8b5cf6; }
        .stat-card.active h3 { color: #10b981; }
        .stat-card.expired h3 { color: #ef4444; }

        .stat-card p {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .internship-card.expired {
            opacity: 0.7;
            background: #f8fafc;
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

        .saved-date {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .btn-remove {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-remove:hover {
            background: #fecaca;
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
                    <a href="browse.php" class="nav-link">
                        <i class="fas fa-search"></i>
                        Browse Internships
                    </a>
                    <a href="../applications/my-applications.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        My Applications
                    </a>
                    <a href="saved.php" class="nav-link active">
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
                    <h1><i class="fas fa-bookmark"></i> Saved Internships</h1>
                    <p>Your bookmarked opportunities for quick access</p>
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
                    <h3><?php echo $total_saved; ?></h3>
                    <p>Total Saved</p>
                </div>
                <div class="stat-card active">
                    <h3><?php echo $active_count; ?></h3>
                    <p>Still Active</p>
                </div>
                <div class="stat-card expired">
                    <h3><?php echo $expired_count; ?></h3>
                    <p>Expired/Closed</p>
                </div>
            </div>

            <!-- Saved Internships Grid -->
            <?php if (count($saved_internships) > 0): ?>
                <div class="internships-grid">
                    <?php foreach ($saved_internships as $internship): 
                        $positions_left = $internship['positions_available'] - $internship['application_count'];
                        $is_full = $positions_left <= 0;
                        // ✅ FIXED: Check deadline AND status (approved/active are valid)
                        $is_expired = strtotime($internship['application_deadline']) < strtotime('today') || 
                                      !in_array($internship['status'], ['approved', 'active']);
                    ?>
                        <div class="internship-card <?php echo $is_expired ? 'expired' : ''; ?>">
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

                            <div class="saved-date">
                                <i class="fas fa-bookmark"></i>
                                Saved on <?php echo date('M d, Y', strtotime($internship['saved_at'])); ?>
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

                            <div class="card-footer">
                                <?php if ($is_expired): ?>
                                    <span class="status-badge status-expired">
                                        <i class="fas fa-times-circle"></i> Expired
                                    </span>
                                <?php elseif ($is_full): ?>
                                    <span class="status-badge status-expired">
                                        <i class="fas fa-times-circle"></i> Positions Filled
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-active">
                                        <i class="fas fa-check-circle"></i> Active
                                    </span>
                                <?php endif; ?>

                                <div class="card-actions">
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
                                    <a href="?unsave=<?php echo $internship['id']; ?>" class="btn btn-sm btn-remove" onclick="return confirm('Remove this internship from saved list?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bookmark"></i>
                    <h3>No Saved Internships</h3>
                    <p>You haven't saved any internships yet. Start browsing to bookmark opportunities you're interested in!</p>
                    <a href="browse.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Internships
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
