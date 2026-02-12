<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

requireOrganizer();

$organizer_id = $_SESSION['user_id'];
$query = "SELECT user_id, full_name, username, phone_number, status, created_at 
          FROM USER 
          WHERE role = 'admin' AND created_by = '$organizer_id' 
          ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

$page_title = "Manage Admins";
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

.username-badge {
    font-family: monospace;
    background: #F3F4F6;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
    color: #088395;
}

.badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-active {
    background: #ECFDF5;
    color: #065F46;
}

.badge-inactive {
    background: #FEE2E2;
    color: #991B1B;
}
</style>

<!-- Create Admin Button -->
<div class="text-right mb-3">
    <a href="add-admin.php" class="create-btn">
        <i class="fas fa-plus-circle"></i> Add New Admin
    </a>
</div>

<?php if (mysqli_num_rows($result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-users-cog"></i> Admin List (<?php echo mysqli_num_rows($result); ?>)
            </h2>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Phone Number</th>
                    <th>Status</th>
                    <th>Created Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($admin = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($admin['full_name']); ?>
                        </td>
                        <td>
                                <?php echo htmlspecialchars($admin['username']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($admin['phone_number'] ?: '-'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $admin['status']; ?>">
                                <?php echo ucfirst($admin['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y, h:i A', strtotime($admin['created_at'])); ?></td>
                        <td>
                            <div class="action-btns">
                                <!-- Edit Button -->
                                <a href="edit-admin.php?id=<?php echo $admin['user_id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                   <i class="fas fa-edit"></i>
                                </a>
                                <!-- Delete Button -->
                                <a href="delete-admin.php?id=<?php echo $admin['user_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this admin?');">
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
        <i class="fas fa-users-cog"></i>
        <h3>No Admins Yet</h3>
        <p>You haven't created any admin accounts yet.</p>
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