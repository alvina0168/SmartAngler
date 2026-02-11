<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

$user_id = $_SESSION['user_id'];

$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
         WHERE user_id = ? AND approval_status IN ('approved', 'pending')) as total_tournaments,
        (SELECT COUNT(*) FROM FISH_CATCH 
         WHERE user_id = ?) as total_catches,
        (SELECT COUNT(*) FROM REVIEW 
         WHERE user_id = ?) as total_reviews,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION tr
         JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
         WHERE tr.user_id = ? AND tr.approval_status = 'approved' 
         AND t.status = 'upcoming') as upcoming_tournaments
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$tournaments_query = "
    SELECT 
        t.*,
        tr.registration_id,
        tr.approval_status,
        tr.registration_date,
        u.full_name as organizer_name,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
         WHERE tournament_id = t.tournament_id 
         AND approval_status = 'approved') as registered_count,
        (SELECT COUNT(*) 
         FROM FISH_CATCH fc
         JOIN TOURNAMENT_REGISTRATION tr2 ON fc.registration_id = tr2.registration_id
         WHERE tr2.tournament_id = t.tournament_id
           AND fc.user_id = ?) as my_catches_count
    FROM TOURNAMENT_REGISTRATION tr
    JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
    LEFT JOIN USER u ON t.created_by = u.user_id
    WHERE tr.user_id = ?
    ORDER BY tr.registration_date DESC
