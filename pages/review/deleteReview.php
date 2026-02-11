<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/pages/authentication/login.php');
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Review ID is missing!';
    redirect(SITE_URL . '/pages/review/myReviews.php');
}

$user_id = $_SESSION['user_id'];
$review_id = intval($_GET['id']);

$delete_query = "DELETE FROM REVIEW WHERE review_id = $review_id AND user_id = $user_id";

if (mysqli_query($conn, $delete_query)) {
    if (mysqli_affected_rows($conn) > 0) {
        $_SESSION['success'] = 'Review deleted successfully!';
    } else {
        $_SESSION['error'] = 'Review not found or you do not have permission to delete it!';
    }
} else {
    $_SESSION['error'] = 'Failed to delete review: ' . mysqli_error($conn);
}

redirect(SITE_URL . '/pages/review/myReviews.php');
?>
