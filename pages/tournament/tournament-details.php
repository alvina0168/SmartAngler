<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Require login
requireLogin();

// Admins shouldn't access this page
if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$tournament_id = intval($_GET['id']);

// Auto-update tournament status
$update_query = "
    UPDATE TOURNAMENT
    SET status = CASE
        WHEN NOW() < CONCAT(tournament_date, ' ', start_time) THEN 'upcoming'
        WHEN NOW() BETWEEN CONCAT(tournament_date, ' ', start_time) AND CONCAT(tournament_date, ' ', end_time) THEN 'ongoing'
        WHEN NOW() > CONCAT(tournament_date, ' ', end_time) THEN 'completed'
        ELSE status
    END
    WHERE tournament_id = ? AND status != 'cancelled'
";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$stmt->close();

// Get tournament details with save and registration status
$query = "SELECT t.*, u.full_name as organizer_name, u.email as organizer_email, u.phone_number as organizer_phone,
          (SELECT COUNT(*) FROM SAVED 
           WHERE tournament_id = t.tournament_id 
           AND user_id = ? 
           AND is_saved = 1) as is_saved,
          (SELECT registration_id FROM TOURNAMENT_REGISTRATION 
           WHERE tournament_id = t.tournament_id 
           AND user_id = ? 
           AND approval_status IN ('pending', 'approved', 'rejected')
           LIMIT 1) as user_registration_id,
          (SELECT approval_status FROM TOURNAMENT_REGISTRATION 
           WHERE tournament_id = t.tournament_id 
           AND user_id = ? 
           AND approval_status IN ('pending', 'approved', 'rejected')
           LIMIT 1) as user_registration_status
          FROM TOURNAMENT t 
          LEFT JOIN USER u ON t.user_id = u.user_id 
          WHERE t.tournament_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$tournament = $result->fetch_assoc();
$stmt->close();

if (!$tournament) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

// Get available spots count
$spots_query = "
    SELECT 
        COUNT(*) AS total_spots,
        SUM(CASE WHEN fs.spot_status = 'available' THEN 1 ELSE 0 END) AS available_spots
    FROM FISHING_SPOT fs
    JOIN ZONE z ON fs.zone_id = z.zone_id
    WHERE z.tournament_id = ?
