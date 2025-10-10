<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit();
}

$admin_name = $_SESSION['full_name'] ?? 'Admin';
$admin_initials = strtoupper(substr($admin_name, 0, 2));

// ==================== KEY METRICS ====================

// Total Users (from users table joining roles)
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2");
$total_students = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 3");
$total_companies = $result ? $result->fetch_assoc()['count'] : 0;

$total_users = $total_students + $total_companies;

// Total Internships
$result = $conn->query("SELECT COUNT(*) as count FROM internships");
$total_internships = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'active'");
$active_internships = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'pending'");
$pending_internships = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'approved'");
$approved_internships = $result ? $result->fetch_assoc()['count'] : 0;

// Total Applications
$result = $conn->query("SELECT COUNT(*) as count FROM applications");
$total_applications = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
$pending_applications = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'selected'");
$selected_applications = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'");
$rejected_applications = $result ? $result->fetch_assoc()['count'] : 0;

// Total Interviews
$result = $conn->query("SELECT COUNT(*) as count FROM interviews");
$total_interviews = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM interviews WHERE status = 'scheduled'");
$scheduled_interviews = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM interviews WHERE status = 'completed'");
$completed_interviews = $result ? $result->fetch_assoc()['count'] : 0;

// Conversion Rates
$application_to_interview = $total_applications > 0 ? round(($total_interviews / $total_applications) * 100, 2) : 0;
$interview_to_selection = $completed_interviews > 0 ? round(($selected_applications / $completed_interviews) * 100, 2) : 0;
$overall_conversion = $total_applications > 0 ? round(($selected_applications / $total_applications) * 100, 2) : 0;

// ==================== CHART DATA ====================

// 1. Applications Over Time (Last 30 days)
$applications_timeline = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE DATE(applied_at) = '{$date}'");
    $count = $result ? $result->fetch_assoc()['count'] : 0;
    $applications_timeline[] = [
        'date' => date('M d', strtotime($date)),
        'count' => (int)$count
    ];
}

// 2. Application Status Distribution
$result = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM applications 
    GROUP BY status
");
$status_distribution = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// 3. Top Companies by Applications
$result = $conn->query("
    SELECT c.name, COUNT(a.id) as application_count
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    JOIN companies c ON i.company_id = c.user_id
    GROUP BY c.id, c.name
    ORDER BY application_count DESC
    LIMIT 10
");
$top_companies = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// 4. Top Internship Categories
$result = $conn->query("
    SELECT 
        CASE 
            WHEN LOWER(title) LIKE '%web%' OR LOWER(title) LIKE '%frontend%' OR LOWER(title) LIKE '%backend%' THEN 'Web Development'
            WHEN LOWER(title) LIKE '%machine learning%' OR LOWER(title) LIKE '%ml%' OR LOWER(title) LIKE '%ai%' THEN 'Machine Learning/AI'
            WHEN LOWER(title) LIKE '%data%' THEN 'Data Science'
            WHEN LOWER(title) LIKE '%mobile%' OR LOWER(title) LIKE '%android%' OR LOWER(title) LIKE '%ios%' THEN 'Mobile Development'
            WHEN LOWER(title) LIKE '%cloud%' OR LOWER(title) LIKE '%devops%' THEN 'Cloud/DevOps'
            WHEN LOWER(title) LIKE '%cyber%' OR LOWER(title) LIKE '%security%' THEN 'Cybersecurity'
            WHEN LOWER(title) LIKE '%design%' OR LOWER(title) LIKE '%ui%' OR LOWER(title) LIKE '%ux%' THEN 'UI/UX Design'
            WHEN LOWER(title) LIKE '%php%' OR LOWER(title) LIKE '%laravel%' THEN 'PHP Development'
            WHEN LOWER(title) LIKE '%nlp%' OR LOWER(title) LIKE '%language%' THEN 'Natural Language Processing'
            ELSE 'Other'
        END as category,
        COUNT(*) as count
    FROM internships
    WHERE status IN ('active', 'approved')
    GROUP BY category
    ORDER BY count DESC
");
$top_categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// 5. Student Registration Trend (Last 12 months)
$student_registration = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2 AND DATE_FORMAT(created_at, '%Y-%m') = '{$month}'");
    $count = $result ? $result->fetch_assoc()['count'] : 0;
    $student_registration[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'count' => (int)$count
    ];
}

// 6. Company Registration Trend (Last 12 months)
$company_registration = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 3 AND DATE_FORMAT(created_at, '%Y-%m') = '{$month}'");
    $count = $result ? $result->fetch_assoc()['count'] : 0;
    $company_registration[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'count' => (int)$count
    ];
}

