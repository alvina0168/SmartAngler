<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/pages/login.php');
}

// Get statistics
$total_tournaments_query = "SELECT COUNT(*) as total FROM TOURNAMENT";
$total_tournaments = mysqli_fetch_assoc(mysqli_query($conn, $total_tournaments_query))['total'];

$total_users_query = "SELECT COUNT(*) as total FROM USER WHERE role = 'angler'";
$total_users = mysqli_fetch_assoc(mysqli_query($conn, $total_users_query))['total'];

$pending_registrations_query = "SELECT COUNT(*) as total FROM TOURNAMENT_REGISTRATION WHERE approval_status = 'pending'";
$pending_registrations = mysqli_fetch_assoc(mysqli_query($conn, $pending_registrations_query))['total'];

$total_catches_query = "SELECT COUNT(*) as total FROM FISH_CATCH";
$total_catches = mysqli_fetch_assoc(mysqli_query($conn, $total_catches_query))['total'];

$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>

<div style="min-height: 70vh; padding: 30px 0; background-color: #F5EFE6;">
    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-user-shield"></i> Admin Dashboard</h1>
            <p>Manage tournaments, participants, and system settings.</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-trophy" style="font-size: 40px; color: #6D94C5; margin-bottom: 10px;"></i>
                <h3><?php echo $total_tournaments; ?></h3>
                <p>Total Tournaments</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-users" style="font-size: 40px; color: #6D94C5; margin-bottom: 10px;"></i>
                <h3><?php echo $total_users; ?></h3>
                <p>Registered Anglers</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-clock" style="font-size: 40px; color: #ff9800; margin-bottom: 10px;"></i>
                <h3><?php echo $pending_registrations; ?></h3>
                <p>Pending Approvals</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-fish" style="font-size: 40px; color: #6D94C5; margin-bottom: 10px;"></i>
                <h3><?php echo $total_catches; ?></h3>
                <p>Total Catches</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div style="background: white; border-radius: 10px; padding: 30px; margin: 30px 0; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #6D94C5; margin-bottom: 20px;"><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="create-tournament.php" class="btn btn-primary" style="text-align: center;">
                    <i class="fas fa-plus"></i> Create Tournament
                </a>
                <a href="manage-tournaments.php" class="btn btn-secondary" style="text-align: center;">
                    <i class="fas fa-list"></i> Manage Tournaments
                </a>
                <a href="manage-registrations.php" class="btn btn-secondary" style="text-align: center;">
                    <i class="fas fa-user-check"></i> Approve Registrations
                </a>
                <a href="manage-spots.php" class="btn btn-secondary" style="text-align: center;">
                    <i class="fas fa-map-marked-alt"></i> Manage Spots
                </a>
                <a href="manage-catches.php" class="btn btn-secondary" style="text-align: center;">
                    <i class="fas fa-fish"></i> View Catches
                </a>
                <a href="manage-results.php" class="btn btn-secondary" style="text-align: center;">
                    <i class="fas fa-chart-line"></i> Manage Results
                </a>
            </div>
        </div>
        
        <!-- Recent Tournaments -->
        <div style="background: white; border-radius: 10px; padding: 30px; margin-bottom: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #6D94C5; margin-bottom: 20px;"><i class="fas fa-trophy"></i> Recent Tournaments</h2>
            
            <?php
            $recent_tournaments_query = "SELECT t.*, 
                                         (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION WHERE tournament_id = t.tournament_id) as registrations 
                                         FROM TOURNAMENT t 
                                         ORDER BY t.created_date DESC 
                                         LIMIT 5";
            $recent_tournaments_result = mysqli_query($conn, $recent_tournaments_query);
            ?>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Tournament</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Registrations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($tournament = mysqli_fetch_assoc($recent_tournaments_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tournament['tournament_title']); ?></td>
                        <td><?php echo formatDate($tournament['tournament_date']); ?></td>
                        <td><span class="badge badge-<?php 
                            echo $tournament['status'] == 'upcoming' ? 'info' : 
                                ($tournament['status'] == 'ongoing' ? 'warning' : 'success'); 
                        ?>"><?php echo strtoupper($tournament['status']); ?></span></td>
                        <td><?php echo $tournament['registrations']; ?>/<?php echo $tournament['max_participants']; ?></td>
                        <td>
                            <a href="edit-tournament.php?id=<?php echo $tournament['tournament_id']; ?>" class="btn btn-secondary" style="padding: 5px 15px; margin-right: 5px;">Edit</a>
                            <a href="../pages/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" class="btn btn-primary" style="padding: 5px 15px;">View</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pending Registrations -->
        <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #6D94C5; margin-bottom: 20px;"><i class="fas fa-user-clock"></i> Pending Registrations</h2>
            
            <?php
            $pending_query = "SELECT tr.*, t.tournament_title, u.full_name 
                             FROM TOURNAMENT_REGISTRATION tr 
                             JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id 
                             JOIN USER u ON tr.user_id = u.user_id 
                             WHERE tr.approval_status = 'pending' 
                             ORDER BY tr.registration_date DESC 
                             LIMIT 5";
            $pending_result = mysqli_query($conn, $pending_query);
            
            if (mysqli_num_rows($pending_result) > 0):
            ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Participant</th>
                            <th>Tournament</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($reg = mysqli_fetch_assoc($pending_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($reg['tournament_title']); ?></td>
                            <td><?php echo formatDate($reg['registration_date']); ?></td>
                            <td>
                                <a href="approve-registration.php?id=<?php echo $reg['registration_id']; ?>&action=approve" class="btn btn-primary" style="padding: 5px 15px; margin-right: 5px;">Approve</a>
                                <a href="approve-registration.php?id=<?php echo $reg['registration_id']; ?>&action=reject" class="btn btn-secondary" style="padding: 5px 15px;">Reject</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pending registrations at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>