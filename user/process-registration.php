<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$tournament_id = intval($_POST['tournament_id']);
$user_id = $_SESSION['user_id'];

// Validate inputs
$full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
$emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact']);
$spot_id = intval($_POST['spot_id']);

// Check if already registered
$check_query = "SELECT registration_id FROM TOURNAMENT_REGISTRATION 
                WHERE tournament_id = ? AND user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $tournament_id, $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $_SESSION['error'] = 'You have already registered for this tournament!';
    redirect(SITE_URL . '/pages/tournament/tournament-details.php?id=' . $tournament_id);
}

// Handle payment proof upload
$payment_proof = null;
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $file_type = $_FILES['payment_proof']['type'];
    
    if (in_array($file_type, $allowed_types)) {
        $upload_dir = '../assets/images/payments/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $payment_proof = 'payment_' . $user_id . '_' . $tournament_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $payment_proof;
        
        if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_path)) {
            $_SESSION['error'] = 'Failed to upload payment proof. Please try again.';
            redirect(SITE_URL . '/user/register-tournament.php?id=' . $tournament_id);
        }
    } else {
        $_SESSION['error'] = 'Invalid file type. Please upload an image or PDF.';
        redirect(SITE_URL . '/user/register-tournament.php?id=' . $tournament_id);
    }
}

// Insert registration
$insert_query = "INSERT INTO TOURNAMENT_REGISTRATION 
    (tournament_id, user_id, spot_id, payment_proof, approval_status, registration_date) 
    VALUES (?, ?, ?, ?, 'pending', NOW())";

$stmt = $conn->prepare($insert_query);
$stmt->bind_param("iiis", 
    $tournament_id, 
    $user_id, 
    $spot_id, 
    $payment_proof
);


if ($stmt->execute()) {
    $registration_id = $stmt->insert_id;
    
    // Update fishing spot status to reserved
    $update_spot = "UPDATE FISHING_SPOT SET spot_status = 'reserved' WHERE spot_id = ?";
    $spot_stmt = $conn->prepare($update_spot);
    $spot_stmt->bind_param("i", $spot_id);
    $spot_stmt->execute();
    $spot_stmt->close();
    
    $_SESSION['success'] = 'Registration submitted successfully! Your registration is pending approval.';
    redirect(SITE_URL . '/user/registration-details.php?id=' . $registration_id);
} else {
    $_SESSION['error'] = 'Registration failed: ' . $stmt->error;
    redirect(SITE_URL . '/user/register-tournament.php?id=' . $tournament_id);
}

$stmt->close();
?>