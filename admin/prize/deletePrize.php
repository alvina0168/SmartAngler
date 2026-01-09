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

$prize_id = intval($_GET['id']);
$tournament_id = intval($_GET['tournament_id']);

// Delete prize
$delete_query = "DELETE FROM TOURNAMENT_PRIZE WHERE prize_id = $prize_id";

if (mysqli_query($conn, $delete_query)) {
    $_SESSION['success'] = 'Prize deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete prize: ' . mysqli_error($conn);
}

redirect(SITE_URL . '/admin/prize/managePrize.php?tournament_id=' . $tournament_id);
?>