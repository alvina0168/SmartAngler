<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

// Ensure tournament ID exists
if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['id']);

/* -----------------------------------------------------------
   Auto-update tournament status based on date/time
----------------------------------------------------------- */
$update_status_query = "
    UPDATE TOURNAMENT
    SET status = CASE
        WHEN NOW() < CONCAT(tournament_date, ' ', start_time) THEN 'upcoming'
        WHEN NOW() BETWEEN CONCAT(tournament_date, ' ', start_time) AND CONCAT(tournament_date, ' ', end_time) THEN 'ongoing'
        WHEN NOW() > CONCAT(tournament_date, ' ', end_time) THEN 'completed'
        ELSE status
    END
    WHERE tournament_id = '$tournament_id' AND status != 'cancelled'
";
mysqli_query($conn, $update_status_query);

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tournament') {
    $tournament_title = mysqli_real_escape_string($conn, $_POST['tournament_title']);
    $tournament_date = mysqli_real_escape_string($conn, $_POST['tournament_date']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $tournament_fee = mysqli_real_escape_string($conn, $_POST['tournament_fee']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $bank_account_name = mysqli_real_escape_string($conn, $_POST['bank_account_name']);
    $bank_account_number = mysqli_real_escape_string($conn, $_POST['bank_account_number']);
    $bank_account_holder = mysqli_real_escape_string($conn, $_POST['bank_account_holder']);
    
    // Base update query
    $update_query = "
        UPDATE TOURNAMENT SET 
            tournament_title='$tournament_title',
            tournament_date='$tournament_date',
            start_time='$start_time',
            end_time='$end_time',
            location='$location',
            tournament_fee='$tournament_fee',
            description='$description',
            status='$status',
            bank_account_name='$bank_account_name',
            bank_account_number='$bank_account_number',
            bank_account_holder='$bank_account_holder'
        WHERE tournament_id='$tournament_id'
    ";

    // Handle tournament image upload
    if (!empty($_FILES['tournament_image']['name'])) {
        $image_name = time() . '_' . basename($_FILES['tournament_image']['name']);
        $target_dir = "../../assets/images/tournaments/";
        $target_file = $target_dir . $image_name;

        if (move_uploaded_file($_FILES['tournament_image']['tmp_name'], $target_file)) {
            $update_query = str_replace("WHERE tournament_id='$tournament_id'", ", image='$image_name' WHERE tournament_id='$tournament_id'", $update_query);
        } else {
            $_SESSION['error'] = "Failed to upload tournament image.";
        }
    }

    // Handle bank QR image upload
    if (!empty($_FILES['bank_qr_image']['name'])) {
        $qr_name = time() . '_' . basename($_FILES['bank_qr_image']['name']);
        $qr_dir = "../../assets/images/qrcodes/";
        $qr_file = $qr_dir . $qr_name;

        if (move_uploaded_file($_FILES['bank_qr_image']['tmp_name'], $qr_file)) {
            $update_query = str_replace("WHERE tournament_id='$tournament_id'", ", bank_qr='$qr_name' WHERE tournament_id='$tournament_id'", $update_query);
        } else {
            $_SESSION['error'] = "Failed to upload QR image.";
        }
    }

    if (mysqli_query($conn, $update_query)) {
        $_SESSION['success'] = 'Tournament updated successfully!';
    } else {
        $_SESSION['error'] = 'Failed to update tournament: ' . mysqli_error($conn);
    }

    redirect(SITE_URL . '/admin/tournament/viewTournament.php?id=' . $tournament_id);
}

// Fetch tournament
$query = "
    SELECT t.*, u.full_name AS organizer_name,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION WHERE tournament_id=t.tournament_id) AS total_registrations,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION WHERE tournament_id=t.tournament_id AND approval_status='approved') AS approved_count
    FROM TOURNAMENT t
    LEFT JOIN USER u ON t.created_by = u.user_id
    WHERE t.tournament_id='$tournament_id'
";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($result);

