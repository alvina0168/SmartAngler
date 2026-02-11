<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Require login
requireLogin();

// Admins shouldn't access this page
if (isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

// Get tournament ID
if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$tournament_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Check if already registered
$check_query = "SELECT registration_id, approval_status FROM TOURNAMENT_REGISTRATION 
                WHERE tournament_id = ? AND user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $tournament_id, $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    if ($existing['approval_status'] == 'pending') {
        $_SESSION['info'] = 'Your registration is pending approval!';
    } else {
        $_SESSION['error'] = 'You have already registered for this tournament!';
    }
    redirect(SITE_URL . '/pages/tournament/tournament-details.php?id=' . $tournament_id);
}

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

// Get user details
$user_query = "SELECT * FROM USER WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get zones and fishing spots with coordinates
$zones_query = "
    SELECT 
        z.zone_id,
        z.zone_name,
        z.zone_description,
        COUNT(fs.spot_id) AS total_spots,
        SUM(CASE WHEN fs.spot_status = 'available' THEN 1 ELSE 0 END) AS available_spots
    FROM ZONE z
    LEFT JOIN FISHING_SPOT fs ON z.zone_id = fs.zone_id
    WHERE z.tournament_id = ?
    GROUP BY z.zone_id
    HAVING available_spots > 0
    ORDER BY z.zone_name
";

$stmt = $conn->prepare($zones_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$zones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all available fishing spots grouped by zone
$spots_by_zone = [];
$all_spots_with_coords = [];
foreach ($zones as $zone) {
    $spots_query = "
        SELECT * FROM FISHING_SPOT 
        WHERE zone_id = ? AND spot_status = 'available'
        ORDER BY spot_number
    ";
    $stmt = $conn->prepare($spots_query);
    $stmt->bind_param("i", $zone['zone_id']);
    $stmt->execute();
    $spots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $spots_by_zone[$zone['zone_id']] = $spots;
    
    // Collect spots with coordinates for map
    foreach ($spots as $spot) {
        if (!empty($spot['latitude']) && !empty($spot['longitude'])) {
            $spot['zone_name'] = $zone['zone_name'];
            $all_spots_with_coords[] = $spot;
        }
    }
    $stmt->close();
}

$page_title = 'Register for Tournament';
include '../../includes/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

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

.register-hero {
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

.register-page {
    background: var(--white);
    padding: 0 0 60px;
}

.register-container {
    max-width: 1200px;
    margin: -50px auto 0;
    padding: 0 60px;
    position: relative;
    z-index: 10;
}

.register-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    color: var(--white);
    padding: 24px 32px;
}

.card-title {
    font-size: 24px;
    font-weight: 800;
    margin: 0;
}

.card-body {
    padding: 32px;
}

.form-group {
    margin-bottom: 24px;
}

.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.form-label.required::after {
    content: '*';
    color: #EF4444;
    margin-left: 4px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--ocean-light);
    box-shadow: 0 0 0 3px rgba(8, 131, 149, 0.1);
}

.form-control:disabled {
    background: var(--sand);
    cursor: not-allowed;
}

.form-text {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 6px;
    display: block;
}

.map-section {
    margin: 24px 0;
}

.zone-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.zone-tab {
    padding: 12px 24px;
    background: var(--white);
    border: 2px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 600;
    font-size: 14px;
}

.zone-tab:hover {
    border-color: var(--ocean-light);
}

.zone-tab.active {
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    color: var(--white);
    border-color: var(--ocean-light);
}

.map-container {
    position: relative;
    background: var(--sand);
    border-radius: 12px;
    padding: 24px;
    min-height: 400px;
}

#fishingMap {
    width: 100%;
    height: 500px;
    border-radius: 12px;
    margin-bottom: 20px;
    z-index: 1;
    border: 3px solid var(--ocean-light);
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.zone-info {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
}

.zone-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--ocean-blue);
    margin-bottom: 8px;
}

