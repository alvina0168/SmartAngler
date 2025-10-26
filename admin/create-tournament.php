<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/pages/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $tournament_title = sanitize($_POST['tournament_title']);
    $tournament_date = sanitize($_POST['tournament_date']);
    $location = sanitize($_POST['location']);
    $description = sanitize($_POST['description']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $tournament_fee = sanitize($_POST['tournament_fee']);
    $max_participants = sanitize($_POST['max_participants']);
    $status = sanitize($_POST['status']);
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image = uploadFile($_FILES['image'], 'tournaments');
    }
    
    if (empty($tournament_title) || empty($tournament_date) || empty($location)) {
        $error = 'Please fill in all required fields';
    } else {
        $query = "INSERT INTO TOURNAMENT 
                  (user_id, tournament_title, tournament_date, location, description, start_time, end_time, 
                   tournament_fee, max_participants, image, status) 
                  VALUES 
                  ('$user_id', '$tournament_title', '$tournament_date', '$location', '$description', 
                   '$start_time', '$end_time', '$tournament_fee', '$max_participants', '$image', '$status')";
        
        if (mysqli_query($conn, $query)) {
            $success = 'Tournament created successfully!';
            $tournament_id = mysqli_insert_id($conn);
            
            // Add to calendar
            $calendar_query = "INSERT INTO CALENDAR (tournament_id, event_date, event_title) 
                              VALUES ('$tournament_id', '$tournament_date', '$tournament_title')";
            mysqli_query($conn, $calendar_query);
        } else {
            $error = 'Failed to create tournament. Please try again.';
        }
    }
}

$page_title = 'Create Tournament';
include '../includes/header.php';
?>

<div style="min-height: 70vh; padding: 50px 0; background-color: #F5EFE6;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; padding: 40px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
            <h1 style="text-align: center; color: #6D94C5; margin-bottom: 30px;">
                <i class="fas fa-plus-circle"></i> Create New Tournament
            </h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <a href="manage-tournaments.php" style="color: #155724; font-weight: 600;">View Tournaments</a>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Tournament Title *</label>
                    <input type="text" name="tournament_title" class="form-control" placeholder="e.g., Sabah Fishing Championship 2025" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Tournament Date *</label>
                        <input type="date" name="tournament_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Start Time *</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time *</label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Location (include Google Maps link) *</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g., Kota Kinabalu Jetty - https://goo.gl/maps/..." required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe your tournament..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Tournament Fee (RM)</label>
                        <input type="number" step="0.01" name="tournament_fee" class="form-control" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label>Max Participants</label>
                        <input type="number" name="max_participants" class="form-control" placeholder="50">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Tournament Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-plus"></i> Create Tournament
                </button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>