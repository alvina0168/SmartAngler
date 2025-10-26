<?php
/**
 * SmartAngler Helper Functions
 */

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Get user information by user ID
function getUserInfo($user_id) {
    global $conn;
    
    if (empty($user_id)) {
        return null;
    }
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $query = "SELECT * FROM USER WHERE user_id = '$user_id' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Get tournament information by tournament ID
function getTournamentInfo($tournament_id) {
    global $conn;
    
    if (empty($tournament_id)) {
        return null;
    }
    
    $tournament_id = mysqli_real_escape_string($conn, $tournament_id);
    $query = "SELECT * FROM TOURNAMENT WHERE tournament_id = '$tournament_id' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Get user's tournament count
function getUserTournamentCount($user_id) {
    global $conn;
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $query = "SELECT COUNT(*) as total FROM TOURNAMENT_REGISTRATION WHERE user_id = '$user_id'";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['total'];
    }
    
    return 0;
}

// Get user's catches count
function getUserCatchesCount($user_id) {
    global $conn;
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $query = "SELECT COUNT(*) as total FROM FISH_CATCH WHERE user_id = '$user_id'";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['total'];
    }
    
    return 0;
}

// Get user's wins count
function getUserWinsCount($user_id) {
    global $conn;
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $query = "SELECT COUNT(*) as total FROM RESULT WHERE user_id = '$user_id' AND ranking_position = 1";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['total'];
    }
    
    return 0;
}

// Check if email exists
function emailExists($email, $exclude_user_id = null) {
    global $conn;
    
    $email = mysqli_real_escape_string($conn, $email);
    $query = "SELECT COUNT(*) as count FROM USER WHERE email = '$email'";
    
    if ($exclude_user_id) {
        $exclude_user_id = mysqli_real_escape_string($conn, $exclude_user_id);
        $query .= " AND user_id != '$exclude_user_id'";
    }
    
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['count'] > 0;
    }
    
    return false;
}

// Check if user is registered for tournament (mysqli version)
function isUserRegisteredForTournament($user_id, $tournament_id) {
    global $conn;
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $tournament_id = mysqli_real_escape_string($conn, $tournament_id);
    
    $query = "SELECT COUNT(*) as count FROM TOURNAMENT_REGISTRATION 
              WHERE user_id = '$user_id' AND tournament_id = '$tournament_id' 
              AND approval_status IN ('pending', 'approved')";
    
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['count'] > 0;
    }
    
    return false;
}

// Check if tournament is full (mysqli version)
function isTournamentFullCheck($tournament_id) {
    global $conn;
    
    $tournament_id = mysqli_real_escape_string($conn, $tournament_id);
    
    $query = "SELECT t.max_participants, 
              (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
               WHERE tournament_id = t.tournament_id 
               AND approval_status IN ('pending', 'approved')) as registered
              FROM TOURNAMENT t 
              WHERE t.tournament_id = '$tournament_id'";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['registered'] >= $row['max_participants'];
    }
    
    return false;
}

// Get current logged in user
function getCurrentUser($db) {
    if (!isLoggedIn()) {
        return null;
    }
    
    $userId = $_SESSION['user_id'];
    $sql = "SELECT * FROM USER WHERE user_id = ?";
    return $db->fetchOne($sql, [$userId]);
}

// Check session timeout
function checkSessionTimeout() {
    if (isLoggedIn()) {
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > SESSION_TIMEOUT) {
                session_unset();
                session_destroy();
                setFlashMessage('Your session has expired. Please login again.', 'warning');
                redirect(SITE_URL . '/login.php');
            }
        }
        $_SESSION['last_activity'] = time();
    }
}

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Format date
function formatDate($date) {
    if (empty($date)) return '';
    return date('d M Y', strtotime($date));
}

// Format time
function formatTime($time) {
    if (empty($time)) return '';
    return date('h:i A', strtotime($time));
}

// Format datetime
function formatDateTime($datetime) {
    if (empty($datetime)) return '';
    return date('d M Y h:i A', strtotime($datetime));
}

// Get user profile image
function getUserProfileImage($image) {
    if (!empty($image) && file_exists(__DIR__ . '/../assets/images/profiles/' . $image)) {
        return SITE_URL . '/assets/images/profiles/' . $image;
    }
    return SITE_URL . '/assets/images/default-avatar.png';
}

// Flash message functions
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Upload file
function uploadFile($file, $folder = 'uploads') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    // Create directory if not exists
    $targetDir = UPLOAD_PATH . $folder . '/';
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $targetDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    }
    
    return false;
}

// Delete file
function deleteFile($filename, $folder = 'uploads') {
    if (empty($filename)) {
        return false;
    }
    
    $filePath = UPLOAD_PATH . $folder . '/' . $filename;
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    
    return false;
}

// Generate random string
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate phone
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

// Get tournament status badge
function getStatusBadge($status) {
    $badges = [
        'upcoming' => 'badge-info',
        'ongoing' => 'badge-warning',
        'completed' => 'badge-success',
        'cancelled' => 'badge-error'
    ];
    
    return $badges[$status] ?? 'badge-info';
}

// Get approval status badge
function getApprovalBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'approved' => 'badge-success',
        'rejected' => 'badge-error',
        'cancelled' => 'badge-error'
    ];
    
    return $badges[$status] ?? 'badge-info';
}

// Calculate age
function calculateAge($birthdate) {
    $birthDate = new DateTime($birthdate);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// Truncate text
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . $suffix;
    }
    return $text;
}

// Format currency
function formatCurrency($amount) {
    return 'RM ' . number_format($amount, 2);
}

// Check if tournament is full
function isTournamentFull($db, $tournamentId) {
    $sql = "SELECT t.max_participants, 
            (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
             WHERE tournament_id = t.tournament_id 
             AND approval_status IN ('pending', 'approved')) as registered
            FROM TOURNAMENT t 
            WHERE t.tournament_id = ?";
    
    $result = $db->fetchOne($sql, [$tournamentId]);
    
    if ($result) {
        return $result['registered'] >= $result['max_participants'];
    }
    
    return false;
}

// Check if user is registered for tournament
function isUserRegistered($db, $userId, $tournamentId) {
    $sql = "SELECT COUNT(*) as count FROM TOURNAMENT_REGISTRATION 
            WHERE user_id = ? AND tournament_id = ? 
            AND approval_status IN ('pending', 'approved')";
    
    $result = $db->fetchOne($sql, [$userId, $tournamentId]);
    return $result && $result['count'] > 0;
}

// Get available spots count
function getAvailableSpots($db, $tournamentId) {
    $sql = "SELECT COUNT(*) as count FROM FISHING_SPOT 
            WHERE tournament_id = ? AND spot_status = 'available'";
    
    $result = $db->fetchOne($sql, [$tournamentId]);
    return $result ? $result['count'] : 0;
}

// Send notification
function sendNotification($db, $userId, $title, $message) {
    $sql = "INSERT INTO NOTIFICATION (user_id, title, message) VALUES (?, ?, ?)";
    return $db->insert($sql, [$userId, $title, $message]);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('Please login to access this page.', 'warning');
        redirect(SITE_URL . '/login.php');
    }
}

// Require admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlashMessage('Access denied. Admin privileges required.', 'error');
        redirect(SITE_URL . '/index.php');
    }
}

// Get unread notifications count
function getUnreadNotificationsCount($db, $userId) {
    $sql = "SELECT COUNT(*) as count FROM NOTIFICATION 
            WHERE user_id = ? AND sent_date > DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    $result = $db->fetchOne($sql, [$userId]);
    return $result ? $result['count'] : 0;
}
?>