$page_title = $tournament['tournament_title'];

include '../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-style.css">
<style>
/* Edit mode */
.edit-mode .view-only { display: none; }
.edit-mode .edit-only { display: block; }
.view-only { display: block; }
.edit-only { display: none; }

/* Grid layout */
.two-column-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

/* Images */
.image-preview {
    width: 100%;
    max-height: 400px;
    border-radius: var(--radius-lg);
    object-fit: cover;
    box-shadow: var(--shadow-md);
}

.qr-image {
    display: block;
    margin: 1rem 0 1rem 0;
    width: auto;
    height: 30rem;
    max-width: none;
    max-height: none;
    object-fit: unset;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
}

.qr-placeholder {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    height: 200px;
    width: 200px;
    margin: 1rem 0 1rem 0;
    background: var(--color-cream-light);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    font-size: 3rem;
    color: var(--color-gray-400);
}

/* Target only labels inside the Payment Info section */
.section .form-group label {
    font-weight: 700;       
    font-size: 0.9rem;      
    color: #1c4987;      
}

@media(max-width:768px){
    .two-column-grid { grid-template-columns: 1fr; }
}
</style>

<div class="top-bar">
    <div class="top-bar-left">
        <h1>Tournament Details</h1>
        <p>View and manage tournament information</p>
    </div>
    <div class="top-bar-right">
        <button type="button" class="btn btn-secondary" onclick="toggleEditMode()" id="toggleEditBtn">
            <i class="fas fa-edit"></i> Edit Mode
        </button>
        <a href="tournamentList.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="content-container">

    <!-- Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['success']; ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" id="tournamentForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_tournament">

        <!-- Quick Actions -->
        <div class="section">
            <div class="dashboard-stats">
                <a href="../Participant/manageParticipants.php?id=<?= $tournament_id ?>" class="stat-card success" style="text-decoration:none;">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">View Participants</div>
                            <div class="stat-value"><?= $tournament['total_registrations'] ?></div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                    </div>
                </a>
                <a href="../Result/viewResults.php?id=<?= $tournament_id ?>" class="stat-card info" style="text-decoration:none;">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">View Results</div>
                            <div class="stat-value"><i class="fas fa-trophy"></i></div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-medal"></i></div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Tournament Info -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> Tournament Information</h3>
                <div class="edit-only">
                    <button class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </div>

            <div class="two-column-grid">
                <!-- LEFT COLUMN -->
                <div>
                    <div class="form-group">
                        <label>Tournament Title</label>
                        <div class="view-only"><?= htmlspecialchars($tournament['tournament_title']) ?></div>
                        <div class="edit-only"><input type="text" name="tournament_title" class="form-control" value="<?= htmlspecialchars($tournament['tournament_title']) ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>Tournament Date</label>
                        <div class="view-only"><?= date('l, d F Y', strtotime($tournament['tournament_date'])) ?></div>
                        <div class="edit-only"><input type="date" name="tournament_date" class="form-control" value="<?= $tournament['tournament_date'] ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>Start Time</label>
                        <div class="view-only"><?= date('g:i A', strtotime($tournament['start_time'])) ?></div>
                        <div class="edit-only"><input type="time" name="start_time" class="form-control" value="<?= $tournament['start_time'] ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>End Time</label>
                        <div class="view-only"><?= date('g:i A', strtotime($tournament['end_time'])) ?></div>
                        <div class="edit-only"><input type="time" name="end_time" class="form-control" value="<?= $tournament['end_time'] ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <div class="view-only"><?= htmlspecialchars($tournament['location']) ?></div>
                        <div class="edit-only"><input type="text" name="location" class="form-control" value="<?= htmlspecialchars($tournament['location']) ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>Registration Fee</label>
                        <div class="view-only">RM <?= number_format($tournament['tournament_fee'], 2) ?></div>
                        <div class="edit-only"><input type="number" step="0.01" name="tournament_fee" class="form-control" value="<?= $tournament['tournament_fee'] ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <div class="view-only">
                            <span class="badge badge-<?= $tournament['status'] ?>"><?= ucfirst($tournament['status']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div>
                     <!-- Organizer -->
                     <div class="form-group">
                        <label>Organized By</label>
                        <div><?= $tournament['organizer_name'] ?: "Admin" ?></div>
                    </div>

                    <div class="form-group">
                        <label>Created On</label>
                        <div><?= date("d F Y, g:i A", strtotime($tournament['created_at'])) ?></div>
                    </div>
                    
                    <!-- Tournament Image -->
                    <div class="form-group">
                        <label>Tournament Image</label>
                        <?php if ($tournament['image']): ?>
                            <img src="../../assets/images/tournaments/<?= htmlspecialchars($tournament['image']) ?>" class="image-preview">
                        <?php else: ?>
                            <div class="image-preview" style="display:flex;align-items:center;justify-content:center;background:var(--color-cream-light);">
                                <i class="fas fa-image" style="font-size:3rem;color:var(--color-gray-400);"></i>
                            </div>
                        <?php endif; ?>
                        <div class="edit-only" style="margin-top:1rem;">
                            <input type="file" name="tournament_image" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title"><i class="fas fa-credit-card"></i> Payment Information</h3>
            </div>

            <div class="two-column-grid">
                <!-- LEFT: Bank Details -->
                <div>
                    <div class="form-group">
                        <label>Bank Name</label>
                        <div class="view-only"><?= htmlspecialchars($tournament['bank_account_name']) ?></div>
                        <div class="edit-only"><input type="text" name="bank_account_name" class="form-control" value="<?= htmlspecialchars($tournament['bank_account_name']) ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>Account Number</label>
                        <div class="view-only"><?= htmlspecialchars($tournament['bank_account_number']) ?></div>
                        <div class="edit-only"><input type="text" name="bank_account_number" class="form-control" value="<?= htmlspecialchars($tournament['bank_account_number']) ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>Account Holder </label>
                        <div class="view-only"><?= htmlspecialchars($tournament['bank_account_holder']) ?></div>
                        <div class="edit-only"><input type="text" name="bank_account_holder" class="form-control" value="<?= htmlspecialchars($tournament['bank_account_holder']) ?>"></div>
                    </div>
                </div>

                <!-- RIGHT: QR Code -->
                <div class="form-group">
                    <label>Bank QR Image</label>
                    <?php if ($tournament['bank_qr']): ?>
                        <img src="../../assets/images/qrcodes/<?= htmlspecialchars($tournament['bank_qr']) ?>" class="qr-image">
                    <?php else: ?>
                        <div class="qr-placeholder"><i class="fas fa-qrcode"></i></div>
                    <?php endif; ?>
                    <div class="edit-only" style="margin-top:1rem;">
                        <input type="file" name="bank_qr_image" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title"><i class="fas fa-align-left"></i> Description</h3>
            </div>

            <div class="view-only">
                <p style="line-height:1.7;color:var(--color-gray-700);"><?= nl2br(htmlspecialchars($tournament['description'])) ?></p>
            </div>

            <div class="edit-only">
                <textarea class="form-control" name="description" rows="6"><?= htmlspecialchars($tournament['description']) ?></textarea>
            </div>
        </div>
    </form>
</div>

<script>
function toggleEditMode(){
    const form = document.getElementById("tournamentForm");
    const btn = document.getElementById("toggleEditBtn");
    if(form.classList.contains("edit-mode")){
        cancelEdit();
    } else {
        form.classList.add("edit-mode");
        btn.innerHTML = '<i class="fas fa-eye"></i> View Mode';
    }
}
function cancelEdit(){
    const form = document.getElementById("tournamentForm");
    const btn = document.getElementById("toggleEditBtn");
    form.classList.remove("edit-mode");
    form.reset();
    btn.innerHTML = '<i class="fas fa-edit"></i> Edit Mode';
}
</script>

<?php include '../includes/footer.php'; ?>
