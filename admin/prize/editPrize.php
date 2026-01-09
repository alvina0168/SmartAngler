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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = intval($_POST['category_id']);
    $sponsor_id = !empty($_POST['sponsor_id']) ? intval($_POST['sponsor_id']) : NULL;
    $prize_ranking = mysqli_real_escape_string($conn, trim($_POST['prize_ranking']));
    $prize_description = mysqli_real_escape_string($conn, trim($_POST['prize_description']));
    $prize_value = floatval($_POST['prize_value']);
    
    if (empty($category_id) || empty($prize_ranking) || empty($prize_description)) {
        $_SESSION['error'] = 'Category, ranking, and description are required!';
    } else {
        $sponsor_id_value = $sponsor_id ? $sponsor_id : 'NULL';
        
        $update_query = "
            UPDATE TOURNAMENT_PRIZE SET
                sponsor_id = $sponsor_id_value,
                category_id = $category_id,
                prize_ranking = '$prize_ranking',
                prize_description = '$prize_description',
                prize_value = $prize_value
            WHERE prize_id = $prize_id
        ";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Prize updated successfully!';
            
            // Get tournament_id for redirect
            $tournament_query = "SELECT tournament_id FROM TOURNAMENT_PRIZE WHERE prize_id = $prize_id";
            $tournament_result = mysqli_query($conn, $tournament_query);
            $tournament_id = mysqli_fetch_assoc($tournament_result)['tournament_id'];
            
            redirect(SITE_URL . '/admin/prize/managePrize.php?tournament_id=' . $tournament_id);
        } else {
            $_SESSION['error'] = 'Failed to update prize: ' . mysqli_error($conn);
        }
    }
}

// Fetch prize
$query = "
    SELECT tp.*, t.tournament_title, t.tournament_id
    FROM TOURNAMENT_PRIZE tp
    JOIN TOURNAMENT t ON tp.tournament_id = t.tournament_id
    WHERE tp.prize_id = $prize_id
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Prize not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$prize = mysqli_fetch_assoc($result);
$tournament_id = $prize['tournament_id'];

// Fetch categories
$categories_query = "SELECT * FROM CATEGORY ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Fetch sponsors for this tournament
$sponsors_query = "SELECT * FROM SPONSOR WHERE tournament_id = $tournament_id ORDER BY sponsor_name ASC";
$sponsors_result = mysqli_query($conn, $sponsors_query);

include '../includes/header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Prizes
    </a>
</div>

<!-- Edit Prize Form -->
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
                    <label>Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?= $category['category_id'] ?>" 
                                    <?= $category['category_id'] == $prize['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['category_name']) ?> 
                                (<?= $category['number_of_ranking'] ?> positions)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ranking Position <span class="required">*</span></label>
                    <select name="prize_ranking" class="form-control" required>
                        <option value="">-- Select Position --</option>
                        <?php
                        $rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
                        $medals = ['🥇', '🥈', '🥉', '', '', '', '', '', '', ''];
                        foreach ($rankings as $index => $rank) {
                            $selected = ($rank == $prize['prize_ranking']) ? 'selected' : '';
                            echo "<option value='$rank' $selected>$rank Place {$medals[$index]}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Prize Value (RM) <span class="required">*</span></label>
                    <input type="number" 
                           name="prize_value" 
                           class="form-control" 
                           step="0.01" 
                           min="0"
                           value="<?= $prize['prize_value'] ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Sponsor (Optional)</label>
                    <select name="sponsor_id" class="form-control">
                        <option value="">-- No Sponsor --</option>
                        <?php while ($sponsor = mysqli_fetch_assoc($sponsors_result)): ?>
                            <option value="<?= $sponsor['sponsor_id'] ?>"
                                    <?= $sponsor['sponsor_id'] == $prize['sponsor_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sponsor['sponsor_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Right Column -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label>Prize Description <span class="required">*</span></label>
                    <textarea name="prize_description" 
                              class="form-control" 
                              rows="8" 
                              required><?= htmlspecialchars($prize['prize_description']) ?></textarea>
                </div>
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