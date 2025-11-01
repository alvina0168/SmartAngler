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
        Create Fishing Spot
    </a>
</div>

<!-- Zones Grid -->
<div class="zones-grid">
    <?php if (mysqli_num_rows($zones_result) > 0): ?>
        <?php while ($zone = mysqli_fetch_assoc($zones_result)): ?>
            <div class="zone-card">
                <div class="zone-header">
                    <div class="zone-title-section">
                        <h3 class="zone-title">
                            <i class="fas fa-map-marked-alt"></i>
                            <?php echo htmlspecialchars($zone['zone_name']); ?>
                        </h3>
                        <?php if (!empty($zone['tournament_title'])): ?>
                            <span class="zone-tournament">
                                <i class="fas fa-trophy"></i>
                                <?php echo htmlspecialchars($zone['tournament_title']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="zone-body">
                    <?php if (!empty($zone['zone_description'])): ?>
                        <p class="zone-description">
                            <?php echo htmlspecialchars($zone['zone_description']); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Spot Statistics -->
                    <div class="zone-stats">
                        <div class="zone-stat">
                            <div class="stat-icon-small total">
                                <i class="fas fa-map-pin"></i>
                            </div>
                            <div>
                                <div class="stat-number-small"><?php echo $zone['total_spots']; ?></div>
                                <div class="stat-label-small">Total Spots</div>
                            </div>
                        </div>

                        <div class="zone-stat">
                            <div class="stat-icon-small available">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div class="stat-number-small"><?php echo $zone['available_spots']; ?></div>
                                <div class="stat-label-small">Available</div>
                            </div>
                        </div>

                        <div class="zone-stat">
                            <div class="stat-icon-small booked">
                                <i class="fas fa-bookmark"></i>
                            </div>
                            <div>
                                <div class="stat-number-small"><?php echo $zone['booked_spots']; ?></div>
                                <div class="stat-label-small">Booked</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="zone-footer">
                    <a href="viewZone.php?id=<?php echo $zone['zone_id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> View Spots
                    </a>
                    <button onclick="deleteZone(<?php echo $zone['zone_id']; ?>)" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-map-marked-alt"></i>
            <h3>No Fishing Zones Created</h3>
            <p>Start by creating your first fishing zone with spots</p>
            <a href="createFishingSpot.php" class="create-btn">
                <i class="fas fa-plus"></i> Create First Fishing Spot
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteZone(id) {
    if (confirm('Are you sure you want to delete this zone? All spots in this zone will also be deleted.')) {
        window.location.href = 'deleteZone.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>