.zone-description {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.6;
}

.spots-visualization {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 12px;
}

.spot-item {
    position: relative;
}

.spot-item input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.spot-box {
    width: 100%;
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--white);
    border: 3px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 12px;
}

.spot-item input[type="radio"]:checked + .spot-box {
    background: linear-gradient(135deg, rgba(8, 131, 149, 0.15) 0%, rgba(5, 191, 219, 0.15) 100%);
    border-color: var(--ocean-light);
    border-width: 4px;
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(8, 131, 149, 0.3);
}

.spot-box:hover {
    border-color: var(--ocean-light);
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.spot-icon {
    font-size: 24px;
    color: var(--ocean-light);
    margin-bottom: 6px;
}

.spot-number {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-dark);
}

.zone-content {
    display: none;
}

.zone-content.active {
    display: block;
}

.payment-info-box {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
}

.payment-info-box h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--ocean-blue);
    margin-bottom: 12px;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 15px;
}

.payment-row strong {
    color: var(--text-dark);
}

.payment-row span {
    color: var(--text-muted);
}

.qr-code-box {
    text-align: center;
    padding: 16px;
    border: 1px dashed var(--border);
    border-radius: 12px;
    background: rgba(8, 131, 149, 0.03);
    margin-top: 16px;
}

.qr-code-box img {
    max-width: 200px;
    height: auto;
    border-radius: 8px;
}

.file-upload {
    position: relative;
    margin-top: 12px;
}

.file-upload input[type="file"] {
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-upload p {
    text-align: center;
    font-size: 14px;
    color: var(--text-muted);
}

.file-upload:hover {
    border-color: var(--ocean-light);
    background: rgba(8, 131, 149, 0.05);
}

.file-upload i {
    display: block;
    margin: 0 auto 8px;
    font-size: 48px;
    color: var(--ocean-light);
}

@media (max-width: 768px) {
    .payment-info-box, .qr-code-box {
        padding: 16px;
    }
    
    .file-upload i {
        font-size: 36px;
    }
}

.file-upload {
    position: relative;
}

.file-upload input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.file-upload-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 20px;
    background: var(--sand);
    border: 2px dashed var(--border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.file-upload-label:hover {
    border-color: var(--ocean-light);
    background: rgba(8, 131, 149, 0.05);
}

.file-upload-icon {
    font-size: 24px;
    color: var(--ocean-light);
}

.file-name {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 8px;
}

.btn-group {
    display: flex;
    gap: 12px;
    margin-top: 32px;
}

.btn {
    flex: 1;
    padding: 14px 24px;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    color: var(--white);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(8, 131, 149, 0.3);
}

.btn-secondary {
    background: var(--white);
    color: var(--ocean-light);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--ocean-light);
}

.info-box {
    background: linear-gradient(135deg, rgba(8, 131, 149, 0.05) 0%, rgba(5, 191, 219, 0.05) 100%);
    border-left: 4px solid var(--ocean-light);
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.info-box-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--ocean-blue);
    margin: 0 0 8px;
}

.info-box-text {
    font-size: 14px;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.6;
}

.selected-spot-display {
    background: linear-gradient(135deg, rgba(8, 131, 149, 0.1) 0%, rgba(5, 191, 219, 0.1) 100%);
    border: 2px solid var(--ocean-light);
    border-radius: 12px;
    padding: 16px 20px;
    margin-top: 16px;
    display: none;
}

.selected-spot-display.active {
    display: flex;
    align-items: center;
    gap: 12px;
}

.selected-spot-display i {
    font-size: 24px;
    color: var(--ocean-light);
}

.selected-spot-text {
    flex: 1;
}

.selected-spot-label {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.selected-spot-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--ocean-blue);
}

