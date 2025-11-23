<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireLogin();

if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

$pageTitle = 'Saved Tournaments';
include '../../includes/header.php';

$sql = "SELECT t.*, s.saved_id, s.is_saved,
        (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION 
         WHERE tournament_id = t.tournament_id 
         AND approval_status IN ('pending', 'approved')) as registered_count
        FROM SAVED s
        JOIN TOURNAMENT t ON s.tournament_id = t.tournament_id
        WHERE s.user_id = ? AND s.is_saved = TRUE
        ORDER BY s.saved_id DESC";

$savedTournaments = $db->fetchAll($sql, [$_SESSION['user_id']]);
?>

<style>
.saved-tournaments-section {
    min-height: 70vh;
    padding: 50px 0;
    background-color: #F5EFE6;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
}

.page-header h1 {
    color: #6D94C5;
    font-size: 32px;
    margin-bottom: 10px;
}

.page-header h1 i {
    margin-right: 10px;
}

.page-header p {
    color: #666;
    font-size: 16px;
}

.tournaments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
}

.tournament-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    position: relative;
    transition: transform 0.3s ease;
}

.tournament-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
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
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.unsave-btn i {
    color: #F39C12;
    font-size: 20px;
}

.unsave-btn:hover {
    transform: scale(1.1);
    background: #FEF5E7;
}

.tournament-status {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 10;
}

.tournament-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.tournament-content {
    padding: 20px;
}

.tournament-title {
    font-size: 20px;
    font-weight: 700;
    color: #333;
    margin-bottom: 15px;
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
    color: #666;
}

.detail-item i {
    color: #6D94C5;
    width: 20px;
}

.tournament-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.participants-count {
    font-size: 13px;
    color: #666;
    font-weight: 600;
}

.view-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #6D94C5;
    color: white;
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
    color: #6D94C5;
    margin-bottom: 25px;
    opacity: 0.5;
}

.empty-state h2 {
    font-size: 28px;
    color: #333;
    margin-bottom: 15px;
}

.empty-state p {
    font-size: 16px;
    color: #666;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .tournaments-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="saved-tournaments-section">
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-bookmark"></i> Saved Tournaments</h1>
            <p>Your bookmarked fishing tournaments</p>
        </div>

        <?php if ($savedTournaments && count($savedTournaments) > 0): ?>
            <div class="tournaments-grid">
                <?php foreach ($savedTournaments as $tournament): ?>
                    <div class="tournament-card">
                        <button class="unsave-btn" 
                                onclick="unsaveTournament(<?php echo $tournament['tournament_id']; ?>, <?php echo $tournament['saved_id']; ?>)"
                                title="Remove from saved">
                            <i class="fas fa-bookmark"></i>
                        </button>

                        <div class="tournament-status">
                            <span class="badge badge-<?php echo $tournament['status']; ?>">
                                <?php echo strtoupper($tournament['status']); ?>
                            </span>
                        </div>

                        <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($tournament['image']) ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                             class="tournament-image"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">

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

                                <a href="<?php echo SITE_URL; ?>/pages/tournament/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
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
            <div class="empty-state">
                <i class="fas fa-bookmark"></i>
                <h2>No Saved Tournaments Yet</h2>
                <p>Start bookmarking tournaments you're interested in!</p>
                <a href="<?php echo SITE_URL; ?>/pages/tournament/tournaments.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 10px;">
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
        fetch('<?php echo SITE_URL; ?>/pages/tournament/unsave-tournament.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `tournament_id=${tournamentId}&saved_id=${savedId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
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

<?php include '../../includes/footer.php'; ?>