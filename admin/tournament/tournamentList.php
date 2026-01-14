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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Get logged-in admin user ID
$logged_in_user_id = intval($_SESSION['user_id']);

/* -----------------------------------------------------------
   Auto-update tournament status based on date/time
----------------------------------------------------------- */
$update_query = "
    UPDATE TOURNAMENT
    SET status = CASE
        WHEN NOW() < CONCAT(tournament_date, ' ', start_time) THEN 'upcoming'
        WHEN NOW() BETWEEN CONCAT(tournament_date, ' ', start_time) AND CONCAT(tournament_date, ' ', end_time) THEN 'ongoing'
        WHEN NOW() > CONCAT(tournament_date, ' ', end_time) THEN 'completed'
        ELSE status
    END
    WHERE status != 'cancelled' AND created_by = '$logged_in_user_id';
";
mysqli_query($conn, $update_query);

// --- Pagination ---
$limit = 10; // tournaments per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// --- Status filter ---
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';

// Build WHERE clause - ALWAYS filter by created_by
$where_conditions = ["created_by = '$logged_in_user_id'"];

if ($status_filter != 'all') {
    $where_conditions[] = "status = '$status_filter'";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// --- Get total tournaments for pagination ---
$total_query = "SELECT COUNT(*) as total FROM TOURNAMENT $where_clause";
$total_result = mysqli_query($conn, $total_query);
$total_tournaments = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_tournaments / $limit);

// --- Get tournaments for current page ---
$tournaments_query = "SELECT * FROM TOURNAMENT $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
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
</style>

<!-- Create Tournament Button & Filter Tabs -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
    <!-- Filter Tabs -->
    <div class="filter-tabs" style="margin: 0;">
        <?php 
        $statuses = ['all'=>'All Tournaments', 'upcoming'=>'Upcoming', 'ongoing'=>'Ongoing', 'completed'=>'Completed', 'cancelled'=>'Cancelled'];
        $icons = ['all'=>'list','upcoming'=>'calendar-plus','ongoing'=>'hourglass-half','completed'=>'check-circle','cancelled'=>'times-circle'];
        foreach($statuses as $key => $label): ?>
            <a href="?status=<?php echo $key; ?>" class="filter-btn <?php echo $status_filter == $key ? 'active' : ''; ?>">
                <i class="fas fa-<?php echo $icons[$key]; ?>"></i> <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Create Button -->
    <div>
        <a href="createTournament.php" class="create-btn">
            <i class="fas fa-plus-circle"></i> Create Tournament
        </a>
    </div>
</div>

<!-- Tournaments Table -->
<?php if (mysqli_num_rows($tournaments_result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-trophy"></i>
                My Tournaments (<?php echo $total_tournaments; ?>)
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

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($p=1; $p <= $total_pages; $p++): ?>
                <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $p; ?>" class="<?php echo $p == $page ? 'active' : ''; ?>">
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
        <p>You haven't created any tournaments yet<?php echo $status_filter != 'all' ? ' or no tournaments match your filter' : ''; ?>.</p>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>