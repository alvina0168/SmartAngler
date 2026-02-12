<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdminAccess();

$logged_in_user_id = intval($_SESSION['user_id']);

$query = "
    SELECT n.*, u.full_name AS user_name, t.tournament_title 
    FROM NOTIFICATION n
    LEFT JOIN USER u ON n.user_id = u.user_id
    LEFT JOIN TOURNAMENT t ON n.tournament_id = t.tournament_id
    WHERE (t.created_by = '$logged_in_user_id' OR t.tournament_id IS NULL)
    ORDER BY n.sent_date DESC
";
$result = mysqli_query($conn, $query);

$page_title = "My Notifications & Announcements";
include '../includes/header.php';
?>

<style>
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
    background: white;
    border-radius: 15px;
    margin-top: 20px;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #ccc;
}

.empty-state h3 {
    color: #435334;
    font-size: 24px;
    margin-bottom: 10px;
}

.empty-state p {
    color: #999;
    font-size: 15px;
}
</style>

<div class="text-right mb-3">
    <a href="createNotification.php" class="create-btn">
        <i class="fas fa-plus-circle"></i> Create Notification
    </a>
</div>

<?php if (mysqli_num_rows($result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-bell"></i> My Notifications (<?php echo mysqli_num_rows($result); ?>)
            </h2>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Tournament</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Date Sent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($notif = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($notif['user_name'] ?? 'System'); ?>
                        </td>
                        <td>
                            <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($notif['tournament_title'] ?? '-'); ?>
                        </td>
                        <td><?php echo htmlspecialchars($notif['title']); ?></td>
                        <td><?php echo htmlspecialchars(substr($notif['message'], 0, 50)) . (strlen($notif['message']) > 50 ? '...' : ''); ?></td>
                        <td><?php echo date('d M Y, h:i A', strtotime($notif['sent_date'])); ?></td>
                        <td>
                            <div class="action-btns">
                                <!-- Delete Button -->
                                <a href="deleteNotification.php?id=<?php echo $notif['notification_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this notification?');">
                                   <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-bell"></i>
        <h3>No Notifications Yet</h3>
        <p>Create a notification or announcement to notify users about your tournaments.</p>
    </div>
<?php endif; ?>

<script>
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = 0;
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>

<?php include '../includes/footer.php'; ?>