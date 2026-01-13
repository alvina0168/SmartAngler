<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Create Tournament';
$page_description = 'Add a new fishing tournament';

$error = '';

// Handle Final Submit
// Handle Final Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['final_submit'])) {
    $user_id = $_SESSION['user_id'];

    // SANITIZE INPUTS
    $tournament_title = sanitize($_POST['tournament_title']);
    $tournament_date = sanitize($_POST['tournament_date']);
    $location = sanitize($_POST['location']);
    $description = sanitize($_POST['description']);
    $tournament_rules = sanitize($_POST['tournament_rules'] ?? '');
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $tournament_fee = sanitize($_POST['tournament_fee']);
    $status = 'upcoming';

    $zone_id = sanitize($_POST['zone_id'] ?? '');
    $bank_account_name = sanitize($_POST['bank_account_name'] ?? '');
    $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
    $bank_account_holder = sanitize($_POST['bank_account_holder'] ?? '');

    // Get max participants from selected zone's spot count
    $zone_query = mysqli_query($conn, "SELECT COUNT(*) as spot_count FROM FISHING_SPOT WHERE zone_id='$zone_id'");
    $zone_data = mysqli_fetch_assoc($zone_query);
    $max_participants = $zone_data['spot_count'] ?? 0;

    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $image = uploadFile($_FILES['image'], 'tournaments');
        if (!$image) {
            $error = 'Failed to upload tournament image.';
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
            (user_id, tournament_title, tournament_date, location, description, tournament_rules,
             start_time, end_time, tournament_fee, max_participants, image, status, created_by,
             bank_account_name, bank_account_number, bank_account_holder, bank_qr, created_at)
            VALUES
            ('$user_id', '$tournament_title', '$tournament_date', '$location', '$description', '$tournament_rules',
             '$start_time', '$end_time', '$tournament_fee', '$max_participants', '$image', '$status',
             '$user_id', '$bank_account_name', '$bank_account_number', '$bank_account_holder', '$bank_qr', NOW())
        ";

        if (mysqli_query($conn, $query)) {
            $tournament_id = mysqli_insert_id($conn);

            // Update zone with tournament_id
            if (!empty($zone_id)) {
                mysqli_query($conn, "UPDATE ZONE SET tournament_id='$tournament_id' WHERE zone_id='$zone_id'");
            }

            // Insert calendar event
            mysqli_query($conn, "
                INSERT INTO CALENDAR (tournament_id, event_date, event_title)
                VALUES ('$tournament_id', '$tournament_date', '$tournament_title')
            ");

            // Insert sponsors with logo upload
            if (!empty($_POST['sponsor_names'])) {
                foreach ($_POST['sponsor_names'] as $index => $sponsor_name) {
                    if (!empty($sponsor_name)) {
                        $sponsor_name = sanitize($sponsor_name);
                        $sponsor_amount = floatval($_POST['sponsor_amounts'][$index] ?? 0);
                        $sponsor_phone = sanitize($_POST['sponsor_phones'][$index] ?? '');
                        $sponsor_email = sanitize($_POST['sponsor_emails'][$index] ?? '');
                        $sponsor_desc = sanitize($_POST['sponsor_descriptions'][$index] ?? '');
                        
                        $sponsor_logo = '';
                        if (!empty($_FILES['sponsor_logos']['name'][$index])) {
                            $file_tmp = $_FILES['sponsor_logos']['tmp_name'][$index];
                            $file_name = $_FILES['sponsor_logos']['name'][$index];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                            
                            if (in_array($file_ext, $allowed_ext)) {
                                $sponsor_logo = time() . '_' . $index . '_' . basename($file_name);
                                $target_path = '../../assets/images/sponsor/' . $sponsor_logo;
                                move_uploaded_file($file_tmp, $target_path);
                            }
                        }
                        
                        mysqli_query($conn, "
                            INSERT INTO SPONSOR (tournament_id, sponsor_name, contact_phone, contact_email, sponsored_amount, sponsor_description)
                            VALUES ('$tournament_id', '$sponsor_name', '$sponsor_phone', '$sponsor_email', '$sponsor_amount', '$sponsor_desc')
                        ");
                    }
                }
            }

            // Insert prizes for added categories
            if (!empty($_POST['prize_categories'])) {
                foreach ($_POST['prize_categories'] as $prize_index => $category_id) {
                    $category_id = intval($category_id);
                    $num_winners = intval($_POST['prize_num_winners'][$prize_index]);
                    
                    // Update number of rankings for this category
                    mysqli_query($conn, "
                        UPDATE CATEGORY 
                        SET number_of_ranking = '$num_winners' 
                        WHERE category_id = '$category_id'
                    ");
                    
                    // Update target weight if exact_weight category
                    if (isset($_POST['prize_target_weights'][$prize_index])) {
                        $target_weight = floatval($_POST['prize_target_weights'][$prize_index]);
                        mysqli_query($conn, "
                            UPDATE CATEGORY 
                            SET target_weight = '$target_weight' 
                            WHERE category_id = '$category_id'
                        ");
                    }
                    
                    // Insert prizes for each winner position
                    if (isset($_POST['prize_descriptions'][$prize_index])) {
                        foreach ($_POST['prize_descriptions'][$prize_index] as $winner_index => $description) {
                            if (!empty($description)) {
                                $prize_desc = sanitize($description);
                                $prize_value = floatval($_POST['prize_values'][$prize_index][$winner_index] ?? 0);
                                
                                $rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];
                                $prize_ranking = $rankings[$winner_index] ?? ($winner_index + 1) . 'th';
                                
                                mysqli_query($conn, "
                                    INSERT INTO TOURNAMENT_PRIZE (tournament_id, category_id, prize_ranking, prize_description, prize_value)
                                    VALUES ('$tournament_id', '$category_id', '$prize_ranking', '$prize_desc', '$prize_value')
                                ");
                            }
                        }
                    }
                }
            }

            $_SESSION['success'] = 'Tournament created successfully!';
            redirect(SITE_URL . '/admin/tournament/tournamentList.php');
        } else {
            $error = 'Database error: ' . mysqli_error($conn);
        }
    }
}

// Fetch predefined categories
$categories_query = "SELECT * FROM CATEGORY WHERE category_type != 'custom' ORDER BY category_id";
$categories_result = mysqli_query($conn, $categories_query);

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

.zone-info-card {
    background: #e3f2fd;
    border-left: 4px solid var(--color-blue-primary);
    padding: 1rem;
    border-radius: 8px;
    margin-top: 0.75rem;
}

.zone-unavailable {
    background: #ffebee;
    border-left: 4px solid #f44336;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 0.75rem;
}

.review-section {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.review-section-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--color-blue-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.review-item {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f5f5f5;
}

.review-item:last-child {
    border-bottom: none;
}

.review-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
}

.review-value {
    color: #1a1a1a;
    font-size: 0.875rem;
    word-break: break-word;
}

.review-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.review-list li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f5f5f5;
}

.review-list li:last-child {
    border-bottom: none;
}

.prize-table {
    width: 100%;
    margin-top: 1rem;
}

.prize-table td {
    padding: 0.75rem;
    vertical-align: middle;
}

.prize-table input {
    margin: 0;
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
                Step 1: Tournament Information
            </h4>
            
            <div class="form-group">
                <label>Tournament Title <span class="required">*</span></label>
                <input type="text" name="tournament_title" id="tournament_title" class="form-control" required>
            </div>

            <div class="info-grid">
                <div class="form-group">
                    <label>Tournament Date <span class="required">*</span></label>
                    <input type="date" name="tournament_date" id="tournament_date" class="form-control" required onchange="checkZoneAvailability()">
                </div>

                <div class="form-group">
                    <label>Tournament Fee (RM) <span class="required">*</span></label>
                    <input type="number" step="0.01" name="tournament_fee" id="tournament_fee" class="form-control" value="0.00" required>
                </div>
            </div>

            <div class="info-grid">
                <div class="form-group">
                    <label>Start Time <span class="required">*</span></label>
                    <input type="time" name="start_time" id="start_time" class="form-control" required onchange="checkZoneAvailability()">
                </div>

                <div class="form-group">
                    <label>End Time <span class="required">*</span></label>
                    <input type="time" name="end_time" id="end_time" class="form-control" required onchange="checkZoneAvailability()">
                </div>
            </div>

            <div class="form-group">
                <label>Location <span class="required">*</span></label>
                <input type="text" name="location" id="location" class="form-control" placeholder="e.g., Kota Kinabalu Jetty" required>
            </div>

            <div class="form-group">
                <label>Tournament Image</label>
                <input type="file" name="image" id="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                <div id="imagePreview" style="margin-top: 0.75rem; display: none;">
                    <img id="imagePreviewImg" style="max-width: 200px; border-radius: 8px; border: 2px solid #e9ecef;">
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="description" class="form-control" rows="4" placeholder="Describe the tournament..."></textarea>
            </div>

            <div class="form-group">
                <label>Tournament Rules</label>
                <textarea name="tournament_rules" id="tournament_rules" class="form-control" rows="6" placeholder="Enter tournament rules and regulations..."></textarea>
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
                Step 2: Payment Information
            </h4>
            
            <div class="form-group">
                <label>Bank Name <span class="required">*</span></label>
                <input type="text" name="bank_account_name" id="bank_account_name" class="form-control" placeholder="e.g., Maybank, CIMB" required>
            </div>

            <div class="info-grid">
                <div class="form-group">
                    <label>Account Number <span class="required">*</span></label>
                    <input type="text" name="bank_account_number" id="bank_account_number" class="form-control" placeholder="1234567890" required>
                </div>

                <div class="form-group">
                    <label>Account Holder <span class="required">*</span></label>
                    <input type="text" name="bank_account_holder" id="bank_account_holder" class="form-control" placeholder="Account holder name" required>
                </div>
            </div>

            <div class="form-group">
                <label>Payment QR Code</label>
                <input type="file" name="bank_qr" id="bank_qr" class="form-control" accept="image/*" onchange="previewQR(this)">
                <div id="qrPreview" style="margin-top: 0.75rem; display: none;">
                    <img id="qrPreviewImg" style="max-width: 200px; border-radius: 8px; border: 2px solid #e9ecef;">
                </div>
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

        <!-- STEP 3: Zone Details -->
        <div class="form-step">
            <h4 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--color-blue-primary);">
                Step 3: Fishing Zone Details
            </h4>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Only zones that are available during your tournament date and time will be shown below.
            </div>

            <div class="form-group">
                <label>Select Fishing Zone <span class="required">*</span></label>
                <select name="zone_id" id="zoneSelect" class="form-control" required>
                    <option value="">-- Select tournament date and time first --</option>
                </select>
            </div>

            <div id="zoneInfoCard" class="zone-info-card" style="display: none;">
                <h5 style="margin: 0 0 0.75rem 0; color: var(--color-blue-primary); font-size: 1rem;">
                    <i class="fas fa-info-circle"></i> Zone Information
                </h5>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <div style="font-size: 0.8125rem; color: #6c757d; margin-bottom: 0.25rem;">Zone Name</div>
                        <div id="zoneNameDisplay" style="font-weight: 600; color: #1a1a1a;"></div>
                    </div>
                    <div>
                        <div style="font-size: 0.8125rem; color: #6c757d; margin-bottom: 0.25rem;">Available Spots</div>
                        <div id="spotCountDisplay" style="font-weight: 600; color: #1a1a1a;"></div>
                    </div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.8125rem; color: #6c757d; margin-bottom: 0.25rem;">Description</div>
                    <div id="zoneDescDisplay" style="color: #495057; font-size: 0.875rem;"></div>
                </div>
                <div style="padding: 0.75rem; background: #fff3e0; border-radius: 8px; border-left: 3px solid #ff9800;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #f57c00; font-size: 0.875rem;">
                        <i class="fas fa-users"></i>
                        <strong>Max Participants:</strong> <span id="maxParticipantsDisplay" style="font-weight: 700;"></span>
                    </div>
                    <div style="font-size: 0.8125rem; color: #e65100; margin-top: 0.25rem;">
                        Based on available fishing spots in this zone
                    </div>
                </div>
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
                Step 4: Sponsors (Optional)
            </h4>
            <p style="color: #6c757d; font-size: 0.875rem; margin-bottom: 1.5rem;">
                Add sponsors who are supporting this tournament (for display purposes only)
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
                Step 5: Categories & Prizes
            </h4>
            <p style="color: #6c757d; font-size: 0.875rem; margin-bottom: 1.5rem;">
                Add prize categories and define the number of winners and their prizes
            </p>

            <div id="prizesList" class="dynamic-list"></div>

            <button type="button" class="btn btn-add" onclick="addPrize()">
                <i class="fas fa-plus"></i> Add Prize Category
            </button>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-prev">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-primary" id="btnMoveToReview" onclick="moveToReview()">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 6: Review & Submit -->
        <div class="form-step">
            <h4 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--color-blue-primary);">
                Step 6: Review & Submit
            </h4>
            
            <div style="padding: 1rem; background: #fff3e0; border-radius: 12px; border-left: 4px solid #ff9800; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-info-circle" style="color: #f57c00; font-size: 1.25rem;"></i>
                    <div style="color: #e65100; font-size: 0.875rem;">
                        Please review all information carefully before creating the tournament
                    </div>
                </div>
            </div>

            <!-- Review Content -->
            <div id="reviewContent"></div>

            <div style="padding: 1.5rem; background: #e8f5e9; border-radius: 12px; border-left: 4px solid #4caf50; margin-top: 1.5rem;">
                <div style="display: flex; align-items: start; gap: 1rem;">
                    <i class="fas fa-check-circle" style="color: #4caf50; font-size: 1.5rem; margin-top: 0.25rem;"></i>
                    <div>
                        <strong style="color: #2e7d32; font-size: 1rem; display: block; margin-bottom: 0.5rem;">
                            Ready to Create Tournament?
                        </strong>
                        <p style="color: #388e3c; font-size: 0.875rem; margin: 0; line-height: 1.6;">
                            All information is correct? Click "Create Tournament" to finalize. You can edit details after creation.
                        </p>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-prev">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-success" onclick="submitFinal()">
                    <i class="fas fa-check"></i> Create Tournament
                </button>
            </div>
        </div>

    </form>
</div>

<script>
// Categories data from PHP
const categories = <?= json_encode(mysqli_fetch_all($categories_result, MYSQLI_ASSOC)) ?>;

// Form submission handler
function submitFinal() {
    console.log('Submitting tournament...');
    
    // Create hidden input for final submission
    const form = document.getElementById('tournamentForm');
    const finalInput = document.createElement('input');
    finalInput.type = 'hidden';
    finalInput.name = 'final_submit';
    finalInput.value = '1';
    form.appendChild(finalInput);
    
    // Submit the form
    form.submit();
}

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

// Image Preview
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('imagePreviewImg');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewQR(input) {
    const preview = document.getElementById('qrPreview');
    const previewImg = document.getElementById('qrPreviewImg');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Check zone availability based on date and time
function checkZoneAvailability() {
    const tournamentDate = document.getElementById('tournament_date').value;
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    
    if (!tournamentDate || !startTime || !endTime) {
        return;
    }
    
    // Fetch available zones via AJAX
    fetch('../../includes/check_zone_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `tournament_date=${tournamentDate}&start_time=${startTime}&end_time=${endTime}`
    })
    .then(response => response.json())
    .then(data => {
        const zoneSelect = document.getElementById('zoneSelect');
        zoneSelect.innerHTML = '<option value="">-- Select Fishing Zone --</option>';
        
        if (data.zones && data.zones.length > 0) {
            data.zones.forEach(zone => {
                const option = document.createElement('option');
                option.value = zone.zone_id;
                option.setAttribute('data-spots', zone.spot_count);
                option.setAttribute('data-name', zone.zone_name);
                option.setAttribute('data-description', zone.zone_description || '');
                option.textContent = `${zone.zone_name} (${zone.spot_count} spots available)`;
                zoneSelect.appendChild(option);
            });
        } else {
            zoneSelect.innerHTML = '<option value="">No zones available for this date/time</option>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to check zone availability');
    });
}

// Zone selection handler
document.getElementById('zoneSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const zoneId = this.value;
    const spotCount = selectedOption.getAttribute('data-spots');
    const zoneName = selectedOption.getAttribute('data-name');
    const zoneDesc = selectedOption.getAttribute('data-description');
    
    if (zoneId) {
        document.getElementById('zoneInfoCard').style.display = 'block';
        document.getElementById('zoneNameDisplay').textContent = zoneName;
        document.getElementById('spotCountDisplay').textContent = spotCount + ' spots';
        document.getElementById('maxParticipantsDisplay').textContent = spotCount + ' participants';
        document.getElementById('zoneDescDisplay').textContent = zoneDesc || 'No description available';
    } else {
        document.getElementById('zoneInfoCard').style.display = 'none';
    }
});

