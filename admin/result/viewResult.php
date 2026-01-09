<?php
$page_title = "Tournament Results";
require_once __DIR__ . '/../includes/header.php';

$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if ($tournament_id <= 0) {
    $_SESSION['error'] = "Invalid tournament ID.";
    header("Location: resultList.php");
    exit();
}

// Check if user is logged in
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Get tournament details
$query = "SELECT * FROM TOURNAMENT WHERE tournament_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found.";
    header("Location: " . ($is_admin ? "resultList.php" : "../index.php"));
    exit();
}

// Get all categories with results
$query = "SELECT DISTINCT c.* 
          FROM CATEGORY c
          INNER JOIN RESULT r ON c.category_id = r.category_id
          WHERE r.tournament_id = ?
          ORDER BY c.category_id";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get result publication status
$query = "SELECT result_status FROM RESULT WHERE tournament_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$result_status_row = $stmt->fetch(PDO::FETCH_ASSOC);
$is_published = $result_status_row ? $result_status_row['result_status'] === 'final' : false;

// Get prizes for this tournament
$query = "SELECT tp.*, s.sponsor_name 
          FROM TOURNAMENT_PRIZE tp
          LEFT JOIN SPONSOR s ON tp.sponsor_id = s.sponsor_id
          WHERE tp.tournament_id = ?
          ORDER BY tp.prize_ranking";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Action Buttons -->
<div class="text-end mb-3">
    <a href="resultList.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <a href="calculateResult.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-primary">
        <i class="fas fa-calculator"></i> Recalculate
    </a>
    <a href="printResult.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-info" target="_blank">
        <i class="fas fa-print"></i> Print
    </a>
</div>

<!-- Tournament Header -->
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-0"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h3>
                            <p class="mb-0">
                                <i class="fas fa-calendar"></i> <?php echo date('l, d F Y', strtotime($tournament['tournament_date'])); ?>
                                <span class="mx-2">|</span>
                                <i class="fas fa-geo-alt"></i> <?php echo htmlspecialchars($tournament['location']); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($is_published): ?>
                                <span class="badge bg-success fs-6">
                                    <i class="fas fa-check-circle"></i> OFFICIAL RESULTS
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark fs-6">
                                    <i class="fas fa-clock"></i> LIVE RESULTS
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$is_published): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Live Results:</strong> These results are calculated in real-time and may change as more catches are recorded.
                    <?php if ($is_admin): ?>
                        Official results will be published when you click "Publish Results" button.
                    <?php else: ?>
                        Official results will be published by the tournament organizer at the end of the tournament.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($categories)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No results available yet. 
                    <?php if ($is_admin): ?>
                        Please <a href="calculateResult.php?tournament_id=<?php echo $tournament_id; ?>">calculate results</a> first.
                    <?php else: ?>
                        Results will be available once catches are recorded and calculated.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Results by Category -->
                <?php foreach ($categories as $category): ?>
                    <?php
                    // Get results for this category
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
                              WHERE r.tournament_id = ? AND r.category_id = ?
                              ORDER BY r.ranking_position ASC";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$tournament_id, $category['category_id']]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="card mb-4">
                        <div class="card-header bg-dark text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-award"></i> <?php echo htmlspecialchars($category['category_name']); ?>
                            </h4>
                            <?php if ($category['description']): ?>
                                <small><?php echo htmlspecialchars($category['description']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="80">Rank</th>
                                            <th>Angler</th>
                                            <?php if (strtolower($category['category_name']) === 'most catches'): ?>
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
                                        <?php foreach ($results as $index => $result): ?>
                                            <?php
                                            $rank_class = '';
                                            $medal = '';
                                            if ($result['ranking_position'] == 1) {
                                                $rank_class = 'table-warning';
                                                $medal = '<i class="fas fa-trophy-fill text-warning fs-4"></i>';
                                            } elseif ($result['ranking_position'] == 2) {
                                                $rank_class = 'table-secondary';
                                                $medal = '<i class="fas fa-trophy-fill text-secondary fs-5"></i>';
                                            } elseif ($result['ranking_position'] == 3) {
                                                $rank_class = 'table-info';
                                                $medal = '<i class="fas fa-trophy-fill text-danger fs-6"></i>';
                                            }
                                            
                                            // Find matching prize
                                            $prize_info = '';
                                            foreach ($prizes as $prize) {
                                                if ($prize['prize_ranking'] == $result['ranking_position'] || 
                                                    $prize['prize_ranking'] == 'Top ' . $result['ranking_position']) {
                                                    $prize_info = $prize['prize_description'];
                                                    if ($prize['prize_value']) {
                                                        $prize_info .= ' (RM ' . number_format($prize['prize_value'], 2) . ')';
                                                    }
                                                    if ($prize['sponsor_name']) {
                                                        $prize_info .= '<br><small class="text-muted">Sponsored by: ' . htmlspecialchars($prize['sponsor_name']) . '</small>';
                                                    }
                                                    break;
                                                }
                                            }
                                            ?>
                                            <tr class="<?php echo $rank_class; ?>">
                                                <td class="text-center">
                                                    <?php if ($medal): ?>
                                                        <?php echo $medal; ?>
                                                    <?php else: ?>
                                                        <strong class="fs-5">#<?php echo $result['ranking_position']; ?></strong>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($result['profile_image']): ?>
                                                            <img src="../assets/images/profiles/<?php echo htmlspecialchars($result['profile_image']); ?>" 
                                                                 class="rounded-circle me-2" 
                                                                 width="40" 
                                                                 height="40"
                                                                 alt="Profile">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" 
                                                                 style="width: 40px; height: 40px;">
                                                                <i class="fas fa-person"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($result['full_name']); ?></strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <?php if (strtolower($category['category_name']) === 'most catches'): ?>
                                                    <td>
                                                        <span class="badge bg-primary fs-6">
                                                            <?php echo $result['total_fish_count']; ?> catches
                                                        </span>
                                                    </td>
                                                <?php else: ?>
                                                    <td><?php echo htmlspecialchars($result['fish_species']); ?></td>
                                                    <td>
                                                        <strong class="text-primary fs-5">
                                                            <?php echo number_format($result['fish_weight'], 2); ?> kg
                                                        </strong>
                                                    </td>
                                                <?php endif; ?>
                                                <td><?php echo $result['catch_time'] ? date('H:i:s', strtotime($result['catch_time'])) : '-'; ?></td>
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
                                                    <td>
                                                        <?php echo $prize_info ? $prize_info : '-'; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Publish Button (Admin Only) -->
                <?php if ($is_admin && !$is_published): ?>
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <h5><i class="fas fa-send"></i> Ready to Publish Official Results?</h5>
                            <p class="text-muted">
                                Publishing will mark these results as official and notify all participants.
                                This action cannot be undone.
                            </p>
                            <form method="POST" action="publishResult.php" onsubmit="return confirm('Are you sure you want to publish these results as official? This action cannot be undone.');">
                                <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-send-check"></i> Publish Official Results
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>