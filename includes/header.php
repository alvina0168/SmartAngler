<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// Create PDO Database object for pages that need it
$db = new Database();

checkSessionTimeout();

$currentUser = null;
$savedCount = 0;
$notificationCount = 0;

if (isLoggedIn()) {
    $currentUser = getCurrentUser($db);
    
    if (!isAdmin()) {
        $savedCountQuery = "SELECT COUNT(*) as count FROM SAVED 
                           WHERE user_id = ? AND is_saved = 1";
        $savedResult = $db->fetchOne($savedCountQuery, [$_SESSION['user_id']]);
        $savedCount = $savedResult ? $savedResult['count'] : 0;
        
        $notifCountQuery = "SELECT COUNT(*) as count FROM NOTIFICATION 
                           WHERE user_id = ? 
                           AND sent_date > DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $notifResult = $db->fetchOne($notifCountQuery, [$_SESSION['user_id']]);
        $notificationCount = $notifResult ? $notifResult['count'] : 0;
    }
}

$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
        }

        :root {
            --ocean-blue: #0A4D68;
            --ocean-light: #088395;
            --ocean-teal: #05BFDB;
            --sand: #F8F6F0;
            --text-dark: #1A1A1A;
            --text-muted: #6B7280;
            --white: #FFFFFF;
            --border: #E5E7EB;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--sand);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .main-header {
            background: var(--white);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }

        .header-container {
            max-width: 100%;
            padding: 0 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
            font-weight: 800;
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 10px;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .logo-text span {
            color: var(--ocean-light);
        }

        .main-nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 16px 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            border-radius: 10px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link:hover {
            background: var(--sand);
            color: var(--ocean-light);
        }

        .nav-link.active {
            background: linear-gradient(135deg, rgba(8, 131, 149, 0.1) 0%, rgba(5, 191, 219, 0.1) 100%);
            color: var(--ocean-blue);
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -16px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--ocean-light) 0%, var(--ocean-teal) 100%);
            border-radius: 3px 3px 0 0;
        }

        .nav-link i {
            font-size: 16px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--sand);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            color: var(--text-muted);
            text-decoration: none;
        }

        .icon-btn:hover {
            background: #E5E7EB;
            color: var(--ocean-light);
        }

        .icon-btn i {
            font-size: 18px;
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            background: #EF4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: white;
            border: 2px solid var(--white);
            padding: 0 4px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px 6px 6px;
            background: var(--sand);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .user-profile:hover {
            background: #E5E7EB;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
            background: var(--ocean-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-avatar i {
            font-size: 20px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .user-role {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dropdown-icon {
            color: var(--text-muted);
            font-size: 12px;
            margin-left: 4px;
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            min-width: 220px;
            padding: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            z-index: 100;
        }

        .user-profile.active .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background: var(--sand);
            color: var(--ocean-light);
        }

        .dropdown-item i {
            width: 18px;
            color: var(--text-muted);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border);
            margin: 8px 0;
        }

        .dropdown-item.logout {
            color: #EF4444;
        }

        .dropdown-item.logout:hover {
            background: #FEE2E2;
        }

        .login-btn {
            padding: 10px 18px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--ocean-light), var(--ocean-teal));
            color: #fff;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .login-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .mobile-menu-btn {
            display: none;
            width: 40px;
            height: 40px;
            background: var(--sand);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .mobile-menu-btn span {
            width: 20px;
            height: 2px;
            background: var(--text-dark);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .mobile-nav {
            display: none;
            background: var(--white);
            border-top: 1px solid var(--border);
            padding: 16px;
        }

        .mobile-nav.active {
            display: block;
        }

        .mobile-nav .nav-link,
        .mobile-nav .icon-btn {
            width: 100%;
            justify-content: flex-start;
            margin-bottom: 4px;
            padding: 12px 16px;
            border-radius: 8px;
        }

        .mobile-nav .icon-btn {
            display: flex;
        }

        .flash-message-container {
            position: fixed;
            top: 88px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            width: 90%;
            max-width: 500px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }

        .alert-info {
            background: #DBEAFE;
            color: #1E40AF;
            border: 1px solid #93C5FD;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            padding: 4px;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @media (max-width: 1024px) {
            .header-container {
                padding: 0 40px;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                padding: 0 20px;
                height: 64px;
            }

            .main-nav {
                display: none;
            }

            .mobile-menu-btn {
                display: flex;
            }

            .user-info {
                display: none;
            }

            .logo-text {
                font-size: 20px;
            }

            .logo-icon {
                width: 36px;
                height: 36px;
                font-size: 18px;
            }

            .header-actions .icon-btn {
                display: none;
            }

            .header-actions .icon-btn:last-of-type {
                display: flex;
            }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="header-container">
        <a href="<?php echo SITE_URL; ?>/index.php" class="logo-section">
            <div class="logo-icon">
                <?php if (file_exists(__DIR__ . '/../assets/images/logo.png')): ?>
                    <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="SmartAngler">
                <?php else: ?>
                    <i class="fas fa-fish"></i>
                <?php endif; ?>
            </div>
            <div class="logo-text">
                Smart<span>Angler</span>
            </div>
        </a>

        <div class="header-actions">

    <nav class="main-nav">
        <a href="<?php echo SITE_URL; ?>/index.php" 
           class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>

        <a href="<?php echo SITE_URL; ?>/pages/tournament/tournaments.php" 
           class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['tournaments.php','tournament-details.php']) ? 'active' : ''; ?>">
            <i class="fas fa-trophy"></i>
            <span>Tournaments</span>
        </a>

        <a href="<?php echo SITE_URL; ?>/pages/calendar/calendar.php" 
           class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendar</span>
        </a>
    </nav>

    <?php if (isLoggedIn()): ?>

        <!-- Saved Tournaments -->
        <a href="<?php echo SITE_URL; ?>/pages/saved/saved-tournaments.php" class="icon-btn" title="Saved Tournaments">
            <i class="fas fa-bookmark"></i>
            <?php if ($savedCount > 0): ?>
                <span class="notification-badge"><?php echo $savedCount; ?></span>
            <?php endif; ?>
        </a>

        <!-- Notifications -->
        <a href="<?php echo SITE_URL; ?>/pages/notification/notifications.php" class="icon-btn" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($notificationCount > 0): ?>
                <span class="notification-badge"><?php echo min($notificationCount, 99); ?><?php echo $notificationCount > 99 ? '+' : ''; ?></span>
            <?php endif; ?>
        </a>

        <!-- User Profile Dropdown -->
        <div class="user-profile" onclick="toggleDropdown(event)">
            <div class="user-avatar">
                <?php if (!empty($currentUser['profile_image']) && file_exists(__DIR__ . '/../assets/images/profiles/' . $currentUser['profile_image'])): ?>
                    <img src="<?php echo SITE_URL . '/assets/images/profiles/' . $currentUser['profile_image']; ?>" alt="Profile">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($currentUser['full_name'] ?? 'User'); ?></div>
                <div class="user-role">Angler</div>
            </div>
            <i class="fas fa-chevron-down dropdown-icon"></i>

            <div class="dropdown-menu" onclick="event.stopPropagation()">
                <a href="<?php echo SITE_URL; ?>/pages/profile/profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/dashboard/myDashboard.php" class="dropdown-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?php echo SITE_URL; ?>/pages/review/myReviews.php" class="dropdown-item">
                    <i class="fas fa-star"></i> My Reviews
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo SITE_URL; ?>/pages/authentication/logout.php" class="dropdown-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

    <?php else: ?>

        <!-- Login Button -->
        <a href="<?php echo SITE_URL; ?>/pages/authentication/login.php" class="login-btn">
            <i class="fas fa-sign-in-alt"></i> Login
        </a>

    <?php endif; ?>

    <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
        <span></span>
        <span></span>
        <span></span>
    </button>
</div>

    </div>

    <!-- Mobile Navigation -->
    <div class="mobile-nav" id="mobileNav">
        <a href="<?php echo SITE_URL; ?>/index.php" class="nav-link">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/pages/tournament/tournaments.php" class="nav-link">
            <i class="fas fa-trophy"></i>
            <span>Tournaments</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/pages/calendar/calendar.php" class="nav-link">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendar</span>
        </a>
        <div class="dropdown-divider" style="margin: 12px 0;"></div>
        <a href="<?php echo SITE_URL; ?>/pages/saved/saved-tournaments.php" class="icon-btn">
            <i class="fas fa-bookmark"></i>
            <span>Saved Tournaments</span>
            <?php if ($savedCount > 0): ?>
                <span class="notification-badge"><?php echo $savedCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo SITE_URL; ?>/pages/notification/notifications.php" class="icon-btn">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
            <?php if ($notificationCount > 0): ?>
                <span class="notification-badge"><?php echo min($notificationCount, 99); ?></span>
            <?php endif; ?>
        </a>
        <div class="dropdown-divider" style="margin: 12px 0;"></div>
        <a href="<?php echo SITE_URL; ?>/pages/profile/profile.php" class="nav-link">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/pages/review/myReviews.php" class="nav-link">
            <i class="fas fa-star"></i>
            <span>My Reviews</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/pages/authentication/logout.php" class="nav-link" style="color: #EF4444;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</header>

<?php
$flash = getFlashMessage();
if ($flash):
?>
<div class="flash-message-container">
    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
        <i class="fas fa-<?php echo $flash['type'] == 'success' ? 'check-circle' : ($flash['type'] == 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
        <span><?php echo htmlspecialchars($flash['message']); ?></span>
        <button class="alert-close" onclick="this.parentElement.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
<?php endif; ?>

<script>
function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobileNav');
    mobileNav.classList.toggle('active');
}

function toggleDropdown(event) {
    event.stopPropagation();
    const userProfile = event.currentTarget;
    userProfile.classList.toggle('active');
}

document.addEventListener('click', function(event) {
    const userProfile = document.querySelector('.user-profile');
    if (userProfile && !userProfile.contains(event.target)) {
        userProfile.classList.remove('active');
    }
});

document.addEventListener('click', function(event) {
    const mobileNav = document.getElementById('mobileNav');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    if (mobileNav && !mobileNav.contains(event.target) && !menuBtn.contains(event.target)) {
        mobileNav.classList.remove('active');
    }
});
</script>