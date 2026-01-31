<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Don't require login - tournament details should be public
// Only check if user is logged in for personalized features
$isLoggedIn = isLoggedIn();
$currentUserId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Admins shouldn't access this page
if ($isLoggedIn && isAdmin()) {
    redirect(SITE_URL . '/admin/index.php');
}

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$tournament_id = intval($_GET['id']);

// Auto-update tournament status
$update_query = "
    UPDATE TOURNAMENT
    SET status = CASE
        WHEN NOW() < CONCAT(tournament_date, ' ', start_time) THEN 'upcoming'
        WHEN NOW() BETWEEN CONCAT(tournament_date, ' ', start_time) AND CONCAT(tournament_date, ' ', end_time) THEN 'ongoing'
        WHEN NOW() > CONCAT(tournament_date, ' ', end_time) THEN 'completed'
        ELSE status
    END
    WHERE tournament_id = ? AND status != 'cancelled'
";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$stmt->close();

// Get tournament details with save and registration status
if ($isLoggedIn) {
    $query = "SELECT t.*, u.full_name as organizer_name,
              (SELECT COUNT(*) FROM SAVED 
               WHERE tournament_id = t.tournament_id 
               AND user_id = ? 
               AND is_saved = 1) as is_saved,
              (SELECT registration_id FROM TOURNAMENT_REGISTRATION 
               WHERE tournament_id = t.tournament_id 
               AND user_id = ? 
               AND approval_status IN ('pending', 'approved', 'rejected')
               LIMIT 1) as user_registration_id,
              (SELECT approval_status FROM TOURNAMENT_REGISTRATION 
               WHERE tournament_id = t.tournament_id 
               AND user_id = ? 
               AND approval_status IN ('pending', 'approved', 'rejected')
               LIMIT 1) as user_registration_status
              FROM TOURNAMENT t 
              LEFT JOIN USER u ON t.user_id = u.user_id 
              WHERE t.tournament_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $currentUserId, $currentUserId, $currentUserId, $tournament_id);
} else {
    // Guest users - no personalization
    $query = "SELECT t.*, u.full_name as organizer_name,
              0 as is_saved,
              NULL as user_registration_id,
              NULL as user_registration_status
              FROM TOURNAMENT t 
              LEFT JOIN USER u ON t.user_id = u.user_id 
              WHERE t.tournament_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tournament_id);
}

$stmt->execute();
$result = $stmt->get_result();
$tournament = $result->fetch_assoc();
$stmt->close();

if (!$tournament) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

// Get available spots count
$spots_query = "
    SELECT 
        COUNT(*) AS total_spots,
        SUM(CASE WHEN fs.spot_status = 'available' THEN 1 ELSE 0 END) AS available_spots
    FROM FISHING_SPOT fs
    JOIN ZONE z ON fs.zone_id = z.zone_id
    WHERE z.tournament_id = ?
";
$stmt = $conn->prepare($spots_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$spots_data = $result->fetch_assoc();
$stmt->close();

// Get registered participants count
$participants_query = "SELECT COUNT(*) as registered 
                       FROM TOURNAMENT_REGISTRATION 
                       WHERE tournament_id = ? AND approval_status IN ('pending', 'approved')";
$stmt = $conn->prepare($participants_query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$participants_data = $result->fetch_assoc();
$stmt->close();

// Check if spots are full
$spotsAvailable = $tournament['max_participants'] - $participants_data['registered'];
$isFull = $spotsAvailable <= 0;

$page_title = $tournament['tournament_title'];
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

/* Page Container */
.details-page {
    background: var(--sand);
    min-height: 100vh;
    padding: 40px 0 60px;
}

.details-container {
    max-width: 100%;
    padding: 0 60px;
}

/* Back Button */
.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--ocean-light);
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 24px;
    transition: all 0.2s ease;
}

.back-button:hover {
    color: var(--ocean-blue);
    gap: 12px;
}

/* Tournament Header Card */
.tournament-header-card {
    background: var(--white);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--border);
}

.header-grid {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 40px;
}

