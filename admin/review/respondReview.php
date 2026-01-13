<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Respond to Review';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Review ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$review_id = intval($_GET['id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_response = mysqli_real_escape_string($conn, trim($_POST['admin_response']));
    
    if (empty($admin_response)) {
        $_SESSION['error'] = 'Response is required!';
    } else {
        $update_query = "
            UPDATE REVIEW SET
                admin_response = '$admin_response',
                response_date = NOW()
            WHERE review_id = $review_id
        ";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Response submitted successfully!';
            
            // Get tournament_id for redirect
            $tournament_query = "SELECT tournament_id FROM REVIEW WHERE review_id = $review_id";
            $tournament_result = mysqli_query($conn, $tournament_query);
            $tournament_id = mysqli_fetch_assoc($tournament_result)['tournament_id'];
            
            redirect(SITE_URL . '/admin/review/reviewList.php?tournament_id=' . $tournament_id);
        } else {
            $_SESSION['error'] = 'Failed to submit response: ' . mysqli_error($conn);
        }
    }
}

// Fetch review with user info
$query = "
    SELECT r.*, u.full_name, u.email, t.tournament_title, t.tournament_id
    FROM REVIEW r
    JOIN USER u ON r.user_id = u.user_id
    JOIN TOURNAMENT t ON r.tournament_id = t.tournament_id
    WHERE r.review_id = $review_id
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Review not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$review = mysqli_fetch_assoc($result);

include '../includes/header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="reviewList.php?tournament_id=<?= $review['tournament_id'] ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Reviews
    </a>
</div>

<!-- Review & Response Form -->
<div class="section">
    <div class="section-header">
        <div>
            <h3 class="section-title">
                <i class="fas fa-reply"></i> <?= !empty($review['admin_response']) ? 'Edit Response' : 'Respond to Review' ?>
            </h3>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($review['tournament_title']) ?>
            </p>
        </div>
    </div>

    <!-- Original Review -->
    <div style="background: #f8f9fa; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #e9ecef;">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, var(--color-blue-primary), var(--color-blue-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.25rem;">
                <?= strtoupper(substr($review['full_name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-weight: 600; color: #1a1a1a; font-size: 1.0625rem;">
                    <?= htmlspecialchars($review['full_name']) ?>
                </div>
                <div style="font-size: 0.8125rem; color: #6c757d;">
                    <?= htmlspecialchars($review['email']) ?>
                </div>
            </div>
        </div>

        <!-- Rating -->
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
            <div style="display: flex; gap: 0.25rem; font-size: 1.25rem;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star" style="color: <?= $i <= $review['rating'] ? '#ff9800' : '#dee2e6' ?>;"></i>
                <?php endfor; ?>
            </div>
            <span style="font-weight: 600; color: #495057; font-size: 1rem;">
                <?= $review['rating'] ?>/5
            </span>
            <span style="color: #6c757d; font-size: 0.875rem; margin-left: 0.5rem;">
                • <?= date('d M Y, h:i A', strtotime($review['review_date'])) ?>
            </span>
        </div>

        <!-- Review Text -->
        <div style="background: white; border-radius: 8px; padding: 1rem;">
            <div style="color: #495057; line-height: 1.7; font-size: 0.9375rem;">
                <?= nl2br(htmlspecialchars($review['review_text'])) ?>
            </div>
        </div>
    </div>

    <!-- Response Form -->
    <form method="POST" action="">
        <div class="form-group">
            <label>Your Response <span class="required">*</span></label>
            <textarea name="admin_response" 
                      class="form-control" 
                      rows="6" 
                      placeholder="Write a professional and helpful response to this review..."
                      required><?= htmlspecialchars($review['admin_response']) ?></textarea>
            <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                Be professional, address concerns, and thank them for their feedback
            </small>
        </div>

        <div style="padding: 1rem; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: start; gap: 0.75rem;">
                <i class="fas fa-lightbulb" style="color: #388e3c; font-size: 1.25rem; margin-top: 0.125rem;"></i>
                <div style="color: #2e7d32; font-size: 0.8125rem;">
                    <strong>Response Tips:</strong>
                    <ul style="margin: 0.5rem 0 0 0; padding-left: 1.25rem;">
                        <li>Thank the participant for their feedback</li>
                        <li>Address specific concerns mentioned</li>
                        <li>Explain how you'll improve for future events</li>
                        <li>Keep it professional and constructive</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-paper-plane"></i> <?= !empty($review['admin_response']) ? 'Update Response' : 'Submit Response' ?>
            </button>
            <a href="reviewList.php?tournament_id=<?= $review['tournament_id'] ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
