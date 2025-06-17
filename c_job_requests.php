<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Setup filter parameters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'newest';

// Prepare base SQL query
$sql = "
    SELECT j.*, s.name as service_name, 
           COUNT(DISTINCT ja.id) as application_count
    FROM jobs j
    LEFT JOIN services s ON j.service_id = s.id
    LEFT JOIN job_applications ja ON j.id = ja.job_id
    WHERE j.customer_id = ?
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
    $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR s.name LIKE ?)";
}

// Group by job ID
$sql .= " GROUP BY j.id";

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
    case 'applications':
        $sql .= " ORDER BY application_count DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY j.created_at DESC";
        break;
}

// Prepare and execute query
$stmt = $conn->prepare($sql);

// Bind parameters based on filters - Fixed to use references
$params = array();
$types = "i"; // For user_id
$params[] = &$user_id;

if ($status_filter !== 'all') {
    $types .= "s";
    $status_param = $status_filter;
    $params[] = &$status_param;
}

if (!empty($search)) {
    $types .= "sss";
    $search_param = "%$search%";
    $search_param2 = $search_param;
    $search_param3 = $search_param;
    $params[] = &$search_param;
    $params[] = &$search_param2;
    $params[] = &$search_param3;
}

// Apply the parameters to the prepared statement
if (!empty($params)) {
    // Add the types string as the first parameter
    array_unshift($params, $types);
    call_user_func_array(array($stmt, 'bind_param'), $params);
}

$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all job statuses for filter dropdown
$statuses = array(
    'all' => 'All Statuses',
    'pending_approval' => 'Pending Approval',
    'open' => 'Open',
    'assigned' => 'Assigned',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
);

