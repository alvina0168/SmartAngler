<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Create Fishing Spot';
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

<div class="create-spots-container">
    <h2 class="page-title">
        <i class="fas fa-map-marked-alt"></i> Create Spots
    </h2>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="createSpotsForm">
        <!-- Fishing Zone Name -->
        <div class="form-section-inline">
            <div class="form-group-inline">
                <label>Fishing Zone Name <span class="required">*</span></label>
                <input type="text" name="zone_name" class="form-control-inline" 
                       placeholder="e.g., Pond 1, Zone A" required>
            </div>

            <div class="form-group-inline">
                <label>Link to Tournament (Optional)</label>
                <select name="tournament_id" class="form-control-inline">
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

        <!-- Pick Your Spot Section -->
        <div class="pick-spot-section">
            <h3 class="section-subtitle">Pick Your Spot</h3>
            
            <div class="map-and-coords">
                <!-- Map -->
                <div class="map-wrapper">
                    <div id="spotMap" class="spot-map"></div>
                </div>
                
                <!-- Coordinates with Buttons -->
                <div class="coordinates-panel">
                    <div class="coord-field">
                        <label>Latitude</label>
                        <input type="text" id="currentLat" class="coord-input" readonly placeholder="Click on map">
                    </div>
                    <div class="coord-field">
                        <label>Longitude</label>
                        <input type="text" id="currentLng" class="coord-input" readonly placeholder="Click on map">
                    </div>
                    
                    <!-- Buttons below coordinates -->
                    <div class="coord-buttons">
                        <button type="button" class="btn-clear" onclick="clearAllSpots()">
                            Clear
                        </button>
                        <button type="submit" class="btn-add-spot-inline" id="submitBtn" disabled>
                            Add Spot
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Spots Table -->
        <div class="spots-table-section">
            <table class="spots-table">
                <thead>
                    <tr>
                        <th>SPOT ID</th>
                        <th>AVAILABILITY</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="spotsTableBody">
                    <tr class="no-spots-row">
                        <td colspan="3">No spots added yet. Click on the map to add spots.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Hidden input for spots data -->
        <input type="hidden" name="spots_data" id="spotsData">
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
            updateSubmitButton();
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
            updateSpotInTable(spotId, newLat, newLng);
        }
        
        marker.setPopupContent(`<b>Spot ${spotId}</b><br>Lat: ${newLat}<br>Lng: ${newLng}`);
    });
    
    updateSubmitButton();
});

function addSpotToTable(spot) {
    const tbody = document.getElementById('spotsTableBody');
    
    // Remove "no spots" row if exists
    const noSpotsRow = tbody.querySelector('.no-spots-row');
    if (noSpotsRow) {
        noSpotsRow.remove();
    }
    
    const row = document.createElement('tr');
    row.id = `spot-row-${spot.id}`;
    row.innerHTML = `
        <td>${String(spot.id).padStart(3, '0')}</td>
        <td><span class="status-badge available">AVAILABLE</span></td>
        <td>
            <button type="button" class="btn-icon-action" onclick="removeSpot(${spot.id})" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
}

function updateSpotInTable(spotId, lat, lng) {
    // Table doesn't show lat/lng anymore, so just update the spot data
    const spot = spots.find(s => s.id === spotId);
    if (spot) {
        spot.lat = lat;
        spot.lng = lng;
    }
}

function removeSpotFromTable(spotId) {
    const row = document.getElementById(`spot-row-${spotId}`);
    if (row) {
        row.remove();
    }
    
    // Add "no spots" row if table is empty
    const tbody = document.getElementById('spotsTableBody');
    if (tbody.children.length === 0) {
        const noSpotsRow = document.createElement('tr');
        noSpotsRow.className = 'no-spots-row';
        noSpotsRow.innerHTML = '<td colspan="3">No spots added yet. Click on the map to add spots.</td>';
        tbody.appendChild(noSpotsRow);
    }
}

function removeSpot(spotId) {
    const spot = spots.find(s => s.id === spotId);
    if (spot && confirm('Remove this spot?')) {
        map.removeLayer(spot.marker);
        spots = spots.filter(s => s.id !== spotId);
        removeSpotFromTable(spotId);
        updateSubmitButton();
    }
}

function clearAllSpots() {
    if (spots.length === 0) {
        alert('No spots to clear');
        return;
    }
    
    if (confirm(`Clear all ${spots.length} spot(s)?`)) {
        // Remove all markers from map
        spots.forEach(spot => {
            map.removeLayer(spot.marker);
        });
        
        // Clear spots array
        spots = [];
        spotCounter = 0;
        
        // Clear table
        const tbody = document.getElementById('spotsTableBody');
        tbody.innerHTML = '<tr class="no-spots-row"><td colspan="3">No spots added yet. Click on the map to add spots.</td></tr>';
        
        // Clear coordinates
        document.getElementById('currentLat').value = '';
        document.getElementById('currentLng').value = '';
        
        // Update button
        updateSubmitButton();
    }
}

function updateSubmitButton() {
    document.getElementById('submitBtn').disabled = (spots.length === 0);
}

// Form submission
document.getElementById('createSpotsForm').addEventListener('submit', function(e) {
    if (spots.length === 0) {
        e.preventDefault();
        alert('Please add at least one spot by clicking on the map');
        return false;
    }
    
    // Prepare data
    const spotsData = spots.map(spot => ({
        lat: spot.lat,
        lng: spot.lng
    }));
    
    document.getElementById('spotsData').value = JSON.stringify(spotsData);
});

L.control.scale().addTo(map);
</script>

<?php include '../includes/footer.php'; ?>