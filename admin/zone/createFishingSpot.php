<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Create Fishing Spot';
$page_description = 'Create a new fishing zone and add spots';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $zone_name = sanitize($_POST['zone_name']);
    $zone_description = sanitize($_POST['zone_description'] ?? '');
    $tournament_id = sanitize($_POST['tournament_id'] ?? '');
    $spots_data = $_POST['spots'] ?? [];
    
    if (empty($zone_name)) {
        $error = 'Zone name is required';
    } elseif (empty($spots_data)) {
        $error = 'Please add at least one fishing spot';
    } else {
        // Insert zone
        $zone_query = "INSERT INTO ZONE (zone_name, zone_description, tournament_id, created_at) 
                       VALUES ('$zone_name', '$zone_description', " . 
                       ($tournament_id ? "'$tournament_id'" : "NULL") . ", NOW())";
        
        if (mysqli_query($conn, $zone_query)) {
            $zone_id = mysqli_insert_id($conn);
            
            // Insert spots
            $spots_inserted = 0;
            foreach ($spots_data as $spot) {
                $latitude = sanitize($spot['lat']);
                $longitude = sanitize($spot['lng']);
                
                $spot_query = "INSERT INTO FISHING_SPOT (zone_id, latitude, longitude, spot_status, created_at) 
                               VALUES ('$zone_id', '$latitude', '$longitude', 'available', NOW())";
                
                if (mysqli_query($conn, $spot_query)) {
                    $spots_inserted++;
                }
            }
            
            $_SESSION['success'] = "Zone created successfully with $spots_inserted fishing spots!";
            redirect(SITE_URL . '/admin/zone/zoneList.php');
        } else {
            $error = 'Failed to create zone: ' . mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* Create Fishing Spot Page Styles */
.create-spots-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.form-section-inline {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.form-row-inline {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group-inline {
    display: flex;
    flex-direction: column;
}

.form-group-inline label {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-size: 0.9375rem;
}

.form-control-inline {
    padding: 0.875rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 0.9375rem;
    transition: all 0.2s;
}

.form-control-inline:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

/* Pick Spot Section */
.pick-spot-section {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.section-title-inline {
    font-size: 1.25rem;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
    font-weight: 600;
}

.section-subtitle {
    color: #7f8c8d;
    font-size: 0.9375rem;
    margin: 0 0 1.5rem 0;
}

.map-and-coords {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1.5rem;
}

.map-wrapper {
    position: relative;
}

.spot-map {
    width: 100%;
    height: 500px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
}

.coordinates-panel {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.coord-field {
    display: flex;
    flex-direction: column;
}

.coord-field label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.coord-input {
    padding: 0.75rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 0.9375rem;
    background: white;
    color: #2c3e50;
}

.coord-buttons {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

.btn-clear {
    flex: 1;
    padding: 0.875rem;
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-clear:hover {
    background: #c0392b;
    transform: translateY(-2px);
}

.btn-add-spot-inline {
    flex: 1;
    padding: 0.875rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add-spot-inline:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102,126,234,0.3);
}

/* Spots Table Section */
.spots-table-section {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.spots-table {
    width: 100%;
    border-collapse: collapse;
}

.spots-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.spots-table thead th {
    padding: 1rem 1.5rem;
    text-align: left;
    color: white;
    font-weight: 600;
    font-size: 0.8125rem;
    text-transform: uppercase;
}

.spots-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
}

.spots-table tbody tr:hover {
    background: #f8f9ff;
}

.spots-table tbody td {
    padding: 1rem 1.5rem;
}

.status-badge {
    display: inline-block;
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.8125rem;
    font-weight: 600;
}

.status-badge.available {
    background: #d4edda;
    color: #155724;
}

.btn-remove-spot {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-remove-spot:hover {
    background: #c0392b;
}

@media (max-width: 1024px) {
    .map-and-coords {
        grid-template-columns: 1fr;
    }
    
    .form-row-inline {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="create-spots-container">
    <form method="POST" action="" id="createZoneForm">
        
        <!-- Zone Information -->
        <div class="form-section-inline">
            <h2 class="section-title-inline">
                <i class="fas fa-info-circle"></i> Zone Information
            </h2>
            <p class="section-subtitle">Enter basic zone details</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <div class="form-row-inline">
                <div class="form-group-inline">
                    <label>Zone Name <span style="color: red;">*</span></label>
                    <input type="text" name="zone_name" class="form-control-inline" 
                           placeholder="e.g., Pond 1, Zone A, Main Fishing Area" required>
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
            
            <div class="form-group-inline">
                <label>Zone Description (Optional)</label>
                <textarea name="zone_description" class="form-control-inline" rows="3" 
                          placeholder="Brief description of this fishing zone"></textarea>
            </div>
        </div>
        
        <!-- Pick Fishing Spots -->
        <div class="pick-spot-section">
            <h2 class="section-title-inline">
                <i class="fas fa-map-marker-alt"></i> Pick Fishing Spots
            </h2>
            <p class="section-subtitle">Click on the map to add fishing spots. Drag markers to adjust position.</p>
            
            <div class="map-and-coords">
                <div class="map-wrapper">
                    <div id="spotMap" class="spot-map"></div>
                </div>
                
                <div class="coordinates-panel">
                    <div class="coord-field">
                        <label>Latitude</label>
                        <input type="text" id="currentLat" class="coord-input" readonly value="4.2422">
                    </div>
                    
                    <div class="coord-field">
                        <label>Longitude</label>
                        <input type="text" id="currentLng" class="coord-input" readonly value="118.5925">
                    </div>
                    
                    <div class="coord-field">
                        <label>Total Spots</label>
                        <input type="text" id="spotCount" class="coord-input" readonly value="0">
                    </div>
                    
                    <div class="coord-buttons">
                        <button type="button" class="btn-clear" onclick="clearAllSpots()">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                        <button type="submit" class="btn-add-spot-inline">
                            <i class="fas fa-save"></i> Add Spot
                        </button>
                    </div>
                    
                    <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107;">
                        <div style="font-size: 0.8125rem; color: #856404;">
                            <strong>💡 Tips:</strong>
                            <ul style="margin: 0.5rem 0 0 1.25rem; padding: 0;">
                                <li>Click map to add spots</li>
                                <li>Drag markers to move</li>
                                <li>Right-click to remove</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Spots Table -->
        <div class="spots-table-section">
            <h2 class="section-title-inline">
                <i class="fas fa-list"></i> Added Spots (<span id="tableCount">0</span>)
            </h2>
            <p class="section-subtitle">List of fishing spots to be created</p>
            
            <table class="spots-table">
                <thead>
                    <tr>
                        <th>Spot ID</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Availability</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="spotsTableBody">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: #7f8c8d;">
                            <i class="fas fa-map-marker-alt" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <div>Click on the map to add fishing spots</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <input type="hidden" name="spots" id="spotsData">
    </form>
</div>

<script>
// Initialize map
const map = L.map('spotMap').setView([4.2422, 118.5925], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let markers = [];
let spotCounter = 0;

// Update display
function updateDisplay() {
    document.getElementById('spotCount').value = markers.length;
    document.getElementById('tableCount').textContent = markers.length;
    updateTable();
    updateHiddenField();
}

// Add marker
function addMarker(lat, lng) {
    spotCounter++;
    const marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    
    marker.spotId = spotCounter;
    marker.bindPopup(`Spot ${String(spotCounter).padStart(3, '0')}`).openPopup();
    
    // Drag event
    marker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        document.getElementById('currentLat').value = pos.lat.toFixed(6);
        document.getElementById('currentLng').value = pos.lng.toFixed(6);
        updateDisplay();
    });
    
    // Right-click to remove
    marker.on('contextmenu', function(e) {
        if (confirm('Remove this spot?')) {
            map.removeLayer(marker);
            markers = markers.filter(m => m !== marker);
            updateDisplay();
        }
    });
    
    markers.push(marker);
    updateDisplay();
}

// Map click event
map.on('click', function(e) {
    addMarker(e.latlng.lat, e.latlng.lng);
    document.getElementById('currentLat').value = e.latlng.lat.toFixed(6);
    document.getElementById('currentLng').value = e.latlng.lng.toFixed(6);
});

// Clear all spots
function clearAllSpots() {
    if (markers.length === 0) return;
    
    if (confirm('Clear all spots?')) {
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];
        spotCounter = 0;
        updateDisplay();
    }
}

// Update table
function updateTable() {
    const tbody = document.getElementById('spotsTableBody');
    
    if (markers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 2rem; color: #7f8c8d;">
                    <i class="fas fa-map-marker-alt" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <div>Click on the map to add fishing spots</div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = markers.map((marker, index) => {
        const pos = marker.getLatLng();
        return `
            <tr>
                <td><strong>Spot ${String(marker.spotId).padStart(3, '0')}</strong></td>
                <td>${pos.lat.toFixed(6)}</td>
                <td>${pos.lng.toFixed(6)}</td>
                <td><span class="status-badge available">Available</span></td>
                <td>
                    <button type="button" class="btn-remove-spot" onclick="removeSpot(${index})">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Remove spot
function removeSpot(index) {
    if (confirm('Remove this spot?')) {
        map.removeLayer(markers[index]);
        markers.splice(index, 1);
        updateDisplay();
    }
}

// Update hidden field
function updateHiddenField() {
    const spotsData = markers.map(marker => {
        const pos = marker.getLatLng();
        return { lat: pos.lat, lng: pos.lng };
    });
    document.getElementById('spotsData').value = JSON.stringify(spotsData);
}

// Form validation
document.getElementById('createZoneForm').addEventListener('submit', function(e) {
    if (markers.length === 0) {
        e.preventDefault();
        alert('Please add at least one fishing spot on the map');
    }
});
</script>

<?php include '../includes/footer.php'; ?>