<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/admin/index.php');
}

$page_title = "Tournament Results";

$tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;

if ($tournament_id <= 0) {
    $_SESSION['error'] = "Invalid tournament ID.";
    redirect(SITE_URL . '/admin/result/resultList.php');
}

// Get tournament details
$query = "SELECT * FROM TOURNAMENT WHERE tournament_id = '$tournament_id'";
$tournament = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found.";
    redirect(SITE_URL . '/admin/result/resultList.php');
}

// Auto-calculate results function (inline, not from external file)
function autoCalculateResults($conn, $tournament_id) {
    try {
        mysqli_begin_transaction($conn);
        
        mysqli_query($conn, "DELETE FROM RESULT WHERE tournament_id = '$tournament_id'");
        
        $cat_query = "SELECT DISTINCT c.* 
                      FROM CATEGORY c
                      INNER JOIN TOURNAMENT_PRIZE tp ON c.category_id = tp.category_id
                      WHERE tp.tournament_id = '$tournament_id'";
        $categories = mysqli_query($conn, $cat_query);
        
        $total_results = 0;
        
        while ($category = mysqli_fetch_assoc($categories)) {
            $category_id = $category['category_id'];
            $category_type = $category['category_type'] ?? 'heaviest';
            $number_of_ranking = $category['number_of_ranking'];
            $target_weight = $category['target_weight'] ?? null;
            
            $results = [];
            
            switch ($category_type) {
                case 'heaviest':
                    $query = "SELECT 
                                fc.catch_id,
                                fc.user_id,
                                fc.fish_weight,
                                fc.fish_species,
                                fc.catch_time
                              FROM FISH_CATCH fc
                              JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                              WHERE tr.tournament_id = '$tournament_id' 
                              AND tr.approval_status = 'approved'
                              AND fc.catch_id IN (
                                  SELECT fc3.catch_id FROM (
                                      SELECT MAX(fc2.fish_weight) as max_weight, fc2.user_id
                                      FROM FISH_CATCH fc2
                                      JOIN TOURNAMENT_REGISTRATION tr2 ON fc2.registration_id = tr2.registration_id
                                      WHERE tr2.tournament_id = '$tournament_id'
                                      GROUP BY fc2.user_id
                                  ) as max_catches
                                  JOIN FISH_CATCH fc3 ON fc3.fish_weight = max_catches.max_weight 
                                  AND fc3.user_id = max_catches.user_id
                              )
                              ORDER BY fc.fish_weight DESC, fc.catch_time ASC
                              LIMIT $number_of_ranking";
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $results[] = $row;
                        }
                    }
                    break;
                    
                case 'lightest':
                    $query = "SELECT 
                                fc.catch_id,
                                fc.user_id,
                                fc.fish_weight,
                                fc.fish_species,
                                fc.catch_time
                              FROM FISH_CATCH fc
                              JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                              WHERE tr.tournament_id = '$tournament_id' 
                              AND tr.approval_status = 'approved'
                              AND fc.fish_weight > 0
                              AND fc.catch_id IN (
                                  SELECT fc3.catch_id FROM (
                                      SELECT MIN(fc2.fish_weight) as min_weight, fc2.user_id
                                      FROM FISH_CATCH fc2
                                      JOIN TOURNAMENT_REGISTRATION tr2 ON fc2.registration_id = tr2.registration_id
                                      WHERE tr2.tournament_id = '$tournament_id'
                                      AND fc2.fish_weight > 0
                                      GROUP BY fc2.user_id
                                  ) as min_catches
                                  JOIN FISH_CATCH fc3 ON fc3.fish_weight = min_catches.min_weight 
                                  AND fc3.user_id = min_catches.user_id
                              )
                              ORDER BY fc.fish_weight ASC, fc.catch_time ASC
                              LIMIT $number_of_ranking";
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $results[] = $row;
                        }
                    }
                    break;
                    
                case 'most_catches':
                    $query = "SELECT 
                                MAX(fc.catch_id) as catch_id,
                                fc.user_id,
                                COUNT(fc.catch_id) as total_catches,
                                MIN(fc.catch_time) as catch_time
                              FROM FISH_CATCH fc
                              JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                              WHERE tr.tournament_id = '$tournament_id' 
                              AND tr.approval_status = 'approved'
                              GROUP BY fc.user_id
                              ORDER BY total_catches DESC, catch_time ASC
                              LIMIT $number_of_ranking";
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $results[] = $row;
                        }
                    }
                    break;
                    
                case 'exact_weight':
                    if ($target_weight > 0) {
                        $query = "SELECT 
                                    fc.catch_id,
                                    fc.user_id,
                                    fc.fish_weight,
                                    fc.fish_species,
                                    fc.catch_time
                                  FROM FISH_CATCH fc
                                  JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                                  WHERE tr.tournament_id = '$tournament_id' 
                                  AND tr.approval_status = 'approved'
                                  AND fc.fish_weight = $target_weight
                                  ORDER BY fc.catch_time ASC
                                  LIMIT $number_of_ranking";
                        $result = mysqli_query($conn, $query);
                        if ($result) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $results[] = $row;
                            }
                        }
                    }
                    break;
                    
                default:
                    $query = "SELECT 
                                fc.catch_id,
                                fc.user_id,
                                fc.fish_weight,
                                fc.fish_species,
                                fc.catch_time
                              FROM FISH_CATCH fc
                              JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                              WHERE tr.tournament_id = '$tournament_id' 
                              AND tr.approval_status = 'approved'
                              AND fc.catch_id IN (
                                  SELECT fc3.catch_id FROM (
                                      SELECT MAX(fc2.fish_weight) as max_weight, fc2.user_id
                                      FROM FISH_CATCH fc2
                                      JOIN TOURNAMENT_REGISTRATION tr2 ON fc2.registration_id = tr2.registration_id
                                      WHERE tr2.tournament_id = '$tournament_id'
                                      GROUP BY fc2.user_id
                                  ) as max_catches
                                  JOIN FISH_CATCH fc3 ON fc3.fish_weight = max_catches.max_weight 
                                  AND fc3.user_id = max_catches.user_id
                              )
                              ORDER BY fc.fish_weight DESC, fc.catch_time ASC
                              LIMIT $number_of_ranking";
                    $result = mysqli_query($conn, $query);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $results[] = $row;
                        }
                    }
                    break;
            }
            
            $rank = 1;
            foreach ($results as $result_row) {
                $catch_id = $result_row['catch_id'];
                $user_id = $result_row['user_id'];
                $total_fish_count = isset($result_row['total_catches']) ? $result_row['total_catches'] : 0;
                
                $insert_query = "INSERT INTO RESULT 
                               (tournament_id, user_id, catch_id, category_id, ranking_position, total_fish_count, result_status, last_updated)
                               VALUES 
                               ('$tournament_id', '$user_id', " . ($catch_id ? "'$catch_id'" : "NULL") . ", '$category_id', '$rank', '$total_fish_count', 'ongoing', NOW())";
                
                if (mysqli_query($conn, $insert_query)) {
                    $total_results++;
                }
                
                $rank++;
            }
        }
        
        mysqli_commit($conn);
        return true;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

