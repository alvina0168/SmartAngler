<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check admin login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

// Fetch notifications with user and tournament info
$query = "
    SELECT n.*, u.full_name AS user_name, t.tournament_title 
    FROM NOTIFICATION n
    LEFT JOIN USER u ON n.user_id = u.user_id
    LEFT JOIN TOURNAMENT t ON n.tournament_id = t.tournament_id
    ORDER BY n.sent_date DESC
";
$result = mysqli_query($conn, $query);

$page_title = "Notifications & Announcements";
include '../includes/header.php';
?>

<!-- Create Notification Button -->
<div class="text-right mb-3">
    <a href="createNotification.php" class="create-btn">
        <i class="fas fa-plus-circle"></i> Create Notification
    </a>
</div>

<?php if (mysqli_num_rows($result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-bell"></i> All Notifications (<?php echo mysqli_num_rows($result); ?>)
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
                        <td><?php echo htmlspecialchars($notif['message']); ?></td>
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
        <p>Create a notification or announcement to notify users.</p>
        <a href="createNotification.php" class="create-btn">
            <i class="fas fa-plus"></i> Create Notification
        </a>
    </div>
<?php endif; ?>

<script>
    // Optional: auto-hide alerts after 5 seconds
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
