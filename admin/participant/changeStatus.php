<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

// Get registration ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Invalid registration!";
    redirect(SITE_URL . '/admin/participant/manageParticipant.php');
}

$registration_id = intval($_GET['id']);

// Fetch registration details with user info
$query = "SELECT tr.*, u.full_name, u.email, fs.spot_status 
          FROM TOURNAMENT_REGISTRATION tr
          JOIN USER u ON tr.user_id = u.user_id
          LEFT JOIN FISHING_SPOT fs ON tr.spot_id = fs.spot_id
          WHERE tr.registration_id=?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $registration_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Registration not found!";
    redirect(SITE_URL . '/admin/participant/manageParticipant.php');
}

$registration = mysqli_fetch_assoc($result);
$spot_id = $registration['spot_id'];
$user_id = $registration['user_id'];
$tournament_id = $registration['tournament_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status']; // approved or rejected
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    // Update registration
    $approved_date = ($status === 'approved') ? ", approved_date=NOW()" : "";
    $update = "UPDATE TOURNAMENT_REGISTRATION 
               SET approval_status=?, notes=? $approved_date 
               WHERE registration_id=?";
    $stmt_update = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt_update, "ssi", $status, $notes, $registration_id);
    mysqli_stmt_execute($stmt_update);

    // Update fishing spot
    if ($spot_id) {
        if ($status === 'rejected') {
            $update_spot = "UPDATE FISHING_SPOT SET spot_status='available' WHERE spot_id=?";
        } else {
            $update_spot = "UPDATE FISHING_SPOT SET spot_status='booked' WHERE spot_id=?";
        }
        $stmt_spot = mysqli_prepare($conn, $update_spot);
        mysqli_stmt_bind_param($stmt_spot, "i", $spot_id);
        mysqli_stmt_execute($stmt_spot);
    }

    // Insert notification
    $title = ($status === 'approved') ? "Registration Approved" : "Registration Rejected";
    $message = ($status === 'approved') 
               ? "Your registration has been approved. Notes: $notes"
               : "Your registration has been rejected. Reason: $notes";

    $notif_query = "INSERT INTO NOTIFICATION (user_id, tournament_id, title, message) VALUES (?,?,?,?)";
    $stmt_notif = mysqli_prepare($conn, $notif_query);
    mysqli_stmt_bind_param($stmt_notif, "iiss", $user_id, $tournament_id, $title, $message);
    mysqli_stmt_execute($stmt_notif);

    // Send email to user
    $to = $registration['email'];
    $subject = $title;
    $body = "Hello ".$registration['full_name'].",\n\n".$message."\n\nBest regards,\nSmartAngler Team";
    $headers = "From: no-reply@smartangler.com\r\n";
    @mail($to, $subject, $body, $headers);

    $_SESSION['success'] = "Participant status updated successfully!";
    redirect(SITE_URL."/admin/participant/manageParticipant.php?id=$tournament_id");
}
?>

<?php include '../includes/header.php'; ?>

<div class="content-container">
    <h2>Change Registration Status</h2>
    <form method="POST" class="form-card">
        <div class="form-group">
            <label>Participant Name</label>
            <input type="text" value="<?= htmlspecialchars($registration['full_name']) ?>" disabled>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status" required>
                <option value="approved" <?= ($registration['approval_status']=='approved')?'selected':'' ?>>Approve</option>
                <option value="rejected" <?= ($registration['approval_status']=='rejected')?'selected':'' ?>>Reject</option>
            </select>
        </div>

        <div class="form-group">
            <label>Notes (sent to participant)</label>
            <textarea name="notes" placeholder="Enter notes for the participant"><?= htmlspecialchars($registration['notes']); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i> Update Status
            </button>
            <a href="manageParticipant.php?id=<?= $tournament_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
