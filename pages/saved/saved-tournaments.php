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

/* Hero Section */
.saved-hero {
    background: linear-gradient(135deg, var(--ocean-blue) 0%, var(--ocean-light) 100%);
    padding: 60px 0 100px;
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

/* Filter Section */
.filter-section {
    max-width: 100%;
    margin: -50px 60px 0;
    position: relative;
    z-index: 10;
}

.filter-card {
    background: var(--white);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
}

.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 10px;
    background: #F3F4F6;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.filter-tab:hover {
    background: #E5E7EB;
}

.filter-tab.active {
    background: var(--ocean-light);
    color: var(--white);
}

.search-controls {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
}

.search-wrapper {
    position: relative;
}

.search-input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    font-size: 14px;
}

.search-input:focus {
    outline: none;
    border-color: var(--ocean-light);
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.sort-select {
    padding: 12px 40px 12px 16px;
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    background: var(--white);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    min-width: 200px;
}

/* Saved Tournaments Container */
.saved-page {
    background: var(--white);
    padding: 40px 0 60px;
}

.saved-container {
    max-width: 100%;
    padding: 0 60px;
}

/* Tournament Grid - 3 Columns */
.tournaments-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.tournament-card {
    background: var(--white);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border);
    position: relative;
}

.tournament-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
}

.card-image {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.tournament-card:hover .card-image img {
    transform: scale(1.05);
}

.status-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    backdrop-filter: blur(10px);
}

.badge-upcoming { background: rgba(8, 131, 149, 0.9); color: white; }
.badge-ongoing { background: rgba(245, 158, 11, 0.9); color: white; }
.badge-completed { background: rgba(16, 185, 129, 0.9); color: white; }

.unsave-btn {
    position: absolute;
    top: 12px;
    left: 12px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.95);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 2;
}

.unsave-btn:hover {
    transform: scale(1.1);
    background: #FFF3E0;
}

.unsave-btn i {
    font-size: 16px;
    color: #F59E0B;
}

.card-content {
    padding: 16px;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.location-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--ocean-light);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.card-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 12px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 47px;
}

.card-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-dark);
}

.meta-item i {
    width: 16px;
    color: var(--ocean-light);
    font-size: 13px;
}

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    margin-top: auto;
    border-top: 1px solid #F3F4F6;
}

.price-tag {
    display: flex;
    flex-direction: column;
}

.price-amount {
    font-size: 24px;
    font-weight: 800;
    color: var(--ocean-blue);
    line-height: 1;
}

.price-label {
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
}

.btn-view {
    padding: 10px 20px;
    background: var(--ocean-light);
    color: var(--white);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-view:hover {
    background: var(--ocean-blue);
}

/* Empty State */
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
    font-size: 32px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 12px;
}

.empty-text {
    font-size: 16px;
    color: var(--text-muted);
    margin: 0 0 32px;
}

.btn-browse {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: var(--ocean-light);
    color: var(--white);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-browse:hover {
    background: var(--ocean-blue);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(8, 131, 149, 0.3);
}

/* Responsive */
@media (max-width: 1400px) {
    .saved-container,
    .filter-section,
    .hero-content {
        padding-left: 40px;
        padding-right: 40px;
    }
}

@media (max-width: 1200px) {
    .tournaments-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }
    
    .tournaments-grid {
        grid-template-columns: 1fr;
    }
    
    .saved-container,
    .filter-section,
    .hero-content {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .search-controls {
        grid-template-columns: 1fr;
    }
    
    .sort-select {
        width: 100%;
    }
}
</style>

<!-- Hero Section -->
<div class="saved-hero">
    <div class="hero-content">
        <h1 class="hero-title">Saved Tournaments</h1>
        <p class="hero-subtitle">Track your favorite fishing tournaments</p>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-card">
        <div class="filter-tabs">
            <a href="../tournament/tournaments.php?status=all" class="filter-tab">
                All Tournaments
            </a>
            <a href="../tournament/tournaments.php?status=upcoming" class="filter-tab">
                Upcoming
            </a>
            <a href="../tournament/tournaments.php?status=ongoing" class="filter-tab">
                Live Now
            </a>
            <a href="../tournament/tournaments.php?status=completed" class="filter-tab">
                Completed
            </a>
        </div>
        
        <div class="search-controls">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       class="search-input"
                       placeholder="Search saved tournaments..." 
                       id="searchInput">
            </div>
            <select class="sort-select" id="sortSelect">
                <option value="recent">Recently Saved</option>
                <option value="date_asc">Date: Soonest First</option>
                <option value="date_desc">Date: Latest First</option>
                <option value="price_low">Price: Low to High</option>
                <option value="price_high">Price: High to Low</option>
            </select>
        </div>
    </div>
</div>

<!-- Saved Tournaments Page -->
<div class="saved-page">
    <div class="saved-container">
        <?php if ($savedTournaments && count($savedTournaments) > 0): ?>
            <div class="tournaments-grid">
                <?php foreach ($savedTournaments as $tournament): ?>
                    <div class="tournament-card" onclick="window.location.href='<?php echo SITE_URL; ?>/pages/tournament/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>'">
                        <div class="card-image">
                            <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>"
                                 onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                            
                            <span class="status-badge badge-<?php echo $tournament['status']; ?>">
                                <?php echo $tournament['status']; ?>
                            </span>
                            
                            <button class="unsave-btn" 
                                    onclick="event.stopPropagation(); unsaveTournament(<?php echo $tournament['tournament_id']; ?>, <?php echo $tournament['saved_id']; ?>)"
                                    title="Remove from saved">
                                <i class="fas fa-bookmark"></i>
                            </button>
                        </div>
                        
                        <div class="card-content">
                            <div class="location-tag">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php 
                                $location_parts = explode('-', $tournament['location']);
                                echo htmlspecialchars(trim($location_parts[0]));
                                ?>
                            </div>
                            
                            <h3 class="card-title">
                                <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                            </h3>
                            
                            <div class="card-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M j, Y', strtotime($tournament['tournament_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('g:i A', strtotime($tournament['start_time'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $tournament['registered_count']; ?> / <?php echo $tournament['max_participants']; ?> Anglers</span>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <div class="price-tag">
                                    <span class="price-amount">RM<?php echo number_format($tournament['tournament_fee'], 0); ?></span>
                                    <span class="price-label">Entry Fee</span>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/pages/tournament/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
                                   class="btn-view"
                                   onclick="event.stopPropagation()">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h2 class="empty-title">No Saved Tournaments</h2>
                <p class="empty-text">Start saving tournaments you're interested in!</p>
                <a href="<?php echo SITE_URL; ?>/pages/tournament/tournaments.php" class="btn-browse">
                    Browse Tournaments
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

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

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.tournament-card');
    
    cards.forEach(card => {
        const title = card.querySelector('.card-title').textContent.toLowerCase();
        const location = card.querySelector('.location-tag').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || location.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>