<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/zone/zoneList.php');
}

$zone_id = intval($_GET['id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['new_spots'])) {
    $new_spots = json_decode($_POST['new_spots'], true);

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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
.leaflet-control-layers {
    border: 2px solid rgba(0,0,0,0.2);
    border-radius: 8px;
}

.leaflet-bar {
    border-radius: 8px;
}

.custom-marker {
    background: transparent;
}

.marker-available {
    background: #28a745;
}

.marker-occupied {
    background: #007bff;
}

.marker-maintenance {
    background: #17a2b8;
}

.marker-new {
    background: #ff6b6b;
}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <div>
        <a href="zoneList.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Zones
        </a>
    </div>
    <div>
        <a href="editZone.php?id=<?php echo $zone_id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Zone
        </a>
    </div>
</div>

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

<div class="dashboard-stats">
    <?php 
    $stat_colors = ['total'=>'blue','available'=>'success','occupied'=>'primary','maintenance'=>'info'];
    foreach($stat_colors as $key=>$color):
    ?>
    <div class="stat-card <?php echo $color==='blue'?'':$color; ?>">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo $stats[$key]; ?></div>
                <div class="stat-label"><?php echo ucfirst($key); ?></div>
            </div>
            <div class="stat-icon">
                <?php
                    switch($key){
                        case 'total': echo '<i class="fas fa-map-pin"></i>'; break;
                        case 'available': echo '<i class="fas fa-check-circle"></i>'; break;
                        case 'occupied': echo '<i class="fas fa-users"></i>'; break;
                        case 'maintenance': echo '<i class="fas fa-tools"></i>'; break;
                    }
                ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-map"></i> Spots Map View
        </h2>
    </div>
    <div id="spotsMap" style="width:100%; height:650px; border-radius: var(--radius-md); border:3px solid var(--color-blue-primary); box-shadow: var(--shadow-md);"></div>
</div>

<div class="section">
    <h3>
        <i class="fas fa-plus-circle"></i> Add New Fishing Spots
    </h3>
    <form method="POST" id="addSpotsForm">
        <p style="margin-bottom:1rem;">Click on the map to add new spots. Drag markers to adjust coordinates.</p>
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

<div class="section">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2 class="section-title"><i class="fas fa-list"></i> All Spots (<?php echo $stats['total']; ?>)</h2>
        <button id="deleteSelectedBtn" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Selected</button>
    </div>

    <?php if(mysqli_num_rows($spots_result) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px; text-align:center;"><input type="checkbox" id="selectAllSpots"></th>
                    <th>Spot Number</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Status</th>
                    <th>Booked By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                mysqli_data_seek($spots_result,0);
                while($spot = mysqli_fetch_assoc($spots_result)):
                ?>
                <tr id="spotRow<?php echo $spot['spot_id']; ?>">
                    <td style="text-align:center;">
                        <input type="checkbox" class="selectSpot" data-id="<?php echo $spot['spot_id']; ?>">
                    </td>
                    <td><strong>#<?php echo $spot['spot_number']; ?></strong></td>
                    <td style="font-family:'Courier New', monospace;"><?php echo $spot['latitude'] ?: '-'; ?></td>
                    <td style="font-family:'Courier New', monospace;"><?php echo $spot['longitude'] ?: '-'; ?></td>
                    <td>
                        <span class="badge badge-<?php 
                            switch($spot['spot_status']){
                                case 'available': echo 'success'; break;
                                case 'occupied': echo 'primary'; break;
                                case 'maintenance': echo 'info'; break;
                                default: echo 'secondary';
                            }
                        ?>"><?php echo strtoupper($spot['spot_status']); ?></span>
                    </td>
                    <td><?php echo $spot['booked_by'] ?: '-'; ?></td>
                    <td>
                        <div class="action-btns">
                            <a href="editSpot.php?id=<?php echo $spot['spot_id']; ?>" class="btn btn-success btn-sm"><i class="fas fa-edit"></i></a>
                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteSpotSingle(<?php echo $spot['spot_id']; ?>)"><i class="fas fa-trash"></i></button>
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
            <p>No fishing spots have been added yet</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const spotsMap = L.map('spotsMap').setView([5.4164, 116.0553], 10);
const googleStreets = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    attribution: '© Google Maps'
});

const googleSatellite = L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    attribution: '© Google Maps'
});

