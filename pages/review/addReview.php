<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Write Review';

if (!isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/pages/authentication/login.php');
}

if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$user_id = $_SESSION['user_id'];
$tournament_id = intval($_GET['tournament_id']);

// Check if user participated in this tournament
$participation_query = "
    SELECT tr.*, t.tournament_title, t.tournament_date, t.status
    FROM TOURNAMENT_REGISTRATION tr
    JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
    WHERE tr.user_id = $user_id 
    AND tr.tournament_id = $tournament_id 
    AND tr.approval_status = 'approved'
";
$participation_result = mysqli_query($conn, $participation_query);

if (!$participation_result || mysqli_num_rows($participation_result) == 0) {
    $_SESSION['error'] = 'You must be an approved participant to review this tournament!';
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

$tournament = mysqli_fetch_assoc($participation_result);

// Check if tournament is completed
if ($tournament['status'] != 'completed') {
    $_SESSION['error'] = 'You can only review completed tournaments!';
    redirect(SITE_URL . '/pages/tournament/tournaments.php');
}

// Check if user already reviewed this tournament
$existing_review_query = "
    SELECT review_id FROM REVIEW 
    WHERE user_id = $user_id AND tournament_id = $tournament_id
";
$existing_review_result = mysqli_query($conn, $existing_review_query);

if (mysqli_num_rows($existing_review_result) > 0) {
    $_SESSION['error'] = 'You have already reviewed this tournament!';
    redirect(SITE_URL . '/pages/review/myReviews.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $review_text = mysqli_real_escape_string($conn, trim($_POST['review_text']));
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Please select a rating between 1 and 5!';
    } elseif (empty($review_text)) {
        $_SESSION['error'] = 'Please write your review!';
    } else {
        $insert_query = "
            INSERT INTO REVIEW (user_id, tournament_id, rating, review_text, is_anonymous, review_date)
            VALUES ($user_id, $tournament_id, $rating, '$review_text', $is_anonymous, NOW())
        ";
        
        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['success'] = 'Review submitted successfully! Thank you for your feedback.';
            redirect(SITE_URL . '/pages/review/myReviews.php');
        } else {
            $_SESSION['error'] = 'Failed to submit review: ' . mysqli_error($conn);
        }
    }
}

include '../../includes/header.php';
?>

<div class="container" style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
    <!-- Back Button -->
    <div style="margin-bottom: 1.5rem;">
        <a href="../tournament/tournaments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Tournaments
        </a>
    </div>

    <!-- Review Form -->
    <div class="section">
        <div class="section-header">
            <div>
                <h3 class="section-title">
                    <i class="fas fa-star"></i> Write a Review
                </h3>
                <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                    Share your experience with <?= htmlspecialchars($tournament['tournament_title']) ?>
                </p>
            </div>
        </div>

        <!-- Tournament Info -->
        <div style="background: #e3f2fd; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border-left: 4px solid #6D94C5;">
            <div style="font-weight: 600; color: #1565c0; margin-bottom: 0.25rem;">
                <i class="fas fa-trophy"></i> <?= htmlspecialchars($tournament['tournament_title']) ?>
            </div>
            <div style="font-size: 0.875rem; color: #1976d2;">
                <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($tournament['tournament_date'])) ?>
            </div>
        </div>

        <form method="POST" action="">
            <!-- Rating Selection -->
            <div class="form-group">
                <label>Your Rating <span class="required">*</span></label>
                <div id="ratingStars" style="display: flex; gap: 0.5rem; font-size: 2.5rem; margin-top: 0.5rem; cursor: pointer;">
                    <i class="far fa-star" data-rating="1"></i>
                    <i class="far fa-star" data-rating="2"></i>
                    <i class="far fa-star" data-rating="3"></i>
                    <i class="far fa-star" data-rating="4"></i>
                    <i class="far fa-star" data-rating="5"></i>
                </div>
                <input type="hidden" name="rating" id="ratingValue" required>
                <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.5rem; display: block;">
                    Click on the stars to rate (1 = Poor, 5 = Excellent)
                </small>
            </div>

            <!-- Review Text -->
            <div class="form-group">
                <label>Your Review <span class="required">*</span></label>
                <textarea name="review_text" 
                          class="form-control" 
                          rows="8" 
                          placeholder="Share your experience... What did you like? What could be improved?"
                          required></textarea>
                <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                    Be specific and helpful - your review helps improve future tournaments
                </small>
            </div>

            <!-- Anonymous Checkbox -->
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <input type="checkbox" name="is_anonymous" id="is_anonymous" style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 600; color: #1a1a1a;">
                            <i class="fas fa-user-secret"></i> Post anonymously
                        </div>
                        <div style="font-size: 0.8125rem; color: #6c757d; margin-top: 0.25rem;">
                            Your name will be hidden from public view
                        </div>
                    </div>
                </label>
            </div>

            <!-- Guidelines -->
            <div style="padding: 1rem; background: #fff3e0; border-radius: 8px; border-left: 4px solid #ff9800; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: start; gap: 0.75rem;">
                    <i class="fas fa-info-circle" style="color: #f57c00; font-size: 1.25rem; margin-top: 0.125rem;"></i>
                    <div>
                        <strong style="color: #e65100; font-size: 0.875rem;">Review Guidelines:</strong>
                        <ul style="margin: 0.5rem 0 0 0; padding-left: 1.25rem; color: #e65100; font-size: 0.8125rem;">
                            <li>Be honest and constructive</li>
                            <li>Comment on organization, location, and overall experience</li>
                            <li>Mention what you enjoyed and what could improve</li>
                            <li>Keep it respectful and professional</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> Submit Review
                </button>
                <a href="../tournament/tournaments.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const stars = document.querySelectorAll('#ratingStars i');
const ratingValue = document.getElementById('ratingValue');
let selectedRating = 0;

stars.forEach(star => {
    star.addEventListener('mouseenter', function() {
        const rating = parseInt(this.getAttribute('data-rating'));
        highlightStars(rating);
    });
    
    star.addEventListener('click', function() {
        selectedRating = parseInt(this.getAttribute('data-rating'));
        ratingValue.value = selectedRating;
        highlightStars(selectedRating);
    });
});

document.getElementById('ratingStars').addEventListener('mouseleave', function() {
    highlightStars(selectedRating);
});

function highlightStars(rating) {
    stars.forEach((star, index) => {
        if (index < rating) {
            star.className = 'fas fa-star';
            star.style.color = '#ff9800';
        } else {
            star.className = 'far fa-star';
            star.style.color = '#dee2e6';
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
