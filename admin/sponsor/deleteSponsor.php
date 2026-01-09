<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id']) || !isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Missing parameters!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$sponsor_id = intval($_GET['id']);
$tournament_id = intval($_GET['tournament_id']);

// Check if sponsor has linked prizes
$check_query = "SELECT COUNT(*) as count FROM TOURNAMENT_PRIZE WHERE sponsor_id = $sponsor_id";
$check_result = mysqli_query($conn, $check_query);
$check = mysqli_fetch_assoc($check_result);

if ($check['count'] > 0) {
    $_SESSION['warning'] = "Sponsor deleted. {$check['count']} prize(s) have been unlinked.";
    
    // Unlink prizes
    $unlink_query = "UPDATE TOURNAMENT_PRIZE SET sponsor_id = NULL WHERE sponsor_id = $sponsor_id";
    mysqli_query($conn, $unlink_query);
}

// Delete sponsor
$delete_query = "DELETE FROM SPONSOR WHERE sponsor_id = $sponsor_id";

if (mysqli_query($conn, $delete_query)) {
    if ($check['count'] == 0) {
        $_SESSION['success'] = 'Sponsor deleted successfully!';
    }
} else {
    $_SESSION['error'] = 'Failed to delete sponsor: ' . mysqli_error($conn);
}

redirect(SITE_URL . '/admin/sponsor/sponsorList.php?tournament_id=' . $tournament_id);
?>