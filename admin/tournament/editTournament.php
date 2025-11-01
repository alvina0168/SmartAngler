<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Edit Tournament';
$page_description = 'Modify an existing fishing tournament.';

// Get tournament ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: tournamentList.php');
    exit;
}

$tournament_id = intval($_GET['id']);
$tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = '$tournament_id'";
$tournament_result = mysqli_query($conn, $tournament_query);
if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    echo "<p>Tournament not found.</p>";
    exit;
}
$tournament = mysqli_fetch_assoc($tournament_result);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tournament_title = sanitize($_POST['tournament_title']);
    $tournament_date = sanitize($_POST['tournament_date']);
    $location = sanitize($_POST['location']);
    $description = sanitize($_POST['description']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $tournament_fee = sanitize($_POST['tournament_fee']);
    $max_participants = sanitize($_POST['max_participants']);
    $zone_id = sanitize($_POST['zone_id'] ?? '');
    $bank_account_name = sanitize($_POST['bank_account_name'] ?? '');
    $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
    $bank_account_holder = sanitize($_POST['bank_account_holder'] ?? '');
    
    // Auto status update based on date and time
    $current_datetime = date('Y-m-d H:i:s');
    $start_datetime = $tournament_date . ' ' . $start_time;
    $end_datetime = $tournament_date . ' ' . $end_time;

    if ($current_datetime < $start_datetime) {
        $status = 'upcoming';
    } elseif ($current_datetime >= $start_datetime && $current_datetime <= $end_datetime) {
        $status = 'ongoing';
    } else {
        $status = 'completed';
    }

    // Handle image upload
    $image = $tournament['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploaded = uploadFile($_FILES['image'], 'tournaments');
        if ($uploaded) $image = $uploaded;
        else $error = 'Failed to upload tournament image.';
    }

    // Handle QR code upload
    $bank_qr = $tournament['bank_qr'];
    if (isset($_FILES['bank_qr']) && $_FILES['bank_qr']['error'] == 0) {
        $uploaded_qr = uploadFile($_FILES['bank_qr'], 'qrcodes');
        if ($uploaded_qr) $bank_qr = $uploaded_qr;
        else $error = 'Failed to upload QR code.';
    }

    if (empty($error)) {
        $update_query = "
            UPDATE TOURNAMENT SET
                tournament_title = '$tournament_title',
                tournament_date = '$tournament_date',
                location = '$location',
                description = '$description',
                start_time = '$start_time',
                end_time = '$end_time',
                tournament_fee = '$tournament_fee',
                max_participants = '$max_participants',
                image = '$image',
                status = '$status',
                bank_account_name = '$bank_account_name',
                bank_account_number = '$bank_account_number',
                bank_account_holder = '$bank_account_holder',
                bank_qr = '$bank_qr',
                updated_at = NOW()
            WHERE tournament_id = '$tournament_id'
        ";

        if (mysqli_query($conn, $update_query)) {
            $success = 'Tournament updated successfully!';
            $tournament = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM TOURNAMENT WHERE tournament_id = '$tournament_id'"));
        } else {
            $error = 'Failed to update tournament. ' . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<div class="form-container">
    <h2 class="form-header-title">
        <i class="fas fa-edit"></i> Edit Tournament
    </h2>
    <p class="form-header-subtitle">Update the details below to modify this fishing tournament</p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <!-- Basic Information -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-info-circle"></i> Basic Information
            </div>

            <div class="form-group">
                <label>Tournament Title <span class="required">*</span></label>
                <input type="text" name="tournament_title" class="form-control"
                       value="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Tournament Date <span class="required">*</span></label>
                    <input type="date" name="tournament_date" class="form-control"
                           value="<?php echo htmlspecialchars($tournament['tournament_date']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Start Time <span class="required">*</span></label>
                    <input type="time" name="start_time" class="form-control"
                           value="<?php echo htmlspecialchars($tournament['start_time']); ?>" required>
                </div>

                <div class="form-group">
                    <label>End Time <span class="required">*</span></label>
                    <input type="time" name="end_time" class="form-control"
                           value="<?php echo htmlspecialchars($tournament['end_time']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Location <span class="required">*</span></label>
                <input type="text" name="location" class="form-control"
                       value="<?php echo htmlspecialchars($tournament['location']); ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control"><?php echo htmlspecialchars($tournament['description']); ?></textarea>
            </div>
        </div>

        <!-- Tournament Details -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-sliders-h"></i> Tournament Details
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Tournament Fee (RM)</label>
                    <input type="number" step="0.01" name="tournament_fee" class="form-control"
                           value="<?php echo htmlspecialchars($tournament['tournament_fee']); ?>">
                </div>

                <div class="form-group">
                    <label>Max Participants</label>
                    <input type="number" name="max_participants" class="form-control"
                           value="<?php echo htmlspecialchars($tournament['max_participants']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Fishing Zone (Optional)</label>
                <select name="zone_id" class="form-control">
                    <option value="">-- Select Fishing Zone --</option>
                    <?php
                    $zone_result = mysqli_query($conn, "SELECT zone_id, zone_name FROM ZONE ORDER BY zone_name ASC");
                    while ($zone = mysqli_fetch_assoc($zone_result)):
                    ?>
                        <option value="<?php echo $zone['zone_id']; ?>" 
                            <?php echo ($zone['zone_id'] == ($tournament['zone_id'] ?? '')) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($zone['zone_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-credit-card"></i> Payment Information
            </div>

            <div class="form-group">
                <label>Bank Account Name</label>
                <input type="text" name="bank_account_name" class="form-control"
                       value="<?php echo htmlspecialchars($tournament['bank_account_name']); ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Bank Account Number</label>
                    <input type="text" name="bank_account_number" class="form-control"
                           value="<?php echo htmlspecialchars($tournament['bank_account_number']); ?>">
                </div>

                <div class="form-group">
                    <label>Account Holder Name</label>
                    <input type="text" name="bank_account_holder" class="form-control"
                           value="<?php echo htmlspecialchars($tournament['bank_account_holder']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Payment QR Code</label>
                <?php if (!empty($tournament['bank_qr'])): ?>
                    <img src="../../assets/images/qrcodes/<?php echo $tournament['bank_qr']; ?>" 
                         alt="QR Code" style="max-width:150px; margin-bottom:10px;">
                <?php endif; ?>
                <label for="qrUpload" class="file-upload">
                    <i class="fas fa-qrcode"></i>
                    <p><strong>Click to upload QR code</strong> or drag and drop</p>
                    <input type="file" id="qrUpload" name="bank_qr" accept="image/*">
                </label>
            </div>
        </div>

        <!-- Tournament Image -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-image"></i> Tournament Image
            </div>

            <div class="form-group">
                <?php if (!empty($tournament['image'])): ?>
                    <img src="../../assets/images/tournaments/<?php echo $tournament['image']; ?>" 
                         alt="Tournament Image" style="max-width:150px; margin-bottom:10px;">
                <?php endif; ?>
                <label for="imageUpload" class="file-upload">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Click to upload</strong> or drag and drop</p>
                    <input type="file" id="imageUpload" name="image" accept="image/*">
                </label>
            </div>
        </div>

        <!-- Buttons -->
        <div class="btn-group">
            <a href="tournamentList.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
