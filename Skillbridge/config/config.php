<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();}
// Application settings
define('APP_NAME', 'Skillbridge');
define('BASE_URL', 'http://localhost/Minor Project\SkillBridge-Smart-Internship-Placement-Management-System/Skillbridge');

// Include database connection
require_once 'database.php';
?>
