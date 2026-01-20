<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Get tournament ID
if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['id']);
$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

// ═══════════════════════════════════════════════════════════════
//              ACCESS CONTROL
// ═══════════════════════════════════════════════════════════════

// Check access permissions
if ($logged_in_role === 'organizer') {
    // Organizer can access their tournaments or tournaments created by their admins
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
    // Admin can access their tournaments or their organizer's tournaments
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
    // Other roles - no access
    $_SESSION['error'] = 'Access denied';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$access_result = mysqli_query($conn, $access_check);

if (!$access_result || mysqli_num_rows($access_result) == 0) {
    $_SESSION['error'] = 'Tournament not found or access denied';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

// ═══════════════════════════════════════════════════════════════
//              END OF ACCESS CONTROL
// ═══════════════════════════════════════════════════════════════

// Fetch tournament details
$tournament_query = "SELECT tournament_title FROM TOURNAMENT WHERE tournament_id = '$tournament_id'";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Handle search and sort
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

// Base query
$registrations_query = "SELECT 
    tr.registration_id,
    tr.registration_date,
    tr.payment_proof,
    tr.approval_status,
    tr.notes,
    tr.spot_id,
    u.user_id,
    u.full_name,
    u.email,
    u.phone_number,
    fs.spot_id as spot_number,
    z.zone_name
    FROM TOURNAMENT_REGISTRATION tr
    JOIN USER u ON tr.user_id = u.user_id
    LEFT JOIN FISHING_SPOT fs ON tr.spot_id = fs.spot_id
    LEFT JOIN ZONE z ON fs.zone_id = z.zone_id
    WHERE tr.tournament_id = '$tournament_id'";

// Apply search
if (!empty($search)) {
    $registrations_query .= " AND (u.user_id LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}

// Apply sort
switch($sort) {
    case 'angler_id_asc': $registrations_query .= " ORDER BY u.user_id ASC"; break;
    case 'angler_id_desc': $registrations_query .= " ORDER BY u.user_id DESC"; break;
    case 'name_asc': $registrations_query .= " ORDER BY u.full_name ASC"; break;
    case 'name_desc': $registrations_query .= " ORDER BY u.full_name DESC"; break;
    case 'date_asc': $registrations_query .= " ORDER BY tr.registration_date ASC"; break;
    case 'date_desc': $registrations_query .= " ORDER BY tr.registration_date DESC"; break;
    default: $registrations_query .= " ORDER BY tr.registration_date DESC"; break;
}

$registrations_result = mysqli_query($conn, $registrations_query);

// Count statistics
$total = mysqli_num_rows($registrations_result);
$approved = $pending = $rejected = 0;

mysqli_data_seek($registrations_result, 0);
while ($row = mysqli_fetch_assoc($registrations_result)) {
    switch ($row['approval_status']) {
        case 'approved': $approved++; break;
        case 'pending': $pending++; break;
        case 'rejected': $rejected++; break;
    }
}
mysqli_data_seek($registrations_result, 0);

$page_title = 'Manage Participants - ' . $tournament['tournament_title'];
include '../includes/header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="<?= SITE_URL; ?>/admin/tournament/viewTournament.php?id=<?= $tournament_id; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Tournament
    </a>
</div>

<div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-tasks"></i>
                Participant Management
            </h3>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="dashboard-stats">
            <div class="stat-card"><div class="stat-header"><div><div class="stat-label">Total</div><div class="stat-value"><?= $total ?></div></div><div class="stat-icon"><i class="fas fa-users"></i></div></div></div>
            <div class="stat-card success"><div class="stat-header"><div><div class="stat-label">Approved</div><div class="stat-value"><?= $approved ?></div></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div></div>
            <div class="stat-card warning"><div class="stat-header"><div><div class="stat-label">Pending</div><div class="stat-value"><?= $pending ?></div></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div></div>
            <div class="stat-card danger"><div class="stat-header"><div><div class="stat-label">Rejected</div><div class="stat-value"><?= $rejected ?></div></div><div class="stat-icon"><i class="fas fa-times-circle"></i></div></div></div>
        </div>
</div>

 
<div class="section">
    <!-- Search & Sort -->
    <div class="table-controls">
        <form method="GET" style="flex:1;">
            <input type="hidden" name="id" value="<?= $tournament_id ?>">
            <input type="text" name="search" placeholder="Search by Angler ID or Name..." 
                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        </form>

        <form method="GET">
            <input type="hidden" name="id" value="<?= $tournament_id ?>">
            <select name="sort" onchange="this.form.submit()">
                <option value="">Sort By</option>
                <option value="angler_id_asc" <?= (isset($_GET['sort']) && $_GET['sort']=='angler_id_asc')?'selected':'' ?>>ID ↑</option>
                <option value="angler_id_desc" <?= (isset($_GET['sort']) && $_GET['sort']=='angler_id_desc')?'selected':'' ?>>ID ↓</option>
                <option value="name_asc" <?= (isset($_GET['sort']) && $_GET['sort']=='name_asc')?'selected':'' ?>>Name A-Z</option>
                <option value="name_desc" <?= (isset($_GET['sort']) && $_GET['sort']=='name_desc')?'selected':'' ?>>Name Z-A</option>
                <option value="date_asc" <?= (isset($_GET['sort']) && $_GET['sort']=='date_asc')?'selected':'' ?>>Date ↑</option>
                <option value="date_desc" <?= (isset($_GET['sort']) && $_GET['sort']=='date_desc')?'selected':'' ?>>Date ↓</option>
            </select>
        </form>
    </div>

    <!-- Participant Table -->
    <?php if ($total > 0): ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Angler ID</th>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Spot</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($reg = mysqli_fetch_assoc($registrations_result)): ?>
                <tr>
                    <td>#<?= $reg['user_id']; ?></td>
                    <td><?= htmlspecialchars($reg['full_name']); ?></td>
                    <td>
                        <?= date('d M Y', strtotime($reg['registration_date'])); ?><br>
                        <small><?= date('g:i A', strtotime($reg['registration_date'])); ?></small>
                    </td>
                    <td>
                        <?php if($reg['spot_id']): ?>
                            <span class="badge badge-info">
                                <?= htmlspecialchars($reg['zone_name']); ?> - Spot #<?= $reg['spot_number']; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Not Assigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(!empty($reg['payment_proof'])): ?>
                            <a href="viewPaymentProof.php?id=<?= $reg['registration_id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                <i class="fas fa-image"></i> View
                            </a>
                        <?php else: ?>
                            <span style="font-style:italic;color:var(--color-gray-500);">No proof</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                            $status_classes = ['approved'=>'badge-success','pending'=>'badge-warning','rejected'=>'badge-danger','cancelled'=>'badge-secondary'];
                            $status_icons = ['approved'=>'check-circle','pending'=>'clock','rejected'=>'times-circle','cancelled'=>'ban'];
                        ?>
                        <span class="badge <?= $status_classes[$reg['approval_status']] ?>">
                            <i class="fas fa-<?= $status_icons[$reg['approval_status']] ?>"></i> <?= ucfirst($reg['approval_status']); ?>
                        </span>
                    </td>
                    <td class="action-buttons">
                        <?php if($reg['approval_status']=='pending'): ?>
                            <!-- Approve -->
                            <a href="changeStatus.php?registration_id=<?= $reg['registration_id']; ?>&tournament_id=<?= $tournament_id; ?>&status=approved" 
                               class="btn btn-sm btn-success" onclick="return confirm('Approve this registration?');">
                                <i class="fas fa-check"></i>
                            </a>
                            <!-- Reject -->
                            <a href="javascript:void(0);" class="btn btn-sm btn-danger" onclick="openRejectModal(<?= $reg['registration_id']; ?>)">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php elseif($reg['approval_status']=='rejected'): ?>
                            <a href="javascript:void(0);" class="btn btn-sm btn-warning" onclick="openRejectModal(<?= $reg['registration_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit Note
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>No Registrations Yet</h3>
            <p>No participants have registered for this tournament yet.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3>Reject Participant</h3>
    <form method="POST" action="changeStatus.php">
        <input type="hidden" name="registration_id" id="rejectRegistrationId">
        <input type="hidden" name="tournament_id" value="<?= $tournament_id; ?>">
        <input type="hidden" name="status" value="rejected">
        <label for="reject_note">Reason for rejection:</label>
        <textarea name="reject_note" id="reject_note" required placeholder="Type the reason here..." rows="4"></textarea>
        <button type="submit" class="btn btn-danger">Reject Participant</button>
    </form>
  </div>
</div>

<script>
function openRejectModal(registrationId) {
    document.getElementById('rejectRegistrationId').value = registrationId;
    document.getElementById('rejectModal').style.display = 'block';
}

document.querySelector('.close').onclick = function() {
    document.getElementById('rejectModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('rejectModal')) {
        document.getElementById('rejectModal').style.display = 'none';
    }
}
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 999;
    padding-top: 100px;
    left: 0; top: 0; width: 100%; height: 100%;
    overflow: auto; background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background-color: var(--color-white);
    margin: auto;
    padding: 2rem;
    border-radius: var(--radius-md);
    width: 400px;
    box-shadow: var(--shadow-lg);
    position: relative;
}
.modal-content .close {
    position: absolute;
    top: 10px; right: 15px;
    font-size: 1.5rem;
    cursor: pointer;
}
.modal textarea {
    width: 100%; padding: 0.5rem; margin-top: 0.5rem;
    border-radius: var(--radius-sm); border: 1px solid var(--color-gray-300);
}
</style>

<?php include '../includes/footer.php'; ?>