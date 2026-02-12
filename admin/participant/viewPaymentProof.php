<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

if (!isset($_GET['id'])) {
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$registration_id = intval($_GET['id']);

$query = "SELECT tr.payment_proof, tr.registration_id, t.tournament_title, u.full_name
          FROM TOURNAMENT_REGISTRATION tr
          JOIN TOURNAMENT t ON tr.tournament_id = t.tournament_id
          JOIN USER u ON tr.user_id = u.user_id
          WHERE tr.registration_id = '$registration_id'";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Payment proof not found!';
    redirect(SITE_URL . '/admin/tournament/tournamentList.php');
}

$data = mysqli_fetch_assoc($result);
$page_title = 'Payment Proof';
include '../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-style.css">

<style>
.proof-container {
    max-width: 900px;
    margin: 2rem auto;
}

.proof-card {
    background: var(--color-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
}

.proof-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.proof-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--color-cream-light);
}

.proof-header h2 {
    color: var(--color-blue-primary);
    margin-bottom: 0.5rem;
}

.proof-info {
    display: flex;
    justify-content: center;
    gap: 2rem;
    font-size: 0.875rem;
    color: var(--color-gray-600);
}

.proof-info span i {
    margin-right: 0.25rem;
    color: var(--color-blue-primary);
}

.proof-image-container {
    text-align: center;
    margin-bottom: 2rem;
}

.proof-image {
    max-width: 100%;
    height: auto;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    border: 3px solid var(--color-cream-dark);
}

.proof-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.proof-actions .btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--color-gray-700);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--color-blue-primary);
}
</style>

<div class="content-container proof-container">
    <div class="proof-card">
        <div class="proof-header">
            <h2><i class="fas fa-file-invoice-dollar"></i> Payment Proof</h2>
            <div class="proof-info">
                <span><i class="fas fa-user"></i> <strong><?= htmlspecialchars($data['full_name']); ?></strong></span>
                <span><i class="fas fa-trophy"></i> <?= htmlspecialchars($data['tournament_title']); ?></span>
            </div>
        </div>

        <div class="proof-image-container">
            <?php if (!empty($data['payment_proof'])): ?>
                <img src="../../assets/images/payments/<?= htmlspecialchars($data['payment_proof']); ?>" 
                     alt="Payment Proof" 
                     class="proof-image">
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-image"></i>
                    <h3>No Payment Proof</h3>
                    <p>Participant has not uploaded payment proof yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="proof-actions">
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Close
            </button>
            <?php if (!empty($data['payment_proof'])): ?>
                <a href="../../assets/images/payments/<?= htmlspecialchars($data['payment_proof']); ?>" 
                   download 
                   class="btn btn-primary">
                    <i class="fas fa-download"></i> Download
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
