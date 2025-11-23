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

// Add user_id to params for subqueries
array_unshift($params, $_SESSION['user_id'], $_SESSION['user_id']);

$tournaments = $db->fetchAll($sql, $params);
?>

<div class="tournament-page">
    <div class="container">
        <!-- Modern Page Header -->
        <div class="tournament-page-header">
            <h1 style="font-size: 30px;"><i class="fas fa-trophy"></i> Fishing Tournaments</h1>
            <p>Discover and join exciting fishing competitions happening near you</p>
        </div>
        
        <!-- Modern Filter Tabs -->
        <div class="filter-tabs-container">
            <div class="filter-tabs-wrapper">
                <a href="?status=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>" 
                   class="filter-tab-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-th"></i> All
                </a>
                <a href="?status=upcoming<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>" 
                   class="filter-tab-btn <?php echo $status_filter == 'upcoming' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Upcoming
                </a>
                <a href="?status=ongoing<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>" 
                   class="filter-tab-btn <?php echo $status_filter == 'ongoing' ? 'active' : ''; ?>">
                    <i class="fas fa-play-circle"></i> Ongoing
                </a>
                <a href="?status=completed<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>" 
                   class="filter-tab-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Completed
                </a>
            </div>
        </div>

        <!-- Search and Sort Bar -->
        <div class="search-sort-container">
            <div class="search-bar">
                <form action="" method="GET" class="search-form">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               name="search" 
                               placeholder="Search by tournament name, location, or organizer..." 
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               class="search-input">
                        <?php if (!empty($search_query)): ?>
                            <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>" class="clear-search">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="sort-dropdown">
                <select name="sort" id="sortSelect" onchange="handleSortChange(this.value)" class="sort-select">
                    <option value="date_asc" <?php echo $sort_by == 'date_asc' ? 'selected' : ''; ?>>Date (Earliest First)</option>
                    <option value="date_desc" <?php echo $sort_by == 'date_desc' ? 'selected' : ''; ?>>Date (Latest First)</option>
                    <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price (Low to High)</option>
                    <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price (High to Low)</option>
                    <option value="participants" <?php echo $sort_by == 'participants' ? 'selected' : ''; ?>>Most Participants</option>
                </select>
            </div>
        </div>

        <!-- Results Count -->
        <?php if (!empty($search_query)): ?>
        <div class="results-info">
            <p>Found <strong><?php echo count($tournaments); ?></strong> tournament(s) matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
        </div>
        <?php endif; ?>
        
        <!-- Tournaments List -->
        <?php if ($tournaments && count($tournaments) > 0): ?>
        <div class="tournaments-list-container">
            <?php foreach ($tournaments as $tournament): ?>
                <div class="tournament-list-item">
                    <!-- Tournament Image/Logo - Left Side -->
                    <div class="tournament-list-image">
                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                    </div>

                    <!-- Tournament Content - Right Side -->
                    <div class="tournament-list-content">
                        <!-- Date/Time Header -->
                        <div class="tournament-datetime">
                            <?php echo date('D, F j, Y', strtotime($tournament['tournament_date'])); ?> at <?php echo formatTime($tournament['start_time']); ?>
                        </div>

                        <!-- Save Icon (Top Right of Image) - Only show if NOT registered -->
                        <?php if ($tournament['user_registered'] == 0): ?>
                            <button class="save-icon-overlay <?php echo $tournament['is_saved'] > 0 ? 'saved' : ''; ?>" 
                                    onclick="toggleSave(<?php echo $tournament['tournament_id']; ?>, this)"
                                    title="<?php echo $tournament['is_saved'] > 0 ? 'Remove from saved' : 'Save tournament'; ?>">
                                <i class="<?php echo $tournament['is_saved'] > 0 ? 'fas' : 'far'; ?> fa-bookmark"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Tournament Title -->
                        <h3 class="tournament-list-title">
                            <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                        </h3>

                        <!-- Tournament Details - Horizontal Icons -->
                        <div class="tournament-details-horizontal">
                            <div class="detail-item-horizontal">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($tournament['organizer_name']); ?></span>
                            </div>
                            
                            <div class="detail-item-horizontal">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?php echo date('M d', strtotime($tournament['tournament_date'])); ?></span>
                            </div>
                            
                            <div class="detail-item-horizontal">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars(substr($tournament['location'], 0, 35)); ?><?php echo strlen($tournament['location']) > 35 ? '...' : ''; ?></span>
                            </div>
                            
                            <div class="detail-item-horizontal">
                                <i class="fas fa-dollar-sign"></i>
                                <span>RM <?php echo number_format($tournament['tournament_fee'], 2); ?> Entry Fee</span>
                            </div>
                        </div>

                        <!-- Registration Info & Actions -->
                        <div class="tournament-footer-horizontal">
                            <div class="registration-stats">
                                <span class="registered-count"><?php echo $tournament['registered_count']; ?> Registered</span>
                                <span class="prereg-count"><?php echo $tournament['max_participants'] - $tournament['registered_count']; ?> Spots Left</span>
                                <?php if ($tournament['user_registered'] > 0): ?>
                                    <span class="user-registered-badge">
                                        <i class="fas fa-check-circle"></i> You're Registered
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="tournament-actions">
                                <!-- View Details Button -->
                                <a href="tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
                                   class="view-details-link-btn">
                                    View Details
                                </a>

                                <!-- Status Badge -->
                                <span class="status-badge-horizontal <?php echo $tournament['status']; ?>">
                                    <?php echo strtoupper($tournament['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <!-- Modern Empty State -->
            <div class="empty-state-modern">
                <i class="fas fa-search"></i>
                <h2>No Tournaments Found</h2>
                <p>
                    <?php if (!empty($search_query)): ?>
                        No tournaments match your search "<strong><?php echo htmlspecialchars($search_query); ?></strong>".
                    <?php else: ?>
                        There are no <?php echo $status_filter == 'all' ? '' : strtolower($status_filter); ?> tournaments at the moment.
                    <?php endif; ?>
                </p>
                <a href="?status=all" class="btn btn-primary">
                    <i class="fas fa-th"></i> View All Tournaments
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSave(tournamentId, button) {
    const isSaved = button.classList.contains('saved');
    const icon = button.querySelector('i');
    
    // Optimistic UI update
    button.disabled = true;
    
    fetch('<?php echo SITE_URL; ?>/pages/tournament/toggle-save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `tournament_id=${tournamentId}&action=${isSaved ? 'unsave' : 'save'}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isSaved) {
                button.classList.remove('saved');
                icon.classList.remove('fas');
                icon.classList.add('far');
                button.title = 'Save tournament';
            } else {
                button.classList.add('saved');
                icon.classList.remove('far');
                icon.classList.add('fas');
                button.title = 'Remove from saved';
            }
        } else {
            alert(data.message || 'Failed to update saved status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        button.disabled = false;
    });
}

function handleSortChange(sortValue) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', sortValue);
    window.location.search = urlParams.toString();
}

// Auto-submit search after user stops typing
let searchTimeout;
document.querySelector('.search-input')?.addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 800);
});
</script>

<?php include '../../includes/footer.php'; ?>