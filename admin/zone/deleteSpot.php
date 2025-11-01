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

// Get spot info for image deletion
$spot_query = "SELECT spot_image FROM FISHING_SPOT WHERE spot_id = '$spot_id'";
$spot_result = mysqli_query($conn, $spot_query);

if ($spot_result && mysqli_num_rows($spot_result) > 0) {
    $spot = mysqli_fetch_assoc($spot_result);
    
    // Delete spot
    $delete_query = "DELETE FROM FISHING_SPOT WHERE spot_id = '$spot_id'";
    
    if (mysqli_query($conn, $delete_query)) {
        // Delete image if exists
        if (!empty($spot['spot_image'])) {
            deleteFile($spot['spot_image'], 'spots');
        }
        
        $_SESSION['success'] = 'Spot deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete spot: ' . mysqli_error($conn);
    }
} else {
    $_SESSION['error'] = 'Spot not found';
}

redirect(SITE_URL . '/admin/zone/viewZone.php?id=' . $zone_id);
?>