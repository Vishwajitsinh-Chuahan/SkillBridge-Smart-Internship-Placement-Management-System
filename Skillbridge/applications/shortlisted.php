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

// Get filter parameter
$internship_filter = isset($_GET['internship']) ? (int)$_GET['internship'] : 0;
$search = trim($_GET['search'] ?? '');

// Fetch company's internships for filter dropdown
$internships_query = "SELECT id, title FROM internships WHERE company_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($internships_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company_internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build query for shortlisted applications
$query = "
    SELECT 
        a.*,
        i.title as internship_title,
        i.location,
        i.duration,
        i.stipend,
        i.internship_type,
        u.full_name as student_name,
        u.email as student_email,
        u.phone as student_phone,
        sp.university,
        sp.course,
        sp.cgpa,
        sp.year_of_study,
        sp.graduation_year,
        sp.skills,
        sp.resume_path,
        sp.github,
        sp.linkedin
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    JOIN users u ON a.student_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE i.company_id = ? 
    AND a.status = 'shortlisted'
";

$params = [$company_id];
$types = 'i';

// Apply internship filter
if ($internship_filter > 0) {
    $query .= " AND i.id = ?";
    $params[] = $internship_filter;
    $types .= 'i';
}

// Apply search filter
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR sp.university LIKE ? OR sp.course LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