.custom-popup .leaflet-popup-content-wrapper {
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.custom-popup .leaflet-popup-content {
    margin: 12px;
    font-size: 14px;
}

.leaflet-control-layers {
    border: 2px solid rgba(0,0,0,0.2);
    border-radius: 8px;
}

.leaflet-bar {
    border-radius: 8px;
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }
    
    .register-container {
        padding: 0 20px;
    }
    
    .card-body {
        padding: 24px;
    }
    
    .spots-visualization {
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .zone-tabs {
        flex-direction: column;
    }
    
    #fishingMap {
        height: 300px;
    }
}
</style>
<div class="register-hero">
    <div class="hero-content">
        <h1 class="hero-title">Register for Tournament</h1>
        <p class="hero-subtitle"><?php echo htmlspecialchars($tournament['tournament_title']); ?></p>
    </div>
</div>

<!-- Registration Page -->
<div class="register-page">
    <div class="register-container">
        <div class="register-card">
            <div class="card-header">
                <h2 class="card-title">Tournament Registration Form</h2>
            </div>
            
            <div class="card-body">
                <div class="info-box">
                    <h3 class="info-box-title">📋 Registration Information</h3>
                    <p class="info-box-text">
                        Please fill in the form below to register for this tournament. Your registration will be reviewed by the tournament organizer. You will receive a notification once your registration is approved or rejected.
                    </p>
                </div>

                <form action="<?php echo SITE_URL; ?>/pages/registration/process-registration.php" method="POST" enctype="multipart/form-data" id="registrationForm">
                    <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                    <input type="hidden" name="spot_id" id="selected_spot_id" value="">
                    
                    <div class="form-group">
                        <label class="form-label required">Full Name</label>
                        <input type="text" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                               readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Email Address</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Phone Number</label>
                        <input type="tel" name="phone_number" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone_number']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Emergency Contact</label>
                        <input type="tel" name="emergency_contact" class="form-control" 
                               placeholder="Enter emergency contact number">
                        <small class="form-text">Optional: Contact person in case of emergency</small>
                    </div>

                    <!-- Fishing Spot Map -->
                    <div class="form-group">
                        <label class="form-label required">Select Fishing Spot</label>
                        
                        <?php if (count($zones) > 0): ?>
                            <div class="map-section">
                                <?php if (count($all_spots_with_coords) > 0): ?>
                                <div id="fishingMap"></div>
                                
                                <div class="info-box" style="margin-top: 16px;">
                                    <p class="info-box-text" style="margin: 0;">
                                        <i class="fas fa-info-circle"></i> Click on any blue marker on the map to select your fishing spot. 
                                    </p>
                                </div>
                                <?php endif; ?>

                                <div class="zone-tabs" style="display: none;">
                                    <?php foreach ($zones as $index => $zone): ?>
                                        <button type="button" 
                                                class="zone-tab <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                onclick="switchZone(<?php echo $zone['zone_id']; ?>)">
                                            <i class="fas fa-map-marked-alt"></i>
                                            <?php echo htmlspecialchars($zone['zone_name']); ?>
                                            (<?php echo $zone['available_spots']; ?> available)
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <div class="map-container" style="display: none;">
                                    <?php foreach ($zones as $index => $zone): ?>
                                        <div class="zone-content <?php echo $index === 0 ? 'active' : ''; ?>" 
                                             id="zone_<?php echo $zone['zone_id']; ?>">

                                            <div class="spots-visualization">
                                                <?php 
                                                if (isset($spots_by_zone[$zone['zone_id']])):
                                                    foreach ($spots_by_zone[$zone['zone_id']] as $spot): 
                                                ?>
                                                    <div class="spot-item">
                                                        <input type="radio" 
                                                               name="spot_selection" 
                                                               id="spot_<?php echo $spot['spot_id']; ?>" 
                                                               value="<?php echo $spot['spot_id']; ?>"
                                                               data-zone="<?php echo htmlspecialchars($zone['zone_name']); ?>"
                                                               data-spot="<?php echo $spot['spot_number']; ?>"
                                                               data-lat="<?php echo $spot['latitude']; ?>"
                                                               data-lng="<?php echo $spot['longitude']; ?>"
                                                               onchange="updateSelectedSpot(this)">
                                                        <label for="spot_<?php echo $spot['spot_id']; ?>" class="spot-box" style="display: none;">
                                                            <i class="fas fa-anchor spot-icon"></i>
                                                            <div class="spot-number">#<?php echo $spot['spot_number']; ?></div>
                                                        </label>
                                                    </div>
                                                <?php 
                                                    endforeach;
                                                endif;
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="selected-spot-display" id="selectedSpotDisplay">
                                    <i class="fas fa-check-circle"></i>
                                    <div class="selected-spot-text">
                                        <div class="selected-spot-label">Selected Fishing Spot</div>
                                        <div class="selected-spot-value" id="selectedSpotText">None</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="info-box" style="background: rgba(239, 68, 68, 0.05); border-left-color: #EF4444;">
                                <p class="info-box-text" style="color: #991B1B;">
                                    No fishing spots available. Please contact the tournament organizer.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

