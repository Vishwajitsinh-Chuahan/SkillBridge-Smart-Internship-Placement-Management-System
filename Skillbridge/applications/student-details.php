<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Company') {
    header('Location: ../auth/login.php');
    exit();
}

$company_id = $_SESSION['user_id'];
$company_name = $_SESSION['full_name'] ?? 'Company';

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$application_id = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;

if ($student_id <= 0) {
    $_SESSION['error'] = 'Invalid student ID.';
    header('Location: view-applications.php');
    exit();
}

// Fetch student details with application info
$query = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at,
        sp.university,
        sp.course,
        sp.graduation_year,
        sp.cgpa,
        sp.year_of_study,
        sp.skills,
        sp.bio,
        sp.github,
        sp.linkedin,
        sp.resume_path,
        a.id as application_id,
        a.status as application_status,
        a.applied_at,
        i.title as internship_title,
        i.location,
        i.duration,
        i.stipend,
        i.location_type
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN applications a ON u.id = a.student_id AND a.id = ?
    LEFT JOIN internships i ON a.internship_id = i.id
    WHERE u.id = ? AND u.role_id = 2
";


$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $application_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Student not found.';
    header('Location: view-applications.php');
    exit();
}

$student = $result->fetch_assoc();

// If application_id is provided, verify it belongs to this company
if ($application_id > 0) {
    $verify_query = "
        SELECT i.company_id 
        FROM applications a 
        JOIN internships i ON a.internship_id = i.id 
        WHERE a.id = ?
    ";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $verify_result = $stmt->get_result();
    
    if ($verify_result->num_rows === 0 || $verify_result->fetch_assoc()['company_id'] != $company_id) {
        $_SESSION['error'] = 'Unauthorized access.';
        header('Location: view-applications.php');
        exit();
    }
}

// Get all applications from this student to this company's internships
$all_apps_query = "
    SELECT 
        a.id,
        a.status,
        a.applied_at,
        i.title,
        i.location
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE a.student_id = ? AND i.company_id = ?
    ORDER BY a.applied_at DESC
";

$stmt = $conn->prepare($all_apps_query);
$stmt->bind_param("ii", $student_id, $company_id);
$stmt->execute();
$all_applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Status badge helper
function getStatusBadge($status) {
    $badges = [
        'pending' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'text' => 'PENDING'],
        'reviewed' => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'text' => 'REVIEWED'],
        'shortlisted' => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'text' => 'SHORTLISTED'],
        'selected' => ['color' => '#059669', 'bg' => '#d1fae5', 'text' => 'SELECTED'],
        'rejected' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'text' => 'REJECTED']
    ];
    return $badges[$status] ?? $badges['pending'];
}

