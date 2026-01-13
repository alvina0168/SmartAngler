<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Edit Review';

if (!isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/pages/authentication/login.php');
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Review ID is missing!';
    redirect(SITE_URL . '/pages/review/myReviews.php');
}

$user_id = $_SESSION['user_id'];
$review_id = intval($_GET['id']);

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
        $update_query = "
            UPDATE REVIEW SET
                rating = $rating,
                review_text = '$review_text',
                is_anonymous = $is_anonymous,
                review_date = NOW()
            WHERE review_id = $review_id AND user_id = $user_id
        ";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Review updated successfully!';
            redirect(SITE_URL . '/pages/review/myReviews.php');
        } else {
            $_SESSION['error'] = 'Failed to update review: ' . mysqli_error($conn);
        }
    }
}

// Fetch review
$query = "
    SELECT r.*, t.tournament_title, t.tournament_date
    FROM REVIEW r
    JOIN TOURNAMENT t ON r.tournament_id = t.tournament_id
    WHERE r.review_id = $review_id AND r.user_id = $user_id
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Review not found or you do not have permission to edit it!';
    redirect(SITE_URL . '/pages/review/myReviews.php');
}

$review = mysqli_fetch_assoc($result);

include '../../includes/header.php';
?>

<div class="container" style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
    <!-- Back Button -->
    <div style="margin-bottom: 1.5rem;">
        <a href="myReviews.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to My Reviews
        </a>
    </div>

    <!-- Edit Review Form -->
    <div class="section">
        <div class="section-header">
            <div>
                <h3 class="section-title">
                    <i class="fas fa-edit"></i> Edit Your Review
                </h3>
                <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                    Update your review for <?= htmlspecialchars($review['tournament_title']) ?>
                </p>
            </div>
        </div>

        <!-- Tournament Info -->
        <div style="background: #e3f2fd; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border-left: 4px solid #6D94C5;">
            <div style="font-weight: 600; color: #1565c0; margin-bottom: 0.25rem;">
                <i class="fas fa-trophy"></i> <?= htmlspecialchars($review['tournament_title']) ?>
            </div>
            <div style="font-size: 0.875rem; color: #1976d2;">
                <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($review['tournament_date'])) ?>
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
                <input type="hidden" name="rating" id="ratingValue" value="<?= $review['rating'] ?>" required>
            </div>

            <!-- Review Text -->
            <div class="form-group">
                <label>Your Review <span class="required">*</span></label>
                <textarea name="review_text" 
                          class="form-control" 
                          rows="8" 
                          required><?= htmlspecialchars($review['review_text']) ?></textarea>
            </div>

            <!-- Anonymous Checkbox -->
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <input type="checkbox" name="is_anonymous" id="is_anonymous" 
                           <?= $review['is_anonymous'] ? 'checked' : '' ?> 
                           style="width: 18px; height: 18px; cursor: pointer;">
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

            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Update Review
                </button>
                <a href="myReviews.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const stars = document.querySelectorAll('#ratingStars i');
const ratingValue = document.getElementById('ratingValue');
let selectedRating = parseInt(ratingValue.value);

highlightStars(selectedRating);

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
