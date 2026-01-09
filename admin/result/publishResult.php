<?php
session_start();
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
} else {
    $tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
}

if ($tournament_id <= 0) {
    $_SESSION['error'] = "Invalid tournament ID.";
    header("Location: resultList.php");
    exit();
}

// Get tournament details
$query = "SELECT * FROM TOURNAMENT WHERE tournament_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found.";
    header("Location: resultList.php");
    exit();
}

try {
    $conn->beginTransaction();
    
    // Update all results for this tournament to 'final' status
    $query = "UPDATE RESULT SET result_status = 'final' WHERE tournament_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tournament_id]);
    
    $updated_count = $stmt->rowCount();
    
    if ($updated_count > 0) {
        // Update tournament status to completed if not already
        if ($tournament['status'] !== 'completed') {
            $query = "UPDATE TOURNAMENT SET status = 'completed' WHERE tournament_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$tournament_id]);
        }
        
        // Get all participants who should receive notification
        $query = "SELECT DISTINCT u.user_id, u.email, u.full_name
                  FROM USER u
                  JOIN TOURNAMENT_REGISTRATION tr ON u.user_id = tr.user_id
                  WHERE tr.tournament_id = ? AND tr.approval_status = 'approved'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$tournament_id]);
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Send notification to all participants
        $notification_title = "Official Results Published!";
        $notification_message = "The official results for '{$tournament['tournament_title']}' have been published. Check your ranking and prizes!";
        
        foreach ($participants as $participant) {
            $query = "INSERT INTO NOTIFICATION (user_id, tournament_id, title, message, sent_date, read_status)
                     VALUES (?, ?, ?, ?, NOW(), 0)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                $participant['user_id'],
                $tournament_id,
                $notification_title,
                $notification_message
            ]);
        }
        
        $conn->commit();
        $_SESSION['success'] = "Results published successfully! Notifications sent to " . count($participants) . " participants.";
    } else {
        $conn->rollBack();
        $_SESSION['warning'] = "No results found to publish. Please calculate results first.";
    }
    
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error'] = "Error publishing results: " . $e->getMessage();
}

header("Location: viewResult.php?tournament_id=" . $tournament_id);
exit();
?>