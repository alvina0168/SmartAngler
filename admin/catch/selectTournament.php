<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Fish Catch Record';
$page_description = 'Choose a tournament to manage catch records';

// Get all tournaments
$tournaments_query = "
    SELECT t.*, 
           COUNT(DISTINCT ws.station_id) as station_count,
           COUNT(DISTINCT fc.catch_id) as catch_count
    FROM TOURNAMENT t
    LEFT JOIN WEIGHING_STATION ws ON t.tournament_id = ws.tournament_id
    LEFT JOIN FISH_CATCH fc ON ws.station_id = fc.station_id
    WHERE t.status IN ('upcoming', 'ongoing', 'completed')
    GROUP BY t.tournament_id
    ORDER BY t.tournament_date DESC
";
$tournaments_result = mysqli_query($conn, $tournaments_query);

include '../includes/header.php';
?>

<style>
.tournament-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.tournament-card {
    background: var(--color-white);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all var(--transition-base);
    cursor: pointer;
    border: 3px solid transparent;
}

.tournament-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
    border-color: var(--color-blue-primary);
}

.tournament-card-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
}

.tournament-card-body {
    padding: 1.25rem;
}

.tournament-card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--color-gray-800);
    margin-bottom: 0.5rem;
}

.tournament-card-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.tournament-card-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--color-gray-600);
}

.tournament-card-meta-item i {
    color: var(--color-blue-primary);
    width: 16px;
}

.tournament-card-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid var(--color-cream-light);
}

.stat-item {
    text-align: center;
    padding: 0.75rem;
    background: var(--color-cream-light);
    border-radius: var(--radius-md);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-blue-primary);
    display: block;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--color-gray-600);
    display: block;
    margin-top: 0.25rem;
}
</style>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-fish"></i>
            Choose Your Tournament
        </h2>
    </div>

    <?php if (mysqli_num_rows($tournaments_result) > 0): ?>
        <div class="tournament-card-grid">
            <?php while ($tournament = mysqli_fetch_assoc($tournaments_result)): ?>
                <a href="stationList.php?tournament_id=<?php echo $tournament['tournament_id']; ?>" 
                   class="tournament-card" style="text-decoration: none;">
                    <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($tournament['image']) ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                         alt="Tournament" 
                         class="tournament-card-image"
                         onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                    
                    <div class="tournament-card-body">
                        <h3 class="tournament-card-title">
                            <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                        </h3>
                        
                        <div class="tournament-card-meta">
                            <div class="tournament-card-meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d M Y', strtotime($tournament['tournament_date'])); ?></span>
                            </div>
                            <div class="tournament-card-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>
                                    <?php echo date('h:i A', strtotime($tournament['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($tournament['end_time'])); ?>
                                </span>
                            </div>
                            <div class="tournament-card-meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars(substr($tournament['location'], 0, 30)) . '...'; ?></span>
                            </div>
                        </div>

                        <span class="badge badge-<?php echo $tournament['status']; ?>">
                            <?php echo ucfirst($tournament['status']); ?>
                        </span>

                        <div class="tournament-card-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $tournament['station_count']; ?></span>
                                <span class="stat-label">Stations</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $tournament['catch_count']; ?></span>
                                <span class="stat-label">Catches</span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-trophy"></i>
            <h3>No Tournaments Available</h3>
            <p>Create a tournament first to start recording fish catches</p>
            <a href="../tournament/createTournament.php" class="create-btn">
                <i class="fas fa-plus"></i> Create Tournament
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>