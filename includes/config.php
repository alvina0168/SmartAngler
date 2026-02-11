<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smartangler');

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (strpos($host, 'ngrok') !== false) {
    define('SITE_URL', 'https://' . $host . '/SmartAngler');
} else {
    define('SITE_URL', 'http://' . $host . '/SmartAngler');
}

define('SITE_NAME', 'SmartAngler');
define('SESSION_TIMEOUT', 3600); 
define('UPLOAD_PATH', __DIR__ . '/../assets/images/');
define('MAX_FILE_SIZE', 5242880); 
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
date_default_timezone_set('Asia/Kuala_Lumpur');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
?>