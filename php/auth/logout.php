<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/update_attendance.php';
session_start();

// Record attendance logout for staff members
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && 
    in_array($_SESSION['role'], ['hr', 'faculty', 'admin', 'finance', 'campus'])) {
    updateAttendance($_SESSION['user_id'], 'logout');
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: /university-system/login.html");
exit();
?>