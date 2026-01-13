<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Tournament Results';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Auto-calculate results in real-time
// Clear existing results
mysqli_query($conn, "DELETE FROM RESULT WHERE tournament_id = $tournament_id");

// Get all prize categories for this tournament
$prizes_query = "
    SELECT DISTINCT tp.category_id, tp.target_weight, c.category_type, tp.prize_ranking
    FROM TOURNAMENT_PRIZE tp
    JOIN CATEGORY c ON tp.category_id = c.category_id
    WHERE tp.tournament_id = $tournament_id
    ORDER BY c.category_name, tp.target_weight, tp.prize_ranking
";
$prizes_result = mysqli_query($conn, $prizes_query);

$processed_categories = [];

while ($prize = mysqli_fetch_assoc($prizes_result)) {
    $category_id = $prize['category_id'];
    $category_type = $prize['category_type'];
    $target_weight = $prize['target_weight'];
    
    // Create unique key for this category configuration
    $cat_key = $category_id . '_' . ($target_weight ?? 'null');
    
    // Skip if already processed
    if (isset($processed_categories[$cat_key])) {
        continue;
    }
    $processed_categories[$cat_key] = true;
    
    if ($category_type === 'heaviest') {
        // Heaviest Catch - Get users with heaviest single fish
        $result_query = "
            SELECT fc.user_id, fc.catch_id, fc.fish_weight, fc.fish_species, fc.catch_time
            FROM FISH_CATCH fc
            JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
            WHERE tr.tournament_id = $tournament_id AND tr.approval_status = 'approved'
            ORDER BY fc.fish_weight DESC, fc.catch_time ASC
            LIMIT 20
        ";
        
    } elseif ($category_type === 'lightest') {
        // Lightest Catch - Get users with lightest single fish
        $result_query = "
            SELECT fc.user_id, fc.catch_id, fc.fish_weight, fc.fish_species, fc.catch_time
            FROM FISH_CATCH fc
            JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
            WHERE tr.tournament_id = $tournament_id AND tr.approval_status = 'approved'
            ORDER BY fc.fish_weight ASC, fc.catch_time ASC
            LIMIT 20
        ";
        
    } elseif ($category_type === 'most_catches') {
        // Most Catches - Get users with most fish caught
        $result_query = "
            SELECT fc.user_id, NULL as catch_id, COUNT(*) as total_fish_count, NULL as fish_weight, NULL as fish_species, MAX(fc.catch_time) as catch_time
            FROM FISH_CATCH fc
            JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
            WHERE tr.tournament_id = $tournament_id AND tr.approval_status = 'approved'
            GROUP BY fc.user_id
            ORDER BY total_fish_count DESC, catch_time ASC
            LIMIT 20
        ";
        
    } elseif ($category_type === 'exact_weight' && $target_weight) {
        // Exact Weight - MUST be exactly the target weight
        $result_query = "
            SELECT fc.user_id, fc.catch_id, fc.fish_weight, fc.fish_species, fc.catch_time
            FROM FISH_CATCH fc
            JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
            WHERE tr.tournament_id = $tournament_id 
                AND tr.approval_status = 'approved'
                AND fc.fish_weight = $target_weight
            ORDER BY fc.catch_time ASC
            LIMIT 20
        ";
    }
    
    if (isset($result_query)) {
        $winners = mysqli_query($conn, $result_query);
        $position = 1;
        
        while ($winner = mysqli_fetch_assoc($winners)) {
            $user_id = $winner['user_id'];
            $catch_id = isset($winner['catch_id']) && $winner['catch_id'] ? $winner['catch_id'] : 'NULL';
            $total_fish = isset($winner['total_fish_count']) ? $winner['total_fish_count'] : 0;
            
            mysqli_query($conn, "
                INSERT INTO RESULT (tournament_id, user_id, catch_id, category_id, ranking_position, total_fish_count, result_status)
                VALUES ($tournament_id, $user_id, $catch_id, $category_id, $position, $total_fish, 'final')
            ");
            
            $position++;
        }
    }
    
    unset($result_query);
}

// Fetch tournament info
$tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = $tournament_id";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Get all prizes with their rankings
$all_prizes_query = "
    SELECT 
        tp.*,
        c.category_name,
        c.category_type
    FROM TOURNAMENT_PRIZE tp
    JOIN CATEGORY c ON tp.category_id = c.category_id
    WHERE tp.tournament_id = $tournament_id
    ORDER BY c.category_name, tp.target_weight, 
        CAST(SUBSTRING_INDEX(tp.prize_ranking, 'st', 1) AS UNSIGNED),
        CAST(SUBSTRING_INDEX(tp.prize_ranking, 'nd', 1) AS UNSIGNED),
        CAST(SUBSTRING_INDEX(tp.prize_ranking, 'rd', 1) AS UNSIGNED),
        CAST(SUBSTRING_INDEX(tp.prize_ranking, 'th', 1) AS UNSIGNED)
";
$all_prizes_result = mysqli_query($conn, $all_prizes_query);

// Group prizes by category + target weight
$prizes_by_category = [];
while ($prize = mysqli_fetch_assoc($all_prizes_result)) {
    $key = $prize['category_id'] . '_' . ($prize['target_weight'] ?? 'null');
    
    if (!isset($prizes_by_category[$key])) {
        $prizes_by_category[$key] = [
            'category_id' => $prize['category_id'],
            'category_name' => $prize['category_name'],
            'category_type' => $prize['category_type'],
            'target_weight' => $prize['target_weight'],
            'prizes' => []
        ];
    }
    
    $prizes_by_category[$key]['prizes'][] = $prize;
}

// Fetch results and match with prizes
$results_query = "
    SELECT 
        r.*,
        u.full_name,
        u.email,
        fc.fish_weight,
        fc.fish_species,
        fc.catch_time
    FROM RESULT r
    JOIN USER u ON r.user_id = u.user_id
    LEFT JOIN FISH_CATCH fc ON r.catch_id = fc.catch_id
    WHERE r.tournament_id = $tournament_id
    ORDER BY r.category_id, r.ranking_position
";
$results_result = mysqli_query($conn, $results_query);

$results_data = [];
while ($result = mysqli_fetch_assoc($results_result)) {
    $results_data[$result['category_id']][$result['ranking_position']] = $result;
}

// Get statistics
$total_winners = mysqli_num_rows(mysqli_query($conn, "SELECT DISTINCT user_id FROM RESULT WHERE tournament_id = $tournament_id"));
$prize_query = "SELECT SUM(prize_value) as total_value FROM TOURNAMENT_PRIZE WHERE tournament_id = $tournament_id";
$prize_result = mysqli_query($conn, $prize_query);
$prize_data = mysqli_fetch_assoc($prize_result);

include '../includes/header.php';
?>

<style>
.results-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.results-table th {
    background: #6D94C5;
    color: white;
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.875rem;
    border: 1px solid #5a7ca0;
}

.results-table td {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    font-size: 0.875rem;
}

.results-table tbody tr:nth-child(even) {
    background: #f8f9fa;
}

.results-table tbody tr:hover {
    background: #e3f2fd;
}

.no-winner {
    color: #999;
    font-style: italic;
}

.winner-name {
    font-weight: 600;
    color: #1a1a1a;
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
                <i class="fas fa-trophy"></i> Tournament Results
            </h2>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?> - Live Results
            </p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="dashboard-stats" style="margin-bottom: 0;">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6D94C5, #CBDCEB);">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-value"><?= $total_winners ?></div>
            <div class="stat-label">Total Winners</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4caf50, #66bb6a);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-value">RM <?= number_format($prize_data['total_value'] ?? 0, 2) ?></div>
            <div class="stat-label">Total Prize Pool</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ff9800, #ffb74d);">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-value"><?= count($prizes_by_category) ?></div>
            <div class="stat-label">Prize Categories</div>
        </div>
    </div>
</div>

<!-- Results by Category -->
<?php foreach ($prizes_by_category as $key => $category_data): ?>
    <div class="section">
        <div class="section-header">
            <div style="flex: 1;">
                <h3 class="section-title" style="margin-bottom: 0.5rem;">
                    <i class="fas fa-trophy"></i>
                    <?= htmlspecialchars($category_data['category_name']) ?>
                </h3>
                <?php if ($category_data['target_weight']): ?>
                    <div style="margin-top: 0.5rem; color: #f57c00; font-size: 0.875rem; font-weight: 600;">
                        <i class="fas fa-weight"></i> Target Weight: <?= $category_data['target_weight'] ?> KG (Exact Match Required)
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Results Table -->
        <div style="overflow-x: auto;">
            <table class="results-table">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">Rank</th>
                        <th>Participant Name</th>
                        <?php if ($category_data['category_type'] === 'most_catches'): ?>
                            <th style="width: 120px; text-align: center;">Total Catches</th>
                        <?php else: ?>
                            <th style="width: 150px;">Fish Species</th>
                            <th style="width: 120px; text-align: right;">Weight (KG)</th>
                            <th style="width: 180px;">Catch Time</th>
                        <?php endif; ?>
                        <th style="width: 250px;">Prize</th>
                        <th style="width: 120px; text-align: right;">Value (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category_data['prizes'] as $prize): ?>
                        <?php
                        // Extract rank number from prize_ranking (e.g., "1st" -> 1)
                        $rank_num = intval($prize['prize_ranking']);
                        $result = isset($results_data[$category_data['category_id']][$rank_num]) 
                                  ? $results_data[$category_data['category_id']][$rank_num] 
                                  : null;
                        ?>
                        <tr>
                            <!-- Rank -->
                            <td style="text-align: center; font-weight: 700; font-size: 1rem; color: #495057;">
                                <?= $rank_num ?>
                            </td>
                            
                            <!-- Participant Name -->
                            <td>
                                <?php if ($result): ?>
                                    <div class="winner-name"><?= htmlspecialchars($result['full_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: #6c757d;"><?= htmlspecialchars($result['email']) ?></div>
                                <?php else: ?>
                                    <span class="no-winner">No winner yet</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($category_data['category_type'] === 'most_catches'): ?>
                                <!-- Total Catches -->
                                <td style="text-align: center; font-weight: 700; color: var(--color-blue-primary); font-size: 1.125rem;">
                                    <?= $result ? $result['total_fish_count'] : '-' ?>
                                </td>
                            <?php else: ?>
                                <!-- Fish Species -->
                                <td>
                                    <?= $result && $result['fish_species'] ? htmlspecialchars($result['fish_species']) : '-' ?>
                                </td>
                                
                                <!-- Weight -->
                                <td style="text-align: right; font-weight: 700; color: var(--color-blue-primary);">
                                    <?= $result && $result['fish_weight'] ? number_format($result['fish_weight'], 2) : '-' ?>
                                </td>
                                
                                <!-- Catch Time -->
                                <td>
                                    <?php if ($result && $result['catch_time']): ?>
                                        <?= date('d M Y, h:i A', strtotime($result['catch_time'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            
                            <!-- Prize Description -->
                            <td>
                                <?= htmlspecialchars($prize['prize_description']) ?>
                            </td>
                            
                            <!-- Prize Value -->
                            <td style="text-align: right; font-weight: 700; color: #28a745;">
                                RM <?= number_format($prize['prize_value'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if (count($prizes_by_category) == 0): ?>
    <div class="empty-state">
        <i class="fas fa-trophy"></i>
        <h3>No Prize Categories</h3>
        <p>Configure prizes first to view results</p>
        <a href="../prize/managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary">
            <i class="fas fa-cog"></i> Configure Prizes
        </a>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>