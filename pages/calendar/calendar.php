<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// First day of the month and total days
$first_day_of_month = date("Y-m-01", strtotime("$year-$month-01"));
$total_days = date("t", strtotime($first_day_of_month));
$start_weekday = date("N", strtotime($first_day_of_month)); // 1 (Mon) to 7 (Sun)

// Get today's date for highlighting
$today = date('j');
$current_month = date('n');
$current_year = date('Y');

// Fetch tournaments for the month using mysqli
$start_date = $first_day_of_month;
$end_date = date("Y-m-t", strtotime($first_day_of_month));

$stmt = $conn->prepare("SELECT * FROM TOURNAMENT 
                        WHERE tournament_date BETWEEN ? AND ? 
                        ORDER BY tournament_date ASC");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$tournaments_by_day = [];
while ($t = $result->fetch_assoc()) {
    $day = intval(date('j', strtotime($t['tournament_date'])));
    $tournaments_by_day[$day][] = $t;
}

$page_title = 'Tournament Calendar';
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

/* Hero Section with Gradient */
.calendar-hero {
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


/* Calendar Container - Full Width */
.calendar-page {
    background: var(--white);
    padding: 40px 0 60px;
}

.calendar-container {
    max-width: 100%;
    padding: 0 60px;
}

/* Navigation Bar */
.nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: var(--white);
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    border: 1px solid var(--border);
}

.month-display {
    font-size: 28px;
    font-weight: 700;
    color: var(--ocean-blue);
}

.nav-buttons {
    display: flex;
    gap: 12px;
}

.nav-btn {
    padding: 10px 20px;
    background: var(--ocean-light);
    color: var(--white);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.nav-btn:hover {
    background: var(--ocean-blue);
}

.btn-today {
    background: #F3F4F6;
    color: var(--ocean-blue);
}

.btn-today:hover {
    background: var(--ocean-light);
    color: var(--white);
}

/* Legend */
.calendar-legend {
    display: flex;
    justify-content: center;
    gap: 24px;
    padding: 16px;
    background: var(--sand);
    border-radius: 12px;
    margin-bottom: 24px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-dark);
    font-weight: 600;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.dot-upcoming { background: var(--ocean-light); }
.dot-ongoing { background: #F59E0B; }
.dot-completed { background: #10B981; }

/* Calendar Grid - Full Width */
.calendar-grid {
    background: var(--white);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--border);
}

.calendar-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.calendar-table thead th {
    padding: 16px 8px;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--sand);
}

.calendar-table tbody td {
    padding: 0;
    height: 110px;
    border: 1px solid var(--border);
    background: var(--white);
    vertical-align: top;
    position: relative;
    transition: all 0.2s ease;
}

.calendar-table tbody td:hover {
    background: var(--sand);
    box-shadow: inset 0 0 0 2px var(--ocean-light);
}

.calendar-table tbody td.empty-cell {
    background: #FAFAFA;
    border-color: #F3F4F6;
}

.calendar-table tbody td.empty-cell:hover {
    background: #FAFAFA;
    box-shadow: none;
}

/* Day Cell */
.day-cell {
    height: 100%;
    padding: 8px;
    display: flex;
    flex-direction: column;
}

.day-number {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Today Highlight */
.calendar-table tbody td.today {
    background: linear-gradient(135deg, rgba(8, 131, 149, 0.1) 0%, rgba(5, 191, 219, 0.1) 100%);
    border: 2px solid var(--ocean-light);
}

.today .day-number {
    color: var(--ocean-blue);
}

.today-badge {
    background: var(--ocean-light);
    color: var(--white);
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
    text-transform: uppercase;
}

/* Tournament List in Cell */
.tournament-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow: hidden;
}

.tournament-badge {
    display: block;
    padding: 4px 6px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
    color: var(--white);
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: opacity 0.2s;
}

.tournament-badge:hover {
    opacity: 0.8;
}

.badge-upcoming { background: var(--ocean-light); }
.badge-ongoing { background: #F59E0B; }
.badge-completed { background: #10B981; }

/* Tournament Count Badge */
.event-count {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--ocean-blue);
    color: var(--white);
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(10, 77, 104, 0.3);
}

/* Tournament Dots */
.tournament-dots {
    display: flex;
    gap: 3px;
    flex-wrap: wrap;
    margin-top: auto;
}

.tournament-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.dot-upcoming-mini { background: var(--ocean-light); }
.dot-ongoing-mini { background: #F59E0B; }
.dot-completed-mini { background: #10B981; }

/* Weekend Styling */
.calendar-table tbody td.weekend {
    background: #FAFAFA;
}

/* Responsive */
@media (max-width: 1400px) {
    .calendar-container,
    .filter-section,
    .hero-content {
        padding-left: 40px;
        padding-right: 40px;
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }
    
    .calendar-container,
    .filter-section,
    .hero-content {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .nav-bar {
        flex-direction: column;
        gap: 16px;
    }
    
    .calendar-table tbody td {
        height: 90px;
    }
    
    .day-number {
        font-size: 14px;
    }

}
</style>

<!-- Hero Section -->
<div class="calendar-hero">
    <div class="hero-content">
        <h1 class="hero-title">Fishing Tournament Schedule</h1>
        <p class="hero-subtitle">Track tournament dates and plan your next fishing challenge</p>
    </div>
</div>

<!-- Calendar Page -->
<div class="calendar-page">
    <div class="calendar-container">
        <!-- Navigation Bar -->
        <div class="nav-bar">
            <div class="month-display">
                <?php echo date('F Y', strtotime("$year-$month-01")); ?>
            </div>
            <div class="nav-buttons">
                <a href="?month=<?php echo $month == 1 ? 12 : $month-1; ?>&year=<?php echo $month == 1 ? $year-1 : $year; ?>" class="nav-btn">
                    <i class="fas fa-chevron-left"></i> Prev
                </a>
                <a href="?" class="nav-btn btn-today">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
                <a href="?month=<?php echo $month == 12 ? 1 : $month+1; ?>&year=<?php echo $month == 12 ? $year+1 : $year; ?>" class="nav-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>

        <!-- Legend -->
        <div class="calendar-legend">
            <div class="legend-item">
                <span class="legend-dot dot-upcoming"></span>
                <span>Upcoming</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot dot-ongoing"></span>
                <span>Ongoing</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot dot-completed"></span>
                <span>Completed</span>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid">
            <table class="calendar-table">
                <thead>
                    <tr>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                        <th>Sunday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $day = 1;
                    $cells = 0;
                    while ($day <= $total_days):
                        echo '<tr>';
                        for ($i = 1; $i <= 7; $i++):
                            $cells++;
                            $is_weekend = ($i == 6 || $i == 7);
                            
                            if ($cells < $start_weekday || $day > $total_days) {
                                echo '<td class="empty-cell"></td>';
                            } else {
                                // Check if this is today
                                $is_today = ($day == $today && $month == $current_month && $year == $current_year);
                                $today_class = $is_today ? 'today' : '';
                                $weekend_class = $is_weekend ? 'weekend' : '';
                                
                                echo "<td class='{$today_class} {$weekend_class}'>";
                                echo '<div class="day-cell">';
                                
                                // Day number
                                echo '<div class="day-number">';
                                echo $day;
                                if ($is_today) {
                                    echo '<span class="today-badge">Today</span>';
                                }
                                echo '</div>';
                                
                                // Tournaments
                                if (isset($tournaments_by_day[$day])) {
                                    $count = count($tournaments_by_day[$day]);
                                    
                                    // Show count badge if more than 2
                                    if ($count > 2) {
                                        echo "<span class='event-count'>{$count}</span>";
                                    }
                                    
                                    // Show first 2 tournaments as badges
                                    echo '<div class="tournament-list">';
                                    $shown = 0;
                                    foreach ($tournaments_by_day[$day] as $t) {
                                        if ($shown >= 2) break;
                                        
                                        $badge_class = '';
                                        switch ($t['status']) {
                                            case 'upcoming': $badge_class = 'badge-upcoming'; break;
                                            case 'ongoing': $badge_class = 'badge-ongoing'; break;
                                            case 'completed': $badge_class = 'badge-completed'; break;
                                        }
                                        
                                        echo "<a href='" . SITE_URL . "/pages/tournament/tournament-details.php?id={$t['tournament_id']}' 
                                                class='tournament-badge {$badge_class}' 
                                                title='" . htmlspecialchars($t['tournament_title']) . "'>";
                                        echo htmlspecialchars($t['tournament_title']);
                                        echo "</a>";
                                        $shown++;
                                    }
                                    echo '</div>';
                                    
                                    // Show dots for remaining tournaments
                                    if ($count > 2) {
                                        echo '<div class="tournament-dots">';
                                        for ($j = 0; $j < min($count - 2, 5); $j++) {
                                            $t = $tournaments_by_day[$day][$shown + $j];
                                            $dot_class = '';
                                            switch ($t['status']) {
                                                case 'upcoming': $dot_class = 'dot-upcoming-mini'; break;
                                                case 'ongoing': $dot_class = 'dot-ongoing-mini'; break;
                                                case 'completed': $dot_class = 'dot-completed-mini'; break;
                                            }
                                            echo "<span class='tournament-dot {$dot_class}'></span>";
                                        }
                                        echo '</div>';
                                    }
                                }
                                
                                echo '</div>'; // .day-cell
                                echo '</td>';
                                $day++;
                            }
                        endfor;
                        echo '</tr>';
                    endwhile;
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>