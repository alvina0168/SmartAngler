<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *         PROCESS ADMIN REGISTRATION (PLAIN PASSWORD VERSION)
 * ═══════════════════════════════════════════════════════════════
 * Matches USER table structure:
 * - email, password, full_name, phone_number
 * - role ('admin' or 'angler')
 * - status ('active' or 'inactive')
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//                  GET AND VALIDATE INPUT
// ═══════════════════════════════════════════════════════════════

$errors = [];

// Get form data
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

// Validation
if (empty($full_name)) $errors[] = "Full name is required";
if (empty($email)) $errors[] = "Email is required";
if (empty($phone_number)) $errors[] = "Phone number is required";
if (empty($password)) $errors[] = "Password is required";
if (empty($confirm_password)) $errors[] = "Confirm password is required";

// Validate email format
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}

// Validate password length
if (!empty($password) && strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters long";
}

// Validate passwords match
if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
    $errors[] = "Passwords do not match";
}

// Return errors if any
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//                  CHECK IF EMAIL EXISTS
// ═══════════════════════════════════════════════════════════════

$check_stmt = mysqli_prepare($conn, "SELECT user_id FROM USER WHERE email = ?");
if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: failed to prepare statement']);
    exit;
}
mysqli_stmt_bind_param($check_stmt, 's', $email);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) > 0) {
    mysqli_stmt_close($check_stmt);
    echo json_encode(['success' => false, 'message' => 'Email address already registered. Please use a different email or login.']);
    exit;
}
mysqli_stmt_close($check_stmt);

// ═══════════════════════════════════════════════════════════════
//                  INSERT INTO DATABASE
// ═══════════════════════════════════════════════════════════════

// Plain text password (⚠️ insecure — for testing only)
$plain_password = $password;

$insert_stmt = mysqli_prepare($conn, "
    INSERT INTO USER (
        email,
        password,
        full_name,
        phone_number,
        role,
        status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?)
");
if (!$insert_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: failed to prepare insert statement']);
    exit;
}

$role = 'admin';
$status = 'active';
$created_at = date('Y-m-d H:i:s');

mysqli_stmt_bind_param(
    $insert_stmt,
    'sssssss',
    $email,
    $plain_password,
    $full_name,
    $phone_number,
    $role,
    $status,
    $created_at
);

if (mysqli_stmt_execute($insert_stmt)) {
    $user_id = mysqli_insert_id($conn);
    echo json_encode([
        'success' => true,
        'message' => 'Admin account created successfully! Redirecting to login...',
        'user_id' => $user_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . mysqli_stmt_error($insert_stmt)
    ]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>
