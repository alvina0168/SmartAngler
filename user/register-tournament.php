<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$tournament_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get tournament details
$tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = ?";
$stmt = $conn->prepare($tournament_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tournament) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

// Check if already registered
$check_query = "SELECT * FROM TOURNAMENT_REGISTRATION WHERE tournament_id = ? AND user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $tournament_id, $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $_SESSION['error'] = 'You have already registered for this tournament!';
    redirect(SITE_URL . '/pages/tournament/tournament-details.php?id=' . $tournament_id);
}

// Check if tournament is full
$count_query = "SELECT COUNT(*) as count FROM TOURNAMENT_REGISTRATION 
                WHERE tournament_id = ? AND approval_status IN ('pending', 'approved')";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

if ($count >= $tournament['max_participants']) {
    $_SESSION['error'] = 'This tournament is full!';
    redirect(SITE_URL . '/pages/tournament/tournament-details.php?id=' . $tournament_id);
}

// Get user details for autofill
$user_query = "SELECT * FROM USER WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get available fishing spots
$spots_query = "SELECT fs.*, z.zone_name 
                FROM FISHING_SPOT fs 
                JOIN ZONE z ON fs.zone_id = z.zone_id 
                WHERE z.tournament_id = ? AND fs.spot_status = 'available'
                ORDER BY z.zone_name, fs.spot_id";
