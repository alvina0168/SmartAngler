<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// Create PDO Database object for pages that need it
$db = new Database();

// Note: $conn is already created in config.php as mysqli connection
// Don't overwrite it! Keep both $conn (mysqli) and $db (PDO)

// Check session timeout
checkSessionTimeout();

// Get current user if logged in
$currentUser = null;
if (isLoggedIn()) {
    $currentUser = getCurrentUser($db);
}

// Get page title
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Modern Navigation Bar -->
    <nav class="navbar">
        <div class="nav-wrapper">
            <!-- Logo Section -->
            <a href="<?php echo SITE_URL; ?>/index.php" class="logo">
                <div class="logo-icon">
                    <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="SmartAngler Logo" class="logo-image">
                </div>
                <div class="logo-text">
                    <span class="logo-smart">Smart</span><span class="logo-angler">Angler</span>
                </div>
            </a>
            
            <!-- Desktop Navigation Menu -->
            <ul class="nav-menu" id="navMenu">
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/index.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/tournaments.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tournaments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-trophy"></i>
                        <span>Tournaments</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/calendar.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Calendar</span>
                    </a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="nav-link">
                                <i class="fas fa-user-shield"></i>
                                <span>Admin Panel</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/user/dashboard.php" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- User Profile Dropdown -->
                    <li class="nav-item user-dropdown">
                        <div class="user-profile">
                            <div class="user-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name'] ?? 'User'); ?></span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </div>
                        <div class="dropdown-menu">
                            <a href="<?php echo SITE_URL; ?>/user/profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/logout.php" class="dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/pages/login.php" class="nav-btn btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/pages/register.php" class="nav-btn btn-register">
                            <i class="fas fa-user-plus"></i>
                            <span>Register</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <!-- Mobile Menu Toggle -->
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Menu">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php
    $flash = getFlashMessage();
    if ($flash):
    ?>
    <div class="flash-message-container">
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
            <i class="fas fa-<?php echo $flash['type'] == 'success' ? 'check-circle' : ($flash['type'] == 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <span><?php echo htmlspecialchars($flash['message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation JavaScript -->
    <script>
        // Navbar scroll effect
        let lastScroll = 0;
        const navbar = document.querySelector('.navbar');
        
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll <= 0) {
                navbar.classList.remove('scroll-up', 'scrolled');
                return;
            }
            
            if (currentScroll > lastScroll && !navbar.classList.contains('scroll-down')) {
                navbar.classList.remove('scroll-up');
                navbar.classList.add('scroll-down', 'scrolled');
            } else if (currentScroll < lastScroll && navbar.classList.contains('scroll-down')) {
                navbar.classList.remove('scroll-down');
                navbar.classList.add('scroll-up', 'scrolled');
            }
            
            lastScroll = currentScroll;
        });
        
        // Mobile menu toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const navMenu = document.getElementById('navMenu');
        
        mobileToggle.addEventListener('click', () => {
            mobileToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.navbar')) {
                mobileToggle.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.classList.remove('menu-open');
            }
        });
        
        // User dropdown toggle
        const userDropdown = document.querySelector('.user-dropdown');
        if (userDropdown) {
            const userProfile = userDropdown.querySelector('.user-profile');
            userProfile.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });
            
            document.addEventListener('click', () => {
                userDropdown.classList.remove('active');
            });
        }
    </script>