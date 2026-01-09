<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$page_title = 'Create Tournament';
$page_description = 'Add a new fishing tournament';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['final_submit'])) {
    $user_id = $_SESSION['user_id'];

    // SANITIZE INPUTS
    $tournament_title = sanitize($_POST['tournament_title']);
    $tournament_date = sanitize($_POST['tournament_date']);
    $location = sanitize($_POST['location']);
    $description = sanitize($_POST['description']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $tournament_fee = sanitize($_POST['tournament_fee']);
    $max_participants = sanitize($_POST['max_participants']);
    $status = sanitize($_POST['status'] ?? 'upcoming');

    $zone_id = sanitize($_POST['zone_id'] ?? '');
    $bank_account_name = sanitize($_POST['bank_account_name'] ?? '');
    $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
    $bank_account_holder = sanitize($_POST['bank_account_holder'] ?? '');

    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $image = uploadFile($_FILES['image'], 'tournaments');
        if (!$image) {
            $error = 'Failed to upload image.';
        }
    }

    $bank_qr = '';
    if (!empty($_FILES['bank_qr']['name'])) {
        $bank_qr = uploadFile($_FILES['bank_qr'], 'qrcodes');
        if (!$bank_qr) {
            $error = 'Failed to upload QR code.';
        }
    }

    if (empty($tournament_title) || empty($tournament_date) || empty($location)) {
        $error = 'Please fill in all required fields.';
    }

    if (empty($error)) {
        // Insert tournament
        $query = "
            INSERT INTO TOURNAMENT 
            (user_id, tournament_title, tournament_date, location, description, start_time, end_time,
             tournament_fee, max_participants, image, status, created_by,
             bank_account_name, bank_account_number, bank_account_holder, bank_qr, created_at)
            VALUES
            ('$user_id', '$tournament_title', '$tournament_date', '$location', '$description',
             '$start_time', '$end_time', '$tournament_fee', '$max_participants', '$image', '$status',
             '$user_id', '$bank_account_name', '$bank_account_number', '$bank_account_holder', '$bank_qr', NOW())
        ";

        if (mysqli_query($conn, $query)) {
            $tournament_id = mysqli_insert_id($conn);

            if (!empty($zone_id)) {
                mysqli_query($conn, "UPDATE ZONE SET tournament_id='$tournament_id' WHERE zone_id='$zone_id'");
            }

            // Insert calendar event
            mysqli_query(
                $conn,
                "INSERT INTO CALENDAR (tournament_id, event_date, event_title)
                 VALUES ('$tournament_id', '$tournament_date', '$tournament_title')"
            );

            // Insert sponsors
            if (!empty($_POST['sponsors'])) {
                foreach ($_POST['sponsors'] as $sponsor) {
                    if (!empty($sponsor['name'])) {
                        $sponsor_name = sanitize($sponsor['name']);
                        $sponsor_amount = floatval($sponsor['amount'] ?? 0);
                        $sponsor_desc = sanitize($sponsor['description'] ?? '');
                        
                        mysqli_query($conn, "
                            INSERT INTO SPONSOR (tournament_id, sponsor_name, sponsored_amount, sponsor_description)
                            VALUES ('$tournament_id', '$sponsor_name', '$sponsor_amount', '$sponsor_desc')
                        ");
                    }
                }
            }

            // Insert categories and prizes
            if (!empty($_POST['categories'])) {
                foreach ($_POST['categories'] as $cat_index => $category) {
                    if (!empty($category['name'])) {
                        $cat_name = sanitize($category['name']);
                        $cat_rankings = intval($category['rankings'] ?? 3);
                        $cat_desc = sanitize($category['description'] ?? '');
                        
                        // Insert category for this tournament
                        $cat_query = "
                            INSERT INTO CATEGORY (category_name, number_of_ranking, description)
                            VALUES ('$cat_name', '$cat_rankings', '$cat_desc')
                        ";
                        mysqli_query($conn, $cat_query);
                        $category_id = mysqli_insert_id($conn);
                        
                        // Insert prizes for this category
                        if (!empty($_POST['prizes'][$cat_index])) {
                            foreach ($_POST['prizes'][$cat_index] as $prize) {
                                if (!empty($prize['ranking']) && !empty($prize['description'])) {
                                    $prize_rank = sanitize($prize['ranking']);
                                    $prize_desc = sanitize($prize['description']);
                                    $prize_value = floatval($prize['value'] ?? 0);
                                    
                                    mysqli_query($conn, "
                                        INSERT INTO TOURNAMENT_PRIZE (tournament_id, category_id, prize_ranking, prize_description, prize_value)
                                        VALUES ('$tournament_id', '$category_id', '$prize_rank', '$prize_desc', '$prize_value')
                                    ");
                                }
                            }
                        }
                    }
                }
            }

            $_SESSION['success'] = 'Tournament created successfully with prizes and sponsors!';
            redirect(SITE_URL . '/admin/tournament/tournamentList.php');
        } else {
            $error = mysqli_error($conn);
        }
    }
}

include '../includes/header.php';
?>

<style>
.form-step { display: none; }
.form-step-active { display: block !important; }
.input-error { border-color: #dc3545 !important; }

.dynamic-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

.dynamic-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.25rem;
}

.dynamic-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #dee2e6;
}

.prize-sub-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 0.75rem;
}

