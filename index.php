<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
$page_title = 'Home';
include 'includes/header.php';
?>

<style>
:root {
    --ocean-blue: #0A4D68;
    --ocean-light: #088395;
    --ocean-teal: #05BFDB;
    --sand: #F5EFE6;
    --text-dark: #1A1A1A;
    --text-muted: #6B7280;
    --white: #FFFFFF;
    --border: #E5E7EB;
}

/* Hero Section */
.hero {
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    color: var(--white);
    text-align: center;
    padding: 300px 20px 300px;
}

.hero h1 {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 15px;
    line-height: 1.2;
}

.hero h1 span {
    color: var(--ocean-teal);
}

.hero p {
    font-size: 1.2rem;
    opacity: 0.9;
    max-width: 700px;
    margin: 0 auto;
}

/* Account Type Section */
.account-type-section {
    background: var(--sand);
    padding: 80px 20px;
    text-align: center;
}

.account-type-title {
    font-size: 2.5rem;
    margin-bottom: 10px;
    font-weight: 700;
    color: var(--text-dark);
}

.account-type-subtitle {
    font-size: 1.1rem;
    color: var(--text-muted);
    margin-bottom: 50px;
}

/* Account Cards */
.account-type-cards {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.account-card {
    background: var(--white);
    padding: 30px 25px;
    border-radius: 20px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    width: 300px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.account-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);
}

.account-card-icon {
    font-size: 50px;
    color: var(--ocean-light);
    margin-bottom: 20px;
}

.account-card-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 15px;
}

.account-card-description {
    font-size: 0.95rem;
    color: var(--text-muted);
    margin-bottom: 25px;
}

.account-card-button {
    display: inline-block;
    background: var(--ocean-light);
    color: var(--white);
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.account-card-button:hover {
    background: var(--ocean-blue);
}

/* Admin Modal */
.admin-request-modal {
    display: none;
    position: fixed;
    top:0; left:0;
    width:100%; height:100%;
    background: rgba(0,0,0,0.5);
    justify-content:center;
    align-items:center;
    z-index: 1000;
}

.modal-content {
    background: var(--white);
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    padding: 30px;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
}

/* Form inside modal */
.modal-content form .form-group {
    margin-bottom: 15px;
    text-align: left;
}

.modal-content form label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.modal-content form input,
.modal-content form textarea {
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid var(--border);
    font-size: 0.95rem;
}

.modal-content form textarea {
    min-height: 80px;
}

.file-upload-area {
    background: #F3F4F6;
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-upload-area:hover {
    background: #E5E7EB;
}

.file-preview img {
    max-width: 100%;
    margin-top: 10px;
    border-radius: 12px;
}

/* Features Section */
.features {
    padding: 160px 20px;
    background: var(--white);
    text-align: center;
}

.features h2 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 15px;
}

.features h2 span {
    color: var(--ocean-light);
}

.features-subtitle {
    color: var(--text-muted);
    font-size: 1rem;
    margin-bottom: 50px;
}

.features-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
}

.feature-card {
    background: var(--sand);
    padding: 25px 20px;
    border-radius: 20px;
    width: 280px;
    transition: all 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.feature-icon {
    font-size: 40px;
    color: var(--ocean-light);
    margin-bottom: 15px;
}

/* Tournament Cards */
.card {
    background: var(--white);
    border-radius: 20px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);
}

.card-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
}

.card-content {
    padding: 20px;
}

.card-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.card-text {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 15px;
}

.btn {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--ocean-light);
    color: var(--white);
}

.btn-primary:hover {
    background: var(--ocean-blue);
}

.btn-secondary {
    background: var(--text-dark);
    color: var(--white);
}

.btn-secondary:hover {
    background: #0A2D40;
}

/* Responsive */
@media (max-width: 992px) {
    .features-grid, .account-type-cards {
        flex-direction: column;
        align-items: center;
    }

    .card {
        width: 90%;
        margin: 0 auto;
    }
}
</style>

<!-- Hero Section - Split Layout -->
<section class="hero-split">
    <div class="container hero-split-container">
        <!-- Left Content -->
        <div class="hero-text">
            <h1>Compete, Track and Win with <span>SmartAngler</span></h1>
            <p>SmartAngler makes fishing competitions easy! Create and join tournaments, log your catches, and compete with anglers for great prizes.</p>
            <a href="pages/authentication/login.php" class="btn btn-primary btn-hero">Get Started</a>
        </div>

        <!-- Right Image -->
        <div class="hero-image">
            <img src="images/angler.webp" alt="Fishing GIF">
        </div>

    </div>
