<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check access - both organizer and admin can create zones
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['organizer', 'admin'])) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Create Fishing Zone';
$page_description = 'Create a fishing zone and add spots';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $zone_name = sanitize($_POST['zone_name']);
    $zone_description = sanitize($_POST['zone_description'] ?? '');
    $spots_data = json_decode($_POST['spots_data'], true);
    
    if (empty($zone_name)) {
        $error = 'Fishing Zone Name is required';
    } elseif (empty($spots_data)) {
        $error = 'Please add at least one spot by clicking on the map';
    } else {
        // Insert zone (without tournament_id - zones are not assigned to tournaments at creation)
        $insert_zone = "INSERT INTO ZONE (zone_name, zone_description, created_at) 
                        VALUES ('$zone_name', '$zone_description', NOW())";
        
        if (mysqli_query($conn, $insert_zone)) {
            $zone_id = mysqli_insert_id($conn);
            
            // Insert all spots with sequential numbering
            $spot_number = 1;
            $added_count = 0;
            
            foreach ($spots_data as $spot) {
                $lat = sanitize($spot['lat']);
                $lng = sanitize($spot['lng']);
                
                $insert_spot = "INSERT INTO FISHING_SPOT (zone_id, spot_number, latitude, longitude, spot_status, created_at) 
                                VALUES ('$zone_id', '$spot_number', '$lat', '$lng', 'available', NOW())";
                
                if (mysqli_query($conn, $insert_spot)) {
                    $added_count++;
                    $spot_number++;
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

<style>
#spotMap {
    width: 100%;
    height: 650px;
    border-radius: 12px;
    border: 3px solid var(--color-blue-primary);
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.leaflet-control-layers {
    border: 2px solid rgba(0,0,0,0.2);
    border-radius: 8px;
}

.leaflet-bar {
    border-radius: 8px;
}

.search-box {
    position: relative;
    margin-bottom: 1rem;
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper .search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.search-input-wrapper input {
    padding-left: 2.5rem;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    margin-top: 0.25rem;
}

.search-result-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid #f1f1f1;
    transition: background 0.2s;
}

.search-result-item:hover {
    background: #f8f9fa;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-title {
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 0.25rem;
}

.search-result-address {
    font-size: 0.875rem;
    color: #6c757d;
}

.search-loading {
    padding: 1rem;
    text-align: center;
    color: #6c757d;
}
</style>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="zoneList.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Zones
    </a>
</div>

<div class="section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-map-marked-alt"></i> Create Fishing Zone
        </h3>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="createZoneForm">
        <!-- Zone Information -->
        <div class="form-group">
            <label>Fishing Zone Name <span class="required">*</span></label>
            <input type="text" name="zone_name" class="form-control" placeholder="Zone Name" required>
        </div>

        <div class="form-group">
            <label>Zone Description</label>
            <textarea name="zone_description" class="form-control" rows="3" placeholder="Describe this fishing zone..."></textarea>
        </div>

        <!-- Location Search -->
        <div class="form-group">
            <label>
                <i class="fas fa-search"></i> Search Location
            </label>
            <div class="search-box">
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           id="locationSearch" 
                           class="form-control" 
                           placeholder="Search for location"
                           autocomplete="off">
                </div>
                <div id="searchResults" class="search-results"></div>
            </div>
        </div>

        <!-- Map and Coordinates Grid -->
        <div class="info-grid" style="margin-bottom: 1.5rem;">
            <!-- Map -->
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 0.75rem;">
                    <i class="fas fa-map"></i> Add Fishing Spots (Click on Map)
                </label>
                <div id="spotMap"></div>
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
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Zoom Level</label>
                    <input type="text" id="zoomLevel" class="form-control" readonly value="10">
                </div>
                <div style="margin-top: auto;">
                    <button type="button" class="btn btn-danger" onclick="clearAllSpots()" style="width: 100%;">
                        <i class="fas fa-trash"></i> Clear All Spots
                    </button>
                </div>
            </div>
        </div>

        <!-- Spots Table -->
        <div style="margin-top: 2rem;">
            <h4 style="color: var(--color-blue-primary); margin-bottom: 1rem; font-size: 1.125rem;">
                <i class="fas fa-list"></i> Added Spots (<span id="spotCount">0</span>)
            </h4>
            <table class="table">
                <thead>
                    <tr>
                        <th width="100">Spot #</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th width="120">Status</th>
                        <th width="100">Action</th>
                    </tr>
                </thead>
                <tbody id="spotsTableBody">
                    <tr>
                        <td colspan="5" style="text-align: center; color: #6c757d; padding: 2rem;">
                            <i class="fas fa-map-marker-alt" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                            No spots added yet. Click on the map to add spots.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Hidden input for spots data -->
        <input type="hidden" name="spots_data" id="spotsData">

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="zoneList.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                <i class="fas fa-check"></i> Create Zone
            </button>
        </div>
    </form>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Initialize map centered on Sabah, Malaysia with higher max zoom
const map = L.map('spotMap').setView([5.4164, 116.0553], 10);

// Google Maps-style Street Layer
const googleStreets = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    attribution: '© Google Maps'
});

// Google Satellite Layer
const googleSatellite = L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    attribution: '© Google Maps'
});

