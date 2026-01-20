<?php
// Start processing BEFORE any output
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Only organizers can access this page
requireOrganizer();

$error = '';
$success = '';

// PROCESS FORM FIRST (before any output)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $phone_number = sanitize($_POST['phone_number']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    
    // Validation
    if (empty($full_name) || empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields';
    } elseif (!validateUsername($username)) {
        $error = 'Username must be 3-20 characters (letters, numbers, underscore only)';
    } elseif (usernameExists($username)) {
        $error = 'Username already exists';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!empty($phone_number) && !validatePhone($phone_number)) {
        $error = 'Please enter a valid phone number (10-15 digits)';
    } else {
        // Insert new admin
        $organizer_id = $_SESSION['user_id'];
        
        // Generate email from username for system
        $generated_email = $username . '@smartangler.local';
        
        $query = "INSERT INTO USER (email, username, password, full_name, phone_number, role, created_by, status, created_at) 
                  VALUES ('$generated_email', '$username', '$password', '$full_name', '$phone_number', 'admin', '$organizer_id', 'active', NOW())";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = 'Admin account created successfully! Username: ' . $username;
            header('Location: ' . SITE_URL . '/admin/admin-management/manage-admins.php');
            exit;
        } else {
            $error = 'Failed to create admin account. Please try again.';
        }
    }
}

// NOW include header (after form processing)
$page_title = 'Create Admin Account';
include '../includes/header.php';
?>

<div class="form-container">
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" class="form-card">
        <!-- Full Name -->
        <div class="form-group">
            <label>Full Name <span class="required">*</span></label>
            <input type="text" 
                   name="full_name" 
                   class="form-control" 
                   placeholder="Enter admin's full name"
                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                   required>
        </div>

        <!-- Username -->
        <div class="form-group">
            <label>Username <span class="required">*</span></label>
            <input type="text" 
                   name="username" 
                   class="form-control" 
                   placeholder="Enter username (3-20 characters)"
                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                   pattern="[a-zA-Z0-9_]{3,20}"
                   required>
            <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 4px;">
                Letters, numbers, and underscore only. This will be used for login.
            </small>
        </div>

        <!-- Phone Number -->
        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" 
                   name="phone_number" 
                   class="form-control" 
                   placeholder="0123456789"
                   value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
            <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 4px;">
                Optional - 10 to 15 digits only
            </small>
        </div>

        <!-- Password -->
        <div class="form-group">
            <label>Password <span class="required">*</span></label>
            <input type="password" 
                   name="password" 
                   class="form-control" 
                   placeholder="Enter password"
                   required>
            <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 4px;">
                Minimum 6 characters
            </small>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
            <label>Confirm Password <span class="required">*</span></label>
            <input type="password" 
                   name="confirm_password" 
                   class="form-control" 
                   placeholder="Re-enter password"
                   required>
        </div>

        <div class="form-actions">
            <a href="manage-admins.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Create Admin Account
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>