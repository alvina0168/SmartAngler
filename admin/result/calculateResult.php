<?php
$page_title = "Calculate Results";
require_once __DIR__ . '/../includes/header.php';

$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if ($tournament_id <= 0) {
    $_SESSION['error'] = "Invalid tournament ID.";
    header("Location: resultList.php");
    exit();
}

// Get tournament details
$query = "SELECT * FROM TOURNAMENT WHERE tournament_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found.";
    header("Location: resultList.php");
    exit();
}

// Get all active categories
$query = "SELECT * FROM CATEGORY ORDER BY category_id";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If form is submitted, calculate results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate'])) {
    try {
        $conn->beginTransaction();
        
        // Delete existing results for this tournament (to recalculate)
        $query = "DELETE FROM RESULT WHERE tournament_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$tournament_id]);
        
        // Process each category
        foreach ($categories as $category) {
            $category_id = $category['category_id'];
            $category_name = $category['category_name'];
            $number_of_ranking = $category['number_of_ranking'];
            
            // Different calculation logic based on category
            switch (strtolower($category_name)) {
                case 'heaviest catch':
                    // Get heaviest catch per participant
                    $query = "SELECT 
                                fc.catch_id,
                                fc.user_id,
                                fc.registration_id,
                                MAX(fc.fish_weight) as max_weight,
                                MIN(fc.catch_time) as earliest_catch_time
                              FROM FISH_CATCH fc
                              JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                              WHERE tr.tournament_id = ? AND tr.approval_status = 'approved'
                              GROUP BY fc.user_id
                              ORDER BY max_weight DESC, earliest_catch_time ASC
                              LIMIT ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$tournament_id, $number_of_ranking]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Insert results with ranking
                    $rank = 1;
                    foreach ($results as $result) {
                        $insertQuery = "INSERT INTO RESULT 
                                       (tournament_id, user_id, catch_id, category_id, ranking_position, result_status)
                                       VALUES (?, ?, ?, ?, ?, 'ongoing')";
                        $insertStmt = $conn->prepare($insertQuery);
                        $insertStmt->execute([
                            $tournament_id,
                            $result['user_id'],
                            $result['catch_id'],
                            $category_id,
                            $rank
                        ]);
                        $rank++;
                    }
                    break;
                    
                case 'lightest catch':
                    // Get lightest catch per participant
                    $query = "SELECT 
                                fc.catch_id,
                                fc.user_id,
                                fc.registration_id,
                                MIN(fc.fish_weight) as min_weight,
                                MIN(fc.catch_time) as earliest_catch_time
                              FROM FISH_CATCH fc
                              JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                              WHERE tr.tournament_id = ? AND tr.approval_status = 'approved'
                              GROUP BY fc.user_id
                              ORDER BY min_weight ASC, earliest_catch_time ASC
                              LIMIT ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$tournament_id, $number_of_ranking]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $rank = 1;
                    foreach ($results as $result) {
                        $insertQuery = "INSERT INTO RESULT 
                                       (tournament_id, user_id, catch_id, category_id, ranking_position, result_status)
                                       VALUES (?, ?, ?, ?, ?, 'ongoing')";
                        $insertStmt = $conn->prepare($insertQuery);
                        $insertStmt->execute([
                            $tournament_id,
                            $result['user_id'],
                            $result['catch_id'],
                            $category_id,
                            $rank
                        ]);
                        $rank++;
                    }
                    break;
                    
                case 'most catches':
                    // Get participants with most catches
                    $query = "SELECT 
                                fc.user_id,
                                fc.registration_id,
                                COUNT(fc.catch_id) as total_catches,
                                MIN(fc.catch_time) as earliest_catch_time,
                                MAX(fc.catch_id) as last_catch_id
                              FROM FISH_CATCH fc
                              JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                              WHERE tr.tournament_id = ? AND tr.approval_status = 'approved'
                              GROUP BY fc.user_id
                              ORDER BY total_catches DESC, earliest_catch_time ASC
                              LIMIT ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$tournament_id, $number_of_ranking]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $rank = 1;
                    foreach ($results as $result) {
                        $insertQuery = "INSERT INTO RESULT 
                                       (tournament_id, user_id, catch_id, category_id, ranking_position, total_fish_count, result_status)
                                       VALUES (?, ?, ?, ?, ?, ?, 'ongoing')";
                        $insertStmt = $conn->prepare($insertQuery);
                        $insertStmt->execute([
                            $tournament_id,
                            $result['user_id'],
                            $result['last_catch_id'],
                            $category_id,
                            $rank,
                            $result['total_catches']
                        ]);
                        $rank++;
                    }
                    break;
                    
                default:
                    // For custom categories, use heaviest catch logic
                    $query = "SELECT 
                                fc.catch_id,
                                fc.user_id,
                                fc.registration_id,
                                MAX(fc.fish_weight) as max_weight,
                                MIN(fc.catch_time) as earliest_catch_time
                              FROM FISH_CATCH fc
                              JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                              WHERE tr.tournament_id = ? AND tr.approval_status = 'approved'
                              GROUP BY fc.user_id
                              ORDER BY max_weight DESC, earliest_catch_time ASC
                              LIMIT ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$tournament_id, $number_of_ranking]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $rank = 1;
                    foreach ($results as $result) {
                        $insertQuery = "INSERT INTO RESULT 
                                       (tournament_id, user_id, catch_id, category_id, ranking_position, result_status)
                                       VALUES (?, ?, ?, ?, ?, 'ongoing')";
                        $insertStmt = $conn->prepare($insertQuery);
                        $insertStmt->execute([
                            $tournament_id,
                            $result['user_id'],
                            $result['catch_id'],
                            $category_id,
                            $rank
                        ]);
                        $rank++;
                    }
                    break;
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Results calculated successfully!";
        header("Location: viewResult.php?tournament_id=" . $tournament_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error calculating results: " . $e->getMessage();
    }
}

