<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

$user_id = $_SESSION['user_id'];

// Get all registrations for this user
$query = "SELECT tr.*, t.tournament_title, t.tournament_date, t.start_time, t.end_time, 
          t.location, t.tournament_fee, t.image, t.status as tournament_status,
          fs.spot_id, z.zone_name
          FROM TOURNAMENT_REGISTRATION tr
          JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
          LEFT JOIN FISHING_SPOT fs ON tr.spot_id = fs.spot_id
          LEFT JOIN ZONE z ON fs.zone_id = z.zone_id
          WHERE tr.user_id = ?
          ORDER BY tr.registration_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$registrations = [];
while ($row = $result->fetch_assoc()) {
    $registrations[] = $row;
}
$stmt->close();

$page_title = 'My Registrations';
include '../includes/header.php';
?>

<style>
.registrations-section {
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

.page-header p {
    color: #666;
    font-size: 16px;
}

.registrations-grid {
    display: grid;
    gap: 25px;
}

.registration-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.registration-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.card-content {
    display: grid;
    grid-template-columns: 200px 1fr auto;
    gap: 20px;
    padding: 20px;
}

.tournament-image {
    width: 200px;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
}

.tournament-info h3 {
    color: #6D94C5;
    font-size: 20px;
    margin-bottom: 10px;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 14px;
    color: #666;
}

.info-row i {
    width: 20px;
    color: #6D94C5;
}

.card-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: flex-end;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending {
    background: #FFF3CD;
    color: #856404;
}

.status-approved {
    background: #D4EDDA;
    color: #155724;
}

.status-rejected {
    background: #F8D7DA;
    color: #721C24;
}

.action-btn {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-view {
    background: #6D94C5;
    color: white;
}

.btn-view:hover {
    background: #5a7ea8;
}

.btn-cancel {
    background: #E74C3C;
    color: white;
}

.btn-cancel:hover {
    background: #C0392B;
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

@media (max-width: 992px) {
    .card-content {
        grid-template-columns: 1fr;
    }
    
    .tournament-image {
        width: 100%;
        height: 200px;
    }
    
    .card-actions {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
</style>

<section class="registrations-section">
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-clipboard-list"></i> My Tournament Registrations</h1>
            <p>Track and manage your tournament registrations</p>
        </div>

        <?php if ($registrations && count($registrations) > 0): ?>
            <div class="registrations-grid">
                <?php foreach ($registrations as $reg): ?>
                    <div class="registration-card">
                        <div class="card-content">
                            <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($reg['image']) ? $reg['image'] : 'default-tournament.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($reg['tournament_title']); ?>" 
                                 class="tournament-image"
                                 onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">

                            <div class="tournament-info">
                                <h3><?php echo htmlspecialchars($reg['tournament_title']); ?></h3>
                                
                                <div class="info-row">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo formatDate($reg['tournament_date']); ?></span>
                                </div>

                                <div class="info-row">
                                    <i class="fas fa-clock"></i>
                                    <span>
                                        <?php echo date('g:i A', strtotime($reg['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($reg['end_time'])); ?>
                                    </span>
                                </div>

                                <div class="info-row">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($reg['location']); ?></span>
                                </div>

                                <div class="info-row">
                                    <i class="fas fa-map-marked-alt"></i>
                                    <span><strong>Fishing Spot:</strong> <?php echo $reg['zone_name'] ?? 'N/A'; ?> - Spot <?php echo $reg['spot_id'] ?? 'N/A'; ?></span>
                                </div>

                                <div class="info-row">
                                    <i class="fas fa-calendar-check"></i>
                                    <span><strong>Registered:</strong> <?php echo date('M d, Y', strtotime($reg['registration_date'])); ?></span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <span class="status-badge status-<?php echo $reg['approval_status']; ?>">
                                    <?php 
                                    if ($reg['approval_status'] == 'pending') {
                                        echo '<i class="fas fa-clock"></i> Pending';
                                    } elseif ($reg['approval_status'] == 'approved') {
                                        echo '<i class="fas fa-check-circle"></i> Approved';
                                    } else {
                                        echo '<i class="fas fa-times-circle"></i> Rejected';
                                    }
                                    ?>
                                </span>

                                <a href="registration-details.php?id=<?php echo $reg['registration_id']; ?>" class="action-btn btn-view">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>

                                <?php if ($reg['approval_status'] == 'pending'): ?>
                                    <button onclick="cancelRegistration(<?php echo $reg['registration_id']; ?>)" 
                                            class="action-btn btn-cancel">
                                        <i class="fas fa-times"></i>
                                        Cancel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h2>No Registrations Yet</h2>
                <p>You haven't registered for any tournaments yet. Browse available tournaments and join one!</p>
                <a href="<?php echo SITE_URL; ?>/pages/tournament/tournaments.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 10px;">
                    <i class="fas fa-trophy"></i>
                    Browse Tournaments
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function cancelRegistration(registrationId) {
    if (confirm('Are you sure you want to cancel this registration? This action cannot be undone.')) {
        fetch('<?php echo SITE_URL; ?>/user/cancel-registration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `registration_id=${registrationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert(data.message || 'Failed to cancel registration');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>