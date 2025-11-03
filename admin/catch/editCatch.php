<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$catch_id = intval($_GET['id']);

// Get catch info
$catch_query = "
    SELECT fc.*, ws.station_name, ws.station_id, t.tournament_title, t.tournament_id, t.tournament_date
    FROM FISH_CATCH fc
    JOIN WEIGHING_STATION ws ON fc.station_id = ws.station_id
    JOIN TOURNAMENT t ON ws.tournament_id = t.tournament_id
    WHERE fc.catch_id = '$catch_id'
";
$catch_result = mysqli_query($conn, $catch_query);

if (!$catch_result || mysqli_num_rows($catch_result) == 0) {
    $_SESSION['error'] = 'Catch record not found';
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$catch = mysqli_fetch_assoc($catch_result);

$page_title = 'Edit Catch Record';
$page_description = 'Update fish catch information';

// Get registered participants for this tournament
$participants_query = "
    SELECT DISTINCT u.user_id, u.full_name
    FROM TOURNAMENT_REGISTRATION tr
    JOIN USER u ON tr.user_id = u.user_id
    WHERE tr.tournament_id = '{$catch['tournament_id']}'
    AND tr.approval_status = 'approved'
    ORDER BY u.full_name ASC
";
$participants_result = mysqli_query($conn, $participants_query);

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = sanitize($_POST['user_id']);
    $fish_species = sanitize($_POST['fish_species']);
    $fish_weight = sanitize($_POST['fish_weight']);
    $catch_time_only = sanitize($_POST['catch_time']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (empty($user_id) || empty($fish_species) || empty($fish_weight) || empty($catch_time_only)) {
        $error = 'Please fill in all required fields';
    } elseif (!is_numeric($fish_weight) || $fish_weight <= 0) {
        $error = 'Fish weight must be a positive number';
    } else {
        // Combine tournament date with catch time
        $catch_datetime = $catch['tournament_date'] . ' ' . $catch_time_only . ':00';
        
        $update_query = "
            UPDATE FISH_CATCH SET 
                user_id = '$user_id',
                fish_species = '$fish_species',
                fish_weight = '$fish_weight',
                catch_time = '$catch_datetime',
                notes = '$notes'
            WHERE catch_id = '$catch_id'
        ";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Catch record updated successfully!';
            redirect(SITE_URL . '/admin/catch/catchList.php?station_id=' . $catch['station_id']);
        } else {
            $error = 'Failed to update catch: ' . mysqli_error($conn);
        }
    }
}

// Extract time from catch_time
$time_only = date('H:i', strtotime($catch['catch_time']));

include '../includes/header.php';
?>

<div class="form-container">
    <h2 class="form-header-title">
        <i class="fas fa-edit"></i> Edit Catch Record
    </h2>
    <p class="form-header-subtitle">
        Catch ID: #<?php echo str_pad($catch_id, 4, '0', STR_PAD_LEFT); ?> | 
        Station: <?php echo htmlspecialchars($catch['station_name']); ?> | 
        Tournament: <?php echo htmlspecialchars($catch['tournament_title']); ?>
    </p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Participant Information -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-user"></i>
                Participant Information
            </div>
            
            <div class="form-group">
                <label>Select Participant <span class="required">*</span></label>
                <select name="user_id" class="form-control" required>
                    <option value="">-- Select Participant --</option>
                    <?php while ($participant = mysqli_fetch_assoc($participants_result)): ?>
                        <option value="<?php echo $participant['user_id']; ?>" 
                                <?php echo ($participant['user_id'] == $catch['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($participant['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <!-- Catch Details -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-fish"></i>
                Catch Details
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Fish Species <span class="required">*</span></label>
                    <input type="text" name="fish_species" class="form-control" 
                           value="<?php echo htmlspecialchars($catch['fish_species']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Fish Weight (KG) <span class="required">*</span></label>
                    <input type="number" name="fish_weight" class="form-control" 
                           step="0.01" min="0.01" 
                           value="<?php echo $catch['fish_weight']; ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Catch Time <span class="required">*</span></label>
                <input type="time" name="catch_time" class="form-control" 
                       value="<?php echo $time_only; ?>" required>
                <span class="hint">Date will be set to tournament date: <?php echo date('d M Y', strtotime($catch['tournament_date'])); ?></span>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control"><?php echo htmlspecialchars($catch['notes']); ?></textarea>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="btn-group">
            <a href="catchList.php?station_id=<?php echo $catch['station_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>