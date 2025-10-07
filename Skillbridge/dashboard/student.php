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

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch student profile
$stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile = $profile_result->num_rows > 0 ? $profile_result->fetch_assoc() : null;

// ✅ Calculate Dynamic Profile Completion
function calculateProfileCompletion($user, $profile) {
    $fields = [
        'full_name' => !empty($user['full_name']),
        'email' => !empty($user['email']),
        'phone' => !empty($user['phone']),
        'university' => !empty($profile['university']),
        'course' => !empty($profile['course']),
        'graduation_year' => !empty($profile['graduation_year']),
        'cgpa' => !empty($profile['cgpa']),
        'year_of_study' => !empty($profile['year_of_study']),
        'skills' => !empty($profile['skills']),
        'bio' => !empty($profile['bio']),
        'github' => !empty($profile['github']),
        'linkedin' => !empty($profile['linkedin']),
        'resume_path' => !empty($profile['resume_path'])
    ];
    
    $completed = count(array_filter($fields));
    $total = count($fields);
    
    return round(($completed / $total) * 100);
}

$profile_completion = $profile ? calculateProfileCompletion($user, $profile) : 0;

// Get statistics
$stats = [];

// Total applications
$stmt = $conn->query("SELECT COUNT(*) as count FROM applications WHERE student_id = $user_id");
$stats['applications'] = $stmt->fetch_assoc()['count'];

// Shortlisted applications
$stmt = $conn->query("SELECT COUNT(*) as count FROM applications WHERE student_id = $user_id AND status = 'shortlisted'");
$stats['shortlisted'] = $stmt->fetch_assoc()['count'];

// Interview scheduled
$stmt = $conn->query("SELECT COUNT(*) as count FROM applications WHERE student_id = $user_id AND status = 'interview'");
$stats['interviews'] = $stmt->fetch_assoc()['count'];

// Saved internships (if you have a saved_internships table)
$stats['saved'] = 0;

