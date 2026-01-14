<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Require admin login
requireLogin();
if (!isAdmin()) {
    redirect(SITE_URL . '/index.php');
}

$page_title = 'Revenue Overview';
$page_description = 'Tournament revenue statistics';

include '../includes/header.php';

/* =========================
   REVENUE CALCULATIONS
========================= */

// Total Revenue
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(t.tournament_fee), 0) AS total
    FROM TOURNAMENT_REGISTRATION tr
    JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
    WHERE tr.approval_status = 'approved'
"))['total'];

// Weekly Revenue
$weekly_revenue = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(t.tournament_fee), 0) AS total
    FROM TOURNAMENT_REGISTRATION tr
    JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
    WHERE tr.approval_status = 'approved'
    AND tr.registration_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
"))['total'];

// Monthly Revenue
$monthly_revenue = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(t.tournament_fee), 0) AS total
    FROM TOURNAMENT_REGISTRATION tr
    JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
    WHERE tr.approval_status = 'approved'
    AND MONTH(tr.registration_date) = MONTH(CURDATE())
    AND YEAR(tr.registration_date) = YEAR(CURDATE())
"))['total'];

// Revenue per tournament
$revenue_per_tournament = mysqli_query($conn, "
    SELECT 
        t.tournament_title,
        t.tournament_fee,
        COUNT(tr.registration_id) AS total_participants,
        (COUNT(tr.registration_id) * t.tournament_fee) AS total_revenue
    FROM TOURNAMENT t
    LEFT JOIN TOURNAMENT_REGISTRATION tr 
        ON t.tournament_id = tr.tournament_id
        AND tr.approval_status = 'approved'
    GROUP BY t.tournament_id
    ORDER BY total_revenue DESC
");
?>

<style>
/* ===== Revenue Page Styles ===== */
.revenue-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.revenue-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    transition: 0.3s ease;
}

.revenue-card:hover {
    transform: translateY(-4px);
}

.revenue-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: #fff;
}

.bg-total { background: #0A4D68; }
.bg-week { background: #088395; }
.bg-month { background: #05BFDB; }

.revenue-info h3 {
    font-size: 24px;
    margin: 0;
    font-weight: 800;
}

.revenue-info span {
    color: #6B7280;
    font-size: 14px;
}

/* Table */
.revenue-table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

.revenue-table thead {
    background: #0A4D68;
    color: white;
}

.revenue-table th,
.revenue-table td {
    padding: 14px 16px;
    text-align: left;
}

.revenue-table tbody tr {
    border-bottom: 1px solid #eee;
}

.revenue-table tbody tr:hover {
    background: #f4f9fb;
}

.badge-money {
    background: #D1FAE5;
    color: #065F46;
    padding: 6px 10px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 13px;
}

/* Responsive */
@media (max-width: 768px) {
    .revenue-info h3 {
        font-size: 20px;
    }
}
</style>

<!-- Page Header -->
<div class="welcome-card">
    <div class="welcome-content">
        <h1>Revenue Overview 💰</h1>
        <p>Monitor earnings from tournament registrations</p>
    </div>
    <div class="welcome-date">
        <i class="fas fa-calendar-day"></i>
        <?php echo date('l, F j, Y'); ?>
    </div>
</div>

<!-- Revenue Summary -->
<div class="revenue-cards">
    <div class="revenue-card">
        <div class="revenue-icon bg-total"><i class="fas fa-wallet"></i></div>
        <div class="revenue-info">
            <h3>RM <?php echo number_format($total_revenue, 2); ?></h3>
            <span>Total Revenue</span>
        </div>
    </div>

    <div class="revenue-card">
        <div class="revenue-icon bg-week"><i class="fas fa-calendar-week"></i></div>
        <div class="revenue-info">
            <h3>RM <?php echo number_format($weekly_revenue, 2); ?></h3>
            <span>Last 7 Days</span>
        </div>
    </div>

    <div class="revenue-card">
        <div class="revenue-icon bg-month"><i class="fas fa-calendar-alt"></i></div>
        <div class="revenue-info">
            <h3>RM <?php echo number_format($monthly_revenue, 2); ?></h3>
            <span>This Month</span>
        </div>
    </div>
</div>

<!-- Revenue Table -->
<div class="dashboard-section">
    <h2 class="section-title-modern">
        <i class="fas fa-chart-line"></i> Revenue Per Tournament
    </h2>

    <table class="revenue-table">
        <thead>
            <tr>
                <th>Tournament</th>
                <th>Fee (RM)</th>
                <th>Approved Anglers</th>
                <th>Total Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($revenue_per_tournament)): ?>
            <tr>
                <td><?= htmlspecialchars($row['tournament_title']); ?></td>
                <td>RM <?= number_format($row['tournament_fee'], 2); ?></td>
                <td><?= $row['total_participants']; ?></td>
                <td><span class="badge-money">RM <?= number_format($row['total_revenue'], 2); ?></span></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

