<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(SITE_URL . '/pages/login.php');
}

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/pages/tournaments.php');
}

$tournament_id = sanitize($_GET['id']);
$user_id = $_SESSION['user_id'];

// Check if tournament exists
$tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = '$tournament_id'";
$tournament_result = mysqli_query($conn, $tournament_query);

if (mysqli_num_rows($tournament_result) == 0) {
    redirect(SITE_URL . '/pages/tournaments.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Check if already registered
$check_query = "SELECT * FROM TOURNAMENT_REGISTRATION 
                WHERE tournament_id = '$tournament_id' AND user_id = '$user_id'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) > 0) {
    $_SESSION['error'] = 'You have already registered for this tournament';
    redirect(SITE_URL . '/pages/tournament-details.php?id=' . $tournament_id);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $spot_id = sanitize($_POST['spot_id']);
    $bank_account_name = sanitize($_POST['bank_account_name']);
    $bank_account_number = sanitize($_POST['bank_account_number']);
    
    // Handle payment proof upload
    $payment_proof = '';
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
        $payment_proof = uploadFile($_FILES['payment_proof'], 'payments');
    }
    
    if (empty($spot_id) || empty($bank_account_name)) {
        $error = 'Please fill in all required fields';
    } else {
        // Insert registration
        $insert_query = "INSERT INTO TOURNAMENT_REGISTRATION 
                        (tournament_id, user_id, spot_id, payment_proof, bank_account_name, bank_account_number, 
                         qr_code_image, approval_status) 
                        VALUES 
                        ('$tournament_id', '$user_id', '$spot_id', '$payment_proof', '$bank_account_name', 
                         '$bank_account_number', 'maybank_qr.png', 'pending')";
        
        if (mysqli_query($conn, $insert_query)) {
            // Update spot status
            $update_spot = "UPDATE FISHING_SPOT SET spot_status = 'booked' WHERE spot_id = '$spot_id'";
            mysqli_query($conn, $update_spot);
            
            // Add booking
            $booking_query = "INSERT INTO SPOT_BOOKING (spot_id, user_id, booking_status) 
                             VALUES ('$spot_id', '$user_id', 'reserved')";
            mysqli_query($conn, $booking_query);
            
            $success = 'Registration submitted successfully! Please wait for admin approval.';
        } else {
            $error = 'Registration failed. Please try again.';
        }
    }
}

// Get available spots
$spots_query = "SELECT * FROM FISHING_SPOT 
                WHERE tournament_id = '$tournament_id' AND spot_status = 'available'";
$spots_result = mysqli_query($conn, $spots_query);

$page_title = 'Register for Tournament';
include '../includes/header.php';
?>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="container">
        <div style="max-width: 700px; margin: 0 auto; background: white; border-radius: 10px; padding: 40px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
            <h1 style="text-align: center; color: #6D94C5; margin-bottom: 30px;">
                <i class="fas fa-user-plus"></i> Register for Tournament
            </h1>
            
            <div style="background: #E8DFCA; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
                <h3 style="color: #6D94C5; margin-bottom: 10px;"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h3>
                <p><strong>Date:</strong> <?php echo formatDate($tournament['tournament_date']); ?></p>
                <p><strong>Fee:</strong> RM <?php echo number_format($tournament['tournament_fee'], 2); ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <a href="../user/dashboard.php" style="color: #155724; font-weight: 600;">Go to Dashboard</a>
                </div>
            <?php else: ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Select Fishing Spot *</label>
                    <select name="spot_id" class="form-control" required>
                        <option value="">-- Select a Spot --</option>
                        <?php while ($spot = mysqli_fetch_assoc($spots_result)): ?>
                            <option value="<?php echo $spot['spot_id']; ?>">
                                Spot #<?php echo $spot['spot_id']; ?> 
                                (Lat: <?php echo $spot['latitude']; ?>, Long: <?php echo $spot['longitude']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Bank Account Name *</label>
                    <input type="text" name="bank_account_name" class="form-control" placeholder="e.g., Maybank - SmartAngler" required>
                </div>
                
                <div class="form-group">
                    <label>Bank Account Number *</label>
                    <input type="text" name="bank_account_number" class="form-control" placeholder="Enter bank account number" required>
                </div>
                
                <div class="form-group">
                    <label>Payment Proof (Optional)</label>
                    <input type="file" name="payment_proof" class="form-control" accept="image/*">
                    <small style="color: #666;">Upload your payment receipt if you have already paid</small>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-check"></i> Submit Registration
                </button>
            </form>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>