.tournament-image {
    width: 100%;
    height: 400px;
    border-radius: 16px;
    object-fit: cover;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.status-badge.upcoming { background: rgba(59, 130, 246, 0.1); color: #3B82F6; }
.status-badge.ongoing { background: rgba(245, 158, 11, 0.1); color: #F59E0B; }
.status-badge.completed { background: rgba(16, 185, 129, 0.1); color: #10B981; }
.status-badge.cancelled { background: rgba(239, 68, 68, 0.1); color: #EF4444; }

.tournament-title {
    color: var(--ocean-blue);
    margin: 20px 0 10px 0;
    font-size: 36px;
    font-weight: 800;
}

.organizer-info {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 30px;
}

/* Quick Info Grid */
.quick-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.info-item {
    display: flex;
    gap: 12px;
    align-items: center;
}

.info-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 18px;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.info-value {
    font-weight: 700;
    color: var(--text-dark);
    font-size: 15px;
}

/* Status Messages */
.status-message {
    padding: 16px 20px;
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.status-message.pending {
    background: #FEF3C7;
    color: #92400E;
    border: 2px solid #FCD34D;
}

.status-message.approved {
    background: #ECFDF5;
    color: #065F46;
    border: 2px solid #A7F3D0;
}

.status-message.rejected {
    background: #FEE2E2;
    color: #991B1B;
    border: 2px solid #FCA5A5;
}

.status-message.full {
    background: #FEE2E2;
    color: #991B1B;
    border: 2px solid #FCA5A5;
}

.status-message.login-required {
    background: #DBEAFE;
    color: #1E40AF;
    border: 2px solid #93C5FD;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    color: var(--white);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(8, 131, 149, 0.3);
}

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: var(--white);
    color: var(--ocean-light);
    border: 2px solid var(--ocean-light);
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: var(--ocean-light);
    color: var(--white);
}

.save-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: var(--white);
    color: #F59E0B;
    border: 2px solid #F59E0B;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.save-btn:hover {
    background: #FEF3C7;
}

.save-btn.saved {
    background: #F59E0B;
    color: var(--white);
}

/* Content Cards */
.content-card {
    background: var(--white);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--border);
}

.card-title {
    color: var(--ocean-blue);
    margin-bottom: 24px;
    font-size: 24px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-content {
    color: var(--text-muted);
    line-height: 1.8;
    font-size: 15px;
}

/* Prize Tables */
.prize-category {
    margin-bottom: 32px;
}

.prize-category:last-child {
    margin-bottom: 0;
}

.category-header {
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    color: var(--white);
    padding: 16px 20px;
    border-radius: 12px 12px 0 0;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.prize-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid var(--border);
    border-top: none;
    border-radius: 0 0 12px 12px;
    overflow: hidden;
}

.prize-table thead th {
    background: var(--sand);
    padding: 14px 16px;
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    color: var(--text-dark);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}

.prize-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text-dark);
    font-size: 14px;
}

.prize-table tbody tr:last-child td {
    border-bottom: none;
}

.prize-table tbody tr:hover {
    background: var(--sand);
}

.prize-rank {
    font-weight: 700;
    color: var(--ocean-blue);
}

.prize-value {
    font-weight: 700;
    color: #10B981;
}

/* Sponsors Grid */
.sponsors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.sponsor-card {
    background: var(--sand);
    border-radius: 12px;
    padding: 24px;
    border: 2px solid var(--border);
    transition: all 0.3s ease;
}

.sponsor-card:hover {
    border-color: var(--ocean-light);
    box-shadow: 0 4px 12px rgba(8, 131, 149, 0.1);
}

.sponsor-name {
    color: var(--text-dark);
    margin-bottom: 12px;
    font-size: 18px;
    font-weight: 700;
}

.sponsor-amount {
    color: #10B981;
    font-weight: 700;
    margin-bottom: 12px;
    font-size: 18px;
}

.sponsor-description {
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.6;
}

/* Reviews */
.reviews-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 20px;
}

.rating-summary {
    display: flex;
    align-items: center;
    gap: 16px;
}

.stars {
    display: flex;
    gap: 4px;
    font-size: 20px;
}

.avg-rating {
    font-weight: 800;
    font-size: 24px;
    color: var(--text-dark);
}

.review-count {
    color: var(--text-muted);
    font-size: 14px;
}

.review-card {
    background: var(--sand);
    border-radius: 12px;
    padding: 24px;
    border: 1px solid var(--border);
    margin-bottom: 16px;
}

.review-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}

.review-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-weight: 700;
    font-size: 20px;
    flex-shrink: 0;
}

.review-info {
    flex: 1;
}

