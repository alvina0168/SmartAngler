<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Category ID is missing!';
    redirect(SITE_URL . '/admin/category/categoryList.php');
}

$category_id = intval($_GET['id']);

// Check if category is being used
$check_query = "SELECT COUNT(*) as count FROM TOURNAMENT_PRIZE WHERE category_id = $category_id";
$check_result = mysqli_query($conn, $check_query);
$check = mysqli_fetch_assoc($check_result);

if ($check['count'] > 0) {
    $_SESSION['error'] = "Cannot delete this category! It is being used by {$check['count']} prize(s). Please reassign or delete those prizes first.";
    redirect(SITE_URL . '/admin/category/categoryList.php');
}

// Delete category
$delete_query = "DELETE FROM CATEGORY WHERE category_id = $category_id";

if (mysqli_query($conn, $delete_query)) {
    $_SESSION['success'] = 'Category deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete category: ' . mysqli_error($conn);
}

redirect(SITE_URL . '/admin/category/categoryList.php');
?>