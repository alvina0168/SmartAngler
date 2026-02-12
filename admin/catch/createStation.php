<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['tournament_id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$tournament_id = intval($_GET['tournament_id']);
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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <a href="<?php echo SITE_URL; ?>/admin/catch/stationList.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>
<p style="color: var(--color-gray-600); font-size: 0.875rem; margin-bottom: 1.5rem;">
    Tournament: <strong><?php echo htmlspecialchars($tournament['tournament_title']); ?></strong>
</p>

<?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom: 1rem;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
<?php endif; ?>

<div class="section" style="padding: 1.5rem; background: var(--color-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-md);">
    <form method="POST" action="">
        <!-- Station Name -->
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-weight: 600;">Station Name <span style="color: red;">*</span></label>
            <input type="text" name="station_name" class="form-control" placeholder="Enter Station Name" required>
            <small class="form-hint">Give a unique name to identify this station</small>
        </div>

        <!-- Marshal Name -->
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-weight: 600;">Marshal Name</label>
            <input type="text" name="marshal_name" class="form-control" placeholder="Marshal Name">
            <small class="form-hint">Person in charge of this station</small>
        </div>

        <!-- Notes -->
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-weight: 600;">Notes</label>
            <textarea name="notes" class="form-control" placeholder="Any additional notes about this station..."></textarea>
            <small class="form-hint">Optional notes or remarks</small>
        </div>

        <!-- Buttons -->
        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
            <a href="<?php echo SITE_URL; ?>/admin/catch/stationList.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i> Create Station
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
