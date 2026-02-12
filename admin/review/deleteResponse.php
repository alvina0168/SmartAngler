<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Review ID is missing!';
    redirect(SITE_URL . '/admin/review/allReviews.php');
}

$review_id = intval($_GET['id']);
$get_tournament_query = "SELECT tournament_id FROM REVIEW WHERE review_id = $review_id";
$result = mysqli_query($conn, $get_tournament_query);
$tournament_id = mysqli_fetch_assoc($result)['tournament_id'] ?? null;

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

if (isset($_GET['redirect']) && $_GET['redirect'] == 'all') {
    redirect(SITE_URL . '/admin/review/allReviews.php');
} elseif ($tournament_id) {
    redirect(SITE_URL . '/admin/review/reviewList.php?tournament_id=' . $tournament_id);
} else {
    redirect(SITE_URL . '/admin/review/allReviews.php');
}
?>
