<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Require login
requireLogin();

// Admins shouldn't access this page
if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

$page_title = 'Tournaments';
include '../../includes/header.php';

// Auto-update tournament status
$update_query = "
    UPDATE TOURNAMENT
    SET status = CASE
        WHEN NOW() < CONCAT(tournament_date, ' ', start_time) THEN 'upcoming'
        WHEN NOW() BETWEEN CONCAT(tournament_date, ' ', start_time) AND CONCAT(tournament_date, ' ', end_time) THEN 'ongoing'
        WHEN NOW() > CONCAT(tournament_date, ' ', end_time) THEN 'completed'
        ELSE status
    END
    WHERE status != 'cancelled'
";
$db->execute($update_query);

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_asc';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter != 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(t.tournament_title LIKE ? OR t.location LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Determine sort order
$order_by = "ORDER BY ";
switch ($sort_by) {
    case 'date_desc':
        $order_by .= "t.tournament_date DESC";
        break;
    case 'date_asc':
        $order_by .= "t.tournament_date ASC";
        break;
    case 'price_low':
        $order_by .= "t.tournament_fee ASC";
        break;
    case 'price_high':
        $order_by .= "t.tournament_fee DESC";
        break;
    case 'participants':
        $order_by .= "registered_count DESC";
        break;
    default:
        $order_by .= "t.tournament_date ASC";
}

$sql = "SELECT t.*, u.full_name as organizer_name,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
         WHERE tournament_id = t.tournament_id 
         AND approval_status IN ('pending', 'approved')) as registered_count,
        (SELECT COUNT(*) FROM SAVED 
         WHERE tournament_id = t.tournament_id 
         AND user_id = ? 
         AND is_saved = TRUE) as is_saved,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
         WHERE tournament_id = t.tournament_id 
         AND user_id = ? 
         AND approval_status IN ('pending', 'approved')) as user_registered
        FROM TOURNAMENT t
        LEFT JOIN USER u ON t.user_id = u.user_id
        $where_clause
        $order_by, t.created_at DESC";

array_unshift($params, $_SESSION['user_id'], $_SESSION['user_id']);
$tournaments = $db->fetchAll($sql, $params);
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
}

/* Hero Section */
.tournaments-hero {
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
    padding: 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
}

.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 10px;
    background: #F3F4F6;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.filter-tab:hover {
    background: #E5E7EB;
}

.filter-tab.active {
    background: var(--ocean-light);
    color: var(--white);
}

.search-controls {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
}

.search-wrapper {
    position: relative;
}

.search-input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    font-size: 14px;
}

.search-input:focus {
    outline: none;
    border-color: var(--ocean-light);
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.sort-select {
    padding: 12px 40px 12px 16px;
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    background: var(--white);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    min-width: 200px;
}

/* Tournament Grid - 3 Columns Full Width */
.tournaments-container {
    max-width: 100%;
    padding: 4px 60px 60px;
}

.tournaments-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.tournament-card {
    background: var(--white);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    border: 1px solid #E5E7EB;
}

.tournament-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
}

.card-image {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.tournament-card:hover .card-image img {
    transform: scale(1.05);
}

.status-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    backdrop-filter: blur(10px);
}

.status-badge.upcoming { background: rgba(59, 130, 246, 0.9); color: white; }
.status-badge.ongoing { background: rgba(245, 158, 11, 0.9); color: white; }
.status-badge.completed { background: rgba(16, 185, 129, 0.9); color: white; }

.save-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.95);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.save-btn:hover {
    transform: scale(1.1);
}

.save-btn i {
    font-size: 16px;
    color: var(--text-dark);
}

.save-btn.saved i {
    color: #EF4444;
}

.card-content {
    padding: 16px;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.location-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--ocean-light);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.card-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 12px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 47px;
}

.card-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-dark);
}

.meta-item i {
    width: 16px;
    color: var(--ocean-light);
    font-size: 13px;
}

.registered-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ECFDF5;
    color: #065F46;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 12px;
    border: 1px solid #A7F3D0;
    align-self: flex-start;
}

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    margin-top: auto;
    border-top: 1px solid #F3F4F6;
}

.price-tag {
    display: flex;
    flex-direction: column;
}

.price-amount {
    font-size: 24px;
    font-weight: 800;
    color: var(--ocean-blue);
    line-height: 1;
}

.price-label {
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
}

