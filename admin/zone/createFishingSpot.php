<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Create Fishing Zone';
$page_description = 'Create a fishing zone and add spots';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $zone_name = sanitize($_POST['zone_name']);
    $zone_description = sanitize($_POST['zone_description'] ?? '');
    $tournament_id = sanitize($_POST['tournament_id'] ?? '');
    $spots_data = json_decode($_POST['spots_data'], true);
    
    if (empty($zone_name)) {
        $error = 'Fishing Zone Name is required';
    } elseif (empty($spots_data)) {
        $error = 'Please add at least one spot by clicking on the map';
    } else {
        // Insert zone first
        $insert_zone = "INSERT INTO ZONE (zone_name, zone_description, tournament_id, created_at) 
                        VALUES ('$zone_name', '$zone_description', " . ($tournament_id ? "'$tournament_id'" : "NULL") . ", NOW())";
        
        if (mysqli_query($conn, $insert_zone)) {
            $zone_id = mysqli_insert_id($conn);
            
            // Insert all spots
            $added_count = 0;
            foreach ($spots_data as $spot) {
                $lat = sanitize($spot['lat']);
                $lng = sanitize($spot['lng']);
                
                $insert_spot = "INSERT INTO FISHING_SPOT (zone_id, latitude, longitude, spot_status, created_at) 
                                VALUES ('$zone_id', '$lat', '$lng', 'available', NOW())";
                
                if (mysqli_query($conn, $insert_spot)) {
                    $added_count++;
                }
            }
            
            $_SESSION['success'] = "Fishing zone '$zone_name' created with $added_count spot(s)!";
            redirect(SITE_URL . '/admin/zone/zoneList.php');
        } else {
            $error = 'Failed to create fishing zone: ' . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="form-container">
    <h2 class="form-header-title">
        <i class="fas fa-map-marked-alt"></i> Create Fishing Zone
    </h2>
    <p class="form-header-subtitle">Create a new fishing zone and add spots by clicking on the map</p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="createSpotsForm">
        <!-- Zone Information -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-info-circle"></i>
                Zone Information
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Fishing Zone Name <span class="required">*</span></label>
                    <input type="text" name="zone_name" class="form-control" 
                           placeholder="e.g., Zone A, Pond 1" required>
                </div>

                <div class="form-group">
                    <label>Link to Tournament (Optional)</label>
                    <select name="tournament_id" class="form-control">
                        <option value="">-- Select Tournament --</option>
                        <?php
                        $tournaments = mysqli_query($conn, "SELECT tournament_id, tournament_title FROM TOURNAMENT ORDER BY tournament_date DESC");
                        while ($t = mysqli_fetch_assoc($tournaments)):
                        ?>
                            <option value="<?php echo $t['tournament_id']; ?>">
                                <?php echo htmlspecialchars($t['tournament_title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Zone Description</label>
                <textarea name="zone_description" class="form-control" 
                          placeholder="Describe this fishing zone..."></textarea>
            </div>
        </div>

        <!-- Map Section -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-map-marker-alt"></i>
                Add Fishing Spots (Click on Map)
            </div>
            
            <div style="background: var(--color-blue-light); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                <i class="fas fa-info-circle"></i>
                <strong>Instructions:</strong> Click anywhere on the map to add fishing spots. Right-click on a marker to remove it.
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <!-- Map -->
                <div>
                    <div id="spotMap" style="width: 100%; height: 400px; border-radius: var(--radius-md); border: 3px solid var(--color-blue-primary); box-shadow: var(--shadow-md);"></div>
                </div>
                
                <!-- Coordinates Panel -->
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Current Latitude</label>
                        <input type="text" id="currentLat" class="form-control" readonly placeholder="Click on map">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Current Longitude</label>
                        <input type="text" id="currentLng" class="form-control" readonly placeholder="Click on map">
                    </div>
                    
                    <button type="button" class="btn btn-danger" onclick="clearAllSpots()" style="margin-top: auto;">
                        <i class="fas fa-trash"></i> Clear All Spots
                    </button>
                </div>
            </div>

            <!-- Spots Table -->
            <div style="margin-top: 2rem;">
                <h3 style="color: var(--color-blue-primary); margin-bottom: 1rem;">
                    <i class="fas fa-list"></i> Added Spots (<span id="spotCount">0</span>)
                </h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Spot ID</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="spotsTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-gray-600);">
                                No spots added yet. Click on the map to add spots.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Hidden input for spots data -->
        <input type="hidden" name="spots_data" id="spotsData">

        <!-- Form Actions -->
        <div class="btn-group">
            <a href="zoneList.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                <i class="fas fa-check"></i> Create Zone
            </button>
        </div>
    </form>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Initialize map
const map = L.map('spotMap').setView([4.2105, 101.9758], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19
}).addTo(map);

let spots = [];
let spotCounter = 0;

// Update spot count display
function updateSpotCount() {
    document.getElementById('spotCount').textContent = spots.length;
    document.getElementById('submitBtn').disabled = (spots.length === 0);
}

// Add spot to table
function addSpotToTable(spot) {
    const tbody = document.getElementById('spotsTableBody');
    
    // Remove "no spots" row if exists
    if (tbody.querySelector('tr td[colspan="5"]')) {
        tbody.innerHTML = '';
    }
    
    const row = document.createElement('tr');
    row.id = `spot-row-${spot.id}`;
    row.innerHTML = `
        <td><strong>#${String(spot.id).padStart(3, '0')}</strong></td>
        <td>${spot.lat}</td>
        <td>${spot.lng}</td>
        <td><span class="badge badge-success">AVAILABLE</span></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeSpot(${spot.id})" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
    updateSpotCount();
}

// Remove spot from table
function removeSpotFromTable(spotId) {
    const row = document.getElementById(`spot-row-${spotId}`);
    if (row) {
        row.remove();
    }
    
    // Add "no spots" row if table is empty
    const tbody = document.getElementById('spotsTableBody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--color-gray-600);">No spots added yet. Click on the map to add spots.</td></tr>';
    }
    updateSpotCount();
}

// Click event - add new spot
map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(8);
    const lng = e.latlng.lng.toFixed(8);
    
    spotCounter++;
    const spotId = spotCounter;
    
    // Create marker
    const marker = L.marker([lat, lng], {
        draggable: true,
        title: `Spot ${spotId}`
    }).addTo(map);
    
    marker.bindPopup(`<b>Spot ${spotId}</b><br>Lat: ${lat}<br>Lng: ${lng}`).openPopup();
    
    // Store spot
    const spotData = {
        id: spotId,
        lat: lat,
        lng: lng,
        marker: marker
    };
    spots.push(spotData);
    
    // Update current coordinates display
    document.getElementById('currentLat').value = lat;
    document.getElementById('currentLng').value = lng;
    
    // Add to table
    addSpotToTable(spotData);
    
    // Right-click to remove
    marker.on('contextmenu', function() {
        if (confirm('Remove this spot?')) {
            map.removeLayer(marker);
            spots = spots.filter(s => s.id !== spotId);
            removeSpotFromTable(spotId);
        }
    });
    
    // Drag event
    marker.on('dragend', function(e) {
        const newLat = e.target.getLatLng().lat.toFixed(8);
        const newLng = e.target.getLatLng().lng.toFixed(8);
        
        const spot = spots.find(s => s.id === spotId);
        if (spot) {
            spot.lat = newLat;
            spot.lng = newLng;
            
            // Update table
            const row = document.getElementById(`spot-row-${spotId}`);
            if (row) {
                row.cells[1].textContent = newLat;
                row.cells[2].textContent = newLng;
            }
        }
        
        marker.setPopupContent(`<b>Spot ${spotId}</b><br>Lat: ${newLat}<br>Lng: ${newLng}`);
    });
});

function removeSpot(spotId) {
    const spot = spots.find(s => s.id === spotId);
    if (spot && confirm('Remove this spot?')) {
        map.removeLayer(spot.marker);
        spots = spots.filter(s => s.id !== spotId);
        removeSpotFromTable(spotId);
    }
}

function clearAllSpots() {
    if (spots.length === 0) {
        alert('No spots to clear');
        return;
    }
    
    if (confirm(`Clear all ${spots.length} spot(s)?`)) {
        spots.forEach(spot => {
            map.removeLayer(spot.marker);
        });
        
        spots = [];
        spotCounter = 0;
        
        const tbody = document.getElementById('spotsTableBody');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--color-gray-600);">No spots added yet. Click on the map to add spots.</td></tr>';
        
        document.getElementById('currentLat').value = '';
        document.getElementById('currentLng').value = '';
        
        updateSpotCount();
    }
}

// Form submission
document.getElementById('createSpotsForm').addEventListener('submit', function(e) {
    if (spots.length === 0) {
        e.preventDefault();
        alert('Please add at least one spot by clicking on the map');
        return false;
    }
    
    const spotsData = spots.map(spot => ({
        lat: spot.lat,
        lng: spot.lng
    }));
    
    document.getElementById('spotsData').value = JSON.stringify(spotsData);
});

L.control.scale().addTo(map);
</script>

<?php include '../includes/footer.php'; ?>