</section>

<style>
.hero-split {
    background: linear-gradient(135deg, var(--ocean-blue), var(--ocean-light));
    color: var(--white);
    padding: 120px 20px;
}

.hero-text {
    flex: 1 1 500px;
    max-width: 600px;
}

.hero-text h1 {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 20px;
}

.hero-text h1 span {
    color: var(--ocean-teal);
}

.hero-text p {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 30px;
}

.btn-hero {
    padding: 12px 30px;
    font-size: 1.1rem;
    border-radius: 12px;
}

.hero-split-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hero-image {
    margin-left: auto; 
}

.hero-image img {
    width: 600px;
    height: auto;
}

/* Responsive */
@media (max-width: 992px) {
    .hero-split-container {
        flex-direction: column-reverse;
        text-align: center;
    }

    .hero-text {
        max-width: 100%;
        margin-top: 40px;
    }
}
</style>

<?php if (!isLoggedIn()): ?>
<!-- Account Type Selection Section - Only visible when NOT logged in -->
<section class="account-type-section">
    <div class="account-type-container">
        <h1 class="account-type-title">Welcome to SmartAngler</h1>
        <p class="account-type-subtitle">Choose your account type to get started</p>
        
        <div class="account-type-cards">
            <!-- Angler Card -->
            <div class="account-card angler-card">
                <div class="account-card-icon">
                    <i class="fas fa-fish"></i>
                </div>
                <h3 class="account-card-title">Angler</h3>
                <p class="account-card-description">
                    Join fishing tournaments, track your catches, and compete with other anglers in your area.
                </p>
                <a href="pages/authentication/login.php" class="account-card-button">
                    <i class="fas fa-sign-in-alt"></i> Login as Angler
                </a>
            </div>
            
            <!-- Admin Card -->
            <div class="account-card admin-card">
                <div class="account-card-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h3 class="account-card-title">Tournament Admin</h3>
                <p class="account-card-description">
                    Organize and manage fishing tournaments, track participants, and oversee competitions.
                </p>
                <button onclick="openAdminRequestModal()" class="account-card-button">
                    <i class="fas fa-paper-plane"></i> Request Admin Access
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Admin Request Modal -->
<div id="adminRequestModal" class="admin-request-modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeAdminRequestModal()">&times;</button>
        
        <div class="modal-header">
            <h2><i class="fas fa-user-shield"></i> Request Admin Access</h2>
            <p>Fill out this form and we'll review your request within 24-48 hours</p>
        </div>
        
        <div id="alertMessage"></div>
        
        <form id="adminRequestForm" method="POST" action="pages/authentication/send_admin_request.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email address">
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number *</label>
                <input type="tel" id="phone" name="phone" required placeholder="e.g., 012-345-6789">
            </div>
            
            <div class="form-group">
                <label for="id_card">ID Card Number *</label>
                <input type="text" id="id_card" name="id_card" required placeholder="e.g., 990101-01-1234">
                <small>Your IC number for verification purposes</small>
            </div>
            
            <div class="form-group">
                <label for="location">Pond/Tournament Location *</label>
                <input type="text" id="location" name="location" required placeholder="e.g., Tawau Fishing Pond, Sabah">
                <small>Where you organize or plan to organize tournaments</small>
            </div>
            
            <div class="form-group">
                <label>ID Card Photo * (Front)</label>
                <div class="file-upload-area" onclick="document.getElementById('id_card_photo').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload your ID card photo</p>
                    <small style="color: #95A5A6;">JPG, PNG (Max 5MB)</small>
                </div>
                <input type="file" id="id_card_photo" name="id_card_photo" accept="image/*" required style="display: none;" onchange="previewFile(this, 'idPreview')">
                <div id="idPreview" class="file-preview"></div>
            </div>
            
            <div class="form-group">
                <label>Location/Tournament Proof (Optional)</label>
                <div class="file-upload-area" onclick="document.getElementById('location_proof').click()">
                    <i class="fas fa-image"></i>
                    <p>Click to upload photo proof of your location or tournament</p>
                    <small style="color: #95A5A6;">JPG, PNG (Max 5MB)</small>
                </div>
                <input type="file" id="location_proof" name="location_proof" accept="image/*" style="display: none;" onchange="previewFile(this, 'locationPreview')">
                <div id="locationPreview" class="file-preview"></div>
            </div>
            
            <div class="form-group">
                <label for="additional_info">Additional Information (Optional)</label>
                <textarea id="additional_info" name="additional_info" placeholder="Any additional details about your tournament organizing experience..."></textarea>
            </div>
            
            <button type="submit" class="submit-button" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Submit Request
            </button>
        </form>
    </div>
