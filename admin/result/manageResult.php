<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Result Management';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Handle calculate results
if (isset($_POST['action']) && $_POST['action'] === 'calculate_results') {
    // Clear existing results
    mysqli_query($conn, "DELETE FROM RESULT WHERE tournament_id = $tournament_id");
    
    // Get all prize categories for this tournament with target weights
    $prizes_query = "
        SELECT DISTINCT tp.category_id, tp.target_weight, c.category_type
        FROM TOURNAMENT_PRIZE tp
        JOIN CATEGORY c ON tp.category_id = c.category_id
        WHERE tp.tournament_id = $tournament_id
        ORDER BY c.category_name, tp.target_weight
    ";
    $prizes_result = mysqli_query($conn, $prizes_query);
    
    while ($prize = mysqli_fetch_assoc($prizes_result)) {
        $category_id = $prize['category_id'];
        $category_type = $prize['category_type'];
        $target_weight = $prize['target_weight'];
        
        $where_weight = $target_weight ? "AND ABS(fc.fish_weight - $target_weight) IS NOT NULL" : "";
        
        if ($category_type === 'heaviest') {
            // Heaviest Catch - Get user with heaviest single fish
            $result_query = "
                SELECT fc.user_id, fc.catch_id, MAX(fc.fish_weight) as max_weight
                FROM FISH_CATCH fc
                JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                WHERE tr.tournament_id = $tournament_id AND tr.approval_status = 'approved'
                GROUP BY fc.user_id
                ORDER BY max_weight DESC
                LIMIT 10
            ";
            
        } elseif ($category_type === 'lightest') {
            // Lightest Catch - Get user with lightest single fish
            $result_query = "
                SELECT fc.user_id, fc.catch_id, MIN(fc.fish_weight) as min_weight
                FROM FISH_CATCH fc
                JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                WHERE tr.tournament_id = $tournament_id AND tr.approval_status = 'approved'
                GROUP BY fc.user_id
                ORDER BY min_weight ASC
                LIMIT 10
            ";
            
        } elseif ($category_type === 'most_catches') {
            // Most Catches - Get user with most fish caught
            $result_query = "
                SELECT fc.user_id, NULL as catch_id, COUNT(*) as total_fish_count
                FROM FISH_CATCH fc
                JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                WHERE tr.tournament_id = $tournament_id AND tr.approval_status = 'approved'
                GROUP BY fc.user_id
                ORDER BY total_fish_count DESC
                LIMIT 10
            ";
            
        } elseif ($category_type === 'exact_weight') {
            // Exact Weight - Get user closest to target weight
            $result_query = "
                SELECT fc.user_id, fc.catch_id, fc.fish_weight,
                       ABS(fc.fish_weight - $target_weight) as weight_diff
                FROM FISH_CATCH fc
                JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                WHERE tr.tournament_id = $tournament_id AND tr.approval_status = 'approved'
                ORDER BY weight_diff ASC, fc.catch_time ASC
                LIMIT 10
            ";
        }
        
        if (isset($result_query)) {
            $winners = mysqli_query($conn, $result_query);
            $position = 1;
            
            while ($winner = mysqli_fetch_assoc($winners)) {
                $user_id = $winner['user_id'];
                $catch_id = $winner['catch_id'] ?? null;
                $total_fish = $winner['total_fish_count'] ?? 0;
                
                $catch_value = $catch_id ? $catch_id : 'NULL';
                
                mysqli_query($conn, "
                    INSERT INTO RESULT (tournament_id, user_id, catch_id, category_id, ranking_position, total_fish_count, result_status)
                    VALUES ($tournament_id, $user_id, $catch_value, $category_id, $position, $total_fish, 'final')
                ");
                
                $position++;
            }
        }
    }
    
    $_SESSION['success'] = 'Results calculated successfully!';
    redirect(SITE_URL . '/admin/result/manageResult.php?tournament_id=' . $tournament_id);
}

// Handle finalize results
if (isset($_POST['action']) && $_POST['action'] === 'finalize_results') {
    mysqli_query($conn, "UPDATE RESULT SET result_status = 'final' WHERE tournament_id = $tournament_id");
    mysqli_query($conn, "UPDATE TOURNAMENT SET status = 'completed' WHERE tournament_id = $tournament_id");
    
    $_SESSION['success'] = 'Results finalized successfully!';
    redirect(SITE_URL . '/admin/result/manageResult.php?tournament_id=' . $tournament_id);
}

// Fetch tournament info
$tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = $tournament_id";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Fetch results grouped by category
$results_query = "
    SELECT 
        r.*,
        u.full_name,
        c.category_name,
        c.category_type,
        tp.target_weight,
        tp.prize_description,
        tp.prize_value,
        fc.fish_weight,
        fc.fish_species,
        fc.catch_time
    FROM RESULT r
    JOIN USER u ON r.user_id = u.user_id
    JOIN CATEGORY c ON r.category_id = c.category_id
    LEFT JOIN TOURNAMENT_PRIZE tp ON tp.category_id = r.category_id 
        AND tp.tournament_id = r.tournament_id
        AND CAST(SUBSTRING(tp.prize_ranking, 1, LENGTH(tp.prize_ranking) - 2) AS UNSIGNED) = r.ranking_position
    LEFT JOIN FISH_CATCH fc ON r.catch_id = fc.catch_id
    WHERE r.tournament_id = $tournament_id
    ORDER BY c.category_name, tp.target_weight, r.ranking_position
";
$results_result = mysqli_query($conn, $results_query);

// Group results
$results_by_category = [];
while ($result = mysqli_fetch_assoc($results_result)) {
    $key = $result['category_id'] . '_' . ($result['target_weight'] ?? 'null');
    
    if (!isset($results_by_category[$key])) {
        $results_by_category[$key] = [
            'category_name' => $result['category_name'],
            'category_type' => $result['category_type'],
            'target_weight' => $result['target_weight'],
            'results' => []
        ];
    }
    
    $results_by_category[$key]['results'][] = $result;
}

// Get statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT r.user_id) as total_winners,
        SUM(tp.prize_value) as total_prize_awarded,
        COUNT(DISTINCT r.category_id) as total_categories
    FROM RESULT r
    LEFT JOIN TOURNAMENT_PRIZE tp ON tp.category_id = r.category_id 
        AND tp.tournament_id = r.tournament_id
        AND CAST(SUBSTRING(tp.prize_ranking, 1, LENGTH(tp.prize_ranking) - 2) AS UNSIGNED) = r.ranking_position
    WHERE r.tournament_id = $tournament_id
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include '../includes/header.php';
?>

<style>
.result-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.winner-row {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    transition: all 0.2s ease;
}

.winner-row:hover {
    background: #e3f2fd;
    transform: translateX(5px);
}

.winner-rank {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-right: 1rem;
}

.winner-info {
    flex: 1;
}

.winner-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 0.25rem;
}

