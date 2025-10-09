<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

// Get student ID from URL
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id <= 0) {
    header('HTTP/1.0 400 Bad Request');
    die('Invalid student ID');
}

// Fetch resume path from database
$stmt = $conn->prepare("SELECT resume_path FROM student_profiles WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.0 404 Not Found');
    die('Resume not found');
}

$student = $result->fetch_assoc();
$resume_path = $student['resume_path'];

if (empty($resume_path)) {
    header('HTTP/1.0 404 Not Found');
    die('No resume uploaded');
}

// Clean the path - remove any 'uploads/resumes/' prefix
$resume_path = str_replace('uploads/resumes/', '', $resume_path);
$filename = basename($resume_path);

// Build the actual file path on server
$file_path = $_SERVER['DOCUMENT_ROOT'] . '/Skillbridge/uploads/resumes/' . $filename;

// Check if file exists
if (!file_exists($file_path)) {
    // Try alternative path (in case it's stored with full path)
    $alt_path = $_SERVER['DOCUMENT_ROOT'] . '/Skillbridge/' . $resume_path;
    if (file_exists($alt_path)) {
        $file_path = $alt_path;
    } else {
        header('HTTP/1.0 404 Not Found');
        die('Resume file not found on server: ' . $filename);
    }
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clear output buffer
ob_clean();
flush();

// Read and output the file
readfile($file_path);
exit;
?>
