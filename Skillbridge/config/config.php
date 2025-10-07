<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();}
// Application settings
define('APP_NAME', 'Skillbridge');
define('BASE_URL', 'http://localhost/Skillbridge');

// Include database connection
require_once 'database.php';
?>
