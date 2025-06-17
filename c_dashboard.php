<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

// Get user data
$stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    // Combine first_name and last_name for display
    $user_name = $user['first_name'] . ' ' . $user['last_name'];
} else {
    // Handle error or redirect
    header("Location: login.php");
    exit();
}

// Get recent jobs
$stmt = $conn->prepare("
    SELECT j.id, j.title, j.description, j.location, j.status, j.created_at, 
           CONCAT(u.first_name, ' ', u.last_name) AS laborer_name
    FROM jobs j
    LEFT JOIN users u ON j.laborer_id = u.id
    WHERE j.customer_id = ?
    ORDER BY j.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$unread_notifications = $stmt->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-btn {
            background: #4CAF50;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .action-btn:hover {
            background: #45a049;
        }

        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .welcome-message {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="container">
        <div class="welcome-message">
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p>Here's an overview of your activity</p>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Active Jobs</h3>
                <div class="stat-number">
                    <?php 
                    $active_jobs = array_filter($recent_jobs, function($job) {
                        return $job['status'] === 'pending' || $job['status'] === 'assigned';
                    });
                    echo count($active_jobs);
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Completed Jobs</h3>
                <div class="stat-number">
                    <?php 
                    $completed_jobs = array_filter($recent_jobs, function($job) {
                        return $job['status'] === 'completed';
                    });
                    echo count($completed_jobs);
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Unread Notifications</h3>
                <div class="stat-number"><?php echo $unread_notifications; ?></div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="browse_laborers.php" class="action-btn">Find Laborers</a>
            <a href="c_job_requests.php" class="action-btn">Track Jobs</a>
            <a href="c_job_completion.php" class="action-btn">Ongoing Jobs</a>
            <a href="c_payments.php" class="action-btn">Make Payment</a>
        </div>

        <div class="recent-activity">
            <h2>Recent Jobs</h2>
            <?php if (empty($recent_jobs)): ?>
                <p>No recent jobs found</p>
            <?php else: ?>
                <?php foreach ($recent_jobs as $job): ?>
                    <div class="activity-item">
                        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                        <p>Status: <strong><?php echo ucfirst($job['status']); ?></strong></p>
                        <?php if ($job['laborer_name']): ?>
                            <p>Assigned to: <?php echo htmlspecialchars($job['laborer_name']); ?></p>
                        <?php endif; ?>
                        <small>Created: <?php echo date('M j, Y', strtotime($job['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
