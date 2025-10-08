<?php
function sendNotification($student_id, $application_id, $type, $title, $message, $conn) {
    $stmt = $conn->prepare("INSERT INTO notifications (student_id, application_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $student_id, $application_id, $type, $title, $message);
    return $stmt->execute();
}

// Get unread notification count
function getUnreadNotificationCount($student_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE student_id = ? AND is_read = 0");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}
?>
