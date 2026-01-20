<?php
// Start processing BEFORE any output
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Only organizers can access this page
requireOrganizer();

// Get admin ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Invalid admin ID';
    header('Location: ' . SITE_URL . '/admin/admin-management/manage-admins.php');
    exit;
}

$admin_id = intval($_GET['id']);
$organizer_id = $_SESSION['user_id'];

// Get admin details (must be created by this organizer)
$query = "SELECT * FROM USER 
          WHERE user_id = '$admin_id' 
          AND role = 'admin' 
          AND created_by = '$organizer_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Admin not found or access denied';
    header('Location: ' . SITE_URL . '/admin/admin-management/manage-admins.php');
    exit;
}

$admin = mysqli_fetch_assoc($result);

$error = '';

// PROCESS FORM (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $phone_number = sanitize($_POST['phone_number']);
    $status = sanitize($_POST['status']);
    $password = sanitize($_POST['password']);
    
    // Validation
    if (empty($full_name) || empty($username)) {
        $error = 'Please fill in all required fields';
    } elseif (!validateUsername($username)) {
        $error = 'Username must be 3-20 characters (letters, numbers, underscore only)';
    } elseif (usernameExists($username, $admin_id)) {
        $error = 'Username already exists';
    } elseif (!empty($phone_number) && !validatePhone($phone_number)) {
        $error = 'Please enter a valid phone number (10-15 digits)';
    } else {
        // Build update query
        if (!empty($password)) {
            // Update with new password
            if (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters';
            } else {
                $query = "UPDATE USER SET 
                          full_name = '$full_name',
                          username = '$username',
                          phone_number = '$phone_number',
                          password = '$password',
                          status = '$status'
                          WHERE user_id = '$admin_id'";
            }
        } else {
            // Update without changing password
            $query = "UPDATE USER SET 
                      full_name = '$full_name',
                      username = '$username',
                      phone_number = '$phone_number',
                      status = '$status'
                      WHERE user_id = '$admin_id'";
        }
        
        if (!$error && mysqli_query($conn, $query)) {
            $_SESSION['success'] = 'Admin account updated successfully!';
            header('Location: ' . SITE_URL . '/admin/admin-management/manage-admins.php');
            exit;
        } elseif (!$error) {
            $error = 'Failed to update admin account. Please try again.';
        }
    }
}

// NOW include header (after form processing)
$page_title = 'Edit Admin Account';
include '../includes/header.php';
?>

<style>
.info-box {
    background: #DBEAFE;
    border: 1px solid #93C5FD;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.info-box i {
    color: #1E40AF;
    font-size: 18px;
    margin-top: 2px;
}

.info-box-content {
    flex: 1;
}

.info-box-content strong {
    color: #1E40AF;
    display: block;
    margin-bottom: 4px;
}

.info-box-content p {
    color: #1E40AF;
    font-size: 13px;
    margin: 0;
}
</style>

<div class="form-container">
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <div class="info-box-content">
            <strong>Account Created:</strong>
            <p><?php echo date('d M Y, h:i A', strtotime($admin['created_at'])); ?></p>
        </div>
    </div>

    <form method="POST" class="form-card">
        <!-- Full Name -->
        <div class="form-group">
            <label>Full Name <span class="required">*</span></label>
            <input type="text" 
                   name="full_name" 
                   class="form-control" 
                   placeholder="Enter admin's full name"
                   value="<?php echo htmlspecialchars($admin['full_name']); ?>"
                   required>
        </div>

        <!-- Username -->
        <div class="form-group">
            <label>Username <span class="required">*</span></label>
            <input type="text" 
                   name="username" 
                   class="form-control" 
                   placeholder="Enter username"
                   value="<?php echo htmlspecialchars($admin['username']); ?>"
                   pattern="[a-zA-Z0-9_]{3,20}"
                   required>
            <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 4px;">
                This will be used for login
            </small>
        </div>

        <!-- Phone Number -->
        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" 
                   name="phone_number" 
                   class="form-control" 
                   placeholder="0123456789"
                   value="<?php echo htmlspecialchars($admin['phone_number']); ?>">
            <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 4px;">
                Optional - 10 to 15 digits only
            </small>
        </div>

        <!-- Status -->
        <div class="form-group">
            <label>Status <span class="required">*</span></label>
            <select name="status" class="form-control" required>
                <option value="active" <?php echo $admin['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $admin['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 4px;">
                Inactive admins cannot login
            </small>
        </div>

        <!-- New Password -->
        <div class="form-group">
            <label>New Password</label>
            <input type="password" 
                   name="password" 
                   class="form-control" 
                   placeholder="Leave blank to keep current password">
            <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 4px;">
                Only fill this if you want to change the password (minimum 6 characters)
            </small>
        </div>

        <div class="form-actions">
            <a href="manage-admins.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Admin Account
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>