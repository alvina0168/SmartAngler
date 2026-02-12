<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require '../../includes/PHPMailer/src/PHPMailer.php';
require '../../includes/PHPMailer/src/SMTP.php';
require '../../includes/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

requireAdminAccess();

$page_title = 'Create Tournament Notification';
$page_description = 'Send notification to all participants of a tournament';

$error = '';
$success = '';
$tournaments_result = mysqli_query($conn, "SELECT tournament_id, tournament_title FROM TOURNAMENT ORDER BY tournament_date DESC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize($_POST['title']);
    $message = sanitize($_POST['message']);
    $tournament_id = intval($_POST['tournament_id']);

    if (empty($title) || empty($message) || $tournament_id <= 0) {
        $error = 'Please fill in all required fields and select a tournament.';
    } else {
        $participants_query = "SELECT user_id FROM TOURNAMENT_REGISTRATION WHERE tournament_id=? AND approval_status='approved'";
        $stmt = mysqli_prepare($conn, $participants_query);
        mysqli_stmt_bind_param($stmt, "i", $tournament_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $user_id = $row['user_id'];

                $insert = mysqli_prepare($conn, "INSERT INTO NOTIFICATION (user_id, tournament_id, title, message) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($insert, "iiss", $user_id, $tournament_id, $title, $message);
                mysqli_stmt_execute($insert);

                // Get participant email
                $user_query = "SELECT email, full_name FROM USER WHERE user_id=?";
                $stmt_user = mysqli_prepare($conn, $user_query);
                mysqli_stmt_bind_param($stmt_user, "i", $user_id);
                mysqli_stmt_execute($stmt_user);
                $result_user = mysqli_stmt_get_result($stmt_user);
                $user = mysqli_fetch_assoc($result_user);

                $email = $user['email'];
                $name = $user['full_name'];

                // Send email using PHPMailer
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com'; 
                    $mail->SMTPAuth = true;
                    $mail->Username = 'alvinaao0168@gmail.com@gmail.com'; 
                    $mail->Password = 'xmwxeyplblbyjeaj'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('noreply@smartangler.com', 'SmartAngler');
                    $mail->addAddress($email, $name);

                    $mail->isHTML(true);
                    $mail->Subject = "🎣 $title";
                    $mail->Body = "<p>Dear <strong>$name</strong>,</p>
                                   <p>$message</p>
                                   <p>SmartAngler Team</p>";
                    $mail->AltBody = "$message";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Email error to $email: " . $mail->ErrorInfo);
                }
            }

            $_SESSION['success'] = 'Notification sent successfully to all participants!';
            redirect('notificationList.php');
        } else {
            $error = 'No approved participants found for this tournament.';
        }
    }
}

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
        <!-- Select Tournament -->
        <div class="form-group">
            <label>Select Tournament <span class="required">*</span></label>
            <select name="tournament_id" class="form-control" required>
                <option value="">-- Select Tournament --</option>
                <?php while($t = mysqli_fetch_assoc($tournaments_result)): ?>
                    <option value="<?= $t['tournament_id'] ?>"><?= htmlspecialchars($t['tournament_title']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Notification Title -->
        <div class="form-group">
            <label>Notification Title <span class="required">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="Enter notification title" required>
        </div>

        <!-- Notification Message -->
        <div class="form-group">
            <label>Message <span class="required">*</span></label>
            <textarea name="message" class="form-control" placeholder="Enter notification message" required></textarea>
        </div>

        <div class="form-actions">
            <a href="notificationList.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Send Notification
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
