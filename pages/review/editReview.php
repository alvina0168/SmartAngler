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

    // Handle image upload
    $review_image = null;
    if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['review_image']['tmp_name'];
        $fileName = $_FILES['review_image']['name'];
        $fileSize = $_FILES['review_image']['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExt, $allowedExts) && $fileSize <= 5 * 1024 * 1024) { // max 5MB
            $newFileName = uniqid('review_', true) . '.' . $fileExt;
            $uploadPath = '../../uploads/reviews/' . $newFileName;

            if (!is_dir('../../uploads/reviews')) mkdir('../../uploads/reviews', 0755, true);
            if (move_uploaded_file($fileTmpPath, $uploadPath)) {
                $review_image = 'uploads/reviews/' . $newFileName;
            } else {
                $_SESSION['error'] = 'Failed to move uploaded image.';
            }
        } else {
            $_SESSION['error'] = 'Invalid image file (allowed: jpg, jpeg, png, gif; max 5MB).';
        }
    }

    if (!isset($_SESSION['error'])) {
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
                    " . ($review_image ? ", review_image = '$review_image'" : "") . "
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

<style>
:root {
    --ocean-blue: #0A4D68;
    --ocean-light: #088395;
    --text-dark: #1A1A1A;
    --text-muted: #6B7280;
    --white: #FFFFFF;
    --sand: #F8F6F0;
}

/* Container */
.review-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 1rem;
}

/* Card */
.section-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    padding: 2rem;
    margin-bottom: 2rem;
}

/* Tournament Info */
.tournament-info {
    background: #e3f2fd;
    border-left: 4px solid var(--ocean-light);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}
.tournament-info .title { font-weight: 600; color: var(--ocean-blue); margin-bottom: 0.25rem; }
.tournament-info .date { font-size: 0.875rem; color: var(--ocean-light); }

/* Rating Stars */
#ratingStars i { font-size: 2.5rem; cursor: pointer; transition: color 0.2s ease; }

/* Form Fields */
.form-group { margin-bottom: 1.5rem; }
label { font-weight: 600; color: var(--text-dark); }
textarea.form-control { width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 12px; resize: vertical; font-size: 14px; }
textarea.form-control:focus { outline: none; border-color: var(--ocean-light); }
input[type="file"] { border-radius: 12px; padding: 0.5rem; }

/* Image Preview */
.image-preview {
    margin-top: 0.75rem;
    max-width: 200px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Anonymous Checkbox */
.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--sand);
    border-radius: 12px;
    cursor: pointer;
}
.checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
.checkbox-wrapper .text { display: flex; flex-direction: column; }
.checkbox-wrapper .text .title { font-weight: 600; color: var(--text-dark); }
.checkbox-wrapper .text .description { font-size: 0.8125rem; color: var(--text-muted); }

/* Buttons */
.form-actions { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 2rem; }
.btn-submit, .btn-cancel {
    padding: 0.75rem 1.5rem; font-weight: 600; border-radius: 12px; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease;
}
.btn-submit { background: var(--ocean-light); color: var(--white); border: none; }
.btn-submit:hover { background: var(--ocean-blue); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.btn-cancel { background: #F3F4F6; color: var(--text-dark); }
.btn-cancel:hover { background: #E5E7EB; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
</style>

<div class="review-container">
    <div class="section-card">
        <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">
            <i class="fas fa-edit"></i> Edit Your Review
        </h3>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
            Update your review for <?= htmlspecialchars($review['tournament_title']) ?>
        </p>

        <div class="tournament-info">
            <div class="title"><i class="fas fa-trophy"></i> <?= htmlspecialchars($review['tournament_title']) ?></div>
            <div class="date"><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($review['tournament_date'])) ?></div>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Rating -->
            <div class="form-group">
                <label>Your Rating <span style="color: #EF4444;">*</span></label>
                <div id="ratingStars" style="display:flex; gap:0.5rem; margin-top:0.5rem;">
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
                <label>Your Review <span style="color: #EF4444;">*</span></label>
                <textarea name="review_text" class="form-control" rows="8" required><?= htmlspecialchars($review['review_text']) ?></textarea>
            </div>

            <!-- Existing Image -->
            <?php if (!empty($review['review_image'])): ?>
                <div class="form-group">
                    <label>Existing Image</label>
                    <img src="../../<?= htmlspecialchars($review['review_image']) ?>" class="image-preview" alt="Review Image">
                </div>
            <?php endif; ?>

            <!-- Upload New Image -->
            <div class="form-group">
                <label>Upload a New Image (optional)</label>
                <input type="file" name="review_image" accept=".jpg,.jpeg,.png,.gif">
                <small style="color: var(--text-muted); display:block; margin-top:0.25rem;">Max size 5MB. Allowed: jpg, jpeg, png, gif</small>
            </div>

            <!-- Anonymous Checkbox -->
            <label class="checkbox-wrapper">
                <input type="checkbox" name="is_anonymous" <?= $review['is_anonymous'] ? 'checked' : '' ?>>
                <div class="text">
                    <div class="title"><i class="fas fa-user-secret"></i> Post anonymously</div>
                    <div class="description">Your name will be hidden from public view</div>
                </div>
            </label>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Review</button>
                <a href="myReviews.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
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
        highlightStars(parseInt(this.getAttribute('data-rating')));
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
