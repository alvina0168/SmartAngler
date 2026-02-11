<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';

$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();
session_start();
setFlashMessage('You have been logged out successfully. See you again soon!', 'success');
redirect(SITE_URL . '/index.php');
?>