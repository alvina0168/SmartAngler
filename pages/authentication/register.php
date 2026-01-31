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

<style>
:root {
    --ocean-blue: #0A4D68;
    --ocean-light: #088395;
    --ocean-teal: #05BFDB;
    --sand: #F8F6F0;
    --text-dark: #1A1A1A;
    --text-muted: #6B7280;
    --white: #FFFFFF;
}

/* Register Page Styles */
.register-page {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    padding: 60px 20px;
}

.register-card {
    background: var(--white);
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    width: 100%;
    max-width: 450px;
    text-align: center;
}

.register-card h2 {
    font-size: 28px;
    font-weight: 800;
    color: var(--ocean-blue);
    margin-bottom: 20px;
}

.register-card .alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 16px;
    font-size: 14px;
}

.alert-error {
    background: #FEE2E2;
    color: #B91C1C;
}

.alert-success {
    background: #D1FAE5;
    color: #065F46;
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: var(--text-dark);
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

.btn-register {
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

.btn-register:hover {
    background: var(--ocean-blue);
}

.login-link {
    margin-top: 20px;
    font-size: 14px;
    color: var(--text-muted);
}

.login-link a {
    color: var(--ocean-light);
    font-weight: 600;
    text-decoration: none;
}

.login-link a:hover {
    color: var(--ocean-teal);
}

/* Responsive */
@media (max-width: 480px) {
    .register-card {
        padding: 30px 20px;
    }

    .register-card h2 {
        font-size: 24px;
    }
}
</style>

<div class="register-page">
    <div class="register-card">
        <h2>Register for SmartAngler</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" placeholder="Enter your full name" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone_number" placeholder="Enter your phone number" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a password (min. 6 characters)" required>
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm your password" required>
            </div>
            
            <button type="submit" class="btn-register">Register</button>
        </form>

        <p class="login-link">
            Already have an account? 
            <a href="login.php">Login here</a>
        </p>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