// Get user initials
$user_initials = strtoupper(substr($company_name, 0, 2));
$student_initials = strtoupper(substr($student['full_name'], 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['full_name']); ?> - SkillBridge</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 245px;
            height: 100vh;
            background: #3d4b5c;
            color: #fff;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }

        .logo {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo i {
            color: #4a90e2;
            font-size: 1.75rem;
        }

        .logo h2 {
            font-size: 1.4rem;
            font-weight: 600;
        }

        .nav-menu {
            padding: 0.5rem 0;
        }

        .nav-section {
            margin-bottom: 0.5rem;
        }

        .nav-section-title {
            padding: 1rem 1.25rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.85rem 1.25rem;
            color: #e5e7eb;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(74, 144, 226, 0.1);
            color: #fff;
        }

        .nav-link.active {
            background: #4a90e2;
            color: #fff;
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.85rem;
            font-size: 1rem;
        }

        .user-profile {
            padding: 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #4a90e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .user-details h4 {
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .user-details p {
            color: #9ca3af;
            font-size: 0.75rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 245px;
            min-height: 100vh;
            background: #f5f5f5;
        }

        .page-header {
            background: #fff;
            padding: 1.5rem 2.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-btn {
            padding: 0.65rem;
            background: #f3f4f6;
            color: #6b7280;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
        }

        .back-btn:hover {
            background: #e5e7eb;
        }

        .page-title h1 {
            font-size: 1.75rem;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .page-title p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .content-wrapper {
            padding: 2.5rem;
        }

        /* Profile Card */
        .profile-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-header {
            background: linear-gradient(135deg, #4a90e2, #357abd);
            padding: 2.5rem;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a90e2;
            font-weight: 700;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .profile-name {
            font-size: 1.75rem;
            color: #fff;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .profile-email {
            color: rgba(255,255,255,0.9);
            font-size: 1rem;
        }

        .profile-body {
            padding: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .info-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .info-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 600;
        }

        .info-value a {
            color: #4a90e2;
            text-decoration: none;
        }

        .info-value a:hover {
            text-decoration: underline;
        }

        .section-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 2rem 0;
        }

        .section-title {
            font-size: 1.15rem;
            color: #1f2937;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #4a90e2;
        }

        /* Bio Section */
        .bio-content {
            color: #4b5563;
            line-height: 1.8;
            font-size: 0.95rem;
            background: #f9fafb;
            padding: 1.25rem;
            border-radius: 6px;
            border-left: 3px solid #4a90e2;
        }

        /* Skills */
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .skill-tag {
            padding: 0.5rem 1rem;
            background: #f3f4f6;
            color: #374151;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #e5e7eb;
        }

        /* Action Buttons */
        .actions-bar {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #4a90e2;
            color: white;
        }

        .btn-primary:hover {
            background: #3a7bc8;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Application Info Card */
        .app-info-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .app-info-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .app-info-title {
            font-size: 1.15rem;
            color: #1f2937;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .app-info-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .app-info-meta span {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        /* All Applications Table */
        .applications-section {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 2rem;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f9fafb;
        }

        th {
            padding: 0.85rem 1rem;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #e5e7eb;
        }

        tbody tr {
            border-bottom: 1px solid #f3f4f6;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        td {
            padding: 0.85rem 1rem;
            font-size: 0.9rem;
            color: #374151;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2>SkillBridge</h2>
        </div>

        <nav class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <a href="../dashboard/company.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    Dashboard
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Internships</div>
                <a href="../internships/create.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    Post New Internship
                </a>
                <a href="../internships/manage.php" class="nav-link">
                    <i class="fas fa-list"></i>
                    Manage Internships
                </a>
                <a href="view-applications.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    View Applications
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
        <div class="page-header">
            <a href="view-applications.php" class="back-btn" title="Back to Applications">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="page-title">
                <h1>Student Profile</h1>
                <p>Detailed information about the applicant</p>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- Current Application Info (if viewing from specific application) -->
            <?php if ($application_id > 0 && $student['application_id']): 
                $badge = getStatusBadge($student['application_status']);
            ?>
                <div class="app-info-card">
                    <div class="app-info-header">
                        <div>
                            <div class="app-info-title"><?php echo htmlspecialchars($student['internship_title']); ?></div>
                            <div class="app-info-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($student['location']); ?></span>
                                <span><i class="fas fa-laptop-house"></i> <?php echo $student['location_type']; ?></span>
                                <span><i class="fas fa-calendar-alt"></i> <?php echo $student['duration']; ?></span>
                                <span><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($student['stipend']); ?></span>
                            </div>
                        </div>
                        <span class="status-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                            <?php echo $badge['text']; ?>
                        </span>
                    </div>
                    <div style="color: #6b7280; font-size: 0.9rem;">
                        <i class="fas fa-clock"></i> Applied on <?php echo date('M d, Y \a\t g:i A', strtotime($student['applied_at'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar"><?php echo $student_initials; ?></div>
                    <div class="profile-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($student['email']); ?></div>
                </div>

                <div class="profile-body">
                    <!-- Basic Information -->
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Member Since</div>
                            <div class="info-value"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></div>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- Education Information -->
                    <div class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Education
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">University</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['university'] ?? 'Not specified'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Course / Degree</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['course'] ?? 'Not specified'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Graduation Year</div>
                            <div class="info-value"><?php echo $student['graduation_year'] ?? 'Not specified'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">CGPA</div>
                            <div class="info-value"><?php echo $student['cgpa'] ? number_format($student['cgpa'], 2) : 'Not specified'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Current Year</div>
                            <div class="info-value"><?php echo $student['year_of_study'] ?? 'Not specified'; ?></div>
                        </div>
                    </div>

                    <?php if (!empty($student['bio'])): ?>
                    <div class="section-divider"></div>

                    <!-- Bio Section -->
                    <div class="section-title">
                        <i class="fas fa-user-circle"></i>
                        About Me
                    </div>

                    <div class="bio-content">
                        <?php echo nl2br(htmlspecialchars($student['bio'])); ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($student['skills'])): ?>
                    <div class="section-divider"></div>

                    <!-- Skills -->
                    <div class="section-title">
                        <i class="fas fa-code"></i>
                        Skills
                    </div>

                    <div class="skills-container">
                        <?php 
                        $skills = explode(',', $student['skills']);
                        foreach ($skills as $skill): 
                        ?>
                            <span class="skill-tag"><?php echo trim($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="section-divider"></div>

                    <!-- Links -->
                    <div class="section-title">
                        <i class="fas fa-link"></i>
                        Links & Resources
                    </div>

                    <div class="info-grid">
                        <?php if (!empty($student['github'])): ?>
                        <div class="info-item">
                            <div class="info-label">GitHub</div>
                            <div class="info-value">
                                <a href="<?php echo htmlspecialchars($student['github']); ?>" target="_blank">
                                    <i class="fab fa-github"></i> View Profile
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($student['linkedin'])): ?>
                        <div class="info-item">
                            <div class="info-label">LinkedIn</div>
                            <div class="info-value">
                                <a href="<?php echo htmlspecialchars($student['linkedin']); ?>" target="_blank">
                                    <i class="fab fa-linkedin"></i> View Profile
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- <?php if (!empty($student['resume_path'])): ?>
                        <div class="info-item">
                            <div class="info-label">Resume</div>
                            <div class="info-value">
                                <a href="../uploads/resumes/<?php echo htmlspecialchars($student['resume_path']); ?>" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Download Resume
                                </a>
                            </div>
                        </div>
                        <?php endif; ?> -->
                    </div>

                    <!-- Action Buttons -->
                    <div class="actions-bar">
                        <?php if (!empty($student['resume_path'])): ?>
                            <a href="../uploads/resumes/<?php echo htmlspecialchars($student['resume_path']); ?>" 
                               target="_blank" 
                               class="btn btn-success">
                                <i class="fas fa-download"></i>
                                Download Resume
                            </a>
                        <?php endif; ?>
                        <a href="view-applications.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Applications
                        </a>
                    </div>
                </div>
            </div>

            <!-- All Applications from this Student -->
            <?php if (count($all_applications) > 0): ?>
            <div class="applications-section">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    All Applications from this Student
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Internship Title</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_applications as $app): 
                                $badge = getStatusBadge($app['status']);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['title']); ?></td>
                                    <td><?php echo htmlspecialchars($app['location']); ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                                            <?php echo $badge['text']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
