<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'My Reviews';

if (!isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/pages/authentication/login.php');
}

$user_id = $_SESSION['user_id'];

// Fetch user's reviews
$reviews_query = "
    SELECT r.*, t.tournament_title, t.tournament_date, t.tournament_id
    FROM REVIEW r
    JOIN TOURNAMENT t ON r.tournament_id = t.tournament_id
    WHERE r.user_id = $user_id
    ORDER BY r.review_date DESC
";
$reviews_result = mysqli_query($conn, $reviews_query);

include '../../includes/header.php';
?>

<div class="container" style="max-width: 1000px; margin: 2rem auto; padding: 0 1rem;">
    <!-- Header -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-star"></i> My Reviews
            </h2>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                Reviews you've submitted for tournaments
            </p>
        </div>
    </div>

    <!-- Reviews List -->
    <?php if (mysqli_num_rows($reviews_result) > 0): ?>
        <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
            <div class="section" style="margin-bottom: 1.5rem;">
                <!-- Tournament Info -->
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 2px solid #f5f5f5; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #1a1a1a; margin-bottom: 0.5rem;">
                            <i class="fas fa-trophy" style="color: #6D94C5; margin-right: 0.5rem;"></i>
                            <?= htmlspecialchars($review['tournament_title']) ?>
                        </h3>
                        <div style="font-size: 0.875rem; color: #6c757d;">
                            <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($review['tournament_date'])) ?>
                            <span style="margin-left: 1rem;">
                                <i class="fas fa-clock"></i> Reviewed on <?= date('d M Y', strtotime($review['review_date'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="action-btns">
                        <a href="editReview.php?id=<?= $review['review_id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button type="button"
                                onclick="if(confirm('Delete this review?')) { window.location.href='deleteReview.php?id=<?= $review['review_id'] ?>'; }"
                                class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <!-- Rating -->
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <div style="display: flex; gap: 0.25rem; font-size: 1.5rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color: <?= $i <= $review['rating'] ? '#ff9800' : '#dee2e6' ?>;"></i>
                        <?php endfor; ?>
                    </div>
                    <span style="font-weight: 600; color: #495057; font-size: 1.125rem;">
                        <?= $review['rating'] ?>/5
                    </span>
                    <?php if ($review['is_anonymous']): ?>
                        <span class="badge" style="background: #9e9e9e; color: white; margin-left: 0.5rem; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;">
                            <i class="fas fa-user-secret"></i> Anonymous
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Review Text -->
                <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="color: #495057; line-height: 1.7;">
                        <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                    </div>
                </div>

                <!-- Admin Response -->
                <?php if (!empty($review['admin_response'])): ?>
                    <div style="background: #e3f2fd; border-left: 4px solid #6D94C5; border-radius: 8px; padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                            <i class="fas fa-reply" style="color: #6D94C5; font-size: 1.125rem;"></i>
                            <strong style="color: #6D94C5; font-size: 0.9375rem;">Admin Response</strong>
                            <span style="font-size: 0.8125rem; color: #6c757d;">
                                • <?= date('d M Y', strtotime($review['response_date'])) ?>
                            </span>
                        </div>
                        <div style="color: #495057; line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($review['admin_response'])) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="padding: 0.75rem; background: #fff3e0; border-radius: 8px; border-left: 4px solid #ff9800;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #f57c00; font-size: 0.875rem;">
                            <i class="fas fa-hourglass-half"></i>
                            <span>Waiting for admin response</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-star"></i>
            <h3>No Reviews Yet</h3>
            <p>You haven't submitted any reviews for tournaments</p>
            <a href="../tournament/tournaments.php" class="btn btn-primary">
                <i class="fas fa-trophy"></i> Browse Tournaments
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
