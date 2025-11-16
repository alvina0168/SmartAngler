<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id'])) {
    redirect("notificationList.php");
}

$id = intval($_GET['id']);
mysqli_query($conn, "DELETE FROM NOTIFICATION WHERE notification_id=$id");

$_SESSION['success'] = "Notification deleted successfully!";
redirect("notificationList.php");
