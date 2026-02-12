<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireOrganizer();

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Invalid admin ID';
    header('Location: ' . SITE_URL . '/admin/admin-management/manage-admins.php');
    exit;
}

$admin_id = intval($_GET['id']);
$organizer_id = $_SESSION['user_id'];

// Verify this admin was created by current organizer
$query = "SELECT full_name FROM USER 
          WHERE user_id = '$admin_id' 
          AND role = 'admin' 
          AND created_by = '$organizer_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Admin not found or access denied';
    header('Location: ' . SITE_URL . '/admin/admin-management/manage-admins.php');
    exit;
}

$admin = mysqli_fetch_assoc($result);

// Delete the admin
$delete_query = "DELETE FROM USER WHERE user_id = '$admin_id'";

if (mysqli_query($conn, $delete_query)) {
    $_SESSION['success'] = 'Admin account "' . htmlspecialchars($admin['full_name']) . '" has been deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete admin account. Please try again.';
}

header('Location: ' . SITE_URL . '/admin/admin-management/manage-admins.php');
exit;
?>