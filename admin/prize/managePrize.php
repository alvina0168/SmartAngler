<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Prize Management';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Handle ranking reorder via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'reorder_rankings') {
    header('Content-Type: application/json');
    
    $category_id = intval($_POST['category_id']);
    $target_weight = isset($_POST['target_weight']) ? floatval($_POST['target_weight']) : null;
    $prize_ids = json_decode($_POST['prize_ids'], true);
    
    if (!empty($prize_ids) && is_array($prize_ids)) {
        $rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];
        
        foreach ($prize_ids as $index => $prize_id) {
            $prize_id = intval($prize_id);
            $new_ranking = $rankings[$index] ?? ($index + 1) . 'th';
            
            mysqli_query($conn, "
                UPDATE TOURNAMENT_PRIZE 
                SET prize_ranking = '$new_ranking' 
                WHERE prize_id = $prize_id AND tournament_id = $tournament_id
            ");
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
    exit;
}

// Handle add new ranking
if (isset($_POST['action']) && $_POST['action'] === 'add_ranking') {
    $category_id = intval($_POST['category_id']);
    $target_weight = !empty($_POST['target_weight']) ? floatval($_POST['target_weight']) : null;
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $value = floatval($_POST['value']);
    
    if (!empty($category_id) && !empty($description) && $value > 0) {
        // Get current count for this category with specific target weight
        $where_weight = $target_weight ? "AND target_weight = $target_weight" : "AND target_weight IS NULL";
        $count_query = "SELECT COUNT(*) as count FROM TOURNAMENT_PRIZE WHERE tournament_id = $tournament_id AND category_id = $category_id $where_weight";
        $count_result = mysqli_query($conn, $count_query);
        $count_data = mysqli_fetch_assoc($count_result);
        $next_index = $count_data['count'];
        
        $rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];
        $prize_ranking = $rankings[$next_index] ?? ($next_index + 1) . 'th';
        
        $target_weight_value = $target_weight ? $target_weight : 'NULL';
        
        $insert_query = "
            INSERT INTO TOURNAMENT_PRIZE (tournament_id, category_id, prize_ranking, prize_description, prize_value, target_weight)
            VALUES ($tournament_id, $category_id, '$prize_ranking', '$description', $value, $target_weight_value)
        ";
        
        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['success'] = 'New ranking added successfully!';
        } else {
            $_SESSION['error'] = 'Failed to add ranking: ' . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = 'Please fill in all fields!';
    }
    
    redirect(SITE_URL . '/admin/prize/managePrize.php?tournament_id=' . $tournament_id);
}

// Fetch tournament info
$tournament_query = "SELECT tournament_title FROM TOURNAMENT WHERE tournament_id = $tournament_id";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Fetch all prizes grouped by category AND target weight
$prizes_query = "
    SELECT 
        tp.*,
        c.category_name,
        c.category_type,
        c.description as category_description,
        c.number_of_ranking
    FROM TOURNAMENT_PRIZE tp
    LEFT JOIN CATEGORY c ON tp.category_id = c.category_id
    WHERE tp.tournament_id = $tournament_id
    ORDER BY c.category_name ASC, 
             tp.target_weight ASC,
             CASE tp.prize_ranking
                 WHEN '1st' THEN 1
                 WHEN '2nd' THEN 2
                 WHEN '3rd' THEN 3
                 WHEN '4th' THEN 4
                 WHEN '5th' THEN 5
                 WHEN '6th' THEN 6
                 WHEN '7th' THEN 7
                 WHEN '8th' THEN 8
                 WHEN '9th' THEN 9
                 WHEN '10th' THEN 10
                 ELSE 99
             END
";
$prizes_result = mysqli_query($conn, $prizes_query);

