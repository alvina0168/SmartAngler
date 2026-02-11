<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$isLoggedIn = isLoggedIn();
$currentUserId = $isLoggedIn ? $_SESSION['user_id'] : null;

if ($isLoggedIn && isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

$page_title = 'Tournaments';
include '../../includes/header.php';

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

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

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

if ($isLoggedIn) {
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
    
    array_unshift($params, $currentUserId, $currentUserId);
} else {
    $sql = "SELECT t.*, u.full_name as organizer_name,
            (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
             WHERE tournament_id = t.tournament_id 
             AND approval_status IN ('pending', 'approved')) as registered_count,
            0 as is_saved,
            0 as user_registered
            FROM TOURNAMENT t
            LEFT JOIN USER u ON t.user_id = u.user_id
            $where_clause
            $order_by, t.created_at DESC";
}

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

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #FAFAFA;
    min-height: 100vh;
}

/* Hero Section - Match Calendar Style */
.tournaments-hero {
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    padding: 50px 60px;
}

.hero-title {
    font-size: 42px;
    font-weight: 800;
    color: white;
    margin: 0 0 8px;
    letter-spacing: -0.5px;
}

.hero-subtitle {
    font-size: 16px;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
    font-weight: 400;
}

.filter-section {
    background: #FAFAFA;
    padding: 30px 60px 0;
}

.filter-card {
    background: var(--white);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #E5E7EB;
    margin-bottom: 20px;
}

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 6px;
    background: #F9FAFB;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid #E5E7EB;
}

.filter-tab:hover {
    background: #F3F4F6;
    border-color: var(--ocean-light);
}

.filter-tab.active {
    background: var(--ocean-light);
    color: var(--white);
    border-color: var(--ocean-light);
}

.search-controls {
    display: grid;
    grid-template-columns: 1fr 200px;
    gap: 12px;
}

.search-wrapper {
    position: relative;
}

.search-input {
    width: 100%;
    padding: 10px 16px 10px 40px;
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--ocean-light);
    box-shadow: 0 0 0 3px rgba(8, 131, 149, 0.1);
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 14px;
}

.sort-select {
    padding: 10px 30px 10px 14px;
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    background: var(--white);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    transition: all 0.2s ease;
}

.sort-select:focus {
    outline: none;
    border-color: var(--ocean-light);
    box-shadow: 0 0 0 3px rgba(8, 131, 149, 0.1);
}

.tournaments-container {
    background: #FAFAFA;
    padding: 0 60px 50px;
}

.tournaments-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.tournament-card {
    background: var(--white);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    display: grid;
    grid-template-columns: 360px 1fr auto;
    align-items: center;
    gap: 24px;
    padding-right: 24px;
    border: 1px solid #E5E7EB;
}

.tournament-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-color: var(--ocean-light);
}

.card-image {
    position: relative;
    width: 360px;
    height: 240px;
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
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    backdrop-filter: blur(10px);
}

.status-badge.upcoming { 
    background: rgba(59, 130, 246, 0.95); 
    color: white; 
}

.status-badge.ongoing { 
    background: rgba(245, 158, 11, 0.95); 
    color: white;
}

.status-badge.completed { 
    background: rgba(16, 185, 129, 0.95); 
    color: white; 
}

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
    z-index: 5;
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
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.card-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
    line-height: 1.3;
    text-transform: uppercase;
}

.organizer-info {
    font-size: 14px;
    color: var(--text-muted);
}

.organizer-info strong {
    color: var(--text-dark);
}

.card-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px 24px;
    font-size: 14px;
    color: var(--text-dark);
}

.detail-item strong {
    color: var(--ocean-blue);
    margin-right: 4px;
}

.card-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    padding-left: 24px;
    border-left: 1px solid #E5E7EB;
}

.price-tag {
    text-align: right;
}

.price-amount {
    font-size: 32px;
    font-weight: 800;
    color: var(--ocean-blue);
    line-height: 1;
    letter-spacing: -0.5px;
    display: block;
}

.price-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-top: 4px;
    display: block;
}

.participants-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #F9FAFB;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
    border: 1px solid #E5E7EB;
}

.participants-badge i {
    color: var(--ocean-light);
}

