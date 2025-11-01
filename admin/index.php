<?php
$page_title = 'Dashboard';
$page_description = 'Overview of your fishing tournament management';
include 'includes/header.php';

// Get statistics
$stats_queries = [
    'tournaments' => "SELECT COUNT(*) as total FROM TOURNAMENT",
    'users' => "SELECT COUNT(*) as total FROM USER WHERE role = 'angler'",
    'pending' => "SELECT COUNT(*) as total FROM TOURNAMENT_REGISTRATION WHERE approval_status = 'pending'",
    'catches' => "SELECT COUNT(*) as total FROM FISH_CATCH",
    'upcoming' => "SELECT COUNT(*) as total FROM TOURNAMENT WHERE status = 'upcoming'",
    'ongoing' => "SELECT COUNT(*) as total FROM TOURNAMENT WHERE status = 'ongoing'",
    'completed' => "SELECT COUNT(*) as total FROM TOURNAMENT WHERE status = 'completed'"
];

$stats = [];
foreach ($stats_queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    $stats[$key] = mysqli_fetch_assoc($result)['total'];
}

// Get recent tournaments
$recent_tournaments_query = "SELECT * FROM TOURNAMENT ORDER BY created_at DESC LIMIT 5";
$recent_tournaments = mysqli_query($conn, $recent_tournaments_query);
?>

<!-- Welcome Section -->
<div class="welcome-card">
    <div class="welcome-content">
        <h1>Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>! 👋</h1>
        <p>Here's what's happening with your fishing tournaments today.</p>
    </div>
    <div class="welcome-date">
        <i class="fas fa-calendar-day"></i>
        <?php echo date('l, F j, Y'); ?>
    </div>
</div>

<!-- Main Statistics Grid -->
<div class="stats-grid-main">
    <div class="stat-card-large">
        <div class="stat-card-icon tournaments">
            <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-number"><?php echo $stats['tournaments']; ?></div>
            <div class="stat-title">Total Tournaments</div>
            <div class="stat-trend">
                <span class="trend-up"><i class="fas fa-arrow-up"></i> Active</span>
            </div>
        </div>
    </div>

    <div class="stat-card-large">
        <div class="stat-card-icon users">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-number"><?php echo $stats['users']; ?></div>
            <div class="stat-title">Registered Anglers</div>
            <div class="stat-trend">
                <span class="trend-neutral"><i class="fas fa-user-check"></i> Members</span>
            </div>
        </div>
    </div>

    <div class="stat-card-large">
        <div class="stat-card-icon pending">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-title">Pending Approvals</div>
            <div class="stat-trend">
                <?php if ($stats['pending'] > 0): ?>
                    <span class="trend-warning"><i class="fas fa-exclamation-circle"></i> Needs attention</span>
                <?php else: ?>
                    <span class="trend-success"><i class="fas fa-check-circle"></i> All clear</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stat-card-large">
        <div class="stat-card-icon catches">
            <i class="fas fa-fish"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-number"><?php echo $stats['catches']; ?></div>
            <div class="stat-title">Total Catches</div>
            <div class="stat-trend">
                <span class="trend-up"><i class="fas fa-chart-line"></i> Growing</span>
            </div>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="dashboard-two-col">
    <!-- Recent Tournaments -->
    <div class="dashboard-section">
        <div class="section-header-modern">
            <div>
                <h2 class="section-title-modern">
                    <i class="fas fa-trophy"></i>
                    Recent Tournaments
                </h2>
                <p class="section-subtitle">Your latest fishing competitions</p>
            </div>
            <a href="tournament/tournamentList.php" class="btn btn-primary btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="tournaments-list">
            <?php if (mysqli_num_rows($recent_tournaments) > 0): ?>
                <?php while ($tournament = mysqli_fetch_assoc($recent_tournaments)): ?>
                    <div class="tournament-card-mini">
                        <div class="tournament-mini-date">
                            <div class="mini-date-day"><?php echo date('d', strtotime($tournament['tournament_date'])); ?></div>
                            <div class="mini-date-month"><?php echo strtoupper(date('M', strtotime($tournament['tournament_date']))); ?></div>
                        </div>
                        <div class="tournament-mini-content">
                            <h4 class="tournament-mini-title"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h4>
                            <div class="tournament-mini-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($tournament['location'], 0, 25)) . '...'; ?></span>
                                <span><i class="fas fa-users"></i> Max: <?php echo $tournament['max_participants']; ?></span>
                            </div>
                        </div>
                        <div class="tournament-mini-actions">
                            <span class="badge badge-<?php echo $tournament['status']; ?>"><?php echo ucfirst($tournament['status']); ?></span>
                            <div class="mini-action-btns">
                                <a href="<?php echo SITE_URL; ?>/pages/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" 
                                   class="btn-icon" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="tournament/editTournament.php?id=<?php echo $tournament['tournament_id']; ?>" 
                                   class="btn-icon" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state-mini">
                    <i class="fas fa-trophy"></i>
                    <p>No tournaments yet</p>
                    <a href="tournament/createTournament.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Create Tournament
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tournament Status Overview -->
    <div class="dashboard-section">
        <div class="section-header-modern">
            <div>
                <h2 class="section-title-modern">
                    <i class="fas fa-chart-pie"></i>
                    Tournament Status
                </h2>
                <p class="section-subtitle">Current tournament breakdown</p>
            </div>
        </div>

        <div class="status-overview">
            <div class="status-item upcoming">
                <div class="status-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="status-content">
                    <div class="status-number"><?php echo $stats['upcoming']; ?></div>
                    <div class="status-label">Upcoming</div>
                    <div class="status-bar">
                        <div class="status-bar-fill" style="width: <?php echo $stats['tournaments'] > 0 ? ($stats['upcoming'] / $stats['tournaments'] * 100) : 0; ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="status-item ongoing">
                <div class="status-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="status-content">
                    <div class="status-number"><?php echo $stats['ongoing']; ?></div>
                    <div class="status-label">Ongoing</div>
                    <div class="status-bar">
                        <div class="status-bar-fill" style="width: <?php echo $stats['tournaments'] > 0 ? ($stats['ongoing'] / $stats['tournaments'] * 100) : 0; ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="status-item completed">
                <div class="status-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="status-content">
                    <div class="status-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Completed</div>
                    <div class="status-bar">
                        <div class="status-bar-fill" style="width: <?php echo $stats['tournaments'] > 0 ? ($stats['completed'] / $stats['tournaments'] * 100) : 0; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Summary -->
        <div class="quick-stats-summary">
            <div class="summary-item">
                <i class="fas fa-trophy"></i>
                <span><?php echo $stats['tournaments']; ?> Total</span>
            </div>
            <div class="summary-item">
                <i class="fas fa-users"></i>
                <span><?php echo $stats['users']; ?> Anglers</span>
            </div>
            <div class="summary-item">
                <i class="fas fa-fish"></i>
                <span><?php echo $stats['catches']; ?> Catches</span>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>