// Group prizes by category AND target weight
$prizes_by_category = [];
while ($prize = mysqli_fetch_assoc($prizes_result)) {
    $category_id = $prize['category_id'] ?? 'uncategorized';
    $category_name = $prize['category_name'] ?? 'Uncategorized';
    $target_weight = $prize['target_weight'] ?? null;
    
    // Create unique key for category + target weight combination
    $group_key = $category_id . '_' . ($target_weight ?? 'null');
    
    if (!isset($prizes_by_category[$group_key])) {
        $prizes_by_category[$group_key] = [
            'category_id' => $category_id,
            'name' => $category_name,
            'description' => $prize['category_description'] ?? '',
            'type' => $prize['category_type'] ?? '',
            'target_weight' => $target_weight,
            'number_of_ranking' => $prize['number_of_ranking'] ?? 0,
            'prizes' => []
        ];
    }
    
    $prizes_by_category[$group_key]['prizes'][] = $prize;
}

// Calculate statistics
$total_prizes = 0;
$total_value = 0;
foreach ($prizes_by_category as $category) {
    $total_prizes += count($category['prizes']);
    foreach ($category['prizes'] as $prize) {
        $total_value += $prize['prize_value'];
    }
}

include '../includes/header.php';
?>

<style>
.prize-row {
    transition: background-color 0.2s ease;
    cursor: move;
}

.prize-row:hover {
    background-color: #f8f9fa;
}

.prize-row.dragging {
    opacity: 0.5;
    background-color: #e3f2fd;
}

.drag-handle {
    cursor: grab;
    color: #999;
    font-size: 1.25rem;
    padding: 0 0.5rem;
}

.drag-handle:active {
    cursor: grabbing;
}

.drag-handle:hover {
    color: var(--color-blue-primary);
}

.add-ranking-row {
    background: #e8f5e9;
    border: 2px dashed #4caf50;
}

.add-ranking-row input,
.add-ranking-row textarea {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.875rem;
}

