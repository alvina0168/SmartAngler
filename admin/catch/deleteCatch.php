<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id']) || !isset($_GET['station_id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$catch_id = intval($_GET['id']);
$station_id = intval($_GET['station_id']);

// Delete the catch record
$delete_query = "DELETE FROM FISH_CATCH WHERE catch_id = '$catch_id'";

if (mysqli_query($conn, $delete_query)) {
    $_SESSION['success'] = 'Catch record deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete catch record';
}

redirect(SITE_URL . '/admin/catch/catchList.php?station_id=' . $station_id);
?>