.winner-details {
    font-size: 0.875rem;
    color: #6c757d;
}

.winner-prize {
    text-align: right;
    min-width: 200px;
}

.prize-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #28a745;
}

.prize-desc {
    font-size: 0.8125rem;
    color: #6c757d;
}
</style>

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
                <i class="fas fa-trophy"></i> Result Management
            </h2>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <?php if (count($results_by_category) == 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="calculate_results">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Calculate results based on catch records?')">
                        <i class="fas fa-calculator"></i> Calculate Results
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="calculate_results">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Recalculate results? This will override existing results!')">
                        <i class="fas fa-sync-alt"></i> Recalculate
                    </button>
                </form>
                <?php if ($tournament['status'] !== 'completed'): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="finalize_results">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Finalize results? This will mark the tournament as completed!')">
                        <i class="fas fa-check-circle"></i> Finalize Results
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($results_by_category) > 0): ?>
    <!-- Statistics Cards -->
    <div class="dashboard-stats" style="margin-bottom: 0;">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6D94C5, #CBDCEB);">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-value"><?= $stats['total_winners'] ?? 0 ?></div>
            <div class="stat-label">Total Winners</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4caf50, #66bb6a);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-value">RM <?= number_format($stats['total_prize_awarded'] ?? 0, 2) ?></div>
            <div class="stat-label">Total Prize Awarded</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ff9800, #ffb74d);">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-value"><?= count($results_by_category) ?></div>
            <div class="stat-label">Prize Categories</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Results by Category -->
<?php if (count($results_by_category) > 0): ?>
    <?php foreach ($results_by_category as $key => $category_data): ?>
        <div class="section">
            <div class="section-header">
                <div>
                    <h3 class="section-title">
                        <i class="fas fa-trophy"></i>
                        <?= htmlspecialchars($category_data['category_name']) ?>
                    </h3>
                    <?php if ($category_data['target_weight']): ?>
                        <div style="margin-top: 0.5rem; color: #f57c00; font-size: 0.875rem; font-weight: 600;">
                            <i class="fas fa-weight"></i> Target Weight: <?= $category_data['target_weight'] ?> KG
                        </div>
                    <?php endif; ?>
                </div>
                <span class="badge badge-success" style="font-size: 1rem;">
                    <?= count($category_data['results']) ?> Winner(s)
                </span>
            </div>

            <div class="result-card">
                <?php foreach ($category_data['results'] as $result): ?>
                    <div class="winner-row">
                        <div class="winner-rank">
                            <?php
                            if ($result['ranking_position'] == 1) echo '🥇';
                            elseif ($result['ranking_position'] == 2) echo '🥈';
                            elseif ($result['ranking_position'] == 3) echo '🥉';
                            else echo $result['ranking_position'];
                            ?>
                        </div>
                        
                        <div class="winner-info">
                            <div class="winner-name">
                                <?= htmlspecialchars($result['full_name']) ?>
                            </div>
                            <div class="winner-details">
                                <?php if ($category_data['category_type'] === 'most_catches'): ?>
                                    <i class="fas fa-fish"></i> Total Catches: <strong><?= $result['total_fish_count'] ?></strong>
                                <?php elseif ($result['fish_weight']): ?>
                                    <i class="fas fa-fish"></i> <?= htmlspecialchars($result['fish_species']) ?> | 
                                    <i class="fas fa-weight"></i> <?= $result['fish_weight'] ?> KG
                                    <?php if ($category_data['target_weight']): ?>
                                        | <i class="fas fa-bullseye"></i> Diff: <?= abs($result['fish_weight'] - $category_data['target_weight']) ?> KG
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($result['prize_value']): ?>
                        <div class="winner-prize">
                            <div class="prize-value">RM <?= number_format($result['prize_value'], 2) ?></div>
                            <div class="prize-desc"><?= htmlspecialchars($result['prize_description']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-chart-line"></i>
        <h3>No Results Yet</h3>
        <p>Click "Calculate Results" to generate results based on catch records</p>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>