// Handle job cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $job_id = (int)$_POST['job_id'];
    
    // Check if the job belongs to the customer
    $check_sql = "SELECT id FROM jobs WHERE id = ? AND customer_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $job_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update job status
        $update_sql = "UPDATE jobs SET status = 'cancelled' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $job_id);
        
        if ($update_stmt->execute()) {
            // Add to status history
            $history_sql = "INSERT INTO job_status_history (job_id, status, notes) VALUES (?, 'cancelled', 'Cancelled by customer')";
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->bind_param("i", $job_id);
            $history_stmt->execute();
            
            // Set success message
            $_SESSION['success_msg'] = "Job has been cancelled successfully.";
        } else {
            $_SESSION['error_msg'] = "Error cancelling job. Please try again.";
        }
    } else {
        $_SESSION['error_msg'] = "You don't have permission to cancel this job.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: c_job_requests.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Job Requests - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .job-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .job-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .job-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .job-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            color: #333;
        }
        
        .job-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
        }
        
        .status-pending_approval { background: #ffc107; color: #212529; }
        .status-admin_approval { background: #fd7e14; color: white; }
        .status-open { background: #17a2b8; }
        .status-assigned { background: #007bff; }
        .status-in_progress { background: #6610f2; }
        .status-completed { background: #28a745; }
        .status-cancelled { background: #dc3545; }
        
        .job-body {
            padding: 15px;
        }
        
        .job-detail {
            margin-bottom: 10px;
            display: flex;
        }
        
        .job-detail-label {
            font-weight: bold;
            min-width: 100px;
        }
        
        .job-footer {
            padding: 15px;
            background: #f9f9f9;
            display: flex;
            justify-content: space-between;
        }
        
        .job-action {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-view {
            background: #4CAF50;
            color: white;
        }
        
        .btn-cancel {
            background: #dc3545;
            color: white;
        }
        
        .btn-edit {
            background: #007bff;
            color: white;
        }
        
        .application-count {
            background: #6c757d;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .status-info {
            display: inline-block;
            margin-left: 10px;
            cursor: pointer;
            color: #007bff;
            font-size: 14px;
        }
        
        .status-tooltip {
            display: none;
            position: absolute;
            background: #333;
            color: white;
            padding: 10px;
            border-radius: 4px;
            max-width: 300px;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .filter-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .apply-filter-btn {
            background: #4CAF50;
            color: white;
        }
        
        .reset-filter-btn {
            background: #f1f1f1;
            color: #333;
        }
        
        .summary-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 150px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .results-info {
            margin-bottom: 10px;
            color: #666;
        }
        
        .no-results {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .no-results i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .create-job-btn {
            margin-top: 15px;
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>
    
    <div class="container">
        <h1>My Job Requests</h1>
        
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert success">
                <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert error">
                <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-container">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Date Range</label>
                        <select id="date" name="date">
                            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort">Sort By</label>
                        <select id="sort" name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="budget_high" <?php echo $sort === 'budget_high' ? 'selected' : ''; ?>>Budget (High to Low)</option>
                            <option value="budget_low" <?php echo $sort === 'budget_low' ? 'selected' : ''; ?>>Budget (Low to High)</option>
                            <option value="applications" <?php echo $sort === 'applications' ? 'selected' : ''; ?>>Most Applications</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="filter-btn apply-filter-btn">Apply Filters</button>
                    <a href="c_job_requests.php" class="filter-btn reset-filter-btn">Reset Filters</a>
                </div>
            </form>
        </div>
        
        <!-- Summary Stats -->
        <div class="summary-stats">
            <?php
            $total_jobs = count($jobs);
            $active_jobs = 0;
            $completed_jobs = 0;
            $pending_jobs = 0;
            
            foreach ($jobs as $job) {
                if ($job['status'] === 'completed') {
                    $completed_jobs++;
                } elseif ($job['status'] === 'pending_approval') {
                    $pending_jobs++;
                } elseif (in_array($job['status'], ['assigned', 'in_progress', 'open'])) {
                    $active_jobs++;
                }
            }
            ?>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_jobs; ?></div>
                <div class="stat-label">Total Jobs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_jobs; ?></div>
                <div class="stat-label">Active Jobs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $completed_jobs; ?></div>
                <div class="stat-label">Completed Jobs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending_jobs; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>
        
        <?php if (empty($jobs)): ?>
            <div class="no-results">
                <i class="fas fa-clipboard-list"></i>
                <h3>No job requests found</h3>
                <p>You haven't submitted any job requests yet or no jobs match your filters.</p>
                <a href="create_job.php" class="create-job-btn">Create Job Request</a>
            </div>
        <?php else: ?>
            <div class="results-info">
                Showing <?php echo count($jobs); ?> job request(s)
            </div>
            
            <div class="job-list">
                <?php foreach ($jobs as $job): ?>
                    <div class="job-card">
                        <div class="job-header">
                            <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                            <span class="job-status status-<?php echo $job['status']; ?>">
                                <?php 
                                $status_text = ucfirst(str_replace('_', ' ', $job['status']));
                                echo $status_text; 
                                ?>
                                
                                <?php if ($job['status'] === 'pending_approval' || $job['status'] === 'admin_approval'): ?>
                                <span class="status-info" title="Click for more info" data-tooltip-id="tooltip-<?php echo $job['id']; ?>">?</span>
                                <div class="status-tooltip" id="tooltip-<?php echo $job['id']; ?>">
                                    <?php if ($job['status'] === 'pending_approval'): ?>
                                        Your job is awaiting initial review before being posted. This usually takes 1-2 business days.
                                    <?php elseif ($job['status'] === 'admin_approval'): ?>
                                        Your job is being reviewed by our administrators before being published. This helps ensure all jobs meet our guidelines.
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="job-body">
                            <div class="job-detail">
                                <span class="job-detail-label">Service:</span>
                                <span><?php echo htmlspecialchars($job['service_name']); ?></span>
                            </div>
                            <div class="job-detail">
                                <span class="job-detail-label">Budget:</span>
                                <span>₹<?php echo number_format($job['budget'], 2); ?></span>
                            </div>
                            <div class="job-detail">
                                <span class="job-detail-label">Posted:</span>
                                <span><?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                            </div>
                            <div class="job-detail">
                                <span class="job-detail-label">Applications:</span>
                                <span><?php echo $job['application_count']; ?></span>
                            </div>
                        </div>
                        <div class="job-footer">
                            <div>
                                <a href="view_job_details.php?id=<?php echo $job['id']; ?>" class="job-action btn-view">View Details</a>
                                <?php if (in_array($job['status'], ['pending_approval', 'admin_approval', 'open'])): ?>
                                    <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="job-action btn-edit">Edit</a>
                                <?php endif; ?>
                            </div>
                            <?php if (in_array($job['status'], ['pending_approval', 'admin_approval', 'open', 'assigned'])): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this job?');">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <button type="submit" class="job-action btn-cancel">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Toggle tooltip visibility
        document.querySelectorAll('.status-info').forEach(info => {
            info.addEventListener('click', function() {
                const tooltipId = this.getAttribute('data-tooltip-id');
                const tooltip = document.getElementById(tooltipId);
                
                if (tooltip.style.display === 'block') {
                    tooltip.style.display = 'none';
                } else {
                    // Hide all other tooltips
                    document.querySelectorAll('.status-tooltip').forEach(tip => {
                        tip.style.display = 'none';
                    });
                    
                    // Position and show this tooltip
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.right + 'px';
                    tooltip.style.top = rect.top + 'px';
                    tooltip.style.display = 'block';
                }
            });
        });
        
        // Close tooltips when clicking elsewhere
        document.addEventListener('click', function(event) {
            if (!event.target.classList.contains('status-info')) {
                document.querySelectorAll('.status-tooltip').forEach(tooltip => {
                    tooltip.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>