// Recent applications
$recent_applications = $conn->query("
    SELECT 
        a.*,
        i.title as internship_title,
        c.name as company_name
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    JOIN companies c ON i.company_id = c.user_id
    WHERE a.student_id = $user_id
    ORDER BY a.applied_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recommended internships (active/approved internships)
$recommended_internships = $conn->query("
    SELECT 
        i.*,
        c.name as company_name,
        c.logo_path
    FROM internships i
    JOIN companies c ON i.company_id = c.user_id
    WHERE i.status IN ('approved', 'active')
    AND i.application_deadline >= CURDATE()
    ORDER BY i.created_at DESC
    LIMIT 2
")->fetch_all(MYSQLI_ASSOC);

// Get user initials
$user_initials = strtoupper(substr($full_name, 0, 2));

// Status badge colors
function getStatusBadge($status) {
    $badges = [
        'applied' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'text' => 'Applied'],
        'reviewed' => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'text' => 'Reviewed'],
        'shortlisted' => ['color' => '#10b981', 'bg' => '#d1fae5', 'text' => 'Shortlisted'],
        'interview' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'text' => 'Interview'],
        'hired' => ['color' => '#059669', 'bg' => '#d1fae5', 'text' => 'Hired'],
        'rejected' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'text' => 'Rejected']
    ];
    return $badges[$status] ?? $badges['applied'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SkillBridge</title>
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

        /* Sidebar - UNCHANGED */
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

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-size: 0.9rem;
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

        .content {
            padding: 2rem;
        }

        /* ✅ Dynamic Profile Completion Card */
        .profile-completion-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 16px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .completion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .completion-header h3 {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .completion-percent {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .progress-bar {
            height: 14px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: white;
            border-radius: 10px;
            transition: width 0.8s ease;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .completion-message {
            font-size: 0.95rem;
            opacity: 0.95;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .completion-message a {
            color: white;
            text-decoration: underline;
            font-weight: 600;
            margin-left: 0.5rem;
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

        .stat-card.applications { border-color: #3b82f6; }
        .stat-card.shortlisted { border-color: #10b981; }
        .stat-card.interviews { border-color: #f59e0b; }
        .stat-card.saved { border-color: #8b5cf6; }

        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .stat-card.applications h3 { color: #3b82f6; }
        .stat-card.shortlisted h3 { color: #10b981; }
        .stat-card.interviews h3 { color: #f59e0b; }
        .stat-card.saved h3 { color: #8b5cf6; }

        .stat-card p {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Section Card */
        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h3 {
            font-size: 1.25rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-header a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .section-header a:hover {
            color: #764ba2;
        }

        .section-body {
            padding: 1.5rem;
        }

        /* Application Item */
        .application-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .application-item:last-child {
            margin-bottom: 0;
        }

        .application-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
            border-color: #667eea;
        }

        .application-info h4 {
            color: #1e293b;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .application-info p {
            color: #64748b;
            font-size: 0.85rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Internship Card */
        .internships-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .internship-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.3s;
            background: white;
        }

        .internship-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transform: translateY(-4px);
            border-color: #667eea;
        }

        .internship-card h4 {
            color: #1e293b;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .internship-card .company {
            color: #667eea;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .internship-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            color: #64748b;
            font-size: 0.85rem;
        }

        .internship-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
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
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar - UNCHANGED -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="student.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                SkillBridge
            </a>
        </div>

        <div class="sidebar-scroll-container">
            <nav>
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="student.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="profile.php" class="nav-link">
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
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>! 👋</h1>
                    <p>Track your applications and discover new opportunities</p>
                </div>
                <div class="header-actions">
                    <a href="../internships/browse.php" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Browse Internships
                    </a>
                </div>
            </div>
        </div>

        <div class="content">
            <!-- ✅ Dynamic Profile Completion Card -->
            <div class="profile-completion-card">
                <div class="completion-header">
                    <h3>
                        <i class="fas fa-user-circle"></i>
                        Profile Completion
                    </h3>
                    <span class="completion-percent"><?php echo $profile_completion; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $profile_completion; ?>%;"></div>
                </div>
                <p class="completion-message">
                    <?php if ($profile_completion < 100): ?>
                        <i class="fas fa-info-circle"></i>
                        <span>
                            Complete your profile to get better internship recommendations and increase your chances!
                            <a href="profile.php">Complete Now →</a>
                        </span>
                    <?php else: ?>
                        <i class="fas fa-check-circle"></i>
                        <span>Excellent! Your profile is 100% complete. Keep it updated for best results.</span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card applications">
                    <h3><?php echo $stats['applications']; ?></h3>
                    <p>Total Applications</p>
                </div>
                <div class="stat-card shortlisted">
                    <h3><?php echo $stats['shortlisted']; ?></h3>
                    <p>Shortlisted</p>
                </div>
                <div class="stat-card interviews">
                    <h3><?php echo $stats['interviews']; ?></h3>
                    <p>Interviews</p>
                </div>
                <div class="stat-card saved">
                    <h3><?php echo $stats['saved']; ?></h3>
                    <p>Saved</p>
                </div>
            </div>

            <!-- Recent Applications -->
            <div class="section-card">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-history"></i>
                        Recent Applications
                    </h3>
                    <a href="../applications/my-applications.php">
                        View All →
                    </a>
                </div>
                <div class="section-body">
                    <?php if (!empty($recent_applications)): ?>
                        <?php foreach ($recent_applications as $app): 
                            $badge = getStatusBadge($app['status']);
                        ?>
                            <div class="application-item">
                                <div class="application-info">
                                    <h4><?php echo htmlspecialchars($app['internship_title']); ?></h4>
                                    <p><?php echo htmlspecialchars($app['company_name']); ?> • Applied on <?php echo date('M d, Y', strtotime($app['applied_at'])); ?></p>
                                </div>
                                <span class="status-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                                    <?php echo $badge['text']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Applications Yet</h3>
                            <p>Start applying to internships to see them here</p>
                            <a href="../internships/browse.php" class="btn btn-primary">
                                Browse Internships
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recommended Internships -->
            <div class="section-card">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-star"></i>
                        Recommended for You
                    </h3>
                    <a href="../internships/browse.php">
                        View All →
                    </a>
                </div>
                <div class="section-body">
                    <?php if (!empty($recommended_internships)): ?>
                        <div class="internships-grid">
                            <?php foreach (array_slice($recommended_internships, 0, 4) as $internship): ?>
                                <div class="internship-card">
                                    <h4><?php echo htmlspecialchars($internship['title']); ?></h4>
                                    <p class="company"><?php echo htmlspecialchars($internship['company_name']); ?></p>
                                    <div class="internship-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($internship['location']); ?></span>
                                        <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($internship['internship_type']); ?></span>
                                    </div>
                                    <a href="../internships/view.php?id=<?php echo $internship['id']; ?>" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                        View Details
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>No Internships Available</h3>
                            <p>Check back later for new opportunities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