.step-indicator {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 2rem;
}

.step-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #dee2e6;
    transition: all 0.3s ease;
}

.step-dot.active {
    background: var(--color-blue-primary);
    transform: scale(1.3);
}
</style>

<!-- Back Button -->
<div style="margin-bottom: 1.5rem;">
    <a href="tournamentList.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Tournaments
    </a>
</div>

<div class="section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-plus-circle"></i> Create New Tournament
        </h3>
    </div>

    <!-- Step Indicator -->
    <div class="step-indicator">
        <span class="step-dot active" data-step="0"></span>
        <span class="step-dot" data-step="1"></span>
        <span class="step-dot" data-step="2"></span>
        <span class="step-dot" data-step="3"></span>
        <span class="step-dot" data-step="4"></span>
        <span class="step-dot" data-step="5"></span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form id="tournamentForm" method="POST" enctype="multipart/form-data">

        <!-- STEP 1: Basic Info -->
        <div class="form-step form-step-active">
            <h4 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--color-blue-primary);">
                <i class="fas fa-info-circle"></i> Step 1: Tournament Information
            </h4>
            
            <div class="form-group">
                <label>Tournament Title <span class="required">*</span></label>
                <input type="text" name="tournament_title" class="form-control" required>
            </div>

            <div class="info-grid">
                <div class="form-group">
                    <label>Tournament Date <span class="required">*</span></label>
                    <input type="date" name="tournament_date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Location <span class="required">*</span></label>
                    <input type="text" name="location" class="form-control" placeholder="e.g., Kota Kinabalu Jetty" required>
                </div>
            </div>

            <div class="info-grid">
                <div class="form-group">
                    <label>Start Time <span class="required">*</span></label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>End Time <span class="required">*</span></label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Describe the tournament..."></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-primary btn-next">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 2: Payment Info -->
        <div class="form-step">
            <h4 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--color-blue-primary);">
                <i class="fas fa-credit-card"></i> Step 2: Payment Information
            </h4>
            
            <div class="form-group">
                <label>Bank Name <span class="required">*</span></label>
                <input type="text" name="bank_account_name" class="form-control" placeholder="e.g., Maybank, CIMB" required>
            </div>

            <div class="info-grid">
                <div class="form-group">
                    <label>Account Number <span class="required">*</span></label>
                    <input type="text" name="bank_account_number" class="form-control" placeholder="1234567890" required>
                </div>

                <div class="form-group">
                    <label>Account Holder <span class="required">*</span></label>
                    <input type="text" name="bank_account_holder" class="form-control" placeholder="Account holder name" required>
                </div>
            </div>

            <div class="form-group">
                <label>Payment QR Code</label>
                <input type="file" name="bank_qr" class="form-control" accept="image/*">
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-prev">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-primary btn-next">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 3: Tournament Details -->
        <div class="form-step">
            <h4 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--color-blue-primary);">
                <i class="fas fa-cog"></i> Step 3: Tournament Details
            </h4>
            
            <div class="info-grid">
                <div class="form-group">
                    <label>Tournament Fee (RM) <span class="required">*</span></label>
                    <input type="number" step="0.01" name="tournament_fee" class="form-control" value="0.00" required>
                </div>

                <div class="form-group">
                    <label>Max Participants <span class="required">*</span></label>
                    <input type="number" name="max_participants" class="form-control" value="50" required>
                </div>
            </div>

            <div class="form-group">
                <label>Fishing Zone <span class="required">*</span></label>
                <select name="zone_id" class="form-control" required>
                    <option value="">-- Select Zone --</option>
                    <?php
                    $zones = mysqli_query($conn, "SELECT zone_id, zone_name FROM ZONE");
                    while ($z = mysqli_fetch_assoc($zones)):
                    ?>
                        <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tournament Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-prev">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-primary btn-next">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 4: Sponsors -->
        <div class="form-step">
            <h4 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; color: var(--color-blue-primary);">
                <i class="fas fa-handshake"></i> Step 4: Sponsors (Optional)
            </h4>
            <p style="color: #6c757d; font-size: 0.875rem; margin-bottom: 1.5rem;">
                Add sponsors who are supporting this tournament
            </p>

            <div id="sponsorsList" class="dynamic-list"></div>

            <button type="button" class="btn btn-add" onclick="addSponsor()">
                <i class="fas fa-plus"></i> Add Sponsor
            </button>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-prev">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-primary btn-next">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 5: Categories & Prizes -->
        <div class="form-step">
            <h4 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; color: var(--color-blue-primary);">
                <i class="fas fa-trophy"></i> Step 5: Categories & Prizes
            </h4>
            <p style="color: #6c757d; font-size: 0.875rem; margin-bottom: 1.5rem;">
                Define competition categories and prizes for this tournament
            </p>

            <div id="categoriesList" class="dynamic-list"></div>

            <button type="button" class="btn btn-add" onclick="addCategory()">
                <i class="fas fa-plus"></i> Add Category
            </button>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-prev">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-primary btn-next">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 6: Review & Submit -->
        <div class="form-step">
            <h4 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--color-blue-primary);">
                <i class="fas fa-check-circle"></i> Step 6: Review & Submit
            </h4>
            
            <div style="padding: 1.5rem; background: #e8f5e9; border-radius: 12px; border-left: 4px solid #4caf50; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: start; gap: 1rem;">
                    <i class="fas fa-check-circle" style="color: #4caf50; font-size: 1.5rem; margin-top: 0.25rem;"></i>
                    <div>
                        <strong style="color: #2e7d32; font-size: 1rem; display: block; margin-bottom: 0.5rem;">
                            Ready to Create Tournament?
                        </strong>
                        <p style="color: #388e3c; font-size: 0.875rem; margin: 0; line-height: 1.6;">
                            Review your information and click "Create Tournament" to finalize. You can edit all details after creation.
                        </p>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-prev">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" name="final_submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Create Tournament
                </button>
            </div>
        </div>

    </form>