// Sponsor management
let sponsorIndex = 0;

function addSponsor() {
    const html = `
        <div class="dynamic-item" id="sponsor-${sponsorIndex}">
            <div class="dynamic-item-header">
                <span style="font-weight: 600; color: #1a1a1a;">Sponsor ${sponsorIndex + 1}</span>
                <button type="button" class="btn-remove" onclick="removeSponsor(${sponsorIndex})">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
            <div class="form-group">
                <label>Sponsor Name <span class="required">*</span></label>
                <input type="text" name="sponsor_names[]" class="form-control sponsor-name" placeholder="e.g., ABC Bank" required>
            </div>
            <div class="form-group">
                <label>Sponsor Logo</label>
                <input type="file" name="sponsor_logos[]" class="form-control sponsor-logo" accept="image/*">
                <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.25rem; display: block;">
                    Logo will be stored in assets/images/sponsor/
                </small>
            </div>
            <div class="info-grid">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="sponsor_phones[]" class="form-control sponsor-phone" placeholder="e.g., 012-3456789">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="sponsor_emails[]" class="form-control sponsor-email" placeholder="e.g., sponsor@company.com">
                </div>
            </div>
            <div class="form-group">
                <label>Sponsored Amount (RM)</label>
                <input type="number" step="0.01" name="sponsor_amounts[]" class="form-control sponsor-amount" value="0.00">
            </div>
            <div class="form-group">
                <label>Description (Optional)</label>
                <textarea name="sponsor_descriptions[]" class="form-control sponsor-desc" rows="2" placeholder="Brief description about the sponsor"></textarea>
            </div>
        </div>
    `;
    document.getElementById('sponsorsList').insertAdjacentHTML('beforeend', html);
    sponsorIndex++;
}

