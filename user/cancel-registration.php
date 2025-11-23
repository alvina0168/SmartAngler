<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

requireLogin();

if (isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Admins cannot cancel registrations']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['registration_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing registration ID']);
    exit;
}

$registration_id = intval($_POST['registration_id']);
$user_id = $_SESSION['user_id'];

try {
    // Get registration details
    $query = "SELECT * FROM TOURNAMENT_REGISTRATION WHERE registration_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $registration_id, $user_id);
    $stmt->execute();
    $registration = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$registration) {
        echo json_encode(['success' => false, 'message' => 'Registration not found']);
        exit;
    }

    // Only allow cancellation of pending registrations
    if ($registration['approval_status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending registrations can be cancelled']);
        exit;
    }

    // Delete registration
    $delete_query = "DELETE FROM TOURNAMENT_REGISTRATION WHERE registration_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $registration_id);
    
    if ($stmt->execute()) {
        // Free up the fishing spot
        if ($registration['spot_id']) {
            $update_spot = "UPDATE FISHING_SPOT SET spot_status = 'available' WHERE spot_id = ?";
            $spot_stmt = $conn->prepare($update_spot);
            $spot_stmt->bind_param("i", $registration['spot_id']);
            $spot_stmt->execute();
            $spot_stmt->close();
        }

        // Delete payment proof file if exists
        if ($registration['payment_proof']) {
            $file_path = '../assets/images/payments/' . $registration['payment_proof'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Registration cancelled successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to cancel registration: ' . $stmt->error
        ]);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>