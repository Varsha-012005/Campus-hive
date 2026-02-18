<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/update_attendance.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['username']) || empty($_POST['password'])) {
            http_response_code(400);
            die(json_encode(['error' => 'Username and password are required.']));
        }
        
        // Get user from database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($_POST['password'], $user['password'])) {
            http_response_code(401);
            die(json_encode(['error' => 'Invalid username or password.']));
        }
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            http_response_code(403);
            die(json_encode(['error' => 'Your account is inactive. Please contact administrator.']));
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Record attendance for staff members
        if (in_array($user['role'], ['hr', 'faculty', 'admin', 'finance', 'campus'])) {
            updateAttendance($user['id'], 'login');
        }
        
        // Prepare response
        $response = ['success' => true];
        switch ($user['role']) {
            case 'admin': $response['redirect'] = 'php/admin/dashboard.php'; break;
            case 'faculty': $response['redirect'] = 'php/faculty/dashboard.php'; break;
            case 'student': $response['redirect'] = 'php/student/dashboard.php'; break;
            case 'hr': $response['redirect'] = 'php/hr/dashboard.php'; break;
            case 'finance': $response['redirect'] = 'php/finance/dashboard.php'; break;
            case 'campus': $response['redirect'] = 'php/campus/dashboard.php'; break;
            default: $response['redirect'] = 'index.php';
        }
        
        die(json_encode($response));
    } catch (Exception $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Internal server error.']));
    }
}
?>