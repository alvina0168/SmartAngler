<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login to continue';
    redirect(SITE_URL . '/pages/login.php');
    exit;
}

// Ensure tournament ID provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Tournament ID is required';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
    exit;
}

$tournament_id = intval($_GET['id']);
$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

// Check access permissions before deleting
if ($logged_in_role === 'organizer') {
    // Organizer can delete their tournaments or tournaments created by their admins
    $access_check = "
        SELECT image FROM TOURNAMENT 
        WHERE tournament_id = '$tournament_id'
        AND (
            created_by = '$logged_in_user_id'
            OR created_by IN (
                SELECT user_id FROM USER WHERE created_by = '$logged_in_user_id' AND role = 'admin'
            )
        )
    ";
} elseif ($logged_in_role === 'admin') {
    // Admin can delete their tournaments or their organizer's tournaments
    $get_creator_query = "SELECT created_by FROM USER WHERE user_id = '$logged_in_user_id'";
    $creator_result = mysqli_query($conn, $get_creator_query);
    $creator_row = mysqli_fetch_assoc($creator_result);
    $organizer_id = $creator_row['created_by'] ?? null;
    
    if ($organizer_id) {
        $access_check = "
            SELECT image FROM TOURNAMENT 
            WHERE tournament_id = '$tournament_id'
            AND (created_by = '$logged_in_user_id' OR created_by = '$organizer_id')
        ";
    } else {
        $access_check = "
            SELECT image FROM TOURNAMENT 
            WHERE tournament_id = '$tournament_id'
            AND created_by = '$logged_in_user_id'
        ";
    }
} else {
    // Other roles - no access
    $_SESSION['error'] = 'Access denied';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
    exit;
}

$result = mysqli_query($conn, $access_check);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Tournament not found or access denied';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
    exit;
}

$tournament = mysqli_fetch_assoc($result);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // =======================================================
    // Delete dependent records (but keep zones/spots)
    // =======================================================
    $related_tables = [
        'CALENDAR',
        'SAVED',
        'NOTIFICATION',
        'REVIEW',
        'RESULT',
        'TOURNAMENT_PRIZE',
        'SPONSOR',
        'TOURNAMENT_REGISTRATION'
    ];

    foreach ($related_tables as $table) {
        $delete_query = "DELETE FROM $table WHERE tournament_id = $tournament_id";
        if (!mysqli_query($conn, $delete_query)) {
            throw new Exception("Failed to delete from $table: " . mysqli_error($conn));
        }
    }

    // =======================================================
    // Unlink zones (keep them reusable)
    // =======================================================
    mysqli_query($conn, "UPDATE ZONE SET tournament_id = NULL WHERE tournament_id = $tournament_id");

    // =======================================================
    // Finally, delete the tournament itself
    // =======================================================
    $delete_tournament = "DELETE FROM TOURNAMENT WHERE tournament_id = $tournament_id";
    if (!mysqli_query($conn, $delete_tournament)) {
        throw new Exception('Failed to delete tournament: ' . mysqli_error($conn));
    }

    // =======================================================
    // Delete tournament image file (if exists)
    // =======================================================
    if (!empty($tournament['image'])) {
        deleteFile($tournament['image'], 'tournaments');
    }

    // Commit transaction
    mysqli_commit($conn);
    $_SESSION['success'] = 'Tournament deleted successfully (zones retained).';

} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Failed to delete tournament: ' . $e->getMessage();
}

// Redirect
redirect(SITE_URL . '/admin/tournament/tournamentList.php');
exit;
?>