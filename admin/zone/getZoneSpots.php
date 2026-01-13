<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

$zone_id = intval($_GET['zone_id'] ?? 0);

if ($zone_id) {
    $query = "SELECT spot_id, spot_number, latitude, longitude, spot_status 
              FROM FISHING_SPOT 
              WHERE zone_id = $zone_id 
              ORDER BY spot_number ASC";
    
    $result = mysqli_query($conn, $query);
    $spots = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $spots[] = $row;
    }
    
    echo json_encode($spots);
} else {
    echo json_encode([]);
}