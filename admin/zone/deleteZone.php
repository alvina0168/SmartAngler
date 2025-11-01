<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Zone ID is required';
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$zone_id = intval($_GET['id']);

// Delete zone (CASCADE will delete all spots)
$delete_query = "DELETE FROM ZONE WHERE zone_id = '$zone_id'";

if (mysqli_query($conn, $delete_query)) {
    $_SESSION['success'] = 'Zone and all its spots deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete zone: ' . mysqli_error($conn);
}

redirect(SITE_URL . '/admin/zone/zoneList.php');
?>