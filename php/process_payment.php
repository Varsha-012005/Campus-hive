<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied");
}

$student_id = $_SESSION['user_id'];

// Validate input
$required = ['amount', 'payment_method'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        header("HTTP/1.1 400 Bad Request");
        exit("Missing required field: $field");
    }
}

$amount = (float)$_POST['amount'];
$payment_method = $_POST['payment_method'];

// Basic validation
if ($amount <= 0) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid payment amount");
}

try {
    $pdo->beginTransaction();

    // Record the payment transaction
    $stmt = $pdo->prepare("
        INSERT INTO financial_transactions 
        (student_id, type, amount, description, status) 
        VALUES (?, 'payment', ?, 'Online payment', 'completed')
    ");
    $stmt->execute([$student_id, $amount]);

    // If this is a new payment method, save it
    if (isset($_POST['card_number']) && !empty($_POST['card_number'])) {
        $stmt = $pdo->prepare("
            INSERT INTO payment_methods 
            (student_id, method_type, card_number, expiry_date, cvv, is_default) 
            VALUES (?, ?, ?, ?, ?, TRUE)
        ");
        $stmt->execute([
            $student_id,
            $payment_method,
            $_POST['card_number'],
            $_POST['expiry_date'],
            $_POST['cvv']
        ]);
    }

    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    header("HTTP/1.1 500 Internal Server Error");
    exit("Payment processing failed: " . $e->getMessage());
}