</div>

<script>
// Multi-step navigation
const nextBtns = document.querySelectorAll('.btn-next');
const prevBtns = document.querySelectorAll('.btn-prev');
const formSteps = document.querySelectorAll('.form-step');
const stepDots = document.querySelectorAll('.step-dot');
let currentStep = 0;

function updateStepIndicator() {
    stepDots.forEach((dot, index) => {
        if (index === currentStep) {
            dot.classList.add('active');
        } else {
            dot.classList.remove('active');
        }
    });
}

nextBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const step = formSteps[currentStep];
        const requiredFields = step.querySelectorAll('[required]');
        let allValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                allValid = false;
                field.classList.add('input-error');
            } else {
                field.classList.remove('input-error');
            }
        });

        if (!allValid) {
            alert('Please fill in all required fields before proceeding.');
            return;
        }

        step.classList.remove('form-step-active');
        currentStep++;
        formSteps[currentStep].classList.add('form-step-active');
        updateStepIndicator();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

prevBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        formSteps[currentStep].classList.remove('form-step-active');
        currentStep--;
        formSteps[currentStep].classList.add('form-step-active');
        updateStepIndicator();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

// Sponsor management
let sponsorIndex = 0;

function addSponsor() {
    const html = `
        <div class="dynamic-item" id="sponsor-${sponsorIndex}">
            <div class="dynamic-item-header">
                <span style="font-weight: 600; color: #1a1a1a;">
                    <i class="fas fa-building"></i> Sponsor ${sponsorIndex + 1}
                </span>
                <button type="button" class="btn-remove" onclick="removeSponsor(${sponsorIndex})">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
            <div class="info-grid">
                <div class="form-group">
                    <label>Sponsor Name</label>
                    <input type="text" name="sponsors[${sponsorIndex}][name]" class="form-control" placeholder="e.g., ABC Bank">
                </div>
                <div class="form-group">
                    <label>Sponsored Amount (RM)</label>
                    <input type="number" step="0.01" name="sponsors[${sponsorIndex}][amount]" class="form-control" value="0.00">
                </div>
            </div>
            <div class="form-group">
                <label>Description (Optional)</label>
                <textarea name="sponsors[${sponsorIndex}][description]" class="form-control" rows="2"></textarea>
            </div>
        </div>
    `;
    document.getElementById('sponsorsList').insertAdjacentHTML('beforeend', html);
    sponsorIndex++;
}

