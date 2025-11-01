<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// ✅ Ensure only admin can access
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error'] = 'Unauthorized access';
    redirect(SITE_URL . '/pages/login.php');
    exit;
}

// ✅ Ensure tournament ID provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Tournament ID is required';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
    exit;
}

$tournament_id = intval($_GET['id']);

// ✅ Get tournament info (for image deletion)
$query = "SELECT image FROM TOURNAMENT WHERE tournament_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $tournament_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Tournament not found';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
    exit;
}

$tournament = mysqli_fetch_assoc($result);

// ✅ Start transaction
mysqli_begin_transaction($conn);

try {
    // =======================================================
    // 🔹 Delete dependent records (but keep zones/spots)
    // =======================================================
    $related_tables = [
        'CALENDAR',
        'SAVED',
        'NOTIFICATION',
        'REVIEW',
        'RESULT',
        'FISH_CATCH',
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
    // 🔹 Unlink zones (keep them reusable)
    // =======================================================
    mysqli_query($conn, "UPDATE ZONE SET tournament_id = NULL WHERE tournament_id = $tournament_id");

    // =======================================================
    // 🔹 Finally, delete the tournament itself
    // =======================================================
    $delete_tournament = "DELETE FROM TOURNAMENT WHERE tournament_id = $tournament_id";
    if (!mysqli_query($conn, $delete_tournament)) {
        throw new Exception('Failed to delete tournament: ' . mysqli_error($conn));
    }

    // =======================================================
    // 🔹 Delete tournament image file (if exists)
    // =======================================================
    if (!empty($tournament['image'])) {
        deleteFile($tournament['image'], 'tournaments');
    }

    // ✅ Commit transaction
    mysqli_commit($conn);
    $_SESSION['success'] = 'Tournament deleted successfully (zones retained).';

} catch (Exception $e) {
    // ❌ Rollback on error
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Failed to delete tournament: ' . $e->getMessage();
}

// ✅ Redirect
redirect(SITE_URL . '/admin/tournament/tournamentList.php');
exit;
?>
