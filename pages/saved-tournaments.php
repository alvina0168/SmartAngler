<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Admins shouldn't access this page
if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

$pageTitle = 'Saved Tournaments';
include '../includes/header.php';

// Get all saved tournaments for current user
$sql = "SELECT t.*, s.saved_id, s.is_saved,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
         WHERE tournament_id = t.tournament_id 
         AND approval_status IN ('pending', 'approved')) as registered_count
        FROM SAVED s
        JOIN TOURNAMENT t ON s.tournament_id = t.tournament_id
        WHERE s.user_id = ? AND s.is_saved = 1
        ORDER BY s.saved_id DESC";

$savedTournaments = $db->fetchAll($sql, [$_SESSION['user_id']]);
?>

<style>
.saved-tournaments-section {
    padding: 40px 0;
    min-height: calc(100vh - 200px);
}

.page-header {
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    color: var(--white);
    padding: 40px;
    border-radius: 16px;
    margin-bottom: 40px;
    box-shadow: var(--shadow-md);
}

.page-header h1 {
    font-size: 32px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header p {
    font-size: 16px;
    opacity: 0.9;
}

.tournaments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin-top: 30px;
}

.tournament-card {
    background: var(--white);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
}

.tournament-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-blue);
}

.tournament-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: transform 0.5s;
}

.tournament-card:hover .tournament-image {
    transform: scale(1.1);
}

.tournament-content {
    padding: 24px;
}

.tournament-status {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 10;
}

.unsave-btn {
    position: absolute;
    top: 15px;
    left: 15px;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.95);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.unsave-btn i {
    color: #F39C12;
    font-size: 20px;
}

.unsave-btn:hover {
    transform: scale(1.1);
    background: #FEF5E7;
}

.tournament-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 15px;
    line-height: 1.4;
}

.tournament-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: var(--text-light);
}

.detail-item i {
    width: 20px;
    color: var(--primary-blue);
    font-size: 16px;
}

.tournament-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 20px;
    border-top: 1px solid var(--secondary-light);
}

.participants-count {
    font-size: 13px;
    color: var(--text-light);
    font-weight: 600;
}

.view-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--primary-blue);
    color: var(--white);
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}

.view-btn:hover {
    background: #5a7ea8;
    transform: translateX(5px);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-state i {
    font-size: 80px;
    color: var(--secondary-blue);
    margin-bottom: 25px;
    opacity: 0.5;
}

.empty-state h2 {
    font-size: 28px;
    color: var(--text-dark);
    margin-bottom: 15px;
}

.empty-state p {
    font-size: 16px;
    color: var(--text-light);
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .tournaments-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header h1 {
        font-size: 24px;
    }
}
</style>

<section class="saved-tournaments-section">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-bookmark"></i>
                Saved Tournaments
            </h1>
            <p>Your bookmarked fishing tournaments - Easy access to events you're interested in</p>
        </div>

        <?php if ($savedTournaments && count($savedTournaments) > 0): ?>
            <div class="tournaments-grid">
                <?php foreach ($savedTournaments as $tournament): ?>
                    <div class="tournament-card">
                        <!-- Unsave Button -->
                        <button class="unsave-btn" 
                                onclick="unsaveTournament(<?php echo $tournament['tournament_id']; ?>, <?php echo $tournament['saved_id']; ?>)"
                                title="Remove from saved">
                            <i class="fas fa-bookmark"></i>
                        </button>

                        <!-- Tournament Status Badge -->
                        <div class="tournament-status">
                            <span class="badge badge-<?php echo $tournament['status']; ?>">
                                <?php echo strtoupper($tournament['status']); ?>
                            </span>
                        </div>

                        <!-- Tournament Image -->
                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($tournament['image']) ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                             class="tournament-image"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">

                        <!-- Tournament Content -->
                        <div class="tournament-content">
                            <h3 class="tournament-title">
                                <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                            </h3>

                            <div class="tournament-details">
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo formatDate($tournament['tournament_date']); ?></span>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span>
                                        <?php echo date('g:i A', strtotime($tournament['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($tournament['end_time'])); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars(substr($tournament['location'], 0, 40)) . (strlen($tournament['location']) > 40 ? '...' : ''); ?></span>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span>RM <?php echo number_format($tournament['tournament_fee'], 2); ?></span>
                                </div>
                            </div>

                            <div class="tournament-footer">
                                <span class="participants-count">
                                    <i class="fas fa-users"></i>
                                    <?php echo $tournament['registered_count']; ?> / <?php echo $tournament['max_participants']; ?> Registered
                                </span>

                                <a href="<?php echo SITE_URL; ?>/pages/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
                                   class="view-btn">
                                    View Details
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="fas fa-bookmark"></i>
                <h2>No Saved Tournaments Yet</h2>
                <p>Start bookmarking tournaments you're interested in!</p>
                <a href="<?php echo SITE_URL; ?>/pages/tournaments.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 10px;">
                    <i class="fas fa-trophy"></i>
                    Browse Tournaments
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function unsaveTournament(tournamentId, savedId) {
    if (confirm('Remove this tournament from your saved list?')) {
        fetch('<?php echo SITE_URL; ?>/pages/unsave-tournament.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `tournament_id=${tournamentId}&saved_id=${savedId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to update the list
                window.location.reload();
            } else {
                alert(data.message || 'Failed to unsave tournament');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>