<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Fishing Zones';
$page_description = 'Manage fishing zones and spots';

// Get all zones with spot count
$zones_query = "
    SELECT z.*, 
           COUNT(DISTINCT fs.spot_id) as total_spots,
           SUM(CASE WHEN fs.spot_status = 'available' THEN 1 ELSE 0 END) as available_spots,
           SUM(CASE WHEN fs.spot_status = 'booked' THEN 1 ELSE 0 END) as booked_spots,
           SUM(CASE WHEN fs.spot_status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_spots,
           t.tournament_title
    FROM ZONE z
    LEFT JOIN FISHING_SPOT fs ON z.zone_id = fs.zone_id
    LEFT JOIN TOURNAMENT t ON z.tournament_id = t.tournament_id
    GROUP BY z.zone_id
    ORDER BY z.created_at DESC
";
$zones_result = mysqli_query($conn, $zones_query);

include '../includes/header.php';
?>

<!-- Create Button -->
<div class="text-right mb-3">
    <a href="createFishingSpot.php" class="create-btn">
        <i class="fas fa-plus-circle"></i>
        Create Fishing Zone
    </a>
</div>

<!-- Zones Table -->
<?php if (mysqli_num_rows($zones_result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-map-marked-alt"></i>
                All Fishing Zones (<?php echo mysqli_num_rows($zones_result); ?>)
            </h2>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Zone Name</th>
                    <th>Description</th>
                    <th>Tournament</th>
                    <th>Total Spots</th>
                    <th>Available</th>
                    <th>Booked</th>
                    <th>Maintenance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($zone = mysqli_fetch_assoc($zones_result)): ?>
                    <tr>
                        <!-- Zone Name -->
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--color-blue-primary), var(--color-blue-light)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.125rem; flex-shrink: 0;">
                                    <i class="fas fa-map-marked-alt"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--color-gray-800); margin-bottom: 0.25rem;">
                                        <?php echo htmlspecialchars($zone['zone_name']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--color-gray-500);">
                                        <i class="fas fa-calendar"></i>
                                        Created: <?php echo date('M d, Y', strtotime($zone['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Description -->
                        <td>
                            <?php if (!empty($zone['zone_description'])): ?>
                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--color-gray-600); font-size: 0.875rem;" title="<?php echo htmlspecialchars($zone['zone_description']); ?>">
                                    <?php echo htmlspecialchars($zone['zone_description']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--color-gray-400); font-style: italic; font-size: 0.875rem;">No description</span>
                            <?php endif; ?>
                        </td>

                        <!-- Tournament -->
                        <td>
                            <?php if (!empty($zone['tournament_title'])): ?>
                                <span class="badge badge-info">
                                    <i class="fas fa-trophy"></i>
                                    <?php echo htmlspecialchars($zone['tournament_title']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--color-gray-400); font-size: 0.875rem;">-</span>
                            <?php endif; ?>
                        </td>

                        <!-- Total Spots -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: var(--color-blue-light); color: var(--color-blue-primary); border-radius: var(--radius-sm); font-weight: 600; font-size: 0.875rem;">
                                <i class="fas fa-map-pin"></i>
                                <strong><?php echo $zone['total_spots']; ?></strong>
                            </div>
                        </td>

                        <!-- Available -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: #e8f5e9; color: #2e7d32; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.875rem;">
                                <i class="fas fa-check-circle"></i>
                                <strong><?php echo $zone['available_spots']; ?></strong>
                            </div>
                        </td>

                        <!-- Booked -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: #fff3e0; color: #f57c00; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.875rem;">
                                <i class="fas fa-bookmark"></i>
                                <strong><?php echo $zone['booked_spots']; ?></strong>
                            </div>
                        </td>

                        <!-- Maintenance -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: #ffebee; color: #c62828; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.875rem;">
                                <i class="fas fa-tools"></i>
                                <strong><?php echo $zone['maintenance_spots']; ?></strong>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td>
                            <div class="action-btns">
                                <a href="viewZone.php?id=<?php echo $zone['zone_id']; ?>" 
                                   class="btn btn-primary btn-sm" title="View Spots">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="editZone.php?id=<?php echo $zone['zone_id']; ?>" 
                                   class="btn btn-success btn-sm" title="Edit Zone">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteZone(<?php echo $zone['zone_id']; ?>)" 
                                        class="btn btn-danger btn-sm" title="Delete Zone">
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
        <i class="fas fa-map-marked-alt"></i>
        <h3>No Fishing Zones Created</h3>
        <p>Start by creating your first fishing zone with spots</p>
        <a href="createFishingSpot.php" class="create-btn">
            <i class="fas fa-plus"></i> Create First Fishing Zone
        </a>
    </div>
<?php endif; ?>

<script>
function deleteZone(id) {
    if (confirm('Are you sure you want to delete this zone? All spots in this zone will also be deleted.')) {
        window.location.href = 'deleteZone.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>