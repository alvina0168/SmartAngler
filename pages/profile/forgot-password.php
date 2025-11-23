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

        // Send email
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;

            // YOUR GMAIL + APP PASSWORD HERE
            $mail->Username = 'alvinaao0168@gmail.com';
            $mail->Password = 'xmwxeyplblbyjeaj'; // your Gmail App Password

            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // FROM email MUST match your Gmail address
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
    .forgot-container {
        max-width: 450px;
        margin: 70px auto;
        background: white;
        padding: 35px 40px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        font-family: 'Segoe UI', sans-serif;
    }
    .forgot-title {
        color: #6D94C5;
        text-align: center;
        margin-bottom: 15px;
        font-size: 28px;
        font-weight: bold;
    }
    .forgot-sub {
        text-align: center;
        color: #555;
        font-size: 14px;
        margin-bottom: 25px;
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
    .alert {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
        text-align: center;
        font-size: 14px;
    }
    .alert-danger { background: #ffdddd; color: #b90000; }
    .alert-success { background: #ddffdd; color: #006b24; }
    .back-link {
        display: block;
        text-align: center;
        margin-top: 15px;
    }
    .back-link a {
        color: #6D94C5;
        text-decoration: none;
        font-weight: 600;
    }
    .back-link a:hover {
        text-decoration: underline;
    }
</style>

<div class="forgot-container">
    <h2 class="forgot-title">Forgot Password</h2>
    <p class="forgot-sub">Enter your email and we’ll send you a reset link.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" class="form-card">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your email" required>
        </div>

        <button class="btn btn-primary">Send Reset Link</button>
    </form>

    <div class="back-link">
        <a href="../authentication/login.php">← Back to Login</a>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