function removeSponsor(index) {
    document.getElementById('sponsor-' + index).remove();
}

// Prize management
let prizeIndex = 0;

function addPrize() {
    const html = `
        <div class="dynamic-item" id="prize-${prizeIndex}">
            <div class="dynamic-item-header">
                <span style="font-weight: 600; color: #1a1a1a;">Prize Category ${prizeIndex + 1}</span>
                <button type="button" class="btn-remove" onclick="removePrize(${prizeIndex})">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
            
            <div class="info-grid">
                <div class="form-group">
                    <label>Select Category <span class="required">*</span></label>
                    <select name="prize_categories[]" class="form-control prize-category" onchange="handleCategoryChange(${prizeIndex}, this.value)" required>
                        <option value="">-- Select Category --</option>
                        ${categories.map(cat => `<option value="${cat.category_id}" data-type="${cat.category_type}">${cat.category_name}</option>`).join('')}
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Number of Winners <span class="required">*</span></label>
                    <input type="number" name="prize_num_winners[]" class="form-control prize-num-winner" min="1" max="20" value="3" onchange="generateWinnerFields(${prizeIndex}, this.value)" required>
                </div>
            </div>
            
            <div id="target-weight-${prizeIndex}" style="display: none; margin-bottom: 1rem;">
                <label>Target Weight (KG) <span class="required">*</span></label>
                <input type="number" step="0.01" name="prize_target_weights[]" class="form-control prize-target-weight" placeholder="e.g., 2.50" style="max-width: 200px;">
                <small style="color: #6c757d; font-size: 0.8125rem; margin-top: 0.25rem; display: block;">
                    Specify the exact weight fish must match to win
                </small>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <label style="font-weight: 600; margin-bottom: 1rem; display: block;">Prize Configuration</label>
                <table class="table prize-table" id="prize-table-${prizeIndex}">
                    <thead>
                        <tr>
                            <th width="100">Place</th>
                            <th>Prize Description</th>
                            <th width="150">Value (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Winner fields will be generated here -->
                    </tbody>
                </table>
            </div>
        </div>
    `;
    document.getElementById('prizesList').insertAdjacentHTML('beforeend', html);
    
    // Generate initial 3 winner fields
    generateWinnerFields(prizeIndex, 3);
    
    prizeIndex++;
}

