<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];

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

    $stmt = $conn->prepare("SELECT user_id FROM USER WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $error = 'Email already in use.';
    }
    $stmt->close();

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

.profile-hero {
    background: linear-gradient(135deg, var(--ocean-blue) 0%, var(--ocean-light) 100%);
    padding: 20px 0 20px;
    text-align: center;
    color: var(--white);
}

.profile-hero h1 {
    font-size: 42px;
    font-weight: 800;
    margin: 0 0 12px;
}

.profile-hero p {
    font-size: 18px;
    opacity: 0.9;
}

.profile-section {
    background: var(--sand);
    padding: 60px 0;
    min-height: 70vh;
}

.profile-card {
    max-width: 700px;
    margin: 0 auto;
    background: var(--white);
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.profile-card:hover {
    transform: translateY(-3px);
}

.profile-card h2 {
    color: var(--ocean-blue);
    margin-bottom: 30px;
    text-align: center;
    font-size: 28px;
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

.profile-card .form-group {
    margin-bottom: 20px;
}

.profile-card label {
    font-weight: 600;
    display: block;
    margin-bottom: 8px;
    color: var(--text-dark);
}

.profile-card input[type="text"],
.profile-card input[type="email"],
.profile-card input[type="password"],
.profile-card input[type="file"] {
    width: 100%;
    padding: 12px 15px;
    border-radius: 10px;
    border: 1px solid var(--border);
    font-size: 14px;
    background: #FAFAFA;
    transition: all 0.2s ease;
}

.profile-card input[type="text"]:focus,
.profile-card input[type="email"]:focus,
.profile-card input[type="password"]:focus {
    border-color: var(--ocean-light);
    box-shadow: 0 0 0 2px rgba(8,131,149,0.2);
    outline: none;
}

.profile-card .profile-image-preview {
    display: block;
    margin: 0 auto 20px auto;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--ocean-light);
    transition: all 0.3s ease;
}

.profile-card .profile-image-preview:hover {
    transform: scale(1.05);
}

.profile-card button {
    background: var(--ocean-light);
    color: var(--white);
    padding: 12px 25px;
    font-weight: 600;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.profile-card button:hover {
    background: var(--ocean-blue);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .profile-card {
        padding: 25px;
    }
    .profile-hero h1 {
        font-size: 32px;
    }
    .profile-hero p {
        font-size: 16px;
    }
}
</style>

<div class="profile-hero">
    <h1>My Profile</h1>
    <p>Update your information and profile image</p>
</div>

<section class="profile-section">
    <div class="profile-card">
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