<!-- Back Button -->
<div class="text-end mb-3">
    <a href="resultList.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Results
    </a>
</div>

<!-- Tournament Info -->
<div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Tournament Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Title:</strong> <?php echo htmlspecialchars($tournament['tournament_title']); ?></p>
                            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($tournament['tournament_date'])); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($tournament['location']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $tournament['status'] === 'ongoing' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($tournament['status']); ?>
                                </span>
                            </p>
                            <?php
                            // Get participant count
                            $query = "SELECT COUNT(*) as count FROM TOURNAMENT_REGISTRATION 
                                     WHERE tournament_id = ? AND approval_status = 'approved'";
                            $stmt = $conn->prepare($query);
                            $stmt->execute([$tournament_id]);
                            $participant_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            // Get catch count
                            $query = "SELECT COUNT(*) as count FROM FISH_CATCH fc
                                     JOIN TOURNAMENT_REGISTRATION tr ON fc.registration_id = tr.registration_id
                                     WHERE tr.tournament_id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->execute([$tournament_id]);
                            $catch_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            ?>
                            <p><strong>Participants:</strong> <?php echo $participant_count; ?></p>
                            <p><strong>Total Catches:</strong> <?php echo $catch_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Overview -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Categories to Calculate</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($categories)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            No categories defined. Please add categories first.
                            <a href="../admin/category/manageCategorries.php" class="btn btn-sm btn-primary ms-2">
                                Add Categories
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Description</th>
                                        <th>Number of Rankings</th>
                                        <th>Calculation Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($category['description']); ?></td>
                                        <td>Top <?php echo $category['number_of_ranking']; ?></td>
                                        <td>
                                            <?php
                                            $method = '';
                                            switch (strtolower($category['category_name'])) {
                                                case 'heaviest catch':
                                                    $method = 'Heaviest single catch weight (ties broken by earliest catch time)';
                                                    break;
                                                case 'lightest catch':
                                                    $method = 'Lightest single catch weight (ties broken by earliest catch time)';
                                                    break;
                                                case 'most catches':
                                                    $method = 'Total number of catches (ties broken by earliest catch time)';
                                                    break;
                                                default:
                                                    $method = 'Default: Heaviest catch weight';
                                                    break;
                                            }
                                            echo $method;
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Calculate Button -->
            <?php if (!empty($categories) && $catch_count > 0): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <h5>Ready to Calculate Results</h5>
                        <p class="text-muted">This will recalculate all results based on current catch data and categories.</p>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to calculate results? This will overwrite existing results.');">
                            <button type="submit" name="calculate" class="btn btn-success btn-lg">
                                <i class="fas fa-calculator"></i> Calculate Results Now
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif ($catch_count === 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No catches recorded yet for this tournament. Results cannot be calculated.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>