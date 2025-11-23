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

// Use spot_number for display
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

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="form-container">
    <h2 class="form-header-title">
        <i class="fas fa-edit"></i> Edit Spot #<?php echo $spot_number; ?>
    </h2>
    <p class="form-header-subtitle">Zone: <?php echo htmlspecialchars($spot['zone_name']); ?></p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Spot Information -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-info-circle"></i>
                Spot Information
            </div>
            
            <div class="form-group">
                <label>Spot Status <span class="required">*</span></label>
                <select name="spot_status" class="form-control" required>
                    <option value="available" <?php echo ($spot['spot_status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                    <option value="booked" <?php echo ($spot['spot_status'] == 'booked') ? 'selected' : ''; ?>>Booked</option>
                    <option value="occupied" <?php echo ($spot['spot_status'] == 'occupied') ? 'selected' : ''; ?>>Occupied</option>
                    <option value="maintenance" <?php echo ($spot['spot_status'] == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="cancelled" <?php echo ($spot['spot_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>

        <!-- Map Section -->
        <div class="form-section">
            <div class="section-title-form">
                <i class="fas fa-map-marker-alt"></i>
                Spot Location (Click on Map to Update)
            </div>
            
            <div style="background: var(--color-blue-light); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                <i class="fas fa-info-circle"></i>
                <strong>Click on the map</strong> to update the spot location. You can also drag the marker.
            </div>
            
            <div id="spotMap" style="width: 100%; height: 450px; border-radius: var(--radius-md); border: 3px solid var(--color-blue-primary); box-shadow: var(--shadow-md); margin-bottom: 1rem;"></div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; padding: 1rem; background: var(--color-cream-light); border-radius: var(--radius-md);">
                <div>
                    <label style="font-weight: 600; color: var(--color-blue-primary); display: block; margin-bottom: 0.5rem;">
                        <i class="fas fa-location-arrow"></i> Latitude:
                    </label>
                    <span id="displayLat" style="font-family: 'Courier New', monospace; font-size: 1rem;">
                        <?php echo !empty($spot['latitude']) ? $spot['latitude'] : 'Not set'; ?>
                    </span>
                </div>
                <div>
                    <label style="font-weight: 600; color: var(--color-blue-primary); display: block; margin-bottom: 0.5rem;">
                        <i class="fas fa-location-arrow"></i> Longitude:
                    </label>
                    <span id="displayLng" style="font-family: 'Courier New', monospace; font-size: 1rem;">
                        <?php echo !empty($spot['longitude']) ? $spot['longitude'] : 'Not set'; ?>
                    </span>
                </div>
            </div>
            
            <input type="hidden" name="latitude" id="spotLat" value="<?php echo $spot['latitude'] ?? ''; ?>">
            <input type="hidden" name="longitude" id="spotLng" value="<?php echo $spot['longitude'] ?? ''; ?>">
        </div>

        <!-- Form Actions -->
        <div class="btn-group">
            <a href="viewZone.php?id=<?php echo $spot['zone_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const existingLat = <?php echo !empty($spot['latitude']) ? $spot['latitude'] : '4.2105'; ?>;
const existingLng = <?php echo !empty($spot['longitude']) ? $spot['longitude'] : '101.9758'; ?>;
const hasCoords = <?php echo !empty($spot['latitude']) && !empty($spot['longitude']) ? 'true' : 'false'; ?>;

const map = L.map('spotMap').setView([existingLat, existingLng], hasCoords ? 15 : 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19
}).addTo(map);

let marker = null;

if (hasCoords) {
    marker = L.marker([existingLat, existingLng], { draggable: true }).addTo(map);
    marker.bindPopup('<b>Spot #<?php echo $spot_number; ?></b>').openPopup();
    
    marker.on('dragend', function(e) {
        const lat = e.target.getLatLng().lat.toFixed(8);
        const lng = e.target.getLatLng().lng.toFixed(8);
        
        document.getElementById('spotLat').value = lat;
        document.getElementById('spotLng').value = lng;
        document.getElementById('displayLat').textContent = lat;
        document.getElementById('displayLng').textContent = lng;
    });
}

map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(8);
    const lng = e.latlng.lng.toFixed(8);
    
    if (marker) map.removeLayer(marker);
    
    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    
    document.getElementById('spotLat').value = lat;
    document.getElementById('spotLng').value = lng;
    document.getElementById('displayLat').textContent = lat;
    document.getElementById('displayLng').textContent = lng;
    
    marker.bindPopup('<b>Spot #<?php echo $spot_number; ?></b>').openPopup();
    
    marker.on('dragend', function(e) {
        const newLat = e.target.getLatLng().lat.toFixed(8);
        const newLng = e.target.getLatLng().lng.toFixed(8);
        
        document.getElementById('spotLat').value = newLat;
        document.getElementById('spotLng').value = newLng;
        document.getElementById('displayLat').textContent = newLat;
        document.getElementById('displayLng').textContent = newLng;
    });
});

L.control.scale().addTo(map);
</script>

<?php include '../includes/footer.php'; ?>
