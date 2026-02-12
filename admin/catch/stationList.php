<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['tournament_id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$tournament_id = intval($_GET['tournament_id']);

$tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = '$tournament_id'";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found';
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

$page_title = 'Weighing Stations - ' . $tournament['tournament_title'];
$page_description = 'Manage weighing stations and record catches';

$stations_query = "
    SELECT ws.*, 
           COUNT(DISTINCT fc.catch_id) as catch_count,
           COALESCE(SUM(fc.fish_weight), 0) as total_weight
    FROM WEIGHING_STATION ws
    LEFT JOIN FISH_CATCH fc ON ws.station_id = fc.station_id
    WHERE ws.tournament_id = '$tournament_id'
    GROUP BY ws.station_id
    ORDER BY ws.station_name ASC
";
$stations_result = mysqli_query($conn, $stations_query);

include '../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
    <div>
        <a href="<?php echo SITE_URL; ?>/admin/tournament/viewTournament.php?id=<?php echo $tournament_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back 
        </a>
    </div>

    <div>
        <a href="createStation.php?tournament_id=<?php echo $tournament_id; ?>" class="create-btn">
            <i class="fas fa-plus-circle"></i> Add Station
        </a>
    </div>
</div>

<div class="section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
            <h2 style="color: var(--color-blue-primary); font-size: 1.5rem; margin-bottom: 0.5rem;">
                <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($tournament['tournament_title']); ?>
            </h2>
            <p style="color: var(--color-gray-600); font-size: 0.875rem; margin: 0;">
                <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($tournament['tournament_date'])); ?> |
                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($tournament['start_time'])); ?> - <?php echo date('h:i A', strtotime($tournament['end_time'])); ?>
            </p>
        </div>
        <span class="badge badge-<?php echo $tournament['status']; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
            <?php echo ucfirst($tournament['status']); ?>
        </span>
    </div>
</div>

<?php if (mysqli_num_rows($stations_result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-weight"></i>
                Weighing Stations (<?php echo mysqli_num_rows($stations_result); ?>)
            </h2>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Station Name</th>
                    <th>Marshal Name</th>
                    <th>Total Catches</th>
                    <th>Total Weight (KG)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($station = mysqli_fetch_assoc($stations_result)): ?>
                    <tr style="cursor: pointer;" 
                        onclick="window.location.href='catchList.php?station_id=<?php echo $station['station_id']; ?>'">
                        
                        <!-- Station Name -->
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--color-blue-primary), var(--color-blue-light)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.125rem; flex-shrink: 0;">
                                    <i class="fas fa-weight"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--color-gray-800);">
                                        <?php echo htmlspecialchars($station['station_name']); ?>
                                    </div>
                                    <?php if (!empty($station['notes'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--color-gray-500);">
                                            <i class="fas fa-sticky-note"></i>
                                            <?php echo htmlspecialchars(substr($station['notes'], 0, 30)) . (strlen($station['notes']) > 30 ? '...' : ''); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Marshal Name -->
                        <td>
                            <?php if (!empty($station['marshal_name'])): ?>
                                <div style="font-weight: 600; color: var(--color-gray-800);">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($station['marshal_name']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--color-gray-400); font-style: italic;">Not assigned</span>
                            <?php endif; ?>
                        </td>

                        <!-- Total Catches -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: var(--color-blue-light); color: var(--color-blue-primary); border-radius: var(--radius-sm); font-weight: 600;">
                                <i class="fas fa-fish"></i>
                                <strong><?php echo $station['catch_count']; ?></strong>
                            </div>
                        </td>

                        <!-- Total Weight -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: #e8f5e9; color: #2e7d32; border-radius: var(--radius-sm); font-weight: 600;">
                                <i class="fas fa-balance-scale"></i>
                                <strong><?php echo number_format($station['total_weight'], 2); ?></strong>
                            </div>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="badge badge-<?php echo $station['status']; ?>">
                                <?php echo ucfirst($station['status']); ?>
                            </span>
                        </td>

                        <!-- Actions -->
                        <td>
                            <div class="action-btns">
                                <!-- Edit -->
                                <a href="editStation.php?id=<?php echo $station['station_id']; ?>" 
                                   class="btn btn-success btn-sm" title="Edit" 
                                   onclick="event.stopPropagation();">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <!-- Delete -->
                                <button onclick="deleteStation(<?php echo $station['station_id']; ?>)" 
                                        class="btn btn-danger btn-sm" title="Delete" 
                                        type="button"
                                        onclick="event.stopPropagation();">
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
        <i class="fas fa-weight"></i>
        <h3>No Weighing Stations</h3>
        <p>Create weighing stations to start recording fish catches</p>
    </div>
<?php endif; ?>

<script>
function deleteStation(id) {
    if (confirm('Are you sure you want to delete this station? All catch records will also be deleted.')) {
        window.location.href = 'deleteStation.php?id=' + id + '&tournament_id=<?php echo $tournament_id; ?>';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