// Auto-calculate on page load
autoCalculateResults($conn, $tournament_id);

// Get all categories with results
$query = "SELECT DISTINCT c.* 
          FROM CATEGORY c
          INNER JOIN RESULT r ON c.category_id = r.category_id
          WHERE r.tournament_id = '$tournament_id'
          ORDER BY c.category_id";
$categories_result = mysqli_query($conn, $query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}

// Get result publication status and last update
$query = "SELECT result_status, MAX(last_updated) as last_updated 
          FROM RESULT 
          WHERE tournament_id = '$tournament_id' 
          GROUP BY result_status
          ORDER BY last_updated DESC
          LIMIT 1";
$result_status_row = mysqli_fetch_assoc(mysqli_query($conn, $query));
$is_published = $result_status_row ? $result_status_row['result_status'] === 'final' : false;
$last_updated = $result_status_row['last_updated'] ?? null;

// Get prizes for this tournament
$query = "SELECT tp.*, c.category_name, s.sponsor_name 
          FROM TOURNAMENT_PRIZE tp
          LEFT JOIN CATEGORY c ON tp.category_id = c.category_id
          LEFT JOIN SPONSOR s ON tp.sponsor_id = s.sponsor_id
          WHERE tp.tournament_id = '$tournament_id'
          ORDER BY c.category_id, tp.prize_ranking";
$prizes_result = mysqli_query($conn, $query);
$prizes = [];
while ($row = mysqli_fetch_assoc($prizes_result)) {
    $prizes[] = $row;
}

include '../includes/header.php';
?>

<style>
.result-medal {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 24px;
}

