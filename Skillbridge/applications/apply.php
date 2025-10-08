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
$internship_id = (int)($_GET['id'] ?? 0);

if ($internship_id <= 0) {
    $_SESSION['error'] = 'Invalid internship ID.';
    header('Location: ../internships/browse.php');
    exit();
}

// ✅ CHECK 1: Profile Completion
if (!canApplyForInternships($user_id, $conn)) {
    $_SESSION['error'] = 'Please complete your profile (100%) before applying to internships.';
    header('Location: ../dashboard/profile.php');
    exit();
}

// Fetch internship details
$query = "
    SELECT 
        i.*,
        c.name as company_name,
        (SELECT COUNT(*) FROM applications WHERE internship_id = i.id) as application_count,
        (SELECT COUNT(*) FROM applications WHERE internship_id = i.id AND student_id = ?) as has_applied
    FROM internships i
    JOIN companies c ON i.company_id = c.user_id
    WHERE i.id = ?
    AND i.status IN ('approved', 'active')
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $internship_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Internship not found or no longer available.';
    header('Location: ../internships/browse.php');
    exit();
}

$internship = $result->fetch_assoc();

// ✅ CHECK 2: Already Applied
if ($internship['has_applied'] > 0) {
    $_SESSION['error'] = 'You have already applied for this internship.';
    header('Location: ../internships/view-details.php?id=' . $internship_id);
    exit();
}

// ✅ CHECK 3: Positions Available
$positions_left = $internship['positions_available'] - $internship['application_count'];
if ($positions_left <= 0) {
    $_SESSION['error'] = 'All positions for this internship have been filled.';
    header('Location: ../internships/view-details.php?id=' . $internship_id);
    exit();
}

// ✅ CHECK 4: Deadline
if (strtotime($internship['application_deadline']) < strtotime('today')) {
    $_SESSION['error'] = 'The application deadline for this internship has passed.';
    header('Location: ../internships/view-details.php?id=' . $internship_id);
    exit();
}

// ✅ ALL CHECKS PASSED - Submit Application
$stmt = $conn->prepare("INSERT INTO applications (internship_id, student_id, status) VALUES (?, ?, 'pending')");
$stmt->bind_param("ii", $internship_id, $user_id);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Application submitted successfully! The company will review your profile.';
    header('Location: my-applications.php');
    exit();
} else {
    $_SESSION['error'] = 'Failed to submit application. Please try again...!';
    header('Location: ../internships/view-details.php?id=' . $internship_id);
    exit();
}
?>
