<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdminAccess();

$admin_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; 
$error = '';
$success = '';

if ($user_role === 'admin') {
    $stmt = mysqli_prepare($conn, "
        SELECT u.full_name, u.email, u.phone_number, u.profile_image, u.username,
               organizer.full_name as organizer_name, organizer.user_id as organizer_id
        FROM USER u
        LEFT JOIN USER organizer ON u.created_by = organizer.user_id
        WHERE u.user_id=?
    ");
} else {
    $stmt = mysqli_prepare($conn, "SELECT full_name, email, phone_number, profile_image, username FROM USER WHERE user_id=?");
}
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($user_role === 'organizer') {
        $email = sanitize($_POST['email']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        }
    }

    if (empty($full_name) || empty($phone)) {
        $error = "Full name and phone number are required.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    }

    $profile_image_db = $admin['profile_image']; 
    if ($user_role === 'organizer' && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $error = "Invalid image format. Only JPG and PNG allowed.";
        } elseif ($_FILES['profile_image']['size'] > $max_file_size) {
            $error = "Image is too large. Maximum 5MB allowed.";
        } else {
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
            $upload_dir = __DIR__ . '/../../assets/images/profiles/';

            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $profile_image_db = 'assets/images/profiles/' . $filename;
            } else {
                $error = "Failed to upload profile image.";
            }
        }
    }

    if (empty($error)) {
        if ($user_role === 'organizer') {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_update = mysqli_prepare($conn, "UPDATE USER SET full_name=?, email=?, phone_number=?, profile_image=?, password=? WHERE user_id=?");
                mysqli_stmt_bind_param($stmt_update, "sssssi", $full_name, $email, $phone, $profile_image_db, $hashed_password, $admin_id);
            } else {
                $stmt_update = mysqli_prepare($conn, "UPDATE USER SET full_name=?, email=?, phone_number=?, profile_image=? WHERE user_id=?");
                mysqli_stmt_bind_param($stmt_update, "ssssi", $full_name, $email, $phone, $profile_image_db, $admin_id);
            }
        } else {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_update = mysqli_prepare($conn, "UPDATE USER SET full_name=?, phone_number=?, password=? WHERE user_id=?");
                mysqli_stmt_bind_param($stmt_update, "sssi", $full_name, $phone, $hashed_password, $admin_id);
            } else {
                $stmt_update = mysqli_prepare($conn, "UPDATE USER SET full_name=?, phone_number=? WHERE user_id=?");
                mysqli_stmt_bind_param($stmt_update, "ssi", $full_name, $phone, $admin_id);
            }
        }

        if (mysqli_stmt_execute($stmt_update)) {
            $success = "Profile updated successfully!";
            $admin['full_name'] = $full_name;
            $admin['phone_number'] = $phone;
            if ($user_role === 'organizer') {
                $admin['email'] = $email;
                $admin['profile_image'] = $profile_image_db;
            }
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}

$page_title = 'My Profile';
include '../includes/header.php';
?>

<style>
.profile-info-card {
    background: #f8f9fa;
    border-left: 4px solid var(--color-blue-primary);
    padding: 1rem;
    border-radius: var(--radius-md);
    margin-bottom: 1.5rem;
}

.profile-info-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.profile-info-item:last-child {
    border-bottom: none;
}

.profile-info-label {
    font-weight: 600;
    color: var(--color-gray-700);
    width: 180px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-info-value {
    color: var(--color-gray-900);
    flex: 1;
}

.role-badge-organizer {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.role-badge-admin {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.readonly-field {
    background-color: #e9ecef;
    cursor: not-allowed;
}
</style>

<div class="form-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 class="form-header-title">
            <i class="fas fa-user"></i> My Profile
        </h2>
        <div>
            <?php if ($user_role === 'organizer'): ?>
                <span class="role-badge-organizer">
                    <i class="fas fa-user-tie"></i> Organizer
                </span>
            <?php else: ?>
                <span class="role-badge-admin">
                    <i class="fas fa-user-shield"></i> Admin
                </span>
            <?php endif; ?>
        </div>
    </div>

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

    <?php if ($user_role === 'admin'): ?>
        <!-- Admin View: Read-only info section -->
        <div class="profile-info-card">
            <h3 style="color: var(--color-blue-primary); margin-bottom: 1rem; font-size: 1.125rem;">
                <i class="fas fa-info-circle"></i> Account Information
            </h3>
            
            <div class="profile-info-item">
                <div class="profile-info-label">
                    <i class="fas fa-user"></i> Username
                </div>
                <div class="profile-info-value">
                    <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
                </div>
            </div>
            
            <div class="profile-info-item">
                <div class="profile-info-label">
                    <i class="fas fa-envelope"></i> Email
                </div>
                <div class="profile-info-value">
                    <?php echo htmlspecialchars($admin['email']); ?>
                </div>
            </div>
            
            <?php if (!empty($admin['organizer_name'])): ?>
            <div class="profile-info-item">
                <div class="profile-info-label">
                    <i class="fas fa-user-tie"></i> Belongs To
                </div>
                <div class="profile-info-value">
                    <strong><?php echo htmlspecialchars($admin['organizer_name']); ?></strong>
                    <span class="badge badge-info" style="margin-left: 0.5rem;">Organizer</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <strong>Admin Access:</strong> You can only update your name, phone number, and password. Email cannot be changed.
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-card">
        <h3 style="color: var(--color-blue-primary); margin-bottom: 1.5rem; font-size: 1.125rem;">
            <i class="fas fa-edit"></i> <?php echo ($user_role === 'admin') ? ' Admin Detail' : 'Profile Information'; ?>
        </h3>

        <div class="form-group">
            <label>Full Name <span class="required">*</span></label>
            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
        </div>

        <?php if ($user_role === 'organizer'): ?>
        <div class="form-group">
            <label>Email <span class="required">*</span></label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Phone Number <span class="required">*</span></label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone_number']); ?>" required>
        </div>

        <?php if ($user_role === 'organizer'): ?>
        <div class="form-group">
            <label>Profile Image</label>
            <input type="file" name="profile_image" class="form-control" accept="image/jpeg,image/png,image/jpg">
            <?php if (!empty($admin['profile_image'])): ?>
                <div style="margin-top: 1rem;">
                    <img src="<?php echo SITE_URL . '/' . $admin['profile_image']; ?>" 
                         alt="Profile Image" 
                         style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid var(--color-blue-primary);">
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <hr style="margin: 2rem 0; border: none; border-top: 2px solid #e9ecef;">

        <h3 style="color: var(--color-blue-primary); margin-bottom: 1.5rem; font-size: 1.125rem;">
            <i class="fas fa-lock"></i> Change Password
        </h3>

        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Leave password fields empty if you don't want to change your password.
        </div>

        <div class="form-group">
            <label>New Password (optional)</label>
            <input type="password" name="password" class="form-control" placeholder="Enter new password (min. 6 characters)">
            <small style="color: var(--color-gray-600); display: block; margin-top: 0.25rem;">
                <i class="fas fa-info-circle"></i> Minimum 6 characters required
            </small>
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