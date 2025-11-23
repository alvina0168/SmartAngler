<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

if (!isset($_POST['tournament_id']) || !isset($_POST['saved_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$tournament_id = intval($_POST['tournament_id']);
$saved_id = intval($_POST['saved_id']);
$user_id = $_SESSION['user_id'];

try {
    $sql = "UPDATE SAVED SET is_saved = FALSE 
            WHERE saved_id = ? AND user_id = ? AND tournament_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $saved_id, $user_id, $tournament_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Tournament removed from saved list'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to remove tournament'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error' => $e->getFile() . ' on line ' . $e->getLine()
    ]);
}
?>