<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Get export type from query parameter
$export_type = $_GET['type'] ?? 'all';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="SkillBridge_Analytics_' . date('Y-m-d_His') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Export based on type
switch ($export_type) {
    case 'users':
        exportUsers($conn, $output);
        break;
    
    case 'internships':
        exportInternships($conn, $output);
        break;
    
    case 'applications':
        exportApplications($conn, $output);
        break;
    
    case 'analytics':
        exportAnalyticsSummary($conn, $output);
        break;
    
    case 'all':
    default:
        exportAllData($conn, $output);
        break;
}

fclose($output);
exit();

// ==================== EXPORT FUNCTIONS ====================

/**
 * Export all users (Students and Companies)
 */
function exportUsers($conn, $output) {
    // Write header
    fputcsv($output, [
        'User ID',
        'Username',
        'Full Name',
        'Email',
        'Phone',
        'Role',
        'Status',
        'Created At',
        'Last Login'
    ]);
    
    // Get all users - CORRECT column names
    $query = "
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            r.role_name,
            u.status,
            u.created_at,
            u.last_login
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.role_name IN ('Student', 'Company')
        ORDER BY u.created_at DESC
    ";
    
    $result = $conn->query($query);
    
    // Check if query was successful
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['full_name'],
                $row['email'],
                $row['phone'] ?? 'N/A',
                $row['role_name'],
                ucfirst($row['status']),
                date('Y-m-d H:i:s', strtotime($row['created_at'])),
                $row['last_login'] ? date('Y-m-d H:i:s', strtotime($row['last_login'])) : 'Never'
            ]);
        }
    } else {
        // If no data, write a message
        fputcsv($output, ['No users found']);
    }
}

/**
 * Export all internships
 */
function exportInternships($conn, $output) {
    // Write header
    fputcsv($output, [
        'Internship ID',
        'Title',
        'Company',
        'Location',
        'Location Type',
        'Duration',
        'Stipend',
        'Positions Available',
        'Status',
        'Application Deadline',
        'Created At',
        'Total Applications',
        'Selected Candidates'
    ]);
    
    // Get all internships with stats
    $query = "
        SELECT 
            i.id,
            i.title,
            c.name as company_name,
            i.location,
            i.location_type,
            i.duration,
            i.stipend,
            i.positions_available,
            i.status,
            i.application_deadline,
            i.created_at,
            (SELECT COUNT(*) FROM applications WHERE internship_id = i.id) as total_applications,
            (SELECT COUNT(*) FROM applications WHERE internship_id = i.id AND status = 'selected') as selected_count
        FROM internships i
        JOIN companies c ON i.company_id = c.user_id
        ORDER BY i.created_at DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['company_name'],
                $row['location'],
                ucfirst($row['location_type']),
                $row['duration'],
                $row['stipend'],
                $row['positions_available'],
                ucfirst($row['status']),
                date('Y-m-d', strtotime($row['application_deadline'])),
                date('Y-m-d H:i:s', strtotime($row['created_at'])),
                $row['total_applications'],
                $row['selected_count']
            ]);
        }
    } else {
        fputcsv($output, ['No internships found']);
    }
}

/**
 * Export all applications
 */
function exportApplications($conn, $output) {
    // Write header
    fputcsv($output, [
        'Application ID',
        'Student Name',
        'Student Email',
        'Internship Title',
        'Company Name',
        'Applied Date',
        'Status',
        'Interview Scheduled',
        'Interview Date',
        'Updated At'
    ]);
    
    // Get all applications
    $query = "
        SELECT 
            a.id,
            u.full_name as student_name,
            u.email as student_email,
            i.title as internship_title,
            c.name as company_name,
            a.applied_at,
            a.status,
            CASE WHEN int_sched.id IS NOT NULL THEN 'Yes' ELSE 'No' END as has_interview,
            int_sched.interview_datetime,
            a.updated_at
        FROM applications a
        JOIN users u ON a.student_id = u.id
        JOIN internships i ON a.internship_id = i.id
        JOIN companies c ON i.company_id = c.user_id
        LEFT JOIN interviews int_sched ON a.id = int_sched.application_id
        ORDER BY a.applied_at DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['student_name'],
                $row['student_email'],
                $row['internship_title'],
                $row['company_name'],
                date('Y-m-d H:i:s', strtotime($row['applied_at'])),
                ucfirst($row['status']),
                $row['has_interview'],
                $row['interview_datetime'] ? date('Y-m-d H:i:s', strtotime($row['interview_datetime'])) : 'N/A',
                date('Y-m-d H:i:s', strtotime($row['updated_at']))
            ]);
        }
    } else {
        fputcsv($output, ['No applications found']);
    }
}