";
$stmt = $conn->prepare($tournaments_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$tournaments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My Dashboard';
include '../../includes/header.php';
?>

<style>
:root {
    --ocean-blue: #0A4D68;
    --ocean-light: #088395;
    --ocean-teal: #05BFDB;
    --sand: #F8F6F0;
    --text-dark: #1A1A1A;
    --text-muted: #6B7280;
    --white: #FFFFFF;
    --border: #E5E7EB;
}

.dashboard-hero {
    background: linear-gradient(135deg, var(--ocean-blue) 0%, var(--ocean-light) 100%);
    padding: 60px 0;
    position: relative;
}

.hero-content {
    max-width: 100%;
    padding: 0 60px;
}

.hero-title {
    font-size: 48px;
    font-weight: 800;
    color: var(--white);
    margin: 0 0 12px;
}

.hero-subtitle {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}

.dashboard-page {
    background: var(--white);
    padding: 40px 0 60px;
}

.dashboard-container {
    max-width: 100%;
    padding: 0 60px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.stat-icon.tournaments {
    background: linear-gradient(135deg, rgba(8, 131, 149, 0.1) 0%, rgba(5, 191, 219, 0.1) 100%);
    color: var(--ocean-light);
}

.stat-icon.catches {
    background: rgba(16, 185, 129, 0.1);
    color: #10B981;
}

.stat-icon.reviews {
    background: rgba(245, 158, 11, 0.1);
    color: #F59E0B;
}

.stat-icon.upcoming {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
}

.stat-value {
    font-size: 36px;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: var(--text-muted);
    font-weight: 600;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.section-title {
    font-size: 28px;
    font-weight: 800;
    color: var(--ocean-blue);
    display: flex;
    align-items: center;
    gap: 12px;
}

.tournaments-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.tournament-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.tournament-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
}

.tournament-card.pending {
    border: 2px solid #F59E0B;
}

.tournament-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.tournament-content {
    padding: 20px;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.status-badge.upcoming { background: rgba(59, 130, 246, 0.1); color: #3B82F6; }
.status-badge.ongoing { background: rgba(245, 158, 11, 0.1); color: #F59E0B; }
.status-badge.completed { background: rgba(16, 185, 129, 0.1); color: #10B981; }

.approval-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
    margin-left: 8px;
}

.approval-badge.pending { 
    background: rgba(245, 158, 11, 0.15); 
    color: #F59E0B;
    animation: pulse 2s ease-in-out infinite;
}

.approval-badge.approved { background: rgba(16, 185, 129, 0.15); color: #10B981; }
.approval-badge.rejected { background: rgba(239, 68, 68, 0.15); color: #EF4444; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.tournament-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 12px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tournament-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-muted);
}

.meta-item i {
    width: 16px;
    color: var(--ocean-light);
}

.tournament-actions {
    display: flex;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
}

.btn-action {
    flex: 1;
    min-width: 100px;
    padding: 10px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ocean-light);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-action:hover {
    background: var(--ocean-light);
    color: var(--white);
    border-color: var(--ocean-light);
}

.btn-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-action:disabled:hover {
    background: var(--white);
    color: var(--text-muted);
    border-color: var(--border);
}

.pending-indicator {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 12px;
    height: 12px;
    background: #F59E0B;
    border-radius: 50%;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
    animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping {
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 32px;
    background: linear-gradient(135deg, var(--sand) 0%, #E5E7EB 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon i {
    font-size: 56px;
    color: var(--text-muted);
}

.empty-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 12px;
}

.empty-text {
    font-size: 16px;
    color: var(--text-muted);
    margin: 0 0 32px;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    color: var(--white);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(8, 131, 149, 0.3);
}

@media (max-width: 1400px) {
    .dashboard-container,
    .hero-content {
        padding-left: 40px;
        padding-right: 40px;
    }
}

@media (max-width: 1200px) {
    .tournaments-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }
    
    .dashboard-container,
    .hero-content {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .tournaments-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard-hero">
    <div class="hero-content">
        <h1 class="hero-title">My Dashboard</h1>
        <p class="hero-subtitle">Track your tournaments, catches, and achievements</p>
    </div>
</div>

<div class="dashboard-page">
    <div class="dashboard-container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon tournaments">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_tournaments']; ?></div>
                <div class="stat-label">Total Tournaments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon catches">
                    <i class="fas fa-fish"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_catches']; ?></div>
                <div class="stat-label">Total Catches</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon reviews">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_reviews']; ?></div>
                <div class="stat-label">Reviews Written</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon upcoming">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?php echo $stats['upcoming_tournaments']; ?></div>
                <div class="stat-label">Upcoming Tournaments</div>
            </div>
        </div>

        <!-- My Tournaments Section -->
        <div class="section-header">
            <h2 class="section-title">
                My Tournaments
            </h2>
        </div>

        <?php if (count($tournaments) > 0): ?>
            <div class="tournaments-grid">
                <?php foreach ($tournaments as $tournament): ?>
                    <div class="tournament-card <?php echo $tournament['approval_status'] == 'pending' ? 'pending' : ''; ?>">
                        <?php if ($tournament['approval_status'] == 'pending'): ?>
                            <div class="pending-indicator"></div>
                        <?php endif; ?>
                        
                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>"
                             class="tournament-image"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                        
                        <div class="tournament-content">
                            <div>
                                <span class="status-badge <?php echo $tournament['status']; ?>">
                                    <?php echo $tournament['status']; ?>
                                </span>
                                
                                <span class="approval-badge <?php echo $tournament['approval_status']; ?>">
                                    <?php 
                                    if ($tournament['approval_status'] == 'pending') {
                                        echo '<i class="fas fa-clock"></i> Pending Approval';
                                    } elseif ($tournament['approval_status'] == 'approved') {
                                        echo '<i class="fas fa-check-circle"></i> Approved';
                                    } else {
                                        echo '<i class="fas fa-times-circle"></i> Rejected';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <h3 class="tournament-title">
                                <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                            </h3>
                            
                            <div class="tournament-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M j, Y', strtotime($tournament['tournament_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars(substr($tournament['location'], 0, 30)); ?><?php echo strlen($tournament['location']) > 30 ? '...' : ''; ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-fish"></i>
                                    <span><?php echo $tournament['my_catches_count']; ?> Catches Recorded</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Registered: <?php echo date('M j, Y', strtotime($tournament['registration_date'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($tournament['approval_status'] == 'approved'): ?>
                            <div class="tournament-actions">
                                <a href="<?php echo SITE_URL; ?>/pages/tournament/get-live-results.php?tournament_id=<?php echo $tournament['tournament_id']; ?>" 
                                   class="btn-action">
                                    <i class="fas fa-trophy"></i>
                                    Results
                                </a>

                                <a href="<?php echo SITE_URL; ?>/pages/catch/my-catches.php?tournament_id=<?php echo $tournament['tournament_id']; ?>" 
                                   class="btn-action">
                                    <i class="fas fa-fish"></i>
                                    Catches
                                </a>

                                <?php if ($tournament['status'] == 'completed'): ?>
                                <a href="<?php echo SITE_URL; ?>/pages/review/addReview.php?tournament_id=<?php echo $tournament['tournament_id']; ?>" 
                                   class="btn-action">
                                    <i class="fas fa-star"></i>
                                    Review
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php elseif ($tournament['approval_status'] == 'pending'): ?>
                            <div class="tournament-actions">
                                <button class="btn-action" disabled>
                                    <i class="fas fa-hourglass-half"></i>
                                    Awaiting Approval
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="tournament-actions">
                                <button class="btn-action" disabled>
                                    <i class="fas fa-times-circle"></i>
                                    Registration Rejected
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 class="empty-title">No Tournaments Yet</h3>
                <p class="empty-text">You haven't registered for any tournaments. Start exploring!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>