// Google Hybrid Layer (Satellite + Roads/Labels)
const googleHybrid = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    attribution: '© Google Maps'
});

// Google Terrain Layer
const googleTerrain = L.tileLayer('https://{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    attribution: '© Google Maps'
});

// Add Google Hybrid as default (best for fishing spots)
googleHybrid.addTo(map);

// Layer Control
const baseLayers = {
    "🌍 Hybrid (Recommended)": googleHybrid,
    "🛰️ Satellite": googleSatellite,
    "🗺️ Street Map": googleStreets,
    "🏔️ Terrain": googleTerrain
};

L.control.layers(baseLayers, null, {
    position: 'topright'
}).addTo(map);

let spots = [];
let spotCounter = 0;
let searchMarker = null;

// Update zoom level display
map.on('zoomend', function() {
    document.getElementById('zoomLevel').value = map.getZoom();
});

// Location Search using Nominatim with better error handling
const searchInput = document.getElementById('locationSearch');
const searchResults = document.getElementById('searchResults');
let searchTimeout;

searchInput.addEventListener('input', function() {
    const query = this.value.trim();
    
    clearTimeout(searchTimeout);
    
    if (query.length < 3) {
        searchResults.style.display = 'none';
        return;
    }
    
    searchResults.innerHTML = '<div class="search-loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
    searchResults.style.display = 'block';
    
    searchTimeout = setTimeout(() => {
        // Use Nominatim API with proper CORS handling
        const apiUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=my&limit=10&addressdetails=1`;
        
        fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'User-Agent': 'SmartAngler/1.0'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Search results:', data);
            
            if (data.length === 0) {
                searchResults.innerHTML = `
                    <div class="search-loading">
                        <i class="fas fa-info-circle"></i> No results found for "${query}"
                        <br><small>Try: Tawau, Kota Kinabalu, Sandakan, Lahad Datu, etc.</small>
                    </div>
                `;
                return;
            }
            
            searchResults.innerHTML = '';
            
            data.forEach(result => {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                
                // Get better display name
                const placeName = result.name || result.display_name.split(',')[0];
                const address = result.display_name;
                
                item.innerHTML = `
                    <div class="search-result-title">
                        <i class="fas fa-map-marker-alt" style="color: var(--color-blue-primary); margin-right: 0.5rem;"></i>
                        ${placeName}
                    </div>
                    <div class="search-result-address">${address}</div>
                `;
                
                item.addEventListener('click', () => {
                    const lat = parseFloat(result.lat);
                    const lng = parseFloat(result.lon);
                    
                    // Zoom to location with higher zoom level
                    map.setView([lat, lng], 18);
                    
                    // Remove previous search marker
                    if (searchMarker) {
                        map.removeLayer(searchMarker);
                    }
                    
                    // Add temporary marker at searched location
                    searchMarker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'search-marker',
                            html: '<div style="background: #ff4444; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>',
                            iconSize: [30, 30],
                            iconAnchor: [15, 15]
                        })
                    }).addTo(map);
                    
                    searchMarker.bindPopup(`
                        <b>📍 ${placeName}</b><br>
                        <small>${address}</small><br>
                        <small style="color: #28a745;"><i class="fas fa-info-circle"></i> Click on map to add spots here</small>
                    `).openPopup();
                    
                    // Clear search
                    searchInput.value = placeName;
                    searchResults.style.display = 'none';
                    
                    // Update coordinates display
                    document.getElementById('currentLat').value = lat.toFixed(8);
                    document.getElementById('currentLng').value = lng.toFixed(8);
                });
                
                searchResults.appendChild(item);
            });
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResults.innerHTML = `
                <div class="search-loading" style="color: #dc3545;">
                    <i class="fas fa-exclamation-triangle"></i> Search temporarily unavailable
                    <br><small style="color: #6c757d; margin-top: 0.5rem; display: block;">
                        💡 Click directly on the map to add fishing spots
                    </small>
                </div>
            `;
        });
    }, 500);
});

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
    }
});

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
        <td><strong>Spot ${spot.number}</strong></td>
        <td>${spot.lat}</td>
        <td>${spot.lng}</td>
        <td><span class="badge badge-success">Available</span></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeSpot(${spot.id})" title="Delete Spot">
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
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; color: #6c757d; padding: 2rem;">
                    <i class="fas fa-map-marker-alt" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                    No spots added yet. Click on the map to add spots.
                </td>
            </tr>
        `;
    }
    
    // Renumber remaining spots
    renumberSpots();
    updateSpotCount();
}

// Renumber spots sequentially
function renumberSpots() {
    spots.forEach((spot, index) => {
        spot.number = index + 1;
        const row = document.getElementById(`spot-row-${spot.id}`);
        if (row) {
            row.cells[0].innerHTML = `<strong>Spot ${spot.number}</strong>`;
        }
        spot.marker.setPopupContent(`<b>Spot ${spot.number}</b><br>Lat: ${spot.lat}<br>Lng: ${spot.lng}`);
    });
}

// Click event - add new spot
map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(8);
    const lng = e.latlng.lng.toFixed(8);
    
    spotCounter++;
    const spotId = spotCounter;
    const spotNumber = spots.length + 1;
    
    // Create marker with custom icon
    const marker = L.marker([lat, lng], {
        draggable: true,
        title: `Spot ${spotNumber}`,
        icon: L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: #007bff; width: 32px; height: 32px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                    <span style="transform: rotate(45deg); color: white; font-weight: bold; font-size: 14px;">${spotNumber}</span>
                   </div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        })
    }).addTo(map);
    
    // CHANGED: Removed .openPopup() - popup will only show when user clicks the marker
    marker.bindPopup(`<b>Spot ${spotNumber}</b><br>Lat: ${lat}<br>Lng: ${lng}`);
    
    // Store spot
    const spotData = {
        id: spotId,
        number: spotNumber,
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
            
            // Update coordinates display
            document.getElementById('currentLat').value = newLat;
            document.getElementById('currentLng').value = newLng;
        }
        
        marker.setPopupContent(`<b>Spot ${spot.number}</b><br>Lat: ${newLat}<br>Lng: ${newLng}`);
    });
    
    // Click event - show coordinates and popup
    marker.on('click', function() {
        document.getElementById('currentLat').value = spotData.lat;
        document.getElementById('currentLng').value = spotData.lng;
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
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; color: #6c757d; padding: 2rem;">
                    <i class="fas fa-map-marker-alt" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                    No spots added yet. Click on the map to add spots.
                </td>
            </tr>
        `;
        
        document.getElementById('currentLat').value = '';
        document.getElementById('currentLng').value = '';
        
        updateSpotCount();
    }
}

// Form submission
document.getElementById('createZoneForm').addEventListener('submit', function(e) {
    if (spots.length === 0) {
        e.preventDefault();
        alert('Please add at least one spot by clicking on the map');
        return false;
    }
    
    // Sort spots by number and prepare data
    spots.sort((a, b) => a.number - b.number);
    
    const spotsData = spots.map(spot => ({
        lat: spot.lat,
        lng: spot.lng
    }));
    
    document.getElementById('spotsData').value = JSON.stringify(spotsData);
});

// Add scale control
L.control.scale({
    metric: true,
    imperial: false
}).addTo(map);
</script>

<?php include '../includes/footer.php'; ?>