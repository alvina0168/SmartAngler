<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Edit Prize';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Prize ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$prize_id = intval($_GET['id']);

// Fetch prize along with tournament info
$query = "
    SELECT tp.*, t.tournament_title, c.category_name
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prize_description = trim($_POST['prize_description']);
    $prize_value = floatval($_POST['prize_value']);

    if (empty($prize_description)) {
        $_SESSION['error'] = 'Prize description is required!';
    } else {
        $update_query = "
            UPDATE TOURNAMENT_PRIZE 
            SET prize_description=?, prize_value=? 
            WHERE prize_id=?
        ";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, 'sdi', $prize_description, $prize_value, $prize_id);

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

<a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary mb-3">
    <i class="fas fa-arrow-left"></i> Back to Prizes
</a>

<div class="section">
    <h3 class="section-title"><i class="fas fa-edit"></i> Edit Prize</h3>
    <p><?= htmlspecialchars($prize['tournament_title']) ?></p>

    <form method="POST">

        <div class="form-group mb-3">
            <label>Category</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($prize['category_name']) ?>" readonly>
        </div>

        <div class="form-group mb-3">
            <label>Ranking Position</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($prize['prize_ranking']) ?>" readonly>
        </div>

        <div class="form-group mb-3">
            <label>Prize Description *</label>
            <textarea name="prize_description" class="form-control" rows="4" required><?= htmlspecialchars($prize['prize_description']) ?></textarea>
        </div>

        <div class="form-group mb-3">
            <label>Prize Value (RM) *</label>
            <input type="number" step="0.01" min="0" name="prize_value" class="form-control" value="<?= $prize['prize_value'] ?>" required>
        </div>

        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Prize</button>
        <a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
