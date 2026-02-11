<?php
ob_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    setFlashMessage('Please log in to view notifications', 'error');
    redirect(SITE_URL . '/pages/authentication/login.php');
}

$pageTitle = 'Notifications';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];

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

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
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
$notifications = $db->fetchAll($baseQuery, $params);
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

<style>
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

.notifications-hero {
    background: linear-gradient(135deg, var(--ocean-blue) 0%, var(--ocean-light) 100%);
    padding: 60px 0 100px;
    position: relative;
}

.hero-content {
    max-width: 100%;
    padding: 0 60px;
}

.hero-title {
    font-size: 48px;
    font-weight: 800;
    color: var(--white);
    margin: 0 0 12px;
}

.hero-subtitle {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}

.filter-section {
    max-width: 100%;
    margin: -50px 60px 0;
    position: relative;
    z-index: 10;
}

.filter-card {
    background: var(--white);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 10px;
    background: #F3F4F6;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-tab:hover {
    background: #E5E7EB;
}

.filter-tab.active {
    background: var(--ocean-light);
    color: var(--white);
}

.filter-tab .badge {
    background: rgba(0, 0, 0, 0.1);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
}

.filter-tab.active .badge {
    background: rgba(255, 255, 255, 0.2);
}

.notifications-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 10px 16px;
    background: var(--white);
    border: 2px solid var(--border);
    border-radius: 10px;
    color: var(--text-dark);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: var(--sand);
    border-color: var(--ocean-light);
    color: var(--ocean-light);
}

.notifications-page {
    background: var(--white);
    padding: 40px 0 60px;
}

.notifications-container {
    max-width: 100%;
    padding: 0 60px;
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.notification-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.2s ease;
    position: relative;
}

.notification-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.notification-card.unread {
    background: linear-gradient(135deg, rgba(8, 131, 149, 0.05) 0%, rgba(5, 191, 219, 0.05) 100%);
    border-color: var(--ocean-light);
    border-width: 2px;
}

.unread-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: var(--ocean-light);
    color: var(--white);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.notification-content {
    display: flex;
    gap: 16px;
}

.notification-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-icon i {
    font-size: 24px;
}

.notification-icon.success {
    background: #ECFDF5;
    color: #10B981;
}

.notification-icon.danger {
    background: #FEE2E2;
    color: #EF4444;
}

.notification-icon.warning {
    background: #FEF3C7;
    color: #F59E0B;
}

.notification-icon.gold {
    background: #FEF3C7;
    color: #D97706;
}

.notification-icon.info {
    background: #DBEAFE;
    color: var(--ocean-light);
}

.notification-icon.primary {
    background: linear-gradient(135deg, rgba(8, 131, 149, 0.1) 0%, rgba(5, 191, 219, 0.1) 100%);
    color: var(--ocean-light);
}

.notification-body {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
    gap: 16px;
}

.notification-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.notification-time {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
}

.notification-message {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0 0 12px;
}

.notification-tournament {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--sand);
    border-radius: 8px;
    font-size: 13px;
    color: var(--ocean-blue);
    font-weight: 600;
    margin-bottom: 12px;
    width: fit-content;
}

.notification-tournament i {
    color: var(--ocean-light);
}

.notification-actions {
    display: flex;
    gap: 8px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

.notif-action-btn {
    padding: 8px 16px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-dark);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.notif-action-btn:hover {
    background: var(--sand);
}

.notif-action-btn.read-btn:hover {
    border-color: var(--ocean-light);
    color: var(--ocean-light);
}

.notif-action-btn.delete-btn:hover {
    border-color: #EF4444;
    color: #EF4444;
    background: #FEE2E2;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 32px;
    background: linear-gradient(135deg, var(--sand) 0%, #E5E7EB 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state-icon i {
    font-size: 56px;
    color: var(--text-muted);
}

.empty-state h3 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 12px;
}

.empty-state p {
    font-size: 16px;
    color: var(--text-muted);
    margin: 0;
}

@media (max-width: 1400px) {
    .notifications-container,
    .filter-section,
    .hero-content {
        padding-left: 40px;
        padding-right: 40px;
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 36px;
    }
    
    .notifications-container,
    .filter-section,
    .hero-content {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .filter-card {
        flex-direction: column;
        align-items: stretch;
    }
    
    .notifications-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .notification-content {
        flex-direction: column;
    }
    
    .notification-header {
        flex-direction: column;
        gap: 8px;
    }
    
    .notification-actions {
        flex-direction: column;
    }
    
    .notif-action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .unread-badge {
        top: 12px;
        right: 12px;
    }
}
</style>

<div class="notifications-hero">
    <div class="hero-content">
        <h1 class="hero-title">Notifications</h1>
        <p class="hero-subtitle">Stay updated with your tournament activities</p>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-card">
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
</div>

<!-- Notifications Page -->
<div class="notifications-page">
    <div class="notifications-container">
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
</div>

<?php
ob_end_flush();
require_once '../../includes/footer.php';
?>