<!-- Payment Information & Proof Upload -->
<div class="registration-card">
    <h3 class="section-title">
        <i class="fas fa-credit-card"></i>
        Payment Information
    </h3>

    <!-- Tournament Fee & Bank Details -->
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

        <?php if (!empty($tournament['bank_qr'])): ?>
        <div class="qr-code-box" style="margin-top: 20px;">
            <p style="font-weight: 600; margin-bottom: 15px;">Scan QR Code to Pay</p>
            <img src="<?php echo SITE_URL; ?>/assets/images/qrcodes/<?php echo htmlspecialchars($tournament['bank_qr']); ?>" 
                 alt="Payment QR Code">
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment Proof Upload -->
    <div class="form-group"> 
        <label class="form-label required">Payment Proof</label> 
        <div class="file-upload"> 
            <input type="file" name="payment_proof" id="payment_proof" accept="image/*,.pdf" required onchange="updateFileName(this)"> 
            <label for="payment_proof" class="file-upload-label"> 
                <i class="fas fa-cloud-upload-alt file-upload-icon"></i> 
                <span> 
                    <strong>Click to upload</strong> or drag and drop<br> 
                    <small style="color: var(--text-muted);">PNG, JPG or PDF (max 5MB)</small> 
                </span> 
            </label> 
        </div> 
        <div id="file-name" class="file-name"></div> 
        <small class="form-text"> 
            Registration Fee: RM <?php echo number_format($tournament['tournament_fee'], 2); ?><br> 
            Upload your payment receipt or proof of payment 
        </small> 
    </div>
</div>

                    <div class="btn-group">
                        <a href="<?php echo SITE_URL; ?>/pages/tournament/tournament-details.php?id=<?php echo $tournament_id; ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" <?php echo count($zones) == 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-check"></i>
                            Submit Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map;
let markers = {};
let selectedMarker = null;

