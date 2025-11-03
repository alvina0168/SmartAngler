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
    WHERE status != 'cancelled';
";
mysqli_query($conn, $update_query);

$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$where_clause = '';
if ($status_filter != 'all') {
    $where_clause = "WHERE status = '$status_filter'";
}

$tournaments_query = "SELECT * FROM TOURNAMENT $where_clause ORDER BY created_at DESC";
$tournaments_result = mysqli_query($conn, $tournaments_query);

include '../includes/header.php';
?>

<!-- Create Button -->
<div class="text-right mb-3">
    <a href="createTournament.php" class="create-btn">
        <i class="fas fa-plus-circle"></i>
        Create Tournament
    </a>
</div>

<!-- Filter Tabs -->
<div class="filter-tabs">
    <a href="?status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
        <i class="fas fa-list"></i> All Tournaments
    </a>
    <a href="?status=upcoming" class="filter-btn <?php echo $status_filter == 'upcoming' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-plus"></i> Upcoming
    </a>
    <a href="?status=ongoing" class="filter-btn <?php echo $status_filter == 'ongoing' ? 'active' : ''; ?>">
        <i class="fas fa-hourglass-half"></i> Ongoing
    </a>
    <a href="?status=completed" class="filter-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
        <i class="fas fa-check-circle"></i> Completed
    </a>
    <a href="?status=cancelled" class="filter-btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
        <i class="fas fa-times-circle"></i> Cancelled
    </a>
</div>

<!-- Tournaments Table -->
<?php if (mysqli_num_rows($tournaments_result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-trophy"></i>
                All Tournaments (<?php echo mysqli_num_rows($tournaments_result); ?>)
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
                <?php 
                mysqli_data_seek($tournaments_result, 0);
                while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                    // Get registration count
                    $reg_query = "SELECT COUNT(*) as count FROM TOURNAMENT_REGISTRATION 
                                  WHERE tournament_id = '{$tournament['tournament_id']}' 
                                  AND approval_status IN ('pending', 'approved')";
                    $reg_result = mysqli_query($conn, $reg_query);
                    $reg_count = mysqli_fetch_assoc($reg_result)['count'];
                ?>
                <tr>
                    <td>
                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($tournament['image']) ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="Tournament" 
                             class="tournament-thumb"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                    </td>
                    <td>
                        <div class="tournament-title"><?php echo htmlspecialchars($tournament['tournament_title']); ?></div>
                        <div class="tournament-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars(substr($tournament['location'], 0, 40)) . '...'; ?>
                        </div>
                    </td>
                    <td>
                        <div class="tournament-title">
                            <?php echo date('d M Y', strtotime($tournament['tournament_date'])); ?>
                        </div>
                        <div class="tournament-location">
                            <?php echo date('h:i A', strtotime($tournament['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($tournament['end_time'])); ?>
                        </div>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo $reg_count; ?></span> / 
                        <?php echo $tournament['max_participants']; ?>
                    </td>
                    <td class="tournament-title">
                        RM <?php echo number_format($tournament['tournament_fee'], 2); ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $tournament['status']; ?>">
                            <?php echo ucfirst($tournament['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="<?php echo SITE_URL; ?>/pages/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
                            class="btn btn-primary btn-sm" 
                            title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="editTournament.php?id=<?php echo $tournament['tournament_id']; ?>" 
                            class="btn btn-success btn-sm" 
                            title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button"
                                    onclick="deleteTournament(<?php echo $tournament['tournament_id']; ?>)" 
                                    class="btn btn-danger btn-sm" 
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-trophy"></i>
        <h3>No Tournaments Found</h3>
        <p>You haven't created any tournaments yet or no tournaments match your filter.</p>
        <a href="createTournament.php" class="create-btn">
            <i class="fas fa-plus"></i> Create Your First Tournament
        </a>
    </div>
<?php endif; ?>

<script>
function deleteTournament(id) {
    if (confirm('Are you sure you want to delete this tournament? This action cannot be undone.')) {
        window.location.href = 'deleteTournament.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>