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
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $query = "SELECT * FROM USER WHERE email = '$email' AND password = '$password' AND status = 'active'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] == 'admin') {
                redirect(SITE_URL . '/admin/index.php');
            } else {
                redirect(SITE_URL . '/index.php');
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$page_title = 'Login';
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

/* Login Page Styles */
.login-page {
    min-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    padding: 60px 20px;
}

.login-card {
    background: var(--white);
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    width: 100%;
    max-width: 400px;
    text-align: center;
}

.login-card h2 {
    font-size: 28px;
    font-weight: 800;
    color: var(--ocean-blue);
    margin-bottom: 20px;
}

.login-card .alert {
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

.forgot-password {
    text-align: right;
    margin-bottom: 20px;
}

.forgot-password a {
    color: var(--ocean-light);
    font-weight: 600;
    text-decoration: none;
}

.forgot-password a:hover {
    color: var(--ocean-teal);
}

.btn-login {
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

.btn-login:hover {
    background: var(--ocean-blue);
}

.register-link {
    margin-top: 20px;
    font-size: 14px;
    color: var(--text-muted);
}

.register-link a {
    color: var(--ocean-light);
    font-weight: 600;
    text-decoration: none;
}

.register-link a:hover {
    color: var(--ocean-teal);
}

/* Responsive */
@media (max-width: 480px) {
    .login-card {
        padding: 30px 20px;
    }

    .login-card h2 {
        font-size: 24px;
    }
}
</style>

<div class="login-page">
    <div class="login-card">
        <h2><i class="fas fa-sign-in-alt"></i> Login to SmartAngler</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Registration successful! Please login.</div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="forgot-password">
                <a href="../profile/forgot-password.php">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <p class="register-link">
            Don't have an account? 
            <a href="register.php">Register here</a>
        </p>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
