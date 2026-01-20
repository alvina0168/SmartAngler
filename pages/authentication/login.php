<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if (hasAdminAccess()) {
        redirect(SITE_URL . '/admin/index.php');
    } else {
        redirect(SITE_URL . '/index.php');
    }
}

$error = '';
$success = '';
$login_type = isset($_GET['type']) ? $_GET['type'] : 'user'; // 'user' or 'admin'

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_input = sanitize($_POST['login_input']); // Can be email OR username
    $password = sanitize($_POST['password']);
    $login_as = sanitize($_POST['login_as']); // 'user' or 'admin'
    
    if (empty($login_input) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Check if login input is email or username
        if (filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
            // It's an email
            $query = "SELECT * FROM USER WHERE email = '$login_input' AND password = '$password' AND status = 'active'";
        } else {
            // It's a username
            $query = "SELECT * FROM USER WHERE username = '$login_input' AND password = '$password' AND status = 'active'";
        }
        
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Check if login type matches user role
            if ($login_as == 'admin') {
                // Admin login - only organizer and admin can login here
                if ($user['role'] == 'organizer' || $user['role'] == 'admin') {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    
                    redirect(SITE_URL . '/admin/index.php');
                } else {
                    $error = 'Invalid credentials for admin login';
                }
            } else {
                // User login - only anglers can login here
                if ($user['role'] == 'angler') {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    
                    redirect(SITE_URL . '/index.php');
                } else {
                    $error = 'Please use admin login for administrative access';
                }
            }
        } else {
            $error = 'Invalid email/username or password';
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
    max-width: 420px;
    text-align: center;
}

.login-card h2 {
    font-size: 28px;
    font-weight: 800;
    color: var(--ocean-blue);
    margin-bottom: 8px;
}

.login-subtitle {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 24px;
}

/* Login Type Tabs */
.login-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    background: var(--sand);
    padding: 6px;
    border-radius: 12px;
}

.login-tab {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    background: transparent;
    border: none;
    font-weight: 600;
    font-size: 14px;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.login-tab.active {
    background: var(--white);
    color: var(--ocean-blue);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.login-tab:hover:not(.active) {
    color: var(--ocean-light);
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
    font-size: 14px;
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
    font-size: 14px;
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
        <p class="login-subtitle">Choose your login type</p>

        <!-- Login Type Tabs -->
        <div class="login-tabs">
            <button class="login-tab <?php echo $login_type == 'user' ? 'active' : ''; ?>" 
                    onclick="switchLoginType('user')">
                <i class="fas fa-user"></i> User Login
            </button>
            <button class="login-tab <?php echo $login_type == 'admin' ? 'active' : ''; ?>" 
                    onclick="switchLoginType('admin')">
                <i class="fas fa-user-shield"></i> Admin Login
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Registration successful! Please login.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="login_as" id="login_as" value="<?php echo $login_type; ?>">
            
            <div class="form-group">
                <label>
                    <i class="fas fa-user"></i> 
                    <?php echo $login_type == 'admin' ? 'Username' : 'Email Address'; ?>
                </label>
                <input type="text" 
                       name="login_input" 
                       placeholder="<?php echo $login_type == 'admin' ? 'Enter your username' : 'Enter your email'; ?>" 
                       required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="forgot-password">
                <a href="../profile/forgot-password.php">
                    <i class="fas fa-key"></i> Forgot Password?
                </a>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <?php if ($login_type == 'user'): ?>
        <p class="register-link">
            Don't have an account? 
            <a href="register.php"><i class="fas fa-user-plus"></i> Register here</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<script>
function switchLoginType(type) {
    window.location.href = 'login.php?type=' + type;
}
</script>

<?php include '../../includes/footer.php'; ?>