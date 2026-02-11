<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../includes/PHPMailer/src/Exception.php';
require_once '../../includes/PHPMailer/src/PHPMailer.php';
require_once '../../includes/PHPMailer/src/SMTP.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM USER WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $error = "Email not found.";
    } else {
        $token = bin2hex(random_bytes(16));
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];

        // Save token
        $conn->query("
            UPDATE USER 
            SET reset_token='$token', reset_expiry=DATE_ADD(NOW(), INTERVAL 1 HOUR) 
            WHERE user_id=$user_id
        ");

        // Create reset link
        $reset_link = SITE_URL . "/pages/profile/reset-password.php?token=$token";
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'alvinaao0168@gmail.com';
            $mail->Password = 'xmwxeyplblbyjeaj'; // Gmail App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('alvinaao0168@gmail.com', 'SmartAngler Support');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset';
            $mail->Body = "
                <p>You requested a password reset.</p>
                <p>Click this link to reset:</p>
                <p><a href='$reset_link'>$reset_link</a></p>
                <p>This link expires in 1 hour.</p>
            ";

            $mail->send();
            $success = "A reset link has been sent to your email.";

        } catch (Exception $e) {
            $error = "Email sending failed: " . $mail->ErrorInfo;
        }
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

.forgot-page {
    min-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    padding: 60px 20px;
    font-family: 'Segoe UI', sans-serif;
}

.forgot-card {
    background: var(--white);
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    width: 100%;
    max-width: 450px;
    text-align: center;
}

.forgot-card h2 {
    font-size: 28px;
    font-weight: 800;
    color: var(--ocean-blue);
    margin-bottom: 20px;
}

.forgot-card p {
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

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    text-align: center;
}

.alert-danger { background: #FEE2E2; color: #B91C1C; }
.alert-success { background: #D1FAE5; color: #065F46; }

.back-link {
    margin-top: 20px;
}

.back-link a {
    color: var(--ocean-light);
    font-weight: 600;
    text-decoration: none;
}

.back-link a:hover {
    color: var(--ocean-teal);
    text-decoration: underline;
}

@media (max-width: 480px) {
    .forgot-card {
        padding: 30px 20px;
    }

    .forgot-card h2 {
        font-size: 24px;
    }
}
</style>

<div class="forgot-page">
    <div class="forgot-card">
        <h2> Forgot Password</h2>
        <p>Enter your email and we’ll send you a reset link.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>

            <button class="btn-primary">Send Reset Link</button>
        </form>

        <div class="back-link">
            <a href="../authentication/login.php"> Back to Login</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
