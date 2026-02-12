<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login to continue';
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Tournament ID is required';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['id']);
$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

$has_access = false;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tournament') {
    $tournament_title = mysqli_real_escape_string($conn, $_POST['tournament_title']);
    $tournament_date = mysqli_real_escape_string($conn, $_POST['tournament_date']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $tournament_fee = mysqli_real_escape_string($conn, $_POST['tournament_fee']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $tournament_rules = mysqli_real_escape_string($conn, $_POST['tournament_rules']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $bank_account_name = mysqli_real_escape_string($conn, $_POST['bank_account_name']);
    $bank_account_number = mysqli_real_escape_string($conn, $_POST['bank_account_number']);
    $bank_account_holder = mysqli_real_escape_string($conn, $_POST['bank_account_holder']);
    
    $update_query = "
        UPDATE TOURNAMENT SET 
            tournament_title='$tournament_title',
            tournament_date='$tournament_date',
            start_time='$start_time',
            end_time='$end_time',
            location='$location',
            tournament_fee='$tournament_fee',
            description='$description',
            tournament_rules='$tournament_rules',
            status='$status',
            bank_account_name='$bank_account_name',
            bank_account_number='$bank_account_number',
            bank_account_holder='$bank_account_holder'
        WHERE tournament_id='$tournament_id'
    ";

    if (!empty($_FILES['tournament_image']['name'])) {
        $image_name = time() . '_' . basename($_FILES['tournament_image']['name']);
        $target_dir = "../../assets/images/tournaments/";
        $target_file = $target_dir . $image_name;

        if (move_uploaded_file($_FILES['tournament_image']['tmp_name'], $target_file)) {
            $update_query = str_replace("WHERE tournament_id='$tournament_id'", ", image='$image_name' WHERE tournament_id='$tournament_id'", $update_query);
        }
    }

    if (!empty($_FILES['bank_qr_image']['name'])) {
        $qr_name = time() . '_' . basename($_FILES['bank_qr_image']['name']);
        $qr_dir = "../../assets/images/qrcodes/";
        $qr_file = $qr_dir . $qr_name;

        if (move_uploaded_file($_FILES['bank_qr_image']['tmp_name'], $qr_file)) {
            $update_query = str_replace("WHERE tournament_id='$tournament_id'", ", bank_qr='$qr_name' WHERE tournament_id='$tournament_id'", $update_query);
        }
    }

    if (mysqli_query($conn, $update_query)) {
        $_SESSION['success'] = 'Tournament updated successfully!';
    } else {
        $_SESSION['error'] = 'Failed to update tournament: ' . mysqli_error($conn);
    }

    redirect(SITE_URL . '/admin/tournament/viewTournament.php?id=' . $tournament_id);
}

$query = "
    SELECT t.*, u.full_name AS organizer_name,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION WHERE tournament_id=t.tournament_id) AS total_registrations,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION WHERE tournament_id=t.tournament_id AND approval_status='approved') AS approved_count,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION WHERE tournament_id=t.tournament_id AND approval_status='pending') AS pending_count,
        (SELECT COUNT(*) FROM WEIGHING_STATION WHERE tournament_id=t.tournament_id) AS station_count,
        (SELECT COUNT(*) FROM FISH_CATCH fc JOIN WEIGHING_STATION ws ON fc.station_id=ws.station_id WHERE ws.tournament_id=t.tournament_id) AS catch_count,
        (SELECT COUNT(*) FROM REVIEW WHERE tournament_id=t.tournament_id) AS review_count,
        (SELECT COUNT(*) FROM SPONSOR WHERE tournament_id=t.tournament_id) AS sponsor_count,
        (SELECT COUNT(DISTINCT tp.category_id) FROM TOURNAMENT_PRIZE tp WHERE tp.tournament_id=t.tournament_id) AS category_count,
        (SELECT COUNT(*) FROM TOURNAMENT_PRIZE WHERE tournament_id=t.tournament_id) AS prize_count,
        (SELECT zone_id FROM ZONE WHERE tournament_id=t.tournament_id LIMIT 1) AS assigned_zone_id,
        (SELECT COUNT(*) FROM FISHING_SPOT fs JOIN ZONE z ON fs.zone_id = z.zone_id WHERE z.tournament_id=t.tournament_id) AS total_spots
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

