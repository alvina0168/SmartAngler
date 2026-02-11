<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Tournament Management';
$page_description = 'Manage all your fishing tournaments';

if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $conn;
        return mysqli_real_escape_string($conn, trim($data));
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

if ($logged_in_role === 'organizer') {
    $status_update_where = "
        (created_by = '$logged_in_user_id' 
        OR created_by IN (
            SELECT user_id FROM USER WHERE created_by = '$logged_in_user_id' AND role = 'admin'
        ))
    ";
} elseif ($logged_in_role === 'admin') {
    $get_creator_query = "SELECT created_by FROM USER WHERE user_id = '$logged_in_user_id'";
    $creator_result = mysqli_query($conn, $get_creator_query);
    $creator_row = mysqli_fetch_assoc($creator_result);
    $organizer_id = $creator_row['created_by'] ?? null;
    
    if ($organizer_id) {
        $status_update_where = "(created_by = '$logged_in_user_id' OR created_by = '$organizer_id')";
    } else {
        $status_update_where = "created_by = '$logged_in_user_id'";
    }
} else {
    $status_update_where = "created_by = '$logged_in_user_id'";
}

$update_query = "
    UPDATE TOURNAMENT
    SET status = CASE
        WHEN NOW() < CONCAT(tournament_date, ' ', start_time) THEN 'upcoming'
        WHEN NOW() BETWEEN CONCAT(tournament_date, ' ', start_time) AND CONCAT(tournament_date, ' ', end_time) THEN 'ongoing'
        WHEN NOW() > CONCAT(tournament_date, ' ', end_time) THEN 'completed'
        ELSE status
    END
    WHERE status != 'cancelled' AND $status_update_where
";
mysqli_query($conn, $update_query);

$limit = 10; 
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search_query = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'latest';

if ($logged_in_role === 'organizer') {
    $where_conditions = [
        "(t.created_by = '$logged_in_user_id' 
        OR t.created_by IN (
            SELECT user_id FROM USER WHERE created_by = '$logged_in_user_id' AND role = 'admin'
        ))"
    ];
} elseif ($logged_in_role === 'admin') {
    if (isset($organizer_id) && $organizer_id) {
        $where_conditions = ["(t.created_by = '$logged_in_user_id' OR t.created_by = '$organizer_id')"];
    } else {
        $where_conditions = ["t.created_by = '$logged_in_user_id'"];
    }
} else {
    $where_conditions = ["t.created_by = '$logged_in_user_id'"];
}

if ($status_filter != 'all') {
    $where_conditions[] = "t.status = '$status_filter'";
}

if (!empty($search_query)) {
    $where_conditions[] = "(t.tournament_title LIKE '%$search_query%' 
                            OR t.location LIKE '%$search_query%' 
                            OR t.description LIKE '%$search_query%'
                            OR u.full_name LIKE '%$search_query%')";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
$order_by = "ORDER BY ";
switch($sort_by) {
    case 'oldest':
        $order_by .= "t.created_at ASC";
        break;
    case 'title_asc':
        $order_by .= "t.tournament_title ASC";
        break;
    case 'title_desc':
        $order_by .= "t.tournament_title DESC";
        break;
    case 'date_asc':
        $order_by .= "t.tournament_date ASC, t.start_time ASC";
        break;
    case 'date_desc':
        $order_by .= "t.tournament_date DESC, t.start_time DESC";
        break;
    case 'fee_asc':
        $order_by .= "t.tournament_fee ASC";
        break;
    case 'fee_desc':
        $order_by .= "t.tournament_fee DESC";
        break;
    case 'participants':
        $order_by .= "t.max_participants DESC";
        break;
    case 'latest':
    default:
        $order_by .= "t.created_at DESC";
        break;
}

$total_query = "SELECT COUNT(*) as total FROM TOURNAMENT t LEFT JOIN USER u ON t.created_by = u.user_id $where_clause";
$total_result = mysqli_query($conn, $total_query);
$total_tournaments = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_tournaments / $limit);

$tournaments_query = "
    SELECT t.*, u.full_name as creator_name, u.user_id as creator_id
    FROM TOURNAMENT t
    LEFT JOIN USER u ON t.created_by = u.user_id
    $where_clause 
    $order_by
    LIMIT $limit OFFSET $offset
";
$tournaments_result = mysqli_query($conn, $tournaments_query);

include '../includes/header.php';
?>

