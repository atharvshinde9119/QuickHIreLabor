<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Get dashboard statistics
$stats = [];

// Total users count
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();
$stats['total_users'] = $row['count'];

// Active jobs count
$result = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'assigned'");
$row = $result->fetch_assoc();
$stats['active_jobs'] = $row['count'];

// Completed jobs count
$result = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'completed'");
$row = $result->fetch_assoc();
$stats['completed_jobs'] = $row['count'];

// Total earnings
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
$row = $result->fetch_assoc();
$stats['total_earnings'] = $row['total'] ? $row['total'] : 0;

// Get recent job requests
$recent_jobs = [];
$result = $conn->query("
    SELECT j.id, j.title, j.status, j.created_at,
           CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
           CONCAT(l.first_name, ' ', l.last_name) AS laborer_name
    FROM jobs j
    JOIN users c ON j.customer_id = c.id
    LEFT JOIN users l ON j.laborer_id = l.id
    ORDER BY j.created_at DESC
    LIMIT 5
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_jobs[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="content">
        <header>
            <h2>Dashboard</h2>
            <div class="admin-info">
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
        </header>

        <div class="cards">
            <div class="card">
                <h3>Total Users</h3>
                <p><?php echo $stats['total_users']; ?></p>
            </div>
            <div class="card">
                <h3>Active Jobs</h3>
                <p><?php echo $stats['active_jobs']; ?></p>
            </div>
            <div class="card">
                <h3>Completed Jobs</h3>
                <p><?php echo $stats['completed_jobs']; ?></p>
            </div>
            <div class="card">
                <h3>Total Earnings</h3>
                <p>Rs.<?php echo number_format($stats['total_earnings'], 2); ?></p>
            </div>
        </div>

        <div class="table-section">
            <h3>Recent Job Requests</h3>
            <?php if (!empty($recent_jobs)): ?>
            <table>
                <tr>
                    <th>Job ID</th>
                    <th>Title</th>
                    <th>Customer</th>
                    <th>Laborer</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($recent_jobs as $job): ?>
                <tr>
                    <td>#<?php echo $job['id']; ?></td>
                    <td><?php echo $job['title']; ?></td>
                    <td><?php echo $job['customer_name']; ?></td>
                    <td><?php echo $job['laborer_name'] ? $job['laborer_name'] : 'Not Assigned'; ?></td>
                    <td><?php echo ucfirst($job['status']); ?></td>
                    <td><a href="view_job.php?id=<?php echo $job['id']; ?>" class="btn">View</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
            <p>No job requests found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
