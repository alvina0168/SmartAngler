<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$isLoggedIn = isLoggedIn();
$currentUserId = $isLoggedIn ? $_SESSION['user_id'] : null;

if ($isLoggedIn && isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

if (!isset($_GET['tournament_id'])) {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$tournament_id = intval($_GET['tournament_id']);

$tournament_q = $conn->prepare("SELECT tournament_title, tournament_date, status, location FROM TOURNAMENT WHERE tournament_id = ?");
$tournament_q->bind_param("i", $tournament_id);
$tournament_q->execute();
$tournament = $tournament_q->get_result()->fetch_assoc();
$tournament_q->close();

if (!$tournament) {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$prizes_query = "
    SELECT 
        tp.*,
        c.category_name,
        c.category_type
    FROM TOURNAMENT_PRIZE tp
    JOIN CATEGORY c ON tp.category_id = c.category_id
    WHERE tp.tournament_id = ?
    ORDER BY c.category_name, CAST(tp.prize_ranking AS UNSIGNED) ASC
";
$stmt = $conn->prepare($prizes_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$all_prizes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$prizes_by_category = [];
foreach ($all_prizes as $prize) {
    $cat_id = $prize['category_id'];
    if (!isset($prizes_by_category[$cat_id])) {
        $prizes_by_category[$cat_id] = [
            'category_name' => $prize['category_name'],
            'category_type' => $prize['category_type'],
            'target_weight' => $prize['target_weight'],
            'prizes' => []
        ];
    }
    $prizes_by_category[$cat_id]['prizes'][] = $prize;
}

$results_query = "
    SELECT 
        r.*,
        u.full_name,
        u.phone_number,
        fc.fish_weight,
        fc.fish_species,
        fc.catch_time,
        ws.station_name AS weighing_station,
        tp.prize_description,
        tp.prize_value
    FROM RESULT r
    JOIN USER u ON r.user_id = u.user_id
    JOIN CATEGORY c ON r.category_id = c.category_id
    LEFT JOIN FISH_CATCH fc ON r.catch_id = fc.catch_id
    LEFT JOIN WEIGHING_STATION ws ON fc.station_id = ws.station_id
    LEFT JOIN TOURNAMENT_PRIZE tp ON tp.tournament_id = r.tournament_id 
        AND tp.category_id = r.category_id 
        AND CAST(tp.prize_ranking AS UNSIGNED) = r.ranking_position
    WHERE r.tournament_id = ?
    ORDER BY r.category_id, r.ranking_position ASC
";
$stmt = $conn->prepare($results_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$all_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$results_by_category = [];
foreach ($all_results as $result) {
    $cat_id = $result['category_id'];
    $rank = $result['ranking_position'];
    $results_by_category[$cat_id][$rank] = $result;
}

$page_title = 'Tournament Results - ' . $tournament['tournament_title'];
include '../../includes/header.php';
?>

<style>
:root {
    --ocean-blue: #0A4D68;
    --ocean-light: #088395;
    --ocean-teal: #05BFDB;
    --sand: #F8F6F0;
    --text-dark: #1A1A1A;
    --text-muted: #6B7280;
    --white: #FFFFFF;
    --border: #E5E7EB;
}

.results-hero {
    background: linear-gradient(135deg, var(--ocean-blue) 0%, var(--ocean-light) 100%);
    padding: 60px 0 100px;
    position: relative;
}

.hero-content {
    max-width: 100%;
    padding: 0 60px;
}

.hero-title {
    font-size: 48px;
    font-weight: 800;
    color: var(--white);
    margin: 0 0 12px;
}

.hero-subtitle {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.hero-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-section {
    max-width: 100%;
    margin: -50px 60px 0;
    position: relative;
    z-index: 10;
}

.filter-card {
    background: var(--white);
    border-radius: 16px;
    padding: 20px 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    display: flex;
    gap: 16px;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--ocean-light);
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
}

.back-button:hover {
    color: var(--ocean-blue);
}

.back-button i {
    font-size: 12px;
}

.results-page {
    background: var(--white);
    padding: 40px 0 60px;
}

.results-container {
    max-width: 100%;
    padding: 0 60px;
}

.category-section {
    margin-bottom: 40px;
}

.category-header {
    padding: 0 0 12px 0;
    border-bottom: 3px solid var(--ocean-light);
    margin-bottom: 20px;
}

.category-title {
    font-size: 24px;
    font-weight: 800;
    margin: 0;
    color: var(--ocean-blue);
    display: flex;
    align-items: center;
    gap: 10px;
}

.target-weight-badge {
    display: inline-block;
    padding: 6px 14px;
    background: rgba(255, 152, 0, 0.2);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #FF9800;
    vertical-align: middle;
}

.results-table-container {
    background: var(--white);
    border: 2px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}

.results-table {
    width: 100%;
    border-collapse: collapse;
}

.results-table thead th {
    background: #7AA5C4;
    color: var(--white);
    padding: 14px 16px;
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    letter-spacing: 0.5px;
    border: 1px solid #6694B4;
}

.results-table tbody td {
    padding: 16px;
    border: 1px solid var(--border);
    color: var(--text-dark);
    font-size: 14px;
}

.results-table tbody tr:hover {
    background: var(--sand);
}

.angler-name {
    font-weight: 700;
    color: var(--text-dark);
}

.angler-email {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.angler-phone {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.weight-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--ocean-blue);
}

.prize-value {
    font-size: 16px;
    font-weight: 800;
    color: #10B981;
}

.no-winner {
    color: var(--text-muted);
    font-style: italic;
}

.status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.upcoming { background: rgba(59, 130, 246, 0.2); color: #3B82F6; }
.status-badge.ongoing { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
.status-badge.completed { background: rgba(16, 185, 129, 0.2); color: #10B981; }

@media (max-width: 1400px) {
    .results-container,
    .filter-section,
    .hero-content {
        padding-left: 40px;
        padding-right: 40px;
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }
    
    .hero-subtitle {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .results-container,
    .filter-section,
    .hero-content {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .results-table-container {
        overflow-x: auto;
    }
    
    .results-table {
        min-width: 1000px;
    }
}
</style>

<!-- Hero Section -->
<div class="results-hero">
    <div class="hero-content">
        <h1 class="hero-title">Tournament Results</h1>
        <div class="hero-subtitle">
            <div class="hero-meta">
                <i class="fas fa-trophy"></i>
                <strong><?php echo htmlspecialchars($tournament['tournament_title']); ?></strong>
            </div>
            <div class="hero-meta">
                <i class="fas fa-calendar"></i>
                <span><?php echo date('M j, Y', strtotime($tournament['tournament_date'])); ?></span>
            </div>
            <div class="hero-meta">
                <span class="status-badge <?php echo $tournament['status']; ?>">
                    <?php echo ucfirst($tournament['status']); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-card">
        <a href="<?php echo SITE_URL; ?>/pages/tournament/tournament-details.php?id=<?php echo $tournament_id; ?>" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Tournament Details
        </a>
        <?php if ($isLoggedIn): ?>
            <a href="<?php echo SITE_URL; ?>/pages/dashboard/myDashboard.php" class="back-button" style="margin-left: auto;">
                <i class="fas fa-tachometer-alt"></i> My Dashboard
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Results Page -->
<div class="results-page">
    <div class="results-container">
        <?php if (count($prizes_by_category) > 0): ?>
            <?php foreach ($prizes_by_category as $cat_id => $category): ?>
            <div class="category-section">
                <div class="category-header">
                    <h2 class="category-title">
                        <?php echo htmlspecialchars($category['category_name']); ?>
                        <?php if ($category['target_weight']): ?>
                            <span class="target-weight-badge" style="margin-left: 12px;">
                                Target: <?php echo $category['target_weight']; ?> KG
                            </span>
                        <?php endif; ?>
                    </h2>
                </div>
                
                <div class="results-table-container">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Rank</th>
                                <th>Participant Name</th>
                                <?php if ($category['category_type'] === 'most_catches'): ?>
                                    <th style="width: 150px; text-align: center;">Total Catches</th>
                                    <th style="width: 180px; text-align: center;">Weighing Station</th>
                                <?php else: ?>
                                    <th style="width: 150px; text-align: center;">Fish Species</th>
                                    <th style="width: 120px; text-align: center;">Weight (KG)</th>
                                    <th style="width: 120px; text-align: center;">Catch Time</th>
                                    <th style="width: 180px; text-align: center;">Weighing Station</th>
                                <?php endif; ?>
                                <th style="width: 200px; text-align: center;">Prize</th>
                                <th style="width: 200px; text-align: center;">Value (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category['prizes'] as $prize): 
                                $rank_num = intval($prize['prize_ranking']);
                                $result = isset($results_by_category[$cat_id][$rank_num]) 
                                          ? $results_by_category[$cat_id][$rank_num] 
                                          : null;
                            ?>
                            <tr>
                                <!-- Rank -->
                                <td style="text-align: center; font-weight: 700; font-size: 16px;">
                                    <?php echo $rank_num; ?>
                                </td>
                                
                                <!-- Participant Name -->
                                <td>
                                    <?php if ($result): ?>
                                        <div class="angler-name"><?php echo htmlspecialchars($result['full_name']); ?></div>
                                        <div class="angler-email"><?php echo htmlspecialchars($result['phone_number']); ?></div>
                                    <?php else: ?>
                                        <span class="no-winner">No winner yet</span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php if ($category['category_type'] === 'most_catches'): ?>
                                    <!-- Total Catches -->
                                    <td style="text-align: center;">
                                        <?php if ($result): ?>
                                            <span class="weight-value"><?php echo $result['total_fish_count']; ?></span>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Weighing Station -->
                                    <td style="text-align: center;">
                                        <?php echo $result && $result['weighing_station'] ? htmlspecialchars($result['weighing_station']) : '-'; ?>
                                    </td>
                                <?php else: ?>
                                    <!-- Fish Species -->
                                    <td style="text-align: center;">
                                        <?php echo $result && $result['fish_species'] ? htmlspecialchars($result['fish_species']) : '-'; ?>
                                    </td>
                                    
                                    <!-- Weight -->
                                    <td style="text-align: center;">
                                        <?php if ($result && $result['fish_weight']): ?>
                                            <span class="weight-value"><?php echo number_format($result['fish_weight'], 2); ?></span>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Catch Time (Time only, no date) -->
                                    <td style="text-align: center;">
                                        <?php if ($result && $result['catch_time']): ?>
                                            <?php echo date('g:i A', strtotime($result['catch_time'])); ?>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Weighing Station -->
                                    <td style="text-align: center;">
                                        <?php echo $result && $result['weighing_station'] ? htmlspecialchars($result['weighing_station']) : '-'; ?>
                                    </td>
                                <?php endif; ?>
                                
                                <!-- Prize Description -->
                                <td style="text-align: center;">
                                    <?php echo htmlspecialchars($prize['prize_description']); ?>
                                </td>
                                
                                <!-- Prize Value -->
                                <td style="text-align: center;">
                                    <span class="prize-value">RM <?php echo number_format($prize['prize_value'], 2); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 80px 20px;">
                <div style="width: 120px; height: 120px; margin: 0 auto 32px; background: linear-gradient(135deg, var(--sand) 0%, #E5E7EB 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-trophy" style="font-size: 56px; color: var(--text-muted);"></i>
                </div>
                <h3 style="font-size: 28px; font-weight: 700; color: var(--text-dark); margin: 0 0 12px;">No Results Yet</h3>
                <p style="font-size: 16px; color: var(--text-muted); margin: 0;">Results will be displayed once prizes are configured</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>