<style>
.table tbody tr { cursor: pointer; transition: background-color 0.2s ease; }
.table tbody tr:hover { background-color: #e3f2fd; }
.action-btns { display: flex; gap: 0.5rem; }
.action-btns button { pointer-events: all; }
.pagination { display: flex; justify-content: center; margin-top: 1.5rem; gap: 0.5rem; flex-wrap: wrap; }
.pagination a { padding: 0.5rem 1rem; border-radius: 5px; border: 1px solid #ccc; text-decoration: none; color: #333; }
.pagination a.active { background-color: #007bff; color: #fff; border-color: #007bff; }
.pagination a:hover { background-color: #0056b3; color: #fff; border-color: #0056b3; }
.creator-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.search-sort-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    border: 2px solid #e0e0e0;
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.search-box input:focus {
    border-color: var(--color-blue-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.search-box .search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    pointer-events: none;
}

.search-box .clear-search {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem;
    display: none;
}

.search-box input:not(:placeholder-shown) ~ .clear-search {
    display: block;
}

.search-box .clear-search:hover {
    color: #dc3545;
}

.sort-dropdown {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sort-dropdown select {
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
}

.sort-dropdown select:focus {
    border-color: var(--color-blue-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.sort-dropdown label {
    font-weight: 600;
    color: var(--color-gray-700);
    white-space: nowrap;
}

.filter-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: #e3f2fd;
    color: #1976d2;
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    font-weight: 500;
}

.filter-tag button {
    background: none;
    border: none;
    color: #1976d2;
    cursor: pointer;
    padding: 0;
    font-size: 1rem;
    line-height: 1;
}

.filter-tag button:hover {
    color: #dc3545;
}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
    <div class="filter-tabs" style="margin: 0;">
        <?php 
        $statuses = ['all'=>'All Tournaments', 'upcoming'=>'Upcoming', 'ongoing'=>'Ongoing', 'completed'=>'Completed'];
        $icons = ['all'=>'list','upcoming'=>'calendar-plus','ongoing'=>'hourglass-half','completed'=>'check-circle'];
        foreach($statuses as $key => $label): ?>
            <a href="?status=<?php echo $key; ?>&search=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort_by; ?>" 
               class="filter-btn <?php echo $status_filter == $key ? 'active' : ''; ?>">
                <i class="fas fa-<?php echo $icons[$key]; ?>"></i> <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div>
        <a href="createTournament.php" class="create-btn">
            <i class="fas fa-plus-circle"></i> Create Tournament
        </a>
    </div>
</div>

<div class="search-sort-bar">
    <div class="search-box">
        <i class="fas fa-search search-icon"></i>
        <form method="GET" style="margin: 0;">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="hidden" name="sort" value="<?php echo $sort_by; ?>">
            <input type="text" 
                   name="search" 
                   placeholder="Search by tournament name, location, creator..."
                   value="<?php echo htmlspecialchars($search_query); ?>"
                   id="searchInput">
            <button type="button" class="clear-search" onclick="clearSearch()">
                <i class="fas fa-times-circle"></i>
            </button>
        </form>
    </div>
    
    <div class="sort-dropdown">
        <label><i class="fas fa-sort"></i> Sort by:</label>
        <select onchange="window.location.href='?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&sort=' + this.value">
            <option value="latest" <?php echo $sort_by == 'latest' ? 'selected' : ''; ?>>Latest First</option>
            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
            <option value="fee_asc" <?php echo $sort_by == 'fee_asc' ? 'selected' : ''; ?>>Fee (Low to High)</option>
            <option value="fee_desc" <?php echo $sort_by == 'fee_desc' ? 'selected' : ''; ?>>Fee (High to Low)</option>
            <option value="participants" <?php echo $sort_by == 'participants' ? 'selected' : ''; ?>>Max Participants</option>
        </select>
    </div>
</div>

<?php if (!empty($search_query) || $status_filter != 'all' || $sort_by != 'latest'): ?>
<div class="filter-tags">
    <span style="font-weight: 600; color: var(--color-gray-700);">
        <i class="fas fa-filter"></i> Active Filters:
    </span>
    
    <?php if (!empty($search_query)): ?>
    <span class="filter-tag">
        <i class="fas fa-search"></i> Search: "<?php echo htmlspecialchars($search_query); ?>"
        <button onclick="window.location.href='?status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>'" title="Remove search">
            <i class="fas fa-times"></i>
        </button>
    </span>
    <?php endif; ?>
    
    <?php if ($status_filter != 'all'): ?>
    <span class="filter-tag">
        <i class="fas fa-tag"></i> Status: <?php echo ucfirst($status_filter); ?>
        <button onclick="window.location.href='?search=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort_by; ?>'" title="Remove status filter">
            <i class="fas fa-times"></i>
        </button>
    </span>
    <?php endif; ?>
    
    <?php if ($sort_by != 'latest'): ?>
    <span class="filter-tag">
        <i class="fas fa-sort"></i> Sort: <?php 
            $sort_labels = [
                'oldest' => 'Oldest First',
                'title_asc' => 'Title (A-Z)',
                'title_desc' => 'Title (Z-A)',
                'date_asc' => 'Date (Earliest)',
                'date_desc' => 'Date (Latest)',
                'fee_asc' => 'Fee (Low to High)',
                'fee_desc' => 'Fee (High to Low)',
                'participants' => 'Max Participants'
            ];
            echo $sort_labels[$sort_by];
        ?>
        <button onclick="window.location.href='?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>'" title="Remove sort">
            <i class="fas fa-times"></i>
        </button>
    </span>
    <?php endif; ?>
    
    <a href="tournamentList.php" style="color: #dc3545; text-decoration: none; font-weight: 600; font-size: 0.875rem; margin-left: 0.5rem;">
        <i class="fas fa-times-circle"></i> Clear All
    </a>
</div>
<?php endif; ?>

<?php if (mysqli_num_rows($tournaments_result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <?php 
                if ($logged_in_role === 'organizer') {
                    echo 'My Tournaments';
                } else {
                    echo 'Accessible Tournaments';
                }
                ?> (<?php echo $total_tournaments; ?>)
            </h2>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Tournament Details</th>
                    <th>Date & Time</th>
                    <th>Participants</th>
                    <th>Fee</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($tournament = mysqli_fetch_assoc($tournaments_result)):
                    $reg_query = "SELECT COUNT(*) as count FROM TOURNAMENT_REGISTRATION 
                                  WHERE tournament_id = '{$tournament['tournament_id']}' 
                                  AND approval_status IN ('pending','approved')";
                    $reg_result = mysqli_query($conn, $reg_query);
                    $reg_count = mysqli_fetch_assoc($reg_result)['count'];
                    
                    $is_own = ($tournament['creator_id'] == $logged_in_user_id);
                ?>
                <tr onclick="window.location.href='viewTournament.php?id=<?php echo $tournament['tournament_id']; ?>'">
                    <td>
                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($tournament['image']) ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="Tournament" class="tournament-thumb"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                    </td>
                    <td>
                        <div class="tournament-title"><?php echo htmlspecialchars($tournament['tournament_title']); ?></div>
                        <div class="tournament-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars(substr($tournament['location'],0,40)) . (strlen($tournament['location']) > 40 ? '...' : ''); ?>
                        </div>
                        <?php if (!$is_own): ?>
                            <div class="creator-badge">
                                <i class="fas fa-user"></i> Created by: <?php echo htmlspecialchars($tournament['creator_name']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="tournament-title"><?php echo date('d M Y', strtotime($tournament['tournament_date'])); ?></div>
                        <div class="tournament-location">
                            <?php echo date('h:i A', strtotime($tournament['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($tournament['end_time'])); ?>
                        </div>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo $reg_count; ?></span> / <?php echo $tournament['max_participants']; ?>
                    </td>
                    <td class="tournament-title">RM <?php echo number_format($tournament['tournament_fee'],2); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $tournament['status']; ?>"><?php echo ucfirst($tournament['status']); ?></span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button type="button"
                                    onclick="event.stopPropagation(); if(confirm('Are you sure you want to delete this tournament? This action cannot be undone.')) { window.location.href='deleteTournament.php?id=<?php echo $tournament['tournament_id']; ?>'; }" 
                                    class="btn btn-danger btn-sm" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($p=1; $p <= $total_pages; $p++): ?>
                <a href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $p; ?>" 
                   class="<?php echo $p == $page ? 'active' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-trophy"></i>
        <h3>No Tournaments Found</h3>
        <p>
            <?php if (!empty($search_query)): ?>
                No tournaments match your search for "<?php echo htmlspecialchars($search_query); ?>".
            <?php elseif ($status_filter != 'all'): ?>
                No <?php echo $status_filter; ?> tournaments found.
            <?php else: ?>
                You haven't created any tournaments yet.
            <?php endif; ?>
        </p>
        <?php if (!empty($search_query) || $status_filter != 'all'): ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });

    function clearSearch() {
        window.location.href = '?status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>';
    }
</script>

<?php include '../includes/footer.php'; ?>