.add-ranking-row input:focus,
.add-ranking-row textarea:focus {
    outline: none;
    border-color: #4caf50;
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.btn-add-ranking {
    background: #28a745;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-add-ranking:hover {
    background: #218838;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.btn-save-ranking {
    background: #007bff;
    color: white;
    border: none;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
}

.btn-save-ranking:hover {
    background: #0056b3;
}

.btn-cancel-ranking {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
}

.btn-cancel-ranking:hover {
    background: #5a6268;
}
</style>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="../tournament/viewTournament.php?id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Tournament
    </a>
</div>

<!-- Header Section -->
<div class="section">
    <div class="section-header">
        <div>
            <h2 class="section-title">
                <i class="fas fa-trophy"></i> Categories & Prizes
            </h2>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
            </p>
        </div>
        <a href="addPrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Configure Prizes
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="dashboard-stats" style="margin-bottom: 0;">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6D94C5, #CBDCEB);">
                    <i class="fas fa-gift"></i>
                </div>
            </div>
            <div class="stat-value"><?= $total_prizes ?></div>
            <div class="stat-label">Total Prizes</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4caf50, #66bb6a);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-value">RM <?= number_format($total_value, 2) ?></div>
            <div class="stat-label">Total Prize Value</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ff9800, #ffb74d);">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-value"><?= count($prizes_by_category) ?></div>
            <div class="stat-label">Prize Categories</div>
        </div>
    </div>
</div>

<!-- Prizes by Category -->
<?php if (count($prizes_by_category) > 0): ?>
    <?php foreach ($prizes_by_category as $group_key => $category_data): ?>
        <div class="section">
            <div class="section-header">
                <div style="flex: 1;">
                    <h3 class="section-title" style="margin-bottom: 0.5rem;">
                        <i class="fas fa-trophy"></i>
                        <?= htmlspecialchars($category_data['name']) ?>
                    </h3>
                    <?php if (!empty($category_data['description'])): ?>
                        <p style="color: #6c757d; font-size: 0.875rem; margin: 0;">
                            <?= htmlspecialchars($category_data['description']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($category_data['type'] === 'exact_weight' && !empty($category_data['target_weight'])): ?>
                        <div style="margin-top: 0.5rem; color: #f57c00; font-size: 0.875rem; font-weight: 600;">
                            <i class="fas fa-weight"></i> Target Weight: <?= $category_data['target_weight'] ?> KG
                        </div>
                    <?php endif; ?>
                </div>
                <span class="badge badge-info">
                    <?= count($category_data['prizes']) ?> <?= count($category_data['prizes']) == 1 ? 'prize' : 'prizes' ?>
                </span>
            </div>

            <!-- Prize Table -->
            <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; overflow-x: auto;">
                <form method="POST" action="" id="add-form-<?= $group_key ?>">
                    <input type="hidden" name="action" value="add_ranking">
                    <input type="hidden" name="category_id" value="<?= $category_data['category_id'] ?>">
                    <?php if ($category_data['target_weight']): ?>
                    <input type="hidden" name="target_weight" value="<?= $category_data['target_weight'] ?>">
                    <?php endif; ?>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #dee2e6;">
                                <th style="text-align: center; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 60px;"></th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 100px;">Place</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem;">Prize Description</th>
                                <th style="text-align: right; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 150px;">Value (RM)</th>
                                <th style="text-align: center; padding: 0.75rem; font-weight: 600; color: #6c757d; font-size: 0.875rem; width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="category-tbody-<?= $group_key ?>" 
                               data-category-id="<?= $category_data['category_id'] ?>"
                               data-target-weight="<?= $category_data['target_weight'] ?? '' ?>">
                            <?php foreach ($category_data['prizes'] as $prize): ?>
                                <tr class="prize-row" 
                                    data-prize-id="<?= $prize['prize_id'] ?>" 
                                    draggable="true"
                                    style="border-bottom: 1px solid #e9ecef;">
                                    <td style="padding: 0.75rem; text-align: center;">
                                        <i class="fas fa-grip-vertical drag-handle"></i>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span style="font-size: 1.25rem;" class="ranking-emoji">
                                                <?php
                                                $ranking = $prize['prize_ranking'];
                                                if ($ranking == '1st') echo '🥇';
                                                elseif ($ranking == '2nd') echo '🥈';
                                                elseif ($ranking == '3rd') echo '🥉';
                                                ?>
                                            </span>
                                            <span style="font-weight: 700; color: #495057;" class="ranking-label">
                                                <?= htmlspecialchars($prize['prize_ranking']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td style="padding: 0.75rem; color: #1a1a1a;">
                                        <?= htmlspecialchars($prize['prize_description']) ?>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right; font-weight: 700; color: #28a745; font-size: 1rem;">
                                        RM <?= number_format($prize['prize_value'], 2) ?>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: center;">
                                        <div class="action-btns" style="display: inline-flex; gap: 0.5rem;">
                                            <a href="editPrize.php?id=<?= $prize['prize_id'] ?>" 
                                               class="btn btn-primary btn-sm" 
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button"
                                                    onclick="if(confirm('Are you sure you want to delete this prize?\n\nPlace: <?= htmlspecialchars($prize['prize_ranking']) ?>\nDescription: <?= htmlspecialchars($prize['prize_description']) ?>')) { window.location.href='deletePrize.php?id=<?= $prize['prize_id'] ?>&tournament_id=<?= $tournament_id ?>'; }" 
                                                    class="btn btn-danger btn-sm" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Add Ranking Row (Hidden by default) -->
                            <tr class="add-ranking-row" id="add-row-<?= $group_key ?>" style="display: none; border-bottom: 1px solid #4caf50;">
                                <td style="padding: 0.75rem; text-align: center;">
                                    <i class="fas fa-plus" style="color: #4caf50; font-size: 1.25rem;"></i>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 1.25rem;" id="new-ranking-emoji-<?= $group_key ?>"></span>
                                        <span style="font-weight: 700; color: #495057;" id="new-ranking-label-<?= $group_key ?>">
                                            <?php
                                            $next_index = count($category_data['prizes']);
                                            $rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];
                                            $next_ranking = $rankings[$next_index] ?? ($next_index + 1) . 'th';
                                            echo $next_ranking;
                                            ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <input type="text" 
                                           name="description" 
                                           placeholder="Enter prize description..." 
                                           required>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <input type="number" 
                                           name="value" 
                                           step="0.01" 
                                           min="0" 
                                           placeholder="0.00" 
                                           required
                                           style="text-align: right;">
                                </td>
                                <td style="padding: 0.75rem; text-align: center;">
                                    <div style="display: inline-flex; gap: 0.5rem;">
                                        <button type="submit" class="btn-save-ranking" title="Save">
                                            <i class="fas fa-check"></i> Save
                                        </button>
                                        <button type="button" 
                                                class="btn-cancel-ranking" 
                                                onclick="cancelAddRanking('<?= $group_key ?>')"
                                                title="Cancel">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>

                <!-- Add Ranking Button -->
                <button type="button" 
                        class="btn-add-ranking" 
                        id="btn-add-<?= $group_key ?>"
                        onclick="showAddRankingRow('<?= $group_key ?>')">
                    <i class="fas fa-plus"></i> Add Ranking
                </button>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-gift"></i>
        <h3>No Prizes Yet</h3>
        <p>Start adding prizes to reward tournament winners</p>
        <a href="addPrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Configure Prizes
        </a>
    </div>
<?php endif; ?>

<script>
const rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];
const medals = ['🥇', '🥈', '🥉'];

// Initialize drag and drop for all categories
document.addEventListener('DOMContentLoaded', function() {
    const tbodies = document.querySelectorAll('tbody[id^="category-tbody-"]');
    
    tbodies.forEach(tbody => {
        initDragAndDrop(tbody);
    });
});

function initDragAndDrop(tbody) {
    const rows = tbody.querySelectorAll('.prize-row');
    let draggedRow = null;
    
    rows.forEach(row => {
        row.addEventListener('dragstart', function(e) {
            draggedRow = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        
        row.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
            
            // Update rankings after drag
            const groupKey = tbody.id.replace('category-tbody-', '');
            updateRankings(groupKey);
            saveOrder(groupKey);
        });
        
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if (draggedRow !== this) {
                const allRows = Array.from(tbody.querySelectorAll('.prize-row'));
                const draggedIndex = allRows.indexOf(draggedRow);
                const targetIndex = allRows.indexOf(this);
                
                if (draggedIndex < targetIndex) {
                    this.parentNode.insertBefore(draggedRow, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedRow, this);
                }
            }
        });
    });
}