$stmt = $conn->prepare($spots_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$spots_result = $stmt->get_result();
$spots_array = $spots_result->fetch_all(MYSQLI_ASSOC); // store for map
$stmt->close();

$page_title = 'Register for Tournament';
include '../includes/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<style>
.registration-container {
    min-height: 70vh;
    padding: 50px 0;
    background-color: #F5EFE6;
}

.registration-card {
    background: white;
    border-radius: 10px;
    padding: 40px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.section-title {
    color: #6D94C5;
    font-size: 24px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #6D94C5;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #6D94C5;
}

.payment-info-box {
    background: #F5EFE6;
    padding: 25px;
    border-radius: 10px;
    border-left: 4px solid #6D94C5;
    margin-bottom: 20px;
}

.payment-info-box h4 {
    color: #6D94C5;
    margin-bottom: 15px;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #ddd;
}

.payment-row:last-child {
    border-bottom: none;
}

.qr-code-box {
    text-align: center;
    padding: 20px;
    background: white;
    border-radius: 10px;
    margin: 20px 0;
}

.qr-code-box img {
    max-width: 250px;
    border-radius: 8px;
}

.file-upload {
    border: 2px dashed #6D94C5;
    padding: 30px;
    text-align: center;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

.file-upload:hover {
    background: #F5EFE6;
}

.file-upload input[type="file"] {
    display: none;
}

.spots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.spot-card {
    border: 2px solid #ddd;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.spot-card:hover {
    border-color: #6D94C5;
    background: #F5EFE6;
}

.spot-card input[type="radio"] {
    display: none;
}

.spot-card.selected {
    border-color: #6D94C5;
    background: #6D94C5;
    color: white;
}

#map {
    width: 100%;
    height: 400px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.submit-btn {
    background: #6D94C5;
    color: white;
    padding: 15px 40px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.submit-btn:hover {
    background: #5a7ea8;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .spots-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
}
</style>

<div class="registration-container">
    <div class="container">
        <div style="margin-bottom: 20px;">
            <a href="<?php echo SITE_URL; ?>/pages/tournament/tournament-details.php?id=<?php echo $tournament_id; ?>" 
               style="color: #6D94C5; text-decoration: none; font-weight: 600;">
                <i class="fas fa-arrow-left"></i> Back to Tournament Details
            </a>
        </div>

        <form action="process-registration.php" method="POST" enctype="multipart/form-data" id="registrationForm">
            <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">

            <!-- Tournament Info -->
            <div class="registration-card">
                <h2 class="section-title">
                    <i class="fas fa-trophy"></i>
                    <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                </h2>
                <div class="form-row">
                    <div>
                        <p><strong>Date:</strong> <?php echo formatDate($tournament['tournament_date']); ?></p>
                        <p><strong>Time:</strong> <?php echo formatTime($tournament['start_time']); ?> - <?php echo formatTime($tournament['end_time']); ?></p>
                    </div>
                    <div>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($tournament['location']); ?></p>
                        <p><strong>Fee:</strong> RM <?php echo number_format($tournament['tournament_fee'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Participant Information -->
            <div class="registration-card">
                <h3 class="section-title">
                    <i class="fas fa-user"></i>
                    Participant Information
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="text" name="phone_number" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Emergency Contact *</label>
                        <input type="text" name="emergency_contact" class="form-control" 
                               placeholder="Emergency contact number" required>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="registration-card">
                <h3 class="section-title">
                    <i class="fas fa-credit-card"></i>
                    Payment Information
                </h3>

                <div class="payment-info-box">
                    <h4>Tournament Fee: RM <?php echo number_format($tournament['tournament_fee'], 2); ?></h4>
                    <p style="margin-bottom: 20px;">Please transfer the tournament fee to the account below:</p>

                    <div class="payment-row">
                        <strong>Bank Name:</strong>
                        <span><?php echo htmlspecialchars($tournament['bank_account_name']); ?></span>
                    </div>
                    <div class="payment-row">
                        <strong>Account Number:</strong>
                        <span><?php echo htmlspecialchars($tournament['bank_account_number']); ?></span>
                    </div>
                    <div class="payment-row">
                        <strong>Account Holder:</strong>
                        <span><?php echo htmlspecialchars($tournament['bank_account_holder']); ?></span>
                    </div>
                </div>

                <?php if (!empty($tournament['bank_qr'])): ?>
                <div class="qr-code-box">
                    <p style="font-weight: 600; margin-bottom: 15px;">Scan QR Code to Pay</p>
                    <img src="<?php echo SITE_URL; ?>/assets/images/qrcodes/<?php echo htmlspecialchars($tournament['bank_qr']); ?>" 
                         alt="Payment QR Code">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Upload Payment Proof *</label>
                    <div class="file-upload" onclick="document.getElementById('payment_proof').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #6D94C5; margin-bottom: 10px;"></i>
                        <p id="file-name">Click to upload payment receipt/screenshot</p>
                        <input type="file" id="payment_proof" name="payment_proof" 
                               accept="image/*,.pdf" required onchange="displayFileName(this)">
                    </div>
                </div>
            </div>

            <!-- Fishing Spot Selection with Map -->
            <div class="registration-card">
                <h3 class="section-title">
                    <i class="fas fa-map-marked-alt"></i>
                    Select Fishing Spot
                </h3>

                <?php if (count($spots_array) > 0): ?>
                    <div id="map"></div>

                    <p style="margin-bottom: 15px;">Choose your preferred fishing spot for this tournament:</p>
                    <div class="spots-grid">
                        <?php foreach ($spots_array as $spot): ?>
                        <label class="spot-card" onclick="selectSpot(this)">
                            <input type="radio" name="spot_id" value="<?php echo $spot['spot_id']; ?>" required>
                            <div>
                                <strong><?php echo htmlspecialchars($spot['zone_name']); ?> - Spot <?php echo $spot['spot_id']; ?></strong>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: #F5EFE6; border-radius: 10px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #F39C12; margin-bottom: 15px;"></i>
                        <p>No fishing spots available for this tournament yet. Please contact the organizer.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <div class="registration-card" style="text-align: center;">
                <button type="submit" class="submit-btn">
                    <i class="fas fa-check-circle"></i>
                    Complete Registration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function displayFileName(input) {
    const fileName = input.files[0]?.name;
    if (fileName) {
        document.getElementById('file-name').textContent = fileName;
    }
}

function selectSpot(label) {
    document.querySelectorAll('.spot-card').forEach(card => {
        card.classList.remove('selected');
    });
    label.classList.add('selected');
    label.querySelector('input[type="radio"]').checked = true;
}

// Form validation
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const spotSelected = document.querySelector('input[name="spot_id"]:checked');
    if (!spotSelected) {
        e.preventDefault();
        alert('Please select a fishing spot');
        return false;
    }
});

// Leaflet map setup
<?php if (count($spots_array) > 0): ?>
const map = L.map('map').setView([<?php echo $spots_array[0]['latitude'] ?? 0; ?>, <?php echo $spots_array[0]['longitude'] ?? 0; ?>], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

const spots = <?php echo json_encode($spots_array); ?>;

spots.forEach(spot => {
    if (!spot.latitude || !spot.longitude) return;

    const marker = L.marker([spot.latitude, spot.longitude]).addTo(map)
        .bindPopup(`${spot.zone_name} - Spot ${spot.spot_id}`);

    marker.on('click', () => {
        document.querySelectorAll('.spot-card').forEach(card => card.classList.remove('selected'));
        const spotLabel = document.querySelector(`.spot-card input[value="${spot.spot_id}"]`).parentElement;
        spotLabel.classList.add('selected');
        spotLabel.querySelector('input[type="radio"]').checked = true;
    });
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
