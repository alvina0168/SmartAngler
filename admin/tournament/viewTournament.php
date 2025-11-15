<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect('tournamentList.php');
}

$tournament_id = sanitize($_GET['id']);

// Fetch tournament details
$query = "
    SELECT t.*, u.full_name AS organizer_name
    FROM TOURNAMENT t
    LEFT JOIN USER u ON t.user_id = u.user_id
    WHERE t.tournament_id = '$tournament_id'
";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
    redirect('tournamentList.php');
}

$tournament = mysqli_fetch_assoc($result);

// Fetch zone name (if assigned)
$zone_name = '';
$zone_res = mysqli_query($conn, "SELECT zone_name FROM ZONE WHERE tournament_id = '{$tournament['tournament_id']}'");
if ($zone_row = mysqli_fetch_assoc($zone_res)) {
    $zone_name = $zone_row['zone_name'];
}

// Count registered participants
$participants_res = mysqli_query($conn, "SELECT COUNT(*) AS registered FROM TOURNAMENT_REGISTRATION WHERE tournament_id = '$tournament_id'");
$participants_count = mysqli_fetch_assoc($participants_res)['registered'];

$page_title = 'View Tournament';
include '../includes/header.php';
?>

<div class="tournament-card">
    <div class="tournament-info-left">
        <h1 class="tournament-title"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h1>
        <p class="tournament-organizer"><i class="fas fa-user"></i> Organized by: <?php echo htmlspecialchars($tournament['organizer_name']); ?></p>

        <div class="tournament-meta">
            <span class="badge badge-<?php echo $tournament['status']; ?>"><?php echo ucfirst($tournament['status']); ?></span>
            <?php if(!empty($zone_name)): ?>
                <span class="badge badge-zone">Zone: <?php echo htmlspecialchars($zone_name); ?></span>
            <?php endif; ?>
        </div>

        <div class="tournament-info">
            <p><i class="fas fa-calendar-alt"></i> <strong>Date:</strong> <?php echo date('d M Y', strtotime($tournament['tournament_date'])); ?></p>
            <p><i class="fas fa-clock"></i> <strong>Time:</strong> <?php echo date('h:i A', strtotime($tournament['start_time'])); ?> - <?php echo date('h:i A', strtotime($tournament['end_time'])); ?></p>
            <p><i class="fas fa-map-marker-alt"></i> <strong>Location:</strong> <?php echo htmlspecialchars($tournament['location']); ?></p>
            <p><i class="fas fa-users"></i> <strong>Participants:</strong> <?php echo $participants_count; ?> / <?php echo $tournament['max_participants']; ?></p>
            <p><i class="fas fa-money-bill-wave"></i> <strong>Fee:</strong> RM <?php echo number_format($tournament['tournament_fee'],2); ?></p>
        </div>

        <?php if(!empty($tournament['description'])): ?>
        <div class="tournament-description">
            <h3>Description</h3>
            <p><?php echo nl2br(htmlspecialchars($tournament['description'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if(!empty($tournament['bank_account_name']) || !empty($tournament['bank_qr'])): ?>
        <div class="tournament-payment">
            <h3>Payment Info</h3>
            <?php if(!empty($tournament['bank_account_name'])): ?>
                <p><i class="fas fa-university"></i> <strong>Bank:</strong> <?php echo htmlspecialchars($tournament['bank_account_name']); ?></p>
                <p><i class="fas fa-credit-card"></i> <strong>Account Number:</strong> <?php echo htmlspecialchars($tournament['bank_account_number']); ?></p>
                <p><i class="fas fa-user"></i> <strong>Account Holder:</strong> <?php echo htmlspecialchars($tournament['bank_account_holder']); ?></p>
            <?php endif; ?>
            <?php if(!empty($tournament['bank_qr'])): ?>
                <div class="bank-qr">
                    <img src="<?php echo SITE_URL; ?>/assets/images/qrcodes/<?php echo $tournament['bank_qr']; ?>" alt="Bank QR">
                    <p>Scan QR to pay</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="tournament-actions mt-3">
            <a href="tournamentList.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="tournament-image-right">
        <img id="tournament-img" src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo !empty($tournament['image']) ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
             alt="Tournament Image">
    </div>
</div>

<!-- Expandable image modal -->
<div id="imageModal" class="image-modal">
    <span class="close-modal">&times;</span>
    <img class="modal-content" id="modal-img">
</div>

<style>
.tournament-card {
    display: flex;
    gap: 30px;
    max-width: 1000px;
    margin: 40px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    overflow: hidden;
    padding: 20px;
    font-family: 'Arial', sans-serif;
}
.tournament-info-left {
    flex: 1;
}
.tournament-image-right {
    width: 350px;
    cursor: pointer;
}
.tournament-image-right img {
    width: 100%;
    height: auto;
    border-radius: 12px;
    transition: transform 0.3s ease;
}
.tournament-image-right img:hover {
    transform: scale(1.03);
}
.tournament-title {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 8px;
}
.tournament-organizer {
    font-size: 0.95rem;
    color: #555;
    margin-bottom: 15px;
}
.tournament-meta .badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 6px;
    margin-right: 10px;
    font-size: 0.85rem;
    font-weight: bold;
    color: #fff;
}
.badge-upcoming { background-color: #3498db; }
.badge-ongoing { background-color: #f39c12; }
.badge-completed { background-color: #2ecc71; }
.badge-cancelled { background-color: #e74c3c; }
.badge-zone { background-color: #9b59b6; }
.tournament-info p { margin: 6px 0; font-size: 1rem; }
.tournament-description h3,
.tournament-payment h3 { margin-top: 20px; margin-bottom: 10px; font-size: 1.2rem; color: #333; }
.tournament-description p,
.tournament-payment p { margin: 4px 0; font-size: 0.95rem; }
.bank-qr img { max-width: 150px; margin-top: 10px; }
/* Modal styles */
.image-modal { display: none; position: fixed; z-index: 999; padding-top: 60px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); }
.image-modal .modal-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 12px; }
.close-modal { position: absolute; top: 25px; right: 35px; color: #fff; font-size: 40px; font-weight: bold; cursor: pointer; }
.close-modal:hover { color: #bbb; }
</style>

<script>
// Expandable image modal
const modal = document.getElementById('imageModal');
const img = document.getElementById('tournament-img');
const modalImg = document.getElementById('modal-img');
const closeModal = document.getElementsByClassName('close-modal')[0];

img.onclick = function() {
    modal.style.display = 'block';
    modalImg.src = this.src;
}
closeModal.onclick = function() {
    modal.style.display = 'none';
}
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
