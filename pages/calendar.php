<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get tournaments for this month
$start_date = "$year-$month-01";
$end_date = date("Y-m-t", strtotime($start_date));

$tournaments_query = "SELECT * FROM TOURNAMENT 
                      WHERE tournament_date BETWEEN '$start_date' AND '$end_date' 
                      ORDER BY tournament_date ASC";
$tournaments_result = mysqli_query($conn, $tournaments_query);

$page_title = 'Calendar';
include '../includes/header.php';
?>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="container">
        <h1 style="text-align: center; color: #6D94C5; margin-bottom: 30px;">
            <i class="fas fa-calendar-alt"></i> Tournament Calendar
        </h1>
        
        <!-- Month Navigation -->
        <div style="text-align: center; margin-bottom: 30px;">
            <a href="?month=<?php echo $month == 1 ? 12 : $month-1; ?>&year=<?php echo $month == 1 ? $year-1 : $year; ?>" class="btn btn-secondary">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <span style="margin: 0 20px; font-size: 20px; font-weight: 600;">
                <?php echo date('F Y', strtotime("$year-$month-01")); ?>
            </span>
            <a href="?month=<?php echo $month == 12 ? 1 : $month+1; ?>&year=<?php echo $month == 12 ? $year+1 : $year; ?>" class="btn btn-secondary">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        
        <!-- Tournament List for this Month -->
        <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #6D94C5; margin-bottom: 20px;">Tournaments in <?php echo date('F Y', strtotime("$year-$month-01")); ?></h2>
            
            <?php if (mysqli_num_rows($tournaments_result) > 0): ?>
                <div style="display: grid; gap: 20px;">
                    <?php while ($tournament = mysqli_fetch_assoc($tournaments_result)): ?>
                        <div style="border-left: 4px solid #6D94C5; padding: 20px; background: #F5EFE6; border-radius: 5px;">
                            <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 20px; align-items: center;">
                                <div style="text-align: center; background: #6D94C5; color: white; padding: 10px 15px; border-radius: 5px;">
                                    <div style="font-size: 24px; font-weight: 600;">
                                        <?php echo date('d', strtotime($tournament['tournament_date'])); ?>
                                    </div>
                                    <div style="font-size: 12px;">
                                        <?php echo strtoupper(date('M', strtotime($tournament['tournament_date']))); ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <h3 style="margin-bottom: 5px; color: #6D94C5;">
                                        <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                                    </h3>
                                    <p style="color: #666; margin: 5px 0;">
                                        <i class="fas fa-clock"></i> <?php echo formatTime($tournament['start_time']); ?> - <?php echo formatTime($tournament['end_time']); ?>
                                    </p>
                                    <p style="color: #666; margin: 5px 0;">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($tournament['location'], 0, 50)); ?>...
                                    </p>
                                    <span class="badge badge-<?php 
                                        echo $tournament['status'] == 'upcoming' ? 'info' : 
                                            ($tournament['status'] == 'ongoing' ? 'warning' : 'success'); 
                                    ?>"><?php echo strtoupper($tournament['status']); ?></span>
                                </div>
                                
                                <div>
                                    <a href="tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" class="btn btn-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-info-circle" style="font-size: 48px; color: #6D94C5; display: block; margin-bottom: 20px;"></i>
                    No tournaments scheduled for this month.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>