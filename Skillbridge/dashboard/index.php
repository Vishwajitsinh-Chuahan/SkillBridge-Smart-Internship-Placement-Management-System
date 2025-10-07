<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

// Redirect to role-specific dashboard
switch ($_SESSION['role']) {
    case 'Admin':
        header("Location: " . BASE_URL . "/admin/index.php");
        break;
    case 'Organizer':
        header("Location: " . BASE_URL . "/dashboard/organizer.php");
        break;
    case 'Attendee':
        header("Location: " . BASE_URL . "/dashboard/attendee.php");
        break;
    default:
        header("Location: " . BASE_URL . "/auth/logout.php");
}
exit();
?>
