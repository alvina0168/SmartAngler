<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *         ADMIN REQUEST - SIMPLE EMAIL ONLY (NO DATABASE)
 * ═══════════════════════════════════════════════════════════════
 * 
 * This version:
 * - Collects form data
 * - Uploads files
 * - Sends email to admin
 * - NO DATABASE NEEDED!
 */

// Set JSON header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//                  VALIDATE INPUTS
// ═══════════════════════════════════════════════════════════════

$errors = [];

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$id_card = isset($_POST['id_card']) ? trim($_POST['id_card']) : '';
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$additional_info = isset($_POST['additional_info']) ? trim($_POST['additional_info']) : '';

// Validation
if (empty($name)) $errors[] = "Name is required";
if (empty($email)) $errors[] = "Email is required";
if (empty($phone)) $errors[] = "Phone is required";
if (empty($id_card)) $errors[] = "ID Card number is required";
if (empty($location)) $errors[] = "Location is required";

// Validate email format
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}

// Check if ID card photo is uploaded
if (!isset($_FILES['id_card_photo']) || $_FILES['id_card_photo']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = "ID Card photo is required";
}

// Return errors if any
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//                  HANDLE FILE UPLOADS
// ═══════════════════════════════════════════════════════════════

$upload_dir = dirname(__DIR__) . '/assets/images/admin_requests/';

// Create directory if not exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
$max_file_size = 5 * 1024 * 1024; // 5MB

$id_card_photo_path = '';
$location_proof_path = '';

// Handle ID Card Photo (Required)
if (isset($_FILES['id_card_photo']) && $_FILES['id_card_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['id_card_photo'];
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID card photo format. Only JPG, PNG allowed.']);
        exit;
    }
    
    if ($file['size'] > $max_file_size) {
        echo json_encode(['success' => false, 'message' => 'ID card photo is too large. Maximum 5MB.']);
        exit;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'id_card_' . $name . '_' . date('YmdHis') . '.' . $ext;
    $id_card_photo_path = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $id_card_photo_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload ID card photo.']);
        exit;
    }
}

