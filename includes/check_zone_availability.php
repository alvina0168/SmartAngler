<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tournament_date = mysqli_real_escape_string($conn, $_POST['tournament_date'] ?? '');
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time'] ?? '');
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time'] ?? '');
    
    if (empty($tournament_date) || empty($start_time) || empty($end_time)) {
        echo json_encode(['zones' => [], 'error' => 'Missing parameters']);
        exit;
    }
    
    $query = "
        SELECT z.zone_id, z.zone_name, z.zone_description,
               (SELECT COUNT(*) FROM FISHING_SPOT WHERE zone_id = z.zone_id) as spot_count
        FROM ZONE z
        WHERE (z.tournament_id IS NULL OR z.tournament_id = 0)
        AND z.zone_id NOT IN (
            SELECT DISTINCT z2.zone_id
            FROM ZONE z2
            INNER JOIN TOURNAMENT t ON z2.tournament_id = t.tournament_id
            WHERE t.tournament_date = '$tournament_date'
            AND t.status != 'cancelled'
            AND (
                (TIME('$start_time') < TIME(t.end_time) AND TIME('$end_time') > TIME(t.start_time))
            )
        )
        ORDER BY z.zone_name
        LIMIT 50
    ";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo json_encode(['zones' => [], 'error' => mysqli_error($conn)]);
        exit;
    }
    
    $zones = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $zones[] = [
            'zone_id' => $row['zone_id'],
            'zone_name' => $row['zone_name'],
            'zone_description' => $row['zone_description'] ?? '',
            'spot_count' => $row['spot_count']
        ];
    }
    
    echo json_encode(['zones' => $zones]);
    exit;
}

echo json_encode(['zones' => [], 'error' => 'Invalid request method']);
?>