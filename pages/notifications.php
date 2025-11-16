<?php
ob_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn() || isAdmin()) {
    setFlashMessage('Please log in to view notifications', 'error');
    redirect(SITE_URL . '/pages/login.php');
}

$pageTitle = 'Notifications';
require_once '../includes/header.php';

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
        redirect(SITE_URL . '/pages/notifications.php');
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

<style>
/* Notifications Page Styles */
.notifications-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.notifications-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.notifications-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #6D94C5 0%, #4A7BA7 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
}

.notifications-header p {
    color: #64748b;
    font-size: 1.125rem;
}

/* Filters and Actions Bar */
.notifications-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    background: #f8fafc;
    padding: 0.375rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-tab {
    padding: 0.625rem 1.25rem;
    border: none;
    background: transparent;
    color: #64748b;
    font-weight: 600;
    font-size: 0.9375rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-tab:hover {
    background: #e2e8f0;
    color: #334155;
}

.filter-tab.active {
    background: #6D94C5;
    color: white;
    box-shadow: 0 2px 8px rgba(109, 148, 197, 0.3);
}

.filter-tab .badge {
    background: rgba(255,255,255,0.3);
    padding: 0.125rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8125rem;
    font-weight: 700;
}

.filter-tab.active .badge {
    background: rgba(255,255,255,0.95);
    color: #6D94C5;
}

.notifications-actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.625rem 1rem;
    border: 2px solid #e2e8f0;
    background: white;
    color: #64748b;
    font-weight: 600;
    font-size: 0.875rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.action-btn:hover {
    border-color: #6D94C5;
    color: #6D94C5;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(109, 148, 197, 0.2);
}

.action-btn i {
    font-size: 1rem;
}

/* Notifications List */
.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.notification-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #e2e8f0;
    transition: all 0.3s;
    position: relative;
}

.notification-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.notification-card.unread {
    background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
    border-left-color: #6D94C5;
}

.notification-content {
    display: flex;
    gap: 1rem;
}

.notification-icon {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.notification-icon.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.notification-icon.danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.notification-icon.warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.notification-icon.info {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.notification-icon.primary {
    background: linear-gradient(135deg, #6D94C5 0%, #4A7BA7 100%);
    color: white;
}

.notification-icon.gold {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: white;
}

.notification-body {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
    gap: 1rem;
}

.notification-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.notification-time {
    font-size: 0.875rem;
    color: #94a3b8;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.notification-time i {
    font-size: 0.75rem;
}

.notification-message {
    color: #475569;
    line-height: 1.6;
    margin-bottom: 0.75rem;
}

.notification-tournament {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.875rem;
    background: #f1f5f9;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #475569;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.notification-tournament i {
    color: #6D94C5;
}

.notification-actions {
    display: flex;
    gap: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f1f5f9;
}

.notif-action-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: #f8fafc;
    color: #64748b;
    font-weight: 600;
    font-size: 0.875rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.notif-action-btn:hover {
    background: #e2e8f0;
    color: #334155;
}

.notif-action-btn.read-btn:hover {
    background: #dbeafe;
    color: #2563eb;
}

.notif-action-btn.delete-btn:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* Unread Badge */
.unread-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: #6D94C5;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    font-size: 5rem;
    color: #e2e8f0;
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #94a3b8;
    font-size: 1rem;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .notifications-container {
        padding: 1rem;
    }
    
    .notifications-header h1 {
        font-size: 2rem;
    }
    
    .notifications-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tabs {
        width: 100%;
        justify-content: space-between;
    }
    
    .filter-tab {
        flex: 1;
        justify-content: center;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .filter-tab .badge {
        display: none;
    }
    
    .notifications-actions {
        width: 100%;
    }
    
    .action-btn {
        flex: 1;
        justify-content: center;
        padding: 0.75rem;
    }
    
    .action-btn span {
        display: none;
    }
    
    .notification-content {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .notification-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }
    
    .notification-header {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .notification-actions {
        flex-wrap: wrap;
    }
    
    .notif-action-btn {
        flex: 1;
        min-width: calc(50% - 0.25rem);
        justify-content: center;
    }
    
    .unread-badge {
        position: static;
        display: inline-block;
        margin-bottom: 0.5rem;
    }
}
</style>

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
require_once '../includes/footer.php';
?>
