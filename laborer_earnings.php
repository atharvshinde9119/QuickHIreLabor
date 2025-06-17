<?php
require_once 'config.php';

// Check if user is logged in and is a laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get earnings statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_jobs,
        SUM(CASE WHEN status = 'completed' THEN budget ELSE 0 END) as total_earnings,
        AVG(CASE WHEN status = 'completed' THEN budget ELSE NULL END) as avg_earnings,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_jobs
    FROM jobs 
    WHERE laborer_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get monthly earnings
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_jobs,
        SUM(budget) as monthly_earnings
    FROM jobs 
    WHERE laborer_id = ? AND status = 'completed'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_earnings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }

        .earnings-history {
            margin-top: 30px;
        }

        .earnings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .earnings-table th, .earnings-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .earnings-table th {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <div class="container">
        <h1>My Earnings</h1>

        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Earnings</h3>
                <div class="stat-number">₹<?php echo number_format($stats['total_earnings'], 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Completed Jobs</h3>
                <div class="stat-number"><?php echo $stats['completed_jobs']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Average per Job</h3>
                <div class="stat-number">₹<?php echo number_format($stats['avg_earnings'], 2); ?></div>
            </div>
        </div>

        <div class="earnings-history">
            <h2>Monthly Earnings</h2>
            <table class="earnings-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Jobs Completed</th>
                        <th>Total Earnings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_earnings as $earning): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($earning['month'] . '-01')); ?></td>
                            <td><?php echo $earning['total_jobs']; ?></td>
                            <td>₹<?php echo number_format($earning['monthly_earnings'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>