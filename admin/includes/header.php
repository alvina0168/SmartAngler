<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn() || !hasAdminAccess()) {
    redirect(SITE_URL . '/pages/authentication/login.php?type=admin');
}

$current_user = getUserInfo($_SESSION['user_id']);

if (!$current_user || !is_array($current_user)) {
    $current_user = [
        'full_name' => 'Admin User',
        'email' => $_SESSION['email'] ?? 'admin@smartangler.com',
        'profile_image' => '',
        'role' => $_SESSION['role']
    ];
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Admin Panel' : 'Admin Panel'; ?> - SmartAngler</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin-style.css">
    
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="SmartAngler Logo" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-logo.png'">
            </div>
            <div class="sidebar-title">SmartAngler</div>
            <div class="sidebar-subtitle">
                <?php echo isOrganizer() ? 'Organizer Panel' : 'Admin Panel'; ?>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo SITE_URL; ?>/admin/index.php" class="<?php echo $current_page == 'index.php' && $current_folder == 'admin' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/tournament/tournamentList.php" class="<?php echo $current_folder == 'tournament' ? 'active' : ''; ?>">
                    <i class="fas fa-trophy"></i>
                    <span>Tournament</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/zone/zoneList.php" class="<?php echo $current_folder == 'zone' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Create Fishing Spot</span>
                </a>
            </li>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/notification/notificationList.php" class="<?php echo $current_folder == 'notification' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>

            <?php if (isOrganizer()): ?>
            <li>
                <a href="<?php echo SITE_URL; ?>/admin/revenue/revenue.php" class="<?php echo $current_folder == 'revenue' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Revenue</span>
                </a>
            </li>
            <li>
                <a href="<?php echo SITE_URL; ?>/admin/admin-management/manage-admins.php" class="<?php echo $current_folder == 'admin-management' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Manage Admins</span>
                </a>
            </li>
            <?php endif; ?>

            <li>
                <a href="<?php echo SITE_URL; ?>/admin/profile/profile.php" class="<?php echo $current_folder == 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>

            <li class="logout-btn">
                <a href="<?php echo SITE_URL; ?>/pages/authentication/logout.php" onclick="return confirm('Are you sure you want to logout?')">
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
                        <?php if (!empty($current_user['profile_image']) && file_exists(__DIR__ . '/../../assets/images/profiles/' . $current_user['profile_image'])): ?>
                            <img src="<?php echo SITE_URL . '/assets/images/profiles/' . htmlspecialchars($current_user['profile_image']); ?>" alt="Profile" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($current_user['full_name'] ?? 'A', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($current_user['full_name'] ?? 'Admin User'); ?></div>
                        <div class="user-role"><?php echo getRoleDisplayName($current_user['role']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-container">
            <?php
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

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>