function removePrize(index) {
    document.getElementById('prize-' + index).remove();
}

function handleCategoryChange(prizeIndex, categoryId) {
    const category = categories.find(c => c.category_id == categoryId);
    const targetWeightDiv = document.getElementById(`target-weight-${prizeIndex}`);
    
    if (category && category.category_type === 'exact_weight') {
        targetWeightDiv.style.display = 'block';
        targetWeightDiv.querySelector('input').required = true;
    } else {
        targetWeightDiv.style.display = 'none';
        targetWeightDiv.querySelector('input').required = false;
    }
}

function generateWinnerFields(prizeIndex, numWinners) {
    const tbody = document.querySelector(`#prize-table-${prizeIndex} tbody`);
    tbody.innerHTML = '';
    
    const rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];
    
    for (let i = 0; i < numWinners; i++) {
        const row = `
            <tr>
                <td><strong>${rankings[i]} Place</strong></td>
                <td>
                    <input type="text" name="prize_descriptions[${prizeIndex}][]" class="form-control prize-desc" placeholder="e.g., Cash Prize + Trophy" required>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="color: #6c757d;">RM</span>
                        <input type="number" step="0.01" name="prize_values[${prizeIndex}][]" class="form-control prize-value" placeholder="0.00" style="text-align: right;" required>
                    </div>
                </td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', row);
    }
}

// Move to review step
function moveToReview() {
    console.log('moveToReview() called');
    
    const prizes = document.querySelectorAll('#prizesList .dynamic-item');
    
    if (prizes.length === 0) {
        alert('Please add at least one prize category for this tournament.');
        return;
    }
    
    // Validate each prize
    let allValid = true;
    prizes.forEach(prize => {
        const categorySelect = prize.querySelector('select[name="prize_categories[]"]');
        const numWinnersInput = prize.querySelector('input[name="prize_num_winners[]"]');
        
        if (!categorySelect.value) {
            allValid = false;
            categorySelect.classList.add('input-error');
        } else {
            categorySelect.classList.remove('input-error');
        }
        
        if (!numWinnersInput.value || numWinnersInput.value < 1) {
            allValid = false;
            numWinnersInput.classList.add('input-error');
        } else {
            numWinnersInput.classList.remove('input-error');
        }
    });
    
    if (!allValid) {
        alert('Please complete all prize category configurations.');
        return;
    }
    
    // Generate review content
    try {
        console.log('Generating review...');
        generateReview();
        console.log('Review generated successfully');
        
        // Move to next step
        const step = formSteps[currentStep];
        step.classList.remove('form-step-active');
        currentStep++;
        formSteps[currentStep].classList.add('form-step-active');
        updateStepIndicator();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        console.error('Error in moveToReview:', error);
        alert('Error generating review. Check console for details.');
    }
}

// Generate Review Content
function generateReview() {
    console.log('generateReview() called');
    let reviewHTML = '';
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '-';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Step 1: Tournament Information
    const tournamentTitle = document.getElementById('tournament_title').value;
    const tournamentDate = document.getElementById('tournament_date').value;
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    const location = document.getElementById('location').value;
    const tournamentFee = document.getElementById('tournament_fee').value;
    const description = document.getElementById('description').value;
    const tournamentRules = document.getElementById('tournament_rules').value;
    const tournamentImage = document.getElementById('image').files.length;
    
    reviewHTML += `
        <div class="review-section">
            <div class="review-section-title">
                <i class="fas fa-info-circle"></i> Tournament Information
            </div>
            <div class="review-item">
                <div class="review-label">Title:</div>
                <div class="review-value">${escapeHtml(tournamentTitle)}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Date:</div>
                <div class="review-value">${tournamentDate || '-'}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Time:</div>
                <div class="review-value">${startTime || '-'} - ${endTime || '-'}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Location:</div>
                <div class="review-value">${escapeHtml(location)}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Entry Fee:</div>
                <div class="review-value">RM ${tournamentFee || '0.00'}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Description:</div>
                <div class="review-value">${escapeHtml(description) || '<em style="color: #6c757d;">Not provided</em>'}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Rules:</div>
                <div class="review-value">${escapeHtml(tournamentRules) || '<em style="color: #6c757d;">Not provided</em>'}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Tournament Image:</div>
                <div class="review-value">${tournamentImage > 0 ? '✓ Uploaded (' + document.getElementById('image').files[0].name + ')' : '<em style="color: #6c757d;">No image</em>'}</div>
            </div>
        </div>
    `;
    
    // Step 2: Payment Information
    const bankName = document.getElementById('bank_account_name').value;
    const accountNumber = document.getElementById('bank_account_number').value;
    const accountHolder = document.getElementById('bank_account_holder').value;
    const bankQR = document.getElementById('bank_qr').files.length;
    
    reviewHTML += `
        <div class="review-section">
            <div class="review-section-title">
                <i class="fas fa-credit-card"></i> Payment Information
            </div>
            <div class="review-item">
                <div class="review-label">Bank Name:</div>
                <div class="review-value">${escapeHtml(bankName)}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Account Number:</div>
                <div class="review-value">${escapeHtml(accountNumber)}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Account Holder:</div>
                <div class="review-value">${escapeHtml(accountHolder)}</div>
            </div>
            <div class="review-item">
                <div class="review-label">QR Code:</div>
                <div class="review-value">${bankQR > 0 ? '✓ Uploaded (' + document.getElementById('bank_qr').files[0].name + ')' : '<em style="color: #6c757d;">No QR code</em>'}</div>
            </div>
        </div>
    `;
    
    // Step 3: Zone Details
    const zoneSelect = document.getElementById('zoneSelect');
    const selectedZone = zoneSelect.options[zoneSelect.selectedIndex];
    const zoneName = selectedZone.getAttribute('data-name') || selectedZone.text;
    const spotCount = selectedZone.getAttribute('data-spots') || '0';
    
    reviewHTML += `
        <div class="review-section">
            <div class="review-section-title">
                <i class="fas fa-map-marked-alt"></i> Fishing Zone
            </div>
            <div class="review-item">
                <div class="review-label">Zone Name:</div>
                <div class="review-value">${escapeHtml(zoneName)}</div>
            </div>
            <div class="review-item">
                <div class="review-label">Max Participants:</div>
                <div class="review-value">${spotCount} participants (based on available spots)</div>
            </div>
        </div>
    `;
    
    // Step 4: Sponsors
    const sponsors = document.querySelectorAll('#sponsorsList .dynamic-item');
    if (sponsors.length > 0) {
        reviewHTML += `
            <div class="review-section">
                <div class="review-section-title">
                    <i class="fas fa-handshake"></i> Sponsors (${sponsors.length})
                </div>
                <ul class="review-list">
        `;
        sponsors.forEach((sponsor, index) => {
            const name = sponsor.querySelector('.sponsor-name').value;
            const amount = sponsor.querySelector('.sponsor-amount').value;
            const phone = sponsor.querySelector('.sponsor-phone').value;
            const email = sponsor.querySelector('.sponsor-email').value;
            const logo = sponsor.querySelector('.sponsor-logo').files.length;
            
            if (name) {
                let contactInfo = [];
                if (phone) contactInfo.push(phone);
                if (email) contactInfo.push(email);
                const contact = contactInfo.length > 0 ? ' (' + contactInfo.join(', ') + ')' : '';
                const logoInfo = logo > 0 ? ' [Logo uploaded]' : '';
                
                reviewHTML += `<li><strong>${escapeHtml(name)}</strong> - RM ${amount}${contact}${logoInfo}</li>`;
            }
        });
        reviewHTML += `</ul></div>`;
    } else {
        reviewHTML += `
            <div class="review-section">
                <div class="review-section-title">
                    <i class="fas fa-handshake"></i> Sponsors
                </div>
                <p style="color: #6c757d; font-style: italic;">No sponsors added</p>
            </div>
        `;
    }
    
    // Step 5: Categories & Prizes
    const prizes = document.querySelectorAll('#prizesList .dynamic-item');
    if (prizes.length > 0) {
        reviewHTML += `
            <div class="review-section">
                <div class="review-section-title">
                    <i class="fas fa-trophy"></i> Categories & Prizes (${prizes.length})
                </div>
        `;
        
        prizes.forEach((prize, index) => {
            const categorySelect = prize.querySelector('.prize-category');
            const categoryName = categorySelect.options[categorySelect.selectedIndex].text;
            const numWinners = prize.querySelector('.prize-num-winner').value;
            const prizeDescs = prize.querySelectorAll('.prize-desc');
            const prizeValues = prize.querySelectorAll('.prize-value');
            
            reviewHTML += `<div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                <strong style="color: var(--color-blue-primary); font-size: 1rem;">${escapeHtml(categoryName)}</strong> 
                <span style="color: #6c757d; font-size: 0.875rem;">(${numWinners} winners)</span>`;
            
            // Show target weight if exact weight category
            const targetWeightInput = prize.querySelector('input.prize-target-weight');
            if (targetWeightInput && targetWeightInput.offsetParent !== null && targetWeightInput.value) {
                reviewHTML += `<div style="margin-top: 0.5rem; color: #6c757d; font-size: 0.875rem;">
                    <i class="fas fa-weight"></i> Target Weight: ${targetWeightInput.value} KG
                </div>`;
            }
            
            // Show prizes
            reviewHTML += `<ul class="review-list" style="margin-top: 0.75rem;">`;
            prizeDescs.forEach((input, i) => {
                const desc = input.value;
                const value = prizeValues[i] ? prizeValues[i].value : '0.00';
                const rankings = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];
                if (desc) {
                    reviewHTML += `<li><strong style="color: #495057;">${rankings[i]} Place:</strong> ${escapeHtml(desc)} - <strong style="color: #28a745;">RM ${value}</strong></li>`;
                }
            });
            reviewHTML += `</ul></div>`;
        });
        reviewHTML += `</div>`;
    }
    
    console.log('Setting innerHTML...');
    document.getElementById('reviewContent').innerHTML = reviewHTML;
    console.log('Review content set successfully');
}
</script>

<?php include '../includes/footer.php'; ?>