.reviewer-name {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.review-date {
    font-size: 13px;
    color: var(--text-muted);
}

.review-rating {
    display: flex;
    gap: 4px;
    font-size: 16px;
}

.review-text {
    color: var(--text-dark);
    line-height: 1.7;
    font-size: 15px;
    margin-bottom: 16px;
}

.admin-response {
    background: #E3F2FD;
    border-left: 4px solid var(--ocean-light);
    border-radius: 8px;
    padding: 16px;
    margin-top: 16px;
}

.response-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--ocean-blue);
    font-weight: 700;
    font-size: 14px;
}

.response-text {
    color: var(--text-dark);
    font-size: 14px;
    line-height: 1.6;
}

.empty-reviews {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-reviews i {
    font-size: 48px;
    opacity: 0.5;
    margin-bottom: 16px;
}

/* Responsive */
@media (max-width: 1400px) {
    .details-container {
        padding: 0 40px;
    }
}

@media (max-width: 992px) {
    .header-grid {
        grid-template-columns: 1fr;
    }
    
    .tournament-image {
        height: 300px;
    }
    
    .quick-info-grid {
        grid-template-columns: 1fr;
    }
    
    .sponsors-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .details-container {
        padding: 0 20px;
    }
    
    .tournament-header-card {
        padding: 24px;
    }
    
    .tournament-title {
        font-size: 28px;
    }
    
    .content-card {
        padding: 20px;
    }
    
    .reviews-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-primary,
    .btn-secondary,
    .save-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="details-page">
    <div class="details-container">
        <!-- Back Button -->
        <a href="tournaments.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Tournaments
        </a>

        <!-- Tournament Header Card -->
        <div class="tournament-header-card">
            <div class="header-grid">
                <!-- Tournament Image -->
                <div>
                    <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                         class="tournament-image"
                         onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                </div>
                
                <!-- Tournament Info -->
                <div>
                    <span class="status-badge <?php echo $tournament['status']; ?>">
                        <?php echo strtoupper($tournament['status']); ?>
                    </span>
                    
                    <h1 class="tournament-title">
                        <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                    </h1>
                    
                    <p class="organizer-info">
                        <i class="fas fa-user-tie"></i> Organized by <?php echo htmlspecialchars($tournament['organizer_name']); ?>
                    </p>
                    
                    <!-- Quick Info Grid -->
                    <div class="quick-info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Date</div>
                                <div class="info-value"><?php echo formatDate($tournament['tournament_date']); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Location</div>
                                <div class="info-value" style="font-size: 13px; line-height: 1.4;">
                                    <?php echo htmlspecialchars($tournament['location']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Time</div>
                                <div class="info-value"><?php echo formatTime($tournament['start_time']); ?> - <?php echo formatTime($tournament['end_time']); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Participants</div>
                                <div class="info-value"><?php echo $participants_data['registered']; ?>/<?php echo $tournament['max_participants']; ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Entry Fee</div>
                                <div class="info-value">RM <?php echo number_format($tournament['tournament_fee'], 2); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Spots Available</div>
                                <div class="info-value"><?php echo $spots_data['available_spots']; ?>/<?php echo $spots_data['total_spots']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Registration Status Messages -->
                    <?php if ($isLoggedIn): ?>
                        <?php if ($tournament['user_registration_status']): ?>
                            <?php if ($tournament['user_registration_status'] == 'pending'): ?>
                                <div class="status-message pending">
                                    <i class="fas fa-clock"></i> Registration Pending Approval
                                </div>
                            <?php elseif ($tournament['user_registration_status'] == 'approved'): ?>
                                <div class="status-message approved">
                                    <i class="fas fa-check-circle"></i> You're Registered for This Tournament!
                                </div>
                            <?php endif; ?>
                        <?php elseif ($isFull): ?>
                            <div class="status-message full">
                                <i class="fas fa-user-slash"></i> Tournament Full - No Spots Available
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Guest user message -->
                        <?php if ($tournament['status'] == 'upcoming' && !$isFull): ?>
                            <div class="status-message login-required">
                                <i class="fas fa-info-circle"></i> Please login to register for this tournament
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($isLoggedIn): ?>
                            <!-- Logged-in users: Show register/save buttons -->
                            <?php if ((!$tournament['user_registration_status'] || $tournament['user_registration_status'] == 'rejected') 
                                    && $tournament['status'] == 'upcoming' && !$isFull): ?>
                                <a href="../../pages/registration/register-tournament.php?id=<?php echo $tournament_id; ?>" class="btn-primary">
                                    <i class="fas fa-user-plus"></i> Register for Tournament
                                </a>
                            <?php elseif ($tournament['user_registration_status'] == 'approved'): ?>
                                <a href="<?php echo SITE_URL; ?>/user/my-registrations.php" class="btn-primary">
                                    <i class="fas fa-list-alt"></i> View My Registrations
                                </a>
                            <?php endif; ?>

                            <?php if ((!$tournament['user_registration_status'] || $tournament['user_registration_status'] == 'rejected')): ?>
                                <button class="save-btn <?php echo $tournament['is_saved'] > 0 ? 'saved' : ''; ?>" 
                                        id="saveBtn"
                                        onclick="toggleSave(<?php echo $tournament_id; ?>)">
                                    <i class="<?php echo $tournament['is_saved'] > 0 ? 'fas' : 'far'; ?> fa-bookmark"></i>
                                    <span><?php echo $tournament['is_saved'] > 0 ? 'Saved' : 'Save'; ?></span>
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Guest users: Show login prompt buttons -->
                            <?php if ($tournament['status'] == 'upcoming' && !$isFull): ?>
                                <button onclick="promptLogin('register')" class="btn-primary">
                                    <i class="fas fa-user-plus"></i> Register for Tournament
                                </button>
                                <button onclick="promptLogin('save')" class="save-btn">
                                    <i class="far fa-bookmark"></i>
                                    <span>Save</span>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- View Results Button (available for everyone if completed) -->
                        <?php if ($tournament['status'] == 'completed'): ?>
                            <a href="<?php echo SITE_URL; ?>/pages/tournament/get-live-results.php?tournament_id=<?php echo $tournament_id; ?>" class="btn-primary">
                                <i class="fas fa-trophy"></i> View Results
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Description -->
        <?php if (!empty($tournament['description'])): ?>
        <div class="content-card">
            <h3 class="card-title">
                <i class="fas fa-align-left"></i> Description
            </h3>
            <div class="card-content">
                <?php echo nl2br(htmlspecialchars($tournament['description'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tournament Rules -->
        <?php if (!empty($tournament['tournament_rules'])): ?>
        <div class="content-card">
            <h3 class="card-title">
                <i class="fas fa-gavel"></i> Tournament Rules
            </h3>
            <div class="card-content">
                <?php echo nl2br(htmlspecialchars($tournament['tournament_rules'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Prizes by Category -->
        <?php
        $prizes_query = "SELECT tp.*, c.category_name, c.category_type
                         FROM TOURNAMENT_PRIZE tp 
                         LEFT JOIN CATEGORY c ON tp.category_id = c.category_id 
                         WHERE tp.tournament_id = ? 
                         ORDER BY c.category_name, tp.target_weight, tp.prize_ranking ASC";
        $stmt = $conn->prepare($prizes_query);
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $prizes_result = $stmt->get_result();
        
        // Group prizes by category + target weight
        $prizes_by_category = [];
        while ($prize = $prizes_result->fetch_assoc()) {
            $key = $prize['category_id'] . '_' . ($prize['target_weight'] ?? 'null');
            if (!isset($prizes_by_category[$key])) {
                $prizes_by_category[$key] = [
                    'category_name' => $prize['category_name'],
                    'category_type' => $prize['category_type'],
                    'target_weight' => $prize['target_weight'],
                    'prizes' => []
                ];
            }
            $prizes_by_category[$key]['prizes'][] = $prize;
        }
        
        if (count($prizes_by_category) > 0):
        ?>
        <div class="content-card">
            <h3 class="card-title">
                <i class="fas fa-gift"></i> Tournament Prizes
            </h3>
            
            <?php foreach ($prizes_by_category as $category_data): ?>
                <div class="prize-category">
                    <div class="category-header">
                        <i class="fas fa-trophy"></i>
                        <span><?php echo htmlspecialchars($category_data['category_name']); ?></span>
                        <?php if ($category_data['target_weight']): ?>
                            <span style="opacity: 0.9; font-size: 14px; margin-left: 8px;">
                                (Target: <?php echo $category_data['target_weight']; ?> KG)
                            </span>
                        <?php endif; ?>
                    </div>
                    <table class="prize-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Prize Description</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category_data['prizes'] as $prize): ?>
                            <tr>
                                <td class="prize-rank"><?php echo htmlspecialchars($prize['prize_ranking']); ?></td>
                                <td><?php echo htmlspecialchars($prize['prize_description']); ?></td>
                                <td class="prize-value">RM <?php echo number_format($prize['prize_value'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
        <?php 
        endif; 
        $stmt->close();
        ?>

        <!-- Sponsors -->
        <?php
        $sponsors_query = "SELECT * FROM SPONSOR WHERE tournament_id = ? ORDER BY sponsored_amount DESC";
        $stmt = $conn->prepare($sponsors_query);
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $sponsors_result = $stmt->get_result();
        
        if ($sponsors_result->num_rows > 0):
        ?>
        <div class="content-card">
            <h3 class="card-title">
                <i class="fas fa-handshake"></i> Tournament Sponsors
            </h3>
            <div class="sponsors-grid">
                <?php while ($sponsor = $sponsors_result->fetch_assoc()): ?>
                <div class="sponsor-card">
                    <h4 class="sponsor-name">
                        <?php echo htmlspecialchars($sponsor['sponsor_name']); ?>
                    </h4>
                    <?php if ($sponsor['sponsored_amount'] > 0): ?>
                        <p class="sponsor-amount">
                            RM <?php echo number_format($sponsor['sponsored_amount'], 2); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($sponsor['sponsor_description'])): ?>
                        <p class="sponsor-description">
                            <?php echo htmlspecialchars($sponsor['sponsor_description']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php 
        endif; 
        $stmt->close();
        ?>

        <!-- Reviews Section -->
        <?php
        // Check if user can review (only for logged-in users)
        $can_review = false;
        $has_reviewed = false;

        if ($isLoggedIn) {
            $participation_query = "
                SELECT tr.registration_id
                FROM TOURNAMENT_REGISTRATION tr
                WHERE tr.user_id = ? 
                AND tr.tournament_id = ?
                AND tr.approval_status = 'approved'
            ";
            $stmt = $conn->prepare($participation_query);
            $stmt->bind_param("ii", $currentUserId, $tournament_id);
            $stmt->execute();
            $participation_result = $stmt->get_result();
            $participated = $participation_result->num_rows > 0;
            $stmt->close();
            
            $can_review = $participated && $tournament['status'] == 'completed';
            
            if ($can_review) {
                $existing_review_query = "SELECT review_id FROM REVIEW WHERE user_id = ? AND tournament_id = ?";
                $stmt = $conn->prepare($existing_review_query);
                $stmt->bind_param("ii", $currentUserId, $tournament_id);
                $stmt->execute();
                $existing_review_result = $stmt->get_result();
                $has_reviewed = $existing_review_result->num_rows > 0;
                $stmt->close();
            }
        }

        $reviews_query = "
            SELECT r.*, u.full_name
            FROM REVIEW r
            LEFT JOIN USER u ON r.user_id = u.user_id
            WHERE r.tournament_id = ?
            ORDER BY r.review_date DESC
            LIMIT 10
        ";
        $stmt = $conn->prepare($reviews_query);
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $reviews_result = $stmt->get_result();

        $stats_query = "
            SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as avg_rating
            FROM REVIEW
            WHERE tournament_id = ?
        ";
        $stmt_stats = $conn->prepare($stats_query);
        $stmt_stats->bind_param("i", $tournament_id);
        $stmt_stats->execute();
        $stats_result = $stmt_stats->get_result();
        $review_stats = $stats_result->fetch_assoc();
        $stmt_stats->close();

        $total_reviews = $review_stats['total_reviews'] ?? 0;
        $avg_rating = $review_stats['avg_rating'] ?? 0;
        ?>

        <div class="content-card">
            <div class="reviews-header">
                <div>
                    <h3 class="card-title" style="margin-bottom: 12px;">
                        <i class="fas fa-star"></i> Reviews & Ratings
                    </h3>
                    <?php if ($total_reviews > 0): ?>
                        <div class="rating-summary">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" style="color: <?= $i <= round($avg_rating) ? '#F59E0B' : '#E5E7EB' ?>;"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="avg-rating">
                                <?= number_format($avg_rating, 1) ?>/5
                            </span>
                            <span class="review-count">
                                (<?= $total_reviews ?> <?= $total_reviews == 1 ? 'review' : 'reviews' ?>)
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($isLoggedIn && $can_review): ?>
                    <?php if ($has_reviewed): ?>
                        <a href="<?= SITE_URL ?>/pages/review/myReviews.php" class="btn-secondary">
                            <i class="fas fa-eye"></i> View My Review
                        </a>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/pages/review/addReview.php?tournament_id=<?= $tournament_id ?>" class="btn-primary">
                            <i class="fas fa-star"></i> Write a Review
                        </a>
                    <?php endif; ?>
                <?php elseif (!$isLoggedIn && $tournament['status'] == 'completed'): ?>
                    <button onclick="promptLogin('review')" class="btn-primary">
                        <i class="fas fa-star"></i> Write a Review
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($total_reviews > 0): ?>
                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="review-avatar">
                                <?php if ($review['is_anonymous']): ?>
                                    <i class="fas fa-user-secret"></i>
                                <?php else: ?>
                                    <?= strtoupper(substr($review['full_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="review-info">
                                <div class="reviewer-name">
                                    <?php if ($review['is_anonymous']): ?>
                                        Anonymous User
                                    <?php else: ?>
                                        <?= htmlspecialchars($review['full_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="review-date">
                                    <?= date('d M Y', strtotime($review['review_date'])) ?>
                                </div>
                            </div>
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" style="color: <?= $i <= $review['rating'] ? '#F59E0B' : '#E5E7EB' ?>;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Review Text -->
                        <div class="review-text">
                            <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                        </div>

                        <!-- Review Image -->
                        <?php if (!empty($review['review_image'])): ?>
                            <div class="review-image" style="margin-top: 12px;">
                            <img src="<?= SITE_URL ?>/<?= htmlspecialchars($review['review_image']) ?>" 
                                alt="Review Image" 
                                style="max-width: 200px; max-height: 150px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); object-fit: cover;"
                                onerror="this.src='<?= SITE_URL ?>/assets/images/default-review.jpg'">

                            </div>
                        <?php endif; ?>

                        <?php if (!empty($review['admin_response'])): ?>
                            <div class="admin-response">
                                <div class="response-header">
                                    <i class="fas fa-reply"></i>
                                    <span>Admin Response</span>
                                    <span style="font-weight: 400; opacity: 0.8;">
                                        • <?= date('d M Y', strtotime($review['response_date'])) ?>
                                    </span>
                                </div>
                                <div class="response-text">
                                    <?= nl2br(htmlspecialchars($review['admin_response'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>

                <?php if ($total_reviews > 10): ?>
                    <div style="text-align: center; margin-top: 20px; color: var(--text-muted); font-size: 14px;">
                        Showing 10 of <?= $total_reviews ?> reviews
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-reviews">
                    <i class="fas fa-star"></i>
                    <p>
                        No reviews yet.
                        <?php if ($isLoggedIn && $can_review && !$has_reviewed): ?>
                            <a href="<?= SITE_URL ?>/pages/review/addReview.php?tournament_id=<?= $tournament_id ?>" style="color: var(--ocean-light); font-weight: 600;">Be the first to review!</a>
                        <?php elseif (!$isLoggedIn && $tournament['status'] == 'completed'): ?>
                            <a href="#" onclick="promptLogin('review'); return false;" style="color: var(--ocean-light); font-weight: 600;">Login to be the first to review!</a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <?php $stmt->close(); ?>
    </div>
</div>

<script>
// Prompt login for guest users
function promptLogin(action) {
    let message = '';
    switch(action) {
        case 'register':
            message = 'Please login to register for tournaments. Would you like to login now?';
            break;
        case 'save':
            message = 'Please login to save tournaments. Would you like to login now?';
            break;
        case 'review':
            message = 'Please login to write a review. Would you like to login now?';
            break;
        default:
            message = 'Please login to continue. Would you like to login now?';
    }
    
    if (confirm(message)) {
        window.location.href = '<?php echo SITE_URL; ?>/pages/authentication/login.php?redirect=' + encodeURIComponent(window.location.href);
    }
}

// Toggle save (only for logged-in users)
function toggleSave(tournamentId) {
    const button = document.getElementById('saveBtn');
    const isSaved = button.classList.contains('saved');
    
    fetch('<?php echo SITE_URL; ?>/pages/tournament/toggle-save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `tournament_id=${tournamentId}&action=${isSaved ? 'unsave' : 'save'}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isSaved) {
                button.classList.remove('saved');
                button.innerHTML = '<i class="far fa-bookmark"></i><span>Save</span>';
            } else {
                button.classList.add('saved');
                button.innerHTML = '<i class="fas fa-bookmark"></i><span>Saved</span>';
            }
        } else {
            alert(data.message || 'Failed to update saved status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>

<?php include '../../includes/footer.php'; ?>