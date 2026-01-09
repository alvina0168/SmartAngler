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

$review_id = intval($_GET['id']);
$tournament_id = intval($_GET['tournament_id']);

// Delete response (set to NULL)
$update_query = "
    UPDATE REVIEW SET
        admin_response = NULL,
        response_date = NULL
    WHERE review_id = $review_id
";

if (mysqli_query($conn, $update_query)) {
    $_SESSION['success'] = 'Response deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete response: ' . mysqli_error($conn);
}

redirect(SITE_URL . '/admin/review/reviewList.php?tournament_id=' . $tournament_id);
?>