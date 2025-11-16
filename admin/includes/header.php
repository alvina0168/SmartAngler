<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config from root includes
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/pages/login.php');
}

// Get current user info
$current_user = getUserInfo($_SESSION['user_id']);

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Admin Panel' : 'Admin Panel'; ?> - SmartAngler</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin Styles -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin-style.css">
    
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <!-- Logo Section -->
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="SmartAngler Logo" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-logo.png'">
            </div>
            <div class="sidebar-title">SmartAngler</div>
            <div class="sidebar-subtitle">Admin Panel</div>
        </div>

        <!-- Navigation Menu -->
        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo SITE_URL; ?>/admin/index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>./admin/tournament/tournamentList.php" class="<?php echo ($current_page == 'tournamentList.php' || $current_folder == 'tournament') ? 'active' : ''; ?>">
                    <i class="fas fa-trophy"></i>
                    <span>Tournament</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/zone/zoneList.php" class="<?php echo $current_page == 'zoneList.php' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Create Fishing Spot</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/catch/selectTournament.php" class="<?php echo $current_page == 'selectTournament.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fish"></i>
                    <span>Fish Catch Record</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/prize-management.php" class="<?php echo $current_page == 'prize-management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-gift"></i>
                    <span>Prize Management</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/sponsor-management.php" class="<?php echo $current_page == 'sponsor-management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-handshake"></i>
                    <span>Sponsor Management</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/notification/notificationList.php" class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/profile/profile.php" class="<?php echo $current_page == 'my-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>

            <li class="logout-btn">
                <a href="<?php echo SITE_URL; ?>/pages/logout.php" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <h1><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
            </div>
            <div class="top-bar-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php if (!empty($current_user['profile_image']) && file_exists(__DIR__ . '/../../' . $current_user['profile_image'])): ?>
                            <img src="<?php echo SITE_URL . '/' . $current_user['profile_image']; ?>" alt="Profile Image" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>

                </div>
            </div>
        </div>

        <!-- Content Container -->
        <div class="content-container">
            <?php
            // Display flash messages
            if (isset($_SESSION['success'])):
            ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['warning'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span><?php echo $_SESSION['info']; unset($_SESSION['info']); ?></span>
                </div>
            <?php endif; ?>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>