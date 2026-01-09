<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Add Prize';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Fetch tournament info
$tournament_query = "SELECT tournament_title FROM TOURNAMENT WHERE tournament_id = $tournament_id";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Fetch categories
$categories_query = "SELECT * FROM CATEGORY ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Fetch sponsors for this tournament
$sponsors_query = "SELECT * FROM SPONSOR WHERE tournament_id = $tournament_id ORDER BY sponsor_name ASC";
$sponsors_result = mysqli_query($conn, $sponsors_query);

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
        
        $insert_query = "
            INSERT INTO TOURNAMENT_PRIZE (tournament_id, sponsor_id, category_id, prize_ranking, prize_description, prize_value)
            VALUES ($tournament_id, $sponsor_id_value, $category_id, '$prize_ranking', '$prize_description', $prize_value)
        ";
        
        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['success'] = 'Prize added successfully!';
            redirect(SITE_URL . '/admin/prize/managePrize.php?tournament_id=' . $tournament_id);
        } else {
            $_SESSION['error'] = 'Failed to add prize: ' . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Prizes
    </a>
</div>

<!-- Add Prize Form -->
<div class="section">
    <div class="section-header">
        <div>
            <h3 class="section-title">
                <i class="fas fa-plus-circle"></i> Add New Prize
            </h3>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
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
                            <option value="<?= $category['category_id'] ?>">
                                <?= htmlspecialchars($category['category_name']) ?> 
                                (<?= $category['number_of_ranking'] ?> positions)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                        No categories? <a href="../category/addCategory.php" target="_blank">Create one first</a>
                    </small>
                </div>

                <div class="form-group">
                    <label>Ranking Position <span class="required">*</span></label>
                    <select name="prize_ranking" class="form-control" required>
                        <option value="">-- Select Position --</option>
                        <option value="1st">1st Place 🥇</option>
                        <option value="2nd">2nd Place 🥈</option>
                        <option value="3rd">3rd Place 🥉</option>
                        <option value="4th">4th Place</option>
                        <option value="5th">5th Place</option>
                        <option value="6th">6th Place</option>
                        <option value="7th">7th Place</option>
                        <option value="8th">8th Place</option>
                        <option value="9th">9th Place</option>
                        <option value="10th">10th Place</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Prize Value (RM) <span class="required">*</span></label>
                    <input type="number" 
                           name="prize_value" 
                           class="form-control" 
                           step="0.01" 
                           min="0"
                           placeholder="0.00"
                           required>
                    <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                        Estimated cash or retail value
                    </small>
                </div>

                <div class="form-group">
                    <label>Sponsor (Optional)</label>
                    <select name="sponsor_id" class="form-control">
                        <option value="">-- No Sponsor --</option>
                        <?php while ($sponsor = mysqli_fetch_assoc($sponsors_result)): ?>
                            <option value="<?= $sponsor['sponsor_id'] ?>">
                                <?= htmlspecialchars($sponsor['sponsor_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                        Link to sponsor if applicable. <a href="../sponsor/addSponsor.php?tournament_id=<?= $tournament_id ?>" target="_blank">Add sponsor</a>
                    </small>
                </div>
            </div>

            <!-- Right Column -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label>Prize Description <span class="required">*</span></label>
                    <textarea name="prize_description" 
                              class="form-control" 
                              rows="6" 
                              placeholder="e.g., Cash prize RM 5,000 + Trophy, Fishing rod set with accessories, Gift vouchers..."
                              required></textarea>
                </div>

                <div style="padding: 1rem; background: #fff3e0; border-radius: 8px; border-left: 4px solid #ff9800;">
                    <div style="display: flex; align-items: start; gap: 0.75rem;">
                        <i class="fas fa-info-circle" style="color: #f57c00; font-size: 1.25rem; margin-top: 0.125rem;"></i>
                        <div>
                            <strong style="color: #e65100; font-size: 0.875rem;">Prize Examples:</strong>
                            <ul style="margin: 0.5rem 0 0 0; padding-left: 1.25rem; color: #e65100; font-size: 0.8125rem;">
                                <li><strong>1st Place:</strong> Cash RM 5,000 + Trophy</li>
                                <li><strong>2nd Place:</strong> Cash RM 3,000 + Medal</li>
                                <li><strong>3rd Place:</strong> Fishing Equipment Set</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div style="padding: 1rem; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">
                    <div style="display: flex; align-items: start; gap: 0.75rem;">
                        <i class="fas fa-lightbulb" style="color: #388e3c; font-size: 1.25rem; margin-top: 0.125rem;"></i>
                        <div style="color: #2e7d32; font-size: 0.8125rem;">
                            <strong>Quick Setup:</strong> Create multiple prizes for the same category (1st, 2nd, 3rd) to establish a complete prize structure.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save Prize
            </button>
            <a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>