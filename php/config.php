<?php
// Database Configuration
define('DB_HOST', 'localhost'); // or another host if needed
define('DB_USER', 'root');      // MySQL username
define('DB_PASS', '');          // MySQL password
define('DB_NAME', 'university_management'); // Database name

// Base URL (for links and redirects)
define('BASE_URL', 'http://localhost/university-system/'); // Change this on another system

// File Upload Paths
define('UPLOAD_ASSIGNMENTS_DIR', __DIR__ . '/../uploads/assignments/');
define('UPLOAD_RESUMES_DIR', __DIR__ . '/../uploads/resumes/');

// Session & Security Settings
define('SESSION_NAME', 'SECURE_SESSION_ID');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Turn off in production (`0`)

// Configuration loaded message
echo "Configuration loaded successfully!<br>";
echo "Database: " . DB_NAME . " configured for host: " . DB_HOST . "<br>";
echo "Base URL set to: " . BASE_URL . "<br>";

// Include this in other PHP files like:
// require_once __DIR__ . '/config.php';
?>