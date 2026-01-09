<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$zone_id = intval($_GET['id']);

// Get zone info
$zone_query = "SELECT z.*, t.tournament_title 
               FROM ZONE z 
               LEFT JOIN TOURNAMENT t ON z.tournament_id = t.tournament_id
               WHERE z.zone_id = '$zone_id'";
$zone_result = mysqli_query($conn, $zone_query);

if (!$zone_result || mysqli_num_rows($zone_result) == 0) {
    $_SESSION['error'] = 'Zone not found';
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$zone = mysqli_fetch_assoc($zone_result);

$page_title = 'Edit Zone - ' . $zone['zone_name'];
$page_description = 'Update zone information';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $zone_name = sanitize($_POST['zone_name']);
    $zone_description = sanitize($_POST['zone_description'] ?? '');
    $tournament_id = sanitize($_POST['tournament_id'] ?? '');
    
    if (empty($zone_name)) {
        $error = 'Zone name is required';
    } else {
        $update_query = "UPDATE ZONE SET 
                         zone_name = '$zone_name',
                         zone_description = '$zone_description',
                         tournament_id = " . ($tournament_id ? "'$tournament_id'" : "NULL") . "
                         WHERE zone_id = '$zone_id'";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Zone updated successfully!';
            redirect(SITE_URL . '/admin/zone/viewZone.php?id=' . $zone_id);
        } else {
            $error = 'Failed to update zone: ' . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-edit"></i> Edit Fishing Zone
        </h2>
        <p class="section-subtitle">Update zone information</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="form">
        <!-- Zone Information Section -->
        <div class="form-section">
            <h3 class="form-section-title">
                <i class="fas fa-info-circle"></i> Zone Information
            </h3>

            <div class="form-group">
                <label for="zone_name">Zone Name <span class="required">*</span></label>
                <input type="text" name="zone_name" id="zone_name" class="form-control" 
                       value="<?php echo htmlspecialchars($zone['zone_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="zone_description">Zone Description</label>
                <textarea name="zone_description" id="zone_description" class="form-control" rows="4"><?php echo htmlspecialchars($zone['zone_description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="tournament_id">Linked Tournament</label>
                <select name="tournament_id" id="tournament_id" class="form-control">
                    <option value="">-- No Tournament --</option>
                    <?php
                    $tournaments = mysqli_query($conn, "SELECT tournament_id, tournament_title FROM TOURNAMENT ORDER BY tournament_date DESC");
                    while ($t = mysqli_fetch_assoc($tournaments)):
                    ?>
                        <option value="<?php echo $t['tournament_id']; ?>" 
                                <?php echo ($t['tournament_id'] == $zone['tournament_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['tournament_title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <!-- Buttons -->
        <div class="form-actions">
            <a href="viewZone.php?id=<?php echo $zone_id; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
