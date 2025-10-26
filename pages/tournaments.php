<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
$page_title = 'Tournaments';
include '../includes/header.php';

// Get filter
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'upcoming';
?>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="container">
        <h1 style="text-align: center; color: #6D94C5; margin-bottom: 30px;">
            <i class="fas fa-trophy"></i> Fishing Tournaments
        </h1>
        
        <!-- Filter Tabs -->
        <div style="text-align: center; margin-bottom: 40px;">
            <a href="?status=upcoming" class="btn <?php echo $status_filter == 'upcoming' ? 'btn-primary' : 'btn-secondary'; ?>" style="margin: 5px;">Upcoming</a>
            <a href="?status=ongoing" class="btn <?php echo $status_filter == 'ongoing' ? 'btn-primary' : 'btn-secondary'; ?>" style="margin: 5px;">Ongoing</a>
            <a href="?status=completed" class="btn <?php echo $status_filter == 'completed' ? 'btn-primary' : 'btn-secondary'; ?>" style="margin: 5px;">Completed</a>
        </div>
        
        <!-- Tournaments Grid -->
        <div class="features-grid">
            <?php
            $query = "SELECT t.*, u.full_name as organizer_name 
                      FROM TOURNAMENT t 
                      LEFT JOIN USER u ON t.user_id = u.user_id 
                      WHERE t.status = '$status_filter' 
                      ORDER BY t.tournament_date DESC";
            $result = mysqli_query($conn, $query);
            
            if (mysqli_num_rows($result) > 0):
                while ($tournament = mysqli_fetch_assoc($result)):
            ?>
                <div class="card">
                    <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                         class="card-image">
                    <div class="card-content">
                        <span class="badge badge-<?php 
                            echo $tournament['status'] == 'upcoming' ? 'info' : 
                                ($tournament['status'] == 'ongoing' ? 'warning' : 'success'); 
                        ?>"><?php echo strtoupper($tournament['status']); ?></span>
                        
                        <h3 class="card-title" style="margin-top: 10px;"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h3>
                        
                        <p class="card-text">
                            <i class="fas fa-calendar"></i> <?php echo formatDate($tournament['tournament_date']); ?><br>
                            <i class="fas fa-clock"></i> <?php echo formatTime($tournament['start_time']); ?> - <?php echo formatTime($tournament['end_time']); ?><br>
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($tournament['location'], 0, 40)); ?>...<br>
                            <i class="fas fa-dollar-sign"></i> RM <?php echo number_format($tournament['tournament_fee'], 2); ?><br>
                            <i class="fas fa-users"></i> Max: <?php echo $tournament['max_participants']; ?> participants
                        </p>
                        
                        <a href="tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" class="btn btn-primary" style="width: 100%;">View Details</a>
                    </div>
                </div>
            <?php 
                endwhile;
            else:
            ?>
                <div style="text-align: center; grid-column: 1/-1; padding: 40px;">
                    <i class="fas fa-info-circle" style="font-size: 48px; color: #6D94C5; margin-bottom: 20px;"></i>
                    <p>No <?php echo $status_filter; ?> tournaments at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>