<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Add Prizes';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['tournament_id'])) {
    $_SESSION['error'] = 'Tournament ID is missing!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$tournament_id = intval($_GET['tournament_id']);

// Fetch tournament
$tournament_q = mysqli_query($conn, "
    SELECT tournament_title 
    FROM TOURNAMENT 
    WHERE tournament_id = $tournament_id
");
$tournament = mysqli_fetch_assoc($tournament_q);

// Fetch categories
$categories_q = mysqli_query($conn, "
    SELECT category_id, category_name
    FROM CATEGORY
    ORDER BY category_name ASC
");

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($_POST['categories'] as $catIndex => $catData) {

        if (empty($catData['category_id'])) continue;

        $category_id = intval($catData['category_id']);
        $number_of_ranks = intval($catData['number_of_ranks']);

        for ($i = 0; $i < $number_of_ranks; $i++) {

            $prize = $catData['prizes'][$i] ?? null;
            if (!$prize || empty($prize['description']) || empty($prize['value'])) continue;

            $description = mysqli_real_escape_string($conn, trim($prize['description']));
            $value = floatval($prize['value']);
            $prize_ranking = $i + 1; // Admin-defined rank order

            mysqli_query($conn, "
                INSERT INTO TOURNAMENT_PRIZE
                (tournament_id, category_id, prize_ranking, prize_description, prize_value)
                VALUES
                ($tournament_id, $category_id, '$prize_ranking', '$description', $value)
            ");
        }
    }

    $_SESSION['success'] = 'Prizes added successfully!';
    redirect(SITE_URL . "/admin/prize/managePrize.php?tournament_id=$tournament_id");
}

include '../includes/header.php';
?>

<a href="managePrize.php?tournament_id=<?= $tournament_id ?>" class="btn btn-secondary mb-3">
    <i class="fas fa-arrow-left"></i> Back
</a>

<div class="section">
    <h3 class="section-title"><i class="fas fa-trophy"></i> Prize Configuration</h3>
    <p><?= htmlspecialchars($tournament['tournament_title']) ?></p>

    <form method="POST">

        <div id="prizeCategories"></div>

        <button type="button" class="btn btn-outline-primary mt-2" onclick="addPrizeCategory()">
            + Add Prize Category
        </button>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save Prizes
            </button>
        </div>
    </form>
</div>

<script>
let categoryIndex = 0;

function addPrizeCategory() {
    const container = document.getElementById('prizeCategories');
    const card = document.createElement('div');
    card.className = 'card mb-4';
    card.dataset.index = categoryIndex;

    card.innerHTML = `
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Prize Category ${categoryIndex + 1}</strong>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.card').remove()">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Select Category *</label>
                    <select name="categories[${categoryIndex}][category_id]" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php
                        mysqli_data_seek($categories_q, 0);
                        while ($c = mysqli_fetch_assoc($categories_q)):
                        ?>
                            <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Number of Winners / Ranks *</label>
                    <input type="number" name="categories[${categoryIndex}][number_of_ranks]" class="form-control" min="1" value="1" oninput="renderPrizeRows(this)" required>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-primary">
                        <tr>
                            <th width="120">Rank</th>
                            <th>Prize Description</th>
                            <th width="160">Value (RM)</th>
                        </tr>
                    </thead>
                    <tbody class="prize-rows"></tbody>
                </table>
            </div>
        </div>
    `;
    container.appendChild(card);
    categoryIndex++;
}

function renderPrizeRows(input) {
    const card = input.closest('.card');
    const tbody = card.querySelector('.prize-rows');
    const index = card.dataset.index;
    const count = parseInt(input.value) || 0;
    tbody.innerHTML = '';

    for (let i = 0; i < count; i++) {
        tbody.innerHTML += `
            <tr>
                <td><strong>Rank ${i+1}</strong></td>
                <td>
                    <input type="text" name="categories[${index}][prizes][${i}][description]" class="form-control" placeholder="Prize description" required>
                </td>
                <td>
                    <input type="number" name="categories[${index}][prizes][${i}][value]" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </td>
            </tr>
        `;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
