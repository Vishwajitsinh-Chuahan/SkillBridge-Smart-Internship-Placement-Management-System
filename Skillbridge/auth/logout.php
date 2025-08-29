<?php
session_start();

// Clear user session data
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['email']);
unset($_SESSION['role']);

// Set logout success message
$_SESSION['logout_success'] = true;

// Redirect to home page
header("Location: ../index.php");
exit();
?>
