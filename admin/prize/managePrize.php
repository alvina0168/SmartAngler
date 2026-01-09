<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Prize Management';

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

// Fetch all prizes grouped by category
$prizes_query = "
    SELECT 
        tp.*,
        c.category_name,
        c.number_of_ranking,
        s.sponsor_name,
        s.sponsor_logo
    FROM TOURNAMENT_PRIZE tp
    LEFT JOIN CATEGORY c ON tp.category_id = c.category_id
    LEFT JOIN SPONSOR s ON tp.sponsor_id = s.sponsor_id
    WHERE tp.tournament_id = $tournament_id
    ORDER BY c.category_name ASC, tp.prize_ranking ASC
";
$prizes_result = mysqli_query($conn, $prizes_query);

// Group prizes by category
$prizes_by_category = [];
while ($prize = mysqli_fetch_assoc($prizes_result)) {
    $category_id = $prize['category_id'] ?? 'uncategorized';
    $category_name = $prize['category_name'] ?? 'Uncategorized';
    
    if (!isset($prizes_by_category[$category_id])) {
        $prizes_by_category[$category_id] = [
            'name' => $category_name,
            'prizes' => []
        ];
    }
    
    $prizes_by_category[$category_id]['prizes'][] = $prize;
}

// Calculate statistics
$total_prizes = 0;
$total_value = 0;
foreach ($prizes_by_category as $category) {
    $total_prizes += count($category['prizes']);
    foreach ($category['prizes'] as $prize) {
        $total_value += $prize['prize_value'];
    }
}

include '../includes/header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="../tournament/viewTournament.php?id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Tournament
    </a>
</div>

<!-- Header Section -->
<div class="section">
    <div class="section-header">
        <div>
            <h2 class="section-title">
                <i class="fas fa-gift"></i> Prize Management
            </h2>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
            </p>
        </div>
        <a href="addPrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Add Prize
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="dashboard-stats" style="margin-bottom: 0;">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6D94C5, #CBDCEB);">
                    <i class="fas fa-gift"></i>
                </div>
            </div>
            <div class="stat-value"><?= $total_prizes ?></div>
            <div class="stat-label">Total Prizes</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4caf50, #66bb6a);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-value">RM <?= number_format($total_value, 2) ?></div>
            <div class="stat-label">Total Prize Value</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ff9800, #ffb74d);">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-value"><?= count($prizes_by_category) ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>
</div>

<!-- Prizes by Category -->
<?php if (count($prizes_by_category) > 0): ?>
    <?php foreach ($prizes_by_category as $category_id => $category_data): ?>
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-trophy"></i>
                    <?= htmlspecialchars($category_data['name']) ?>
                </h3>
                <span class="badge badge-info">
                    <?= count($category_data['prizes']) ?> prize(s)
                </span>
            </div>

            <div style="display: grid; gap: 1rem;">
                <?php foreach ($category_data['prizes'] as $prize): ?>
                    <div style="background: #f8f9fa; border-radius: 12px; padding: 1.25rem; border: 1px solid #e9ecef; transition: all 0.2s ease;" 
                         onmouseover="this.style.background='#e3f2fd'; this.style.borderColor='var(--color-blue-light)'" 
                         onmouseout="this.style.background='#f8f9fa'; this.style.borderColor='#e9ecef'">
                        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 1rem;">
                            <!-- Left: Prize Info -->
                            <div style="flex: 1; min-width: 200px;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: linear-gradient(135deg, var(--color-blue-primary), var(--color-blue-light)); color: white; border-radius: 10px; font-weight: 700; font-size: 1rem;">
                                        <?php
                                        $ranking = $prize['prize_ranking'];
                                        if ($ranking == '1st' || $ranking == '1') echo '🥇';
                                        elseif ($ranking == '2nd' || $ranking == '2') echo '🥈';
                                        elseif ($ranking == '3rd' || $ranking == '3') echo '🥉';
                                        else echo substr($ranking, 0, 1);
                                        ?>
                                    </span>
                                    <div>
                                        <div style="font-weight: 600; font-size: 1.125rem; color: #1a1a1a;">
                                            <?= htmlspecialchars($prize['prize_ranking']) ?> Place
                                        </div>
                                        <div style="font-size: 0.875rem; color: #6c757d;">
                                            <?= htmlspecialchars($prize['prize_description']) ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Prize Value & Sponsor -->
                                <div style="display: flex; align-items: center; gap: 1.5rem; margin-top: 0.75rem; flex-wrap: wrap;">
                                    <div>
                                        <div style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Value</div>
                                        <div style="font-size: 1.25rem; font-weight: 700; color: #4caf50;">
                                            RM <?= number_format($prize['prize_value'], 2) ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($prize['sponsor_name'])): ?>
                                        <div>
                                            <div style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Sponsor</div>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <?php if (!empty($prize['sponsor_logo'])): ?>
                                                    <img src="../../assets/images/sponsors/<?= htmlspecialchars($prize['sponsor_logo']) ?>" 
                                                         alt="Sponsor" 
                                                         style="width: 30px; height: 30px; object-fit: contain; border-radius: 6px; background: white; padding: 4px;">
                                                <?php endif; ?>
                                                <span style="font-size: 0.875rem; font-weight: 600; color: #495057;">
                                                    <?= htmlspecialchars($prize['sponsor_name']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Right: Actions -->
                            <div class="action-btns">
                                <a href="editPrize.php?id=<?= $prize['prize_id'] ?>" 
                                   class="btn btn-primary btn-sm" 
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button"
                                        onclick="if(confirm('Delete this prize?')) { window.location.href='deletePrize.php?id=<?= $prize['prize_id'] ?>&tournament_id=<?= $tournament_id ?>'; }" 
                                        class="btn btn-danger btn-sm" 
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-gift"></i>
        <h3>No Prizes Yet</h3>
        <p>Start adding prizes to reward tournament winners</p>
        <a href="addPrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add First Prize
        </a>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>