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
/* Calendar Container */
.calendar-wrapper {
    background: #F5EFE6;
    min-height: 70vh;
    padding: 30px 0;
}

.calendar-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(109, 148, 197, 0.1);
    padding: 25px;
    max-width: 1100px;
    margin: 0 auto;
}

/* Header */
.calendar-header {
    text-align: center;
    margin-bottom: 20px;
}

.calendar-header h1 {
    color: #6D94C5;
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
}

.calendar-header h1 i {
    margin-right: 8px;
}

/* Month Navigation */
.month-navigation {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 25px;
    margin-bottom: 20px;
    padding: 12px;
    background: #CBDCEB;
    border-radius: 8px;
}

.month-navigation .nav-btn {
    background: #6D94C5;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: background 0.3s;
}

.month-navigation .nav-btn:hover {
    background: #5a7ba8;
}

.current-month {
    font-size: 1.3rem;
    font-weight: 700;
    color: #6D94C5;
    min-width: 160px;
    text-align: center;
}

/* Legend */
.calendar-legend {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-bottom: 15px;
    padding: 8px;
    background: #F5EFE6;
    border-radius: 6px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #5a7ba8;
    font-weight: 600;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
}

/* Calendar Table */
.calendar {
    width: 100%;
    border-collapse: separate;
    border-spacing: 6px;
}

.calendar th {
    background: #6D94C5;
    color: white;
    padding: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    border-radius: 6px;
    text-transform: uppercase;
}

.calendar td {
    width: 14.28%;
    height: 80px;
    background: white;
    border: 2px solid #CBDCEB;
    border-radius: 6px;
    vertical-align: top;
    padding: 6px;
    transition: all 0.2s;
}

.calendar td:hover {
    border-color: #6D94C5;
    box-shadow: 0 2px 8px rgba(109, 148, 197, 0.15);
}

.calendar td.empty-day {
    background: #F5EFE6;
    border-color: #E8DFCA;
}

.calendar td.empty-day:hover {
    box-shadow: none;
}

/* Today Highlight */
.calendar td.today {
    background: #CBDCEB;
    border: 2px solid #6D94C5;
}

/* Day Number */
.day-number {
    font-weight: 700;
    font-size: 0.95rem;
    color: #6D94C5;
    margin-bottom: 5px;
}

.today .day-number {
    color: #5a7ba8;
}

.day-badge {
    background: #6D94C5;
    color: white;
    font-size: 8px;
    padding: 2px 5px;
    border-radius: 6px;
    font-weight: 600;
    margin-left: 4px;
}

/* Tournament Items */
.tournament-item {
    display: block;
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 10px;
    color: white;
    text-decoration: none;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 500;
    transition: opacity 0.2s;
}

.tournament-item:hover {
    opacity: 0.85;
}

/* Status Colors */
.status-upcoming {
    background: #6D94C5;
}

.status-ongoing {
    background: #d4a574;
}

.status-completed {
    background: #d9725d;
}

/* Tournament Count */
.tournament-count {
    position: absolute;
    top: 6px;
    right: 6px;
    background: #6D94C5;
    color: white;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
}

/* Responsive */
@media (max-width: 768px) {
    .calendar-container {
        padding: 15px;
    }
    
    .calendar {
        border-spacing: 4px;
    }
    
    .calendar th, .calendar td {
        height: 70px;
        font-size: 10px;
        padding: 5px;
    }
    
    .current-month {
        font-size: 1.1rem;
    }
    
    .tournament-item {
        font-size: 9px;
        padding: 3px 5px;
    }
    
    .day-number {
        font-size: 0.85rem;
    }
}
</style>

<div class="calendar-wrapper">
    <div class="container">
        <div class="calendar-container">
            <!-- Header -->
            <div class="calendar-header">
                <h1>
                    <i class="fas fa-calendar-alt"></i>
                    Tournament Calendar
                </h1>
            </div>

            <!-- Month Navigation -->
            <div class="month-navigation">
                <a href="?month=<?php echo $month == 1 ? 12 : $month-1; ?>&year=<?php echo $month == 1 ? $year-1 : $year; ?>" class="nav-btn">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <span class="current-month">
                    <?php echo date('F Y', strtotime("$year-$month-01")); ?>
                </span>
                <a href="?month=<?php echo $month == 12 ? 1 : $month+1; ?>&year=<?php echo $month == 12 ? $year+1 : $year; ?>" class="nav-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            </div>

            <!-- Calendar Table -->
            <table class="calendar">
                <tr>
                    <th>Mon</th>
                    <th>Tue</th>
                    <th>Wed</th>
                    <th>Thu</th>
                    <th>Fri</th>
                    <th>Sat</th>
                    <th>Sun</th>
                </tr>

                <?php
                $day = 1;
                $cells = 0;
                while ($day <= $total_days):
                    echo '<tr>';
                    for ($i = 1; $i <= 7; $i++):
                        $cells++;
                        if ($cells < $start_weekday || $day > $total_days) {
                            echo '<td class="empty-day"></td>';
                        } else {
                            // Check if this is today
                            $is_today = ($day == $today && $month == $current_month && $year == $current_year);
                            $today_class = $is_today ? 'today' : '';
                            
                            echo "<td class='{$today_class}'>";
                            echo '<div class="day-number">';
                            echo $day;
                            if ($is_today) {
                                echo '<span class="day-badge">TODAY</span>';
                            }
                            echo '</div>';
                            
                            if (isset($tournaments_by_day[$day])) {
                                $count = count($tournaments_by_day[$day]);
                                if ($count > 2) {
                                    echo "<span class='tournament-count'>{$count}</span>";
                                }
                                
                                foreach ($tournaments_by_day[$day] as $t) {
                                    $statusClass = '';
                                    switch ($t['status']) {
                                        case 'upcoming': $statusClass = 'status-upcoming'; break;
                                        case 'ongoing': $statusClass = 'status-ongoing'; break;
                                        case 'completed': $statusClass = 'status-completed'; break;
                                    }
                                    echo "<a href='" . SITE_URL . "/pages/tournament/tournament-details.php?id={$t['tournament_id']}' 
                                            class='tournament-item {$statusClass}' 
                                            title='" . htmlspecialchars($t['tournament_title']) . "'>";
                                    echo htmlspecialchars($t['tournament_title']);
                                    echo "</a>";
                                }
                            }
                            echo '</td>';
                            $day++;
                        }
                    endfor;
                    echo '</tr>';
                endwhile;
                ?>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>