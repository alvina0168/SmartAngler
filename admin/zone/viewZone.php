<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$zone_id = intval($_GET['id']);

// Handle adding new spots
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['new_spots'])) {
    $new_spots = json_decode($_POST['new_spots'], true);

    // Get the current max spot_number in this zone
    $max_spot_result = mysqli_query($conn, "SELECT MAX(spot_number) AS max_number FROM FISHING_SPOT WHERE zone_id = '$zone_id'");
    $max_spot_row = mysqli_fetch_assoc($max_spot_result);
    $max_number = $max_spot_row['max_number'] ?? 0;

    foreach ($new_spots as $s) {
        $lat = sanitize($s['lat']);
        $lng = sanitize($s['lng']);
        $max_number++;
        mysqli_query($conn, "INSERT INTO FISHING_SPOT (zone_id, spot_number, latitude, longitude, spot_status, created_at) 
                             VALUES ('$zone_id','$max_number','$lat','$lng','available',NOW())");
    }
    $_SESSION['success'] = count($new_spots) . " new spot(s) added!";
    redirect(SITE_URL . '/admin/zone/viewZone.php?id=' . $zone_id);
}

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
    ORDER BY fs.spot_number ASC
";
$spots_result = mysqli_query($conn, $spots_query);

// Count stats: Total, Available, Occupied, Maintenance
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN spot_status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN spot_status = 'occupied' THEN 1 ELSE 0 END) as occupied,
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

<!-- Back Button -->
<div class="text-right mb-3">
    <a href="zoneList.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Zones
    </a>
    <a href="editZone.php?id=<?php echo $zone_id; ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Edit Zone
    </a>
</div>

<!-- Zone Header -->
<div class="section">
    <div class="section-header">
        <div>
            <h2 class="section-title">
                <i class="fas fa-map-marked-alt"></i> <?php echo htmlspecialchars($zone['zone_name']); ?>
            </h2>
            <?php if (!empty($zone['zone_description'])): ?>
                <p style="color: var(--color-gray-600); margin-top: 0.5rem;">
                    <?php echo htmlspecialchars($zone['zone_description']); ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($zone['tournament_title'])): ?>
                <span class="badge badge-info" style="margin-top: 0.5rem;">
                    <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($zone['tournament_title']); ?>
                </span>
            <?php endif; ?>
        </div>
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

    <div class="stat-card occupied">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo $stats['occupied']; ?></div>
                <div class="stat-label">Occupied</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-users"></i>
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
<div class="section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-map"></i>
            Spots Map View
        </h2>
    </div>
    <div id="spotsMap" style="width: 100%; height: 500px; border-radius: var(--radius-md); border: 3px solid var(--color-blue-primary); box-shadow: var(--shadow-md);"></div>
</div>

<!-- Add New Spots Section -->
<div class="section">
    <h3>
        <i class="fas fa-plus-circle"></i> Add New Fishing Spots
    </h3>

    <form method="POST" id="addSpotsForm">
        <p style="margin-bottom: 1rem;">
            Click on the map to add new spots. Drag markers to adjust coordinates.
        </p>
        <table class="table">
            <thead>
                <tr>
                    <th>Spot Number</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="newSpotsTable">
                <tr><td colspan="4" style="text-align:center;color:var(--color-gray-600);">Click on the map to add spots</td></tr>
            </tbody>
        </table>

        <input type="hidden" name="new_spots" id="newSpotsInput">
        <button type="submit" class="btn btn-success" id="addSpotsBtn" disabled>
            <i class="fas fa-plus"></i> Add New Spots
        </button>
    </form>
</div>