$categories_query = "
    SELECT DISTINCT c.*, 
           (SELECT COUNT(*) FROM TOURNAMENT_PRIZE WHERE tournament_id='$tournament_id' AND category_id=c.category_id) as prize_count
    FROM CATEGORY c
    INNER JOIN TOURNAMENT_PRIZE tp ON c.category_id = tp.category_id
    WHERE tp.tournament_id = '$tournament_id'
    ORDER BY c.category_id
";
$categories_result = mysqli_query($conn, $categories_query);
$sponsors_query = "SELECT * FROM SPONSOR WHERE tournament_id = '$tournament_id' ORDER BY sponsor_id";
$sponsors_result = mysqli_query($conn, $sponsors_query);

$page_title = $tournament['tournament_title'];

include '../includes/header.php';
?>

<style>
.edit-only {
    display: none;
}

.edit-mode .edit-only {
    display: block;
}

.edit-mode .view-only {
    display: none;
}

.edit-mode select.edit-only,
.edit-mode input.edit-only,
.edit-mode textarea.edit-only {
    display: block;
}

.tournament-image-container {
    width: 100%;
    height: 300px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e9ecef;
}

.tournament-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.qr-container {
    width: 250px;
    height: 250px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e9ecef;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-container img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 1rem;
}

.management-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.management-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.management-card:hover {
    border-color: var(--color-blue-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.management-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.management-card-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--color-blue-primary), #7AA5C4);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.management-card-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1a1a1a;
}

.management-card-stats {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    color: #6c757d;
    font-size: 0.875rem;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .management-grid {
        grid-template-columns: 1fr;
    }
}

.info-item {
    margin-bottom: 1rem;
}

