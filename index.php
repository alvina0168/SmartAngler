<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
$page_title = 'Home';
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Compete, Track, and Win<br>with <span>SmartAngler!</span></h1>
        <p>SmartAngler makes fishing competitions easy! Create and join tournaments, log your catches, and compete with anglers for great prizes!</p>
        <a href="pages/login.php" class="btn btn-primary">GET STARTED</a>
    </div>
</section>

<!-- Features Section -->
<section class="features">
    <div class="container">
        <h2>Everything a <span>Champion Angler Needs.</span></h2>
        <p class="features-subtitle">SmartAngler brings competitive angling features to your device. Register, Compete, and Track your fishing performance in real-time. Sign up and be part of the digital angling revolution.</p>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3>Tournament Hub</h3>
                <p>Stay updated with the latest fishing tournaments. Browse, view details and register for upcoming competitions. Get all the information you need in one place.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Participant Tools</h3>
                <p>Register, scan fishing spots, and log your catches. All in one place! Easy-to-use tools designed for anglers, by anglers.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-medal"></i>
                </div>
                <h3>Live Leaderboard</h3>
                <p>Follow the action! Best boat rankings and results at the tournament in real-time. See where you stand compared to fellow anglers.</p>
            </div>
        </div>
    </div>
</section>

<!-- Latest Tournaments Section -->
<section style="padding: 80px 0; background-color: #F5EFE6;">
    <div class="container">
        <h2 style="text-align: center; font-size: 36px; margin-bottom: 50px;">
            Upcoming <span style="color: #6D94C5;">Tournaments</span>
        </h2>

        <div class="features-grid">
            <?php
            $sql = "SELECT t.*, u.full_name as organizer_name 
                    FROM TOURNAMENT t 
                    LEFT JOIN USER u ON t.user_id = u.user_id 
                    WHERE t.status = 'upcoming' 
                    ORDER BY t.tournament_date ASC 
                    LIMIT 3";

            $tournaments = $db->fetchAll($sql);

            if ($tournaments && count($tournaments) > 0): 
                foreach ($tournaments as $tournament): ?>
                    <div class="card">
                        <img src="assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                             class="card-image">
                        <div class="card-content">
                            <h3 class="card-title"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h3>
                            <p class="card-text">
                                <i class="fas fa-calendar"></i> <?php echo formatDate($tournament['tournament_date']); ?><br>
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($tournament['location'], 0, 50)); ?>...<br>
                                <i class="fas fa-dollar-sign"></i> RM <?php echo number_format($tournament['tournament_fee'], 2); ?>
                            </p>
                            <a href="pages/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <p style="text-align: center; grid-column: 1 / -1;">No upcoming tournaments at the moment. Check back soon!</p>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 40px;">
            <a href="pages/tournaments.php" class="btn btn-secondary">View All Tournaments</a>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 100px 0; background: linear-gradient(135deg, #CBDCEB 0%, #6D94C5 100%); text-align: center; color: white;">
    <div class="container">
        <h2 style="font-size: 36px; margin-bottom: 20px;">Ready to Join the Competition?</h2>
        <p style="font-size: 18px; margin-bottom: 30px;">Sign up now and start your angling journey with SmartAngler!</p>
        <a href="pages/register.php" class="btn btn-primary">REGISTER NOW</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