";
$stmt = $conn->prepare($spots_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$spots_data = $result->fetch_assoc();
$stmt->close();

// Get registered participants count
$participants_query = "SELECT COUNT(*) as registered 
                       FROM TOURNAMENT_REGISTRATION 
                       WHERE tournament_id = ? AND approval_status IN ('pending', 'approved')";
$stmt = $conn->prepare($participants_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$participants_data = $result->fetch_assoc();
$stmt->close();

// Check if spots are full
$spotsAvailable = $tournament['max_participants'] - $participants_data['registered'];
$isFull = $spotsAvailable <= 0;

$page_title = $tournament['tournament_title'];
include '../../includes/header.php';
?>

<style>
/* Tournament Status Badges */
.badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-upcoming {
    background: #3498DB;
    color: white;
}

.badge-ongoing {
    background: #F1C40F;
    color: #333;
}

.badge-completed {
    background: #2ECC71;
    color: white;
}

.badge-cancelled {
    background: #E74C3C;
    color: white;
}

/* Save Button */
.save-details-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: white;
    color: #6D94C5;
    border: 2px solid #6D94C5;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.save-details-btn:hover {
    background: #6D94C5;
    color: white;
}

.save-details-btn.saved {
    background: #FEF5E7;
    border-color: #F39C12;
    color: #F39C12;
}

.save-details-btn.saved:hover {
    background: #F39C12;
    color: white;
}

/* Status Messages */
.status-message {
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    margin-bottom: 15px;
}

.status-message.pending {
    background: #FFF3CD;
    color: #856404;
    border: 2px solid #856404;
}

.status-message.approved {
    background: #D4EDDA;
    color: #155724;
    border: 2px solid #155724;
}

.status-message.rejected {
    background: #F8D7DA;
    color: #721C24;
    border: 2px solid #721C24;
}

.status-message.full {
    background: #F8D7DA;
    color: #721C24;
    border: 2px solid #721C24;
}
</style>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="container">
        <!-- Back Button -->
        <div style="margin-bottom: 20px;">
            <a href="tournaments.php" style="color: #6D94C5; text-decoration: none; font-weight: 600;">
                <i class="fas fa-arrow-left"></i> Back to Tournaments
            </a>
        </div>

        <!-- Tournament Header -->
        <div style="background: white; border-radius: 10px; padding: 30px; margin-bottom: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                     alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                     style="width: 100%; border-radius: 10px; object-fit: cover;"
                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                
                <div>
                    <span class="badge badge-<?php echo $tournament['status']; ?>">
                        <?php echo strtoupper($tournament['status']); ?>
                    </span>
                    
                    <h1 style="color: #6D94C5; margin: 15px 0;"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h1>
                    
                    <p style="color: #666; line-height: 1.8; margin-bottom: 20px;">
                        <?php echo nl2br(htmlspecialchars($tournament['description'])); ?>
                    </p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div>
                            <p><i class="fas fa-calendar" style="color: #6D94C5; margin-right: 10px;"></i><strong>Date:</strong> <?php echo formatDate($tournament['tournament_date']); ?></p>
                            <p><i class="fas fa-clock" style="color: #6D94C5; margin-right: 10px;"></i><strong>Time:</strong> <?php echo formatTime($tournament['start_time']); ?> - <?php echo formatTime($tournament['end_time']); ?></p>
                            <p><i class="fas fa-dollar-sign" style="color: #6D94C5; margin-right: 10px;"></i><strong>Fee:</strong> RM <?php echo number_format($tournament['tournament_fee'], 2); ?></p>
                        </div>
                        <div>
                            <p><i class="fas fa-users" style="color: #6D94C5; margin-right: 10px;"></i><strong>Max Participants:</strong> <?php echo $tournament['max_participants']; ?></p>
                            <p><i class="fas fa-user-check" style="color: #6D94C5; margin-right: 10px;"></i><strong>Registered:</strong> <?php echo $participants_data['registered']; ?></p>
                            <p><i class="fas fa-map-marked-alt" style="color: #6D94C5; margin-right: 10px;"></i><strong>Spots Available:</strong> <?php echo $spots_data['available_spots']; ?>/<?php echo $spots_data['total_spots']; ?></p>
                        </div>
                    </div>
                    
                    <p style="margin-bottom: 20px;">
                        <i class="fas fa-map-marker-alt" style="color: #6D94C5; margin-right: 10px;"></i><strong>Location:</strong><br>
                        <span style="margin-left: 30px;"><?php echo htmlspecialchars($tournament['location']); ?></span>
                    </p>
                    
                    <!-- Registration Status Messages -->
                    <?php if ($tournament['user_registration_status']): ?>
                        <?php if ($tournament['user_registration_status'] == 'pending'): ?>
                            <div class="status-message pending">
                                <i class="fas fa-clock"></i> Registration Pending Approval
                            </div>
                        <?php elseif ($tournament['user_registration_status'] == 'approved'): ?>
                            <div class="status-message approved">
                                <i class="fas fa-check-circle"></i> You're Registered for This Tournament!
                            </div>
                        <?php endif; ?>
                    <?php elseif ($isFull): ?>
                        <div class="status-message full">
                            <i class="fas fa-user-slash"></i> Tournament Full - No Spots Available
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php if ((!$tournament['user_registration_status'] || $tournament['user_registration_status'] == 'rejected') 
                                && $tournament['status'] == 'upcoming' && !$isFull): ?>
                            <a href="../../user/register-tournament.php?id=<?php echo $tournament_id; ?>" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Register for Tournament
                            </a>
                        <?php elseif ($tournament['user_registration_status'] == 'approved'): ?>
                            <a href="<?php echo SITE_URL; ?>/user/my-registrations.php" class="btn btn-primary">
                                <i class="fas fa-list-alt"></i> View My Registrations
                            </a>
                        <?php endif; ?>

                        <?php if ((!$tournament['user_registration_status'] || $tournament['user_registration_status'] == 'rejected')): ?>
                            <button class="save-details-btn <?php echo $tournament['is_saved'] > 0 ? 'saved' : ''; ?>" 
                                    id="saveBtn"
                                    onclick="toggleSave(<?php echo $tournament_id; ?>)">
                                <i class="<?php echo $tournament['is_saved'] > 0 ? 'fas' : 'far'; ?> fa-bookmark"></i>
                                <span><?php echo $tournament['is_saved'] > 0 ? 'Saved' : 'Save for Later'; ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Organizer Information -->
        <div style="background: white; border-radius: 10px; padding: 30px; margin-bottom: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #6D94C5; margin-bottom: 20px;"><i class="fas fa-user-tie"></i> Organizer Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($tournament['organizer_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($tournament['organizer_email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($tournament['organizer_phone']); ?></p>
        </div>

        <!-- Payment Information -->
        <?php if (!empty($tournament['bank_account_name'])): ?>
        <div style="background: white; border-radius: 10px; padding: 30px; margin-bottom: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #6D94C5; margin-bottom: 20px;"><i class="fas fa-credit-card"></i> Payment Information</h3>
            <div style="background: #F5EFE6; padding: 20px; border-radius: 8px; border-left: 4px solid #6D94C5;">
                <p><strong>Bank Name:</strong> <?php echo htmlspecialchars($tournament['bank_account_name']); ?></p>
                <p><strong>Account Number:</strong> <?php echo htmlspecialchars($tournament['bank_account_number']); ?></p>
                <p><strong>Account Holder:</strong> <?php echo htmlspecialchars($tournament['bank_account_holder']); ?></p>
            </div>
            
            <?php if (!empty($tournament['bank_qr'])): ?>
            <div style="text-align: center; margin-top: 20px;">
                <p style="font-weight: 600; margin-bottom: 15px;">Scan QR Code to Pay</p>
                <img src="<?php echo SITE_URL; ?>/assets/images/qrcodes/<?php echo htmlspecialchars($tournament['bank_qr']); ?>" 
                     alt="Payment QR Code"
                     style="max-width: 200px; border-radius: 8px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Prizes -->
        <?php
        $prizes_query = "SELECT tp.*, s.sponsor_name 
                         FROM TOURNAMENT_PRIZE tp 
                         LEFT JOIN SPONSOR s ON tp.sponsor_id = s.sponsor_id 
                         WHERE tp.tournament_id = ? 
                         ORDER BY tp.prize_ranking ASC";
        $stmt = $conn->prepare($prizes_query);
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $prizes_result = $stmt->get_result();
        
        if ($prizes_result->num_rows > 0):
        ?>
        <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <h3 style="color: #6D94C5; margin-bottom: 20px;"><i class="fas fa-gift"></i> Tournament Prizes</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Prize Description</th>
                        <th>Value</th>
                        <th>Sponsor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($prize = $prizes_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($prize['prize_ranking']); ?></strong></td>
                        <td><?php echo htmlspecialchars($prize['prize_description']); ?></td>
                        <td>RM <?php echo number_format($prize['prize_value'], 2); ?></td>
                        <td><?php echo htmlspecialchars($prize['sponsor_name']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php 
        endif; 
        $stmt->close();
        ?>
    </div>
</div>

<script>
function toggleSave(tournamentId) {
    const button = document.getElementById('saveBtn');
    const isSaved = button.classList.contains('saved');
    
    fetch('<?php echo SITE_URL; ?>/pages/tournament/toggle-save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `tournament_id=${tournamentId}&action=${isSaved ? 'unsave' : 'save'}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isSaved) {
                // Unsaved state
                button.classList.remove('saved');
                button.innerHTML = '<i class="far fa-bookmark"></i><span>Save for Later</span>';
            } else {
                // Saved state
                button.classList.add('saved');
                button.innerHTML = '<i class="fas fa-bookmark"></i><span>Saved</span>';
            }
        } else {
            alert(data.message || 'Failed to update saved status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>

<?php include '../../includes/footer.php'; ?>