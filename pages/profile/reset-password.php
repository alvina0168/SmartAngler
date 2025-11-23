<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$error = '';
$success = '';

// Get token from URL
$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';

if (!$token) {
    $error = "Invalid or missing token.";
} else {
    // Check if token exists and is not expired
    $stmt = $conn->prepare("SELECT user_id, reset_expiry FROM USER WHERE reset_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $error = "Invalid token.";
    } else {
        $user = $result->fetch_assoc();
        $expiry = $user['reset_expiry'];
        $user_id = $user['user_id'];

        // Check if token is expired
        if (strtotime($expiry) < time()) {
            $error = "Token has expired. Please request a new password reset.";
        }
    }
}

// Handle new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Update password (plain text) and clear token
        $stmt = $conn->prepare("UPDATE USER SET password=?, reset_token=NULL, reset_expiry=NULL WHERE user_id=?");
        $stmt->bind_param("si", $password, $user_id);
        $stmt->execute();

        // Set success message
        $success = "Your password has been reset successfully.";
    }
}

include '../../includes/header.php';
?>

<style>
.reset-container {
    max-width: 450px;
    margin: 70px auto;
    background: white;
    padding: 35px 40px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    font-family: 'Segoe UI', sans-serif;
}
.reset-title {
    color: #6D94C5;
    text-align: center;
    margin-bottom: 15px;
    font-size: 28px;
    font-weight: bold;
}
.form-group label {
    font-weight: 600;
    color: #444;
}
.form-card input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    margin-bottom: 18px;
    font-size: 15px;
}
.btn-primary {
    width: 100%;
    padding: 12px;
    background: #6D94C5;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
}
.btn-primary:hover {
    background: #5878a1;
}
.btn-login {
    display: block;
    width: 100%;
    padding: 12px;
    margin-top: 15px;
    background: #28a745;
    color: white;
    font-size: 16px;
    border: none;
    border-radius: 8px;
    text-align: center;
    text-decoration: none;
}
.btn-login:hover {
    background: #218838;
}
.alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    text-align: center;
    font-size: 14px;
}
.alert-danger { background: #ffdddd; color: #b90000; }
.alert-success { background: #ddffdd; color: #006b24; }
</style>

<div class="reset-container">
    <h2 class="reset-title">Reset Password</h2>

    <!-- Success or Error messages at the top -->
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Reset Password Form -->
    <?php if (empty($success) && empty($error) || !empty($user_id)): ?>
        <form method="POST" class="form-card">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" placeholder="Enter new password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" required>
            </div>
            <button class="btn-primary">Reset Password</button>
        </form>
    <?php endif; ?>

    <!-- Login Button below form -->
    <?php if ($success): ?>
        <a href="<?php echo SITE_URL; ?>/pages/authentication/login.php" class="btn-login">Go to Login</a>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>