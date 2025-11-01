<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Create Tournament';
$page_description = 'Add a new fishing tournament';

// Get available zones and spots
$spots_query = "
    SELECT fs.spot_id, 
           CONCAT('Zone ', z.zone_name, ' - Spot #', fs.spot_id) AS spot_display
    FROM FISHING_SPOT fs
    JOIN ZONE z ON fs.zone_id = z.zone_id
    WHERE fs.spot_status = 'available'
    ORDER BY z.zone_name ASC, fs.spot_id ASC
";
$spots_result = mysqli_query($conn, $spots_query);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $user_id = $_SESSION['user_id'];
    $tournament_title = sanitize($_POST['tournament_title']);
    $tournament_date = sanitize($_POST['tournament_date']);
    $location = sanitize($_POST['location']);
    $description = sanitize($_POST['description']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $tournament_fee = sanitize($_POST['tournament_fee']);
    $max_participants = sanitize($_POST['max_participants']);
    $status = sanitize($_POST['status']);
    
    // Optional fields
    $zone_id = sanitize($_POST['zone_id'] ?? '');
    $bank_account_name = sanitize($_POST['bank_account_name'] ?? '');
    $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
    $bank_account_holder = sanitize($_POST['bank_account_holder'] ?? '');
    
    // Handle tournament image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image = uploadFile($_FILES['image'], 'tournaments');
        if (!$image) {
            $error = 'Failed to upload image. Please check file size and format.';
        }
    }
    
    // Handle QR code upload
    $bank_qr = '';
    if (isset($_FILES['bank_qr']) && $_FILES['bank_qr']['error'] == 0) {
        $bank_qr = uploadFile($_FILES['bank_qr'], 'qrcodes');
        if (!$bank_qr) {
            $error = 'Failed to upload QR code. Please check file size and format.';
        }
    }
    
    if (empty($tournament_title) || empty($tournament_date) || empty($location)) {
        $error = 'Please fill in all required fields';
    } elseif (empty($error)) {
        // Insert tournament (no spot_id anymore)
        $query = "INSERT INTO TOURNAMENT 
                  (user_id, tournament_title, tournament_date, location, description, start_time, end_time, 
                   tournament_fee, max_participants, image, status, created_by, 
                   bank_account_name, bank_account_number, bank_account_holder, bank_qr, created_at) 
                  VALUES 
                  ('$user_id', '$tournament_title', '$tournament_date', '$location', '$description', 
                   '$start_time', '$end_time', '$tournament_fee', '$max_participants', '$image', '$status',
                   '$user_id', '$bank_account_name', '$bank_account_number', '$bank_account_holder', '$bank_qr', NOW())";
        
        if (mysqli_query($conn, $query)) {
            $tournament_id = mysqli_insert_id($conn);
            
            // Optional: link selected zone (if provided)
            if (!empty($zone_id)) {
                $update_zone = "UPDATE ZONE SET tournament_id = '$tournament_id' WHERE zone_id = '$zone_id'";
                mysqli_query($conn, $update_zone);
            }

            // Add to calendar
            $calendar_query = "INSERT INTO CALENDAR (tournament_id, event_date, event_title) 
                               VALUES ('$tournament_id', '$tournament_date', '$tournament_title')";
            mysqli_query($conn, $calendar_query);
            
            $_SESSION['success'] = 'Tournament created successfully!';
            redirect(SITE_URL . '/admin/tournament/tournamentList.php');
        } else {
            $error = 'Failed to create tournament. Please try again.<br>' . mysqli_error($conn);
        }
    }
}
include '../includes/header.php';
?>