// Initialize Leaflet Map
<?php if (count($all_spots_with_coords) > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    const spots = <?php echo json_encode($all_spots_with_coords); ?>;
    
    if (spots.length > 0) {
        const firstSpot = spots[0];
        map = L.map('fishingMap').setView([firstSpot.latitude, firstSpot.longitude], 13);
        
        const googleHybrid = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '© Google Maps'
        }).addTo(map);
        
        const googleSatellite = L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '© Google Maps'
        });
        
        const googleStreets = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '© Google Maps'
        });
        
        const googleTerrain = L.tileLayer('https://{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '© Google Maps'
        });
        
        const baseLayers = {
            "🌍 Hybrid (Recommended)": googleHybrid,
            "🛰️ Satellite": googleSatellite,
            "🗺️ Street Map": googleStreets,
            "🏔️ Terrain": googleTerrain
        };
        
        L.control.layers(baseLayers, null, {
            position: 'topright'
        }).addTo(map);
        
        // Add scale control
        L.control.scale({
            metric: true,
            imperial: false
        }).addTo(map);
        
        spots.forEach(spot => {
            if (spot.latitude && spot.longitude) {
                // Create blue marker (default/unselected)
                const marker = L.marker([spot.latitude, spot.longitude], {
                    title: `${spot.zone_name} - Spot #${spot.spot_number}`,
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background: #007bff; width: 32px; height: 32px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                                <span style="transform: rotate(45deg); color: white; font-weight: bold; font-size: 14px;">${spot.spot_number}</span>
                               </div>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 32]
                    })
                }).addTo(map);

                marker.bindPopup(`
                    <div style="text-align: center; padding: 8px;">
                        <strong style="color: var(--ocean-blue); font-size: 16px;">
                            ${spot.zone_name}
                        </strong><br>
                        <span style="color: var(--text-muted); font-size: 14px;">
                            Spot #${spot.spot_number}
                        </span>
                    </div>
                `, {
                    className: 'custom-popup'
                });
                
                markers[spot.spot_id] = {
                    marker: marker,
                    spotNumber: spot.spot_number,
                    zoneName: spot.zone_name,
                    lat: spot.latitude,
                    lng: spot.longitude
                };

                marker.on('click', function() {
                    selectSpotFromMap(spot.spot_id);
                });
            }
        });
        
        if (spots.length > 1) {
            const bounds = L.latLngBounds(spots.map(s => [s.latitude, s.longitude]));
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    }
});
<?php endif; ?>

function selectSpotFromMap(spotId) {
    if (selectedMarker && markers[selectedMarker]) {
        const prevMarkerData = markers[selectedMarker];
        prevMarkerData.marker.setIcon(L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: #007bff; width: 32px; height: 32px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                    <span style="transform: rotate(45deg); color: white; font-weight: bold; font-size: 14px;">${prevMarkerData.spotNumber}</span>
                   </div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        }));
    }

    if (markers[spotId]) {
        const markerData = markers[spotId];
        markerData.marker.setIcon(L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: #dc3545; width: 32px; height: 32px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                    <span style="transform: rotate(45deg); color: white; font-weight: bold; font-size: 14px;">${markerData.spotNumber}</span>
                   </div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        }));
        
        selectedMarker = spotId;
        
        const radio = document.getElementById('spot_' + spotId);
        if (radio) {
            radio.checked = true;
            updateSelectedSpot(radio);
        }

        map.setView([markerData.lat, markerData.lng], 15, { animate: true });
        markerData.marker.openPopup();
    }
}

function switchZone(zoneId) {
    document.querySelectorAll('.zone-content').forEach(content => {
        content.classList.remove('active');
    });

    document.querySelectorAll('.zone-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.getElementById('zone_' + zoneId).classList.add('active');
    
    event.target.closest('.zone-tab').classList.add('active');
}

function updateSelectedSpot(radio) {
    const zoneName = radio.dataset.zone;
    const spotNumber = radio.dataset.spot;
    const spotId = radio.value;
    const lat = radio.dataset.lat;
    const lng = radio.dataset.lng;
    
    document.getElementById('selected_spot_id').value = spotId;
    document.getElementById('selectedSpotText').textContent = `${zoneName} - Spot #${spotNumber}`;
    document.getElementById('selectedSpotDisplay').classList.add('active');

    if (map && markers[spotId]) {
        selectSpotFromMap(spotId);
    }
}

function updateFileName(input) {
    const fileNameDiv = document.getElementById('file-name');
    if (input.files && input.files[0]) {
        fileNameDiv.textContent = '📄 ' + input.files[0].name;
        fileNameDiv.style.color = 'var(--ocean-light)';
        fileNameDiv.style.fontWeight = '600';
    } else {
        fileNameDiv.textContent = '';
    }
}

document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const spotId = document.getElementById('selected_spot_id').value;
    if (!spotId) {
        e.preventDefault();
        alert('Please select a fishing spot before submitting!');
        return false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>