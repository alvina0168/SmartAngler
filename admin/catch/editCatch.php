<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$catch_id = intval($_GET['id']);

// Get catch info
$catch_query = "
    SELECT fc.*, ws.station_name, ws.station_id,
           t.tournament_title, t.tournament_id, t.tournament_date
    FROM FISH_CATCH fc
    JOIN WEIGHING_STATION ws ON fc.station_id = ws.station_id
    JOIN TOURNAMENT t ON ws.tournament_id = t.tournament_id
    WHERE fc.catch_id = '$catch_id'
";
$catch_result = mysqli_query($conn, $catch_query);

if (!$catch_result || mysqli_num_rows($catch_result) == 0) {
    $_SESSION['error'] = 'Catch record not found';
    redirect(SITE_URL . '/admin/catch/selectTournament.php');
}

$catch = mysqli_fetch_assoc($catch_result);

$page_title = 'Edit Catch Record';
$page_description = 'Update fish catch information';

// Get approved participants for this tournament
$participants_query = "
    SELECT u.user_id, u.full_name
    FROM TOURNAMENT_REGISTRATION tr
    JOIN USER u ON tr.user_id = u.user_id
    WHERE tr.tournament_id = '{$catch['tournament_id']}'
    AND tr.approval_status = 'approved'
    ORDER BY u.user_id ASC
";
$participants_result = mysqli_query($conn, $participants_query);

// Build array for JS
$participants = [];
while ($p = mysqli_fetch_assoc($participants_result)) {
    $participants[] = [
        'id' => $p['user_id'],
        'angler_id' => 'A' . str_pad($p['user_id'], 3, '0', STR_PAD_LEFT),
        'name' => strtoupper($p['full_name'])
    ];
}

$error = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = sanitize($_POST['user_id']);
    $fish_species = sanitize($_POST['fish_species']);
    $fish_weight = sanitize($_POST['fish_weight']);
    $catch_time_only = sanitize($_POST['catch_time']);
    $notes = sanitize($_POST['notes'] ?? '');

    if (empty($user_id) || empty($fish_species) || empty($fish_weight) || empty($catch_time_only)) {
        $error = 'Please fill in all required fields';
    } elseif (!is_numeric($fish_weight) || $fish_weight <= 0) {
        $error = 'Fish weight must be a positive number';
    } else {
        $catch_datetime = $catch['tournament_date'] . ' ' . $catch_time_only . ':00';

        $update_query = "
            UPDATE FISH_CATCH SET
                user_id = '$user_id',
                fish_species = '$fish_species',
                fish_weight = '$fish_weight',
                catch_time = '$catch_datetime',
                notes = '$notes'
            WHERE catch_id = '$catch_id'
        ";

        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Catch record updated successfully!';
            redirect(SITE_URL . '/admin/catch/catchList.php?station_id=' . $catch['station_id']);
        } else {
            $error = 'Failed to update catch';
        }
    }
}

// Extract time
$time_only = date('H:i', strtotime($catch['catch_time']));

include '../includes/header.php';
?>

<!-- Back & Header -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
    <a href="<?php echo SITE_URL; ?>/admin/catch/catchList.php?station_id=<?php echo $catch['station_id']; ?>"
       class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <h2 style="margin:0; color:var(--color-blue-primary);">
        <i class="fas fa-edit"></i> Edit Catch Record
    </h2>
</div>

<p style="color:var(--color-gray-600); font-size:0.875rem; margin-bottom:1.5rem;">
    Tournament: <strong><?php echo htmlspecialchars($catch['tournament_title']); ?></strong> |
    Station: <strong><?php echo htmlspecialchars($catch['station_name']); ?></strong> |
    Catch ID: <strong>#<?php echo str_pad($catch_id, 4, '0', STR_PAD_LEFT); ?></strong>
</p>

<?php if ($error): ?>
<div class="alert alert-error" style="margin-bottom:1rem;">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo $error; ?>
</div>
<?php endif; ?>

<!-- Form Card -->
<div class="section" style="padding:1.5rem; background:#fff; border-radius:12px; box-shadow:var(--shadow-md);">
<form method="POST">

    <!-- Angler Search -->
    <div class="form-group-inline" style="position:relative; margin-bottom:1rem;">
        <label style="font-weight:600;">Angler ID <span style="color:red">*</span></label>
        <input type="text"
               id="angler_id"
               class="form-control"
               autocomplete="off"
               placeholder="Type A001, A002..."
               value="A<?php echo str_pad($catch['user_id'], 3, '0', STR_PAD_LEFT); ?>"
               required>

        <input type="hidden" name="user_id" id="user_id"
               value="<?php echo $catch['user_id']; ?>">

        <div id="angler_suggestions" class="angler-suggestions"></div>
        <small class="form-hint">Search approved anglers</small>
    </div>

    <div class="form-group">
        <label style="font-weight:600;">Fish Species <span style="color:red">*</span></label>
        <input type="text" name="fish_species" class="form-control"
               value="<?php echo htmlspecialchars($catch['fish_species']); ?>" required>
    </div>

    <div class="form-group">
        <label style="font-weight:600;">Fish Weight (KG) <span style="color:red">*</span></label>
        <input type="number" name="fish_weight" class="form-control"
               step="0.01" min="0.01"
               value="<?php echo $catch['fish_weight']; ?>" required>
    </div>

    <div class="form-group">
        <label style="font-weight:600;">Catch Time <span style="color:red">*</span></label>
        <input type="time" name="catch_time" class="form-control"
               value="<?php echo $time_only; ?>" required>
        <small class="form-hint">
            Tournament Date: <?php echo date('d M Y', strtotime($catch['tournament_date'])); ?>
        </small>
    </div>

    <div class="form-group">
        <label style="font-weight:600;">Notes</label>
        <textarea name="notes" class="form-control"><?php echo htmlspecialchars($catch['notes']); ?></textarea>
    </div>

    <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <a href="<?php echo SITE_URL; ?>/admin/catch/catchList.php?station_id=<?php echo $catch['station_id']; ?>"
           class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
        </button>
    </div>

</form>
</div>

<style>
.angler-suggestions{
    position:absolute;
    background:#fff;
    border:1px solid #ddd;
    width:100%;
    max-height:180px;
    overflow-y:auto;
    border-radius:6px;
    display:none;
    z-index:999;
}
.angler-suggestions div{
    padding:8px 12px;
    cursor:pointer;
}
.angler-suggestions div:hover{
    background:#f0f4ff;
}
</style>

<script>
const anglers = <?php echo json_encode($participants); ?>;
const input = document.getElementById('angler_id');
const hidden = document.getElementById('user_id');
const box = document.getElementById('angler_suggestions');

input.addEventListener('input', () => {
    const val = input.value.toUpperCase();
    box.innerHTML = '';
    box.style.display = 'none';

    if (!val) return;

    anglers.filter(a =>
        a.angler_id.includes(val) || a.name.includes(val)
    ).forEach(a => {
        const d = document.createElement('div');
        d.textContent = `${a.angler_id} - ${a.name}`;
        d.onclick = () => {
            input.value = a.angler_id;
            hidden.value = a.id;
            box.style.display = 'none';
        };
        box.appendChild(d);
    });

    if (box.children.length) box.style.display = 'block';
});

document.addEventListener('click', e => {
    if (!e.target.closest('.form-group-inline')) {
        box.style.display = 'none';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
