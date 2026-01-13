<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/admin/index.php');
}

// Auto-calculate results function
function calculateTournamentResults($conn, $tournament_id) {
    try {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        // Delete existing results for this tournament
        mysqli_query($conn, "DELETE FROM RESULT WHERE tournament_id = '$tournament_id'");
        
        // Get all categories for prizes in this tournament
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
                    // Get heaviest catch per participant - FIXED: Added table aliases
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
                    // Get lightest catch per participant - FIXED: Added table aliases
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
                    // Get participants with most catches - FIXED: Added table aliases
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
                    // Get catches that exactly match target weight
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
                    // Custom/default category - use heaviest catch logic
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
            
            // Insert results with ranking
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
        
        // Commit transaction
        mysqli_commit($conn);
        
        return [
            'success' => true,
            'total_results' => $total_results,
            'message' => "Results calculated successfully! $total_results rankings generated."
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return [
            'success' => false,
            'message' => "Error calculating results: " . $e->getMessage()
        ];
    }
}

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

// Get categories linked to this tournament via prizes
$query = "SELECT DISTINCT c.* 
          FROM CATEGORY c
          INNER JOIN TOURNAMENT_PRIZE tp ON c.category_id = tp.category_id
          WHERE tp.tournament_id = '$tournament_id'
          ORDER BY c.category_id";
$categories_result = mysqli_query($conn, $query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}

// Get participant and catch statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM TOURNAMENT_REGISTRATION WHERE tournament_id = '$tournament_id' AND approval_status = 'approved') as participant_count,
    (SELECT COUNT(*) FROM FISH_CATCH fc 
     JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id 
     WHERE tr.tournament_id = '$tournament_id') as catch_count";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

// Handle manual calculation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate'])) {
    $result = calculateTournamentResults($conn, $tournament_id);
    
    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }
    
    redirect(SITE_URL . '/admin/result/viewResult.php?tournament_id=' . $tournament_id);
}

include '../includes/header.php';
?>

<style>
.category-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.category-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.category-type-badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-heaviest { background: #ffeaa7; color: #2d3436; }
.type-lightest { background: #a29bfe; color: white; }
.type-most_catches { background: #55efc4; color: #2d3436; }
.type-exact_weight { background: #ff7675; color: white; }
.type-custom { background: #dfe6e9; color: #2d3436; }

.stat-box {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--color-blue-primary);
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="viewResult.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Results
    </a>
</div>

<!-- Tournament Info -->
<div class="section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-trophy"></i> Tournament Information
        </h3>
    </div>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div>
            <h4 style="color: var(--color-blue-primary); margin-bottom: 1rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
            </h4>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div>
                    <div style="font-size: 0.8125rem; color: #6c757d; margin-bottom: 0.25rem;">Date</div>
                    <div style="font-weight: 600;"><?= date('d M Y', strtotime($tournament['tournament_date'])) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8125rem; color: #6c757d; margin-bottom: 0.25rem;">Status</div>
                    <div>
                        <span class="badge badge-<?= $tournament['status'] ?>">
                            <?= ucfirst($tournament['status']) ?>
                        </span>
                    </div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <div style="font-size: 0.8125rem; color: #6c757d; margin-bottom: 0.25rem;">Location</div>
                    <div style="font-weight: 600;"><?= htmlspecialchars($tournament['location']) ?></div>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
            <div class="stat-box">
                <div class="stat-value"><?= $stats['participant_count'] ?></div>
                <div class="stat-label">Participants</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $stats['catch_count'] ?></div>
                <div class="stat-label">Total Catches</div>
            </div>
        </div>
    </div>
</div>

<!-- Categories Overview -->
<div class="section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-list-check"></i> Categories to Calculate
        </h3>
    </div>
    
    <?php if (empty($categories)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>No Categories Found!</strong> 
            This tournament has no categories with prizes assigned. Please add categories and prizes first.
            <div style="margin-top: 1rem;">
                <a href="../prize/managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Categories & Prizes
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($categories as $category): ?>
            <div class="category-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div style="flex: 1;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #1a1a1a; font-size: 1.125rem;">
                            <?= htmlspecialchars($category['category_name']) ?>
                        </h5>
                        <?php if (!empty($category['description'])): ?>
                            <p style="margin: 0; color: #6c757d; font-size: 0.875rem;">
                                <?= htmlspecialchars($category['description']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <span class="category-type-badge type-<?= $category['category_type'] ?? 'custom' ?>">
                        <?php
                        $type_label = [
                            'heaviest' => 'Heaviest',
                            'lightest' => 'Lightest',
                            'most_catches' => 'Most Catches',
                            'exact_weight' => 'Exact Weight',
                            'custom' => 'Custom'
                        ];
                        echo $type_label[$category['category_type'] ?? 'custom'];
                        ?>
                    </span>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding-top: 1rem; border-top: 1px solid #e9ecef;">
                    <div>
                        <div style="font-size: 0.75rem; color: #6c757d; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.5px;">Rankings</div>
                        <div style="font-weight: 600; color: #1a1a1a;">Top <?= $category['number_of_ranking'] ?></div>
                    </div>
                    
                    <?php if (($category['category_type'] ?? 'custom') === 'exact_weight' && $category['target_weight']): ?>
                        <div>
                            <div style="font-size: 0.75rem; color: #6c757d; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.5px;">Target Weight</div>
                            <div style="font-weight: 600; color: #ff7675;"><?= number_format($category['target_weight'], 2) ?> KG</div>
                        </div>
                    <?php endif; ?>
                    
                    <div style="grid-column: -2 / -1;">
                        <div style="font-size: 0.75rem; color: #6c757d; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.5px;">Calculation Method</div>
                        <div style="font-size: 0.875rem; color: #495057;">
                            <?php
                            $method = '';
                            switch ($category['category_type'] ?? 'custom') {
                                case 'heaviest':
                                    $method = 'Heaviest single catch (ties: earliest)';
                                    break;
                                case 'lightest':
                                    $method = 'Lightest single catch (ties: earliest)';
                                    break;
                                case 'most_catches':
                                    $method = 'Total catches count (ties: earliest)';
                                    break;
                                case 'exact_weight':
                                    $method = 'Exact weight match (ties: earliest)';
                                    break;
                                default:
                                    $method = 'Heaviest catch (default)';
                                    break;
                            }
                            echo $method;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Manual Recalculate Button -->
<?php if (!empty($categories) && $stats['catch_count'] > 0): ?>
    <div class="section">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 2.5rem; text-align: center; color: white;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">
                <i class="fas fa-sync-alt"></i>
            </div>
            <h4 style="color: white; margin-bottom: 0.75rem; font-size: 1.5rem;">
                Manual Recalculation
            </h4>
            <p style="color: rgba(255,255,255,0.9); font-size: 1rem; margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                Results update automatically when catches are recorded. Use this button only if you need to force a manual recalculation for all <?= count($categories) ?> categories.
            </p>
            <form method="POST" onsubmit="return confirm('Force recalculate results?\n\nThis will:\n- Reanalyze all catches\n- Recalculate rankings\n- Overwrite current results\n\nProceed?');">
                <button type="submit" name="calculate" class="btn btn-light" style="padding: 1rem 3rem; font-size: 1.125rem; font-weight: 600;">
                    <i class="fas fa-redo"></i> Force Recalculate Now
                </button>
            </form>
        </div>
    </div>
<?php elseif ($stats['catch_count'] === 0): ?>
    <div class="alert alert-warning">
        <i class="fas fa-fish"></i>
        <strong>No Catches Recorded!</strong> Results cannot be calculated until catches are recorded for this tournament.
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>