<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id']) || !isset($_GET['zone_id'])) {
    $_SESSION['error'] = 'Spot ID and Zone ID are required';
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$spot_id = intval($_GET['id']);
$zone_id = intval($_GET['zone_id']);

// Delete spot
$delete_query = "DELETE FROM FISHING_SPOT WHERE spot_id = '$spot_id'";

if (mysqli_query($conn, $delete_query)) {
    $_SESSION['success'] = 'Spot deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete spot: ' . mysqli_error($conn);
}

redirect(SITE_URL . '/admin/zone/viewZone.php?id=' . $zone_id);
?>