// 7. Interview Success Rate by Month (Last 6 months)
$interview_success = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    
    $result = $conn->query("SELECT COUNT(*) as count FROM interviews WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$month}'");
    $total_month_interviews = $result ? $result->fetch_assoc()['count'] : 0;
    
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM interviews int_sched
        JOIN applications a ON int_sched.application_id = a.id
        WHERE DATE_FORMAT(int_sched.created_at, '%Y-%m') = '{$month}' AND a.status = 'selected'
    ");
    $successful = $result ? $result->fetch_assoc()['count'] : 0;
    
    $success_rate = $total_month_interviews > 0 ? round(($successful / $total_month_interviews) * 100, 2) : 0;
    $interview_success[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'rate' => $success_rate
    ];
}

// 8. Location Type Distribution (On-site vs Remote vs Hybrid)
$result = $conn->query("
    SELECT location_type, COUNT(*) as count 
    FROM internships 
    WHERE status IN ('active', 'approved')
    GROUP BY location_type
");
$location_type_dist = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// 9. CGPA Distribution of Students
$result = $conn->query("
    SELECT 
        CASE 
            WHEN cgpa >= 9.0 THEN '9.0-10.0'
            WHEN cgpa >= 8.0 THEN '8.0-8.9'
            WHEN cgpa >= 7.0 THEN '7.0-7.9'
            WHEN cgpa >= 6.0 THEN '6.0-6.9'
            ELSE 'Below 6.0'
        END as cgpa_range,
        COUNT(*) as count
    FROM student_profiles
    WHERE cgpa IS NOT NULL
    GROUP BY cgpa_range
    ORDER BY cgpa_range DESC
");
$cgpa_distribution = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// 10. Company Size Distribution
$result = $conn->query("
    SELECT company_size, COUNT(*) as count
    FROM companies
    WHERE status = 'approved'
    GROUP BY company_size
    ORDER BY count DESC
");
$company_size_dist = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// 11. Top Locations for Internships
$result = $conn->query("
    SELECT location, COUNT(*) as count
    FROM internships
    WHERE status IN ('active', 'approved')
    GROUP BY location
    ORDER BY count DESC
    LIMIT 10
");
$top_locations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// 12. Internship Type Distribution (Internship vs Job vs Both)
$result = $conn->query("
    SELECT internship_type, COUNT(*) as count 
    FROM internships 
    WHERE status IN ('active', 'approved')
    GROUP BY internship_type
");
$internship_types = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - SkillBridge Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Your Exact Color Variables */
:root {
    /* Sidebar */
    --sidebar-bg: #334155;
    --sidebar-text: #cbd5e1;
    --sidebar-active: #3b82f6;
    --sidebar-hover: rgba(59, 130, 246, 0.1);
    
    /* Main Content */
    --content-bg: #f8fafc;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    
    /* Text Colors */
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    
    /* Buttons */
    --btn-primary: #3b82f6;
    --btn-primary-hover: #2563eb;
    --btn-secondary: #6b7280;
    --btn-secondary-hover: #4b5563;
    
    /* Status Colors */
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #3b82f6;
}

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }

        /* Sidebar - Exact match to your theme */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background-color: #334155;
            color: #cbd5e1;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(203, 213, 225, 0.1);
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #ffffff;
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .sidebar-header .logo i {
            color: #3b82f6;
            font-size: 1.5rem;
        }

        /* Navigation */
        .nav-section {
            padding: 0.5rem 0;
        }

        .nav-section-title {
            padding: 0.75rem 1rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background-color: rgba(59, 130, 246, 0.1);
            color: #ffffff;
            border-left-color: #3b82f6;
        }

        .nav-link.active {
            background-color: rgba(59, 130, 246, 0.15);
            color: #ffffff;
            border-left-color: #3b82f6;
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #3b82f6;
        }

        /* User Profile in Sidebar */
        .user-profile {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background-color: rgba(0, 0, 0, 0.1);
            border-top: 1px solid rgba(203, 213, 225, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-details h4 {
            color: #ffffff;
            font-size: 0.9rem;
            margin-bottom: 0.125rem;
        }

        .user-details p {
            color: #94a3b8;
            font-size: 0.8rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 240px;
            min-height: 100vh;
            background-color: #f8fafc;
        }

        /* Header */
        .header {
            background: #ffffff;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-title h1 {
            color: #1e293b;
            font-size: 1.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .header-title p {
            color: #64748b;
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-icon.red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .stat-content h3 {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            color: #1e293b;
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
            margin-top: 1rem;
        }

        .stat-detail-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
        }

        .stat-detail-label {
            color: #64748b;
        }

        .stat-detail-value {
            font-weight: 600;
            color: #1e293b;
        }

        /* Charts Section */
        .charts-section {
            padding: 0 2rem 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #3b82f6;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .chart-header {
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .chart-subtitle {
            font-size: 0.875rem;
            color: #64748b;
        }

        .chart-container {
            position: relative;
            height: 350px;
        }

        .chart-container.small {
            height: 280px;
        }

        @media (max-width: 1400px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .header {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .charts-section {
                padding: 0 1rem 1rem;
            }

            .header-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }
        }

        @media print {
            .sidebar,
            .header-actions,
            .btn {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
            }
        }
        /* ==================== MAIN CONTENT AREA ==================== */

/* Analytics Header */
.header {
    background: #ffffff;
    padding: 2rem;
    border-bottom: 1px solid #e2e8f0;
}

.header-title h1 {
    color: #1e293b;
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
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
    font-weight: 400;
}

.header-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
}

/* Stats Grid */
/* ==================== ENHANCED STAT CARDS ==================== */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
    background: #f8fafc;
}

.stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

/* Gradient accent line at top */
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 32px rgba(59, 130, 246, 0.15);
    border-color: #3b82f6;
}

/* Different gradient accents for each card type */
.stat-card:nth-child(1)::before {
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
}

.stat-card:nth-child(2)::before {
    background: linear-gradient(90deg, #10b981, #059669);
}

.stat-card:nth-child(3)::before {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.stat-card:nth-child(4)::before {
    background: linear-gradient(90deg, #8b5cf6, #7c3aed);
}

.stat-card:nth-child(5)::before {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

/* Card Title */
.stat-card > h3 {
    color: #475569;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    text-align: center;
    margin-bottom: 1.5rem;
    position: relative;
    padding-bottom: 0.75rem;
}

.stat-card > h3::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 3px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    border-radius: 2px;
}

/* Main Stat Number */
.stat-number {
    color: #1e293b;
    font-size: 3.5rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 1.5rem;
    line-height: 1;
    background: linear-gradient(135deg, #1e293b, #475569);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Stat Details Section */
.stat-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-top: 1.25rem;
    border-top: 2px solid #f1f5f9;
}

.stat-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    transition: all 0.2s ease;
}

.stat-detail-item:hover {
    padding-left: 0.5rem;
    background: #f8fafc;
    border-radius: 0.5rem;
}

.stat-detail-label {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Add icon before label (optional) */
.stat-detail-label::before {
    content: '●';
    color: #cbd5e1;
    font-size: 0.5rem;
}

.stat-detail-value {
    color: #1e293b !important;
    font-size: 1.125rem;
    font-weight: 700;
    padding: 0.25rem 0.75rem;
    background: #e0f2fe;
    border-radius: 0.5rem;
}

.stat-detail-value-clean {
    color: #1e293b;
    font-size: 1.125rem;
    font-weight: 700;
}

/* ==================== CARD VARIATIONS WITH ICONS ==================== */

/* Add icon container at top-left */
.stat-card-with-icon {
    position: relative;
    padding-left: 2rem;
}

.stat-icon-badge {
    position: absolute;
    top: 1.5rem;
    right: 1.5rem;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Icon colors for each card */
.stat-card:nth-child(1) .stat-icon-badge {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
}

.stat-card:nth-child(2) .stat-icon-badge {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.stat-card:nth-child(3) .stat-icon-badge {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.stat-card:nth-child(4) .stat-icon-badge {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

.stat-card:nth-child(5) .stat-icon-badge {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

/* ==================== ALTERNATIVE GLASSMORPHISM STYLE ==================== */

.stat-card-glass {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

/* ==================== PERCENTAGE BARS (Optional Enhancement) ==================== */

.stat-progress {
    width: 100%;
    height: 4px;
    background: #f1f5f9;
    border-radius: 2px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.stat-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    border-radius: 2px;
    transition: width 0.6s ease;
}

/* ==================== LOADING ANIMATION ==================== */

@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

.stat-card.loading {
    background: linear-gradient(
        90deg,
        #f1f5f9 0%,
        #e2e8f0 50%,
        #f1f5f9 100%
    );
    background-size: 1000px 100%;
    animation: shimmer 2s infinite;
}

/* ==================== RESPONSIVE DESIGN ==================== */

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
    }

    .stat-number {
        font-size: 2.5rem;
    }

    .stat-card {
        padding: 1.5rem;
    }
}

/* Charts Section */
.charts-section {
    padding: 2rem;
    background: #f8fafc;
}

.section-title {
    color: #1e293b;
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title i {
    color: #3b82f6;
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 1.5rem;
}

.chart-card {
    background: #ffffff;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.chart-header {
    margin-bottom: 1.5rem;
}

.chart-title {
    color: #1e293b;
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.chart-subtitle {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 400;
}

.chart-container {
    position: relative;
    height: 350px;
}

.chart-container.small {
    height: 280px;
}

/* Responsive Design */
@media (max-width: 1400px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
    }

    .charts-section {
        padding: 1rem;
    }

    .header {
        padding: 1rem;
    }

    .header-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Print Styles */
@media print {
    .sidebar,
    .header-actions,
    .btn {
        display: none !important;
    }

    .main-content {
        margin-left: 0;
    }

    .stat-card,
    .chart-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
/* Alternative Stat Card Layout (If you prefer the layout in your screenshot) */
.stat-card-alt {
    background: #ffffff;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

/* Title at top */
.stat-card-alt .stat-title {
    color: #334155;
    font-size: 1rem;
    font-weight: 500;
    text-align: center;
    margin-bottom: 1.5rem;
}

/* Large number centered */
.stat-card-alt .stat-value {
    color: #1e293b;
    font-size: 3rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 1.5rem;
}

/* Sub-stats below */
.stat-card-alt .stat-breakdown {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

.stat-card-alt .stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
}

.stat-card-alt .stat-row-label {
    color: #64748b;
}

.stat-card-alt .stat-row-value {
    color: #1e293b;
    font-weight: 600;
}

    </style>

</head>
<body>
    <!-- Sidebar -->
    <!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-graduation-cap"></i>
            SkillBridge
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">DASHBOARD</div>
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard Overview
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">MANAGEMENT</div>
            <a href="company-approvals.php" class="nav-link">
                <i class="fas fa-building"></i>
                Company Approvals
            </a>
            <a href="internship-approvals.php" class="nav-link">
                <i class="fas fa-clock"></i>
                Internship Approvals
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i>
                User Management
            </a>
            <a href="internships.php" class="nav-link">
                <i class="fas fa-briefcase"></i>
                Internship Posts
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">SETTINGS</div>
            <a href="analytics.php" class="nav-link active">
                <i class="fas fa-chart-bar"></i>
                Analytics
            </a>
            <a href="../auth/logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>
    
    <!-- User Profile -->
    <div class="user-profile">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo $admin_initials; ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                <p>Administrator</p>
            </div>
        </div>
    </div>
</div>


    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <div class="header-title">
                    <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
                    <p>Comprehensive system analytics and insights</p>
                </div>
                <div class="header-actions">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Report
                    </button>
            <div class="dropdown" style="position: relative; display: inline-block;">
                <button class="btn btn-secondary" onclick="toggleExportMenu()">
                    <i class="fas fa-file-csv"></i> Export CSV
                    <i class="fas fa-chevron-down" style="font-size: 0.75rem; margin-left: 0.25rem;"></i>
                </button>
                <div id="exportMenu" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 0.5rem; background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 200px; z-index: 1000;">
                    <a href="export-csv.php?type=all" style="display: block; padding: 0.75rem 1rem; color: #1e293b; text-decoration: none; transition: background 0.2s;">
                        <i class="fas fa-database"></i> All Data
                    </a>
                    <a href="export-csv.php?type=users" style="display: block; padding: 0.75rem 1rem; color: #1e293b; text-decoration: none; border-top: 1px solid #f1f5f9; transition: background 0.2s;">
                        <i class="fas fa-users"></i> Users Only
                    </a>
                    <a href="export-csv.php?type=internships" style="display: block; padding: 0.75rem 1rem; color: #1e293b; text-decoration: none; border-top: 1px solid #f1f5f9; transition: background 0.2s;">
                        <i class="fas fa-briefcase"></i> Internships Only
                    </a>
                    <a href="export-csv.php?type=applications" style="display: block; padding: 0.75rem 1rem; color: #1e293b; text-decoration: none; border-top: 1px solid #f1f5f9; transition: background 0.2s;">
                        <i class="fas fa-file-alt"></i> Applications Only
                    </a>
                    <a href="export-csv.php?type=analytics" style="display: block; padding: 0.75rem 1rem; color: #1e293b; text-decoration: none; border-top: 1px solid #f1f5f9; border-radius: 0 0 0.5rem 0.5rem; transition: background 0.2s;">
                        <i class="fas fa-chart-bar"></i> Analytics Summary
                    </a>
        </div>
    </div>
</div>
            </div>
        </div>

        <style>
#exportMenu a:hover {
    background: #f8fafc;
}
</style>

<script>
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = event.target.closest('.dropdown');
    if (!dropdown) {
        document.getElementById('exportMenu').style.display = 'none';
    }
});
</script>
        <!-- Key Stats -->
<div class="stats-grid">
    <!-- Total Users Card -->
    <div class="stat-card">
        <div class="stat-icon-badge">
            <i class="fas fa-users"></i>
        </div>
        <h3>Total Users</h3>
        <div class="stat-number"><?php echo number_format($total_users); ?></div>
        <div class="stat-details">
            <div class="stat-detail-item">
                <span class="stat-detail-label">Students</span>
                <span class="stat-detail-value"><?php echo number_format($total_students); ?></span>
            </div>
            <div class="stat-detail-item">
                <span class="stat-detail-label">Companies</span>
                <span class="stat-detail-value"><?php echo number_format($total_companies); ?></span>
            </div>
        </div>
    </div>

    <!-- Internships Card -->
    <div class="stat-card">
        <div class="stat-icon-badge">
            <i class="fas fa-briefcase"></i>
        </div>
        <h3>Internships</h3>
        <div class="stat-number"><?php echo number_format($total_internships); ?></div>
        <div class="stat-details">
            <div class="stat-detail-item">
                <span class="stat-detail-label">Active</span>
                <span class="stat-detail-value"><?php echo number_format($active_internships); ?></span>
            </div>
            <div class="stat-detail-item">
                <span class="stat-detail-label">Pending Approval</span>
                <span class="stat-detail-value"><?php echo number_format($pending_internships); ?></span>
            </div>
        </div>
    </div>

    <!-- Applications Card -->
    <div class="stat-card">
        <div class="stat-icon-badge">
            <i class="fas fa-file-alt"></i>
        </div>
        <h3>Applications</h3>
        <div class="stat-number"><?php echo number_format($total_applications); ?></div>
        <div class="stat-details">
            <div class="stat-detail-item">
                <span class="stat-detail-label">Pending</span>
                <span class="stat-detail-value"><?php echo number_format($pending_applications); ?></span>
            </div>
            <div class="stat-detail-item">
                <span class="stat-detail-label">Selected</span>
                <span class="stat-detail-value"><?php echo number_format($selected_applications); ?></span>
            </div>
        </div>
    </div>

    <!-- Interviews Card -->
    <div class="stat-card">
        <div class="stat-icon-badge">
            <i class="fas fa-calendar-check"></i>
        </div>
        <h3>Interviews</h3>
        <div class="stat-number"><?php echo number_format($total_interviews); ?></div>
        <div class="stat-details">
            <div class="stat-detail-item">
                <span class="stat-detail-label">Scheduled</span>
                <span class="stat-detail-value"><?php echo number_format($scheduled_interviews); ?></span>
            </div>
            <div class="stat-detail-item">
                <span class="stat-detail-label">Completed</span>
                <span class="stat-detail-value"><?php echo number_format($completed_interviews); ?></span>
            </div>
        </div>
    </div>

    <!-- Conversion Rates Card -->
    <div class="stat-card">
        <div class="stat-icon-badge">
            <i class="fas fa-chart-pie"></i>
        </div>
        <h3>Overall Conversion</h3>
        <div class="stat-number"><?php echo $overall_conversion; ?>%</div>
        <div class="stat-details">
            <div class="stat-detail-item">
                <span class="stat-detail-label">App → Interview</span>
                <span class="stat-detail-value"><?php echo $application_to_interview; ?>%</span>
            </div>
            <div class="stat-detail-item">
                <span class="stat-detail-label">Interview → Selection</span>
                <span class="stat-detail-value"><?php echo $interview_to_selection; ?>%</span>
            </div>
        </div>
    </div>
</div>

        <!-- Charts Section -->
        <div class="charts-section">
            <h2 class="section-title"><i class="fas fa-chart-bar"></i> Detailed Analytics</h2>
            
            <div class="charts-grid">
                <!-- Applications Timeline -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Applications Over Time</h3>
                        <p class="chart-subtitle">Last 30 days trend</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="applicationsTimelineChart"></canvas>
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Application Status Distribution</h3>
                        <p class="chart-subtitle">Current status breakdown</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusDistributionChart"></canvas>
                    </div>
                </div>

                <!-- Student Registration Trend -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Student Registration Trend</h3>
                        <p class="chart-subtitle">Last 12 months</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="studentRegistrationChart"></canvas>
                    </div>
                </div>

                <!-- Interview Success Rate -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Interview Success Rate</h3>
                        <p class="chart-subtitle">Last 6 months performance</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="interviewSuccessChart"></canvas>
                    </div>
                </div>

                <!-- Top Companies -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Top Companies by Applications</h3>
                        <p class="chart-subtitle">Most active recruiters</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="topCompaniesChart"></canvas>
                    </div>
                </div>

                <!-- Internship Categories -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Top Internship Categories</h3>
                        <p class="chart-subtitle">Popular domains</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="topCategoriesChart"></canvas>
                    </div>
                </div>

                <!-- Location Types -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Location Type Distribution</h3>
                        <p class="chart-subtitle">Remote vs On-site vs Hybrid</p>
                    </div>
                    <div class="chart-container small">
                        <canvas id="locationTypeChart"></canvas>
                    </div>
                </div>

                <!-- CGPA Distribution -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Student CGPA Distribution</h3>
                        <p class="chart-subtitle">Academic performance spread</p>
                    </div>
                    <div class="chart-container small">
                        <canvas id="cgpaDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js Configuration
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        Chart.defaults.color = '#64748b';

        // 1. Applications Timeline Chart
        const timelineCtx = document.getElementById('applicationsTimelineChart').getContext('2d');
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($applications_timeline, 'date')); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_column($applications_timeline, 'count')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 2. Status Distribution Chart
        const statusCtx = document.getElementById('statusDistributionChart').getContext('2d');
        const statusData = <?php echo json_encode($status_distribution); ?>;
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
                datasets: [{
                    data: statusData.map(s => s.count),
                    backgroundColor: [
                        '#3b82f6',
                        '#8b5cf6',
                        '#f59e0b',
                        '#06b6d4',
                        '#10b981',
                        '#ef4444'
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12, weight: '600' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        cornerRadius: 8
                    }
                }
            }
        });

        // 3. Student Registration Trend
        const studentRegCtx = document.getElementById('studentRegistrationChart').getContext('2d');
        new Chart(studentRegCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($student_registration, 'month')); ?>,
                datasets: [{
                    label: 'New Students',
                    data: <?php echo json_encode(array_column($student_registration, 'count')); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 4. Interview Success Rate
        const successCtx = document.getElementById('interviewSuccessChart').getContext('2d');
        new Chart(successCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($interview_success, 'month')); ?>,
                datasets: [{
                    label: 'Success Rate (%)',
                    data: <?php echo json_encode(array_column($interview_success, 'rate')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return 'Success Rate: ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 5. Top Companies Chart
        const companiesCtx = document.getElementById('topCompaniesChart').getContext('2d');
        const companiesData = <?php echo json_encode($top_companies); ?>;
        new Chart(companiesCtx, {
            type: 'bar',
            data: {
                labels: companiesData.map(c => c.name),
                datasets: [{
                    label: 'Applications',
                    data: companiesData.map(c => c.application_count),
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    borderColor: '#f59e0b',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 6. Top Categories Chart
        const categoriesCtx = document.getElementById('topCategoriesChart').getContext('2d');
        const categoriesData = <?php echo json_encode($top_categories); ?>;
        new Chart(categoriesCtx, {
            type: 'bar',
            data: {
                labels: categoriesData.map(c => c.category),
                datasets: [{
                    label: 'Internships',
                    data: categoriesData.map(c => c.count),
                    backgroundColor: [
                        '#667eea',
                        '#10b981',
                        '#f59e0b',
                        '#3b82f6',
                        '#ef4444',
                        '#8b5cf6',
                        '#06b6d4',
                        '#ec4899',
                        '#14b8a6'
                    ],
                    borderWidth: 0,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 7. Location Type Chart
        const locationTypeCtx = document.getElementById('locationTypeChart').getContext('2d');
        const locationTypeData = <?php echo json_encode($location_type_dist); ?>;
        new Chart(locationTypeCtx, {
            type: 'pie',
            data: {
                labels: locationTypeData.map(t => t.location_type),
                datasets: [{
                    data: locationTypeData.map(t => t.count),
                    backgroundColor: [
                        '#06b6d4',
                        '#8b5cf6',
                        '#f59e0b'
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12, weight: '600' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8
                    }
                }
            }
        });

        // 8. CGPA Distribution Chart
        const cgpaCtx = document.getElementById('cgpaDistributionChart').getContext('2d');
        const cgpaData = <?php echo json_encode($cgpa_distribution); ?>;
        new Chart(cgpaCtx, {
            type: 'doughnut',
            data: {
                labels: cgpaData.map(c => c.cgpa_range),
                datasets: [{
                    data: cgpaData.map(c => c.count),
                    backgroundColor: [
                        '#10b981',
                        '#3b82f6',
                        '#f59e0b',
                        '#ef4444',
                        '#64748b'
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12, weight: '600' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8
                    }
                }
            }
        });
    </script>
</body>
</html>
