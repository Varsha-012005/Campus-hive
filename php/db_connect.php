<?php
require_once __DIR__ . '/config.php';

date_default_timezone_set('Asia/Kolkata'); // Change to your appropriate timezone

$host = 'localhost';
$dbname = 'university_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Set MySQL timezone to match PHP
    $pdo->exec("SET time_zone = '+05:30'"); // Adjust offset for your timezone

    // Display success message
    echo "Database connected successfully!";

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>