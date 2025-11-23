<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Store user name before destroying session
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';

// Unset all session variables
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Start new session for flash message
session_start();
setFlashMessage('You have been logged out successfully. See you again soon!', 'success');

// Redirect to home page
redirect(SITE_URL . '/index.php');
?>