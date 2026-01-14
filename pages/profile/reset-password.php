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
:root {
    --ocean-blue: #0A4D68;
    --ocean-light: #088395;
    --ocean-teal: #05BFDB;
    --white: #FFFFFF;
    --text-dark: #1A1A1A;
    --text-muted: #6B7280;
}

/* Reset Password Page */
.reset-page {
    min-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    padding: 60px 20px;
    font-family: 'Segoe UI', sans-serif;
}

.reset-card {
    background: var(--white);
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    width: 100%;
    max-width: 450px;
    text-align: center;
}

.reset-card h2 {
    font-size: 28px;
    font-weight: 800;
    color: var(--ocean-blue);
    margin-bottom: 20px;
}

.reset-card p {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 25px;
}

.form-group {
    text-align: left;
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 600;
    color: var(--text-dark);
    display: block;
    margin-bottom: 6px;
}

.form-group input {
    width: 100%;
    padding: 12px 16px;
    border-radius: 12px;
    border: 2px solid #E5E7EB;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
}

.form-group input:focus {
    border-color: var(--ocean-light);
    box-shadow: 0 0 0 3px rgba(8, 131, 149, 0.2);
}

.btn-primary {
    width: 100%;
    padding: 12px 0;
    border-radius: 12px;
    border: none;
    background: var(--ocean-light);
    color: var(--white);
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background: var(--ocean-blue);
}

.btn-login {
    display: block;
    width: 100%;
    padding: 12px 0;
    margin-top: 20px;
    background: var(--ocean-teal);
    color: var(--white);
    font-size: 16px;
    border: none;
    border-radius: 12px;
    text-align: center;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-login:hover {
    background: #04A0C6;
}

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    text-align: center;
}

.alert-danger { background: #FEE2E2; color: #B91C1C; }
.alert-success { background: #D1FAE5; color: #065F46; }

/* Responsive */
@media (max-width: 480px) {
    .reset-card {
        padding: 30px 20px;
    }

    .reset-card h2 {
        font-size: 24px;
    }
}
</style>

<div class="reset-page">
    <div class="reset-card">
        <h2><i class="fas fa-key"></i> Reset Password</h2>
        <p>Enter your new password below.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if (empty($success) && (empty($error) || !empty($user_id))): ?>
            <form method="POST">
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

        <?php if ($success): ?>
            <a href="<?php echo SITE_URL; ?>/pages/authentication/login.php" class="btn-login">Go to Login</a>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
