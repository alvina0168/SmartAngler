<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/pages/login.php');
}

// Handle approval/rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $registration_id = sanitize($_GET['id']);
    $action = sanitize($_GET['action']);
    
    if ($action == 'approve') {
        $update_query = "UPDATE TOURNAMENT_REGISTRATION 
                        SET approval_status = 'approved', approved_date = NOW() 
                        WHERE registration_id = '$registration_id'";
        mysqli_query($conn, $update_query);
        $_SESSION['success'] = 'Registration approved successfully';
    } elseif ($action == 'reject') {
        $update_query = "UPDATE TOURNAMENT_REGISTRATION 
                        SET approval_status = 'rejected' 
                        WHERE registration_id = '$registration_id'";
        mysqli_query($conn, $update_query);
        $_SESSION['success'] = 'Registration rejected';
    }
    
    redirect('manage-registrations.php');
}

// Get filter
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';

$page_title = 'Manage Registrations';
include '../includes/header.php';
?>

<div style="min-height: 70vh; padding: 30px 0; background-color: #F5EFE6;">
    <div class="container">
        <h1 style="color: #6D94C5; margin-bottom: 30px;">
            <i class="fas fa-user-check"></i> Manage Tournament Registrations
        </h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <!-- Filter Tabs -->
        <div style="margin-bottom: 30px;">
            <a href="?status=pending" class="btn <?php echo $status_filter == 'pending' ? 'btn-primary' : 'btn-secondary'; ?>" style="margin-right: 10px;">
                Pending
            </a>
            <a href="?status=approved" class="btn <?php echo $status_filter == 'approved' ? 'btn-primary' : 'btn-secondary'; ?>" style="margin-right: 10px;">
                Approved
            </a>
            <a href="?status=rejected" class="btn <?php echo $status_filter == 'rejected' ? 'btn-primary' : 'btn-secondary'; ?>">
                Rejected
            </a>
        </div>
        
        <!-- Registrations Table -->
        <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <?php
            $query = "SELECT tr.*, t.tournament_title, u.full_name, u.email, u.phone_number, fs.spot_id 
                     FROM TOURNAMENT_REGISTRATION tr 
                     JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id 
                     JOIN USER u ON tr.user_id = u.user_id 
                     LEFT JOIN FISHING_SPOT fs ON tr.spot_id = fs.spot_id 
                     WHERE tr.approval_status = '$status_filter' 
                     ORDER BY tr.registration_date DESC";
            $result = mysqli_query($conn, $query);
            
            if (mysqli_num_rows($result) > 0):
            ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Participant</th>
                            <th>Tournament</th>
                            <th>Spot</th>
                            <th>Payment Info</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($reg = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo $reg['registration_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($reg['full_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($reg['email']); ?></small><br>
                                <small><?php echo htmlspecialchars($reg['phone_number']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($reg['tournament_title']); ?></td>
                            <td>Spot #<?php echo $reg['spot_id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($reg['bank_account_name']); ?><br>
                                <small>Acc: <?php echo htmlspecialchars($reg['bank_account_number']); ?></small>
                                <?php if ($reg['payment_proof']): ?>
                                    <br><a href="../assets/images/payments/<?php echo $reg['payment_proof']; ?>" target="_blank" style="color: #6D94C5;">View Proof</a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($reg['registration_date']); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $reg['approval_status'] == 'approved' ? 'success' : 
                                        ($reg['approval_status'] == 'pending' ? 'warning' : 'error'); 
                                ?>"><?php echo strtoupper($reg['approval_status']); ?></span>
                            </td>
                            <td>
                                <?php if ($reg['approval_status'] == 'pending'): ?>
                                    <a href="?action=approve&id=<?php echo $reg['registration_id']; ?>" 
                                       class="btn btn-primary" style="padding: 5px 15px; margin-right: 5px;"
                                       onclick="return confirm('Approve this registration?')">
                                        Approve
                                    </a>
                                    <a href="?action=reject&id=<?php echo $reg['registration_id']; ?>" 
                                       class="btn btn-secondary" style="padding: 5px 15px;"
                                       onclick="return confirm('Reject this registration?')">
                                        Reject
                                    </a>
                                <?php else: ?>
                                    <span style="color: #666;">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No <?php echo $status_filter; ?> registrations found.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>