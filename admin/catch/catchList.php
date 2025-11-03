<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['station_id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$station_id = intval($_GET['station_id']);

// Get station and tournament info
$station_query = "
    SELECT ws.*, t.tournament_title, t.tournament_id, t.tournament_date, t.status as tournament_status
    FROM WEIGHING_STATION ws
    JOIN TOURNAMENT t ON ws.tournament_id = t.tournament_id
    WHERE ws.station_id = '$station_id'
";
$station_result = mysqli_query($conn, $station_query);

if (!$station_result || mysqli_num_rows($station_result) == 0) {
    $_SESSION['error'] = 'Station not found';
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$station = mysqli_fetch_assoc($station_result);

$page_title = 'Fish Catch Record - ' . $station['station_name'];
$page_description = 'Manage fish catch records for this station';

// Get registered AND APPROVED participants for THIS TOURNAMENT ONLY
$participants_query = "
    SELECT DISTINCT u.user_id, u.full_name
    FROM TOURNAMENT_REGISTRATION tr
    JOIN USER u ON tr.user_id = u.user_id
    WHERE tr.tournament_id = '{$station['tournament_id']}'
    AND tr.approval_status = 'approved'
    ORDER BY u.user_id ASC
";
$participants_result = mysqli_query($conn, $participants_query);

// DEBUG: Count participants
$participant_count = mysqli_num_rows($participants_result);

// Build participants array for JavaScript
$participants_array = [];
while ($participant = mysqli_fetch_assoc($participants_result)) {
    $participants_array[] = [
        'id' => $participant['user_id'],
        'angler_id' => 'A' . str_pad($participant['user_id'], 3, '0', STR_PAD_LEFT),
        'name' => strtoupper($participant['full_name'])
    ];
}

$error = '';
$success = '';

// Handle form submission (Insert Catch)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'insert') {
    $angler_id_input = sanitize($_POST['angler_id']);
    $fish_species = sanitize($_POST['fish_species']);
    $fish_weight = sanitize($_POST['fish_weight']);
    $catch_time_only = sanitize($_POST['catch_time']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Extract user_id from angler_id (remove 'A' prefix and leading zeros)
    $user_id = intval(str_replace('A', '', $angler_id_input));
    
    if (empty($angler_id_input) || empty($fish_species) || empty($fish_weight) || empty($catch_time_only)) {
        $error = 'Please fill in all required fields';
    } elseif (!is_numeric($fish_weight) || $fish_weight <= 0) {
        $error = 'Fish weight must be a positive number';
    } elseif ($user_id <= 0) {
        $error = 'Invalid Angler ID';
    } else {
        // Verify user exists and is approved for this tournament
        $verify_query = "
            SELECT u.user_id 
            FROM USER u
            JOIN TOURNAMENT_REGISTRATION tr ON u.user_id = tr.user_id
            WHERE u.user_id = '$user_id'
            AND tr.tournament_id = '{$station['tournament_id']}'
            AND tr.approval_status = 'approved'
        ";
        $verify_result = mysqli_query($conn, $verify_query);
        
        if (mysqli_num_rows($verify_result) == 0) {
            $error = 'Angler ID not found or not approved for this tournament';
        } else {
            // Combine tournament date with catch time
            $catch_datetime = $station['tournament_date'] . ' ' . $catch_time_only . ':00';
            
            $insert_query = "
                INSERT INTO FISH_CATCH (station_id, user_id, fish_species, fish_weight, catch_time, notes, created_at)
                VALUES ('$station_id', '$user_id', '$fish_species', '$fish_weight', '$catch_datetime', '$notes', NOW())
            ";
            
            if (mysqli_query($conn, $insert_query)) {
                $success = 'Fish catch recorded successfully!';
                // Reload page to show new catch
                header("Location: " . $_SERVER['PHP_SELF'] . "?station_id=" . $station_id);
                exit;
            } else {
                $error = 'Failed to record catch: ' . mysqli_error($conn);
            }
        }
    }
}

// Get all catches for this station
$catches_query = "
    SELECT fc.*, u.full_name as participant_name
    FROM FISH_CATCH fc
    LEFT JOIN USER u ON fc.user_id = u.user_id
    WHERE fc.station_id = '$station_id'
    ORDER BY fc.catch_time DESC
";
$catches_result = mysqli_query($conn, $catches_query);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_catches,
        COALESCE(SUM(fish_weight), 0) as total_weight,
        COALESCE(MAX(fish_weight), 0) as max_weight,
        COALESCE(AVG(fish_weight), 0) as avg_weight
    FROM FISH_CATCH
    WHERE station_id = '$station_id'
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include '../includes/header.php';
?>

<style>
/* Inline Form Styling */
.catch-form-container {
    background: var(--color-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
}

.catch-form-header {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--color-gray-800);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.catch-form-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.catch-form-notes {
    grid-column: 1 / -1;
}

.form-group-inline {
    display: flex;
    flex-direction: column;
    position: relative;
}

.form-group-inline label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-gray-700);
    margin-bottom: 0.375rem;
}

.form-group-inline input,
.form-group-inline textarea {
    padding: 0.625rem;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-md);
    font-size: 0.875rem;
}

