<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Allow both organizer and admin
requireAdminAccess();

if (!isset($_GET['id'])) {
    redirect("notificationList.php");
}

$id = intval($_GET['id']);
mysqli_query($conn, "DELETE FROM NOTIFICATION WHERE notification_id=$id");

$_SESSION['success'] = "Notification deleted successfully!";
redirect("notificationList.php");