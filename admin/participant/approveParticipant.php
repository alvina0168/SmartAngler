<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (
    !isset($_SESSION['user_id']) || 
    !in_array($_SESSION['role'], ['admin', 'organizer'])
) {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id']) || !isset($_GET['tournament_id'])) {
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$registration_id = intval($_GET['id']);
$tournament_id   = intval($_GET['tournament_id']);

$approve_query = "
    UPDATE TOURNAMENT_REGISTRATION 
    SET approval_status = 'approved', 
        approved_date = NOW() 
    WHERE registration_id = $registration_id
";

if (mysqli_query($conn, $approve_query)) {

    $spot_update = "
        UPDATE FISHING_SPOT fs
        JOIN TOURNAMENT_REGISTRATION tr ON fs.spot_id = tr.spot_id
        SET fs.spot_status = 'booked'
        WHERE tr.registration_id = $registration_id
        AND tr.spot_id IS NOT NULL
    ";
    mysqli_query($conn, $spot_update);

    $_SESSION['success'] = 'Participant registration approved successfully!';
} else {
    $_SESSION['error'] = 'Failed to approve registration.';
}

redirect(SITE_URL . '/admin/participant/manageParticipants.php?id=' . $tournament_id);
?>
