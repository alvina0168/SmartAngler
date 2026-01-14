<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Require login for users
requireLogin();

if (!isset($_GET['tournament_id'])) {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Fetch tournament info
$tournament_q = $conn->prepare("SELECT tournament_title, tournament_date, status, location FROM TOURNAMENT WHERE tournament_id = ?");
$tournament_q->bind_param("i", $tournament_id);
$tournament_q->execute();
$tournament = $tournament_q->get_result()->fetch_assoc();
$tournament_q->close();

if (!$tournament) {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

// Get all prizes grouped by category
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

// Group prizes by category
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

// Fetch results
$results_query = "
    SELECT 
        r.*,
        u.full_name,
        u.email,
        fc.fish_weight,
        fc.fish_species,
        fc.catch_time,
        tp.prize_description,
        tp.prize_value
    FROM RESULT r
    JOIN USER u ON r.user_id = u.user_id
    JOIN CATEGORY c ON r.category_id = c.category_id
    LEFT JOIN FISH_CATCH fc ON r.catch_id = fc.catch_id
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

// Group results by category and ranking
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

/* Hero Section */
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

/* Filter Section */
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

/* Results Container */
.results-page {
    background: var(--white);
    padding: 40px 0 60px;
}

.results-container {
    max-width: 100%;
    padding: 0 60px;
}

/* Category Cards */
.category-section {
    margin-bottom: 40px;
}

.category-header {
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    color: var(--white);
    padding: 18px 24px;
    border-radius: 16px 16px 0 0;
}

.category-title {
    font-size: 20px;
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.target-weight-badge {
    display: inline-block;
    margin-top: 8px;
    padding: 4px 12px;
    background: rgba(255, 152, 0, 0.2);
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #FF9800;
}

/* Results Table */
.results-table-container {
    background: var(--white);
    border: 1px solid var(--border);
    border-top: none;
    border-radius: 0 0 16px 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}

.results-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.results-table thead th {
    background: #7AA5C4;
    color: var(--white);
    padding: 14px 16px;
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}

.results-table tbody td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text-dark);
    font-size: 14px;
}

.results-table tbody tr:last-child td {
    border-bottom: none;
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

/* Status Badge */
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

/* Responsive */
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
        min-width: 900px;
    }
}
</style>

<!-- Hero Section -->
<div class="results-hero">
    <div class="hero-content">
        <h1 class="hero-title">🏆 Tournament Results</h1>
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
        <a href="<?php echo SITE_URL; ?>/pages/dashboard/myDashboard.php?id=<?php echo $tournament_id; ?>" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
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
                        <i class="fas fa-award"></i>
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </h2>
                    <?php if ($category['target_weight']): ?>
                        <div class="target-weight-badge">
                            <i class="fas fa-weight"></i> Target Weight: <?php echo $category['target_weight']; ?> KG (Exact Match Required)
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="results-table-container">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Rank</th>
                                <th>Participant Name</th>
                                <?php if ($category['category_type'] === 'most_catches'): ?>
                                    <th style="width: 150px; text-align: center;">Total Catches</th>
                                <?php else: ?>
                                    <th style="width: 150px;">Fish Species</th>
                                    <th style="width: 120px; text-align: right;">Weight (KG)</th>
                                    <th style="width: 180px;">Catch Time</th>
                                <?php endif; ?>
                                <th style="width: 200px;">Prize</th>
                                <th style="width: 120px; text-align: right;">Value (RM)</th>
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
                                        <div class="angler-email"><?php echo htmlspecialchars($result['email']); ?></div>
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
                                <?php else: ?>
                                    <!-- Fish Species -->
                                    <td>
                                        <?php echo $result && $result['fish_species'] ? htmlspecialchars($result['fish_species']) : '-'; ?>
                                    </td>
                                    
                                    <!-- Weight -->
                                    <td style="text-align: right;">
                                        <?php if ($result && $result['fish_weight']): ?>
                                            <span class="weight-value"><?php echo number_format($result['fish_weight'], 2); ?></span>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Catch Time -->
                                    <td>
                                        <?php if ($result && $result['catch_time']): ?>
                                            <?php echo date('d M Y, g:i A', strtotime($result['catch_time'])); ?>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                
                                <!-- Prize Description -->
                                <td>
                                    <?php echo htmlspecialchars($prize['prize_description']); ?>
                                </td>
                                
                                <!-- Prize Value -->
                                <td style="text-align: right;">
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
```

## **✅ Fixed Issues:**

### **1. Total Catches Column:**
- ❌ Removed from all categories
- ✅ Only shows for "Most Catches" category
- Other categories show: Fish Species, Weight, Catch Time

### **2. Table Layout (Like Image):**
- Simple rank numbers (no badges)
- Clean blue header (#7AA5C4)
- All prize slots shown
- "No winner yet" for empty slots

### **3. All Rankings Listed:**
- Shows ALL prize rankings from TOURNAMENT_PRIZE
- Displays winner info OR "No winner yet"
- Matches exactly like your image

### **4. Simple Rank Numbers:**
- Just plain numbers (1, 2, 3, 4, 5...)
- No fancy badges or colors
- Clean and simple

### **Table Structure:**
```
Most Catches Category:
Rank | Name | Total Catches | Prize | Value

Other Categories:
Rank | Name | Species | Weight | Time | Prize | Value