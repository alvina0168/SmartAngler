<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$spot_id = intval($_GET['id']);

// Get spot info
$spot_query = "SELECT fs.*, z.zone_name, z.zone_id 
               FROM FISHING_SPOT fs
               JOIN ZONE z ON fs.zone_id = z.zone_id
               WHERE fs.spot_id = '$spot_id'";
$spot_result = mysqli_query($conn, $spot_query);

if (!$spot_result || mysqli_num_rows($spot_result) == 0) {
    $_SESSION['error'] = 'Spot not found';
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$spot = mysqli_fetch_assoc($spot_result);
$spot_number = $spot['spot_number'];

$page_title = 'Edit Spot #' . $spot_number;
$page_description = 'Update spot information';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $latitude = sanitize($_POST['latitude'] ?? '');
    $longitude = sanitize($_POST['longitude'] ?? '');
    $spot_status = sanitize($_POST['spot_status']);

    $update_query = "UPDATE FISHING_SPOT SET 
                     latitude = " . ($latitude ? "'$latitude'" : "NULL") . ",
                     longitude = " . ($longitude ? "'$longitude'" : "NULL") . ",
                     spot_status = '$spot_status'
                     WHERE spot_id = '$spot_id'";

    if (mysqli_query($conn, $update_query)) {
        $_SESSION['success'] = 'Spot updated successfully!';
        redirect(SITE_URL . '/admin/zone/viewZone.php?id=' . $spot['zone_id']);
    } else {
        $error = 'Failed to update spot: ' . mysqli_error($conn);
    }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
#spotMap {
    width: 100%;
    height: 550px;
    border-radius: 0.5rem;
    border: 2px solid var(--color-blue-primary);
    margin-bottom: 1rem;
}

.leaflet-control-layers {
    border: 2px solid rgba(0,0,0,0.2);
    border-radius: 8px;
}

.leaflet-bar {
    border-radius: 8px;
}

.coordinate-display {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    padding: 1rem;
    background: #eff6ff;
    border-radius: 0.5rem;
    border: 1px solid #bfdbfe;
}

.coordinate-item {
    display: flex;
    flex-direction: column;
}

.coordinate-label {
    font-weight: 600;
    color: #1e40af;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.coordinate-value {
    font-family: 'Courier New', monospace;
    color: #1f2937;
    font-size: 0.9rem;
    padding: 0.5rem;
    background: white;
    border-radius: 0.375rem;
    border: 1px solid #d1d5db;
}
</style>

<div class="section">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-edit"></i> Edit Spot #<?php echo $spot_number; ?></h2>
        <p class="section-subtitle">Zone: <?php echo htmlspecialchars($spot['zone_name']); ?></p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card p-6 shadow-md rounded-lg">
        <!-- Spot Information -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-info-circle"></i> Spot Information</h3>
            <div class="form-group">
                <label>Spot Status <span class="required">*</span></label>
                <select name="spot_status" class="form-control rounded-md border-gray-300 focus:ring-2 focus:ring-blue-400" required>
                    <option value="available" <?php echo ($spot['spot_status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                    <option value="booked" <?php echo ($spot['spot_status'] == 'booked') ? 'selected' : ''; ?>>Booked</option>
                    <option value="maintenance" <?php echo ($spot['spot_status'] == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="cancelled" <?php echo ($spot['spot_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>

        <!-- Map Section -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-map-marker-alt"></i> Spot Location</h3>
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle"></i> Click on the map or drag the marker to update the spot location. Use the layer control to switch between map views.
            </div>
            <div id="spotMap"></div>

            <div class="coordinate-display">
                <div class="coordinate-item">
                    <label class="coordinate-label"><i class="fas fa-location-arrow"></i> Latitude</label>
                    <span id="displayLat" class="coordinate-value"><?php echo !empty($spot['latitude']) ? $spot['latitude'] : 'Not set'; ?></span>
                </div>
                <div class="coordinate-item">
                    <label class="coordinate-label"><i class="fas fa-location-arrow"></i> Longitude</label>
                    <span id="displayLng" class="coordinate-value"><?php echo !empty($spot['longitude']) ? $spot['longitude'] : 'Not set'; ?></span>
                </div>
            </div>

            <input type="hidden" name="latitude" id="spotLat" value="<?php echo $spot['latitude'] ?? ''; ?>">
            <input type="hidden" name="longitude" id="spotLng" value="<?php echo $spot['longitude'] ?? ''; ?>">
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-3">
            <a href="viewZone.php?id=<?php echo $spot['zone_id']; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const existingLat = <?php echo !empty($spot['latitude']) ? $spot['latitude'] : '5.4164'; ?>;
const existingLng = <?php echo !empty($spot['longitude']) ? $spot['longitude'] : '116.0553'; ?>;
const hasCoords = <?php echo !empty($spot['latitude']) && !empty($spot['longitude']) ? 'true' : 'false'; ?>;

// Initialize map with Google Maps tiles
const map = L.map('spotMap').setView([existingLat, existingLng], hasCoords ? 18 : 10);

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

// Add Google Hybrid as default (best for precise location editing)
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

// Add scale control
L.control.scale({
    metric: true,
    imperial: false
}).addTo(map);

// Create marker with custom styling
let marker = null;
if (hasCoords) {
    marker = L.marker([existingLat, existingLng], { 
        draggable: true,
        icon: L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: #007bff; width: 36px; height: 36px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                    <span style="transform: rotate(45deg); color: white; font-weight: bold; font-size: 14px;"><?php echo $spot_number; ?></span>
                   </div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36]
        })
    }).addTo(map);
    
    marker.bindPopup('<b>Spot #<?php echo $spot_number; ?></b><br><small>Drag to adjust position</small>').openPopup();

    marker.on('dragend', function(e) {
        const lat = e.target.getLatLng().lat.toFixed(8);
        const lng = e.target.getLatLng().lng.toFixed(8);
        document.getElementById('spotLat').value = lat;
        document.getElementById('spotLng').value = lng;
        document.getElementById('displayLat').textContent = lat;
        document.getElementById('displayLng').textContent = lng;
        marker.setPopupContent(`<b>Spot #<?php echo $spot_number; ?></b><br>Lat: ${lat}<br>Lng: ${lng}`);
    });
}

map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(8);
    const lng = e.latlng.lng.toFixed(8);
    
    if(marker) map.removeLayer(marker);
    
    marker = L.marker([lat, lng], { 
        draggable: true,
        icon: L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: #007bff; width: 36px; height: 36px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                    <span style="transform: rotate(45deg); color: white; font-weight: bold; font-size: 14px;"><?php echo $spot_number; ?></span>
                   </div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36]
        })
    }).addTo(map);
    
    document.getElementById('spotLat').value = lat;
    document.getElementById('spotLng').value = lng;
    document.getElementById('displayLat').textContent = lat;
    document.getElementById('displayLng').textContent = lng;

    marker.bindPopup(`<b>Spot #<?php echo $spot_number; ?></b><br>Lat: ${lat}<br>Lng: ${lng}`).openPopup();
    
    marker.on('dragend', function(e) {
        const newLat = e.target.getLatLng().lat.toFixed(8);
        const newLng = e.target.getLatLng().lng.toFixed(8);
        document.getElementById('spotLat').value = newLat;
        document.getElementById('spotLng').value = newLng;
        document.getElementById('displayLat').textContent = newLat;
        document.getElementById('displayLng').textContent = newLng;
        marker.setPopupContent(`<b>Spot #<?php echo $spot_number; ?></b><br>Lat: ${newLat}<br>Lng: ${newLng}`);
    });
});
</script>

<?php include '../includes/footer.php'; ?>