function removeSponsor(index) {
    document.getElementById('sponsor-' + index).remove();
}

// Category and Prize management
let categoryIndex = 0;
let prizeIndexes = {};

function addCategory() {
    prizeIndexes[categoryIndex] = 0;
    
    const html = `
        <div class="dynamic-item" id="category-${categoryIndex}">
            <div class="dynamic-item-header">
                <span style="font-weight: 600; color: #1a1a1a;">
                    <i class="fas fa-layer-group"></i> Category ${categoryIndex + 1}
                </span>
                <button type="button" class="btn-remove" onclick="removeCategory(${categoryIndex})">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
            <div class="info-grid">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="categories[${categoryIndex}][name]" class="form-control" placeholder="e.g., Biggest Fish">
                </div>
                <div class="form-group">
                    <label>Number of Rankings</label>
                    <input type="number" name="categories[${categoryIndex}][rankings]" class="form-control" value="3" min="1" max="10">
                </div>
            </div>
            <div class="form-group">
                <label>Description (Optional)</label>
                <textarea name="categories[${categoryIndex}][description]" class="form-control" rows="2"></textarea>
            </div>
            
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <span style="font-weight: 600; color: #495057; font-size: 0.9375rem;">
                        <i class="fas fa-gift"></i> Prizes
                    </span>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addPrize(${categoryIndex})">
                        <i class="fas fa-plus"></i> Add Prize
                    </button>
                </div>
                <div id="prizes-${categoryIndex}"></div>
            </div>
        </div>
    `;
    document.getElementById('categoriesList').insertAdjacentHTML('beforeend', html);
    categoryIndex++;
}

function removeCategory(index) {
    document.getElementById('category-' + index).remove();
}

function addPrize(catIndex) {
    const prizeIndex = prizeIndexes[catIndex];
    
    const html = `
        <div class="prize-sub-item" id="prize-${catIndex}-${prizeIndex}">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-weight: 600; color: #495057; font-size: 0.875rem;">Prize ${prizeIndex + 1}</span>
                <button type="button" class="btn-remove" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="removePrize(${catIndex}, ${prizeIndex})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="info-grid">
                <div class="form-group">
                    <label>Ranking</label>
                    <select name="prizes[${catIndex}][${prizeIndex}][ranking]" class="form-control">
                        <option value="1st">1st Place 🥇</option>
                        <option value="2nd">2nd Place 🥈</option>
                        <option value="3rd">3rd Place 🥉</option>
                        <option value="4th">4th Place</option>
                        <option value="5th">5th Place</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Value (RM)</label>
                    <input type="number" step="0.01" name="prizes[${catIndex}][${prizeIndex}][value]" class="form-control" value="0.00">
                </div>
            </div>
            <div class="form-group">
                <label>Prize Description</label>
                <input type="text" name="prizes[${catIndex}][${prizeIndex}][description]" class="form-control" placeholder="e.g., Cash RM 5,000 + Trophy">
            </div>
        </div>
    `;
    document.getElementById('prizes-' + catIndex).insertAdjacentHTML('beforeend', html);
    prizeIndexes[catIndex]++;
}

function removePrize(catIndex, prizeIndex) {
    document.getElementById('prize-' + catIndex + '-' + prizeIndex).remove();
}
</script>

<?php include '../includes/footer.php'; ?>