<?php
$page_title = 'Participant Management';
require_once '../includes/header.php';

// Get all participants with their registration info
$query = "SELECT u.user_id, u.full_name, u.email, u.phone_number, u.ic_number,
                 COUNT(DISTINCT tr.tournament_id) as tournaments_joined,
                 COUNT(DISTINCT fc.catch_id) as total_catches,
                 u.created_at
          FROM USER u
          LEFT JOIN TOURNAMENT_REGISTRATION tr ON u.user_id = tr.user_id
          LEFT JOIN FISH_CATCH fc ON u.user_id = fc.user_id
          WHERE u.role = 'user'
          GROUP BY u.user_id
          ORDER BY u.created_at DESC";

$participants = mysqli_query($conn, $query);
?>

<div class="page-header">
    <h1><i class="fas fa-users"></i> Participant Management</h1>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php 
        echo $_SESSION['success_message']; 
        unset($_SESSION['success_message']);
        ?>
    </div>
<?php endif; ?>

<?php if (mysqli_num_rows($participants) > 0): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>IC Number</th>
                    <th>Tournaments Joined</th>
                    <th>Total Catches</th>
                    <th>Registered On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($participant = mysqli_fetch_assoc($participants)): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($participant['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($participant['email']); ?></td>
                        <td><?php echo htmlspecialchars($participant['phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($participant['ic_number']); ?></td>
                        <td><?php echo $participant['tournaments_joined']; ?></td>
                        <td><?php echo $participant['total_catches']; ?></td>
                        <td><?php echo date('d M Y', strtotime($participant['created_at'])); ?></td>
                        <td>
                            <a href="viewParticipant.php?id=<?php echo $participant['user_id']; ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body text-center" style="padding: 60px 20px;">
            <i class="fas fa-users" style="font-size: 64px; color: #CBD5E0; margin-bottom: 20px;"></i>
            <h3 style="color: var(--admin-text); margin-bottom: 10px;">No Participants Yet</h3>
            <p style="color: var(--admin-text-light);">Participants will appear here once they register.</p>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>