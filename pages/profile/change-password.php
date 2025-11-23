<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Fetch stored password
    $stmt = $conn->prepare("SELECT password FROM USER WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    // Validate current password
    if (!password_verify($current, $result['password'])) {
        $error = "Current password is incorrect.";
    }

    if (!$error && $new !== $confirm) {
        $error = "New passwords do not match.";
    }

    if (!$error) {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE USER SET password=? WHERE user_id=?");
        $update->bind_param("si", $hashed, $user_id);
        $update->execute();
        $success = "Password updated successfully.";
    }
}

include '../../includes/header.php';
?>

<div class="content-container">
    <h2>Change Password</h2>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <form method="POST" class="form-card">
        <label>Current Password</label>
        <input type="password" name="current_password" required>

        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