<div class="form-container">
    <h2 class="form-header-title">
        <i class="fas fa-plus-circle"></i> Create New Tournament
    </h2>
    <p class="form-header-subtitle">Fill in the details below to create a new fishing tournament</p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <!-- Basic Information -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-info-circle"></i>
                Basic Information
            </div>
            
            <div class="form-group">
                <label>Tournament Title <span class="required">*</span></label>
                <input type="text" name="tournament_title" class="form-control" 
                       placeholder="e.g., Sabah Fishing Championship 2025" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Tournament Date <span class="required">*</span></label>
                    <input type="date" name="tournament_date" class="form-control" required>
                </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Start Time <span class="required">*</span></label>
                    <input type="time" name="start_time" class="form-control" value="07:00" required>
                </div>

                <div class="form-group">
                    <label>End Time <span class="required">*</span></label>
                    <input type="time" name="end_time" class="form-control" value="17:00" required>
                </div>
            </div>

            <div class="form-group">
                <label>Location <span class="required">*</span></label>
                <input type="text" name="location" class="form-control" 
                       placeholder="Include Google Maps link (e.g., Kota Kinabalu Jetty - https://goo.gl/maps/...)" required>
                <p class="hint">You can add a Google Maps link for better navigation</p>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" 
                          placeholder="Describe your tournament, rules, prizes, etc."></textarea>
            </div>
        </div>

        <!-- Tournament Details -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-sliders-h"></i>
                Tournament Details
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Tournament Fee (RM)</label>
                    <input type="number" step="0.01" name="tournament_fee" class="form-control" 
                           placeholder="0.00" value="0.00">
                </div>

                <div class="form-group">
                    <label>Max Participants</label>
                    <input type="number" name="max_participants" class="form-control" 
                           placeholder="50" value="50">
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
                        <option value="<?php echo $zone['zone_id']; ?>">
                            <?php echo htmlspecialchars($zone['zone_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <p class="hint">Optionally assign a fishing zone for this tournament</p>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-credit-card"></i>
                Payment Information (Optional)
            </div>

            <div class="form-group">
                <label>Bank Account Name</label>
                <input type="text" name="bank_account_name" class="form-control" 
                       placeholder="e.g., Maybank - SmartAngler">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Bank Account Number</label>
                    <input type="text" name="bank_account_number" class="form-control" 
                           placeholder="1234567890">
                </div>

                <div class="form-group">
                    <label>Account Holder Name</label>
                    <input type="text" name="bank_account_holder" class="form-control" 
                           placeholder="Admin Name">
                </div>
            </div>

            <div class="form-group">
                <label>Payment QR Code</label>
                <label for="qrUpload" class="file-upload">
                    <i class="fas fa-qrcode"></i>
                    <p><strong>Click to upload QR code</strong> or drag and drop</p>
                    <p>Upload your bank QR code for easy payment</p>
                    <input type="file" id="qrUpload" name="bank_qr" accept="image/*">
                </label>
                <p class="hint">Optional: Upload a QR code for customers to scan and pay</p>
            </div>
        </div>

        <!-- Tournament Image -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-image"></i>
                Tournament Image
            </div>

            <div class="form-group">
                <label for="imageUpload" class="file-upload">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Click to upload</strong> or drag and drop</p>
                    <p>PNG, JPG, GIF up to 5MB</p>
                    <input type="file" id="imageUpload" name="image" accept="image/*">
                </label>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="btn-group">
            <a href="tournamentList.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i> Create Tournament
            </button>
        </div>
    </form>
</div>

<script>
// Tournament image upload preview
document.getElementById('imageUpload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const label = document.querySelector('.file-upload');
        label.innerHTML = `
            <i class="fas fa-check-circle" style="color: var(--color-success);"></i>
            <p><strong>${file.name}</strong></p>
            <p>Click to change file</p>
        `;
    }
});

// QR code upload preview
document.getElementById('qrUpload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const labels = document.querySelectorAll('.file-upload');
        const qrLabel = labels[1]; // Second file upload is QR code
        qrLabel.innerHTML = `
            <i class="fas fa-check-circle" style="color: var(--color-success);"></i>
            <p><strong>${file.name}</strong></p>
            <p>QR code ready to upload</p>
        `;
    }
});

// Drag and drop for tournament image
const dropZone = document.querySelector('.file-upload');
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => {
        dropZone.style.borderColor = 'var(--color-blue-primary)';
        dropZone.style.background = 'var(--color-blue-light)';
    }, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => {
        dropZone.style.borderColor = 'var(--color-cream-dark)';
        dropZone.style.background = 'var(--color-cream-light)';
    }, false);
});

dropZone.addEventListener('drop', function(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    document.getElementById('imageUpload').files = files;
    
    if (files.length > 0) {
        const file = files[0];
        dropZone.innerHTML = `
            <i class="fas fa-check-circle" style="color: var(--color-success);"></i>
            <p><strong>${file.name}</strong></p>
            <p>Click to change file</p>
        `;
    }
}, false);
</script>

<?php include '../includes/footer.php'; ?>