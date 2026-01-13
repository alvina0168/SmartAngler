<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Require login
requireLogin();

// Admins shouldn't access this page
if (isAdmin()) {
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
$stmt->bind_param("iiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $tournament_id);
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
.badge {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-upcoming { background: #3498DB; color: white; }
.badge-ongoing { background: #F1C40F; color: #333; }
.badge-completed { background: #2ECC71; color: white; }
.badge-cancelled { background: #E74C3C; color: white; }

.save-details-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: white;
    color: #F39C12;
    border: 2px solid #F39C12;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.save-details-btn:hover {
    background: #F39C12;
    color: white;
}

.save-details-btn.saved {
    background: #F39C12;
    color: white;
}

.status-message {
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    margin-bottom: 15px;
}

.status-message.pending { background: #FFF3CD; color: #856404; border: 2px solid #856404; }
.status-message.approved { background: #D4EDDA; color: #155724; border: 2px solid #155724; }
.status-message.rejected { background: #F8D7DA; color: #721C24; border: 2px solid #721C24; }
.status-message.full { background: #F8D7DA; color: #721C24; border: 2px solid #721C24; }

.detail-icon-box {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #6D94C5, #CBDCEB);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}
</style>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="container">
        <!-- Back Button -->
        <div style="margin-bottom: 20px;">
            <a href="tournaments.php" style="color: #6D94C5; text-decoration: none; font-weight: 600;">
                <i class="fas fa-arrow-left"></i> Back to Tournaments
            </a>
        </div>

        <!-- Tournament Header Card -->
        <div style="background: white; border-radius: 16px; padding: 40px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <div style="display: grid; grid-template-columns: 400px 1fr; gap: 40px;">
                <!-- Tournament Image -->
                <div>
                    <img src="<?php echo SITE_URL; ?>/assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                         style="width: 100%; height: 400px; border-radius: 16px; object-fit: cover; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"
                         onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-tournament.jpg'">
                </div>
                
                <!-- Tournament Info -->
                <div>
                    <span class="badge badge-<?php echo $tournament['status']; ?>">
                        <?php echo strtoupper($tournament['status']); ?>
                    </span>
                    
                    <h1 style="color: #6D94C5; margin: 20px 0 10px 0; font-size: 36px; font-weight: 700;">
                        <?php echo htmlspecialchars($tournament['tournament_title']); ?>
                    </h1>
                    
                    <p style="color: #999; font-size: 14px; margin-bottom: 30px;">
                        <i class="fas fa-user-tie"></i> Organized by <?php echo htmlspecialchars($tournament['organizer_name']); ?>
                    </p>
                    
                    <!-- Quick Info Grid -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div class="detail-icon-box">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #999; margin-bottom: 3px;">Date</div>
                                <div style="font-weight: 600; color: #333;"><?php echo formatDate($tournament['tournament_date']); ?></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div class="detail-icon-box">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #999; margin-bottom: 3px;">Time</div>
                                <div style="font-weight: 600; color: #333;"><?php echo formatTime($tournament['start_time']); ?> - <?php echo formatTime($tournament['end_time']); ?></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div class="detail-icon-box">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #999; margin-bottom: 3px;">Entry Fee</div>
                                <div style="font-weight: 600; color: #333;">RM <?php echo number_format($tournament['tournament_fee'], 2); ?></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div class="detail-icon-box">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #999; margin-bottom: 3px;">Participants</div>
                                <div style="font-weight: 600; color: #333;"><?php echo $participants_data['registered']; ?>/<?php echo $tournament['max_participants']; ?></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div class="detail-icon-box">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #999; margin-bottom: 3px;">Spots Available</div>
                                <div style="font-weight: 600; color: #333;"><?php echo $spots_data['available_spots']; ?>/<?php echo $spots_data['total_spots']; ?></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div class="detail-icon-box">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #999; margin-bottom: 3px;">Location</div>
                                <div style="font-weight: 600; color: #333; font-size: 13px; line-height: 1.4;">
                                    <?php echo htmlspecialchars($tournament['location']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Registration Status Messages -->
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
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <?php if ((!$tournament['user_registration_status'] || $tournament['user_registration_status'] == 'rejected') 
                                && $tournament['status'] == 'upcoming' && !$isFull): ?>
                            <a href="../../user/register-tournament.php?id=<?php echo $tournament_id; ?>" class="btn btn-primary" style="padding: 14px 28px; font-size: 15px;">
                                <i class="fas fa-user-plus"></i> Register for Tournament
                            </a>
                        <?php elseif ($tournament['user_registration_status'] == 'approved'): ?>
                            <a href="<?php echo SITE_URL; ?>/user/my-registrations.php" class="btn btn-primary" style="padding: 14px 28px; font-size: 15px;">
                                <i class="fas fa-list-alt"></i> View My Registrations
                            </a>
                        <?php endif; ?>

                        <?php if ((!$tournament['user_registration_status'] || $tournament['user_registration_status'] == 'rejected')): ?>
                            <button class="save-details-btn <?php echo $tournament['is_saved'] > 0 ? 'saved' : ''; ?>" 
                                    id="saveBtn"
                                    onclick="toggleSave(<?php echo $tournament_id; ?>)">
                                <i class="<?php echo $tournament['is_saved'] > 0 ? 'fas' : 'far'; ?> fa-bookmark"></i>
                                <span><?php echo $tournament['is_saved'] > 0 ? 'Saved' : 'Save'; ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Description -->
        <?php if (!empty($tournament['description'])): ?>
        <div style="background: white; border-radius: 16px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <h3 style="color: #6D94C5; margin-bottom: 20px; font-size: 22px; font-weight: 700;">
                <i class="fas fa-align-left"></i> Description
            </h3>
            <p style="color: #666; line-height: 1.8; font-size: 15px;">
                <?php echo nl2br(htmlspecialchars($tournament['description'])); ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Tournament Rules -->
        <?php if (!empty($tournament['tournament_rules'])): ?>
        <div style="background: white; border-radius: 16px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <h3 style="color: #6D94C5; margin-bottom: 20px; font-size: 22px; font-weight: 700;">
                <i class="fas fa-gavel"></i> Tournament Rules
            </h3>
            <div style="color: #666; line-height: 1.8; font-size: 15px;">
                <?php echo nl2br(htmlspecialchars($tournament['tournament_rules'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Prizes -->
        <?php
        $prizes_query = "SELECT tp.*, c.category_name 
                         FROM TOURNAMENT_PRIZE tp 
                         LEFT JOIN CATEGORY c ON tp.category_id = c.category_id 
                         WHERE tp.tournament_id = ? 
                         ORDER BY tp.category_id, tp.prize_ranking ASC";
        $stmt = $conn->prepare($prizes_query);
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $prizes_result = $stmt->get_result();
        
        if ($prizes_result->num_rows > 0):
        ?>
        <div style="background: white; border-radius: 16px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <h3 style="color: #6D94C5; margin-bottom: 25px; font-size: 22px; font-weight: 700;">
                <i class="fas fa-gift"></i> Tournament Prizes
            </h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Rank</th>
                        <th>Prize Description</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($prize = $prizes_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($prize['category_name']); ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($prize['prize_ranking']); ?></strong></td>
                        <td><?php echo htmlspecialchars($prize['prize_description']); ?></td>
                        <td style="font-weight: 600; color: #2ECC71;">RM <?php echo number_format($prize['prize_value'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
        <div style="background: white; border-radius: 16px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <h3 style="color: #6D94C5; margin-bottom: 25px; font-size: 22px; font-weight: 700;">
                <i class="fas fa-handshake"></i> Tournament Sponsors
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                <?php while ($sponsor = $sponsors_result->fetch_assoc()): ?>
                <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; border: 2px solid #e9ecef;">
                    <h4 style="color: #333; margin-bottom: 10px; font-size: 18px; font-weight: 700;">
                        <?php echo htmlspecialchars($sponsor['sponsor_name']); ?>
                    </h4>
                    <?php if ($sponsor['sponsored_amount'] > 0): ?>
                        <p style="color: #2ECC71; font-weight: 600; margin-bottom: 10px; font-size: 16px;">
                            RM <?php echo number_format($sponsor['sponsored_amount'], 2); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($sponsor['sponsor_description'])): ?>
                        <p style="color: #666; font-size: 14px; line-height: 1.6;">
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
        // Check if user can review
        $can_review = false;
        $has_reviewed = false;

        if (isset($_SESSION['user_id']) && !isAdmin()) {
            $user_id = $_SESSION['user_id'];
            
            $participation_query = "
                SELECT tr.registration_id
                FROM TOURNAMENT_REGISTRATION tr
                WHERE tr.user_id = ? 
                AND tr.tournament_id = ?
                AND tr.approval_status = 'approved'
            ";
            $stmt = $conn->prepare($participation_query);
            $stmt->bind_param("ii", $user_id, $tournament_id);
            $stmt->execute();
            $participation_result = $stmt->get_result();
            $participated = $participation_result->num_rows > 0;
            $stmt->close();
            
            $can_review = $participated && $tournament['status'] == 'completed';
            
            if ($can_review) {
                $existing_review_query = "SELECT review_id FROM REVIEW WHERE user_id = ? AND tournament_id = ?";
                $stmt = $conn->prepare($existing_review_query);
                $stmt->bind_param("ii", $user_id, $tournament_id);
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

        <div style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h3 style="color: #6D94C5; margin-bottom: 10px; font-size: 22px; font-weight: 700;">
                        <i class="fas fa-star"></i> Reviews & Ratings
                    </h3>
                    <?php if ($total_reviews > 0): ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="display: flex; gap: 4px; font-size: 20px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" style="color: <?= $i <= round($avg_rating) ? '#ff9800' : '#dee2e6' ?>;"></i>
                                <?php endfor; ?>
                            </div>
                            <span style="font-weight: 700; font-size: 20px; color: #495057;">
                                <?= number_format($avg_rating, 1) ?>/5
                            </span>
                            <span style="color: #6c757d; font-size: 14px;">
                                (<?= $total_reviews ?> <?= $total_reviews == 1 ? 'review' : 'reviews' ?>)
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($can_review): ?>
                    <?php if ($has_reviewed): ?>
                        <a href="<?= SITE_URL ?>/pages/review/myReviews.php" class="btn btn-secondary">
                            <i class="fas fa-eye"></i> View My Review
                        </a>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/pages/review/addReview.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary">
                            <i class="fas fa-star"></i> Write a Review
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($total_reviews > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php while ($review = $reviews_result->fetch_assoc()): ?>
                        <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; border: 1px solid #e9ecef;">
                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                <div style="width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #6D94C5, #CBDCEB); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px;">
                                    <?php if ($review['is_anonymous']): ?>
                                        <i class="fas fa-user-secret"></i>
                                    <?php else: ?>
                                        <?= strtoupper(substr($review['full_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #1a1a1a; margin-bottom: 3px;">
                                        <?php if ($review['is_anonymous']): ?>
                                            Anonymous User
                                            <span style="font-size: 11px; background: #9e9e9e; color: white; padding: 3px 8px; border-radius: 10px; margin-left: 8px;">
                                                <i class="fas fa-user-secret"></i> ANONYMOUS
                                            </span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($review['full_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 13px; color: #6c757d;">
                                        <?= date('d M Y', strtotime($review['review_date'])) ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 3px; font-size: 16px;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?= $i <= $review['rating'] ? '#ff9800' : '#dee2e6' ?>;"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div style="color: #495057; line-height: 1.7; font-size: 15px;">
                                <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                            </div>

                            <?php if (!empty($review['admin_response'])): ?>
                                <div style="background: #e3f2fd; border-left: 4px solid #6D94C5; border-radius: 8px; padding: 15px; margin-top: 15px;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                        <i class="fas fa-reply" style="color: #6D94C5;"></i>
                                        <strong style="color: #6D94C5; font-size: 14px;">Admin Response</strong>
                                        <span style="font-size: 13px; color: #6c757d;">
                                            • <?= date('d M Y', strtotime($review['response_date'])) ?>
                                        </span>
                                    </div>
                                    <div style="color: #495057; font-size: 14px; line-height: 1.6;">
                                        <?= nl2br(htmlspecialchars($review['admin_response'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>

                <?php if ($total_reviews > 10): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <p style="color: #6c757d; font-size: 14px;">
                            Showing 10 of <?= $total_reviews ?> reviews
                        </p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px; color: #adb5bd;">
                    <i class="fas fa-star" style="font-size: 48px; opacity: 0.5; margin-bottom: 15px;"></i>
                    <p style="font-size: 14px; margin: 0;">
                        No reviews yet. 
                        <?php if ($can_review && !$has_reviewed): ?>
                            <a href="<?= SITE_URL ?>/pages/review/addReview.php?tournament_id=<?= $tournament_id ?>" style="color: #6D94C5; font-weight: 600;">Be the first to review!</a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <?php $stmt->close(); ?>
    </div>
</div>

<script>
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