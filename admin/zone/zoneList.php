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

<style>
/* Zone List Professional Design */
.zone-list-container {
    background: #f5f5f5;
    min-height: 100vh;
    padding: 2rem;
}

.zone-header-section {
    background: white;
    padding: 1.5rem 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.zone-header-left h1 {
    font-size: 1.75rem;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
    font-weight: 600;
}

.zone-header-left p {
    color: #7f8c8d;
    margin: 0;
    font-size: 0.9375rem;
}

.zone-create-btn {
    background: #4caf50;
    color: white;
    padding: 0.875rem 1.75rem;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.9375rem;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.zone-create-btn:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(76,175,80,0.3);
}

/* Table Container */
.zone-table-wrapper {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.zone-table {
    width: 100%;
    border-collapse: collapse;
}

.zone-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.zone-table thead th {
    padding: 1.25rem 1.5rem;
    text-align: left;
    color: white;
    font-weight: 600;
    font-size: 0.8125rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.zone-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.2s;
}

.zone-table tbody tr:hover {
    background: #f8f9ff;
}

.zone-table tbody tr:last-child {
    border-bottom: none;
}

.zone-table tbody td {
    padding: 1.5rem 1.5rem;
    vertical-align: middle;
}

/* Zone Info Column */
.zone-info-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.zone-icon-box {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(102,126,234,0.3);
}

.zone-icon-box i {
    font-size: 1.75rem;
    color: white;
}

.zone-details {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.zone-name {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.zone-location {
    font-size: 0.8125rem;
    color: #7f8c8d;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.zone-location i {
    font-size: 0.75rem;
}

/* Tournament Badge */
.zone-tournament {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(245,87,108,0.3);
}

.zone-tournament i {
    font-size: 0.875rem;
}

.no-tournament {
    color: #bdc3c7;
    font-size: 0.875rem;
}

/* Stats Badges */
.zone-stats-group {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.zone-stat-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.625rem 1rem;
    border-radius: 10px;
    min-width: 65px;
}

.zone-stat-badge.total {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.zone-stat-badge.available {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.zone-stat-badge.booked {
    background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.stat-label {
    font-size: 0.6875rem;
    color: rgba(255,255,255,0.9);
    text-transform: uppercase;
    margin-top: 0.25rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Action Buttons */
.zone-actions {
    display: flex;
    gap: 0.5rem;
}

.zone-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 0.9375rem;
}

.zone-btn.view {
    background: #3498db;
    color: white;
}

.zone-btn.view:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52,152,219,0.3);
}

.zone-btn.edit {
    background: #4caf50;
    color: white;
}

.zone-btn.edit:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(76,175,80,0.3);
}

.zone-btn.delete {
    background: #e74c3c;
    color: white;
}

.zone-btn.delete:hover {
    background: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(231,76,60,0.3);
}

/* Empty State */
.zone-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.zone-empty-state i {
    font-size: 4rem;
    color: #bdc3c7;
    margin-bottom: 1.5rem;
}

.zone-empty-state h3 {
    font-size: 1.5rem;
    color: #2c3e50;
    margin: 0 0 0.75rem 0;
}

.zone-empty-state p {
    color: #7f8c8d;
    margin: 0 0 2rem 0;
    font-size: 1rem;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .zone-header-section {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .zone-create-btn {
        width: 100%;
        justify-content: center;
    }
    
    .zone-table-wrapper {
        overflow-x: auto;
    }
    
    .zone-stats-group {
        flex-wrap: wrap;
    }
    
    .zone-actions {
        flex-wrap: wrap;
    }
}
</style>

<div class="zone-list-container">
    <!-- Header -->
    <div class="zone-header-section">
        <div class="zone-header-left">
            <h1><i class="fas fa-map-marked-alt"></i> Fishing Zones</h1>
            <p>Manage all fishing zones and spots</p>
        </div>
        <a href="createFishingSpot.php" class="zone-create-btn">
            <i class="fas fa-plus-circle"></i>
            Create Fishing Spot
        </a>
    </div>

    <!-- Table -->
    <?php if (mysqli_num_rows($zones_result) > 0): ?>
        <div class="zone-table-wrapper">
            <table class="zone-table">
                <thead>
                    <tr>
                        <th>Zone Details</th>
                        <th>Tournament</th>
                        <th>Spots Statistics</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($zone = mysqli_fetch_assoc($zones_result)): ?>
                        <tr>
                            <!-- Zone Details -->
                            <td>
                                <div class="zone-info-cell">
                                    <div class="zone-icon-box">
                                        <i class="fas fa-water"></i>
                                    </div>
                                    <div class="zone-details">
                                        <div class="zone-name"><?php echo htmlspecialchars($zone['zone_name']); ?></div>
                                        <div class="zone-location">
                                            <?php if (!empty($zone['zone_description'])): ?>
                                                <i class="fas fa-info-circle"></i>
                                                <?php echo htmlspecialchars(substr($zone['zone_description'], 0, 60)); ?>
                                                <?php if (strlen($zone['zone_description']) > 60) echo '...'; ?>
                                            <?php elseif (!empty($zone['tournament_location'])): ?>
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($zone['tournament_location']); ?>
                                            <?php else: ?>
                                                <i class="fas fa-map-marker-alt"></i>
                                                No location specified
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Tournament -->
                            <td>
                                <?php if (!empty($zone['tournament_title'])): ?>
                                    <div class="zone-tournament">
                                        <i class="fas fa-trophy"></i>
                                        <?php echo htmlspecialchars($zone['tournament_title']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="no-tournament">No tournament</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Statistics -->
                            <td>
                                <div class="zone-stats-group">
                                    <div class="zone-stat-badge total">
                                        <div class="stat-number"><?php echo $zone['total_spots']; ?></div>
                                        <div class="stat-label">Total</div>
                                    </div>
                                    <div class="zone-stat-badge available">
                                        <div class="stat-number"><?php echo $zone['available_spots']; ?></div>
                                        <div class="stat-label">Available</div>
                                    </div>
                                    <div class="zone-stat-badge booked">
                                        <div class="stat-number"><?php echo $zone['booked_spots']; ?></div>
                                        <div class="stat-label">Booked</div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Actions -->
                            <td>
                                <div class="zone-actions">
                                    <a href="viewZone.php?id=<?php echo $zone['zone_id']; ?>" 
                                       class="zone-btn view" title="View Spots">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="editZone.php?id=<?php echo $zone['zone_id']; ?>" 
                                       class="zone-btn edit" title="Edit Zone">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteZone(<?php echo $zone['zone_id']; ?>)" 
                                            class="zone-btn delete" title="Delete Zone">
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
        <div class="zone-empty-state">
            <i class="fas fa-map-marked-alt"></i>
            <h3>No Fishing Zones Yet</h3>
            <p>Create your first fishing zone with spots to get started</p>
            <a href="createFishingSpot.php" class="zone-create-btn">
                <i class="fas fa-plus"></i> Create First Zone
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteZone(id) {
    if (confirm('Delete this zone and all its spots?')) {
        window.location.href = 'deleteZone.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>