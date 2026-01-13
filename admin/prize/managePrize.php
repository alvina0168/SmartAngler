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
        c.category_type,
        c.description as category_description,
        c.target_weight,
        c.number_of_ranking
    FROM TOURNAMENT_PRIZE tp
    LEFT JOIN CATEGORY c ON tp.category_id = c.category_id
    WHERE tp.tournament_id = $tournament_id
    ORDER BY c.category_name ASC, 
             CASE tp.prize_ranking
                 WHEN '1st' THEN 1
                 WHEN '2nd' THEN 2
                 WHEN '3rd' THEN 3
                 WHEN '4th' THEN 4
                 WHEN '5th' THEN 5
                 WHEN '6th' THEN 6
                 WHEN '7th' THEN 7
                 WHEN '8th' THEN 8
                 WHEN '9th' THEN 9
                 WHEN '10th' THEN 10
                 ELSE 99
             END
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
            'description' => $prize['category_description'] ?? '',
            'type' => $prize['category_type'] ?? '',
            'target_weight' => $prize['target_weight'] ?? null,
            'number_of_ranking' => $prize['number_of_ranking'] ?? 0,
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
                <i class="fas fa-trophy"></i> Categories & Prizes
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
                <div style="flex: 1;">
                    <h3 class="section-title" style="margin-bottom: 0.5rem;">
                        <i class="fas fa-trophy"></i>
                        <?= htmlspecialchars($category_data['name']) ?>
                    </h3>
                    <?php if (!empty($category_data['description'])): ?>
                        <p style="color: #6c757d; font-size: 0.875rem; margin: 0;">
                            <?= htmlspecialchars($category_data['description']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($category_data['type'] === 'exact_weight' && !empty($category_data['target_weight'])): ?>
                        <div style="margin-top: 0.5rem; color: #f57c00; font-size: 0.875rem;">
                            <i class="fas fa-weight"></i> Target Weight: <?= $category_data['target_weight'] ?> KG
                        </div>
                    <?php endif; ?>
                </div>
                <span class="badge badge-info">
                    <?= count($category_data['prizes']) ?> <?= count($category_data['prizes']) == 1 ? 'prize' : 'prizes' ?>
                </span>
            </div>

            <!-- Prize Table -->
            <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #dee2e6;">
                            <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 100px;">Place</th>
                            <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem;">Prize Description</th>
                            <th style="text-align: right; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 150px;">Value (RM)</th>
                            <th style="text-align: center; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_data['prizes'] as $prize): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 1.25rem;">
                                            <?php
                                            $ranking = $prize['prize_ranking'];
                                            if ($ranking == '1st') echo '🥇';
                                            elseif ($ranking == '2nd') echo '🥈';
                                            elseif ($ranking == '3rd') echo '🥉';
                                            ?>
                                        </span>
                                        <span style="font-weight: 700; color: #495057;">
                                            <?= htmlspecialchars($prize['prize_ranking']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="padding: 0.75rem; color: #1a1a1a;">
                                    <?= htmlspecialchars($prize['prize_description']) ?>
                                </td>
                                <td style="padding: 0.75rem; text-align: right; font-weight: 700; color: #28a745; font-size: 1rem;">
                                    RM <?= number_format($prize['prize_value'], 2) ?>
                                </td>
                                <td style="padding: 0.75rem; text-align: center;">
                                    <div class="action-btns" style="display: inline-flex; gap: 0.5rem;">
                                        <a href="editPrize.php?id=<?= $prize['prize_id'] ?>" 
                                           class="btn btn-primary btn-sm" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button"
                                                onclick="if(confirm('Are you sure you want to delete this prize?\n\nPlace: <?= htmlspecialchars($prize['prize_ranking']) ?>\nDescription: <?= htmlspecialchars($prize['prize_description']) ?>')) { window.location.href='deletePrize.php?id=<?= $prize['prize_id'] ?>&tournament_id=<?= $tournament_id ?>'; }" 
                                                class="btn btn-danger btn-sm" 
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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