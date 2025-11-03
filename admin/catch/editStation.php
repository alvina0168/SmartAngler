<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$station_id = intval($_GET['id']);

// Get station info
$station_query = "
    SELECT ws.*, t.tournament_title 
    FROM WEIGHING_STATION ws
    JOIN TOURNAMENT t ON ws.tournament_id = t.tournament_id
    WHERE ws.station_id = '$station_id'
";
$station_result = mysqli_query($conn, $station_query);

if (!$station_result || mysqli_num_rows($station_result) == 0) {
    $_SESSION['error'] = 'Station not found';
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$station = mysqli_fetch_assoc($station_result);

$page_title = 'Edit Station - ' . $station['station_name'];
$page_description = 'Update weighing station information';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $station_name = sanitize($_POST['station_name']);
    $marshal_name = sanitize($_POST['marshal_name'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (empty($station_name)) {
        $error = 'Station name is required';
    } else {
        $update_query = "
            UPDATE WEIGHING_STATION SET 
                station_name = '$station_name',
                marshal_name = '$marshal_name',
                notes = '$notes'
            WHERE station_id = '$station_id'
        ";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Station updated successfully!';
            redirect(SITE_URL . '/admin/catch/stationList.php?tournament_id=' . $station['tournament_id']);
        } else {
            $error = 'Failed to update station: ' . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<div class="form-container">
    <h2 class="form-header-title">
        <i class="fas fa-edit"></i> Edit Weighing Station
    </h2>
    <p class="form-header-subtitle">
        Tournament: <?php echo htmlspecialchars($station['tournament_title']); ?>
    </p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Station Information -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-info-circle"></i>
                Station Information
            </div>
            
            <div class="form-group">
                <label>Station Name <span class="required">*</span></label>
                <input type="text" name="station_name" class="form-control" 
                       value="<?php echo htmlspecialchars($station['station_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Marshal Name</label>
                <input type="text" name="marshal_name" class="form-control" 
                       value="<?php echo htmlspecialchars($station['marshal_name']); ?>">
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control"><?php echo htmlspecialchars($station['notes']); ?></textarea>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="btn-group">
            <a href="stationList.php?tournament_id=<?php echo $station['tournament_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>