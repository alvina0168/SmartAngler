<?php
/**
 * SmartAngler Configuration File
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smartangler');

// Site Configuration
define('SITE_NAME', 'SmartAngler');
define('SITE_URL', 'http://localhost/smartangler');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../assets/images/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");
?>