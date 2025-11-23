<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

// Determine request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coming from Reject form/modal
    if (!isset($_POST['registration_id']) || empty($_POST['status'])) {
        $_SESSION['error'] = "Invalid registration!";
        redirect(SITE_URL . '/admin/participant/manageParticipants.php');
    }
    $registration_id = intval($_POST['registration_id']);
    $status = $_POST['status']; // 'rejected'
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
} else {
    // Coming from Approve GET link
    if (!isset($_GET['registration_id']) || !isset($_GET['status'])) {
        $_SESSION['error'] = "Invalid registration!";
        redirect(SITE_URL . '/admin/participant/manageParticipants.php');
    }
    $registration_id = intval($_GET['registration_id']);
    $status = $_GET['status']; // 'approved'
    $notes = 'Registration approved';
}

// Fetch registration info
$query = "SELECT tr.*, u.full_name, u.email, fs.spot_status 
          FROM TOURNAMENT_REGISTRATION tr
          JOIN USER u ON tr.user_id = u.user_id
          LEFT JOIN FISHING_SPOT fs ON tr.spot_id = fs.spot_id
          WHERE tr.registration_id=?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $registration_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Registration not found!";
    redirect(SITE_URL . '/admin/participant/manageParticipants.php');
}

$registration = mysqli_fetch_assoc($result);
$spot_id = $registration['spot_id'];
$user_id = $registration['user_id'];
$tournament_id = $registration['tournament_id'];

// Update registration status
$approved_date = ($status === 'approved') ? ", approved_date=NOW()" : "";
$update = "UPDATE TOURNAMENT_REGISTRATION 
           SET approval_status=?, notes=? $approved_date 
           WHERE registration_id=?";
$stmt_update = mysqli_prepare($conn, $update);
mysqli_stmt_bind_param($stmt_update, "ssi", $status, $notes, $registration_id);
mysqli_stmt_execute($stmt_update);

// Update fishing spot if assigned
if ($spot_id) {
    $spot_status = ($status === 'rejected') ? 'available' : 'booked';
    $update_spot = "UPDATE FISHING_SPOT SET spot_status=? WHERE spot_id=?";
    $stmt_spot = mysqli_prepare($conn, $update_spot);
    mysqli_stmt_bind_param($stmt_spot, "si", $spot_status, $spot_id);
    mysqli_stmt_execute($stmt_spot);
}

// Send notification
$title = ($status === 'approved') ? "Registration Approved" : "Registration Rejected";
$message = ($status === 'approved') 
           ? "Your registration has been approved. Notes: $notes"
           : "Your registration has been rejected. Reason: $notes";

$notif_query = "INSERT INTO NOTIFICATION (user_id, tournament_id, title, message) VALUES (?,?,?,?)";
$stmt_notif = mysqli_prepare($conn, $notif_query);
mysqli_stmt_bind_param($stmt_notif, "iiss", $user_id, $tournament_id, $title, $message);
mysqli_stmt_execute($stmt_notif);

// Send email
$to = $registration['email'];
$subject = $title;
$body = "Hello ".$registration['full_name'].",\n\n".$message."\n\nBest regards,\nSmartAngler Team";
$headers = "From: no-reply@smartangler.com\r\n";
@mail($to, $subject, $body, $headers);

// Success message and redirect
$_SESSION['success'] = "Participant status updated successfully!";
redirect(SITE_URL."/admin/participant/manageParticipants.php?id=$tournament_id");
?>

<?php include '../includes/header.php'; ?>

<div class="content-container">
    <h2>Change Registration Status</h2>

    <form id="statusForm" method="POST" class="form-card">
        <!-- Hidden inputs -->
        <input type="hidden" name="status" id="statusInput" value="">
        <input type="hidden" name="notes" id="notesInput" value="">

        <div class="form-group">
            <label>Participant Name</label>
            <input type="text" value="<?= htmlspecialchars($registration['full_name']); ?>" disabled>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-primary" onclick="approveParticipant()">
                <i class="fas fa-check"></i> Approve
            </button>
            <button type="button" class="btn btn-danger" onclick="rejectParticipant()">
                <i class="fas fa-times"></i> Reject
            </button>
            <a href="manageParticipants.php?id=<?= $tournament_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
function approveParticipant() {
    if(confirm("Are you sure you want to approve this participant?")) {
        document.getElementById('statusInput').value = 'approved';
        document.getElementById('notesInput').value = 'Registration approved';
        document.getElementById('statusForm').submit();
    }
}

function rejectParticipant() {
    let reason = prompt("Reject Participant\nPlease enter reason for rejection:");
    if(reason !== null && reason.trim() !== "") {
        document.getElementById('statusInput').value = 'rejected';
        document.getElementById('notesInput').value = reason;
        document.getElementById('statusForm').submit();
    } else if(reason !== null) {
        alert("You must enter a reason to reject.");
    }
}
</script>

<?php include '../includes/footer.php'; ?>


