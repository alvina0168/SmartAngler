<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'My Reviews';

if (!isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/pages/authentication/login.php');
}

$user_id = $_SESSION['user_id'];

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
    --orange: #ff9800;
}

.reviews-hero {
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    color: var(--white);
    text-align: center;
    padding: 60px 0 80px;
}
.reviews-hero h1 { font-size: 42px; font-weight: 800; margin-bottom: 12px; }
.reviews-hero p { font-size: 18px; opacity: 0.9; }

.reviews-container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }

.review-card {
    background: var(--white);
    border-radius: 16px;
    padding: 25px 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}
.review-card:hover { transform: translateY(-3px); }

.review-card .tournament-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}
.review-card .tournament-date { font-size: 0.875rem; color: var(--text-muted); }

.review-card .action-btns { display: flex; gap: 10px; margin-top: 10px; }
.review-card .action-btns a, .review-card .action-btns button {
    font-size: 0.875rem;
    padding: 6px 12px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease;
}
.review-card .action-btns a.btn-primary, .review-card .action-btns button.btn-primary {
    background: var(--ocean-light);
    color: var(--white);
}
.review-card .action-btns a.btn-primary:hover, .review-card .action-btns button.btn-primary:hover {
    background: var(--ocean-blue);
}
.review-card .action-btns button.btn-danger {
    background: #F87171;
    color: var(--white);
}
.review-card .action-btns button.btn-danger:hover { background: #DC2626; }

.review-card .rating { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
.review-card .rating i { font-size: 1.25rem; }
.review-card .rating .score { font-weight: 600; font-size: 1.125rem; color: var(--text-dark); }

.review-card .badge-anonymous {
    background: #9e9e9e;
    color: var(--white);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
}

.review-card .review-text {
    background: var(--sand);
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    line-height: 1.6;
    color: var(--text-dark);
}

.review-card .review-image {
    max-width: 200px;
    border-radius: 12px;
    margin-bottom: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.review-card .admin-response {
    background: #e3f2fd;
    border-left: 4px solid var(--ocean-blue);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.5rem;
}
.review-card .admin-response strong { color: var(--ocean-blue); font-size: 0.95rem; }

.empty-state {
    text-align: center;
    margin-top: 3rem;
    padding: 2rem;
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid var(--border);
}
.empty-state h3 { font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--text-dark); }
.empty-state p { font-size: 1rem; color: var(--text-muted); margin-bottom: 1rem; }
.empty-state .btn { background: var(--ocean-light); color: var(--white); padding: 10px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; }
.empty-state .btn:hover { background: var(--ocean-blue); }

@media (max-width: 768px) {
    .review-card { padding: 20px; }
    .review-card .action-btns { flex-wrap: wrap; gap: 5px; }
}
</style>

<div class="reviews-hero">
    <h1> My Reviews</h1>
    <p>All the reviews you've submitted for tournaments</p>
</div>

<!-- Reviews Container -->
<div class="reviews-container">
    <?php if (mysqli_num_rows($reviews_result) > 0): ?>
        <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
            <div class="review-card">
                <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <div class="tournament-title">
                            <?= htmlspecialchars($review['tournament_title']) ?>
                        </div>
                        <div class="tournament-date">
                            Reviewed on <?= date('d M Y', strtotime($review['review_date'])) ?>
                        </div>
                    </div>
                    <div class="action-btns">
                        <a href="editReview.php?id=<?= $review['review_id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button type="button"
                                onclick="if(confirm('Delete this review?')) { window.location.href='deleteReview.php?id=<?= $review['review_id'] ?>'; }"
                                class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <!-- Rating -->
                <div class="rating">
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color: <?= $i <= $review['rating'] ? '#ff9800' : '#dee2e6' ?>;"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="score"><?= $review['rating'] ?>/5</span>
                    <?php if ($review['is_anonymous']): ?>
                        <span class="badge-anonymous"><i class="fas fa-user-secret"></i> Anonymous</span>
                    <?php endif; ?>
                </div>

                <!-- Review Image -->
                <?php if (!empty($review['review_image'])): ?>
                    <img src="../../<?= htmlspecialchars($review['review_image']) ?>" alt="Review Image" class="review-image">
                <?php endif; ?>

                <!-- Review Text -->
                <div class="review-text">
                    <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                </div>

                <!-- Admin Response -->
                <?php if (!empty($review['admin_response'])): ?>
                    <div class="admin-response">
                        <div style="margin-bottom:0.5rem;">
                            <i class="fas fa-reply"></i> <strong>Admin Response</strong>
                            <span style="font-size:0.8125rem; color: var(--text-muted); margin-left:0.5rem;">
                                • <?= date('d M Y', strtotime($review['response_date'])) ?>
                            </span>
                        </div>
                        <div><?= nl2br(htmlspecialchars($review['admin_response'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <h3>No Reviews Yet</h3>
            <p>You haven't submitted any reviews for tournaments</p>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
