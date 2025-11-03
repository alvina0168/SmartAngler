<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id']) || !isset($_GET['tournament_id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$station_id = intval($_GET['id']);
$tournament_id = intval($_GET['tournament_id']);

// Delete all catches for this station first
$delete_catches = "DELETE FROM FISH_CATCH WHERE station_id = '$station_id'";
mysqli_query($conn, $delete_catches);

// Delete the station
$delete_station = "DELETE FROM WEIGHING_STATION WHERE station_id = '$station_id'";

if (mysqli_query($conn, $delete_station)) {
    $_SESSION['success'] = 'Weighing station deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete station';
}

redirect(SITE_URL . '/admin/catch/stationList.php?tournament_id=' . $tournament_id);
?>