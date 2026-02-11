<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/user/my-registrations.php');
}

$registration_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$query = "SELECT tr.*, t.tournament_title, t.tournament_date, t.start_time, t.end_time, 
                 t.location, t.tournament_fee, t.image, t.status as tournament_status,
                 fs.spot_id AS spot_number, z.zone_name
          FROM TOURNAMENT_REGISTRATION tr
          JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
          LEFT JOIN FISHING_SPOT fs ON tr.spot_id = fs.spot_id
          LEFT JOIN ZONE z ON fs.zone_id = z.zone_id
          WHERE tr.user_id = ? AND tr.registration_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $registration_id);
$stmt->execute();
$result = $stmt->get_result();
$registration = $result->fetch_assoc();
$stmt->close();

if (!$registration) {
    setFlashMessage("Registration not found.", "error");
    redirect(SITE_URL . '/user/my-registrations.php');
}

$page_title = 'Registration Details';
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
    color: #00000;
    font-size: 30px;
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
            <h1><i class="fas fa-clipboard-list"></i> Registration Details</h1>
        </div>

        <div class="registration-card">
            <div class="card-content">
                <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($registration['image']) ? $registration['image'] : 'default-tournament.jpg'; ?>" 
                     alt="<?php echo htmlspecialchars($registration['tournament_title']); ?>" 
                     class="tournament-image"
                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">

                <div class="tournament-info">
                    <h3><?php echo htmlspecialchars($registration['tournament_title']); ?></h3>
                    
                    <div class="info-row">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo formatDate($registration['tournament_date']); ?></span>
                    </div>

                    <div class="info-row">
                        <i class="fas fa-clock"></i>
                        <span>
                            <?php echo date('g:i A', strtotime($registration['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($registration['end_time'])); ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($registration['location']); ?></span>
                    </div>

                    <div class="info-row">
                        <i class="fas fa-map-marked-alt"></i>
                        <span><strong>Fishing Spot:</strong> <?php echo $registration['zone_name'] ?? 'N/A'; ?> - Spot <?php echo $registration['spot_number'] ?? 'N/A'; ?></span>
                    </div>

                    <div class="info-row">
                        <i class="fas fa-calendar-check"></i>
                        <span><strong>Registered:</strong> <?php echo date('M d, Y', strtotime($registration['registration_date'])); ?></span>
                    </div>
                </div>

                <div class="card-actions">
                    <span class="status-badge status-<?php echo $registration['approval_status']; ?>">
                        <?php 
                        if ($registration['approval_status'] == 'pending') {
                            echo '<i class="fas fa-clock"></i> Pending';
                        } elseif ($registration['approval_status'] == 'approved') {
                            echo '<i class="fas fa-check-circle"></i> Approved';
                        } else {
                            echo '<i class="fas fa-times-circle"></i> Rejected';
                        }
                        ?>
                    </span>

                    <?php if ($registration['approval_status'] == 'pending'): ?>
                        <button onclick="cancelRegistration(<?php echo $registration['registration_id']; ?>)" 
                                class="action-btn btn-cancel">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

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
                window.location.href = '<?php echo SITE_URL; ?>/user/my-registrations.php';
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