</div>

<script>
// Open modal
function openAdminRequestModal() {
    document.getElementById('adminRequestModal').style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

// Close modal
function closeAdminRequestModal() {
    document.getElementById('adminRequestModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    document.getElementById('adminRequestForm').reset();
    document.getElementById('idPreview').style.display = 'none';
    document.getElementById('locationPreview').style.display = 'none';
    document.getElementById('alertMessage').innerHTML = '';
}

// Close modal when clicking outside
document.getElementById('adminRequestModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAdminRequestModal();
    }
});

// Preview uploaded files
function previewFile(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <p style="margin: 0 0 10px 0; color: #27AE60; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> ${file.name}
                </p>
                <img src="${e.target.result}" alt="Preview">
            `;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// Handle form submission
document.getElementById('adminRequestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const alertMessage = document.getElementById('alertMessage');
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    // Create FormData
    const formData = new FormData(this);
    
    // Send request
    fetch('pages/authentication/send_admin_request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertMessage.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> ${data.message}
                </div>
            `;
            this.reset();
            document.getElementById('idPreview').style.display = 'none';
            document.getElementById('locationPreview').style.display = 'none';
            
            // Close modal after 3 seconds
            setTimeout(() => {
                closeAdminRequestModal();
            }, 3000);
        } else {
            alertMessage.innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        alertMessage.innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> Failed to send request. Please try again.
            </div>
        `;
    })
    .finally(() => {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
    });
});
</script>
<?php endif; ?>

<!-- Features Section -->
<section class="features">
    <div class="container">
        <h2>Everything a <span>Champion Angler Needs.</span></h2>
        <p class="features-subtitle">SmartAngler brings competitive angling features to your device. Register, Compete, and Track your fishing performance in real-time. Sign up and be part of the digital angling revolution.</p>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3>Tournament Hub</h3>
                <p>Stay updated with the latest fishing tournaments. Browse, view details and register for upcoming competitions. Get all the information you need in one place.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Participant Tools</h3>
                <p>Register, scan fishing spots, and log your catches. All in one place! Easy-to-use tools designed for anglers, by anglers.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-medal"></i>
                </div>
                <h3>Live Leaderboard</h3>
                <p>Follow the action! Best boat rankings and results at the tournament in real-time. See where you stand compared to fellow anglers.</p>
            </div>
        </div>
    </div>
</section>

<!-- Latest Tournaments Section -->
<section style="padding: 80px 0; background-color: #F5EFE6;">
    <div class="container">
        <h2 style="text-align: center; font-size: 36px; margin-bottom: 50px;">
            Upcoming <span style="color: #6D94C5;">Tournaments</span>
        </h2>

        <div class="features-grid">
            <?php
            $sql = "SELECT t.*, u.full_name as organizer_name 
                    FROM TOURNAMENT t 
                    LEFT JOIN USER u ON t.user_id = u.user_id 
                    WHERE t.status = 'upcoming' 
                    ORDER BY t.tournament_date ASC 
                    LIMIT 3";

            $tournaments = $db->fetchAll($sql);

            if ($tournaments && count($tournaments) > 0): 
                foreach ($tournaments as $tournament): ?>
                    <div class="card">
                        <img src="assets/images/tournaments/<?php echo $tournament['image'] ? $tournament['image'] : 'default-tournament.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($tournament['tournament_title']); ?>" 
                             class="card-image">
                        <div class="card-content">
                            <h3 class="card-title"><?php echo htmlspecialchars($tournament['tournament_title']); ?></h3>
                            <p class="card-text">
                                <i class="fas fa-calendar"></i> <?php echo formatDate($tournament['tournament_date']); ?><br>
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($tournament['location'], 0, 50)); ?>...<br>
                                <i class="fas fa-dollar-sign"></i> RM <?php echo number_format($tournament['tournament_fee'], 2); ?>
                            </p>
                            <a href="pages/tournament/tournament-details.php?id=<?php echo $tournament['tournament_id']; ?>" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <p style="text-align: center; grid-column: 1 / -1;">No upcoming tournaments at the moment. Check back soon!</p>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 40px;">
            <a href="pages/tournament/tournaments.php" class="btn btn-secondary">View All Tournaments</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