.info-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.info-value {
    font-size: 1rem;
    color: #1a1a1a;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--color-blue-primary);
    box-shadow: 0 0 0 3px rgba(109, 148, 197, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.875rem;
}

.badge-upcoming { background: #e3f2fd; color: #1976d2; }
.badge-ongoing { background: #fff3e0; color: #f57c00; }
.badge-completed { background: #e8f5e9; color: #388e3c; }
.badge-cancelled { background: #ffebee; color: #d32f2f; }
</style>

<div style="margin-bottom: 1.5rem;">
    <a href="tournamentList.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<form id="tournamentForm" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_tournament">
    <div class="section">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.75rem; font-weight: 700; color: #1a1a1a; margin: 0 0 0.25rem 0;">
                    <?= htmlspecialchars($tournament['tournament_title']) ?>
                </h2>
                <p style="color: #6c757d; font-size: 0.875rem; margin: 0;">
                    <i class="fas fa-calendar"></i> <?= date('l, d F Y', strtotime($tournament['tournament_date'])) ?>
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <span class="badge badge-<?= $tournament['status'] ?>">
                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                    <?= ucfirst($tournament['status']); ?>
                </span>
                <button type="button" id="toggleEditBtn" class="btn btn-primary" onclick="toggleEditMode()">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-tasks"></i>
                Quick Management
            </h3>
        </div>
        
        <div class="management-grid">
            <a href="../participant/manageParticipants.php?id=<?= $tournament_id ?>" class="management-card">
                <div class="management-card-header">
                    <div class="management-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="management-card-title">Participants</div>
                </div>
                <div class="management-card-stats">
                    <span><strong><?= $tournament['approved_count'] ?></strong> approved</span>
                    <span><strong><?= $tournament['pending_count'] ?></strong> pending</span>
                </div>
            </a>

            <?php if (!empty($tournament['assigned_zone_id'])): ?>
                <a href="../zone/viewZone.php?id=<?= $tournament['assigned_zone_id'] ?>" class="management-card">
                    <div class="management-card-header">
                        <div class="management-card-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="management-card-title">Fishing Spots</div>
                    </div>
                    <div class="management-card-stats">
                        <span><strong><?= $tournament['total_spots'] ?></strong> spots available</span>
                    </div>
                </a>
            <?php else: ?>
                <div class="management-card" style="opacity: 0.6; cursor: not-allowed;">
                    <div class="management-card-header">
                        <div class="management-card-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="management-card-title">Fishing Spots</div>
                    </div>
                    <div class="management-card-stats">
                        <span style="color: #dc3545;">No zone assigned</span>
                    </div>
                </div>
            <?php endif; ?>

            <a href="../catch/stationList.php?tournament_id=<?= $tournament_id ?>" class="management-card">
                <div class="management-card-header">
                    <div class="management-card-icon">
                        <i class="fas fa-fish"></i>
                    </div>
                    <div class="management-card-title">Catch Records</div>
                </div>
                <div class="management-card-stats">
                    <span><strong><?= $tournament['station_count'] ?></strong> stations</span>
                    <span><strong><?= $tournament['catch_count'] ?></strong> catches</span>
                </div>
            </a>

            <!-- Results -->
            <a href="../result/viewResult.php?tournament_id=<?= $tournament_id ?>" class="management-card">
                <div class="management-card-header">
                    <div class="management-card-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <div class="management-card-title">Results</div>
                </div>
                <div class="management-card-stats">
                    <span>View rankings</span>
                </div>
            </a>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Tournament Information
            </h3>
            <div class="edit-only" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>

        <div class="info-grid">
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="info-item">
                    <div class="info-label">Tournament Title</div>
                    <div class="info-value view-only"><?= htmlspecialchars($tournament['tournament_title']) ?></div>
                    <input type="text" name="tournament_title" class="form-control edit-only" value="<?= htmlspecialchars($tournament['tournament_title']) ?>">
                </div>

                <div class="info-item">
                    <div class="info-label">Tournament Date</div>
                    <div class="info-value view-only"><?= date('l, d F Y', strtotime($tournament['tournament_date'])) ?></div>
                    <input type="date" name="tournament_date" class="form-control edit-only" value="<?= $tournament['tournament_date'] ?>">
                </div>

                <div class="info-item">
                    <div class="info-label">Start Time</div>
                    <div class="info-value view-only"><?= date('h:i A', strtotime($tournament['start_time'])) ?></div>
                    <input type="time" name="start_time" class="form-control edit-only" value="<?= $tournament['start_time'] ?>">
                </div>

                <div class="info-item">
                    <div class="info-label">End Time</div>
                    <div class="info-value view-only"><?= date('h:i A', strtotime($tournament['end_time'])) ?></div>
                    <input type="time" name="end_time" class="form-control edit-only" value="<?= $tournament['end_time'] ?>">
                </div>

                <div class="info-item">
                    <div class="info-label">Location</div>
                    <div class="info-value view-only">
                        <i class="fas fa-map-marker-alt" style="color: var(--color-blue-primary);"></i>
                        <?= htmlspecialchars($tournament['location']) ?>
                    </div>
                    <input type="text" name="location" class="form-control edit-only" value="<?= htmlspecialchars($tournament['location']) ?>">
                </div>

                <div class="info-item">
                    <div class="info-label">Registration Fee</div>
                    <div class="info-value view-only">RM <?= number_format($tournament['tournament_fee'], 2) ?></div>
                    <input type="number" step="0.01" name="tournament_fee" class="form-control edit-only" value="<?= $tournament['tournament_fee'] ?>">
                </div>

                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="view-only">
                        <span class="badge badge-<?= $tournament['status'] ?>">
                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                            <?= ucfirst($tournament['status']); ?>
                        </span>
                    </div>
                    <select name="status" class="form-control edit-only">
                        <option value="upcoming" <?= $tournament['status']=='upcoming'?'selected':'' ?>>Upcoming</option>
                        <option value="ongoing" <?= $tournament['status']=='ongoing'?'selected':'' ?>>Ongoing</option>
                        <option value="completed" <?= $tournament['status']=='completed'?'selected':'' ?>>Completed</option>
                        <option value="cancelled" <?= $tournament['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="info-item">
                    <div class="info-label">Organized By</div>
                    <div class="info-value"><?= $tournament['organizer_name'] ?: "Admin" ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Created On</div>
                    <div class="info-value"><?= date("d F Y, g:i A", strtotime($tournament['created_at'])) ?></div>
                </div>

                <div style="margin-top: 1rem;">
                    <div class="info-label" style="margin-bottom: 0.75rem;">Tournament Image</div>
                    <div class="tournament-image-container view-only">
                        <?php if ($tournament['image']): ?>
                            <img src="../../assets/images/tournaments/<?= htmlspecialchars($tournament['image']) ?>" alt="Tournament">
                        <?php else: ?>
                            <div style="display:flex;align-items:center;justify-content:center;height:100%;background:#f5f5f5;">
                                <i class="fas fa-image" style="font-size:3rem;color:#ccc;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="edit-only">
                        <input type="file" name="tournament_image" class="form-control" accept="image/*">
                        <?php if ($tournament['image']): ?>
                            <small style="color: #6c757d; display: block; margin-top: 0.5rem;">Current: <?= htmlspecialchars($tournament['image']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-align-left"></i>
                Description
            </h3>
        </div>
        
        <div class="info-value view-only" style="line-height: 1.7; color: #495057;">
            <?= nl2br(htmlspecialchars($tournament['description'])) ?>
        </div>
        <textarea name="description" class="form-control edit-only" rows="5" placeholder="Enter tournament description..."><?= htmlspecialchars($tournament['description']) ?></textarea>
    </div>

    <div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-gavel"></i>
                Tournament Rules
            </h3>
        </div>
        
        <div class="info-value view-only" style="line-height: 1.7; color: #495057;">
            <?php if (!empty($tournament['tournament_rules'])): ?>
                <?= nl2br(htmlspecialchars($tournament['tournament_rules'])) ?>
            <?php else: ?>
                <span style="color: #999; font-style: italic;">No rules specified</span>
            <?php endif; ?>
        </div>
        <textarea name="tournament_rules" class="form-control edit-only" rows="6" placeholder="Enter tournament rules and regulations..."><?= htmlspecialchars($tournament['tournament_rules']) ?></textarea>
    </div>

    <div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-credit-card"></i>
                Payment Information
            </h3>
        </div>

        <div class="info-grid">
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="info-item">
                    <div class="info-label">Bank Name</div>
                    <div class="info-value view-only"><?= htmlspecialchars($tournament['bank_account_name']) ?></div>
                    <input type="text" name="bank_account_name" class="form-control edit-only" value="<?= htmlspecialchars($tournament['bank_account_name']) ?>">
                </div>

                <div class="info-item">
                    <div class="info-label">Account Number</div>
                    <div class="info-value view-only"><?= htmlspecialchars($tournament['bank_account_number']) ?></div>
                    <input type="text" name="bank_account_number" class="form-control edit-only" value="<?= htmlspecialchars($tournament['bank_account_number']) ?>">
                </div>

                <div class="info-item">
                    <div class="info-label">Account Holder</div>
                    <div class="info-value view-only"><?= htmlspecialchars($tournament['bank_account_holder']) ?></div>
                    <input type="text" name="bank_account_holder" class="form-control edit-only" value="<?= htmlspecialchars($tournament['bank_account_holder']) ?>">
                </div>
            </div>

            <div>
                <div class="info-label" style="margin-bottom: 0.75rem;">Payment QR Code</div>
                <?php if ($tournament['bank_qr']): ?>
                    <div class="qr-container view-only">
                        <img src="../../assets/images/qrcodes/<?= htmlspecialchars($tournament['bank_qr']) ?>" alt="QR Code">
                    </div>
                <?php else: ?>
                    <div class="view-only" style="padding: 2rem; text-align: center; background: #f5f5f5; border-radius: 12px;">
                        <i class="fas fa-qrcode" style="font-size: 3rem; color: #ccc;"></i>
                        <p style="margin-top: 0.5rem; color: #999; font-size: 0.875rem;">No QR code uploaded</p>
                    </div>
                <?php endif; ?>
                <div class="edit-only">
                    <input type="file" name="bank_qr_image" class="form-control" accept="image/*">
                    <?php if ($tournament['bank_qr']): ?>
                        <small style="color: #6c757d; display: block; margin-top: 0.5rem;">Current: <?= htmlspecialchars($tournament['bank_qr']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sponsors Section -->
    <div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-handshake"></i>
                Sponsors
            </h3>
            <a href="../sponsor/sponsorList.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-cog"></i> Manage Sponsors
            </a>
        </div>

        <?php if (mysqli_num_rows($sponsors_result) > 0): ?>
            <div style="display: grid; gap: 1rem;">
                <?php 
                $sponsor_index = 1;
                while ($sponsor = mysqli_fetch_assoc($sponsors_result)): 
                ?>
                    <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 12px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #dee2e6;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 0.5rem 0; color: #1a1a1a; font-size: 1.125rem; font-weight: 600;">
                                    Sponsor <?= $sponsor_index ?>: <?= htmlspecialchars($sponsor['sponsor_name']) ?>
                                </h4>
                                <?php if (!empty($sponsor['sponsor_description'])): ?>
                                    <p style="margin: 0; color: #6c757d; font-size: 0.875rem;">
                                        <?= htmlspecialchars($sponsor['sponsor_description']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <span style="background: #28a745; color: white; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 700; font-size: 0.875rem; white-space: nowrap; margin-left: 1rem;">
                                RM <?= number_format($sponsor['sponsored_amount'], 2) ?>
                            </span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <?php if (!empty($sponsor['contact_phone'])): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem; color: #495057; font-size: 0.875rem;">
                                    <i class="fas fa-phone" style="color: var(--color-blue-primary);"></i>
                                    <span><?= htmlspecialchars($sponsor['contact_phone']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($sponsor['contact_email'])): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem; color: #495057; font-size: 0.875rem;">
                                    <i class="fas fa-envelope" style="color: var(--color-blue-primary);"></i>
                                    <span><?= htmlspecialchars($sponsor['contact_email']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php 
                $sponsor_index++;
                endwhile; 
                ?>
            </div>
            
            <?php
            mysqli_data_seek($sponsors_result, 0);
            $total_sponsorship = 0;
            while ($sponsor = mysqli_fetch_assoc($sponsors_result)) {
                $total_sponsorship += $sponsor['sponsored_amount'];
            }
            ?>
            <div style="margin-top: 1rem; padding: 1rem; background: #e8f5e9; border-radius: 12px; border-left: 4px solid #4caf50; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.875rem; color: #2e7d32; font-weight: 600;">Total Sponsorship</div>
                    <div style="font-size: 1.75rem; font-weight: 700; color: #1b5e20;">RM <?= number_format($total_sponsorship, 2) ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.875rem; color: #2e7d32;"><?= $tournament['sponsor_count'] ?> sponsors</div>
                </div>
            </div>
        <?php else: ?>
            <div style="padding: 2rem; text-align: center; background: #f8f9fa; border-radius: 12px;">
                <i class="fas fa-handshake" style="font-size: 3rem; color: #dee2e6; margin-bottom: 1rem;"></i>
                <p style="color: #6c757d; margin: 0;">No sponsors added yet</p>
                <a href="../sponsor/sponsorList.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Add Sponsors
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-trophy"></i>
                Categories & Prizes
            </h3>
            <a href="../prize/managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-cog"></i> Manage Prizes
            </a>
        </div>

        <?php if (mysqli_num_rows($categories_result) > 0): ?>
            <div style="display: grid; gap: 1rem;">
                <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                    <div style="background: white; border: 1px solid #e9ecef; border-radius: 12px; padding: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <div>
                                <h4 style="margin: 0 0 0.5rem 0; color: var(--color-blue-primary); font-size: 1.125rem; font-weight: 600;">
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </h4>
                                <?php if (!empty($category['description'])): ?>
                                    <p style="margin: 0; color: #6c757d; font-size: 0.875rem;">
                                        <?= htmlspecialchars($category['description']) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($category['category_type'] === 'exact_weight' && !empty($category['target_weight'])): ?>
                                    <div style="margin-top: 0.5rem; color: #f57c00; font-size: 0.875rem;">
                                        <i class="fas fa-weight"></i> Target Weight: <?= $category['target_weight'] ?> KG
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span style="background: #f8f9fa; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 600; color: #495057; font-size: 0.875rem;">
                                <?= $category['number_of_ranking'] ?> winners
                            </span>
                        </div>

                        <?php
                        // Fetch prizes for this category
                        $prizes_query = "SELECT * FROM TOURNAMENT_PRIZE 
                                        WHERE tournament_id = '$tournament_id' 
                                        AND category_id = '{$category['category_id']}'
                                        ORDER BY CAST(prize_ranking AS UNSIGNED) ASC";
                        $prizes_result = mysqli_query($conn, $prizes_query);
                        ?>

                        <?php if (mysqli_num_rows($prizes_result) > 0): ?>
                            <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #dee2e6;">
                                            <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 100px;">Place</th>
                                            <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem;">Prize Description</th>
                                            <th style="text-align: right; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 150px;">Value (RM)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($prize = mysqli_fetch_assoc($prizes_result)): ?>
                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                <td style="padding: 0.75rem; font-weight: 700; color: #495057;"><?= htmlspecialchars($prize['prize_ranking']) ?></td>
                                                <td style="padding: 0.75rem; color: #1a1a1a;"><?= htmlspecialchars($prize['prize_description']) ?></td>
                                                <td style="padding: 0.75rem; text-align: right; font-weight: 700; color: #28a745; font-size: 1rem;">
                                                    RM <?= number_format($prize['prize_value'], 2) ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="padding: 2rem; text-align: center; background: #f8f9fa; border-radius: 12px;">
                <i class="fas fa-trophy" style="font-size: 3rem; color: #dee2e6; margin-bottom: 1rem;"></i>
                <p style="color: #6c757d; margin: 0;">No categories or prizes configured yet</p>
                <a href="../prize/managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Add Categories & Prizes
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <div class="section-header">
            <h3 class="section-title">
                <i class="fas fa-star"></i>
                Participant Reviews
            </h3>
        </div>

        <?php
        $reviews_query = "
            SELECT r.*, u.full_name, u.profile_image
            FROM REVIEW r
            INNER JOIN USER u ON r.user_id = u.user_id
            WHERE r.tournament_id = '$tournament_id'
            ORDER BY r.review_date DESC
        ";
        $reviews_result = mysqli_query($conn, $reviews_query);
        ?>

        <?php if (mysqli_num_rows($reviews_result) > 0): ?>
            <?php
            $total_reviews = mysqli_num_rows($reviews_result);
            $total_rating = 0;
            $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
            
            mysqli_data_seek($reviews_result, 0);
            while ($review = mysqli_fetch_assoc($reviews_result)) {
                $total_rating += $review['rating'];
                $rating_counts[$review['rating']]++;
            }
            $average_rating = $total_rating / $total_reviews;
            ?>

            <div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <div style="display: grid; grid-template-columns: auto 1fr; gap: 2rem; align-items: center;">
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; font-weight: 700; color: #1a1a1a;"><?= number_format($average_rating, 1) ?></div>
                        <div style="color: #ff9800; font-size: 1.25rem; margin: 0.5rem 0;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= floor($average_rating)): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i <= ceil($average_rating) && $average_rating - floor($average_rating) >= 0.5): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <div style="color: #6c757d; font-size: 0.875rem;"><?= $total_reviews ?> reviews</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span style="min-width: 40px; font-size: 0.875rem; color: #6c757d;"><?= $rating ?> <i class="fas fa-star" style="color: #ff9800; font-size: 0.75rem;"></i></span>
                                <div style="flex: 1; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                                    <div style="height: 100%; background: #ff9800; width: <?= $total_reviews > 0 ? ($rating_counts[$rating] / $total_reviews * 100) : 0 ?>%;"></div>
                                </div>
                                <span style="min-width: 40px; text-align: right; font-size: 0.875rem; color: #6c757d;"><?= $rating_counts[$rating] ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div style="display: grid; gap: 1rem;">
                <?php mysqli_data_seek($reviews_result, 0); ?>
                <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
                    <div style="background: white; border: 1px solid #e9ecef; border-radius: 12px; padding: 1.5rem;">
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                            <div style="flex-shrink: 0;">
                                <?php if (!empty($review['profile_image'])): ?>
                                    <img src="../../assets/images/profiles/<?= htmlspecialchars($review['profile_image']) ?>" 
                                         alt="<?= htmlspecialchars($review['full_name']) ?>"
                                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #e9ecef;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--color-blue-primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.25rem;">
                                        <?= strtoupper(substr($review['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <h4 style="margin: 0 0 0.25rem 0; font-size: 1rem; font-weight: 600; color: #1a1a1a;">
                                            <?= htmlspecialchars($review['full_name']) ?>
                                        </h4>
                                        <div style="color: #6c757d; font-size: 0.8125rem;">
                                            <i class="far fa-clock"></i> <?= date('d M Y, g:i A', strtotime($review['review_date'])) ?>
                                        </div>
                                    </div>
                                    <div style="color: #ff9800; font-size: 1rem;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $review['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if (!empty($review['review_text'])): ?>
                                    <p style="margin: 0; color: #495057; line-height: 1.6; font-size: 0.9375rem;">
                                        <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="padding: 2rem; text-align: center; background: #f8f9fa; border-radius: 12px;">
                <i class="fas fa-star" style="font-size: 3rem; color: #dee2e6; margin-bottom: 1rem;"></i>
                <p style="color: #6c757d; margin: 0;">No reviews yet</p>
                <p style="color: #999; font-size: 0.875rem; margin-top: 0.5rem;">Reviews will appear here after the tournament is completed</p>
            </div>
        <?php endif; ?>
    </div>
</form>

<script>
function toggleEditMode() {
    const form = document.getElementById("tournamentForm");
    const btn = document.getElementById("toggleEditBtn");
    
    if (form.classList.contains("edit-mode")) {
        cancelEdit();
    } else {
        form.classList.add("edit-mode");
        btn.innerHTML = '<i class="fas fa-eye"></i> View Mode';
        btn.className = 'btn btn-secondary';
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function cancelEdit() {
    const form = document.getElementById("tournamentForm");
    const btn = document.getElementById("toggleEditBtn");
    
    form.classList.remove("edit-mode");
    form.reset();
    btn.innerHTML = '<i class="fas fa-edit"></i> Edit';
    btn.className = 'btn btn-primary';
}

let formChanged = false;
document.getElementById('tournamentForm').addEventListener('input', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged && document.getElementById('tournamentForm').classList.contains('edit-mode')) {
        e.preventDefault();
        e.returnValue = '';
    }
});

document.getElementById('tournamentForm').addEventListener('submit', function() {
    formChanged = false;
});
</script>

<?php include '../includes/footer.php'; ?>