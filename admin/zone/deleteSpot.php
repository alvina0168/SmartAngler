<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_GET['id'])){
    echo json_encode(['success'=>false,'message'=>'Spot ID required']);
    exit;
}

$spot_id=intval($_GET['id']);
$delete_query="DELETE FROM FISHING_SPOT WHERE spot_id='$spot_id'";

if(mysqli_query($conn,$delete_query)){
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
}
