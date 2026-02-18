<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Validate inputs
    $required = ['role', 'firstName', 'lastName', 'email', 'username', 'password', 'confirmPassword'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            die(json_encode(['error' => "$field is required."]));
        }
    }

    if ($_POST['password'] !== $_POST['confirmPassword']) {
        http_response_code(400);
        die(json_encode(['error' => "Passwords do not match."]));
    }

    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$_POST['username'], $_POST['email']]);
    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        die(json_encode(['error' => "Username or email already exists."]));
    }

    // Hash password
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Insert into users table
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, first_name, last_name, status) 
                              VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $_POST['username'],
            $hashedPassword,
            $_POST['role'],
            $_POST['email'],
            $_POST['firstName'],
            $_POST['lastName']
        ]);
        $userId = $pdo->lastInsertId();

        // Handle role-specific data
        switch ($_POST['role']) {
            case 'student':
                if (empty($_POST['studentId']) || empty($_POST['program'])) {
                    throw new Exception("Student ID and Program are required.");
                }
                $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, program, enrollment_date) 
                              VALUES (?, ?, ?, CURDATE())");
                $stmt->execute([$userId, $_POST['studentId'], $_POST['program']]);
                break;

            case 'faculty':
                $stmt = $pdo->prepare("INSERT INTO faculty (user_id, faculty_id, hire_date) 
                              VALUES (?, ?, CURDATE())");
                $facultyId = 'FAC' . str_pad($userId, 5, '0', STR_PAD_LEFT);
                $stmt->execute([$userId, $facultyId]);
                break;

            case 'hr':
                $stmt = $pdo->prepare("INSERT INTO hr_staff (user_id, hr_id, hire_date) 
                              VALUES (?, ?, CURDATE())");
                $hrId = 'HR' . str_pad($userId, 5, '0', STR_PAD_LEFT);
                $stmt->execute([$userId, $hrId]);
                break;

            case 'finance':
                $stmt = $pdo->prepare("INSERT INTO finance_staff (user_id, finance_id, hire_date) 
                              VALUES (?, ?, CURDATE())");
                $financeId = 'FIN' . str_pad($userId, 5, '0', STR_PAD_LEFT);
                $stmt->execute([$userId, $financeId]);
                break;

            case 'campus':
                $stmt = $pdo->prepare("INSERT INTO campus_staff (user_id, campus_id, hire_date) 
                              VALUES (?, ?, CURDATE())");
                $campusId = 'CAMP' . str_pad($userId, 5, '0', STR_PAD_LEFT);
                $stmt->execute([$userId, $campusId]);
                break;

            case 'admin':
                $stmt = $pdo->prepare("INSERT INTO administrators (user_id, admin_id, access_level) 
                              VALUES (?, ?, ?)");
                $adminId = 'ADM' . str_pad($userId, 5, '0', STR_PAD_LEFT);
                $stmt->execute([$userId, $adminId, 'full']);
                break;

            default:
                throw new Exception("Invalid role selected.");
        }

        $pdo->commit();
        die(json_encode(['success' => true]));
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        die(json_encode(['error' => $e->getMessage()]));
    }
}
?>