.form-group-inline input:focus,
.form-group-inline textarea:focus {
    outline: none;
    border-color: var(--color-blue-primary);
    box-shadow: 0 0 0 3px rgba(109, 148, 197, 0.1);
}

/* Autocomplete Dropdown */
.angler-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid var(--color-gray-300);
    border-top: none;
    border-radius: 0 0 var(--radius-md) var(--radius-md);
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    margin-top: -1px;
}

.angler-suggestions.show {
    display: block;
}

.suggestion-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid var(--color-gray-100);
    transition: background 0.15s;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.suggestion-item:hover {
    background: #e3f2fd;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-angler-id {
    font-weight: 700;
    color: var(--color-blue-primary);
    font-size: 0.875rem;
    min-width: 50px;
}

.suggestion-name {
    font-size: 0.875rem;
    color: var(--color-gray-700);
    font-weight: 500;
}

.suggestion-separator {
    color: var(--color-gray-400);
    font-weight: 400;
}

.no-results {
    padding: 1rem;
    text-align: center;
    color: var(--color-gray-500);
    font-size: 0.875rem;
}

.debug-info {
    background: #fff3cd;
    border: 1px solid #ffc107;
    padding: 1rem;
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

.btn-reset {
    padding: 0.625rem 1.5rem;
    background: #f97316;
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-reset:hover {
    background: #ea580c;
}

.btn-insert {
    padding: 0.625rem 1.5rem;
    background: #22c55e;
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-insert:hover {
    background: #16a34a;
}
</style>

<!-- Back Button -->
<div class="text-right mb-3">
    <a href="stationList.php?tournament_id=<?php echo $station['tournament_id']; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Stations
    </a>
</div>

<!-- Station Info -->
<div class="section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
            <h2 style="color: var(--color-blue-primary); font-size: 1.5rem; margin-bottom: 0.5rem;">
                Station ID: <?php echo htmlspecialchars($station['station_name']); ?>
            </h2>
            <p style="color: var(--color-gray-600); font-size: 0.875rem; margin: 0;">
                <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($station['tournament_title']); ?>
                (Tournament ID: <?php echo $station['tournament_id']; ?>)
                <?php if (!empty($station['marshal_name'])): ?>
                    | <i class="fas fa-user"></i> Marshal: <?php echo htmlspecialchars($station['marshal_name']); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<!-- Inline Catch Form -->
<div class="catch-form-container">
    <div class="catch-form-header">
        <i class="fas fa-fish"></i>
        Record Fish Catch
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="catchForm">
        <input type="hidden" name="action" value="insert">
        
        <div class="catch-form-grid">
            <!-- Angler ID with Search -->
            <div class="form-group-inline">
                <label>Angler ID <span style="color: red;">*</span></label>
                <input type="text" 
                       name="angler_id" 
                       id="angler_id" 
                       placeholder="Type A001, A002..." 
                       autocomplete="off"
                       required>
                <div id="angler_suggestions" class="angler-suggestions"></div>
            </div>

            <!-- Fish Weight -->
            <div class="form-group-inline">
                <label>Fish Weight (KG) <span style="color: red;">*</span></label>
                <input type="number" name="fish_weight" id="fish_weight" step="0.001" min="0.001" placeholder="0.000" required>
            </div>

            <!-- Fish Species -->
            <div class="form-group-inline">
                <label>Fish Species <span style="color: red;">*</span></label>
                <input type="text" name="fish_species" id="fish_species" placeholder="e.g., KELI" required>
            </div>

            <!-- Catch Time -->
            <div class="form-group-inline">
                <label>Catch Time <span style="color: red;">*</span></label>
                <input type="time" name="catch_time" id="catch_time" value="<?php echo date('H:i'); ?>" required>
            </div>

            <!-- Notes -->
            <div class="form-group-inline catch-form-notes">
                <label>Notes</label>
                <textarea name="notes" id="notes" rows="2" placeholder="Optional notes..."></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn-reset" onclick="resetForm()">
                Reset
            </button>
            <button type="submit" class="btn-insert">
                Insert
            </button>
        </div>
    </form>
</div>

<!-- Statistics -->
<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo $stats['total_catches']; ?></div>
                <div class="stat-label">Total Catches</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-fish"></i>
            </div>
        </div>
    </div>

    <div class="stat-card success">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo number_format($stats['total_weight'], 2); ?> KG</div>
                <div class="stat-label">Total Weight</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-weight-hanging"></i>
            </div>
        </div>
    </div>

    <div class="stat-card warning">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo number_format($stats['max_weight'], 2); ?> KG</div>
                <div class="stat-label">Biggest Catch</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-trophy"></i>
            </div>
        </div>
    </div>

    <div class="stat-card info">
        <div class="stat-header">
            <div>
                <div class="stat-value"><?php echo number_format($stats['avg_weight'], 2); ?> KG</div>
                <div class="stat-label">Average Weight</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>
</div>

<!-- Catches Table -->
<?php if (mysqli_num_rows($catches_result) > 0): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Fish Record List
            </h2>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Angler ID</th>
                    <th>Name</th>
                    <th>Fish Weight</th>
                    <th>Catch Time</th>
                    <th>Fish Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                while ($catch = mysqli_fetch_assoc($catches_result)): 
                ?>
                    <tr>
                        <!-- No -->
                        <td style="text-align: center;">
                            <strong><?php echo $no++; ?></strong>
                        </td>

                        <!-- Angler ID -->
                        <td style="text-align: center;">
                            <strong style="color: var(--color-blue-primary);">
                                A<?php echo str_pad($catch['user_id'], 3, '0', STR_PAD_LEFT); ?>
                            </strong>
                        </td>

                        <!-- Name -->
                        <td>
                            <?php if (!empty($catch['participant_name'])): ?>
                                <div style="font-weight: 600; color: var(--color-gray-800); text-transform: uppercase;">
                                    <?php echo htmlspecialchars($catch['participant_name']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--color-gray-400); font-style: italic;">Unknown</span>
                            <?php endif; ?>
                        </td>

                        <!-- Weight -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: #e8f5e9; color: #2e7d32; border-radius: var(--radius-sm); font-weight: 700; font-size: 1rem;">
                                <?php echo number_format($catch['fish_weight'], 3); ?>
                            </div>
                        </td>

                        <!-- Catch Time -->
                        <td style="text-align: center;">
                            <div style="font-weight: 600; color: var(--color-gray-800);">
                                <?php echo date('h:i A', strtotime($catch['catch_time'])); ?>
                            </div>
                        </td>

                        <!-- Species -->
                        <td style="text-align: center;">
                            <div style="font-weight: 600; color: var(--color-gray-800); text-transform: uppercase;">
                                <?php echo htmlspecialchars($catch['fish_species']); ?>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td>
                            <div class="action-btns">
                                <a href="editCatch.php?id=<?php echo $catch['catch_id']; ?>" 
                                   class="btn btn-success btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteCatch(<?php echo $catch['catch_id']; ?>)" 
                                        class="btn btn-danger btn-sm" title="Delete">
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
        <i class="fas fa-fish"></i>
        <h3>No Catch Records</h3>
        <p>Use the form above to record your first catch</p>
    </div>
<?php endif; ?>

<script>
// Participants data from PHP
const participants = <?php echo json_encode($participants_array); ?>;

console.log('Participants loaded:', participants);
console.log('Total participants:', participants.length);

const anglerInput = document.getElementById('angler_id');
const suggestionsDiv = document.getElementById('angler_suggestions');

// Handle angler ID input
anglerInput.addEventListener('input', function() {
    const value = this.value.trim();
    
    console.log('Input value:', value);
    
    // Clear suggestions if input is empty
    if (value.length === 0) {
        suggestionsDiv.classList.remove('show');
        suggestionsDiv.innerHTML = '';
        return;
    }
    
    // Check if we have participants
    if (participants.length === 0) {
        suggestionsDiv.innerHTML = '<div class="no-results">⚠️ No approved participants for this tournament</div>';
        suggestionsDiv.classList.add('show');
        return;
    }
    
    // Filter participants - search by ID or name
    const searchValue = value.toUpperCase();
    const filtered = participants.filter(p => {
        const matchesId = p.angler_id.toUpperCase().includes(searchValue);
        const matchesName = p.name.toUpperCase().includes(searchValue);
        return matchesId || matchesName;
    });
    
    console.log('Filtered results:', filtered);
    
    // Show suggestions
    if (filtered.length > 0) {
        suggestionsDiv.innerHTML = '';
        
        filtered.forEach(p => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.innerHTML = `
                <span class="suggestion-angler-id">${p.angler_id}</span>
                <span class="suggestion-separator">-</span>
                <span class="suggestion-name">${p.name}</span>
            `;
            item.addEventListener('click', function() {
                selectAngler(p);
            });
            suggestionsDiv.appendChild(item);
        });
        
        suggestionsDiv.classList.add('show');
    } else {
        // Show "No results"
        suggestionsDiv.innerHTML = '<div class="no-results">No matching anglers found</div>';
        suggestionsDiv.classList.add('show');
    }
});

// Select angler from suggestions
function selectAngler(participant) {
    console.log('Selected:', participant);
    anglerInput.value = participant.angler_id;
    suggestionsDiv.classList.remove('show');
    suggestionsDiv.innerHTML = '';
    
    // Focus on next field
    document.getElementById('fish_weight').focus();
}

// Close suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!anglerInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
        suggestionsDiv.classList.remove('show');
    }
});

