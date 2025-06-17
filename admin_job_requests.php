<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Setup filter parameters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'newest';

// Handle job status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    $action = $_POST['action'];
    $status = '';
    $message = '';
    
    // Determine new status based on action
    switch ($action) {
        case 'approve':
            $status = 'open';
            $message = 'Job has been approved and is now open for applications.';
            break;
        case 'reject':
            $status = 'rejected';
            $message = 'Job has been rejected.';
            break;
        case 'cancel':
            $status = 'cancelled';
            $message = 'Job has been cancelled.';
            break;
    }
    
    if (!empty($status)) {
        // Update job status
        $update_stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $status, $job_id);
        
        if ($update_stmt->execute()) {
            // Add entry to job status history
            $history_stmt = $conn->prepare("
                INSERT INTO job_status_history (job_id, status, notes) 
                VALUES (?, ?, ?)
            ");
            $notes = "Status updated to $status by admin";
            $history_stmt->bind_param("iss", $job_id, $status, $notes);
            $history_stmt->execute();
            
            // Get job and customer details
            $job_stmt = $conn->prepare("
                SELECT j.title, j.customer_id 
                FROM jobs j
                WHERE j.id = ?
            ");
            $job_stmt->bind_param("i", $job_id);
            $job_stmt->execute();
            $job = $job_stmt->get_result()->fetch_assoc();
            
            if ($job) {
                // Create notification for customer
                $title = "Job Status Update";
                $notification_message = "Your job '{$job['title']}' has been " . 
                                       ($status === 'open' ? 'approved' : $status) . ".";
                createNotification($job['customer_id'], $title, $notification_message, 'job');
            }
            
            $_SESSION['success_msg'] = $message;
        } else {
            $_SESSION['error_msg'] = "Error updating job status.";
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: admin_job_requests.php");
    exit();
}

// Prepare base SQL query
$sql = "
    SELECT j.*, 
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           c.email as customer_email,
           c.phone as customer_phone,
           s.name as service_name,
           (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) as application_count
    FROM jobs j
    JOIN users c ON j.customer_id = c.id
    LEFT JOIN services s ON j.service_id = s.id
    WHERE 1=1
";

// Apply status filter
if ($status_filter !== 'all') {
    $sql .= " AND j.status = ?";
}

// Apply date filter
if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'today':
            $sql .= " AND DATE(j.created_at) = CURDATE()";
            break;
        case 'week':
            $sql .= " AND j.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $sql .= " AND j.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

// Apply search filter
if (!empty($search)) {
    $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
}

// Apply sorting
switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY j.created_at ASC";
        break;
    case 'budget_high':
        $sql .= " ORDER BY j.budget DESC";
        break;
    case 'budget_low':
        $sql .= " ORDER BY j.budget ASC";
        break;
    case 'status':
        $sql .= " ORDER BY j.status ASC, j.created_at DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY j.created_at DESC";
        break;
}

// Prepare and execute query with parameter binding
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $types .= "s";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $search_param = "%$search%";
    $types .= "sss";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count jobs by status
$statusCounts = [
    'pending_approval' => 0,
    'open' => 0,
    'assigned' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'rejected' => 0
];

$total_jobs = count($jobs);
foreach ($jobs as $job) {
    if (isset($statusCounts[$job['status']])) {
        $statusCounts[$job['status']]++;
    }
}

// Get all job statuses for filter dropdown
$statuses = [
    'all' => 'All Statuses',
    'pending_approval' => 'Pending Approval',
    'open' => 'Open',
    'assigned' => 'Assigned',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'rejected' => 'Rejected'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Job Requests - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 20px;
        }
        
        h1 {
            margin-bottom: 20px;
        }
        
        .filter-form {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
        }
        
        .filter-buttons {
            margin-top: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            background-color: #4CAF50;
            color: white;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
            padding: 10px;
            text-align: left;
        }
        
        td {
            padding: 8px;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            color: white;
        }
        
        .status-pending { background-color: #ffc107; color: black; }
        .status-open { background-color: #17a2b8; }
        .status-assigned { background-color: #007bff; }
        .status-progress { background-color: #6f42c1; }
        .status-completed { background-color: #28a745; }
        .status-cancelled { background-color: #dc3545; }
        .status-rejected { background-color: #6c757d; }
        
        .action-btn {
            padding: 5px 10px;
            margin-right: 5px;
            margin-bottom: 5px;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            border-radius: 3px;
            transition: background-color 0.3s;
            min-width: 60px;
            text-align: center;
        }
        
        .view-btn { background-color: #007bff; }
        .view-btn:hover { background-color: #0069d9; }
        
        .approve-btn { background-color: #28a745; }
        .approve-btn:hover { background-color: #218838; }
        
        .reject-btn { background-color: #dc3545; }
        .reject-btn:hover { background-color: #c82333; }
        
        .cancel-btn { background-color: #dc3545; }
        .cancel-btn:hover { background-color: #c82333; }
        
        .action-cell {
            white-space: nowrap;
            padding: 8px 6px;
        }
        
        /* Fix for inline forms */
        .action-form {
            display: inline-block;
            margin-right: 2px;
            margin-bottom: 3px;
        }
        
        .no-records {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <div class="container">
        <h1>Manage Job Requests</h1>
        
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>
        
        <form class="filter-form" method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date">Date Range:</label>
                    <select id="date" name="date">
                        <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort">Sort By:</label>
                    <select id="sort" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="budget_high" <?php echo $sort === 'budget_high' ? 'selected' : ''; ?>>Budget (High to Low)</option>
                        <option value="budget_low" <?php echo $sort === 'budget_low' ? 'selected' : ''; ?>>Budget (Low to High)</option>
                        <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>By Status</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn">Apply Filters</button>
                <a href="admin_job_requests.php" class="btn btn-secondary" style="text-decoration: none; display: inline-block;">Reset Filters</a>
            </div>
        </form>
        
        <?php if (empty($jobs)): ?>
            <div class="no-records">
                <p>No job requests found matching your criteria.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Customer</th>
                        <th>Budget</th>
                        <th>Service</th>
                        <th>Created</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?php echo $job['id']; ?></td>
                            <td><?php echo htmlspecialchars($job['title']); ?></td>
                            <td><?php echo htmlspecialchars($job['customer_name']); ?></td>
                            <td>₹<?php echo number_format($job['budget'], 2); ?></td>
                            <td><?php echo htmlspecialchars($job['service_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                            <td>
                                <?php 
                                $status_class = 'status-';
                                switch ($job['status']) {
                                    case 'pending_approval': $status_class .= 'pending'; break;
                                    case 'open': $status_class .= 'open'; break;
                                    case 'assigned': $status_class .= 'assigned'; break;
                                    case 'in_progress': $status_class .= 'progress'; break;
                                    case 'completed': $status_class .= 'completed'; break;
                                    case 'cancelled': $status_class .= 'cancelled'; break;
                                    case 'rejected': $status_class .= 'rejected'; break;
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <a href="view_job.php?id=<?php echo $job['id']; ?>" class="action-btn view-btn">
                                    View
                                </a>
                                
                                <?php if ($job['status'] === 'pending_approval'): ?>
                                    <form method="POST" action="" class="action-form">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="action-btn approve-btn" onclick="return confirm('Are you sure you want to approve this job?');">
                                            Approve
                                        </button>
                                    </form>
                                    
                                    <form method="POST" action="" class="action-form">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn reject-btn" onclick="return confirm('Are you sure you want to reject this job?');">
                                            Reject
                                        </button>
                                    </form>
                                <?php elseif (!in_array($job['status'], ['completed', 'cancelled', 'rejected'])): ?>
                                    <form method="POST" action="" class="action-form">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="action-btn cancel-btn" onclick="return confirm('Are you sure you want to cancel this job?');">
                                            Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
