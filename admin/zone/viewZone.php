<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$zone_id = intval($_GET['id']);

// Get zone info
$zone_query = "SELECT z.*, t.tournament_title 
               FROM ZONE z 
               LEFT JOIN TOURNAMENT t ON z.tournament_id = t.tournament_id
               WHERE z.zone_id = '$zone_id'";
$zone_result = mysqli_query($conn, $zone_query);

if (!$zone_result || mysqli_num_rows($zone_result) == 0) {
    $_SESSION['error'] = 'Zone not found';
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$zone = mysqli_fetch_assoc($zone_result);

$page_title = $zone['zone_name'];
$page_description = 'View and manage fishing spots';

// Get all spots in this zone
$spots_query = "
    SELECT fs.*, 
           u.full_name as booked_by
    FROM FISHING_SPOT fs
    LEFT JOIN TOURNAMENT_REGISTRATION tr ON fs.spot_id = tr.spot_id AND tr.approval_status = 'approved'
    LEFT JOIN USER u ON tr.user_id = u.user_id
    WHERE fs.zone_id = '$zone_id'
    ORDER BY fs.spot_id ASC
";
$spots_result = mysqli_query($conn, $spots_query);

// Count stats
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN spot_status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN spot_status = 'booked' THEN 1 ELSE 0 END) as booked,
        SUM(CASE WHEN spot_status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
    FROM FISHING_SPOT 
    WHERE zone_id = '$zone_id'
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include '../includes/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Zone Header -->
<div class="zone-details-header">
    <div class="zone-details-left">
        <a href="zoneList.php" class="btn btn-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left"></i> Back to Zones
        </a>
        <h2><i class="fas fa-map-marked-alt"></i> <?php echo htmlspecialchars($zone['zone_name']); ?></h2>
        <?php if (!empty($zone['zone_description'])): ?>
            <p><?php echo htmlspecialchars($zone['zone_description']); ?></p>
        <?php endif; ?>
        <?php if (!empty($zone['tournament_title'])): ?>
            <span class="badge badge-info">
                <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($zone['tournament_title']); ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- Zone Statistics -->
<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Spots</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-map-pin"></i>
            </div>
        </div>
    </div>

    <div class="stat-card success">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo $stats['available']; ?></div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>

    <div class="stat-card warning">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo $stats['booked']; ?></div>
                <div class="stat-label">Booked</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-bookmark"></i>
            </div>
        </div>
    </div>

    <div class="stat-card info">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo $stats['maintenance']; ?></div>
                <div class="stat-label">Maintenance</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-tools"></i>
            </div>
        </div>
    </div>
</div>

<!-- Map View -->
<?php if (mysqli_num_rows($spots_result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-map"></i>
                Spots Map View
            </h2>
        </div>
        
        <div class="map-view-container">
            <div id="spotsMap" class="spots-map-large"></div>
        </div>
    </div>

    <?php mysqli_data_seek($spots_result, 0); ?>
<?php endif; ?>

<!-- Spots Table -->
<div class="section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-list"></i>
            All Spots (<?php echo $stats['total']; ?>)
        </h2>
    </div>

    <?php if (mysqli_num_rows($spots_result) > 0): ?>
        <table class="spots-table">
            <thead>
                <tr>
                    <th>SPOT ID</th>
                    <th>LATITUDE</th>
                    <th>LONGITUDE</th>
                    <th>STATUS</th>
                    <th>BOOKED BY</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($spot = mysqli_fetch_assoc($spots_result)): ?>
                    <tr>
                        <td><?php echo $spot['spot_id']; ?></td>
                        <td class="coord-cell">
                            <?php echo !empty($spot['latitude']) ? $spot['latitude'] : '-'; ?>
                        </td>
                        <td class="coord-cell">
                            <?php echo !empty($spot['longitude']) ? $spot['longitude'] : '-'; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $spot['spot_status']; ?>">
                                <?php echo strtoupper($spot['spot_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo !empty($spot['booked_by']) ? htmlspecialchars($spot['booked_by']) : '-'; ?>
                        </td>
                        <td>
                            <a href="editSpot.php?id=<?php echo $spot['spot_id']; ?>" class="btn-icon-small" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="deleteSpot(<?php echo $spot['spot_id']; ?>)" class="btn-icon-small" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state-mini">
            <i class="fas fa-map-marker-alt"></i>
            <p>No spots in this zone yet</p>
        </div>
    <?php endif; ?>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
<?php if (mysqli_num_rows($spots_result) > 0): ?>
    const spotsMap = L.map('spotsMap').setView([4.2105, 101.9758], 6);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19
    }).addTo(spotsMap);
    
    const spots = [
        <?php 
        mysqli_data_seek($spots_result, 0);
        while ($spot = mysqli_fetch_assoc($spots_result)): 
            if (!empty($spot['latitude']) && !empty($spot['longitude'])):
        ?>
        {
            id: <?php echo $spot['spot_id']; ?>,
            lat: <?php echo $spot['latitude']; ?>,
            lng: <?php echo $spot['longitude']; ?>,
            status: '<?php echo $spot['spot_status']; ?>'
        },
        <?php 
            endif;
        endwhile; 
        ?>
    ];
    
    spots.forEach(spot => {
        const marker = L.marker([spot.lat, spot.lng]).addTo(spotsMap);
        marker.bindPopup(`<b>Spot #${spot.id}</b><br>Status: ${spot.status}`);
    });
    
    if (spots.length > 0) {
        const bounds = L.latLngBounds(spots.map(s => [s.lat, s.lng]));
        spotsMap.fitBounds(bounds, { padding: [50, 50] });
    }
<?php endif; ?>

function deleteSpot(id) {
    if (confirm('Are you sure you want to delete this spot?')) {
        window.location.href = 'deleteSpot.php?id=' + id + '&zone_id=<?php echo $zone_id; ?>';
    }
}
</script>

<?php include '../includes/footer.php'; ?>