<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['tournament_id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Get tournament info
$tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = '$tournament_id'";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found';
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

$page_title = 'Create Weighing Station';
$page_description = 'Add a new weighing station for ' . $tournament['tournament_title'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $station_name = sanitize($_POST['station_name']);
    $marshal_name = sanitize($_POST['marshal_name'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (empty($station_name)) {
        $error = 'Station name is required';
    } else {
        $insert_query = "
            INSERT INTO WEIGHING_STATION (tournament_id, station_name, marshal_name, status, notes)
            VALUES ('$tournament_id', '$station_name', '$marshal_name', 'active', '$notes')
        ";
        
        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['success'] = 'Weighing station created successfully!';
            redirect(SITE_URL . '/admin/catch/stationList.php?tournament_id=' . $tournament_id);
        } else {
            $error = 'Failed to create station: ' . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<div class="form-container">
    <h2 class="form-header-title">
        <i class="fas fa-weight"></i> Create Weighing Station
    </h2>
    <p class="form-header-subtitle">
        Tournament: <?php echo htmlspecialchars($tournament['tournament_title']); ?>
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
                       placeholder="e.g., S1, S2, Station A" required>
                <span class="hint">Give a unique name to identify this station</span>
            </div>

            <div class="form-group">
                <label>Marshal Name</label>
                <input type="text" name="marshal_name" class="form-control" 
                       placeholder="e.g., John Doe">
                <span class="hint">Person in charge of this station</span>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" 
                          placeholder="Any additional notes about this station..."></textarea>
                <span class="hint">Optional notes or remarks</span>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="btn-group">
            <a href="stationList.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i> Create Station
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>