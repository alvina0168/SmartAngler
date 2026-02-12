<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Add Sponsor';

if (!isset($_GET['tournament_id']) || !is_numeric($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Invalid tournament ID!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = (int) $_GET['tournament_id'];
$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

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

$tournament_id = (int) $_GET['tournament_id'];
$tournament_query = "
    SELECT tournament_title 
    FROM TOURNAMENT 
    WHERE tournament_id = $tournament_id
";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) === 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sponsor_name        = mysqli_real_escape_string($conn, trim($_POST['sponsor_name']));
    $sponsor_description = mysqli_real_escape_string($conn, trim($_POST['sponsor_description']));
    $contact_phone       = mysqli_real_escape_string($conn, trim($_POST['contact_phone']));
    $contact_email       = mysqli_real_escape_string($conn, trim($_POST['contact_email']));
    $sponsored_amount    = isset($_POST['sponsored_amount']) ? floatval($_POST['sponsored_amount']) : 0.00;

    if (empty($sponsor_name)) {
        $_SESSION['error'] = 'Sponsor name is required!';
    } elseif (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format!';
    } else {

        $sponsor_logo = NULL;

        if (!empty($_FILES['sponsor_logo']['name'])) {
            $uploaded_logo = uploadFile($_FILES['sponsor_logo'], 'sponsors');
            if ($uploaded_logo) {
                $sponsor_logo = mysqli_real_escape_string($conn, $uploaded_logo);
            } else {
                $_SESSION['error'] = 'Failed to upload sponsor logo!';
            }
        }

        if (!isset($_SESSION['error'])) {

            $insert_query = "
                INSERT INTO SPONSOR (
                    tournament_id,
                    sponsor_name,
                    sponsor_logo,
                    contact_phone,
                    contact_email,
                    sponsor_description,
                    sponsored_amount
                ) VALUES (
                    $tournament_id,
                    '$sponsor_name',
                    " . ($sponsor_logo ? "'$sponsor_logo'" : "NULL") . ",
                    '$contact_phone',
                    '$contact_email',
                    '$sponsor_description',
                    $sponsored_amount
                )
            ";

            if (mysqli_query($conn, $insert_query)) {
                $_SESSION['success'] = 'Sponsor added successfully!';
                redirect(SITE_URL . '/admin/sponsor/sponsorList.php?tournament_id=' . $tournament_id);
            } else {
                $_SESSION['error'] = 'Database error: ' . mysqli_error($conn);
            }
        }
    }
}

include '../includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="sponsorList.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Sponsors
    </a>
</div>

<div class="section">
    <div class="section-header">
        <div>
            <h3 class="section-title">
                <i class="fas fa-plus-circle"></i> Add New Sponsor
            </h3>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
            </p>
        </div>
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="info-grid">
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label>Sponsor Name <span class="required">*</span></label>
                    <input type="text" 
                           name="sponsor_name" 
                           class="form-control" 
                           placeholder="e.g., ABC Bank, XYZ Corporation" 
                           required>
                </div>

                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="text" 
                           name="contact_phone" 
                           class="form-control" 
                           placeholder="e.g., 012-345-6789">
                </div>

                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" 
                           name="contact_email" 
                           class="form-control" 
                           placeholder="e.g., contact@sponsor.com">
                </div>

                <div class="form-group">
                    <label>Sponsored Amount (RM)</label>
                    <input type="number" 
                           name="sponsored_amount" 
                           class="form-control" 
                           step="0.01" 
                           min="0"
                           value="0.00"
                           placeholder="0.00">
                    <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                        Total value of cash/prizes provided
                    </small>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label>Sponsor Logo</label>
                    <input type="file" 
                           name="sponsor_logo" 
                           class="form-control"
                           accept="image/*">
                    <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                        Upload company logo (JPG, PNG)
                    </small>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="sponsor_description" 
                              class="form-control" 
                              rows="6" 
                              placeholder="Brief description about the sponsor or their contribution..."></textarea>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save Sponsor
            </button>
            <a href="sponsorList.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>