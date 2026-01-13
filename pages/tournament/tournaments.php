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
/* Minimal additional styles for tournament listing */
.tournament-list-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    display: flex;
    gap: 20px;
    position: relative;
}

.tournament-list-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.tournament-img-wrapper {
    width: 200px;
    height: 200px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
}

.tournament-img-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.status-overlay {
    position: absolute;
    top: 12px;
    left: 12px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-overlay.upcoming { background: #3B82F6; color: white; }
.status-overlay.ongoing { background: #F59E0B; color: white; }
.status-overlay.completed { background: #10B981; color: white; }

.save-heart-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    z-index: 2;
}

.save-heart-btn:hover {
    background: white;
    transform: scale(1.1);
}

.save-heart-btn i {
    font-size: 16px;
    color: #222222;
}

.save-heart-btn.saved i {
    color: #FF385C;
}

.tournament-list-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.tournament-location-label {
    color: #717171;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.tournament-list-title {
    font-size: 18px;
    font-weight: 600;
    color: #222222;
    margin: 0 0 8px 0;
    line-height: 1.4;
}

.tournament-description-short {
    color: #717171;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tournament-meta-row {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.meta-detail {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #717171;
    font-size: 13px;
}

.meta-detail i {
    color: #222222;
    font-size: 12px;
}

.registered-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #E6F4EA;
    color: #1E7E34;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.tournament-footer-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid #EBEBEB;
}

.price-section {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.price-amount {
    font-size: 20px;
    font-weight: 700;
    color: #222222;
}

.price-text {
    color: #717171;
    font-size: 13px;
}

.search-bar-wrapper {
    position: relative;
    flex: 1;
    min-width: 250px;
}

.search-bar-wrapper input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 1px solid #DDDDDD;
    border-radius: 28px;
    font-size: 14px;
}

.search-bar-wrapper input:focus {
    outline: none;
    box-shadow: 0 0 0 2px #222222;
}

.search-bar-wrapper i {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #717171;
    font-size: 14px;
}

.sort-select-wrapper select {
    padding: 12px 40px 12px 16px;
    border: 1px solid #DDDDDD;
    border-radius: 28px;
    font-size: 14px;
    font-weight: 600;
    background: white;
    color: #222222;
    cursor: pointer;
}

@media (max-width: 768px) {
    .tournament-list-card {
        flex-direction: column;
    }
    
    .tournament-img-wrapper {
        width: 100%;
        height: 240px;
    }
}
</style>

<div style="background: #ffffff; min-height: 100vh; padding: 40px 0;">
    <div class="container">
        <!-- Page Header -->
        <div style="margin-bottom: 32px;">
            <h1 style="font-size: 28px; font-weight: 700; color: #222222; margin: 0 0 8px 0;">
                <?php echo count($tournaments); ?> Fishing Tournaments
                <?php echo !empty($search_query) ? ' for "' . htmlspecialchars($search_query) . '"' : ''; ?>
            </h1>
            <p style="color: #717171; font-size: 15px; margin: 0;">
                Discover and join exciting fishing competitions
            </p>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs" style="margin-bottom: 24px;">
            <a href="?status=all&sort=<?php echo $sort_by; ?>" 
               class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                <i class="fas fa-th"></i> All
            </a>
            <a href="?status=upcoming&sort=<?php echo $sort_by; ?>" 
               class="filter-tab <?php echo $status_filter == 'upcoming' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Upcoming
            </a>
            <a href="?status=ongoing&sort=<?php echo $sort_by; ?>" 
               class="filter-tab <?php echo $status_filter == 'ongoing' ? 'active' : ''; ?>">
                <i class="fas fa-play-circle"></i> Ongoing
            </a>
            <a href="?status=completed&sort=<?php echo $sort_by; ?>" 
               class="filter-tab <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Completed
            </a>
        </div>

        <!-- Search & Sort Controls -->
        <div style="display: flex; gap: 12px; margin-bottom: 32px; align-items: center; flex-wrap: wrap;">
            <div class="search-bar-wrapper">
                <form action="" method="GET">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="Search tournaments, locations, organizers..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </form>
            </div>
            
            <div class="sort-select-wrapper">
                <select onchange="handleSortChange(this.value)">
                    <option value="date_asc" <?php echo $sort_by == 'date_asc' ? 'selected' : ''; ?>>Soonest First</option>
                    <option value="date_desc" <?php echo $sort_by == 'date_desc' ? 'selected' : ''; ?>>Latest First</option>
                    <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Lowest Price</option>
                    <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Highest Price</option>
                </select>
            </div>
        </div>

        <!-- Tournament Cards -->
        <?php if ($tournaments && count($tournaments) > 0): ?>
            <?php foreach ($tournaments as $tournament): ?>
                <div class="tournament-list-card" onclick="window.location.href='tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>'">
                    <!-- Image -->
                    <div class="tournament-img-wrapper">
                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                        
                        <span class="status-overlay <?php echo $tournament['status']; ?>">
                            <?php echo strtoupper($tournament['status']); ?>
                        </span>

                        <?php if ($tournament['user_registered'] == 0): ?>
                            <button class="save-heart-btn <?php echo $tournament['is_saved'] > 0 ? 'saved' : ''; ?>" 
                                    onclick="event.stopPropagation(); toggleSave(<?php echo $tournament['tournament_id']; ?>, this)">
                                <i class="<?php echo $tournament['is_saved'] > 0 ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="tournament-list-content">
                        <div class="tournament-location-label">
                            <?php 
                            $location_parts = explode('-', $tournament['location']);
                            echo htmlspecialchars(trim($location_parts[0]));
                            ?>
                        </div>

                        <h3 class="tournament-list-title">
                            <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                        </h3>

                        <p class="tournament-description-short">
                            <?php echo htmlspecialchars(substr($tournament['description'], 0, 120)); ?><?php echo strlen($tournament['description']) > 120 ? '...' : ''; ?>
                        </p>

                        <!-- Meta -->
                        <div class="tournament-meta-row">
                            <div class="meta-detail">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j, Y', strtotime($tournament['tournament_date'])); ?>
                            </div>
                            <div class="meta-detail">
                                <i class="fas fa-clock"></i>
                                <?php echo formatTime($tournament['start_time']); ?>
                            </div>
                            <div class="meta-detail">
                                <i class="fas fa-users"></i>
                                <?php echo $tournament['registered_count']; ?> registered
                            </div>
                        </div>

                        <?php if ($tournament['user_registered'] > 0): ?>
                            <div class="registered-badge">
                                <i class="fas fa-check-circle"></i> You're registered
                            </div>
                        <?php endif; ?>

                        <!-- Footer -->
                        <div class="tournament-footer-row">
                            <div class="price-section">
                                <span class="price-amount">RM<?php echo number_format($tournament['tournament_fee'], 0); ?></span>
                                <span class="price-text">entry fee</span>
                            </div>
                            <a href="tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
                               class="btn btn-primary"
                               onclick="event.stopPropagation()">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 80px 20px;">
                <i class="fas fa-fish" style="font-size: 64px; color: #DDDDDD; margin-bottom: 20px;"></i>
                <h2 style="font-size: 24px; color: #222222; margin-bottom: 12px;">No tournaments found</h2>
                <p style="color: #717171; margin-bottom: 24px;">
                    <?php if (!empty($search_query)): ?>
                        No results for "<?php echo htmlspecialchars($search_query); ?>"
                    <?php else: ?>
                        No <?php echo $status_filter == 'all' ? '' : $status_filter; ?> tournaments available
                    <?php endif; ?>
                </p>
                <a href="?status=all" class="btn btn-primary">View All Tournaments</a>
            </div>
        <?php endif; ?>
    </div>
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

function handleSortChange(sortValue) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', sortValue);
    window.location.search = urlParams.toString();
}
</script>

<?php include '../../includes/footer.php'; ?>