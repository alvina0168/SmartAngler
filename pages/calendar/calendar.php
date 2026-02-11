<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$first_day_of_month = date("Y-m-01", strtotime("$year-$month-01"));
$total_days = date("t", strtotime($first_day_of_month));
$start_weekday = date("N", strtotime($first_day_of_month));

$today = date('j');
$current_month = date('n');
$current_year = date('Y');

$start_date = $first_day_of_month;
$end_date = date("Y-m-t", strtotime($first_day_of_month));

$stmt = $conn->prepare("SELECT * FROM TOURNAMENT WHERE tournament_date BETWEEN ? AND ? ORDER BY tournament_date ASC");
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
    --cyan: #06B6D4;
    --orange: #F97316;
    --green: #10B981;
}

.calendar-hero {
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    padding: 50px 60px;
}

.hero-title {
    font-size: 42px;
    font-weight: 800;
    color: white;
    margin: 0 0 8px;
}

.hero-subtitle {
    font-size: 16px;
    color: rgba(255,255,255,0.9);
    margin: 0;
}

.calendar-page {
    background: #FAFAFA;
    padding: 30px 60px 50px;
}

.nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    margin-bottom: 20px;
}

.month-display {
    font-size: 24px;
    font-weight: 700;
    color: #1F2937;
}

.nav-buttons {
    display: flex;
    gap: 8px;
}

.nav-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    color: #6B7280;
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    text-decoration: none;
    transition: 0.2s;
}

.nav-btn:hover {
    background: #F9FAFB;
    border-color: var(--ocean-light);
    color: var(--ocean-light);
}

.calendar-legend {
    display: flex;
    justify-content: center;
    gap: 24px;
    padding: 12px;
    background: white;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #E5E7EB;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.legend-item.upcoming { color: var(--cyan); }
.legend-item.ongoing { color: var(--orange); }
.legend-item.completed { color: var(--green); }

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.dot-upcoming { background: var(--cyan); }
.dot-ongoing { background: var(--orange); }
.dot-completed { background: var(--green); }

.calendar-grid {
    background: #F3F4F6;
    border: 2px solid #9CA3AF;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.calendar-table thead th {
    width: 14.28%;
    padding: 12px 8px;
    text-align: center;
    font-size: 15px;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    background: #E5E7EB;
    border-bottom: 2px solid #9CA3AF;
    border-right: 2px solid #9CA3AF;
}

.calendar-table thead th:last-child {
    border-right: none;
}

.calendar-table tbody td {
    width: 14.28%;
    padding: 8px;
    height: 110px;
    border-right: 2px solid #9CA3AF;
    border-bottom: 2px solid #9CA3AF;
    background: white;
    vertical-align: top;
    position: relative;
}

.calendar-table tbody td:last-child {
    border-right: none;
}

.calendar-table tbody tr:last-child td {
    border-bottom: none;
}

.calendar-table tbody td:hover {
    background: #F9FAFB;
}

.calendar-table tbody td.empty-cell {
    background: #F3F4F6;
}

.calendar-table tbody td.empty-cell:hover {
    background: #F3F4F6;
}

.calendar-table tbody td.today {
    background: #EFF6FF;
}

.day-cell {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.day-number {
    font-size: 20px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

.today .day-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: #3B82F6;
    color: white;
    border-radius: 50%;
}

.tournament-list {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.tournament-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 15px;
    font-weight: 500;
    color: #374151;
    text-decoration: none;
    transition: 0.2s;
    border-left: 3px solid;
}

.tournament-badge:hover {
    background: #799adb;
}

.badge-upcoming { 
    background: #E0F2FE;
    border-left-color: var(--cyan);
}

.badge-ongoing { 
    background: #FFF7ED;
    border-left-color: var(--orange);
}

.badge-completed { 
    background: #D1FAE5;
    border-left-color: var(--green);
}

.badge-icon {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.badge-icon.upcoming { background: var(--cyan); }
.badge-icon.ongoing { background: var(--orange); }
.badge-icon.completed { background: var(--green); }

.badge-text {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.more-link {
    font-size: 10px;
    color: #6B7280;
    margin-top: 2px;
    text-decoration: none;
}

.more-link:hover {
    color: var(--ocean-light);
    text-decoration: underline;
}

@media (max-width: 768px) {
    .calendar-hero, .calendar-page {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .hero-title { font-size: 32px; }
    .calendar-table tbody td { height: 100px; padding: 6px; }
    .day-number { font-size: 12px; }
    .tournament-badge { font-size: 9px; padding: 3px 5px; }
}
</style>

<div class="calendar-hero">
    <h1 class="hero-title">Tournament Calendar</h1>
    <p class="hero-subtitle">View and track upcoming fishing tournaments</p>
</div>

<!-- Calendar -->
<div class="calendar-page">
    <div class="nav-bar">
        <div class="month-display">
            <?php echo date('F Y', strtotime("$year-$month-01")); ?>
        </div>
        <div class="nav-buttons">
            <a href="?month=<?php echo $month == 1 ? 12 : $month-1; ?>&year=<?php echo $month == 1 ? $year-1 : $year; ?>" class="nav-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <a href="?month=<?php echo $month == 12 ? 1 : $month+1; ?>&year=<?php echo $month == 12 ? $year+1 : $year; ?>" class="nav-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>

    <!-- Legend -->
    <div class="calendar-legend">
        <div class="legend-item upcoming">
            <span class="legend-dot dot-upcoming"></span>
            <span>Upcoming</span>
        </div>
        <div class="legend-item ongoing">
            <span class="legend-dot dot-ongoing"></span>
            <span>Ongoing</span>
        </div>
        <div class="legend-item completed">
            <span class="legend-dot dot-completed"></span>
            <span>Completed</span>
        </div>
    </div>

    <div class="calendar-grid">
        <table class="calendar-table">
            <thead>
                <tr>
                    <th>Mon</th>
                    <th>Tue</th>
                    <th>Wed</th>
                    <th>Thu</th>
                    <th>Fri</th>
                    <th>Sat</th>
                    <th>Sun</th>
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
                        
                        if ($cells < $start_weekday || $day > $total_days) {
                            echo '<td class="empty-cell"></td>';
                        } else {
                            $is_today = ($day == $today && $month == $current_month && $year == $current_year);
                            $today_class = $is_today ? 'today' : '';
                            
                            echo "<td class='{$today_class}'>";
                            echo '<div class="day-cell">';
                            echo '<div class="day-number">' . $day . '</div>';
                            
                            if (isset($tournaments_by_day[$day])) {
                                $count = count($tournaments_by_day[$day]);
                                echo '<div class="tournament-list">';
                                
                                $shown = 0;
                                foreach ($tournaments_by_day[$day] as $t) {
                                    if ($shown >= 3) break;
                                    
                                    $badge_class = "badge-{$t['status']}";
                                    $icon_class = $t['status'];
                                    
                                    echo "<a href='" . SITE_URL . "/pages/tournament/tournament-details.php?id={$t['tournament_id']}' 
                                            class='tournament-badge {$badge_class}' 
                                            title='" . htmlspecialchars($t['tournament_title']) . "'>";
                                    echo "<span class='badge-icon {$icon_class}'></span>";
                                    echo "<span class='badge-text'>" . htmlspecialchars($t['tournament_title']) . "</span>";
                                    echo "</a>";
                                    $shown++;
                                }
                                
                                if ($count > 3) {
                                    echo "<a href='#' class='more-link'>+ " . ($count - 3) . " more</a>";
                                }
                                
                                echo '</div>';
                            }
                            
                            echo '</div></td>';
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

<?php include '../../includes/footer.php'; ?>