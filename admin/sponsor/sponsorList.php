<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Sponsor Management';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Fetch tournament info
$tournament_query = "SELECT tournament_title FROM TOURNAMENT WHERE tournament_id = $tournament_id";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Fetch sponsors for this tournament
$sponsors_query = "
    SELECT s.*,
           COUNT(DISTINCT tp.prize_id) as prize_count
    FROM SPONSOR s
    LEFT JOIN TOURNAMENT_PRIZE tp ON s.sponsor_id = tp.sponsor_id
    WHERE s.tournament_id = $tournament_id
    GROUP BY s.sponsor_id
    ORDER BY s.sponsor_name ASC
";
$sponsors_result = mysqli_query($conn, $sponsors_query);

// Calculate total sponsorship
$total_amount_query = "SELECT SUM(sponsored_amount) as total FROM SPONSOR WHERE tournament_id = $tournament_id";
$total_result = mysqli_query($conn, $total_amount_query);
$total_amount = mysqli_fetch_assoc($total_result)['total'] ?? 0;

include '../includes/header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="../tournament/viewTournament.php?id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Tournament
    </a>
</div>

<!-- Header Section -->
<div class="section">
    <div class="section-header">
        <div>
            <h2 class="section-title">
                <i class="fas fa-handshake"></i> Sponsor Management
            </h2>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
            </p>
        </div>
        <a href="addSponsor.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Add Sponsor
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="dashboard-stats" style="margin-bottom: 0;">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6D94C5, #CBDCEB);">
                    <i class="fas fa-building"></i>
                </div>
            </div>
            <div class="stat-value"><?= mysqli_num_rows($sponsors_result) ?></div>
            <div class="stat-label">Total Sponsors</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4caf50, #66bb6a);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-value">RM <?= number_format($total_amount, 2) ?></div>
            <div class="stat-label">Total Sponsorship</div>
        </div>
    </div>
</div>

<!-- Sponsors List -->
<?php if (mysqli_num_rows($sponsors_result) > 0): ?>
    <div class="section">
        <table class="table">
            <thead>
                <tr>
                    <th>Logo</th>
                    <th>Sponsor Name</th>
                    <th>Contact</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($sponsor = mysqli_fetch_assoc($sponsors_result)): ?>
                <tr>
                    <td>
                        <?php if (!empty($sponsor['sponsor_logo'])): ?>
                            <img src="../../assets/images/sponsors/<?= htmlspecialchars($sponsor['sponsor_logo']) ?>" 
                                 alt="Logo" 
                                 style="width: 50px; height: 50px; border-radius: 8px; object-fit: contain; background: #f8f9fa; padding: 4px;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; border-radius: 8px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-building" style="color: #dee2e6; font-size: 1.5rem;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #1a1a1a; margin-bottom: 0.25rem;">
                            <?= htmlspecialchars($sponsor['sponsor_name']) ?>
                        </div>
                        <?php if (!empty($sponsor['sponsor_description'])): ?>
                            <div style="font-size: 0.875rem; color: #6c757d; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($sponsor['sponsor_description']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($sponsor['contact_phone'])): ?>
                            <div style="font-size: 0.875rem; margin-bottom: 0.25rem;">
                                <i class="fas fa-phone" style="color: var(--color-blue-primary); margin-right: 0.375rem;"></i>
                                <?= htmlspecialchars($sponsor['contact_phone']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($sponsor['contact_email'])): ?>
                            <div style="font-size: 0.875rem;">
                                <i class="fas fa-envelope" style="color: var(--color-blue-primary); margin-right: 0.375rem;"></i>
                                <?= htmlspecialchars($sponsor['contact_email']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 700; font-size: 1.125rem; color: #4caf50;">
                            RM <?= number_format($sponsor['sponsored_amount'], 2) ?>
                        </div>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="editSponsor.php?id=<?= $sponsor['sponsor_id'] ?>" 
                               class="btn btn-primary btn-sm" 
                               title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button"
                                    onclick="if(confirm('Delete this sponsor?')) { window.location.href='deleteSponsor.php?id=<?= $sponsor['sponsor_id'] ?>&tournament_id=<?= $tournament_id ?>'; }" 
                                    class="btn btn-danger btn-sm" 
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-handshake"></i>
        <h3>No Sponsors Yet</h3>
        <p>Add sponsors to recognize their support for this tournament</p>
        <a href="addSponsor.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add First Sponsor
        </a>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>