<!-- Spots Table -->
<div class="section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-list"></i>
            All Spots (<?php echo $stats['total']; ?>)
        </h2>
    </div>

    <?php if (mysqli_num_rows($spots_result) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Spot Number</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Status</th>
                    <th>Booked By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($spot = mysqli_fetch_assoc($spots_result)): ?>
                    <tr>
                        <td><strong>#<?php echo $spot['spot_number']; ?></strong></td>
                        <td style="font-family: 'Courier New', monospace; font-size: 0.875rem;">
                            <?php echo !empty($spot['latitude']) ? $spot['latitude'] : '-'; ?>
                        </td>
                        <td style="font-family: 'Courier New', monospace; font-size: 0.875rem;">
                            <?php echo !empty($spot['longitude']) ? $spot['longitude'] : '-'; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php 
                                switch($spot['spot_status']){
                                    case 'available': echo 'success'; break;
                                    case 'occupied': echo 'primary'; break;
                                    case 'maintenance': echo 'info'; break;
                                    default: echo 'secondary';
                                } 
                            ?>">
                                <?php echo strtoupper($spot['spot_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo !empty($spot['booked_by']) ? htmlspecialchars($spot['booked_by']) : '-'; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="editSpot.php?id=<?php echo $spot['spot_id']; ?>" 
                                   class="btn btn-success btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteSpot(<?php echo $spot['spot_id']; ?>)" 
                                        class="btn btn-danger btn-sm" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-map-marker-alt"></i>
            <h3>No Spots in This Zone</h3>
            <p>No fishing spots have been added to this zone yet</p>
        </div>
    <?php endif; ?>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const spotsMap = L.map('spotsMap').setView([4.2105, 101.9758], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19
}).addTo(spotsMap);

// Existing spots markers
const existingSpots = [
<?php 
mysqli_data_seek($spots_result, 0);
while ($spot = mysqli_fetch_assoc($spots_result)):
    if(!empty($spot['latitude']) && !empty($spot['longitude'])):
?>
{number: <?php echo $spot['spot_number']; ?>, lat: <?php echo $spot['latitude']; ?>, lng: <?php echo $spot['longitude']; ?>, status: '<?php echo $spot['spot_status']; ?>'},
<?php 
    endif;
endwhile; 
?>
];

existingSpots.forEach(spot=>{
    const marker = L.marker([spot.lat, spot.lng]).addTo(spotsMap);
    marker.bindPopup(`<b>Spot #${spot.number}</b><br>Status: ${spot.status}`);
});

if(existingSpots.length>0){
    const bounds = L.latLngBounds(existingSpots.map(s=>[s.lat,s.lng]));
    spotsMap.fitBounds(bounds,{padding:[50,50]});
}

// Add new spots logic with continuous spot_number
let newSpots = [];
let maxSpotNumber = <?php 
    $max_result = mysqli_query($conn, "SELECT MAX(spot_number) AS max_number FROM FISHING_SPOT WHERE zone_id = '$zone_id'");
    $max_row = mysqli_fetch_assoc($max_result);
    echo $max_row['max_number'] ?? 0;
?>;

const addSpotsForm = document.getElementById('addSpotsForm');
const newSpotsInput = document.getElementById('newSpotsInput');
const newSpotsTable = document.getElementById('newSpotsTable');
const addSpotsBtn = document.getElementById('addSpotsBtn');

spotsMap.on('click', function(e){
    const lat = e.latlng.lat.toFixed(8);
    const lng = e.latlng.lng.toFixed(8);

    maxSpotNumber++; // increment spot_number for new spot

    const marker = L.marker([lat,lng], { draggable: true }).addTo(spotsMap);
    marker.bindPopup(`New Spot #${maxSpotNumber}`).openPopup();

    const spot = { lat, lng, marker, spot_number: maxSpotNumber };
    newSpots.push(spot);
    updateNewSpotsTable();

    marker.on('dragend', function(ev){
        const idx = newSpots.findIndex(s => s.marker === marker);
        newSpots[idx].lat = ev.target.getLatLng().lat.toFixed(8);
        newSpots[idx].lng = ev.target.getLatLng().lng.toFixed(8);
        updateNewSpotsTable();
    });
});

function updateNewSpotsTable(){
    newSpotsTable.innerHTML = '';
    newSpots.forEach((s)=>{
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>#${s.spot_number}</td>
            <td>${s.lat}</td>
            <td>${s.lng}</td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeNewSpot(${s.spot_number})"><i class="fas fa-trash"></i></button></td>
        `;
        newSpotsTable.appendChild(row);
    });
    if(newSpots.length === 0){
        newSpotsTable.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--color-gray-600);">Click on the map to add spots</td></tr>';
    }
    newSpotsInput.value = JSON.stringify(newSpots.map(s => ({lat:s.lat,lng:s.lng,spot_number:s.spot_number})));
    addSpotsBtn.disabled = newSpots.length === 0;
}

function removeNewSpot(spotNumber){
    const index = newSpots.findIndex(s => s.spot_number === spotNumber);
    if(index !== -1){
        spotsMap.removeLayer(newSpots[index].marker);
        newSpots.splice(index,1);
        updateNewSpotsTable();
    }
}


function deleteSpot(id){
    if(confirm('Are you sure you want to delete this spot?')){
        window.location.href = 'deleteSpot.php?id='+id+'&zone_id=<?php echo $zone_id; ?>';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
