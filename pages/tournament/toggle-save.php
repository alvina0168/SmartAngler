<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to save tournaments']);
    exit;
}

if (!isset($_POST['tournament_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$tournament_id = intval($_POST['tournament_id']);
$action = $_POST['action'];
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'save') {
        $sql = "INSERT INTO SAVED (user_id, tournament_id, is_saved) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE is_saved = 1";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $user_id, $tournament_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Tournament saved successfully',
            'action' => 'saved'
        ]);

    } elseif ($action === 'unsave') {
        $sql = "UPDATE SAVED SET is_saved = 0 WHERE user_id = ? AND tournament_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $user_id, $tournament_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Tournament removed from saved',
            'action' => 'unsaved'
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>