<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// If custom date range is provided
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// Initialize reports data arrays
$jobStats = [];
$customerStats = [];
$laborerStats = [];
$financialStats = [
    'total_jobs' => 0,
    'total_payments' => 0,
    'avg_job_cost' => 0,
    'completed_jobs' => 0,
    'pending_payments' => 0
];

// Check if required tables exist
$requiredTables = ['jobs', 'users', 'payments', 'ratings'];
$missingTables = [];

foreach ($requiredTables as $table) {
    if (!tableExists($conn, $table)) {
        $missingTables[] = $table;
    }
}

if (empty($missingTables)) {
    // Job completion and status report
    $sql = "SELECT 
            j.id as job_id,
            j.title as job_title,
            j.status as job_status,
            j.created_at as job_date,
            j.budget as job_budget,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
            p.amount as payment_amount,
            p.status as payment_status
            FROM jobs j
            LEFT JOIN users c ON j.customer_id = c.id
            LEFT JOIN users l ON j.laborer_id = l.id
            LEFT JOIN payments p ON j.id = p.job_id
            WHERE j.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
            ORDER BY j.created_at DESC";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobStats[] = $row;
        }
    }
    
    // Customer activity
    $sql = "SELECT 
            c.id as customer_id,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            COUNT(j.id) as total_jobs,
            SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN j.status = 'open' THEN 1 ELSE 0 END) as open_jobs,
            SUM(p.amount) as total_spent
            FROM users c
            LEFT JOIN jobs j ON c.id = j.customer_id
            LEFT JOIN payments p ON j.id = p.job_id
            WHERE c.role = 'customer'
            AND (j.created_at IS NULL OR j.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59')
            GROUP BY c.id
            ORDER BY total_jobs DESC";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $customerStats[] = $row;
        }
    }
    
    // Laborer performance
    $sql = "SELECT 
            l.id as laborer_id,
            CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
            COUNT(j.id) as total_jobs,
            SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            AVG(r.rating) as avg_rating
            FROM users l
            LEFT JOIN jobs j ON l.id = j.laborer_id
            LEFT JOIN ratings r ON j.id = r.job_id AND r.ratee_id = l.id
            WHERE l.role = 'laborer'
            AND (j.created_at IS NULL OR j.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59')
            GROUP BY l.id
            ORDER BY completed_jobs DESC";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $laborerStats[] = $row;
        }
    }
    
    // Financial overview
    $sql = "SELECT 
            COUNT(j.id) as total_jobs,
            SUM(p.amount) as total_payments,
            AVG(j.budget) as avg_job_cost,
            SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_payments
            FROM jobs j
            LEFT JOIN payments p ON j.id = p.job_id
            WHERE j.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
    
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $financialStats = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Add any necessary styles or scripts -->
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Business Reports</h2>
        </header>

        <?php if (!empty($missingTables)): ?>
            <div class="alert error">
                Some required tables are missing: <?php echo implode(', ', $missingTables); ?>. 
                Please <a href="sql_setup.php">run the SQL setup</a> to create all necessary tables.
            </div>
        <?php else: ?>
            <!-- Date range filter -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="start_date">From:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">To:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <button type="submit" class="btn">Apply Filter</button>
                </form>
            </div>

            <!-- Financial summary -->
            <div class="report-section">
                <h3>Financial Overview</h3>
                <div class="stats-cards">
                    <div class="stat-card">
                        <h4>Total Jobs</h4>
                        <p class="stat-value"><?php echo $financialStats['total_jobs'] ?? 0; ?></p>
                    </div>
                    <div class="stat-card">
                        <h4>Total Payments</h4>
                        <p class="stat-value">Rs.<?php echo number_format($financialStats['total_payments'] ?? 0, 2); ?></p>
                    </div>
                    <div class="stat-card">
                        <h4>Average Job Cost</h4>
                        <p class="stat-value">Rs.<?php echo number_format($financialStats['avg_job_cost'] ?? 0, 2); ?></p>
                    </div>
                    <div class="stat-card">
                        <h4>Completed Jobs</h4>
                        <p class="stat-value"><?php echo $financialStats['completed_jobs'] ?? 0; ?></p>
                    </div>
                    <div class="stat-card">
                        <h4>Pending Payments</h4>
                        <p class="stat-value">Rs.<?php echo number_format($financialStats['pending_payments'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Job status report -->
            <div class="report-section">
                <h3>Job Status Report</h3>
                <?php if (!empty($jobStats)): ?>
                    <table>
                        <tr>
                            <th>Job ID</th>
                            <th>Job Title</th>
                            <th>Customer</th>
                            <th>Laborer</th>
                            <th>Budget</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                        <?php foreach ($jobStats as $job): ?>
                            <tr>
                                <td>#<?php echo $job['job_id']; ?></td>
                                <td><?php echo $job['job_title']; ?></td>
                                <td><?php echo $job['customer_name']; ?></td>
                                <td><?php echo $job['laborer_name'] ?? 'Not Assigned'; ?></td>
                                <td>₹<?php echo number_format($job['job_budget'], 2); ?></td>
                                <td>
                                    <?php if ($job['payment_amount']): ?>
                                        $<?php echo number_format($job['payment_amount'], 2); ?>
                                        (<?php echo ucfirst($job['payment_status']); ?>)
                                    <?php else: ?>
                                        No Payment
                                    <?php endif; ?>
                                </td>
                                <td><?php echo ucfirst($job['job_status']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($job['job_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No job data available for the selected date range.</p>
                <?php endif; ?>
            </div>

            <!-- Customer Stats -->
            <div class="report-section">
                <h3>Customer Activity</h3>
                <?php if (!empty($customerStats)): ?>
                    <table>
                        <tr>
                            <th>Customer</th>
                            <th>Total Jobs</th>
                            <th>Completed Jobs</th>
                            <th>Open Jobs</th>
                            <th>Total Spent</th>
                        </tr>
                        <?php foreach ($customerStats as $customer): ?>
                            <tr>
                                <td><?php echo $customer['customer_name']; ?></td>
                                <td><?php echo $customer['total_jobs']; ?></td>
                                <td><?php echo $customer['completed_jobs']; ?></td>
                                <td><?php echo $customer['open_jobs']; ?></td>
                                <td>Rs.<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No customer data available for the selected date range.</p>
                <?php endif; ?>
            </div>

            <!-- Laborer Performance -->
            <div class="report-section">
                <h3>Laborer Performance</h3>
                <?php if (!empty($laborerStats)): ?>
                    <table>
                        <tr>
                            <th>Laborer</th>
                            <th>Total Jobs</th>
                            <th>Completed Jobs</th>
                            <th>Average Rating</th>
                        </tr>
                        <?php foreach ($laborerStats as $laborer): ?>
                            <tr>
                                <td><?php echo $laborer['laborer_name']; ?></td>
                                <td><?php echo $laborer['total_jobs']; ?></td>
                                <td><?php echo $laborer['completed_jobs']; ?></td>
                                <td>
                                    <?php if ($laborer['avg_rating']): ?>
                                        <?php echo number_format($laborer['avg_rating'], 1); ?>/5
                                        <span class="rating-stars">
                                            <?php 
                                            $rating = round($laborer['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $rating ? '★' : '☆';
                                            }
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        No ratings
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No laborer data available for the selected date range.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .filter-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-section form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .report-section {
            margin-bottom: 30px;
        }
        .stats-cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #fff;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex: 1;
            min-width: 150px;
            text-align: center;
        }
        .stat-card h4 {
            margin-top: 0;
            color: #666;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 0;
            color: #0355cc;
        }
        .rating-stars {
            color: #FFD700;
            margin-left: 5px;
        }
    </style>
</body>
</html>
