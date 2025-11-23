<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Require login
requireLogin();

// Admins shouldn't access this page
if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

$user_id = $_SESSION['user_id'];
$page_title = 'My Dashboard';
include '../../includes/header.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$order_by = isset($_GET['order']) ? $_GET['order'] : 'date_desc';

// Build query conditions - SIMPLIFIED VERSION
$where_conditions = ["tr.user_id = ?"];
$params = [$user_id];

// Apply status filter - DIRECT COMPARISON
if ($status_filter != 'all') {
    $where_conditions[] = "t.status = ?";  // Simple direct comparison
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(t.tournament_title LIKE ? OR t.location LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Determine sort order
$order_clause = "ORDER BY ";
switch ($order_by) {
    case 'date_asc':
        $order_clause .= "t.tournament_date ASC";
        break;
    case 'date_desc':
        $order_clause .= "t.tournament_date DESC";
        break;
    case 'title_asc':
        $order_clause .= "t.tournament_title ASC";
        break;
    case 'title_desc':
        $order_clause .= "t.tournament_title DESC";
        break;
    default:
        $order_clause .= "t.tournament_date DESC";
}

// Get user's registered tournaments - SIMPLIFIED QUERY
$sql = "SELECT 
    t.tournament_id,
    t.tournament_title,
    t.tournament_date,
    t.location,
    t.status,
    t.tournament_fee,
    t.image,
    tr.registration_id,
    tr.approval_status,
    tr.registration_date,
    tr.boat_number,
    u.full_name as organizer_name,
    0 as total_catches,
    0 as total_weight,
    0 as valid_catches,
    1 as current_rank,
    (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION WHERE tournament_id = t.tournament_id AND approval_status = 'approved') as total_participants
FROM TOURNAMENT_REGISTRATION tr
INNER JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
LEFT JOIN USER u ON t.user_id = u.user_id
$where_clause
$order_clause";

try {
    $my_tournaments = $db->fetchAll($sql, $params);
    if (!is_array($my_tournaments)) {
        $my_tournaments = [];
    }
    
    // After fetching, get catch counts for each tournament
    if (count($my_tournaments) > 0) {
        foreach ($my_tournaments as &$tournament) {
            // Get catch counts
            $catch_sql = "SELECT 
                COUNT(*) as total_catches,
                SUM(weight) as total_weight,
                COUNT(CASE WHEN is_valid = TRUE THEN 1 END) as valid_catches
            FROM FISH_CATCH 
            WHERE registration_id = ?";
            $catch_result = $db->fetchAll($catch_sql, [$tournament['registration_id']]);
            
            if (isset($catch_result[0])) {
                $tournament['total_catches'] = $catch_result[0]['total_catches'] ?? 0;
                $tournament['total_weight'] = $catch_result[0]['total_weight'] ?? 0;
                $tournament['valid_catches'] = $catch_result[0]['valid_catches'] ?? 0;
            }
        }
    }
} catch (Exception $e) {
    $my_tournaments = [];
    $sql_error = $e->getMessage();
    error_log("Tournament fetch error: " . $sql_error);
}

// Get quick stats
try {
    $stats_sql = "SELECT 
        COUNT(CASE WHEN t.status = 'upcoming' THEN 1 END) as upcoming_count,
        COUNT(CASE WHEN t.status = 'ongoing' THEN 1 END) as ongoing_count,
        COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN tr.approval_status = 'pending' THEN 1 END) as pending_count
    FROM TOURNAMENT_REGISTRATION tr
    INNER JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
    WHERE tr.user_id = ?";
    
    $stats_result = $db->fetchAll($stats_sql, [$user_id]);
    
    if (is_array($stats_result) && isset($stats_result[0])) {
        $stats = $stats_result[0];
    } else {
        $stats = [
            'upcoming_count' => 0,
            'ongoing_count' => 0,
            'completed_count' => 0,
            'pending_count' => 0
        ];
    }
    
    // Get total catches
    $catches_sql = "SELECT COUNT(*) as total_catches_overall 
                    FROM FISH_CATCH fc 
                    JOIN TOURNAMENT_REGISTRATION tr2 ON fc.registration_id = tr2.registration_id 
                    WHERE tr2.user_id = ?";
    $catches_result = $db->fetchAll($catches_sql, [$user_id]);
    $stats['total_catches_overall'] = isset($catches_result[0]['total_catches_overall']) ? $catches_result[0]['total_catches_overall'] : 0;
    
} catch (Exception $e) {
    $stats = [
        'upcoming_count' => 0,
        'ongoing_count' => 0,
        'completed_count' => 0,
        'pending_count' => 0,
        'total_catches_overall' => 0
    ];
}
?>

<div class="dashboard-page">
    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <div class="user-avatar-large">
                    <i class="fas fa-user"></i>
                </div>
                <div class="welcome-text">
                    <p class="welcome-back">Welcome back,</p>
                    <h1><?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                </div>
            </div>
        </div>

        <!-- Quick Stats Cards -->
        <div class="stats-cards-row">
            <div class="stat-card-modern">
                <div class="stat-icon upcoming">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['upcoming_count']; ?></h3>
                    <p>Upcoming Tournaments</p>
                </div>
            </div>

            <div class="stat-card-modern">
                <div class="stat-icon ongoing">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['ongoing_count']; ?></h3>
                    <p>Ongoing Tournaments</p>
                </div>
            </div>

            <div class="stat-card-modern">
                <div class="stat-icon completed">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['completed_count']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>

            <div class="stat-card-modern">
                <div class="stat-icon catches">
                    <i class="fas fa-fish"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['total_catches_overall']; ?></h3>
                    <p>Total Catches</p>
                </div>
            </div>
        </div>

        <!-- Page Title -->
        <div class="page-title-section">
            <h2>My Tournaments</h2>
            <p>Track your tournament registrations, catches, and rankings</p>
        </div>

        <!-- Filters and Search Bar -->
        <div class="filters-search-bar">
            <div class="filter-group">
                <label>Order By</label>
                <select class="filter-select" onchange="handleOrderChange(this.value)">
                    <option value="date_desc" <?php echo $order_by == 'date_desc' ? 'selected' : ''; ?>>Date (Newest First)</option>
                    <option value="date_asc" <?php echo $order_by == 'date_asc' ? 'selected' : ''; ?>>Date (Oldest First)</option>
                    <option value="title_asc" <?php echo $order_by == 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                    <option value="title_desc" <?php echo $order_by == 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Filter By Status</label>
                <select class="filter-select" onchange="handleStatusChange(this.value)">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="ongoing" <?php echo $status_filter == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>

            <div class="search-group">
                <form action="" method="GET" class="search-form-dash">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($order_by); ?>">
                    <input type="text" 
                           name="search" 
                           placeholder="Search a listing" 
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           class="search-input-dash">
                    <button type="submit" class="search-btn-dash">Search</button>
                </form>
            </div>
        </div>

        <!-- Active Filter Notice -->
        <?php if ($status_filter != 'all' || !empty($search_query)): ?>
        <div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>Active Filters:</strong>
                    <?php if ($status_filter != 'all'): ?>
                        <span style="display: inline-block; background: #2196F3; color: white; padding: 4px 12px; border-radius: 12px; margin-left: 10px; font-size: 13px;">
                            Status: <?php echo ucfirst($status_filter); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($search_query)): ?>
                        <span style="display: inline-block; background: #2196F3; color: white; padding: 4px 12px; border-radius: 12px; margin-left: 10px; font-size: 13px;">
                            Search: "<?php echo htmlspecialchars($search_query); ?>"
                        </span>
                    <?php endif; ?>
                </div>
                <a href="?" style="background: #f44336; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600;">
                    <i class="fas fa-times"></i> Clear All Filters
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tournaments Table/List -->
        <?php if (count($my_tournaments) > 0): ?>
        <div class="tournaments-table">
            <table>
                <thead>
                    <tr>
                        <th>Tournament</th>
                        <th>Category</th>
                        <th>Registration Status</th>
                        <th>Rank</th>
                        <th>Catches</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_tournaments as $tournament): ?>
                    <tr>
                        <td>
                            <div class="tournament-cell">
                                <div class="tournament-thumb">
                                    <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>"
                                         onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                                    <span class="featured-badge">
                                        <?php echo strtoupper($tournament['status']); ?>
                                    </span>
                                </div>
                                <div class="tournament-info">
                                    <h4><?php echo htmlspecialchars($tournament['tournament_title']); ?></h4>
                                    <p class="tournament-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars(substr($tournament['location'], 0, 30)); ?><?php echo strlen($tournament['location']) > 30 ? '...' : ''; ?>
                                    </p>
                                    <p class="tournament-date">
                                        Date: <?php echo date('M j, Y', strtotime($tournament['tournament_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="category-cell">
                                <span><?php echo htmlspecialchars($tournament['organizer_name']); ?></span>
                                <span class="tournament-type">Tournament</span>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge-table <?php echo $tournament['approval_status']; ?>">
                                <?php 
                                $status_text = [
                                    'pending' => 'Pending',
                                    'approved' => 'Approved',
                                    'rejected' => 'Rejected'
                                ];
                                echo $status_text[$tournament['approval_status']] ?? 'Unknown';
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="rank-cell">
                                <?php if ($tournament['approval_status'] == 'approved'): ?>
                                    <span class="rank-number">#<?php echo $tournament['current_rank']; ?></span>
                                    <span class="rank-total">of <?php echo $tournament['total_participants']; ?></span>
                                <?php else: ?>
                                    <span class="rank-na">N/A</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="catches-cell">
                                <span class="catches-count">
                                    <i class="fas fa-fish"></i> <?php echo $tournament['valid_catches']; ?> valid
                                </span>
                                <?php if ($tournament['total_weight'] > 0): ?>
                                <span class="catches-weight">
                                    <?php echo number_format($tournament['total_weight'], 2); ?> kg
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="dropdown-actions">
                                <button class="actions-btn" onclick="toggleDropdown(<?php echo $tournament['registration_id']; ?>)">
                                    Actions <i class="fas fa-chevron-down"></i>
                                </button>
                                <div class="dropdown-menu-actions" id="dropdown-<?php echo $tournament['registration_id']; ?>">
                                    <a href="../tournament/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" class="dropdown-item-action">
                                        <i class="fas fa-eye"></i> View Tournament
                                    </a>
                                    <?php if ($tournament['status'] == 'ongoing' && $tournament['approval_status'] == 'approved'): ?>
                                    <a href="../angler/log-catch.php?tournament_id=<?php echo $tournament['tournament_id']; ?>" class="dropdown-item-action">
                                        <i class="fas fa-plus-circle"></i> Log Catch
                                    </a>
                                    <?php endif; ?>
                                    <a href="../angler/view-catches.php?registration_id=<?php echo $tournament['registration_id']; ?>" class="dropdown-item-action">
                                        <i class="fas fa-fish"></i> View My Catches
                                    </a>
                                    <a href="../angler/leaderboard.php?tournament_id=<?php echo $tournament['tournament_id']; ?>" class="dropdown-item-action">
                                        <i class="fas fa-trophy"></i> View Leaderboard
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state-dashboard">
            <i class="fas fa-clipboard-list"></i>
            <h3>No Tournaments Found</h3>
            <p>
                <?php if ($status_filter != 'all'): ?>
                    No <strong><?php echo strtolower($status_filter); ?></strong> tournaments found.
                <?php elseif (!empty($search_query)): ?>
                    No tournaments match your search "<strong><?php echo htmlspecialchars($search_query); ?></strong>".
                <?php else: ?>
                    You haven't registered for any tournaments yet.
                <?php endif; ?>
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
                <?php if ($status_filter != 'all' || !empty($search_query)): ?>
                <a href="?" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Clear Filters
                </a>
                <?php endif; ?>
                <a href="../tournament/tournaments.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Browse Tournaments
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function handleOrderChange(value) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('order', value);
    window.location.search = urlParams.toString();
}

function handleStatusChange(value) {
    const urlParams = new URLSearchParams(window.location.search);
    if (value === 'all') {
        urlParams.delete('status');
    } else {
        urlParams.set('status', value);
    }
    window.location.search = urlParams.toString();
}

function toggleDropdown(id) {
    const dropdown = document.getElementById('dropdown-' + id);
    const allDropdowns = document.querySelectorAll('.dropdown-menu-actions');
    
    allDropdowns.forEach(dd => {
        if (dd.id !== 'dropdown-' + id) {
            dd.classList.remove('show');
        }
    });
    
    dropdown.classList.toggle('show');
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown-actions')) {
        document.querySelectorAll('.dropdown-menu-actions').forEach(dd => {
            dd.classList.remove('show');
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>