/**
 * Export analytics summary
 */
function exportAnalyticsSummary($conn, $output) {
    // Section 1: Overall Statistics
    fputcsv($output, ['SKILLBRIDGE ANALYTICS SUMMARY']);
    fputcsv($output, ['Generated On: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    fputcsv($output, ['OVERALL STATISTICS']);
    fputcsv($output, ['Metric', 'Count']);
    
    // Get stats
    $stats = [];
    
    // Total Students (role_id = 2)
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2");
    $stats['Total Students'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total Companies (role_id = 3)
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 3");
    $stats['Total Companies'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total Internships
    $result = $conn->query("SELECT COUNT(*) as count FROM internships");
    $stats['Total Internships'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Active Internships
    $result = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'active'");
    $stats['Active Internships'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total Applications
    $result = $conn->query("SELECT COUNT(*) as count FROM applications");
    $stats['Total Applications'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Selected Applications
    $result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'selected'");
    $stats['Selected Applications'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total Interviews
    $result = $conn->query("SELECT COUNT(*) as count FROM interviews");
    $stats['Total Interviews'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Completed Interviews
    $result = $conn->query("SELECT COUNT(*) as count FROM interviews WHERE status = 'completed'");
    $stats['Completed Interviews'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    foreach ($stats as $metric => $count) {
        fputcsv($output, [$metric, $count]);
    }
    
    fputcsv($output, []);
    
    // Section 2: Conversion Rates
    fputcsv($output, ['CONVERSION RATES']);
    fputcsv($output, ['Metric', 'Percentage']);
    
    $total_applications = $stats['Total Applications'];
    $selected_applications = $stats['Selected Applications'];
    $total_interviews = $stats['Total Interviews'];
    $completed_interviews = $stats['Completed Interviews'];
    
    $app_to_interview = $total_applications > 0 ? round(($total_interviews / $total_applications) * 100, 2) : 0;
    $interview_to_selection = $completed_interviews > 0 ? round(($selected_applications / $completed_interviews) * 100, 2) : 0;
    $overall_conversion = $total_applications > 0 ? round(($selected_applications / $total_applications) * 100, 2) : 0;
    
    fputcsv($output, ['Application to Interview', $app_to_interview . '%']);
    fputcsv($output, ['Interview to Selection', $interview_to_selection . '%']);
    fputcsv($output, ['Overall Conversion Rate', $overall_conversion . '%']);
    
    fputcsv($output, []);
    
    // Section 3: Top Companies
    fputcsv($output, ['TOP 10 COMPANIES BY APPLICATIONS']);
    fputcsv($output, ['Company Name', 'Total Applications']);
    
    $top_companies = $conn->query("
        SELECT c.name, COUNT(a.id) as application_count
        FROM applications a
        JOIN internships i ON a.internship_id = i.id
        JOIN companies c ON i.company_id = c.user_id
        GROUP BY c.id, c.name
        ORDER BY application_count DESC
        LIMIT 10
    ");
    
    if ($top_companies && $top_companies->num_rows > 0) {
        while ($row = $top_companies->fetch_assoc()) {
            fputcsv($output, [$row['name'], $row['application_count']]);
        }
    } else {
        fputcsv($output, ['No data available', '0']);
    }
    
    fputcsv($output, []);
    
    // Section 4: Application Status Distribution
    fputcsv($output, ['APPLICATION STATUS DISTRIBUTION']);
    fputcsv($output, ['Status', 'Count']);
    
    $status_dist = $conn->query("
        SELECT status, COUNT(*) as count 
        FROM applications 
        GROUP BY status
    ");
    
    if ($status_dist && $status_dist->num_rows > 0) {
        while ($row = $status_dist->fetch_assoc()) {
            fputcsv($output, [ucfirst($row['status']), $row['count']]);
        }
    } else {
        fputcsv($output, ['No data available', '0']);
    }
}

/**
 * Export all data in multiple sections
 */
function exportAllData($conn, $output) {
    // Overall Summary
    fputcsv($output, ['SKILLBRIDGE COMPLETE DATA EXPORT']);
    fputcsv($output, ['Generated On: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, []);
    
    // Section 1: Users
    fputcsv($output, ['========== USERS ==========']);
    fputcsv($output, []);
    
    // Write users data
    fputcsv($output, [
        'User ID',
        'Username',
        'Full Name',
        'Email',
        'Phone',
        'Role',
        'Status',
        'Created At',
        'Last Login'
    ]);
    
    $query = "
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            r.role_name,
            u.status,
            u.created_at,
            u.last_login
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.role_name IN ('Student', 'Company')
        ORDER BY u.created_at DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['full_name'],
                $row['email'],
                $row['phone'] ?? 'N/A',
                $row['role_name'],
                ucfirst($row['status']),
                date('Y-m-d H:i:s', strtotime($row['created_at'])),
                $row['last_login'] ? date('Y-m-d H:i:s', strtotime($row['last_login'])) : 'Never'
            ]);
        }
    }
    
    fputcsv($output, []);
    fputcsv($output, []);
    
    // Section 2: Internships
    fputcsv($output, ['========== INTERNSHIPS ==========']);
    fputcsv($output, []);
    
    fputcsv($output, [
        'Internship ID',
        'Title',
        'Company',
        'Location',
        'Location Type',
        'Duration',
        'Stipend',
        'Positions Available',
        'Status',
        'Application Deadline',
        'Created At',
        'Total Applications',
        'Selected Candidates'
    ]);
    
    $query = "
        SELECT 
            i.id,
            i.title,
            c.name as company_name,
            i.location,
            i.location_type,
            i.duration,
            i.stipend,
            i.positions_available,
            i.status,
            i.application_deadline,
            i.created_at,
            (SELECT COUNT(*) FROM applications WHERE internship_id = i.id) as total_applications,
            (SELECT COUNT(*) FROM applications WHERE internship_id = i.id AND status = 'selected') as selected_count
        FROM internships i
        JOIN companies c ON i.company_id = c.user_id
        ORDER BY i.created_at DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['company_name'],
                $row['location'],
                ucfirst($row['location_type']),
                $row['duration'],
                $row['stipend'],
                $row['positions_available'],
                ucfirst($row['status']),
                date('Y-m-d', strtotime($row['application_deadline'])),
                date('Y-m-d H:i:s', strtotime($row['created_at'])),
                $row['total_applications'],
                $row['selected_count']
            ]);
        }
    }
    
    fputcsv($output, []);
    fputcsv($output, []);
    
    // Section 3: Applications
    fputcsv($output, ['========== APPLICATIONS ==========']);
    fputcsv($output, []);
    
    fputcsv($output, [
        'Application ID',
        'Student Name',
        'Student Email',
        'Internship Title',
        'Company Name',
        'Applied Date',
        'Status',
        'Interview Scheduled',
        'Interview Date',
        'Updated At'
    ]);
    
    $query = "
        SELECT 
            a.id,
            u.full_name as student_name,
            u.email as student_email,
            i.title as internship_title,
            c.name as company_name,
            a.applied_at,
            a.status,
            CASE WHEN int_sched.id IS NOT NULL THEN 'Yes' ELSE 'No' END as has_interview,
            int_sched.interview_datetime,
            a.updated_at
        FROM applications a
        JOIN users u ON a.student_id = u.id
        JOIN internships i ON a.internship_id = i.id
        JOIN companies c ON i.company_id = c.user_id
        LEFT JOIN interviews int_sched ON a.id = int_sched.application_id
        ORDER BY a.applied_at DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['student_name'],
                $row['student_email'],
                $row['internship_title'],
                $row['company_name'],
                date('Y-m-d H:i:s', strtotime($row['applied_at'])),
                ucfirst($row['status']),
                $row['has_interview'],
                $row['interview_datetime'] ? date('Y-m-d H:i:s', strtotime($row['interview_datetime'])) : 'N/A',
                date('Y-m-d H:i:s', strtotime($row['updated_at']))
            ]);
        }
    }
    
    fputcsv($output, []);
    fputcsv($output, []);
    
    // Section 4: Analytics Summary
    fputcsv($output, ['========== ANALYTICS SUMMARY ==========']);
    fputcsv($output, []);
    exportAnalyticsSummary($conn, $output);
}
?>
