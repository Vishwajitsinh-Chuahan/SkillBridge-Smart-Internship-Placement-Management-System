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

// Handle interview scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_interview'])) {
    $application_id = (int)$_POST['application_id'];
    $interview_date = $_POST['interview_date'];
    $interview_time = $_POST['interview_time'];
    $interview_mode = $_POST['interview_mode'];
    $interview_location = trim($_POST['interview_location']);
    $interview_notes = trim($_POST['interview_notes']);
    
    // Combine date and time
    $interview_datetime = $interview_date . ' ' . $interview_time;
    
    // Verify this application belongs to this company
    $verify_query = "
        SELECT a.student_id, a.internship_id, i.title, u.full_name, u.email
        FROM applications a 
        JOIN internships i ON a.internship_id = i.id 
        JOIN users u ON a.student_id = u.id
        WHERE a.id = ? AND i.company_id = ? AND a.status = 'shortlisted'
    ";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("ii", $application_id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $app_data = $result->fetch_assoc();
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into interviews table
            $insert_interview = $conn->prepare("
                INSERT INTO interviews 
                (application_id, student_id, company_id, internship_id, interview_date, interview_time, interview_datetime, interview_mode, interview_location, interview_notes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
            ");
            $insert_interview->bind_param(
                "iiiissssss", 
                $application_id, 
                $app_data['student_id'], 
                $company_id, 
                $app_data['internship_id'], 
                $interview_date, 
                $interview_time, 
                $interview_datetime, 
                $interview_mode, 
                $interview_location, 
                $interview_notes
            );
            $insert_interview->execute();
            
            // Update application status to 'interview'
            $update_stmt = $conn->prepare("UPDATE applications SET status = 'interview', updated_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $application_id);
            $update_stmt->execute();
            
            // Create notification for student
            $mode_text = $interview_mode === 'online' ? 'Online' : 'In-person';
            $location_text = $interview_mode === 'online' ? $interview_location : "at " . $interview_location;
            
            $notification_title = "Interview Scheduled - {$app_data['title']}";
            $notification_message = "Congratulations {$app_data['full_name']}! You have been called for an interview for the {$app_data['title']} position at {$company_name}.\n\n";
            $notification_message .= "📅 Date & Time: " . date('l, F d, Y \a\t g:i A', strtotime($interview_datetime)) . "\n";
            $notification_message .= "📍 Mode: {$mode_text}\n";
            $notification_message .= "🏢 Location: {$location_text}\n";
            if (!empty($interview_notes)) {
                $notification_message .= "\n📝 Additional Instructions: {$interview_notes}";
            }
            
            $notif_stmt = $conn->prepare("
                INSERT INTO notifications (student_id, application_id, type, title, message, created_at) 
                VALUES (?, ?, 'interview_scheduled', ?, ?, NOW())
            ");
            $notif_stmt->bind_param("iiss", $app_data['student_id'], $application_id, $notification_title, $notification_message);
            $notif_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Interview scheduled successfully! {$app_data['full_name']} has been notified.";
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error'] = "Failed to schedule interview. Please try again.";
            error_log("Interview scheduling error: " . $e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Invalid application or candidate not shortlisted.";
    }
    
    header('Location: interviews.php');
    exit();
}

// Get filter parameters
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
        i.location as internship_location,
        u.full_name as student_name,
        u.email as student_email,
        u.phone as student_phone,
        sp.university,
        sp.course,
        sp.cgpa,
        sp.year_of_study
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
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR sp.university LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

$query .= " ORDER BY a.updated_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$shortlisted_candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_shortlisted,
        COUNT(DISTINCT a.internship_id) as internships_count
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
    <title>Interview Scheduling - SkillBridge</title>
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
            color: #3b82f6;
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

        /* Alerts */
        .alert {
            padding: 1rem 2.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
        }

        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
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
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
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
            background: linear-gradient(135deg, #3b82f6, #2563eb);
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
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.75rem;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
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

        .internship-tag {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-weight: 600;
            border: 1px solid #93c5fd;
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

        .actions-row {
            display: flex;
            gap: 0.85rem;
            padding-top: 1.5rem;
            border-top: 2px solid #f3f4f6;
        }

        .btn-schedule {
            flex: 1;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            justify-content: center;
        }

        .btn-schedule:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            margin-bottom: 2rem;
        }

        .modal-header h3 {
            font-size: 1.75rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-header h3 i {
            color: #3b82f6;
        }

        .modal-student-info {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .modal-student-info p {
            color: #4b5563;
            font-size: 0.95rem;
            margin: 0;
        }

        .modal-student-info strong {
            color: #1f2937;
        }

        .modal-body {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-label .required {
            color: #ef4444;
            margin-left: 0.25rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .info-box p {
            color: #1e40af;
            font-size: 0.9rem;
            margin: 0;
            display: flex;
            align-items: start;
            gap: 0.5rem;
        }

        .info-box i {
            margin-top: 0.2rem;
        }

        .modal-footer {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .btn-cancel {
            background: #e5e7eb;
            color: #4b5563;
        }

        .btn-cancel:hover {
            background: #d1d5db;
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
            color: #3b82f6;
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

            .header-stats {
                flex-direction: column;
                gap: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
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
                    <a href="../internships/analytics.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        Analytics
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Applications</div>
                    <a href="view-applications.php" class="nav-link">
                        <i class="fas fa-inbox"></i>
                        Received Applications
                    </a>
                    <a href="shortlisted.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        Shortlisted Candidates
                    </a>
                    <a href="interviews.php" class="nav-link active">
                        <i class="fas fa-calendar-check"></i>
                        Interview Schedule
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
                    <h1><i class="fas fa-calendar-check"></i> Interview Scheduling</h1>
                    <p>Schedule interviews with shortlisted candidates</p>
                </div>
                <div class="header-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_shortlisted']; ?></div>
                        <div class="stat-label">Shortlisted</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['internships_count']; ?></div>
                        <div class="stat-label">Internships</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: center; flex: 1; flex-wrap: wrap;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="Search by name, email, university..." 
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
                <a href="interviews.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>

        <!-- Candidates Cards -->
        <div class="cards-container">
            <?php if (count($shortlisted_candidates) > 0): ?>
                <div class="candidates-grid">
                    <?php foreach ($shortlisted_candidates as $candidate): 
                        $student_initials = strtoupper(substr($candidate['student_name'], 0, 2));
                    ?>
                        <div class="candidate-card">
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
                                    <div class="info-label">Year of Study</div>
                                    <div class="info-value"><?php echo htmlspecialchars($candidate['year_of_study'] ?? 'N/A'); ?></div>
                                </div>
                            </div>

                            <div class="actions-row">
                                <button onclick="openScheduleModal(<?php echo $candidate['id']; ?>, '<?php echo htmlspecialchars($candidate['student_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($candidate['internship_title'], ENT_QUOTES); ?>')" 
                                        class="btn btn-sm btn-schedule">
                                    <i class="fas fa-calendar-plus"></i> Schedule Interview
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <h3>No Shortlisted Candidates</h3>
                    <p>Shortlist candidates from applications to schedule interviews</p>
                    <a href="view-applications.php" class="btn btn-primary">
                        <i class="fas fa-inbox"></i> View Applications
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Schedule Interview Modal -->
    <div class="modal" id="scheduleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus"></i> Schedule Interview</h3>
                <div class="modal-student-info">
                    <p><strong>Candidate:</strong> <span id="modalStudentName"></span></p>
                    <p><strong>Position:</strong> <span id="modalInternshipTitle"></span></p>
                </div>
            </div>
            <form method="POST" action="" id="scheduleForm">
                <input type="hidden" name="application_id" id="modalApplicationId">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Interview Date<span class="required">*</span></label>
                            <input type="date" name="interview_date" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Interview Time<span class="required">*</span></label>
                            <input type="time" name="interview_time" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interview Mode<span class="required">*</span></label>
                        <select name="interview_mode" id="interviewMode" class="form-select" required onchange="toggleLocationLabel()">
                            <option value="">Select mode</option>
                            <option value="online">Online Interview</option>
                            <option value="offline">In-person Interview</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="locationLabel">Location/Meeting Link<span class="required">*</span></label>
                        <input type="text" name="interview_location" class="form-input" placeholder="Enter office address or meeting link" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Additional Instructions (Optional)</label>
                        <textarea name="interview_notes" class="form-textarea" placeholder="Any special instructions or documents to bring..."></textarea>
                    </div>

                    <div class="info-box">
                        <p>
                            <i class="fas fa-info-circle"></i>
                            The candidate will be automatically notified via email and in-app notification about the interview schedule.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeScheduleModal()" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="schedule_interview" class="btn btn-primary">
                        <i class="fas fa-check"></i> Schedule Interview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openScheduleModal(applicationId, studentName, internshipTitle) {
            document.getElementById('modalApplicationId').value = applicationId;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalInternshipTitle').textContent = internshipTitle;
            document.getElementById('scheduleModal').classList.add('active');
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').classList.remove('active');
            document.getElementById('scheduleForm').reset();
        }

        function toggleLocationLabel() {
            const mode = document.getElementById('interviewMode').value;
            const label = document.getElementById('locationLabel');
            const input = document.querySelector('input[name="interview_location"]');
            
            if (mode === 'online') {
                label.innerHTML = 'Meeting Link<span class="required">*</span>';
                input.placeholder = 'Enter Zoom/Google Meet/Teams link';
            } else if (mode === 'offline') {
                label.innerHTML = 'Interview Location<span class="required">*</span>';
                input.placeholder = 'Enter office address';
            } else {
                label.innerHTML = 'Location/Meeting Link<span class="required">*</span>';
                input.placeholder = 'Enter office address or meeting link';
            }
        }

        // Close modal on outside click
        document.getElementById('scheduleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeScheduleModal();
            }
        });

        // Prevent form submission on Enter key in text inputs
        document.getElementById('scheduleForm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