.btn-view {
    padding: 10px 20px;
    background: var(--ocean-light);
    color: var(--white);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-view:hover {
    background: var(--ocean-blue);
}

/* Responsive */
@media (max-width: 1400px) {
    .tournaments-container,
    .filter-section,
    .hero-content {
        padding-left: 40px;
        padding-right: 40px;
    }
}

@media (max-width: 1200px) {
    .tournaments-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }
    
    .tournaments-grid {
        grid-template-columns: 1fr;
    }
    
    .tournaments-container,
    .filter-section,
    .hero-content {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .search-controls {
        grid-template-columns: 1fr;
    }
    
    .sort-select {
        width: 100%;
    }
}
</style>

<!-- Hero Section -->
<div class="tournaments-hero">
    <div class="hero-content">
        <h1 class="hero-title">Discover Fishing Tournaments</h1>
        <p class="hero-subtitle">Join exciting competitions and compete with anglers</p>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-card">
        <div class="filter-tabs">
            <a href="?status=all&sort=<?php echo $sort_by; ?>" 
               class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                All Tournaments
            </a>
            <a href="?status=upcoming&sort=<?php echo $sort_by; ?>" 
               class="filter-tab <?php echo $status_filter == 'upcoming' ? 'active' : ''; ?>">
                Upcoming
            </a>
            <a href="?status=ongoing&sort=<?php echo $sort_by; ?>" 
               class="filter-tab <?php echo $status_filter == 'ongoing' ? 'active' : ''; ?>">
                Live Now
            </a>
            <a href="?status=completed&sort=<?php echo $sort_by; ?>" 
               class="filter-tab <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
                Completed
            </a>
        </div>
        
        <form action="" method="GET" class="search-controls">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       name="search" 
                       class="search-input"
                       placeholder="Search tournaments..." 
                       value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <select class="sort-select" name="sort" onchange="this.form.submit()">
                <option value="date_asc" <?php echo $sort_by == 'date_asc' ? 'selected' : ''; ?>>Soonest First</option>
                <option value="date_desc" <?php echo $sort_by == 'date_desc' ? 'selected' : ''; ?>>Latest First</option>
                <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Lowest Price</option>
                <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Highest Price</option>
            </select>
        </form>
    </div>
</div>

<!-- Tournaments Grid -->
<div class="tournaments-container">
    <?php if ($tournaments && count($tournaments) > 0): ?>
        <div class="tournaments-grid">
            <?php foreach ($tournaments as $tournament): ?>
                <div class="tournament-card" onclick="window.location.href='tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>'">
                    <div class="card-image">
                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                        
                        <span class="status-badge <?php echo $tournament['status']; ?>">
                            <?php echo $tournament['status']; ?>
                        </span>
                        
                        <?php if ($tournament['user_registered'] == 0): ?>
                            <button class="save-btn <?php echo $tournament['is_saved'] > 0 ? 'saved' : ''; ?>" 
                                    onclick="event.stopPropagation(); toggleSave(<?php echo $tournament['tournament_id']; ?>, this)">
                                <i class="<?php echo $tournament['is_saved'] > 0 ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-content">
                        <div class="location-tag">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php 
                            $location_parts = explode('-', $tournament['location']);
                            echo htmlspecialchars(trim($location_parts[0]));
                            ?>
                        </div>
                        
                        <h3 class="card-title">
                            <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                        </h3>
                        
                        <div class="card-meta">
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('M j, Y', strtotime($tournament['tournament_date'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo formatTime($tournament['start_time']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo $tournament['registered_count']; ?> Anglers</span>
                            </div>
                        </div>
                        
                        <?php if ($tournament['user_registered'] > 0): ?>
                            <div class="registered-badge">
                                <i class="fas fa-check-circle"></i>
                                Registered
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-footer">
                            <div class="price-tag">
                                <span class="price-amount">RM<?php echo number_format($tournament['tournament_fee'], 0); ?></span>
                                <span class="price-label">Entry Fee</span>
                            </div>
                            <a href="tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
                               class="btn-view"
                               onclick="event.stopPropagation()">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-fish" style="font-size: 48px; color: #D1D5DB; margin-bottom: 16px;"></i>
            <h3 style="font-size: 20px; margin-bottom: 8px;">No tournaments found</h3>
            <p style="color: var(--text-muted);">Try adjusting your filters</p>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleSave(tournamentId, button) {
    const isSaved = button.classList.contains('saved');
    const icon = button.querySelector('i');
    button.disabled = true;
    
    fetch('<?php echo SITE_URL; ?>/pages/tournament/toggle-save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `tournament_id=${tournamentId}&action=${isSaved ? 'unsave' : 'save'}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.toggle('saved');
            icon.classList.toggle('fas');
            icon.classList.toggle('far');
        }
    })
    .finally(() => button.disabled = false);
}
</script>

<?php include '../../includes/footer.php'; ?>