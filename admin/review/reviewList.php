<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Review Management';

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

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build WHERE clause
$where_clause = "r.tournament_id = $tournament_id";
if ($filter == 'pending') {
    $where_clause .= " AND r.admin_response IS NULL";
} elseif ($filter == 'responded') {
    $where_clause .= " AND r.admin_response IS NOT NULL";
}

// Fetch reviews
$reviews_query = "
    SELECT r.*, u.full_name, u.email
    FROM REVIEW r
    JOIN USER u ON r.user_id = u.user_id
    WHERE $where_clause
    ORDER BY r.review_date DESC
";
$reviews_result = mysqli_query($conn, $reviews_query);

// Calculate statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_reviews,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN admin_response IS NULL THEN 1 ELSE 0 END) as pending_reviews,
        SUM(CASE WHEN admin_response IS NOT NULL THEN 1 ELSE 0 END) as responded_reviews
    FROM REVIEW
    WHERE tournament_id = $tournament_id
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

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
                <i class="fas fa-star"></i> Review Management
            </h2>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
            </p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="dashboard-stats" style="margin-bottom: 1rem;">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6D94C5, #CBDCEB);">
                    <i class="fas fa-comments"></i>
                </div>
            </div>
            <div class="stat-value"><?= $stats['total_reviews'] ?></div>
            <div class="stat-label">Total Reviews</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ff9800, #ffb74d);">
                    <i class="fas fa-star"></i>
                </div>
            </div>
            <div class="stat-value"><?= number_format($stats['avg_rating'], 1) ?> <i class="fas fa-star" style="font-size: 1rem; color: #ff9800;"></i></div>
            <div class="stat-label">Average Rating</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f44336, #e57373);">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value"><?= $stats['pending_reviews'] ?></div>
            <div class="stat-label">Pending Response</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4caf50, #66bb6a);">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?= $stats['responded_reviews'] ?></div>
            <div class="stat-label">Responded</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?tournament_id=<?= $tournament_id ?>&filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All Reviews
        </a>
        <a href="?tournament_id=<?= $tournament_id ?>&filter=pending" class="filter-btn <?= $filter == 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending Response
        </a>
        <a href="?tournament_id=<?= $tournament_id ?>&filter=responded" class="filter-btn <?= $filter == 'responded' ? 'active' : '' ?>">
            <i class="fas fa-check"></i> Responded
        </a>
    </div>
</div>

<!-- Reviews List -->
<?php if (mysqli_num_rows($reviews_result) > 0): ?>
    <div class="section">
        <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
            <div style="background: #f8f9fa; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; border: 1px solid #e9ecef;">
                <!-- Review Header -->
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--color-blue-primary), var(--color-blue-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                                <?= strtoupper(substr($review['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #1a1a1a;"><?= htmlspecialchars($review['full_name']) ?></div>
                                <div style="font-size: 0.8125rem; color: #6c757d;">
                                    <?= date('d M Y, h:i A', strtotime($review['review_date'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Star Rating -->
                        <div style="display: flex; gap: 0.25rem; font-size: 1.125rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?= $i <= $review['rating'] ? '#ff9800' : '#dee2e6' ?>;"></i>
                            <?php endfor; ?>
                            <span style="margin-left: 0.5rem; font-size: 0.9375rem; font-weight: 600; color: #495057;">
                                <?= $review['rating'] ?>/5
                            </span>
                        </div>
                    </div>

                    <div>
                        <?php if (empty($review['admin_response'])): ?>
                            <a href="respondReview.php?id=<?= $review['review_id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-reply"></i> Respond
                            </a>
                        <?php else: ?>
                            <span class="badge badge-completed">
                                <i class="fas fa-check-circle"></i> Responded
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Content -->
                <div style="background: white; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="color: #495057; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                    </div>
                </div>

                <!-- Admin Response -->
                <?php if (!empty($review['admin_response'])): ?>
                    <div style="background: #e3f2fd; border-left: 4px solid var(--color-blue-primary); border-radius: 8px; padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <i class="fas fa-reply" style="color: var(--color-blue-primary);"></i>
                            <strong style="color: var(--color-blue-primary); font-size: 0.875rem;">Admin Response</strong>
                            <span style="font-size: 0.8125rem; color: #6c757d;">
                                • <?= date('d M Y', strtotime($review['response_date'])) ?>
                            </span>
                        </div>
                        <div style="color: #495057; font-size: 0.9375rem;">
                            <?= nl2br(htmlspecialchars($review['admin_response'])) ?>
                        </div>
                        <div style="margin-top: 0.75rem;">
                            <a href="respondReview.php?id=<?= $review['review_id'] ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Edit Response
                            </a>
                            <button type="button" 
                                    onclick="if(confirm('Delete this response?')) { window.location.href='deleteResponse.php?id=<?= $review['review_id'] ?>&tournament_id=<?= $tournament_id ?>'; }"
                                    class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i> Delete Response
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-comments"></i>
        <h3>No Reviews Found</h3>
        <p>
            <?php if ($filter == 'pending'): ?>
                No reviews are pending response
            <?php elseif ($filter == 'responded'): ?>
                No reviews have been responded to yet
            <?php else: ?>
                This tournament has no reviews yet
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>