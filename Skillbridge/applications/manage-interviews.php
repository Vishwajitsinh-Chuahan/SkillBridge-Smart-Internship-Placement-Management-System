 <?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Company') {
    header('Location: ../auth/login.php');
    exit();
}

$company_id = $_SESSION['user_id'];

// Fetch company name
$company_name_query = $conn->query("SELECT name FROM companies WHERE user_id = $company_id");
$company_name = 'Company';
if ($company_name_query && $company_name_query->num_rows > 0) {
    $company_name = $company_name_query->fetch_assoc()['name'];
}

// Handle interview actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // MARK AS COMPLETED
    if (isset($_POST['mark_completed'])) {
        $interview_id = (int)$_POST['interview_id'];
        
        $stmt = $conn->prepare("UPDATE interviews SET status = 'completed', updated_at = NOW() WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ii", $interview_id, $company_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Interview marked as completed successfully!";
        } else {
            $_SESSION['error'] = "Failed to update interview status.";
        }
    }
    
    // RESCHEDULE INTERVIEW
    if (isset($_POST['reschedule_interview'])) {
        $interview_id = (int)$_POST['interview_id'];
        $new_date = $_POST['interview_date'];
        $new_time = $_POST['interview_time'];
        $new_mode = $_POST['interview_mode'];
        $new_location = trim($_POST['interview_location']);
        $new_notes = trim($_POST['interview_notes']);
        $new_datetime = $new_date . ' ' . $new_time;
        
        // Get student details for notification
       $stmt = $conn->prepare("
        SELECT i.student_id, i.application_id, u.full_name, u.email, intern.title
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN internships intern ON i.internship_id = intern.id
        JOIN users u ON i.student_id = u.id
        WHERE i.id = ? AND i.company_id = ?
        ");
            $stmt->bind_param("ii", $interview_id, $company_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $interview_data = $result->fetch_assoc();
            
            // Update interview
            $update_stmt = $conn->prepare("
                UPDATE interviews 
                SET interview_date = ?, interview_time = ?, interview_datetime = ?, 
                    interview_mode = ?, interview_location = ?, interview_notes = ?,
                    status = 'rescheduled', updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $update_stmt->bind_param("ssssssii", $new_date, $new_time, $new_datetime, $new_mode, $new_location, $new_notes, $interview_id, $company_id);
            
            if ($update_stmt->execute()) {
                // Send notification
                $mode_text = $new_mode === 'online' ? 'Online' : 'In-person';
                $location_text = $new_mode === 'online' ? $new_location : "at " . $new_location;
                
                $notification_title = "Interview Rescheduled - {$interview_data['title']}";
                $notification_message = "Hello {$interview_data['full_name']},\n\n";
                $notification_message .= "Your interview for {$interview_data['title']} at {$company_name} has been rescheduled.\n\n";
                $notification_message .= "📅 New Date & Time: " . date('l, F d, Y \a\t g:i A', strtotime($new_datetime)) . "\n";
                $notification_message .= "📍 Mode: {$mode_text}\n";
                $notification_message .= "🏢 Location: {$location_text}\n";
                if (!empty($new_notes)) {
                    $notification_message .= "\n📝 Additional Instructions: {$new_notes}";
                }
                
                $notif_stmt = $conn->prepare("
                INSERT INTO notifications (student_id, application_id, type, title, message, created_at) 
                VALUES (?, ?, 'interview_rescheduled', ?, ?, NOW())
                ");
                $notif_stmt->bind_param("iiss", $interview_data['student_id'], $interview_data['application_id'], $notification_title, $notification_message);

                $notif_stmt->execute();
                
                $_SESSION['success'] = "Interview rescheduled successfully! {$interview_data['full_name']} has been notified.";
            } else {
                $_SESSION['error'] = "Failed to reschedule interview.";
            }
        }
    }
    
    // CANCEL INTERVIEW
    if (isset($_POST['cancel_interview'])) {
        $interview_id = (int)$_POST['interview_id'];
        $cancel_reason = trim($_POST['cancel_reason']);
        $application_action = $_POST['application_action']; // shortlisted, rejected
        
        // Get student details
        $stmt = $conn->prepare("
        SELECT i.student_id, i.application_id, u.full_name, u.email, intern.title
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN internships intern ON i.internship_id = intern.id
        JOIN users u ON i.student_id = u.id
        WHERE i.id = ? AND i.company_id = ?
        ");


        $stmt->bind_param("ii", $interview_id, $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $interview_data = $result->fetch_assoc();
            
            $conn->begin_transaction();
            
            try {
                // Update interview status
                $stmt = $conn->prepare("UPDATE interviews SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $interview_id);
                $stmt->execute();
                
                // Update application status
                $stmt = $conn->prepare("UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $application_action, $interview_data['application_id']);
                $stmt->execute();
                
                // Send notification
                $notification_title = "Interview Cancelled - {$interview_data['title']}";
                $notification_message = "Hello {$interview_data['full_name']},\n\n";
                $notification_message .= "We regret to inform you that your scheduled interview for {$interview_data['title']} at {$company_name} has been cancelled.\n\n";
                if (!empty($cancel_reason)) {
                    $notification_message .= "Reason: {$cancel_reason}\n\n";
                }
                if ($application_action === 'rejected') {
                    $notification_message .= "Unfortunately, we will not be moving forward with your application at this time.";
                } else {
                    $notification_message .= "Your application remains active and we may contact you again if another opportunity arises.";
                }
                
                $notif_stmt = $conn->prepare("
                INSERT INTO notifications (student_id, application_id, type, title, message, created_at) 
                VALUES (?, ?, 'interview_cancelled', ?, ?, NOW())
                ");
                $notif_stmt->bind_param("iiss", $interview_data['student_id'], $interview_data['application_id'], $notification_title, $notification_message);


                $notif_stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = "Interview cancelled successfully. {$interview_data['full_name']} has been notified.";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Failed to cancel interview.";
            }
        }
    }
    
    // SELECT CANDIDATE
    if (isset($_POST['select_candidate'])) {
        $interview_id = (int)$_POST['interview_id'];
        
        // Get interview details
        $stmt = $conn->prepare("
        SELECT i.student_id, i.application_id, u.full_name, u.email, intern.title
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN internships intern ON i.internship_id = intern.id
        JOIN users u ON i.student_id = u.id
        WHERE i.id = ? AND i.company_id = ? AND i.status = 'completed'
        ");

        $stmt->bind_param("ii", $interview_id, $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            // Update application status
            $stmt = $conn->prepare("UPDATE applications SET status = 'selected', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $data['application_id']);
            
            if ($stmt->execute()) {
                // Send congratulations notification
                $notification_title = "🎉 Congratulations! You're Selected - {$data['title']}";
                $notification_message = "Dear {$data['full_name']},\n\n";
                $notification_message .= "Congratulations! We are pleased to inform you that you have been selected for the {$data['title']} position at {$company_name}.\n\n";
                $notification_message .= "We were impressed with your interview performance and believe you will be a great addition to our team.\n\n";
                $notification_message .= "We will contact you soon with further details regarding the onboarding process.\n\n";
                $notification_message .= "Welcome aboard!\n\n";
                $notification_message .= "Best regards,\n{$company_name}";
                
                $notif_stmt = $conn->prepare("
                INSERT INTO notifications (student_id, application_id, type, title, message, created_at) 
                VALUES (?, ?, 'application_selected', ?, ?, NOW())
                ");

                $notif_stmt->bind_param("iiss", $data['student_id'], $data['application_id'],$notification_title, $notification_message);
                $notif_stmt->execute();
                
                $_SESSION['success'] = "🎉 {$data['full_name']} has been selected! Congratulations notification sent.";
            }
        }
    }
    
    // REJECT CANDIDATE
    if (isset($_POST['reject_candidate'])) {
        $interview_id = (int)$_POST['interview_id'];
        $rejection_feedback = trim($_POST['rejection_feedback']);
        
        // Get interview details
        $stmt = $conn->prepare("
        SELECT i.student_id, i.application_id, u.full_name, u.email, intern.title
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN internships intern ON i.internship_id = intern.id
        JOIN users u ON i.student_id = u.id
        WHERE i.id = ? AND i.company_id = ? AND i.status = 'completed'
        ");

        $stmt->bind_param("ii", $interview_id, $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            // Update application status
            $stmt = $conn->prepare("UPDATE applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $data['application_id']);
            
            if ($stmt->execute()) {
                // Send rejection notification
                $notification_title = "Application Update - {$data['title']}";
                $notification_message = "Dear {$data['full_name']},\n\n";
                $notification_message .= "Thank you for taking the time to interview for the {$data['title']} position at {$company_name}.\n\n";
                $notification_message .= "After careful consideration, we have decided to move forward with other candidates whose experience more closely matches our current needs.\n\n";
                if (!empty($rejection_feedback)) {
                    $notification_message .= "Feedback: {$rejection_feedback}\n\n";
                }
                $notification_message .= "We appreciate your interest in {$company_name} and encourage you to apply for future opportunities that match your skills and experience.\n\n";
                $notification_message .= "Best wishes in your career journey!\n\n";
                $notification_message .= "Best regards,\n{$company_name}";
                
                $notif_stmt = $conn->prepare("
                INSERT INTO notifications (student_id, application_id, type, title, message, created_at) 
                VALUES (?, ?, 'application_rejected', ?, ?, NOW())
                ");

                $notif_stmt->bind_param("iiss", $data['student_id'], $data['application_id'],$notification_title, $notification_message);
                $notif_stmt->execute();
                
                $_SESSION['success'] = "Application status updated. {$data['full_name']} has been notified.";
            }
        }
    }
    
    header('Location: manage-interviews.php');
    exit();
}

// Get filter parameters
$tab = $_GET['tab'] ?? 'all';
$internship_filter = isset($_GET['internship']) ? (int)$_GET['internship'] : 0;
$search = trim($_GET['search'] ?? '');

// Fetch company's internships for filter
$internships_query = "SELECT id, title FROM internships WHERE company_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($internships_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company_internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build main query for interviews
$query = "
    SELECT 
        int_sched.*,
        a.status as application_status,
        i.title as internship_title,
        u.full_name as student_name,
        u.email as student_email,
        u.phone as student_phone,
        sp.university,
        sp.course,
        sp.cgpa,
        sp.resume_path
    FROM interviews int_sched
    JOIN applications a ON int_sched.application_id = a.id
    JOIN internships i ON int_sched.internship_id = i.id
    JOIN users u ON int_sched.student_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE int_sched.company_id = ?
";

$params = [$company_id];
$types = 'i';

// Apply tab filter
if ($tab !== 'all') {
    if ($tab === 'upcoming') {
        $query .= " AND int_sched.status = 'scheduled' AND int_sched.interview_datetime >= NOW()";
    } elseif ($tab === 'past') {
        $query .= " AND int_sched.status = 'scheduled' AND int_sched.interview_datetime < NOW()";
    } else {
        $query .= " AND int_sched.status = ?";
        $params[] = $tab;
        $types .= 's';
    }
}

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

$query .= " ORDER BY int_sched.interview_datetime ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$interviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN int_sched.status = 'scheduled' AND int_sched.interview_datetime >= NOW() THEN 1 END) as upcoming,
        COUNT(CASE WHEN int_sched.status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN int_sched.status = 'cancelled' THEN 1 END) as cancelled,
        COUNT(CASE WHEN int_sched.status = 'rescheduled' THEN 1 END) as rescheduled,
        COUNT(CASE WHEN a.status = 'selected' THEN 1 END) as selected,
        COUNT(CASE WHEN int_sched.status = 'completed' AND a.status = 'interview' THEN 1 END) as pending_decision
    FROM interviews int_sched
    JOIN applications a ON int_sched.application_id = a.id
    JOIN internships intern ON int_sched.internship_id = intern.id
    WHERE int_sched.company_id = ? AND a.status IN ('interview', 'selected', 'rejected')
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
    <title>Manage Interviews - SkillBridge</title>
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

        /* Sidebar - Same as before */
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
        }

        .user-details p {
            color: #94a3b8;
            font-size: 0.8rem;
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
            color: #06b6d4;
        }

        .header-title p {
            color: #64748b;
            font-size: 1rem;
        }

        /* Stats Bar */
        .stats-bar {
            background: white;
            padding: 2rem 2.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 2.5rem;
            overflow-x: auto;
        }

        .stat-item {
            text-align: center;
            min-width: 100px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-item.total .stat-value { color: #667eea; }
        .stat-item.upcoming .stat-value { color: #06b6d4; }
        .stat-item.completed .stat-value { color: #10b981; }
        .stat-item.cancelled .stat-value { color: #ef4444; }
        .stat-item.selected .stat-value { color: #059669; }
        .stat-item.pending .stat-value { color: #f59e0b; }

        .stat-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        /* Alerts */
        .alert {
            padding: 1rem 2.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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

        /* Tabs */
        .tabs-container {
            background: white;
            padding: 0 2.5rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
        }

        .tab-button {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .tab-button:hover {
            color: #06b6d4;
            background: rgba(6, 182, 212, 0.05);
        }

        .tab-button.active {
            color: #06b6d4;
            border-bottom-color: #06b6d4;
        }

        .tab-badge {
            background: #e5e7eb;
            color: #4b5563;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .tab-button.active .tab-badge {
            background: #06b6d4;
            color: white;
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
            border-color: #06b6d4;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
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
            border-color: #06b6d4;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
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
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(6, 182, 212, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-sm {
            padding: 0.6rem 1.15rem;
            font-size: 0.9rem;
        }

        /* Content */
        .content {
            padding: 2.5rem;
        }

        .interviews-grid {
            display: grid;
            gap: 2rem;
        }

        .interview-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .interview-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .interview-card:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            transform: translateY(-4px);
        }

        .interview-card:hover::before {
            opacity: 1;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 2px solid #f3f4f6;
        }

        .student-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.75rem;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
        }

        .student-info {
            flex: 1;
            margin-left: 1.25rem;
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

        .status-badges {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .status-badge {
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .status-scheduled {
            background: #cffafe;
            color: #0e7490;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-rescheduled {
            background: #fef3c7;
            color: #92400e;
        }

        .status-selected {
            background: #d1fae5;
            color: #065f46;
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

        .interview-details {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .interview-details h4 {
            color: #0e7490;
            font-size: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }

        .detail-value {
            font-size: 0.95rem;
            color: #1f2937;
            font-weight: 600;
        }

        .actions-row {
            display: flex;
            gap: 0.75rem;
            padding-top: 1.5rem;
            border-top: 2px solid #f3f4f6;
            flex-wrap: wrap;
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
            border-color: #06b6d4;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
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

        .modal-footer {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: 16px;
            border: 2px dashed #e5e7eb;
        }

        .empty-state i {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #4b5563;
            margin-bottom: 0.75rem;
        }

        .empty-state p {
            color: #9ca3af;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .sidebar {
                display: none;
            }
            .details-grid,
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
                    <a href="interviews.php" class="nav-link">
                        <i class="fas fa-calendar-plus"></i>
                        Schedule Interviews
                    </a>
                    <a href="manage-interviews.php" class="nav-link active">
                        <i class="fas fa-calendar-check"></i>
                        Manage Interviews
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
                    <h1><i class="fas fa-calendar-check"></i> Manage Interviews</h1>
                    <p>Track and manage all scheduled interviews</p>
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item total">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-item upcoming">
                <div class="stat-value"><?php echo $stats['upcoming']; ?></div>
                <div class="stat-label">Upcoming</div>
            </div>
            <div class="stat-item completed">
                <div class="stat-value"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-item cancelled">
                <div class="stat-value"><?php echo $stats['cancelled']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-item selected">
                <div class="stat-value"><?php echo $stats['selected']; ?></div>
                <div class="stat-label">Selected</div>
            </div>
            <div class="stat-item pending">
                <div class="stat-value"><?php echo $stats['pending_decision']; ?></div>
                <div class="stat-label">Pending Decision</div>
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

        <!-- Tabs -->
        <div class="tabs-container">
            <a href="?tab=all" class="tab-button <?php echo $tab === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All Interviews
                <span class="tab-badge"><?php echo $stats['total']; ?></span>
            </a>
            <a href="?tab=upcoming" class="tab-button <?php echo $tab === 'upcoming' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Upcoming
                <span class="tab-badge"><?php echo $stats['upcoming']; ?></span>
            </a>
            <a href="?tab=past" class="tab-button <?php echo $tab === 'past' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Past Due
            </a>
            <a href="?tab=completed" class="tab-button <?php echo $tab === 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Completed
                <span class="tab-badge"><?php echo $stats['completed']; ?></span>
            </a>
            <a href="?tab=cancelled" class="tab-button <?php echo $tab === 'cancelled' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Cancelled
                <span class="tab-badge"><?php echo $stats['cancelled']; ?></span>
            </a>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: center; flex: 1; flex-wrap: wrap;">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                
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
                <a href="manage-interviews.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>

        <!-- Interviews Content -->
        <div class="content">
            <?php if (count($interviews) > 0): ?>
                <div class="interviews-grid">
                    <?php foreach ($interviews as $interview): 
                        $student_initials = strtoupper(substr($interview['student_name'], 0, 2));
                        $is_past = strtotime($interview['interview_datetime']) < time();
                        $can_reschedule = $interview['status'] === 'scheduled' || $interview['status'] === 'rescheduled';
                        $can_complete = ($interview['status'] === 'scheduled' || $interview['status'] === 'rescheduled') && $is_past;
                        $can_select = $interview['status'] === 'completed' && $interview['application_status'] === 'interview';
                    ?>
                        <div class="interview-card">
                            <div class="card-header">
                                <div style="display: flex; align-items: start; flex: 1;">
                                    <div class="student-avatar"><?php echo $student_initials; ?></div>
                                    <div class="student-info">
                                        <h3><?php echo htmlspecialchars($interview['student_name']); ?></h3>
                                        <div class="contact-row">
                                            <div class="contact-item">
                                                <i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($interview['student_email']); ?>
                                            </div>
                                            <?php if (!empty($interview['student_phone'])): ?>
                                                <div class="contact-item">
                                                    <i class="fas fa-phone"></i>
                                                    <?php echo htmlspecialchars($interview['student_phone']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="status-badges">
                                    <div class="status-badge status-<?php echo $interview['status']; ?>">
                                        <i class="fas fa-<?php 
                                            echo $interview['status'] === 'scheduled' ? 'clock' : 
                                                ($interview['status'] === 'completed' ? 'check-circle' : 
                                                ($interview['status'] === 'cancelled' ? 'times-circle' : 'redo')); 
                                        ?>"></i>
                                        <?php echo strtoupper($interview['status']); ?>
                                    </div>
                                    <?php if ($interview['application_status'] === 'selected'): ?>
                                        <div class="status-badge status-selected">
                                            <i class="fas fa-trophy"></i>
                                            HIRED
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="internship-tag">
                                <i class="fas fa-briefcase"></i>
                                <?php echo htmlspecialchars($interview['internship_title']); ?>
                            </div>

                            <div class="interview-details">
                                <h4><i class="fas fa-info-circle"></i> Interview Details</h4>
                                <div class="details-grid">
                                    <div class="detail-item">
                                        <div class="detail-label">Date & Time</div>
                                        <div class="detail-value"><?php echo date('M d, Y - g:i A', strtotime($interview['interview_datetime'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Mode</div>
                                        <div class="detail-value"><?php echo ucfirst($interview['interview_mode']); ?></div>
                                    </div>
                                    <div class="detail-item" style="grid-column: span 2;">
                                        <div class="detail-label"><?php echo $interview['interview_mode'] === 'online' ? 'Meeting Link' : 'Location'; ?></div>
                                        <div class="detail-value"><?php echo htmlspecialchars($interview['interview_location']); ?></div>
                                    </div>
                                    <?php if (!empty($interview['interview_notes'])): ?>
                                        <div class="detail-item" style="grid-column: span 2;">
                                            <div class="detail-label">Notes</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($interview['interview_notes']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-item">
                                        <div class="detail-label">University</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($interview['university'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">CGPA</div>
                                        <div class="detail-value"><?php echo number_format($interview['cgpa'] ?? 0, 2); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="actions-row">
                                <!-- View Profile -->
                                <a href="student-details.php?id=<?php echo $interview['student_id']; ?>&app_id=<?php echo $interview['application_id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View Profile
                                </a>

                                <?php if ($can_complete): ?>
                                    <!-- Mark as Completed -->
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="interview_id" value="<?php echo $interview['id']; ?>">
                                        <button type="submit" name="mark_completed" class="btn btn-sm btn-success"
                                                onclick="return confirm('Mark this interview as completed?')">
                                            <i class="fas fa-check"></i> Mark Completed
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($can_reschedule): ?>
                                    <!-- Reschedule -->
                                    <button onclick="openRescheduleModal(<?php echo htmlspecialchars(json_encode($interview)); ?>)" 
                                            class="btn btn-sm btn-warning">
                                        <i class="fas fa-redo"></i> Reschedule
                                    </button>

                                    <!-- Cancel -->
                                    <button onclick="openCancelModal(<?php echo $interview['id']; ?>, '<?php echo htmlspecialchars($interview['student_name'], ENT_QUOTES); ?>')" 
                                            class="btn btn-sm btn-danger">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php endif; ?>

                                <?php if ($can_select): ?>
                                    <!-- Select Candidate -->
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="interview_id" value="<?php echo $interview['id']; ?>">
                                        <button type="submit" name="select_candidate" class="btn btn-sm btn-success"
                                                onclick="return confirm('Select this candidate? This will mark them as HIRED.')">
                                            <i class="fas fa-trophy"></i> Select Candidate
                                        </button>
                                    </form>

                                    <!-- Reject Candidate -->
                                    <button onclick="openRejectModal(<?php echo $interview['id']; ?>, '<?php echo htmlspecialchars($interview['student_name'], ENT_QUOTES); ?>')" 
                                            class="btn btn-sm btn-danger">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                <?php endif; ?>

                                <?php if (!empty($interview['resume_path'])): ?>
                                    <!-- Download Resume -->
                                    <a href="download-resume.php?student_id=<?php echo $interview['student_id']; ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-secondary">
                                        <i class="fas fa-download"></i> Resume
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Interviews Found</h3>
                    <p>There are no interviews matching your filters.</p>
                    <a href="interviews.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Schedule New Interview
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal" id="rescheduleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-redo"></i> Reschedule Interview</h3>
                <p style="color: #6b7280; margin-top: 0.5rem;">Update interview date, time, and details</p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="interview_id" id="reschedule_id">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="interview_date" id="reschedule_date" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time</label>
                            <input type="time" name="interview_time" id="reschedule_time" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mode</label>
                        <select name="interview_mode" id="reschedule_mode" class="form-select" required>
                            <option value="online">Online</option>
                            <option value="offline">In-person</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Location/Link</label>
                        <input type="text" name="interview_location" id="reschedule_location" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="interview_notes" id="reschedule_notes" class="form-textarea"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeRescheduleModal()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="reschedule_interview" class="btn btn-primary">
                        <i class="fas fa-check"></i> Reschedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Cancel Interview</h3>
                <p style="color: #6b7280; margin-top: 0.5rem;" id="cancel_student_name"></p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="interview_id" id="cancel_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Reason for Cancellation (Optional)</label>
                        <textarea name="cancel_reason" class="form-textarea" placeholder="Position filled, candidate not suitable, etc."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">What should happen to the application?</label>
                        <select name="application_action" class="form-select" required>
                            <option value="shortlisted">Keep as Shortlisted (give another chance)</option>
                            <option value="rejected">Reject Application</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeCancelModal()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </button>
                    <button type="submit" name="cancel_interview" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel Interview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Reject Candidate</h3>
                <p style="color: #6b7280; margin-top: 0.5rem;" id="reject_student_name"></p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="interview_id" id="reject_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Feedback for Candidate (Optional)</label>
                        <textarea name="rejection_feedback" class="form-textarea" placeholder="Provide constructive feedback (optional)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeRejectModal()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </button>
                    <button type="submit" name="reject_candidate" class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to reject this candidate?')">
                        <i class="fas fa-times"></i> Reject Candidate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Reschedule Modal
        function openRescheduleModal(interview) {
            document.getElementById('reschedule_id').value = interview.id;
            document.getElementById('reschedule_date').value = interview.interview_date;
            document.getElementById('reschedule_time').value = interview.interview_time;
            document.getElementById('reschedule_mode').value = interview.interview_mode;
            document.getElementById('reschedule_location').value = interview.interview_location;
            document.getElementById('reschedule_notes').value = interview.interview_notes || '';
            document.getElementById('rescheduleModal').classList.add('active');
        }

        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').classList.remove('active');
        }

        // Cancel Modal
        function openCancelModal(interviewId, studentName) {
            document.getElementById('cancel_id').value = interviewId;
            document.getElementById('cancel_student_name').textContent = 'Cancelling interview for ' + studentName;
            document.getElementById('cancelModal').classList.add('active');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }

        // Reject Modal
        function openRejectModal(interviewId, studentName) {
            document.getElementById('reject_id').value = interviewId;
            document.getElementById('reject_student_name').textContent = 'Rejecting ' + studentName;
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
        }

        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