// Handle Location Proof (Optional)
if (isset($_FILES['location_proof']) && $_FILES['location_proof']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['location_proof'];
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid location proof format. Only JPG, PNG allowed.']);
        exit;
    }
    
    if ($file['size'] > $max_file_size) {
        echo json_encode(['success' => false, 'message' => 'Location proof is too large. Maximum 5MB.']);
        exit;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'location_' . $name . '_' . date('YmdHis') . '.' . $ext;
    $location_proof_path = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $location_proof_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload location proof.']);
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
//                  SEND EMAIL WITH PHPMAILER
// ═══════════════════════════════════════════════════════════════

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If using Composer, uncomment this:
// require '../vendor/autoload.php';

// If manual installation, uncomment these:
require '../includes/PHPMailer/src/Exception.php';
require '../includes/PHPMailer/src/PHPMailer.php';
require '../includes/PHPMailer/src/SMTP.php';

try {
    $mail = new PHPMailer(true);
    
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'alvinaao0168@gmail.com'; //  Gmail
    $mail->Password = 'nixakmguanvrspcb'; // Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Recipients
    $mail->setFrom('noreply@smartangler.com', 'SmartAngler');
    $mail->addAddress('alvinaao0168@gmail.com'); // Your email
    $mail->addReplyTo($email, $name);
    
    // Attachments
    if (!empty($id_card_photo_path) && file_exists($id_card_photo_path)) {
        $mail->addAttachment($id_card_photo_path, 'ID_Card_' . $name . '.jpg');
    }
    
    if (!empty($location_proof_path) && file_exists($location_proof_path)) {
        $mail->addAttachment($location_proof_path, 'Location_Proof_' . $name . '.jpg');
    }
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = '🎣 New Admin Access Request - SmartAngler';
    
    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
            .header { background: linear-gradient(135deg, #6D94C5 0%, #3D5A80 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: white; border-radius: 0 0 10px 10px; }
            .info-row { margin: 15px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #6D94C5; border-radius: 5px; }
            .label { font-weight: bold; color: #6D94C5; display: block; margin-bottom: 5px; }
            .value { color: #333; }
            .action-box { background: #FFF3CD; border-left: 4px solid #FFC107; padding: 20px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎣 New Admin Access Request</h1>
                <p>Someone wants to become a tournament admin!</p>
            </div>
            
            <div class='content'>
                <h2 style='color: #6D94C5; margin-top: 0;'>Applicant Information</h2>
                
                <div class='info-row'>
                    <span class='label'>👤 Full Name:</span>
                    <span class='value'>" . htmlspecialchars($name) . "</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>📧 Email Address:</span>
                    <span class='value'>" . htmlspecialchars($email) . "</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>📱 Phone Number:</span>
                    <span class='value'>" . htmlspecialchars($phone) . "</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>🆔 ID Card Number:</span>
                    <span class='value'>" . htmlspecialchars($id_card) . "</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>📍 Tournament Location:</span>
                    <span class='value'>" . htmlspecialchars($location) . "</span>
                </div>
                
                " . (!empty($additional_info) ? "
                <div class='info-row'>
                    <span class='label'>📝 Additional Information:</span>
                    <span class='value'>" . nl2br(htmlspecialchars($additional_info)) . "</span>
                </div>
                " : "") . "
                
                <div class='info-row'>
                    <span class='label'>📎 Attachments:</span>
                    <span class='value'>
                        ✅ ID Card Photo: Attached to this email<br>
                        " . (!empty($location_proof_path) ? "✅ Location Proof: Attached to this email" : "❌ Location Proof: Not provided") . "
                    </span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>🕒 Submitted:</span>
                    <span class='value'>" . date('d M Y, g:i A') . "</span>
                </div>
                
                <div class='action-box'>
                    <h3 style='margin-top: 0; color: #856404;'>⚠️ What to do next:</h3>
                    <ol style='margin: 10px 0; padding-left: 20px;'>
                        <li>Review the attached documents (ID card & location proof)</li>
                        <li>Verify the information provided</li>
                        <li>Contact applicant at: <strong>" . htmlspecialchars($email) . "</strong></li>
                        <li>If approved: Send them registration link or create account</li>
                        <li>If rejected: Send them a polite email explaining why</li>
                    </ol>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <p style='font-size: 14px; color: #666;'>Reply directly to this email to contact the applicant</p>
                </div>
            </div>
            
            <div style='text-align: center; margin-top: 20px; color: #999; font-size: 12px;'>
                <p>This is an automated email from SmartAngler<br>
                Received: " . date('d M Y, g:i A') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mail->AltBody = "
New Admin Access Request - SmartAngler

Name: $name
Email: $email
Phone: $phone
ID Card: $id_card
Location: $location
" . (!empty($additional_info) ? "Additional Info: $additional_info\n" : "") . "
Submitted: " . date('d M Y, g:i A') . "

Please review the attached documents and contact the applicant.
    ";
    
    $mail->send();
    $email_sent = true;
    
} catch (Exception $e) {
    $email_sent = false;
    error_log("Email error: " . $mail->ErrorInfo);
    
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to send email. Please check your email configuration. Error: ' . $mail->ErrorInfo
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//                  SEND CONFIRMATION TO APPLICANT
// ═══════════════════════════════════════════════════════════════

try {
    $confirmMail = new PHPMailer(true);
    
    $confirmMail->isSMTP();
    $confirmMail->Host = 'smtp.gmail.com';
    $confirmMail->SMTPAuth = true;
    $confirmMail->Username = 'alvinaao0168@gmail.com';
    $confirmMail->Password = 'your-app-password-here'; // Same password
    $confirmMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $confirmMail->Port = 587;
    
    $confirmMail->setFrom('noreply@smartangler.com', 'SmartAngler');
    $confirmMail->addAddress($email, $name);
    $confirmMail->addReplyTo('alvinaao0168@gmail.com', 'SmartAngler Admin');
    
    $confirmMail->isHTML(true);
    $confirmMail->Subject = 'Admin Access Request Received - SmartAngler';
    
    $confirmMail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #6D94C5 0%, #3D5A80 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .success-box { background: #D5F4E6; border-left: 4px solid #27AE60; padding: 20px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Request Received!</h1>
            </div>
            
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
                
                <div class='success-box'>
                    <h3 style='margin-top: 0; color: #27AE60;'>✅ Your admin access request has been received!</h3>
                    <p style='margin-bottom: 0;'>We've successfully received your application to become a tournament admin on SmartAngler.</p>
                </div>
                
                <h3 style='color: #6D94C5;'>What happens next?</h3>
                <ul>
                    <li>📋 Our team will review your application and documents</li>
                    <li>🔍 We'll verify your information and tournament location</li>
                    <li>📧 You'll receive a response within 24-48 hours</li>
                    <li>✅ If approved, we'll send you instructions to create your admin account</li>
                </ul>
                
                <h3 style='color: #6D94C5;'>Your Submitted Information:</h3>
                <ul>
                    <li><strong>Name:</strong> " . htmlspecialchars($name) . "</li>
                    <li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>
                    <li><strong>Phone:</strong> " . htmlspecialchars($phone) . "</li>
                    <li><strong>Location:</strong> " . htmlspecialchars($location) . "</li>
                </ul>
                
                <p>If you have any questions, feel free to reply to this email.</p>
                
                <p style='margin-top: 30px;'>Best regards,<br><strong>SmartAngler Team</strong></p>
            </div>
            
            <div style='text-align: center; margin-top: 20px; color: #999; font-size: 12px;'>
                <p>This is an automated confirmation from SmartAngler</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $confirmMail->send();
    
} catch (Exception $e) {
    // Don't fail if confirmation email fails
    error_log("Confirmation email error: " . $confirmMail->ErrorInfo);
}

// ═══════════════════════════════════════════════════════════════
//                  RETURN SUCCESS
// ═══════════════════════════════════════════════════════════════

echo json_encode([
    'success' => true,
    'message' => 'Your admin access request has been submitted successfully! We\'ll review your application and get back to you within 24-48 hours. Check your email for confirmation.'
]);
?>