const googleHybrid = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    attribution: '© Google Maps'
});

const googleTerrain = L.tileLayer('https://{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    attribution: '© Google Maps'
});

googleHybrid.addTo(spotsMap);

// Layer Control
const baseLayers = {
    "🌍 Hybrid (Recommended)": googleHybrid,
    "🛰️ Satellite": googleSatellite,
    "🗺️ Street Map": googleStreets,
    "🏔️ Terrain": googleTerrain
};

L.control.layers(baseLayers, null, {
    position: 'topright'
}).addTo(spotsMap);

L.control.scale({
    metric: true,
    imperial: false
}).addTo(spotsMap);

let spotMarkers = [];
const existingSpots = [
<?php mysqli_data_seek($spots_result,0); while($spot = mysqli_fetch_assoc($spots_result)):
if(!empty($spot['latitude']) && !empty($spot['longitude'])): ?>
{spot_id:<?php echo $spot['spot_id']; ?>, number:<?php echo $spot['spot_number']; ?>, lat:<?php echo $spot['latitude']; ?>, lng:<?php echo $spot['longitude']; ?>, status:'<?php echo $spot['spot_status']; ?>'},
<?php endif; endwhile; ?>
];

existingSpots.forEach(s=>{
    let markerColor = '#28a745'; 
    if (s.status === 'occupied') markerColor = '#007bff'; 
    if (s.status === 'maintenance') markerColor = '#17a2b8'; 
    
    const marker = L.marker([s.lat, s.lng], {
        icon: L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: ${markerColor}; width: 32px; height: 32px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                    <span style="transform: rotate(45deg); color: white; font-weight: bold; font-size: 12px;">${s.number}</span>
                   </div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        })
    }).addTo(spotsMap);
    
    marker.bindPopup(`<b>Spot #${s.number}</b><br>Status: <strong>${s.status}</strong><br>Lat: ${s.lat}<br>Lng: ${s.lng}`);
    spotMarkers.push({spot_id:s.spot_id, leafletMarker:marker});
});

if(existingSpots.length>0){
    const bounds = L.latLngBounds(existingSpots.map(s=>[s.lat,s.lng]));
    spotsMap.fitBounds(bounds,{padding:[50,50]});
}

let newSpots=[];
let maxSpotNumber = <?php
$max_result = mysqli_query($conn,"SELECT MAX(spot_number) AS max_number FROM FISHING_SPOT WHERE zone_id='$zone_id'");
$max_row=mysqli_fetch_assoc($max_result);
echo $max_row['max_number'] ?? 0;
?>;

const addSpotsForm = document.getElementById('addSpotsForm');
const newSpotsInput = document.getElementById('newSpotsInput');
const newSpotsTable = document.getElementById('newSpotsTable');
const addSpotsBtn = document.getElementById('addSpotsBtn');

spotsMap.on('click', function(e){
    const lat = e.latlng.lat.toFixed(8);
    const lng = e.latlng.lng.toFixed(8);

    maxSpotNumber++;
    
    // Custom red marker for new spots
    const marker = L.marker([lat, lng], {
        draggable: true,
        icon: L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: #ff6b6b; width: 32px; height: 32px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                    <span style="transform: rotate(45deg); color: white; font-weight: bold; font-size: 12px;">${maxSpotNumber}</span>
                   </div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        })
    }).addTo(spotsMap);
    
    marker.bindPopup(`<b>New Spot #${maxSpotNumber}</b><br><small style="color: #ff6b6b;">Drag to adjust position</small>`).openPopup();

    const spot={lat,lng,marker,spot_number:maxSpotNumber};
    newSpots.push(spot);
    updateNewSpotsTable();

    marker.on('dragend', function(ev){
        const idx=newSpots.findIndex(s=>s.marker===marker);
        newSpots[idx].lat=ev.target.getLatLng().lat.toFixed(8);
        newSpots[idx].lng=ev.target.getLatLng().lng.toFixed(8);
        updateNewSpotsTable();
        marker.setPopupContent(`<b>New Spot #${newSpots[idx].spot_number}</b><br>Lat: ${newSpots[idx].lat}<br>Lng: ${newSpots[idx].lng}`);
    });
});

