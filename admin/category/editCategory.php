<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Category Management';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

$query = "
    SELECT c.*, 
           COUNT(DISTINCT tp.prize_id) as prize_count,
           COUNT(DISTINCT tp.tournament_id) as tournament_count
    FROM CATEGORY c
    LEFT JOIN TOURNAMENT_PRIZE tp ON c.category_id = tp.category_id
    GROUP BY c.category_id
    ORDER BY c.category_name ASC
";
$result = mysqli_query($conn, $query);

include '../includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="../tournament/tournamentList.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Tournaments
    </a>
</div>

<div class="section">
    <div class="section-header">
        <div>
            <h2 class="section-title">
                <i class="fas fa-layer-group"></i> Category Management
            </h2>
            <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.25rem;">
                Manage competition categories used across all tournaments
            </p>
        </div>
        <a href="addCategory.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Add Category
        </a>
    </div>

    <!-- Statistics -->
    <div class="dashboard-stats" style="margin-bottom: 0;">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6D94C5, #CBDCEB);">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-value"><?= mysqli_num_rows($result) ?></div>
            <div class="stat-label">Total Categories</div>
        </div>
    </div>
</div>

<!-- Categories List -->
<?php if (mysqli_num_rows($result) > 0): ?>
    <div class="section">
        <table class="table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Rankings</th>
                    <th>Description</th>
                    <th>Used In</th>
                    <th>Total Prizes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($category = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600; color: #1a1a1a;">
                            <i class="fas fa-trophy" style="color: var(--color-blue-primary); margin-right: 0.5rem;"></i>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            <?= $category['number_of_ranking'] ?> positions
                        </span>
                    </td>
                    <td>
                        <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #6c757d; font-size: 0.875rem;">
                            <?= htmlspecialchars($category['description']) ?>
                        </div>
                    </td>
                    <td>
                        <span style="font-weight: 600;"><?= $category['tournament_count'] ?></span> tournaments
                    </td>
                    <td>
                        <span style="font-weight: 600;"><?= $category['prize_count'] ?></span> prizes
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="editCategory.php?id=<?= $category['category_id'] ?>" 
                               class="btn btn-primary btn-sm" 
                               title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button"
                                    onclick="if(confirm('Delete this category? All prizes will be unlinked.')) { window.location.href='deleteCategory.php?id=<?= $category['category_id'] ?>'; }" 
                                    class="btn btn-danger btn-sm" 
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-layer-group"></i>
        <h3>No Categories Yet</h3>
        <p>Create your first competition category to start organizing prizes</p>
        <a href="addCategory.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create First Category
        </a>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>