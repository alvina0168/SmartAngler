<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Get tournament ID
$tournament_id = isset($_GET['id']) ? sanitize($_GET['id']) : null;

if (!$tournament_id) {
    redirect(SITE_URL . '/pages/tournaments.php');
}

// Get tournament details
$tournament_query = "SELECT * FROM TOURNAMENT WHERE tournament_id = '$tournament_id'";
$tournament_result = mysqli_query($conn, $tournament_query);

if (mysqli_num_rows($tournament_result) == 0) {
    redirect(SITE_URL . '/pages/tournaments.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Get category filter
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : 'all';

$page_title = 'Leaderboard - ' . $tournament['tournament_title'];
include '../../includes/header.php';
?>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="container">
        <h1 style="text-align: center; color: #6D94C5; margin-bottom: 20px;">
            <i class="fas fa-trophy"></i> Live Leaderboard
        </h1>
        
        <h2 style="text-align: center; color: #666; margin-bottom: 40px;">
            <?php echo htmlspecialchars($tournament['tournament_title']); ?>
        </h2>
        
        <!-- Category Filters -->
        <div style="text-align: center; margin-bottom: 30px;">
            <a href="?id=<?php echo $tournament_id; ?>&category=all" 
               class="btn <?php echo $category_filter == 'all' ? 'btn-primary' : 'btn-secondary'; ?>" 
               style="margin: 5px;">
                All Results
            </a>
            
            <?php
            $categories_query = "SELECT * FROM CATEGORY";
            $categories_result = mysqli_query($conn, $categories_query);
            while ($cat = mysqli_fetch_assoc($categories_result)):
            ?>
                <a href="?id=<?php echo $tournament_id; ?>&category=<?php echo $cat['category_id']; ?>" 
                   class="btn <?php echo $category_filter == $cat['category_id'] ? 'btn-primary' : 'btn-secondary'; ?>" 
                   style="margin: 5px;">
                    <?php echo htmlspecialchars($cat['category_name']); ?>
                </a>
            <?php endwhile; ?>
        </div>
        
        <!-- Leaderboard Table -->
        <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 3px 15px rgba(0,0,0,0.1);">
            <?php
            if ($category_filter == 'all') {
                // Show all catches sorted by weight
                $leaderboard_query = "SELECT fc.*, u.full_name, 
                                      SUM(fc.fish_weight) as total_weight,
                                      COUNT(fc.catch_id) as total_catches
                                      FROM FISH_CATCH fc 
                                      JOIN USER u ON fc.user_id = u.user_id 
                                      WHERE fc.tournament_id = '$tournament_id' 
                                      GROUP BY fc.user_id 
                                      ORDER BY total_weight DESC";
            } else {
                // Show results for specific category
                $leaderboard_query = "SELECT r.*, u.full_name, fc.fish_weight, fc.fish_species, c.category_name 
                                      FROM RESULT r 
                                      JOIN USER u ON r.user_id = u.user_id 
                                      LEFT JOIN FISH_CATCH fc ON r.catch_id = fc.catch_id 
                                      JOIN CATEGORY c ON r.category_id = c.category_id 
                                      WHERE r.tournament_id = '$tournament_id' 
                                      AND r.category_id = '$category_filter' 
                                      ORDER BY r.ranking_position ASC";
            }
            
            $leaderboard_result = mysqli_query($conn, $leaderboard_query);
            
            if (mysqli_num_rows($leaderboard_result) > 0):
            ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Angler</th>
                            <?php if ($category_filter == 'all'): ?>
                                <th>Total Catches</th>
                                <th>Total Weight (kg)</th>
                            <?php else: ?>
                                <th>Category</th>
                                <th>Fish Species</th>
                                <th>Weight (kg)</th>
                            <?php endif; ?>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while ($entry = mysqli_fetch_assoc($leaderboard_result)): 
                        ?>
                        <tr style="<?php echo $rank <= 3 ? 'background-color: #CBDCEB;' : ''; ?>">
                            <td>
                                <strong style="font-size: 20px; color: <?php 
                                    echo $rank == 1 ? '#FFD700' : ($rank == 2 ? '#C0C0C0' : ($rank == 3 ? '#CD7F32' : '#6D94C5')); 
                                ?>">
                                    <?php if ($rank <= 3): ?>
                                        <i class="fas fa-medal"></i>
                                    <?php endif; ?>
                                    #<?php echo $rank; ?>
                                </strong>
                            </td>
                            <td><strong><?php echo htmlspecialchars($entry['full_name']); ?></strong></td>
                            
                            <?php if ($category_filter == 'all'): ?>
                                <td><?php echo $entry['total_catches']; ?></td>
                                <td><?php echo number_format($entry['total_weight'], 2); ?> kg</td>
                            <?php else: ?>
                                <td><?php echo htmlspecialchars($entry['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($entry['fish_species']); ?></td>
                                <td><?php echo number_format($entry['fish_weight'], 2); ?> kg</td>
                            <?php endif; ?>
                            
                            <td>
                                <?php if (isset($entry['result_status'])): ?>
                                    <span class="badge badge-<?php echo $entry['result_status'] == 'final' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($entry['result_status']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-info">LIVE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                        $rank++;
                        endwhile; 
                        ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-info-circle" style="font-size: 48px; color: #6D94C5; display: block; margin-bottom: 20px;"></i>
                    No results available yet for this category.
                </p>
            <?php endif; ?>
        </div>
        
        <?php if ($tournament['status'] == 'ongoing'): ?>
            <div style="text-align: center; margin-top: 20px; color: #666;">
                <i class="fas fa-sync-alt"></i> Live updates - Rankings may change as tournament progresses
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>