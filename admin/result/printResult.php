<?php
session_start();
require_once '../includes/functions.php';

$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if ($tournament_id <= 0) {
    die("Invalid tournament ID.");
}

// Get tournament details
$query = "SELECT * FROM TOURNAMENT WHERE tournament_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    die("Tournament not found.");
}

// Get all categories with results
$query = "SELECT DISTINCT c.* 
          FROM CATEGORY c
          INNER JOIN RESULT r ON c.category_id = r.category_id
          WHERE r.tournament_id = ?
          ORDER BY c.category_id";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get result status
$query = "SELECT result_status FROM RESULT WHERE tournament_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$result_status_row = $stmt->fetch(PDO::FETCH_ASSOC);
$is_published = $result_status_row ? $result_status_row['result_status'] === 'final' : false;

// Get prizes
$query = "SELECT tp.*, s.sponsor_name 
          FROM TOURNAMENT_PRIZE tp
          LEFT JOIN SPONSOR s ON tp.sponsor_id = s.sponsor_id
          WHERE tp.tournament_id = ?
          ORDER BY tp.prize_ranking";
$stmt = $conn->prepare($query);
$stmt->execute([$tournament_id]);
$prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - <?php echo htmlspecialchars($tournament['tournament_title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 20px;
            }
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: white;
        }
        
        .header-section {
            text-align: center;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .logo {
            max-width: 150px;
            margin-bottom: 15px;
        }
        
        .tournament-title {
            font-size: 28px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 5px;
        }
        
        .tournament-info {
            color: #666;
            font-size: 14px;
        }
        
        .category-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        
        .category-header {
            background: #0d6efd;
            color: white;
            padding: 10px 15px;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .result-table th {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        
        .result-table td {
            border: 1px solid #dee2e6;
            padding: 10px;
        }
        
        .rank-1 {
            background: #fff3cd !important;
        }
        
        .rank-2 {
            background: #e2e3e5 !important;
        }
        
        .rank-3 {
            background: #cfe2ff !important;
        }
        
        .medal {
            font-size: 24px;
            margin-right: 5px;
        }
        
        .footer-section {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
            color: #666;
            font-size: 12px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .status-official {
            background: #198754;
            color: white;
        }
        
        .status-live {
            background: #ffc107;
            color: #000;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <div class="no-print text-center mb-3">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-printer"></i> Print Results
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            Close
        </button>
    </div>

    <!-- Header -->
    <div class="header-section">
        <!-- Add logo if you have one -->
        <!-- <img src="../assets/images/logo.png" alt="SmartAngler Logo" class="logo"> -->
        
        <div class="tournament-title">
            <?php echo htmlspecialchars($tournament['tournament_title']); ?>
        </div>
        <div class="tournament-info">
            <strong>Date:</strong> <?php echo date('l, d F Y', strtotime($tournament['tournament_date'])); ?><br>
            <strong>Location:</strong> <?php echo htmlspecialchars($tournament['location']); ?><br>
            <strong>Time:</strong> <?php echo date('H:i', strtotime($tournament['start_time'])); ?> - <?php echo date('H:i', strtotime($tournament['end_time'])); ?>
        </div>
        
        <?php if ($is_published): ?>
            <div class="status-badge status-official">
                ✓ OFFICIAL RESULTS
            </div>
        <?php else: ?>
            <div class="status-badge status-live">
                ⏱ LIVE RESULTS
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($categories)): ?>
        <div class="alert alert-warning">
            No results available for this tournament.
        </div>
    <?php else: ?>
        <!-- Results by Category -->
        <?php foreach ($categories as $category): ?>
            <?php
            // Get results for this category
            $query = "SELECT 
                        r.*,
                        u.full_name,
                        fc.fish_weight,
                        fc.fish_species,
                        fc.catch_time,
                        z.zone_name,
                        fs.spot_number
                      FROM RESULT r
                      JOIN USER u ON r.user_id = u.user_id
                      LEFT JOIN FISH_CATCH fc ON r.catch_id = fc.catch_id
                      LEFT JOIN TOURNAMENT_REGISTRATION tr ON r.user_id = tr.user_id AND tr.tournament_id = r.tournament_id
                      LEFT JOIN FISHING_SPOT fs ON tr.spot_id = fs.spot_id
                      LEFT JOIN ZONE z ON fs.zone_id = z.zone_id
                      WHERE r.tournament_id = ? AND r.category_id = ?
                      ORDER BY r.ranking_position ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute([$tournament_id, $category['category_id']]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="category-section">
                <div class="category-header">
                    🏆 <?php echo htmlspecialchars($category['category_name']); ?>
                </div>
                
                <?php if ($category['description']): ?>
                    <p style="color: #666; margin-bottom: 15px;">
                        <em><?php echo htmlspecialchars($category['description']); ?></em>
                    </p>
                <?php endif; ?>

                <table class="result-table">
                    <thead>
                        <tr>
                            <th width="60">Rank</th>
                            <th>Angler Name</th>
                            <?php if (strtolower($category['category_name']) === 'most catches'): ?>
                                <th>Total Catches</th>
                            <?php else: ?>
                                <th>Fish Species</th>
                                <th>Weight (kg)</th>
                            <?php endif; ?>
                            <th>Catch Time</th>
                            <th>Zone/Spot</th>
                            <?php if (!empty($prizes)): ?>
                                <th>Prize</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <?php
                            $rank_class = '';
                            $medal = '';
                            if ($result['ranking_position'] == 1) {
                                $rank_class = 'rank-1';
                                $medal = '🥇';
                            } elseif ($result['ranking_position'] == 2) {
                                $rank_class = 'rank-2';
                                $medal = '🥈';
                            } elseif ($result['ranking_position'] == 3) {
                                $rank_class = 'rank-3';
                                $medal = '🥉';
                            }
                            
                            // Find matching prize
                            $prize_info = '';
                            foreach ($prizes as $prize) {
                                if ($prize['prize_ranking'] == $result['ranking_position'] || 
                                    $prize['prize_ranking'] == 'Top ' . $result['ranking_position']) {
                                    $prize_info = $prize['prize_description'];
                                    if ($prize['prize_value']) {
                                        $prize_info .= ' (RM ' . number_format($prize['prize_value'], 2) . ')';
                                    }
                                    if ($prize['sponsor_name']) {
                                        $prize_info .= ' - Sponsored by: ' . htmlspecialchars($prize['sponsor_name']);
                                    }
                                    break;
                                }
                            }
                            ?>
                            <tr class="<?php echo $rank_class; ?>">
                                <td style="text-align: center; font-weight: bold; font-size: 18px;">
                                    <?php if ($medal): ?>
                                        <span class="medal"><?php echo $medal; ?></span>
                                    <?php else: ?>
                                        #<?php echo $result['ranking_position']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($result['full_name']); ?></strong></td>
                                <?php if (strtolower($category['category_name']) === 'most catches'): ?>
                                    <td style="text-align: center; font-weight: bold;">
                                        <?php echo $result['total_fish_count']; ?> catches
                                    </td>
                                <?php else: ?>
                                    <td><?php echo htmlspecialchars($result['fish_species']); ?></td>
                                    <td style="font-weight: bold; color: #0d6efd;">
                                        <?php echo number_format($result['fish_weight'], 2); ?> kg
                                    </td>
                                <?php endif; ?>
                                <td><?php echo $result['catch_time'] ? date('H:i:s', strtotime($result['catch_time'])) : '-'; ?></td>
                                <td>
                                    <?php 
                                    if ($result['zone_name'] && $result['spot_number']) {
                                        echo htmlspecialchars($result['zone_name']) . ' - Spot #' . $result['spot_number'];
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <?php if (!empty($prizes)): ?>
                                    <td><?php echo $prize_info ? $prize_info : '-'; ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer-section">
        <p>
            <strong>SmartAngler Tournament Management System</strong><br>
            Generated on <?php echo date('l, d F Y H:i:s'); ?><br>
            <?php if ($is_published): ?>
                These are the official tournament results.
            <?php else: ?>
                These are live results and may change until officially published.
            <?php endif; ?>
        </p>
    </div>
</body>
</html>