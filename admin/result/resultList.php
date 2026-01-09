<?php
$page_title = "Tournament Results Management";
require_once __DIR__ . '/../includes/header.php';

// ======================================================
// QUERY: Get all tournaments with result & catch summary
// ======================================================
$query = "
SELECT 
    t.tournament_id,
    t.tournament_title,
    t.tournament_date,
    t.location,
    t.status,
    COUNT(DISTINCT tr.registration_id) AS total_participants,
    COUNT(DISTINCT fc.catch_id) AS total_catches,
    MAX(r.last_updated) AS result_last_updated,
    CASE 
        WHEN EXISTS (
            SELECT 1 
            FROM RESULT r2 
            WHERE r2.tournament_id = t.tournament_id 
              AND r2.result_status = 'final'
        ) THEN 'published'
        ELSE 'draft'
    END AS result_publication_status
FROM TOURNAMENT t
LEFT JOIN TOURNAMENT_REGISTRATION tr 
    ON t.tournament_id = tr.tournament_id 
    AND tr.approval_status = 'approved'
LEFT JOIN FISH_CATCH fc 
    ON tr.registration_id = fc.registration_id
LEFT JOIN RESULT r 
    ON t.tournament_id = r.tournament_id
GROUP BY t.tournament_id
ORDER BY t.tournament_date DESC
";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$tournaments = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">

    <!-- ===================== STATISTICS CARDS ===================== -->
    <div class="row mb-4">

        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6>Total Tournaments</h6>
                    <h3><?= count($tournaments); ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6>Published Results</h6>
                    <h3>
                        <?= count(array_filter($tournaments, fn($t) => $t['result_publication_status'] === 'published')); ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6>Draft Results</h6>
                    <h3>
                        <?= count(array_filter($tournaments, fn($t) => 
                            $t['result_publication_status'] === 'draft' && $t['total_catches'] > 0
                        )); ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6>Ongoing Tournaments</h6>
                    <h3>
                        <?= count(array_filter($tournaments, fn($t) => $t['status'] === 'ongoing')); ?>
                    </h3>
                </div>
            </div>
        </div>

    </div>

    <!-- ===================== RESULTS TABLE ===================== -->
    <div class="card">
        <div class="card-header">
            <h5>Tournament Results</h5>
        </div>

        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Tournament</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Participants</th>
                        <th>Total Catches</th>
                        <th>Result Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($tournaments as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['tournament_title']); ?></strong></td>

                        <td><?= date('d M Y', strtotime($t['tournament_date'])); ?></td>

                        <td>
                            <?php
                            $statusClass = [
                                'upcoming'  => 'primary',
                                'ongoing'   => 'success',
                                'completed' => 'secondary',
                                'cancelled' => 'danger'
                            ];
                            ?>
                            <span class="badge bg-<?= $statusClass[$t['status']] ?>">
                                <?= ucfirst($t['status']); ?>
                            </span>
                        </td>

                        <td><?= $t['total_participants']; ?></td>
                        <td><?= $t['total_catches']; ?></td>

                        <td>
                            <?php if ($t['result_publication_status'] === 'published'): ?>
                                <span class="badge bg-success">Published</span>
                            <?php elseif ($t['total_catches'] > 0): ?>
                                <span class="badge bg-warning text-dark">Draft</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No Data</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= $t['result_last_updated'] 
                                ? date('d M Y H:i', strtotime($t['result_last_updated'])) 
                                : '-'; ?>
                        </td>

                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($t['total_catches'] > 0): ?>
                                    <a href="viewResult.php?tournament_id=<?= $t['tournament_id']; ?>" 
                                       class="btn btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <a href="calculateResult.php?tournament_id=<?= $t['tournament_id']; ?>" 
                                       class="btn btn-primary" title="Calculate">
                                        <i class="fas fa-calculator"></i>
                                    </a>

                                    <?php if ($t['result_publication_status'] === 'draft'): ?>
                                        <button onclick="publishResults(<?= $t['tournament_id']; ?>)" 
                                                class="btn btn-success" title="Publish">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    <?php endif; ?>

                                    <a href="printResult.php?tournament_id=<?= $t['tournament_id']; ?>" 
                                       class="btn btn-secondary" target="_blank" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>No Data</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    </div>
</div>

<script>
function publishResults(tournamentId) {
    if (confirm('Are you sure you want to publish these results?')) {
        window.location.href = 'publishResult.php?tournament_id=' + tournamentId;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
