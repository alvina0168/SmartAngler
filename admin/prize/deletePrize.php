<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Get parameters first
if (!isset($_GET['id']) || !isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Missing parameters!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$prize_id = intval($_GET['id']);
$tournament_id = intval($_GET['tournament_id']);
$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

// ═══════════════════════════════════════════════════════════════
//              ACCESS CONTROL
// ═══════════════════════════════════════════════════════════════

// Check access permissions
if ($logged_in_role === 'organizer') {
    $access_check = "
        SELECT tournament_id FROM TOURNAMENT 
        WHERE tournament_id = '$tournament_id'
        AND (
            created_by = '$logged_in_user_id'
            OR created_by IN (
                SELECT user_id FROM USER WHERE created_by = '$logged_in_user_id' AND role = 'admin'
            )
        )
    ";
} elseif ($logged_in_role === 'admin') {
    $get_creator_query = "SELECT created_by FROM USER WHERE user_id = '$logged_in_user_id'";
    $creator_result = mysqli_query($conn, $get_creator_query);
    $creator_row = mysqli_fetch_assoc($creator_result);
    $organizer_id = $creator_row['created_by'] ?? null;
    
    if ($organizer_id) {
        $access_check = "
            SELECT tournament_id FROM TOURNAMENT 
            WHERE tournament_id = '$tournament_id'
            AND (created_by = '$logged_in_user_id' OR created_by = '$organizer_id')
        ";
    } else {
        $access_check = "
            SELECT tournament_id FROM TOURNAMENT 
            WHERE tournament_id = '$tournament_id'
            AND created_by = '$logged_in_user_id'
        ";
    }
} else {
    $_SESSION['error'] = 'Access denied';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$access_result = mysqli_query($conn, $access_check);

if (!$access_result || mysqli_num_rows($access_result) == 0) {
    $_SESSION['error'] = 'Tournament not found or access denied';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

// Delete prize
$delete_query = "DELETE FROM TOURNAMENT_PRIZE WHERE prize_id = $prize_id";

if (mysqli_query($conn, $delete_query)) {
    $_SESSION['success'] = 'Prize deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete prize: ' . mysqli_error($conn);
}

redirect(SITE_URL . '/admin/prize/managePrize.php?tournament_id=' . $tournament_id);
?>