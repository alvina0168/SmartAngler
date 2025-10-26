<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(SITE_URL . '/pages/login.php');
}

$user_id = $_SESSION['user_id'];
$user = getUserInfo($user_id);

// Get user statistics using helper functions
$tournaments_count = getUserTournamentCount($user_id);
$catches_count = getUserCatchesCount($user_id);
$wins_count = getUserWinsCount($user_id);

$page_title = 'My Dashboard';
include '../includes/header.php';
?>

<!-- Hero Section - Personalized Welcome -->
<section class="hero">
    <div class="container">
        <h1>Welcome Back, <span><?php echo htmlspecialchars($user['full_name']); ?>!</span></h1>
        <p>The water is calling! Your next big catch is waiting. Join a tournament, track your catches, and compete with fellow anglers for amazing prizes!</p>
        <a href="<?php echo SITE_URL; ?>/pages/tournaments.php" class="btn btn-primary">
            <i class="fas fa-fish"></i> START FISHING
        </a>
    </div>
</section>

<!-- User Statistics Section -->
<section class="features">
    <div class="container">
        <h2>Your Fishing <span>Journey</span></h2>
        <p class="features-subtitle">Track your progress and achievements. Every catch counts, every tournament matters!</p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3><?php echo $tournaments_count; ?></h3>
                <p>Tournaments Joined</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-fish"></i>
                </div>
                <h3><?php echo $catches_count; ?></h3>
                <p>Total Catches</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-medal"></i>
                </div>
                <h3><?php echo $wins_count; ?></h3>
                <p>Championship Wins</p>
            </div>
        </div>
    </div>
</section>

<!-- My Tournaments Section -->
<section style="padding: 80px 0; background-color: #F5EFE6;">
    <div class="container">
        <h2 style="text-align: center; font-size: 36px; margin-bottom: 50px;">
            My Registered <span style="color: #6D94C5;">Tournaments</span>
        </h2>
        
        <?php
        $my_tournaments_query = "SELECT t.*, tr.approval_status, tr.registration_date 
                                 FROM TOURNAMENT_REGISTRATION tr 
                                 JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id 
                                 WHERE tr.user_id = '$user_id' 
                                 ORDER BY t.tournament_date DESC 
                                 LIMIT 3";
        $my_tournaments_result = mysqli_query($conn, $my_tournaments_query);
        
        if ($my_tournaments_result && mysqli_num_rows($my_tournaments_result) > 0):
        ?>
            <div class="features-grid">
                <?php while ($tournament = mysqli_fetch_assoc($my_tournaments_result)): ?>
                <div class="card">
                    <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($tournament['image']) ? htmlspecialchars($tournament['image']) : 'default-tournament.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                         class="card-image"
                         onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h3>
                        <p class="card-text">
                            <i class="fas fa-calendar"></i> <?php echo formatDate($tournament['tournament_date']); ?><br>
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(truncateText($tournament['location'], 50)); ?><br>
                            <i class="fas fa-check-circle"></i> Status: 
                            <span class="badge badge-<?php 
                                echo $tournament['approval_status'] == 'approved' ? 'success' : 
                                    ($tournament['approval_status'] == 'pending' ? 'warning' : 'error'); 
                            ?>"><?php echo strtoupper($tournament['approval_status']); ?></span>
                        </p>
                        <a href="<?php echo SITE_URL; ?>/pages/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" class="btn btn-primary">View Details</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <a href="my-tournaments.php" class="btn btn-secondary">View All My Tournaments</a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 16px;">
                <i class="fas fa-calendar-times" style="font-size: 80px; color: #CBDCEB; margin-bottom: 20px;"></i>
                <h3 style="color: #6D94C5; margin-bottom: 15px;">No Tournaments Yet</h3>
                <p style="color: #666; margin-bottom: 30px;">You haven't registered for any tournaments. Start your fishing journey today!</p>
                <a href="<?php echo SITE_URL; ?>/pages/tournaments.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Browse Tournaments
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Recent Catches Section -->
<section style="padding: 80px 0; background-color: white;">
    <div class="container">
        <h2 style="text-align: center; font-size: 36px; margin-bottom: 50px;">
            Recent <span style="color: #6D94C5;">Catches</span>
        </h2>
        
        <?php
        $recent_catches_query = "SELECT fc.*, t.tournament_title 
                                 FROM FISH_CATCH fc 
                                 JOIN TOURNAMENT t ON fc.tournament_id = t.tournament_id 
                                 WHERE fc.user_id = '$user_id' 
                                 ORDER BY fc.catch_date DESC, fc.catch_time DESC 
                                 LIMIT 5";
        $recent_catches_result = mysqli_query($conn, $recent_catches_query);
        
        if ($recent_catches_result && mysqli_num_rows($recent_catches_result) > 0):
        ?>
            <div style="background: #F5EFE6; border-radius: 16px; padding: 30px; overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-trophy"></i> Tournament</th>
                            <th><i class="fas fa-fish"></i> Species</th>
                            <th><i class="fas fa-weight"></i> Weight (kg)</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-clock"></i> Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($catch = mysqli_fetch_assoc($recent_catches_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($catch['tournament_title']); ?></td>
                            <td><strong><?php echo htmlspecialchars($catch['fish_species']); ?></strong></td>
                            <td><span style="color: #6D94C5; font-weight: 600;"><?php echo number_format($catch['fish_weight'], 2); ?> kg</span></td>
                            <td><?php echo formatDate($catch['catch_date']); ?></td>
                            <td><?php echo formatTime($catch['catch_time']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="my-catches.php" class="btn btn-secondary">View All Catches</a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: #F5EFE6; border-radius: 16px;">
                <i class="fas fa-fish" style="font-size: 80px; color: #CBDCEB; margin-bottom: 20px;"></i>
                <h3 style="color: #6D94C5; margin-bottom: 15px;">No Catches Yet</h3>
                <p style="color: #666; margin-bottom: 30px;">Start logging your catches when you join a tournament!</p>
                <a href="<?php echo SITE_URL; ?>/pages/tournaments.php" class="btn btn-primary">
                    <i class="fas fa-trophy"></i> Join Tournament
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Quick Actions CTA Section -->
<section style="padding: 80px 0; background: linear-gradient(135deg, #CBDCEB 0%, #6D94C5 100%); text-align: center; color: white;">
    <div class="container">
        <h2 style="font-size: 36px; margin-bottom: 20px;">Ready for Your Next Adventure?</h2>
        <p style="font-size: 18px; margin-bottom: 40px;">Explore new tournaments, update your profile, or check the leaderboard!</p>
        
        <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
            <a href="<?php echo SITE_URL; ?>/pages/tournaments.php" class="btn btn-primary">
                <i class="fas fa-trophy"></i> Browse Tournaments
            </a>
            <a href="<?php echo SITE_URL; ?>/pages/leaderboard.php" class="btn btn-secondary">
                <i class="fas fa-chart-line"></i> View Leaderboard
            </a>
            <a href="profile.php" class="btn btn-secondary">
                <i class="fas fa-user"></i> My Profile
            </a>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
```

---

## 📥 **HOW TO APPLY:**

### **STEP 1: Update database.php**
```
Location: C:\xampp\htdocs\SmartAngler\includes\database.php
Action: Replace entire file with code above
```

### **STEP 2: Update header.php**
```
Location: C:\xampp\htdocs\SmartAngler\includes\header.php
Action: Replace first 30 lines with code above
```

### **STEP 3: Update dashboard.php**
```
Location: C:\xampp\htdocs\SmartAngler\user\dashboard.php
Action: Replace entire file with code above