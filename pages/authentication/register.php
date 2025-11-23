<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(SITE_URL . '/admin/index.php');
    } else {
        redirect(SITE_URL . '/index.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    $full_name = sanitize($_POST['full_name']);
    $phone_number = sanitize($_POST['phone_number']);
    
    // Validation
    if (empty($email) || empty($password) || empty($full_name) || empty($phone_number)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email already exists
        $check_query = "SELECT * FROM USER WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Email already registered';
        } else {
            // Insert new user
            $insert_query = "INSERT INTO USER (email, password, full_name, phone_number, role, status) 
                            VALUES ('$email', '$password', '$full_name', '$phone_number', 'angler', 'active')";
            
            if (mysqli_query($conn, $insert_query)) {
                redirect(SITE_URL . '/pages/authentication/login.php?registered=1');
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$page_title = 'Register';
include '../../includes/header.php';
?>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="form-container">
        <h2 style="text-align: center; color: #6D94C5; margin-bottom: 30px;">
            <i class="fas fa-user-plus"></i> Register for SmartAngler
        </h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone_number" class="form-control" placeholder="Enter your phone number" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Create a password (min. 6 characters)" required>
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            Already have an account? <a href="login.php" style="color: #6D94C5; font-weight: 600;">Login here</a>
        </p>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>