.btn-view {
    padding: 10px 24px;
    background: var(--ocean-light);
    color: var(--white);
    border: none;
    border-radius: 6px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.btn-view:hover {
    background: var(--ocean-blue);
    transform: translateX(2px);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 8px;
    border: 1px solid #E5E7EB;
}

.empty-state i {
    font-size: 56px;
    color: #D1D5DB;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 22px;
    margin-bottom: 8px;
    color: var(--text-dark);
}

.empty-state p {
    color: var(--text-muted);
    font-size: 15px;
}

@media (max-width: 1200px) {
    .tournament-card {
        grid-template-columns: 280px 1fr auto;
        gap: 20px;
        padding-right: 20px;
    }
    
    .card-image {
        width: 280px;
        height: 200px;
    }
    
    .card-title {
        font-size: 20px;
    }
}

@media (max-width: 992px) {
    .tournaments-hero,
    .filter-section,
    .tournaments-container {
        padding-left: 30px;
        padding-right: 30px;
    }
    
    .tournament-card {
        grid-template-columns: 220px 1fr;
        gap: 16px;
        padding: 0;
    }
    
    .card-image {
        width: 220px;
        height: 160px;
    }
    
    .card-content {
        padding: 16px 16px 16px 0;
    }
    
    .card-actions {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-left: none;
        border-top: 1px solid #E5E7EB;
    }
    
    .price-tag {
        text-align: left;
    }
}

@media (max-width: 768px) {
    .tournaments-hero,
    .filter-section,
    .tournaments-container {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .hero-title {
        font-size: 32px;
    }
    
    .hero-subtitle {
        font-size: 14px;
    }
    
    .search-controls {
        grid-template-columns: 1fr;
    }
    
    .sort-select {
        width: 100%;
    }
    
    .tournament-card {
        grid-template-columns: 1fr;
    }
    
    .card-image {
        width: 100%;
        height: 200px;
    }
    
    .card-content {
        padding: 20px;
    }
    
    .card-title {
        font-size: 18px;
    }
    
    .card-details {
        grid-template-columns: 1fr;
        gap: 6px;
    }
    
    .card-actions {
        padding: 16px 20px;
    }
    
    .btn-view {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .filter-card {
        padding: 16px;
    }
    
    .filter-tabs {
        gap: 8px;
    }
    
    .filter-tab {
        font-size: 12px;
        padding: 8px 14px;
    }
    
    .card-content {
        padding: 16px;
    }
    
    .price-amount {
        font-size: 28px;
    }
}
</style>

<div class="tournaments-hero">
    <h1 class="hero-title">Discover Fishing Tournaments</h1>
    <p class="hero-subtitle">Join exciting competitions and compete with passionate anglers across the region</p>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-card">
        <div class="filter-tabs">
            <a href="?status=all&sort=<?php echo htmlspecialchars($sort_by); ?>" 
               class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                All Tournaments
            </a>
            <a href="?status=upcoming&sort=<?php echo htmlspecialchars($sort_by); ?>" 
               class="filter-tab <?php echo $status_filter == 'upcoming' ? 'active' : ''; ?>">
                Upcoming
            </a>
            <a href="?status=ongoing&sort=<?php echo htmlspecialchars($sort_by); ?>" 
               class="filter-tab <?php echo $status_filter == 'ongoing' ? 'active' : ''; ?>">
                Live Now
            </a>
            <a href="?status=completed&sort=<?php echo htmlspecialchars($sort_by); ?>" 
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
                       placeholder="Search tournaments by title, location, or organizer..." 
                       value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <select class="sort-select" name="sort" onchange="this.form.submit()">
                <option value="date_desc" <?php echo $sort_by == 'date_desc' ? 'selected' : ''; ?>>Latest First</option>
                <option value="date_asc" <?php echo $sort_by == 'date_asc' ? 'selected' : ''; ?>>Soonest First</option>
                <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Lowest Price</option>
                <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Highest Price</option>
            </select>
        </form>
    </div>
</div>

<div class="tournaments-container">
    <?php if ($tournaments && count($tournaments) > 0): ?>
        <div class="tournaments-list">
            <?php foreach ($tournaments as $tournament): ?>
                <div class="tournament-card" onclick="window.location.href='tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>'">

                    <div class="card-image">
                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? htmlspecialchars($tournament['image']) : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                        
                        <span class="status-badge <?php echo htmlspecialchars($tournament['status']); ?>">
                            <?php echo htmlspecialchars(strtoupper($tournament['status'])); ?>
                        </span>
                        
                        <?php if ($tournament['user_registered'] == 0): ?>
                            <?php if ($isLoggedIn): ?>
                                <button class="save-btn <?php echo $tournament['is_saved'] > 0 ? 'saved' : ''; ?>" 
                                        onclick="event.stopPropagation(); toggleSave(<?php echo $tournament['tournament_id']; ?>, this)">
                                    <i class="<?php echo $tournament['is_saved'] > 0 ? 'fas' : 'far'; ?> fa-heart"></i>
                                </button>
                            <?php else: ?>
                                <button class="save-btn" 
                                        onclick="event.stopPropagation(); promptLogin()">
                                    <i class="far fa-heart"></i>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-content">
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <h3 class="card-title" style="margin:0;">
                                <?= htmlspecialchars($tournament['tournament_title']); ?>
                            </h3>

                            <span class="status-badge <?= htmlspecialchars($tournament['status']); ?>" style="position:static;">
                                <?= strtoupper($tournament['status']); ?>
                            </span>
                        </div>

                        <div class="organizer-info">
                            Organizer: <strong><?= htmlspecialchars($tournament['organizer_name']); ?></strong>
                        </div>

                        <div class="card-details">
                            <div class="detail-item">
                                <strong>Date:</strong>
                                <?= date('d M Y', strtotime($tournament['tournament_date'])); ?>
                            </div>

                            <div class="detail-item">
                                <strong>Location:</strong>
                                <?= htmlspecialchars($tournament['location']); ?>
                            </div>

                            <div class="detail-item">
                                <strong>Time:</strong>
                                <?= formatTime($tournament['start_time']); ?>
                            </div>

                            <div class="detail-item">
                                <strong>Anglers Registered:</strong>
                                <?= $tournament['registered_count']; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-actions">
                        <div class="price-tag">
                            <span class="price-amount">RM<?php echo number_format($tournament['tournament_fee'], 0); ?></span>
                            <span class="price-label">Entry Fee</span>
                        </div>
                        <div class="participants-badge">
                            <i class="fas fa-users"></i>
                            <span><?php echo $tournament['registered_count']; ?> Anglers</span>
                        </div>
                        <a href="tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
                           class="btn-view"
                           onclick="event.stopPropagation()">
                            View Details
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-fish"></i>
            <h3>No tournaments found</h3>
            <p>Try adjusting your filters or search criteria</p>
        </div>
    <?php endif; ?>
</div>

<script>
function promptLogin() {
    if (confirm('Please login to save tournaments. Would you like to login now?')) {
        window.location.href = '<?php echo SITE_URL; ?>/pages/authentication/login.php';
    }
}

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
        } else {
            alert(data.message || 'Failed to save tournament');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => button.disabled = false);
}
</script>

<?php include '../../includes/footer.php'; ?>