function updateNewSpotsTable(){
    newSpotsTable.innerHTML='';
    newSpots.forEach(s=>{
        const row=document.createElement('tr');
        row.innerHTML=`<td><strong>#${s.spot_number}</strong></td><td>${s.lat}</td><td>${s.lng}</td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeNewSpot(${s.spot_number})"><i class="fas fa-trash"></i></button></td>`;
        newSpotsTable.appendChild(row);
    });
    if(newSpots.length===0){
        newSpotsTable.innerHTML='<tr><td colspan="4" style="text-align:center;color:var(--color-gray-600);">Click on the map to add spots</td></tr>';
    }
    newSpotsInput.value=JSON.stringify(newSpots.map(s=>({lat:s.lat,lng:s.lng,spot_number:s.spot_number})));
    addSpotsBtn.disabled=newSpots.length===0;
}

function removeNewSpot(number){
    const idx=newSpots.findIndex(s=>s.spot_number===number);
    if(idx!==-1){
        spotsMap.removeLayer(newSpots[idx].marker);
        newSpots.splice(idx,1);
        updateNewSpotsTable();
    }
}

function deleteSpotSingle(id){
    if(confirm('Delete this spot?')){
        fetch(`deleteSpot.php?id=${id}&ajax=1`)
        .then(res=>res.json())
        .then(data=>{
            if(data.success){
                document.getElementById(`spotRow${id}`).remove();
                const markerObj=spotMarkers.find(m=>m.spot_id==id);
                if(markerObj){
                    spotsMap.removeLayer(markerObj.leafletMarker);
                    spotMarkers=spotMarkers.filter(m=>m.spot_id!=id);
                }
            } else alert(data.message);
        });
    }
}

document.getElementById('deleteSelectedBtn').addEventListener('click', async function(){
    const selected=Array.from(document.querySelectorAll('.selectSpot:checked')).map(cb=>cb.dataset.id);
    if(selected.length===0){ alert('No spots selected'); return; }
    if(confirm(`Delete ${selected.length} spots?`)){
        const promises=selected.map(id=>fetch(`deleteSpot.php?id=${id}&ajax=1`).then(r=>r.json()));
        const results=await Promise.all(promises);
        results.forEach((data,index)=>{
            const id=selected[index];
            if(data.success){
                const row=document.getElementById(`spotRow${id}`);
                if(row) row.remove();
                const markerObj=spotMarkers.find(m=>m.spot_id==id);
                if(markerObj){
                    spotsMap.removeLayer(markerObj.leafletMarker);
                    spotMarkers=spotMarkers.filter(m=>m.spot_id!=id);
                }
            } else alert(`Failed to delete spot #${id}: ${data.message}`);
        });
        document.getElementById('selectAllSpots').checked=false;
    }
});

const selectAllCheckbox=document.getElementById('selectAllSpots');
selectAllCheckbox.addEventListener('change',function(){
    document.querySelectorAll('.selectSpot').forEach(cb=>cb.checked=this.checked);
});
document.querySelectorAll('.selectSpot').forEach(cb=>{
    cb.addEventListener('change',function(){
        if(!this.checked) selectAllCheckbox.checked=false;
        else if(document.querySelectorAll('.selectSpot:checked').length===document.querySelectorAll('.selectSpot').length)
            selectAllCheckbox.checked=true;
    });
});
</script>

<?php include '../includes/footer.php'; ?>