$query .= " ORDER BY a.updated_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$shortlisted = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_shortlisted,
        COUNT(DISTINCT a.internship_id) as internships_with_shortlisted
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.company_id = ? AND a.status = 'shortlisted'
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get user initials
$user_initials = strtoupper(substr($company_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortlisted Candidates - SkillBridge</title>
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
            color: #f59e0b;
        }

        .header-title p {
            color: #64748b;
            font-size: 1rem;
        }

        .header-stats {
            display: flex;
            gap: 3rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            margin-top: 0.25rem;
        }

        /* Filters Bar */
        .filters-bar {
            background: #fff;
            padding: 1.5rem 2.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1.15rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1rem;
        }

        .filter-select {
            padding: 0.75rem 1.25rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            background: #fff;
            min-width: 240px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-sm {
            padding: 0.6rem 1.15rem;
            font-size: 0.9rem;
        }

        /* Cards Container */
        .cards-container {
            padding: 2.5rem;
        }

        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 2rem;
        }

        .candidate-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .candidate-card::before {
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

        .candidate-card:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            transform: translateY(-4px);
        }

        .candidate-card:hover::before {
            opacity: 1;
        }

        .card-header {
            display: flex;
            align-items: start;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 2px solid #f3f4f6;
        }

        .student-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.75rem;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .student-info {
            flex: 1;
        }

        .student-info h3 {
            font-size: 1.25rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .contact-row {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .contact-item {
            color: #6b7280;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .contact-item i {
            width: 16px;
            color: #94a3b8;
        }

        .shortlist-badge {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .shortlist-badge i {
            color: #f59e0b;
        }

        .internship-tag {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #1e40af;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-weight: 600;
            border: 1px solid #bfdbfe;
        }

        .internship-tag i {
            color: #3b82f6;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .info-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }

        .info-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 600;
        }

        .skills-container {
            margin-bottom: 1.5rem;
        }

        .skills-title {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .skill-tag {
            padding: 0.5rem 0.85rem;
            background: #f3f4f6;
            color: #4b5563;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }

        .skill-tag:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }

        .actions-row {
            display: flex;
            gap: 0.85rem;
            padding-top: 1.5rem;
            border-top: 2px solid #f3f4f6;
        }

        .btn-view {
            flex: 1;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            justify-content: center;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-download {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: #9ca3af;
            background: white;
            border-radius: 16px;
            border: 2px dashed #e5e7eb;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #4b5563;
            margin-bottom: 0.75rem;
            font-weight: 700;
        }

        .empty-state p {
            color: #9ca3af;
            margin-bottom: 2rem;
            font-size: 1.05rem;
        }

        @media (max-width: 1200px) {
            .candidates-grid {
                grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .candidates-grid {
                grid-template-columns: 1fr;
            }

            .header-stats {
                flex-direction: column;
                gap: 1.5rem;
            }

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
                    <a href="../internships/create.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        Post New Internship
                    </a>
                    <a href="../internships/manage.php" class="nav-link">
                        <i class="fas fa-list"></i>
                        Manage Internships
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Applications</div>
                    <a href="view-applications.php" class="nav-link">
                        <i class="fas fa-inbox"></i>
                        Received Applications
                    </a>
                    <a href="shortlisted.php" class="nav-link active">
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
                    <h1><i class="fas fa-star"></i> Shortlisted Candidates</h1>
                    <p>Review and manage your top talent selections</p>
                </div>
                <div class="header-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_shortlisted']; ?></div>
                        <div class="stat-label">Total Shortlisted</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['internships_with_shortlisted']; ?></div>
                        <div class="stat-label">Internships</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <form method="GET" action="shortlisted.php" style="display: flex; gap: 1rem; align-items: center; flex: 1; flex-wrap: wrap;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="Search by name, email, university, course..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <select name="internship" class="filter-select">
                    <option value="0">All Internships</option>
                    <?php foreach ($company_internships as $int): ?>
                        <option value="<?php echo $int['id']; ?>" <?php echo $internship_filter == $int['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($int['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="shortlisted.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>

        <!-- Candidates Cards -->
        <div class="cards-container">
            <?php if (count($shortlisted) > 0): ?>
                <div class="candidates-grid">
                    <?php foreach ($shortlisted as $candidate): 
                        $student_initials = strtoupper(substr($candidate['student_name'], 0, 2));
                        $skills = !empty($candidate['skills']) ? explode(',', $candidate['skills']) : [];
                    ?>
                        <div class="candidate-card">
                            <span class="shortlist-badge">
                                <i class="fas fa-star"></i>
                                SHORTLISTED
                            </span>

                            <div class="card-header">
                                <div class="student-avatar"><?php echo $student_initials; ?></div>
                                <div class="student-info">
                                    <h3><?php echo htmlspecialchars($candidate['student_name']); ?></h3>
                                    <div class="contact-row">
                                        <div class="contact-item">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($candidate['student_email']); ?>
                                        </div>
                                        <?php if (!empty($candidate['student_phone'])): ?>
                                            <div class="contact-item">
                                                <i class="fas fa-phone"></i>
                                                <?php echo htmlspecialchars($candidate['student_phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="internship-tag">
                                <i class="fas fa-briefcase"></i>
                                <?php echo htmlspecialchars($candidate['internship_title']); ?>
                            </div>

                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">University</div>
                                    <div class="info-value"><?php echo htmlspecialchars($candidate['university'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Course</div>
                                    <div class="info-value"><?php echo htmlspecialchars($candidate['course'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">CGPA</div>
                                    <div class="info-value"><?php echo number_format($candidate['cgpa'] ?? 0, 2); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Graduation</div>
                                    <div class="info-value"><?php echo $candidate['graduation_year'] ?? 'N/A'; ?></div>
                                </div>
                            </div>

                            <?php if (!empty($skills)): ?>
                            <div class="skills-container">
                                <div class="skills-title">Skills</div>
                                <div class="skills-list">
                                    <?php foreach (array_slice($skills, 0, 5) as $skill): ?>
                                        <span class="skill-tag"><?php echo trim($skill); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($skills) > 5): ?>
                                        <span class="skill-tag">+<?php echo count($skills) - 5; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="actions-row">
                                <a href="student-details.php?id=<?php echo $candidate['student_id']; ?>&app_id=<?php echo $candidate['id']; ?>" 
                                   class="btn btn-sm btn-view" title="View Full Profile">
                                    <i class="fas fa-eye"></i> View Profile
                                </a>
                                <?php if (!empty($candidate['resume_path'])): ?>
                                    <a href="../uploads/resumes/<?php echo basename($candidate['resume_path']); ?>" 
                                    target="_blank" 
                                    class="btn btn-sm btn-download" title="Download Resume">
                                        <i class="fas fa-download"></i> Resume
                                    </a>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <h3>No Shortlisted Candidates Yet</h3>
                    <p>Start reviewing applications and shortlist talented candidates for your internships</p>
                    <a href="view-applications.php" class="btn btn-primary">
                        <i class="fas fa-inbox"></i> View All Applications
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
