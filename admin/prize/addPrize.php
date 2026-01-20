<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Prize Configuration';

// Get tournament ID first
if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['tournament_id']);
$logged_in_user_id = intval($_SESSION['user_id']);
$logged_in_role = $_SESSION['role'];

// ═══════════════════════════════════════════════════════════════
//              ACCESS CONTROL
// ═══════════════════════════════════════════════════════════════

// Check access permissions
if ($logged_in_role === 'organizer') {
    $access_check = "
        SELECT tournament_id FROM TOURNAMENT 
        WHERE tournament_id = '$tournament_id'
        AND (
            created_by = '$logged_in_user_id'
            OR created_by IN (
                SELECT user_id FROM USER WHERE created_by = '$logged_in_user_id' AND role = 'admin'
            )
        )
    ";
} elseif ($logged_in_role === 'admin') {
    $get_creator_query = "SELECT created_by FROM USER WHERE user_id = '$logged_in_user_id'";
    $creator_result = mysqli_query($conn, $get_creator_query);
    $creator_row = mysqli_fetch_assoc($creator_result);
    $organizer_id = $creator_row['created_by'] ?? null;
    
    if ($organizer_id) {
        $access_check = "
            SELECT tournament_id FROM TOURNAMENT 
            WHERE tournament_id = '$tournament_id'
            AND (created_by = '$logged_in_user_id' OR created_by = '$organizer_id')
        ";
    } else {
        $access_check = "
            SELECT tournament_id FROM TOURNAMENT 
            WHERE tournament_id = '$tournament_id'
            AND created_by = '$logged_in_user_id'
        ";
    }
} else {
    $_SESSION['error'] = 'Access denied';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$access_result = mysqli_query($conn, $access_check);

if (!$access_result || mysqli_num_rows($access_result) == 0) {
    $_SESSION['error'] = 'Tournament not found or access denied';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

// Fetch tournament
$tournament_query = "SELECT tournament_title FROM TOURNAMENT WHERE tournament_id = $tournament_id";
$tournament_result = mysqli_query($conn, $tournament_query);

if (!$tournament_result || mysqli_num_rows($tournament_result) == 0) {
    $_SESSION['error'] = 'Tournament not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament = mysqli_fetch_assoc($tournament_result);

// Fetch categories
$categories_query = "SELECT * FROM CATEGORY ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Fetch existing prizes for this tournament to check duplicates
$existing_prizes_query = "
    SELECT DISTINCT category_id, target_weight
    FROM TOURNAMENT_PRIZE
    WHERE tournament_id = $tournament_id
";
$existing_prizes_result = mysqli_query($conn, $existing_prizes_query);
$existing_categories = [];
while ($row = mysqli_fetch_assoc($existing_prizes_result)) {
    $key = $row['category_id'] . '_' . ($row['target_weight'] ?? 'null');
    $existing_categories[$key] = true;
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success_count = 0;
    
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        foreach ($_POST['categories'] as $catIndex => $catData) {
            if (empty($catData['category_id'])) continue;

            $category_id = intval($catData['category_id']);
            $target_weight = !empty($catData['target_weight']) ? floatval($catData['target_weight']) : null;
            
            // Check if category already exists (except for exact_weight with different weights)
            $check_key = $category_id . '_' . ($target_weight ?? 'null');
            if (isset($existing_categories[$check_key])) {
                // Get category name for error message
                $cat_name_query = mysqli_query($conn, "SELECT category_name, category_type FROM CATEGORY WHERE category_id = $category_id");
                $cat_info = mysqli_fetch_assoc($cat_name_query);
                
                if ($cat_info['category_type'] === 'exact_weight') {
                    $errors[] = "Category '" . $cat_info['category_name'] . "' with target weight " . $target_weight . " KG has already been created for this tournament.";
                } else {
                    $errors[] = "Category '" . $cat_info['category_name'] . "' has already been created for this tournament.";
                }
                continue;
            }

            $number_of_ranks = intval($catData['number_of_ranks']);
            $rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];

            for ($i = 0; $i < $number_of_ranks; $i++) {
                $prize = $catData['prizes'][$i] ?? null;
                if (!$prize || empty($prize['description']) || empty($prize['value'])) continue;

                $description = mysqli_real_escape_string($conn, trim($prize['description']));
                $value = floatval($prize['value']);
                $prize_ranking = $rankings[$i] ?? ($i + 1) . 'th';
                
                $target_weight_value = $target_weight ? $target_weight : 'NULL';

                $insert_query = "
                    INSERT INTO TOURNAMENT_PRIZE
                    (tournament_id, category_id, prize_ranking, prize_description, prize_value, target_weight)
                    VALUES
                    ($tournament_id, $category_id, '$prize_ranking', '$description', $value, $target_weight_value)
                ";
                
                if (mysqli_query($conn, $insert_query)) {
                    $success_count++;
                }
            }
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
    
    if ($success_count > 0) {
        $_SESSION['success'] = "$success_count prize(s) added successfully!";
    }
    
    redirect(SITE_URL . "/admin/prize/managePrize.php?tournament_id=$tournament_id");
}

include '../includes/header.php';
?>

<style>
.prize-category-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.prize-category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.prize-category-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1a1a1a;
}

.prize-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.prize-table thead {
    background: #6D94C5;
    color: white;
}

.prize-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.prize-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e9ecef;
}

.prize-table tbody tr:last-child td {
    border-bottom: none;
}

.prize-table input[type="text"],
.prize-table input[type="number"] {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.875rem;
}

.prize-table input:focus {
    outline: none;
    border-color: var(--color-blue-primary);
    box-shadow: 0 0 0 3px rgba(109, 148, 197, 0.1);
}

.btn-remove-category {
    background: #dc3545;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-remove-category:hover {
    background: #c82333;
}

.btn-add-category {
    background: transparent;
    color: #495057;
    border: 2px dashed #dee2e6;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 0.9375rem;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 1rem;
}

.btn-add-category:hover {
    border-color: var(--color-blue-primary);
    color: var(--color-blue-primary);
    background: #f8f9fa;
}

.target-weight-field {
    display: none;
}

.target-weight-field.show {
    display: block;
}
</style>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Prizes
    </a>
</div>

<!-- Prize Configuration Form -->
<div class="section">
    <div class="section-header">
        <div>
            <h3 class="section-title">
                <i class="fas fa-trophy"></i> Prize Configuration
            </h3>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($tournament['tournament_title']) ?>
            </p>
        </div>
    </div>

    <form method="POST" action="" id="prizeForm">
        <div id="prizeCategoriesContainer"></div>

        <button type="button" class="btn-add-category" onclick="addPrizeCategory()">
            <i class="fas fa-plus"></i> Add Prize Category
        </button>

        <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save Prizes
            </button>
            <a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
let categoryIndex = 0;

// Categories data from PHP
const categories = <?= json_encode(mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM CATEGORY ORDER BY category_name ASC"), MYSQLI_ASSOC)) ?>;

// Existing categories to check duplicates
const existingCategories = <?= json_encode($existing_categories) ?>;

// Ranking labels
const rankingLabels = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];

function addPrizeCategory() {
    const container = document.getElementById('prizeCategoriesContainer');
    const card = document.createElement('div');
    card.className = 'prize-category-card';
    card.id = `category-card-${categoryIndex}`;
    
    let categoryOptions = '<option value="">-- Select Category --</option>';
    categories.forEach(cat => {
        // Remove (X positions) from display
        categoryOptions += `<option value="${cat.category_id}" data-type="${cat.category_type}">${cat.category_name}</option>`;
    });
    
    card.innerHTML = `
        <div class="prize-category-header">
            <div class="prize-category-title">Prize Category ${categoryIndex + 1}</div>
            <button type="button" class="btn-remove-category" onclick="removeCategory(${categoryIndex})">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div class="form-group">
                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Select Category <span class="required">*</span></label>
                <select name="categories[${categoryIndex}][category_id]" 
                        class="form-control" 
                        onchange="handleCategorySelection(${categoryIndex}, this)"
                        required>
                    ${categoryOptions}
                </select>
            </div>
            
            <div class="form-group target-weight-field" id="target-weight-${categoryIndex}">
                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Target Weight (KG) <span class="required">*</span></label>
                <input type="number" 
                       name="categories[${categoryIndex}][target_weight]" 
                       class="form-control" 
                       step="0.01" 
                       min="0.01"
                       placeholder="e.g., 2.5">
                <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                    Specify the exact target weight for this category
                </small>
            </div>
            
            <div class="form-group" id="num-ranks-${categoryIndex}">
                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Number of Winners / Ranks <span class="required">*</span></label>
                <input type="number" 
                       name="categories[${categoryIndex}][number_of_ranks]" 
                       class="form-control" 
                       min="1" 
                       max="20"
                       value="1"
                       onchange="updatePrizeTable(${categoryIndex}, this.value)"
                       required>
                <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.375rem; display: block;">
                    You can add any number of rankings (not limited by category positions)
                </small>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="prize-table">
                <thead>
                    <tr>
                        <th width="120">RANK</th>
                        <th>PRIZE DESCRIPTION</th>
                        <th width="160">VALUE (RM)</th>
                    </tr>
                </thead>
                <tbody id="prize-tbody-${categoryIndex}">
                    <tr>
                        <td><span style="font-weight: 700; color: #495057;">🥇 1st</span></td>
                        <td><input type="text" name="categories[${categoryIndex}][prizes][0][description]" placeholder="Prize description" required></td>
                        <td><input type="number" name="categories[${categoryIndex}][prizes][0][value]" step="0.01" min="0" placeholder="0.00" required></td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    container.appendChild(card);
    categoryIndex++;
}

function removeCategory(index) {
    const card = document.getElementById(`category-card-${index}`);
    if (card) {
        card.remove();
    }
}

function handleCategorySelection(index, selectElement) {
    const categoryId = selectElement.value;
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const categoryType = selectedOption.dataset.type;
    
    // Show/hide target weight field
    const targetWeightField = document.getElementById(`target-weight-${index}`);
    const targetWeightInput = targetWeightField.querySelector('input');
    
    if (categoryType === 'exact_weight') {
        targetWeightField.classList.add('show');
        targetWeightInput.required = true;
    } else {
        targetWeightField.classList.remove('show');
        targetWeightInput.required = false;
        targetWeightInput.value = '';
        
        // Check if category already exists (non-exact_weight categories)
        const checkKey = categoryId + '_null';
        if (existingCategories[checkKey]) {
            const categoryName = selectedOption.textContent;
            alert('Category "' + categoryName + '" has already been created for this tournament. Please select a different category.');
            selectElement.value = '';
            return;
        }
    }
    
    // Reset table to 1 row
    const numRanksInput = document.querySelector(`input[name="categories[${index}][number_of_ranks]"]`);
    if (numRanksInput) {
        numRanksInput.value = 1;
    }
    updatePrizeTable(index, 1);
}

function updatePrizeTable(index, numRanks) {
    const tbody = document.getElementById(`prize-tbody-${index}`);
    tbody.innerHTML = '';
    
    numRanks = parseInt(numRanks) || 1;
    
    for (let i = 0; i < numRanks; i++) {
        const row = document.createElement('tr');
        const rankLabel = rankingLabels[i] || `${i+1}th`;
        const emoji = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '';
        
        row.innerHTML = `
            <td><span style="font-weight: 700; color: #495057;">${emoji} ${rankLabel}</span></td>
            <td><input type="text" name="categories[${index}][prizes][${i}][description]" placeholder="Prize description" required></td>
            <td><input type="number" name="categories[${index}][prizes][${i}][value]" step="0.01" min="0" placeholder="0.00" required></td>
        `;
        tbody.appendChild(row);
    }
}

// Validate form before submit
document.getElementById('prizeForm').addEventListener('submit', function(e) {
    const cards = document.querySelectorAll('.prize-category-card');
    
    if (cards.length === 0) {
        e.preventDefault();
        alert('Please add at least one prize category.');
        return false;
    }
    
    // Check for duplicate exact_weight categories with same weight
    const exactWeightCategories = {};
    let hasDuplicate = false;
    
    cards.forEach(card => {
        const select = card.querySelector('select[name*="[category_id]"]');
        const categoryId = select.value;
        const selectedOption = select.options[select.selectedIndex];
        const categoryType = selectedOption.dataset.type;
        
        if (categoryType === 'exact_weight') {
            const weightInput = card.querySelector('input[name*="[target_weight]"]');
            const weight = weightInput ? weightInput.value : '';
            
            if (!weight) {
                e.preventDefault();
                alert('Please specify target weight for exact weight categories.');
                hasDuplicate = true;
                return;
            }
            
            const key = categoryId + '_' + weight;
            
            // Check against existing categories in database
            if (existingCategories[key]) {
                e.preventDefault();
                alert('An exact weight category with weight ' + weight + ' KG has already been created for this tournament.');
                hasDuplicate = true;
                return;
            }
            
            // Check for duplicates in current form
            if (exactWeightCategories[key]) {
                e.preventDefault();
                alert('You cannot add the same exact weight category with the same target weight twice.');
                hasDuplicate = true;
                return;
            }
            
            exactWeightCategories[key] = true;
        }
    });
    
    if (hasDuplicate) {
        return false;
    }
});

// Add first category on page load
window.addEventListener('DOMContentLoaded', function() {
    addPrizeCategory();
});
</script>

<?php include '../includes/footer.php'; ?>