.medal-1 { background: linear-gradient(135deg, #FFD700, #FFA500); }
.medal-2 { background: linear-gradient(135deg, #C0C0C0, #808080); }
.medal-3 { background: linear-gradient(135deg, #CD7F32, #8B4513); }

.result-row-1 { background: #fff3cd !important; }
.result-row-2 { background: #e2e3e5 !important; }
.result-row-3 { background: #cfe2ff !important; }

.auto-update-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: #e3f2fd;
    border-left: 4px solid #2196F3;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #1565C0;
}
</style>

<!-- Header Section -->
<div class="section">
    <div style="background: linear-gradient(135deg, var(--color-blue-primary), #4A7BA7); color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="color: white; margin: 0 0 0.5rem 0; font-size: 1.75rem; font-weight: 700;">
                    <?= htmlspecialchars($tournament['tournament_title']) ?> - Results
                </h2>
                <p style="margin: 0; opacity: 0.9;">
                    <i class="fas fa-calendar"></i> <?= date('l, d F Y', strtotime($tournament['tournament_date'])) ?>
                    <span style="margin: 0 1rem;">|</span>
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($tournament['location']) ?>
                </p>
            </div>
            <div>
                <?php if ($is_published): ?>
                    <span style="background: #28a745; padding: 0.75rem 1.5rem; border-radius: 50px; font-weight: 600; font-size: 1rem;">
                        <i class="fas fa-check-circle"></i> OFFICIAL RESULTS
                    </span>
                <?php else: ?>
                    <span style="background: #ffc107; color: #000; padding: 0.75rem 1.5rem; border-radius: 50px; font-weight: 600; font-size: 1rem;">
                        <i class="fas fa-sync-alt"></i> LIVE AUTO-UPDATED
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="margin-bottom: 1.5rem; display: flex; gap: 0.75rem; justify-content: space-between; flex-wrap: wrap; align-items: center;">
        <a href="../tournament/viewTournament.php?id=<?= $tournament_id ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Tournament
        </a>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="printResult.php?tournament_id=<?= $tournament_id ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-print"></i> Print Results
            </a>
        </div>
    </div>

    <!-- Auto-Update Info -->
    <div style="margin-bottom: 1.5rem;">
        <div class="auto-update-badge">
            <i class="fas fa-robot"></i>
            <div>
                <strong>Auto-Calculated Rankings</strong>
                <div style="font-size: 0.8125rem; opacity: 0.8; margin-top: 0.25rem;">
                    Rankings update automatically when catches are recorded. 
                    <?php if ($last_updated): ?>
                        Last updated: <?= date('d M Y, h:i A', strtotime($last_updated)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$is_published): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Live Results:</strong> The system automatically calculates and updates rankings based on recorded catches. Rankings are determined dynamically according to category rules. No manual calculation needed - just publish when ready.
        </div>
    <?php endif; ?>
</div>

<?php if (empty($categories)): ?>
    <div class="section">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>No Results Available!</strong> No catches have been recorded yet, or no categories are defined for this tournament.
        </div>
    </div>
<?php else: ?>
    <!-- Results by Category -->
    <?php foreach ($categories as $category): ?>
        <?php
        $query = "SELECT 
                    r.*,
                    u.full_name,
                    u.profile_image,
                    fc.fish_weight,
                    fc.fish_species,
                    fc.catch_time,
                    tr.spot_id,
                    z.zone_name,
                    fs.spot_number
                  FROM RESULT r
                  JOIN USER u ON r.user_id = u.user_id
                  LEFT JOIN FISH_CATCH fc ON r.catch_id = fc.catch_id
                  LEFT JOIN TOURNAMENT_REGISTRATION tr ON r.user_id = tr.user_id AND tr.tournament_id = r.tournament_id
                  LEFT JOIN FISHING_SPOT fs ON tr.spot_id = fs.spot_id
                  LEFT JOIN ZONE z ON fs.zone_id = z.zone_id
                  WHERE r.tournament_id = '$tournament_id' AND r.category_id = '{$category['category_id']}'
                  ORDER BY r.ranking_position ASC";
        $results = mysqli_query($conn, $query);
        ?>

        <div class="section" style="margin-bottom: 2rem;">
            <div style="background: #2c3e50; color: white; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0;">
                <h4 style="margin: 0; color: white; font-size: 1.25rem;">
                    <i class="fas fa-award"></i> <?= htmlspecialchars($category['category_name']) ?>
                </h4>
                <?php if ($category['description']): ?>
                    <small style="opacity: 0.9;"><?= htmlspecialchars($category['description']) ?></small>
                <?php endif; ?>
            </div>
            
            <div style="background: white; border-radius: 0 0 12px 12px; overflow: hidden;">
                <table class="table" style="margin: 0;">
                    <thead style="background: #f8f9fa;">
                        <tr>
                            <th width="80" style="text-align: center;">Rank</th>
                            <th>Angler</th>
                            <?php if (($category['category_type'] ?? 'custom') === 'most_catches'): ?>
                                <th>Total Catches</th>
                            <?php else: ?>
                                <th>Fish Species</th>
                                <th>Weight (kg)</th>
                            <?php endif; ?>
                            <th>Catch Time</th>
                            <th>Zone/Spot</th>
                            <?php if (!empty($prizes)): ?>
                                <th>Prize</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $result_found = false;
                        while ($result = mysqli_fetch_assoc($results)): 
                            $result_found = true;
                            $rank_class = '';
                            $medal = '';
                            if ($result['ranking_position'] == 1) {
                                $rank_class = 'result-row-1';
                                $medal = '<div class="result-medal medal-1">🥇</div>';
                            } elseif ($result['ranking_position'] == 2) {
                                $rank_class = 'result-row-2';
                                $medal = '<div class="result-medal medal-2">🥈</div>';
                            } elseif ($result['ranking_position'] == 3) {
                                $rank_class = 'result-row-3';
                                $medal = '<div class="result-medal medal-3">🥉</div>';
                            }
                            
                            $prize_info = '';
                            foreach ($prizes as $prize) {
                                if ($prize['category_name'] == $category['category_name'] && 
                                    ($prize['prize_ranking'] == $result['ranking_position'] . 'st' || 
                                     $prize['prize_ranking'] == $result['ranking_position'] . 'nd' ||
                                     $prize['prize_ranking'] == $result['ranking_position'] . 'rd' ||
                                     $prize['prize_ranking'] == $result['ranking_position'] . 'th' ||
                                     $prize['prize_ranking'] == 'Top ' . $result['ranking_position'])) {
                                    $prize_info = htmlspecialchars($prize['prize_description']);
                                    if ($prize['prize_value']) {
                                        $prize_info .= ' <strong style="color: #28a745;">(RM ' . number_format($prize['prize_value'], 2) . ')</strong>';
                                    }
                                    if ($prize['sponsor_name']) {
                                        $prize_info .= '<br><small style="color: #6c757d;">Sponsored by: ' . htmlspecialchars($prize['sponsor_name']) . '</small>';
                                    }
                                    break;
                                }
                            }
                        ?>
                            <tr class="<?= $rank_class ?>">
                                <td style="text-align: center;">
                                    <?php if ($medal): ?>
                                        <?= $medal ?>
                                    <?php else: ?>
                                        <strong style="font-size: 1.25rem;">#<?= $result['ranking_position'] ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <?php if ($result['profile_image']): ?>
                                            <img src="../../assets/images/profiles/<?= htmlspecialchars($result['profile_image']) ?>" 
                                                 style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #e9ecef;"
                                                 alt="Profile">
                                        <?php else: ?>
                                            <div style="width: 45px; height: 45px; border-radius: 50%; background: var(--color-blue-primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                                                <?= strtoupper(substr($result['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <strong style="font-size: 1rem;"><?= htmlspecialchars($result['full_name']) ?></strong>
                                    </div>
                                </td>
                                <?php if (($category['category_type'] ?? 'custom') === 'most_catches'): ?>
                                    <td>
                                        <span style="background: #007bff; color: white; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 600; font-size: 1rem;">
                                            <?= $result['total_fish_count'] ?> catches
                                        </span>
                                    </td>
                                <?php else: ?>
                                    <td><?= htmlspecialchars($result['fish_species']) ?></td>
                                    <td>
                                        <strong style="color: var(--color-blue-primary); font-size: 1.125rem;">
                                            <?= number_format($result['fish_weight'], 2) ?> kg
                                        </strong>
                                    </td>
                                <?php endif; ?>
                                <td><?= $result['catch_time'] ? date('H:i:s', strtotime($result['catch_time'])) : '-' ?></td>
                                <td>
                                    <?php 
                                    if ($result['zone_name'] && $result['spot_number']) {
                                        echo htmlspecialchars($result['zone_name']) . ' - Spot #' . $result['spot_number'];
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <?php if (!empty($prizes)): ?>
                                    <td><?= $prize_info ? $prize_info : '-' ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (!$result_found): ?>
                            <tr>
                                <td colspan="<?= !empty($prizes) ? '7' : '6' ?>" style="text-align: center; padding: 2rem; color: #6c757d;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                                    No catches recorded for this category yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Publish Button -->
    <?php if (!$is_published): ?>
        <div class="section">
            <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; border-radius: 16px; padding: 2.5rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h4 style="color: white; margin-bottom: 0.75rem; font-size: 1.5rem;">
                    Ready to Publish Official Results?
                </h4>
                <p style="color: rgba(255,255,255,0.9); margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                    Publishing will mark these results as official and finalize the tournament standings. This action cannot be undone.
                </p>
                <form method="POST" action="publishResult.php" onsubmit="return confirm('Publish these results as official?\n\nThis action:\n- Finalizes all rankings\n- Cannot be undone\n- Marks tournament as completed\n\nProceed?');">
                    <input type="hidden" name="tournament_id" value="<?= $tournament_id ?>">
                    <button type="submit" class="btn btn-light" style="padding: 1rem 3rem; font-size: 1.125rem; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> Publish Official Results
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>