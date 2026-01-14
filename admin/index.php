<?php
$page_title = 'Dashboard';
$page_description = 'Overview of your fishing tournament management';
include 'includes/header.php';

// Get statistics
$stats_queries = [
    'tournaments' => "SELECT COUNT(*) as total FROM TOURNAMENT",
    'active_tournaments' => "SELECT COUNT(*) as total FROM TOURNAMENT WHERE status = 'ongoing'",
    'pending' => "SELECT COUNT(*) as total FROM TOURNAMENT_REGISTRATION WHERE approval_status = 'pending'",
    'recent_registrations' => "SELECT COUNT(*) as total FROM TOURNAMENT_REGISTRATION WHERE DATE(registration_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    'upcoming' => "SELECT COUNT(*) as total FROM TOURNAMENT WHERE status = 'upcoming'",
    'ongoing' => "SELECT COUNT(*) as total FROM TOURNAMENT WHERE status = 'ongoing'",
    'completed' => "SELECT COUNT(*) as total FROM TOURNAMENT WHERE status = 'completed'",
    'total_participants' => "SELECT COUNT(*) as total FROM TOURNAMENT_REGISTRATION WHERE approval_status = 'approved'"
];

$stats = [];
foreach ($stats_queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    $stats[$key] = mysqli_fetch_assoc($result)['total'];
}

// Get all recent activity
$recent_activity_query = "
    SELECT 
        tr.registration_date,
        tr.approval_status,
        u.full_name,
        t.tournament_title,
        t.tournament_id
    FROM TOURNAMENT_REGISTRATION tr
    JOIN USER u ON tr.user_id = u.user_id
    JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
    ORDER BY tr.registration_date DESC
";
$recent_activity = mysqli_query($conn, $recent_activity_query);
$total_activities = mysqli_num_rows($recent_activity);
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
            <div class="stat-title">Total Tournaments</div>
            <div class="stat-number"><?php echo $stats['tournaments']; ?></div>
            <div class="stat-trend">
            </div>
        </div>
    </div>

    <div class="stat-card-large">
        <div class="stat-card-icon ongoing">
            <i class="fas fa-play-circle"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-title">Active Tournaments</div>
            <div class="stat-number"><?php echo $stats['active_tournaments']; ?></div>
            <div class="stat-trend">
            </div>
        </div>
    </div>

    <div class="stat-card-large">
        <div class="stat-card-icon pending">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-title">Pending Approvals</div>
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-trend">
            </div>
        </div>
    </div>

    <div class="stat-card-large">
        <div class="stat-card-icon users">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-title">New Registrations</div>
            <div class="stat-number"><?php echo $stats['recent_registrations']; ?></div>
        </div>
    </div>
</div>

<!-- Two Column Layout: Recent Activity (Left) + Tournament Status (Right) -->
<div class="dashboard-two-col">
    <!-- Left Column: Recent Activity -->
    <div class="dashboard-section">
        <div class="section-header-modern">
            <div>
                <h2 class="section-title-modern">
                    <i class="fas fa-bell"></i>
                    Recent Activity
                </h2>
            </div>
            <?php if ($stats['pending'] > 0): ?>
                <a href="participant/manageParticipants.php" class="btn btn-warning btn-sm">
                    <i class="fas fa-user-check"></i> Review
                </a>
            <?php endif; ?>
        </div>

        <div class="activity-feed" id="activityFeed">
            <?php if ($total_activities > 0): ?>
                <?php 
                $count = 0;
                mysqli_data_seek($recent_activity, 0);
                while ($activity = mysqli_fetch_assoc($recent_activity)): 
                    $count++;
                    $hidden_class = $count > 5 ? 'activity-hidden' : '';
                ?>
                    <div class="activity-card <?php echo $hidden_class; ?>">
                        <div class="activity-icon <?php echo $activity['approval_status']; ?>">
                            <?php 
                            if ($activity['approval_status'] == 'approved') echo '<i class="fas fa-check-circle"></i>';
                            elseif ($activity['approval_status'] == 'pending') echo '<i class="fas fa-hourglass-half"></i>';
                            else echo '<i class="fas fa-times-circle"></i>';
                            ?>
                        </div>
                        <div class="activity-details">
                            <div class="activity-user">
                                <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                registered for 
                                <a href="tournament/viewTournament.php?id=<?php echo $activity['tournament_id']; ?>">
                                    <?php echo htmlspecialchars($activity['tournament_title']); ?>
                                </a>
                            </div>
                            <div class="activity-meta">
                                <span class="badge badge-<?php echo $activity['approval_status']; ?>">
                                    <?php echo ucfirst($activity['approval_status']); ?>
                                </span>
                                <span class="activity-time">
                                    <?php 
                                    $time_diff = time() - strtotime($activity['registration_date']);
                                    if ($time_diff < 3600) echo floor($time_diff / 60) . ' min ago';
                                    elseif ($time_diff < 86400) echo floor($time_diff / 3600) . ' hours ago';
                                    else echo floor($time_diff / 86400) . ' days ago';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state-mini">
                    <i class="fas fa-bell-slash"></i>
                    <p>No recent activity</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_activities > 5): ?>
            <div class="view-more-container">
                <button type="button" id="viewMoreBtn" class="view-more-btn" onclick="toggleActivities()">
                    <i class="fas fa-chevron-down"></i>
                    <span id="viewMoreText">View More (<?php echo $total_activities - 5; ?> more)</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Tournament Status -->
    <div class="dashboard-section">
        <div class="section-header-modern">
            <div>
                <h2 class="section-title-modern">
                    <i class="fas fa-chart-pie"></i>
                    Tournament Status
                </h2>
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
                    <div class="status-label">Completed</div>
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
                <span><?php echo $stats['total_participants']; ?> Participants</span>
            </div>
            <div class="summary-item">
                <i class="fas fa-user-check"></i>
                <span><?php echo $stats['recent_registrations']; ?> New (7d)</span>
            </div>
        </div>
    </div>
</div>

<script>
let isExpanded = false;

function toggleActivities() {
    const hiddenActivities = document.querySelectorAll('.activity-hidden');
    const btn = document.getElementById('viewMoreBtn');
    const btnText = document.getElementById('viewMoreText');
    const btnIcon = btn.querySelector('i');
    
    if (!isExpanded) {
        // Show all activities
        hiddenActivities.forEach(activity => {
            activity.style.display = 'flex';
        });
        btnIcon.className = 'fas fa-chevron-up';
        btnText.textContent = 'Show Less';
        btn.classList.add('view-less-btn');
        isExpanded = true;
    } else {
        // Hide activities after first 5
        hiddenActivities.forEach(activity => {
            activity.style.display = 'none';
        });
        btnIcon.className = 'fas fa-chevron-down';
        btnText.textContent = 'View More (<?php echo $total_activities - 5; ?> more)';
        btn.classList.remove('view-less-btn');
        isExpanded = false;
    }
}

// Hide activities on page load
document.addEventListener('DOMContentLoaded', function() {
    const hiddenActivities = document.querySelectorAll('.activity-hidden');
    hiddenActivities.forEach(activity => {
        activity.style.display = 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>