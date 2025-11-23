<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Require login
requireLogin();

$user_id = $_SESSION['user_id'];

// Fetch user
$stmt = $conn->prepare("SELECT * FROM USER WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone_number = sanitize($_POST['phone_number']);

    // Validate email uniqueness
    $stmt = $conn->prepare("SELECT user_id FROM USER WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $error = 'Email already in use.';
    }
    $stmt->close();

    // Handle image upload
    $profile_image = $user['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            $newName = "profile_{$user_id}.{$ext}";
            $path = "../../assets/images/profiles/" . $newName;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $path)) {
                $profile_image = $newName;
            } else {
                $error = 'Image upload failed.';
            }
        } else {
            $error = 'Invalid image type.';
        }
    }

    // Update if no errors
    if (!$error) {
        $stmt = $conn->prepare("UPDATE USER SET full_name=?, email=?, phone_number=?, profile_image=? WHERE user_id=?");
        $stmt->bind_param("ssssi", $full_name, $email, $phone_number, $profile_image, $user_id);

        if ($stmt->execute()) {
            $success = "Profile updated.";
            $_SESSION['user_full_name'] = $full_name;
        } else {
            $error = "Update failed.";
        }
        $stmt->close();
    }
}

$page_title = "My Profile";
include '../../includes/header.php';
?>
<style>
.profile-section {
    min-height: 70vh;
    padding: 50px 0;
    background-color: #F5EFE6;
}

.profile-card {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.profile-card h2 {
    color: #6D94C5;
    margin-bottom: 30px;
    text-align: center;
}

.profile-card .form-group {
    margin-bottom: 20px;
}

.profile-card label {
    font-weight: 600;
    display: block;
    margin-bottom: 5px;
}

.profile-card input[type="text"],
.profile-card input[type="email"],
.profile-card input[type="password"] {
    width: 100%;
    padding: 12px 15px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
}

.profile-card input[type="file"] {
    border: none;
}

.profile-card .profile-image-preview {
    display: block;
    margin: 0 auto 20px auto;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #6D94C5;
}

.profile-card button {
    background: #6D94C5;
    color: white;
    padding: 12px 25px;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s;
}

.profile-card button:hover {
    background: #5a7ea8;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 600;
}

.alert-success {
    background: #D4EDDA;
    color: #155724;
    border: 2px solid #155724;
}

.alert-error {
    background: #F8D7DA;
    color: #721C24;
    border: 2px solid #721C24;
}

@media (max-width: 768px) {
    .profile-card {
        padding: 25px;
    }
}
</style>

<section class="profile-section">
    <div class="profile-card">
        <h2><i class="fas fa-user-circle"></i> My Profile</h2>

        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <img id="profilePreview"
                 class="profile-image-preview"
                 src="<?= $user['profile_image'] ? SITE_URL . '/assets/images/profiles/'.$user['profile_image'] : SITE_URL . '/assets/images/default-profile.png' ?>">

            <div class="form-group">
                <label>Profile Image</label>
                <input type="file" name="profile_image" accept="image/*" onchange="previewImage(event)">
            </div>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>">
            </div>

            <button type="submit"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</section>

<script>
function previewImage(event){
    document.getElementById('profilePreview').src = URL.createObjectURL(event.target.files[0]);
}
</script>

<?php include '../../includes/footer.php'; ?>