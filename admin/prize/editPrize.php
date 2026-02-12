<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Edit Prize';

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Prize ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$prize_id = intval($_GET['id']);
$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

$prize_check_query = "SELECT tournament_id FROM TOURNAMENT_PRIZE WHERE prize_id = '$prize_id'";
$prize_check_result = mysqli_query($conn, $prize_check_query);

if (!$prize_check_result || mysqli_num_rows($prize_check_result) == 0) {
    $_SESSION['error'] = 'Prize not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$prize_data = mysqli_fetch_assoc($prize_check_result);
$tournament_id = $prize_data['tournament_id'];

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

$query = "
    SELECT tp.*, t.tournament_title, c.category_name, c.category_type
    FROM TOURNAMENT_PRIZE tp
    JOIN TOURNAMENT t ON tp.tournament_id = t.tournament_id
    JOIN CATEGORY c ON tp.category_id = c.category_id
    WHERE tp.prize_id = ?
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $prize_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Prize not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$prize = mysqli_fetch_assoc($result);
$tournament_id = $prize['tournament_id'];
$is_exact_weight = $prize['category_type'] === 'exact_weight';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prize_description = trim($_POST['prize_description']);
    $prize_value = floatval($_POST['prize_value']);
    $target_weight = !empty($_POST['target_weight']) ? floatval($_POST['target_weight']) : null;

    if (empty($prize_description)) {
        $_SESSION['error'] = 'Prize description is required!';
    } elseif ($is_exact_weight && empty($target_weight)) {
        $_SESSION['error'] = 'Target weight is required for exact weight categories!';
    } else {
        if ($is_exact_weight) {
            $update_query = "
                UPDATE TOURNAMENT_PRIZE 
                SET prize_description=?, prize_value=?, target_weight=?
                WHERE prize_id=?
            ";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, 'sddi', $prize_description, $prize_value, $target_weight, $prize_id);
        } else {
            $update_query = "
                UPDATE TOURNAMENT_PRIZE 
                SET prize_description=?, prize_value=?
                WHERE prize_id=?
            ";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, 'sdi', $prize_description, $prize_value, $prize_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = 'Prize updated successfully!';
            redirect(SITE_URL . "/admin/prize/managePrize.php?tournament_id=$tournament_id");
        } else {
            $_SESSION['error'] = 'Failed to update prize: ' . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Prizes
    </a>
</div>

<div class="section">
    <div class="section-header">
        <div>
            <h3 class="section-title">
                <i class="fas fa-edit"></i> Edit Prize
            </h3>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($prize['tournament_title']) ?>
            </p>
        </div>
    </div>

    <form method="POST" action="">
        <div class="info-grid">
            <!-- Left Column -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" 
                           class="form-control" 
                           value="<?= htmlspecialchars($prize['category_name']) ?>" 
                           readonly 
                           style="background-color: #f8f9fa;">
                </div>

                <div class="form-group">
                    <label>Ranking Position</label>
                    <input type="text" 
                           class="form-control" 
                           value="<?= htmlspecialchars($prize['prize_ranking']) ?>" 
                           readonly
                           style="background-color: #f8f9fa;">
                </div>

                <?php if ($is_exact_weight): ?>
                <div class="form-group">
                    <label>Target Weight (KG) <span class="required">*</span></label>
                    <input type="number" 
                           name="target_weight" 
                           class="form-control" 
                           step="0.01" 
                           min="0.01"
                           value="<?= $prize['target_weight'] ?>"
                           required>
                    <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                        The exact target weight for this category
                    </small>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Prize Value (RM) <span class="required">*</span></label>
                    <input type="number" 
                           name="prize_value" 
                           class="form-control" 
                           step="0.01" 
                           min="0"
                           value="<?= $prize['prize_value'] ?>"
                           required>
                    <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                        Estimated cash or retail value
                    </small>
                </div>
            </div>

            <!-- Right Column -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label>Prize Description <span class="required">*</span></label>
                    <textarea name="prize_description" 
                              class="form-control" 
                              rows="8" 
                              placeholder="e.g., Cash prize RM 5,000 + Trophy"
                              required><?= htmlspecialchars($prize['prize_description']) ?></textarea>
                </div>

                <?php if ($is_exact_weight): ?>
                <div style="padding: 1rem; background: #fff3e0; border-radius: 8px; border-left: 4px solid #ff9800;">
                    <div style="display: flex; align-items: start; gap: 0.75rem;">
                        <i class="fas fa-info-circle" style="color: #f57c00; font-size: 1.25rem; margin-top: 0.125rem;"></i>
                        <div style="color: #e65100; font-size: 0.8125rem;">
                            <strong>Exact Weight Category:</strong> This prize is awarded to participants whose catch matches the target weight exactly or comes closest to it.
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="padding: 1rem; background: #e3f2fd; border-radius: 8px; border-left: 4px solid var(--color-blue-primary);">
                    <div style="display: flex; align-items: start; gap: 0.75rem;">
                        <i class="fas fa-info-circle" style="color: var(--color-blue-primary); font-size: 1.25rem; margin-top: 0.125rem;"></i>
                        <div style="color: #1565c0; font-size: 0.8125rem;">
                            <strong>Note:</strong> Changes will be reflected immediately in the tournament prize list and results page.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Update Prize
            </button>
            <a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>