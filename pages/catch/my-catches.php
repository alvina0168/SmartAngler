<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireLogin();

if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

$user_id = $_SESSION['user_id'];
$tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'time_desc';

$tournament = null;
if ($tournament_id > 0) {
    $tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = ?";
    $stmt = $conn->prepare($tournament_query);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$where_clause = "WHERE fc.user_id = ?";
$params = [$user_id];
$param_types = "i";

if ($tournament_id > 0) {
    $where_clause .= " AND t.tournament_id = ?";
    $params[] = $tournament_id;
    $param_types .= "i";
}

$order_by = "ORDER BY ";
switch ($sort_by) {
    case 'time_asc':
        $order_by .= "fc.catch_time ASC";
        break;
    case 'time_desc':
        $order_by .= "fc.catch_time DESC";
        break;
    case 'weight_heaviest':
        $order_by .= "fc.fish_weight DESC";
        break;
    case 'weight_lightest':
        $order_by .= "fc.fish_weight ASC";
        break;
    case 'species':
        $order_by .= "fc.fish_species ASC";
        break;
    default:
        $order_by .= "fc.catch_time DESC";
}

$catches_query = "
    SELECT 
        fc.catch_id,
        fc.fish_species,
        fc.fish_weight,
        fc.catch_time,
        t.tournament_title,
        ws.station_name
    FROM FISH_CATCH fc
    JOIN WEIGHING_STATION ws ON fc.station_id = ws.station_id
    JOIN TOURNAMENT_REGISTRATION treg ON fc.registration_id = treg.registration_id
    JOIN TOURNAMENT t ON treg.tournament_id = t.tournament_id
    $where_clause
    $order_by
";

$stmt = $conn->prepare($catches_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$catches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My Fish Catches';
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

.catches-hero {
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
    padding: 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--ocean-light);
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.2s ease;
}

.back-button:hover {
    color: var(--ocean-blue);
    gap: 12px;
}

.sort-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sort-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dark);
}

.sort-select {
    padding: 10px 40px 10px 16px;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    background: var(--white);
    color: var(--text-dark);
    cursor: pointer;
    transition: all 0.2s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    min-width: 200px;
}

.sort-select:focus {
    outline: none;
    border-color: var(--ocean-light);
    box-shadow: 0 0 0 3px rgba(8, 131, 149, 0.1);
}

.catches-page {
    background: var(--white);
    padding: 40px 0 60px;
}

.catches-container {
    max-width: 100%;
    padding: 0 60px;
}

.catches-table-container {
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    border: 2px solid var(--border);
}

.catches-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.catches-table thead th {
    background: var(--ocean-light);
    color: var(--white);
    padding: 16px;
    text-align: left;
    font-weight: 700;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.catches-table tbody td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text-dark);
    font-size: 14px;
}

.catches-table tbody tr:last-child td {
    border-bottom: none;
}

.catches-table tbody tr:hover {
    background: var(--sand);
}

.weight-value {
    font-size: 20px;
    font-weight: 800;
    color: var(--ocean-blue);
}

.time-display {
    font-weight: 600;
    color: var(--text-dark);
}

.date-display {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 2px;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 32px;
    background: linear-gradient(135deg, var(--sand) 0%, #E5E7EB 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon i {
    font-size: 56px;
    color: var(--text-muted);
}

.empty-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 12px;
}

.empty-text {
    font-size: 16px;
    color: var(--text-muted);
    margin: 0 0 32px;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    color: var(--white);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(8, 131, 149, 0.3);
}

@media (max-width: 1400px) {
    .catches-container,
    .filter-section,
    .hero-content {
        padding-left: 40px;
        padding-right: 40px;
    }
}

@media (max-width: 1200px) {
    .catches-table thead th,
    .catches-table tbody td {
        padding: 12px;
        font-size: 13px;
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }
    
    .catches-container,
    .filter-section,
    .hero-content {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .filter-card {
        flex-direction: column;
        align-items: stretch;
    }
    
    .sort-select {
        width: 100%;
    }
    
    .catches-table-container {
        overflow-x: auto;
    }
    
    .catches-table {
        min-width: 700px;
    }
}
</style>

<div class="catches-hero">
    <div class="hero-content">
        <h1 class="hero-title">My Fish Catches</h1>
        <p class="hero-subtitle">
            <?php if ($tournament): ?>
                Tournament: <?php echo htmlspecialchars($tournament['tournament_title']); ?>
            <?php else: ?>
                All your recorded catches across tournaments
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-card">
        <a href="<?php echo SITE_URL; ?>/pages/dashboard/myDashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="sort-controls">
            <span class="sort-label">Sort by:</span>
            <select class="sort-select" onchange="window.location.href='?tournament_id=<?php echo $tournament_id; ?>&sort=' + this.value">
                <option value="time_desc" <?php echo $sort_by == 'time_desc' ? 'selected' : ''; ?>>Newest First</option>
                <option value="time_asc" <?php echo $sort_by == 'time_asc' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="weight_heaviest" <?php echo $sort_by == 'weight_heaviest' ? 'selected' : ''; ?>>Heaviest</option>
                <option value="weight_lightest" <?php echo $sort_by == 'weight_lightest' ? 'selected' : ''; ?>>Lightest</option>
                <option value="species" <?php echo $sort_by == 'species' ? 'selected' : ''; ?>>Species (A-Z)</option>
            </select>
        </div>
    </div>
</div>

<!-- Catches Page -->
<div class="catches-page">
    <div class="catches-container">
        <?php if (count($catches) > 0): ?>
            <div class="catches-table-container">
                <table class="catches-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <?php if ($tournament_id == 0): ?>
                            <th>Tournament</th>
                            <?php endif; ?>
                            <th>Weight</th>
                            <th>Fish Species</th>
                            <th>Catch Time</th>
                            <th>Weighing Station</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($catches as $catch): 
                        ?>
                        <tr>
                            <td><strong><?php echo $counter++; ?></strong></td>
                            
                            <?php if ($tournament_id == 0): ?>
                            <td>
                                <strong><?php echo htmlspecialchars($catch['tournament_title']); ?></strong>
                            </td>
                            <?php endif; ?>
                            
                            <td>
                                <span class="weight-value"><?php echo number_format($catch['fish_weight'], 2); ?> KG</span>
                            </td>
                            
                            <td>
                                <strong><?php echo htmlspecialchars($catch['fish_species']); ?></strong>
                            </td>
                            
                            <td>
                                <div class="time-display"><?php echo date('g:i A', strtotime($catch['catch_time'])); ?></div>
                                <div class="date-display"><?php echo date('M j, Y', strtotime($catch['catch_time'])); ?></div>
                            </td>
                            
                            <td>
                                <?php echo htmlspecialchars($catch['station_name']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3 class="empty-title">No Catches Yet</h3>
                <p class="empty-text">
                    <?php if ($tournament_id > 0): ?>
                        No catches recorded for this tournament yet.
                    <?php else: ?>
                        You don't have any recorded catches yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
