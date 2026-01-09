<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Add Sponsor';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Fetch tournament info
$tournament_query = "SELECT tournament_title FROM TOURNAMENT WHERE tournament_id = $tournament_id";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

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
        $sponsor_logo = '';
        
        // Handle logo upload
        if (!empty($_FILES['sponsor_logo']['name'])) {
            $sponsor_logo = uploadFile($_FILES['sponsor_logo'], 'sponsors');
            if (!$sponsor_logo) {
                $_SESSION['error'] = 'Failed to upload logo.';
            }
        }
        
        if (!isset($_SESSION['error'])) {
            $insert_query = "
                INSERT INTO SPONSOR (tournament_id, sponsor_name, sponsor_logo, sponsor_description, 
                                     contact_phone, contact_email, sponsored_amount)
                VALUES ($tournament_id, '$sponsor_name', '$sponsor_logo', '$sponsor_description', 
                        '$contact_phone', '$contact_email', $sponsored_amount)
            ";
            
            if (mysqli_query($conn, $insert_query)) {
                $_SESSION['success'] = 'Sponsor added successfully!';
                redirect(SITE_URL . '/admin/sponsor/sponsorList.php?tournament_id=' . $tournament_id);
            } else {
                $_SESSION['error'] = 'Failed to add sponsor: ' . mysqli_error($conn);
            }
        }
    }
}

include '../includes/header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="sponsorList.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Sponsors
    </a>
</div>

<!-- Add Sponsor Form -->
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
            <!-- Left Column -->
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

            <!-- Right Column -->
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