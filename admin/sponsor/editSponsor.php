<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Edit Sponsor';

// Get sponsor ID first
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Sponsor ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$sponsor_id = intval($_GET['id']);
$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

// Get tournament_id from sponsor
$sponsor_check_query = "SELECT tournament_id FROM SPONSOR WHERE sponsor_id = '$sponsor_id'";
$sponsor_check_result = mysqli_query($conn, $sponsor_check_query);

if (!$sponsor_check_result || mysqli_num_rows($sponsor_check_result) == 0) {
    $_SESSION['error'] = 'Sponsor not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$sponsor_data = mysqli_fetch_assoc($sponsor_check_result);
$tournament_id = $sponsor_data['tournament_id'];

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
$sponsor_id = intval($_GET['id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sponsor_name = mysqli_real_escape_string($conn, trim($_POST['sponsor_name']));
    $sponsor_description = mysqli_real_escape_string($conn, trim($_POST['sponsor_description']));
    $contact_phone = mysqli_real_escape_string($conn, trim($_POST['contact_phone']));
    $contact_email = mysqli_real_escape_string($conn, trim($_POST['contact_email']));
    $sponsored_amount = floatval($_POST['sponsored_amount']);
    
    if (empty($sponsor_name)) {
        $_SESSION['error'] = 'Sponsor name is required!';
    } else {
        $update_query = "
            UPDATE SPONSOR SET
                sponsor_name = '$sponsor_name',
                sponsor_description = '$sponsor_description',
                contact_phone = '$contact_phone',
                contact_email = '$contact_email',
                sponsored_amount = $sponsored_amount
            WHERE sponsor_id = $sponsor_id
        ";
        
        // Handle logo upload
        if (!empty($_FILES['sponsor_logo']['name'])) {
            $sponsor_logo = uploadFile($_FILES['sponsor_logo'], 'sponsors');
            if ($sponsor_logo) {
                $update_query = str_replace("WHERE sponsor_id", ", sponsor_logo='$sponsor_logo' WHERE sponsor_id", $update_query);
            }
        }
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Sponsor updated successfully!';
            
            // Get tournament_id for redirect
            $tournament_query = "SELECT tournament_id FROM SPONSOR WHERE sponsor_id = $sponsor_id";
            $tournament_result = mysqli_query($conn, $tournament_query);
            $tournament_id = mysqli_fetch_assoc($tournament_result)['tournament_id'];
            
            redirect(SITE_URL . '/admin/sponsor/sponsorList.php?tournament_id=' . $tournament_id);
        } else {
            $_SESSION['error'] = 'Failed to update sponsor: ' . mysqli_error($conn);
        }
    }
}

// Fetch sponsor
$query = "
    SELECT s.*, t.tournament_title
    FROM SPONSOR s
    JOIN TOURNAMENT t ON s.tournament_id = t.tournament_id
    WHERE s.sponsor_id = $sponsor_id
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Sponsor not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$sponsor = mysqli_fetch_assoc($result);

include '../includes/header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="sponsorList.php?tournament_id=<?= $sponsor['tournament_id'] ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Sponsors
    </a>
</div>

<!-- Edit Sponsor Form -->
<div class="section">
    <div class="section-header">
        <div>
            <h3 class="section-title">
                <i class="fas fa-edit"></i> Edit Sponsor
            </h3>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($sponsor['tournament_title']) ?>
            </p>
        </div>
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="info-grid">
            <!-- Left Column -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label>Sponsor Name <span class="required">*</span></label>
                    <input type="text" 
                           name="sponsor_name" 
                           class="form-control" 
                           value="<?= htmlspecialchars($sponsor['sponsor_name']) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="text" 
                           name="contact_phone" 
                           class="form-control" 
                           value="<?= htmlspecialchars($sponsor['contact_phone']) ?>">
                </div>

                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" 
                           name="contact_email" 
                           class="form-control" 
                           value="<?= htmlspecialchars($sponsor['contact_email']) ?>">
                </div>

                <div class="form-group">
                    <label>Sponsored Amount (RM)</label>
                    <input type="number" 
                           name="sponsored_amount" 
                           class="form-control" 
                           step="0.01" 
                           min="0"
                           value="<?= $sponsor['sponsored_amount'] ?>">
                </div>
            </div>

            <!-- Right Column -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label>Sponsor Logo</label>
                    <?php if (!empty($sponsor['sponsor_logo'])): ?>
                        <div style="margin-bottom: 0.75rem;">
                            <img src="../../assets/images/sponsors/<?= htmlspecialchars($sponsor['sponsor_logo']) ?>" 
                                 alt="Current Logo" 
                                 style="max-width: 200px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        </div>
                    <?php endif; ?>
                    <input type="file" 
                           name="sponsor_logo" 
                           class="form-control"
                           accept="image/*">
                    <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                        Upload new logo to replace current one
                    </small>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="sponsor_description" 
                              class="form-control" 
                              rows="6"><?= htmlspecialchars($sponsor['sponsor_description']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Update Sponsor
            </button>
            <a href="sponsorList.php?tournament_id=<?= $sponsor['tournament_id'] ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>