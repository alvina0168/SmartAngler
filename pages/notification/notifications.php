<?php
ob_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn() || isAdmin()) {
    setFlashMessage('Please log in to view notifications', 'error');
    redirect(SITE_URL . '/pages/authentication/login.php');
}

$pageTitle = 'Notifications';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];

// Handle actions (mark as read, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
        
        if ($action === 'mark_read' && $notification_id > 0) {
            $query = "UPDATE NOTIFICATION SET read_status = 1 WHERE notification_id = ? AND user_id = ?";
            $db->execute($query, [$notification_id, $user_id]);
            setFlashMessage('Notification marked as read', 'success');
        } elseif ($action === 'mark_unread' && $notification_id > 0) {
            $query = "UPDATE NOTIFICATION SET read_status = 0 WHERE notification_id = ? AND user_id = ?";
            $db->execute($query, [$notification_id, $user_id]);
            setFlashMessage('Notification marked as unread', 'success');
        } elseif ($action === 'delete' && $notification_id > 0) {
            $query = "DELETE FROM NOTIFICATION WHERE notification_id = ? AND user_id = ?";
            $db->execute($query, [$notification_id, $user_id]);
            setFlashMessage('Notification deleted', 'success');
        } elseif ($action === 'mark_all_read') {
            $query = "UPDATE NOTIFICATION SET read_status = 1 WHERE user_id = ? AND read_status = 0";
            $db->execute($query, [$user_id]);
            setFlashMessage('All notifications marked as read', 'success');
        } elseif ($action === 'delete_all_read') {
            $query = "DELETE FROM NOTIFICATION WHERE user_id = ? AND read_status = 1";
            $db->execute($query, [$user_id]);
            setFlashMessage('All read notifications deleted', 'success');
        }
        redirect(SITE_URL . '/pages/notification/notifications.php');
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$baseQuery = "SELECT n.*, t.tournament_title 
              FROM NOTIFICATION n
              LEFT JOIN TOURNAMENT t ON n.tournament_id = t.tournament_id
              WHERE n.user_id = ?";

$params = [$user_id];

if ($filter === 'unread') {
    $baseQuery .= " AND n.read_status = 0";
} elseif ($filter === 'read') {
    $baseQuery .= " AND n.read_status = 1";
}

$baseQuery .= " ORDER BY n.sent_date DESC";

// Get notifications
$notifications = $db->fetchAll($baseQuery, $params);

// Count notifications by status
$countQuery = "SELECT 
                SUM(CASE WHEN read_status = 0 THEN 1 ELSE 0 END) as unread_count,
                SUM(CASE WHEN read_status = 1 THEN 1 ELSE 0 END) as read_count,
                COUNT(*) as total_count
               FROM NOTIFICATION 
               WHERE user_id = ?";
$counts = $db->fetchOne($countQuery, [$user_id]);

$unread_count = $counts['unread_count'] ?? 0;
$read_count = $counts['read_count'] ?? 0;
$total_count = $counts['total_count'] ?? 0;

// Function to get relative time
function getRelativeTime($datetime) {
    $now = time();
    $time = strtotime($datetime);
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

// Function to get notification icon based on content
function getNotificationIcon($title, $message) {
    $content = strtolower($title . ' ' . $message);
    
    if (strpos($content, 'approved') !== false || strpos($content, 'accepted') !== false) {
        return ['icon' => 'fa-check-circle', 'color' => 'success'];
    } elseif (strpos($content, 'rejected') !== false || strpos($content, 'declined') !== false) {
        return ['icon' => 'fa-times-circle', 'color' => 'danger'];
    } elseif (strpos($content, 'pending') !== false || strpos($content, 'waiting') !== false) {
        return ['icon' => 'fa-clock', 'color' => 'warning'];
    } elseif (strpos($content, 'winner') !== false || strpos($content, 'prize') !== false) {
        return ['icon' => 'fa-trophy', 'color' => 'gold'];
    } elseif (strpos($content, 'cancelled') !== false) {
        return ['icon' => 'fa-ban', 'color' => 'danger'];
    } elseif (strpos($content, 'update') !== false || strpos($content, 'change') !== false) {
        return ['icon' => 'fa-info-circle', 'color' => 'info'];
    } else {
        return ['icon' => 'fa-bell', 'color' => 'primary'];
    }
}
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">


<div class="notifications-container">
    <!-- Header -->
    <div class="notifications-header">
        <h1><i class="fas fa-bell"></i> Notifications</h1>
        <p>Stay updated with your tournament activities</p>
    </div>

    <!-- Controls: Filters and Actions -->
    <div class="notifications-controls">
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i>
                <span>All</span>
                <span class="badge"><?php echo $total_count; ?></span>
            </a>
            <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>Unread</span>
                <span class="badge"><?php echo $unread_count; ?></span>
            </a>
            <a href="?filter=read" class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">
                <i class="fas fa-envelope-open"></i>
                <span>Read</span>
                <span class="badge"><?php echo $read_count; ?></span>
            </a>
        </div>

        <div class="notifications-actions">
            <?php if ($unread_count > 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="action-btn" onclick="return confirm('Mark all notifications as read?')">
                        <i class="fas fa-check-double"></i>
                        <span>Mark All Read</span>
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if ($read_count > 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_all_read">
                    <button type="submit" class="action-btn" onclick="return confirm('Delete all read notifications?')">
                        <i class="fas fa-trash-alt"></i>
                        <span>Clear Read</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3>No Notifications</h3>
                <p>
                    <?php if ($filter === 'unread'): ?>
                        You're all caught up! No unread notifications.
                    <?php elseif ($filter === 'read'): ?>
                        No read notifications yet.
                    <?php else: ?>
                        You don't have any notifications yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <?php 
                $icon_info = getNotificationIcon($notif['title'], $notif['message']);
                $is_unread = $notif['read_status'] == 0;
                ?>
                <div class="notification-card <?php echo $is_unread ? 'unread' : ''; ?>">
                    <?php if ($is_unread): ?>
                        <span class="unread-badge">New</span>
                    <?php endif; ?>
                    
                    <div class="notification-content">
                        <div class="notification-icon <?php echo $icon_info['color']; ?>">
                            <i class="fas <?php echo $icon_info['icon']; ?>"></i>
                        </div>
                        
                        <div class="notification-body">
                            <div class="notification-header">
                                <h3 class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></h3>
                                <span class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo getRelativeTime($notif['sent_date']); ?>
                                </span>
                            </div>
                            
                            <p class="notification-message"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                            
                            <?php if (!empty($notif['tournament_title'])): ?>
                                <div class="notification-tournament">
                                    <i class="fas fa-trophy"></i>
                                    <span><?php echo htmlspecialchars($notif['tournament_title']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="notification-actions">
                                <?php if ($is_unread): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                        <button type="submit" class="notif-action-btn read-btn">
                                            <i class="fas fa-check"></i>
                                            <span>Mark as Read</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="mark_unread">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                        <button type="submit" class="notif-action-btn read-btn">
                                            <i class="fas fa-envelope"></i>
                                            <span>Mark as Unread</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" class="notif-action-btn delete-btn" onclick="return confirm('Delete this notification?')">
                                        <i class="fas fa-trash-alt"></i>
                                        <span>Delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
ob_end_flush();
require_once '../../includes/footer.php';
?>
