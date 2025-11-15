<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/pages/tournaments.php');
}

$tournament_id = sanitize($_GET['id']);

// Get tournament details
$query = "SELECT t.*, u.full_name as organizer_name, u.email as organizer_email, u.phone_number as organizer_phone 
          FROM TOURNAMENT t 
          LEFT JOIN USER u ON t.user_id = u.user_id 
          WHERE t.tournament_id = '$tournament_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    redirect(SITE_URL . '/pages/tournaments.php');
}

$tournament = mysqli_fetch_assoc($result);

// Get available spots count
$spots_query = "
    SELECT 
        COUNT(*) AS total_spots,
        SUM(CASE WHEN fs.spot_status = 'available' THEN 1 ELSE 0 END) AS available_spots
    FROM FISHING_SPOT fs
    JOIN ZONE z ON fs.zone_id = z.zone_id
    WHERE z.tournament_id = '$tournament_id'
";
$spots_result = mysqli_query($conn, $spots_query);
$spots_data = mysqli_fetch_assoc($spots_result);


// Get registered participants count
$participants_query = "SELECT COUNT(*) as registered 
                       FROM TOURNAMENT_REGISTRATION 
                       WHERE tournament_id = '$tournament_id' AND approval_status IN ('pending', 'approved')";
$participants_result = mysqli_query($conn, $participants_query);
$participants_data = mysqli_fetch_assoc($participants_result);

$page_title = $tournament['tournament_title'];
include '../includes/header.php';
?>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="container">
        <!-- Tournament Header -->
        <div style="background: white; border-radius: 10px; padding: 30px; margin-bottom: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                     alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                     style="width: 100%; border-radius: 10px; object-fit: cover;">
                
                <div>
                    <span class="badge badge-<?php 
                        echo $tournament['status'] == 'upcoming' ? 'info' : 
                            ($tournament['status'] == 'ongoing' ? 'warning' : 'success'); 
                    ?>"><?php echo strtoupper($tournament['status']); ?></span>
                    
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
                    
                    <p style="margin-bottom: 15px;">
                        <i class="fas fa-map-marker-alt" style="color: #6D94C5; margin-right: 10px;"></i><strong>Location:</strong><br>
                        <span style="margin-left: 30px;"><?php echo htmlspecialchars($tournament['location']); ?></span>
                    </p>
                    
                    <?php if (isLoggedIn() && !isAdmin()): ?>
                        <a href="../user/register-tournament.php?id=<?php echo $tournament_id; ?>" class="btn btn-primary" style="margin-right: 10px;">
                            <i class="fas fa-user-plus"></i> Register for Tournament
                        </a>
                    <?php elseif (!isLoggedIn()): ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login to Register
                        </a>
                    <?php endif; ?>
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
        
        <!-- Prizes -->
        <?php
        $prizes_query = "SELECT tp.*, s.sponsor_name 
                         FROM TOURNAMENT_PRIZE tp 
                         LEFT JOIN SPONSOR s ON tp.sponsor_id = s.sponsor_id 
                         WHERE tp.tournament_id = '$tournament_id' 
                         ORDER BY tp.prize_ranking ASC";
        $prizes_result = mysqli_query($conn, $prizes_query);
        
        if (mysqli_num_rows($prizes_result) > 0):
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
                    <?php while ($prize = mysqli_fetch_assoc($prizes_result)): ?>
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
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>