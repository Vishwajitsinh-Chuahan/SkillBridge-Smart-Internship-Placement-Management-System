<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$internship_id = (int)($_POST['internship_id'] ?? 0);

if (empty($action) || $internship_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    if ($action === 'save') {
        // Check if internship exists and is active
        $check_internship = $conn->prepare("SELECT id FROM internships WHERE id = ? AND status IN ('approved', 'active')");
        $check_internship->bind_param("i", $internship_id);
        $check_internship->execute();
        
        if ($check_internship->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Internship not found']);
            exit();
        }
        
        // Save internship
        $stmt = $conn->prepare("INSERT IGNORE INTO saved_internships (student_id, internship_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $internship_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Internship saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save internship']);
        }
        
    } else if ($action === 'unsave') {
        // Remove saved internship
        $stmt = $conn->prepare("DELETE FROM saved_internships WHERE student_id = ? AND internship_id = ?");
        $stmt->bind_param("ii", $user_id, $internship_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Internship removed from saved']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove internship']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Save internship error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong']);
}
?>
