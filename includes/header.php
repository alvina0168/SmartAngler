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
$savedCount = 0;
$notificationCount = 0;

if (isLoggedIn()) {
    $currentUser = getCurrentUser($db);
    
    // Get saved tournaments count for anglers
    if (!isAdmin()) {
        $savedCountQuery = "SELECT COUNT(*) as count FROM SAVED 
                           WHERE user_id = ? AND is_saved = 1";
        $savedResult = $db->fetchOne($savedCountQuery, [$_SESSION['user_id']]);
        $savedCount = $savedResult ? $savedResult['count'] : 0;
        
        // Get unread notifications count (last 7 days)
        $notifCountQuery = "SELECT COUNT(*) as count FROM NOTIFICATION 
                           WHERE user_id = ? 
                           AND sent_date > DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $notifResult = $db->fetchOne($notifCountQuery, [$_SESSION['user_id']]);
        $notificationCount = $notifResult ? $notifResult['count'] : 0;
    }
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
    
    <style>
        /* Icon Badges Styling */
        .icon-link {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(109, 148, 197, 0.08);
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .icon-link:hover {
            background: var(--primary-blue);
            color: var(--white);
            transform: translateY(-2px);
        }
        
        .icon-link i {
            font-size: 18px;
        }
        
        .icon-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 20px;
            height: 20px;
            background: #E74C3C;
            color: white;
            font-size: 11px;
            font-weight: 700;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            border: 2px solid white;
            box-shadow: 0 2px 6px rgba(231, 76, 60, 0.4);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .icon-link.saved-icon:hover {
            background: #F39C12;
        }
        
        .icon-link.notif-icon .icon-badge {
            background: #E74C3C;
        }
        
        .icon-link.saved-icon .icon-badge {
            background: #F39C12;
        }
        
        /* Dropdown separator */
        .dropdown-separator {
            height: 1px;
            background: var(--secondary-light);
            margin: 8px 0;
        }
        
        /* Mobile responsive for icons */
        @media (max-width: 992px) {
            .icon-link {
                width: 100%;
                height: auto;
                border-radius: 8px;
                padding: 12px 16px;
                justify-content: flex-start;
                gap: 12px;
                margin-bottom: 8px;
            }
            
            .icon-link i {
                font-size: 16px;
            }
            
            .icon-badge {
                position: static;
                margin-left: auto;
            }
            
            .icon-link span {
                font-weight: 600;
                font-size: 14px;
            }
        }
    </style>
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
                    <a href="<?php echo SITE_URL; ?>/pages/tournament/tournaments.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tournaments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-trophy"></i>
                        <span>Tournaments</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/calendar/calendar.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Calendar</span>
                    </a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <!-- Admin Panel Link -->
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/admin/index.php" class="nav-link">
                                <i class="fas fa-user-shield"></i>
                                <span>Admin Panel</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Saved Tournaments Icon (Anglers Only) -->
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/pages/saved/saved-tournaments.php" 
                               class="icon-link saved-icon" 
                               title="Saved Tournaments">
                                <i class="fas fa-bookmark"></i>
                                <?php if ($savedCount > 0): ?>
                                    <span class="icon-badge"><?php echo $savedCount; ?></span>
                                <?php endif; ?>
                                <span style="display: none;">Saved</span>
                            </a>
                        </li>
                        
                        <!-- Notifications Icon (Anglers Only) -->
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/pages/notification/notifications.php" 
                               class="icon-link notif-icon" 
                               title="Notifications">
                                <i class="fas fa-bell"></i>
                                <?php if ($notificationCount > 0): ?>
                                    <span class="icon-badge"><?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?></span>
                                <?php endif; ?>
                                <span style="display: none;">Notifications</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- User Profile Dropdown -->
                    <li class="nav-item user-dropdown">
                        <div class="user-profile">
                            <div class="user-avatar">
                                <?php if (!empty($currentUser['profile_image']) && file_exists(__DIR__ . '/../assets/images/profiles/' . $currentUser['profile_image'])): ?>
                                    <img src="<?php echo SITE_URL . '/assets/images/profiles/' . $currentUser['profile_image']; ?>" 
                                         alt="Profile" 
                                         style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name'] ?? 'User'); ?></span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </div>
                        <div class="dropdown-menu">
                            <a href="<?php echo SITE_URL; ?>/pages/profile/profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            
                            <?php if (!isAdmin()): ?>
    <!-- Dashboard for Anglers (moved to dropdown) -->
    <a href="<?php echo SITE_URL; ?>/pages/dashboard/myDashboard.php" class="dropdown-item">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    
    <!-- My Reviews -->
    <a href="<?php echo SITE_URL; ?>/pages/review/myReviews.php" class="dropdown-item">
        <i class="fas fa-star"></i> My Reviews
    </a>
<?php endif; ?>
                            <div class="dropdown-separator"></div>
                            
                            <a href="<?php echo SITE_URL; ?>/pages/authentication/logout.php" class="dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <!-- Guest User - Login/Register Buttons -->
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/pages/authentication/login.php" class="nav-btn btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/pages/authentication/register.php" class="nav-btn btn-register">
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
        
        // Show icon labels in mobile menu
        window.addEventListener('resize', () => {
            const iconLabels = document.querySelectorAll('.icon-link span');
            if (window.innerWidth <= 992) {
                iconLabels.forEach(label => label.style.display = 'block');
            } else {
                iconLabels.forEach(label => label.style.display = 'none');
            }
        });
    </script>