<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/pages/login.php');
}

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current admin data
$stmt = mysqli_prepare($conn, "SELECT full_name, email, phone_number, profile_image FROM USER WHERE user_id=?");
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate required fields
    if (empty($full_name) || empty($email) || empty($phone)) {
        $error = "Full name, email, and phone number are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Passwords do not match.";
    }

    // Handle profile image upload
    $profile_image_db = $admin['profile_image']; // keep old if no new image
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $error = "Invalid image format. Only JPG and PNG allowed.";
        } elseif ($_FILES['profile_image']['size'] > $max_file_size) {
            $error = "Image is too large. Maximum 5MB allowed.";
        } else {
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
            $upload_dir = __DIR__ . '/../../assets/images/profiles/'; // fixed path

            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $profile_image_db = 'assets/images/profiles/' . $filename; // store relative path in DB
            } else {
                $error = "Failed to upload profile image.";
            }
        }
    }

    // If no errors, update admin info
    if (empty($error)) {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update = mysqli_prepare($conn, "UPDATE USER SET full_name=?, email=?, phone_number=?, profile_image=?, password=? WHERE user_id=?");
            mysqli_stmt_bind_param($stmt_update, "sssssi", $full_name, $email, $phone, $profile_image_db, $hashed_password, $admin_id);
        } else {
            $stmt_update = mysqli_prepare($conn, "UPDATE USER SET full_name=?, email=?, phone_number=?, profile_image=? WHERE user_id=?");
            mysqli_stmt_bind_param($stmt_update, "ssssi", $full_name, $email, $phone, $profile_image_db, $admin_id);
        }

        if (mysqli_stmt_execute($stmt_update)) {
            $success = "Profile updated successfully!";
            $admin['full_name'] = $full_name;
            $admin['email'] = $email;
            $admin['phone_number'] = $phone;
            $admin['profile_image'] = $profile_image_db;
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}

include '../includes/header.php';
?>

<div class="form-container">
    <h2 class="form-header-title"><i class="fas fa-user"></i> My Profile</h2>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-card">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone_number']); ?>" required>
        </div>

        <div class="form-group">
            <label>Profile Image</label>
            <input type="file" name="profile_image" class="form-control">
            <?php if (!empty($admin['profile_image'])): ?>
                <img src="<?php echo SITE_URL . '/' . $admin['profile_image']; ?>" alt="Profile Image" style="width:100px; margin-top:10px;">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>New Password (optional)</label>
            <input type="password" name="password" class="form-control" placeholder="Enter new password">
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Profile
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