function updateRankings(groupKey) {
    const tbody = document.getElementById(`category-tbody-${groupKey}`);
    const rows = Array.from(tbody.querySelectorAll('.prize-row'));
    
    rows.forEach((row, index) => {
        // Update ranking label
        const rankingLabel = row.querySelector('.ranking-label');
        rankingLabel.textContent = rankings[index] || `${index + 1}th`;
        
        // Update medal emoji
        const emojiSpan = row.querySelector('.ranking-emoji');
        emojiSpan.textContent = medals[index] || '';
    });
}

function saveOrder(groupKey) {
    const tbody = document.getElementById(`category-tbody-${groupKey}`);
    const categoryId = tbody.dataset.categoryId;
    const targetWeight = tbody.dataset.targetWeight;
    const rows = Array.from(tbody.querySelectorAll('.prize-row'));
    const prizeIds = rows.map(row => row.dataset.prizeId);
    
    let body = `action=reorder_rankings&category_id=${categoryId}&prize_ids=${JSON.stringify(prizeIds)}`;
    if (targetWeight) {
        body += `&target_weight=${targetWeight}`;
    }
    
    fetch('managePrize.php?tournament_id=<?= $tournament_id ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Rankings updated successfully');
        } else {
            alert('Failed to update rankings. Please refresh the page.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function showAddRankingRow(groupKey) {
    // Hide add button
    document.getElementById(`btn-add-${groupKey}`).style.display = 'none';
    
    // Show add ranking row
    document.getElementById(`add-row-${groupKey}`).style.display = 'table-row';
    
    // Focus on description input
    const descInput = document.querySelector(`#add-row-${groupKey} input[name="description"]`);
    if (descInput) {
        descInput.focus();
    }
}

function cancelAddRanking(groupKey) {
    // Show add button
    document.getElementById(`btn-add-${groupKey}`).style.display = 'inline-flex';
    
    // Hide add ranking row
    document.getElementById(`add-row-${groupKey}`).style.display = 'none';
    
    // Clear form inputs
    const form = document.getElementById(`add-form-${groupKey}`);
    form.reset();
}
</script>

<?php include '../includes/footer.php'; ?>