// Handle keyboard navigation
anglerInput.addEventListener('keydown', function(e) {
    const items = suggestionsDiv.querySelectorAll('.suggestion-item');
    const activeItem = suggestionsDiv.querySelector('.suggestion-item.active');
    let index = Array.from(items).indexOf(activeItem);
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (activeItem) activeItem.classList.remove('active');
        index = (index + 1) % items.length;
        if (items[index]) items[index].classList.add('active');
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (activeItem) activeItem.classList.remove('active');
        index = (index - 1 + items.length) % items.length;
        if (items[index]) items[index].classList.add('active');
    } else if (e.key === 'Enter' && activeItem) {
        e.preventDefault();
        activeItem.click();
    } else if (e.key === 'Escape') {
        suggestionsDiv.classList.remove('show');
    }
});

// Reset form
function resetForm() {
    document.getElementById('catchForm').reset();
    document.getElementById('catch_time').value = '<?php echo date('H:i'); ?>';
    suggestionsDiv.classList.remove('show');
    suggestionsDiv.innerHTML = '';
    anglerInput.focus();
}

// Delete catch
function deleteCatch(id) {
    if (confirm('Are you sure you want to delete this catch record?')) {
        window.location.href = 'deleteCatch.php?id=' + id + '&station_id=<?php echo $station_id; ?>';
    }
}

// Focus on angler ID on page load
window.addEventListener('load', function() {
    anglerInput.focus();
});
</script>

<?php include '../includes/footer.php'; ?>