<?php
require_once 'config.php';

// Check if user is logged in as laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all jobs for this laborer
$stmt = $conn->prepare("
    SELECT j.*, 
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           c.phone as customer_phone,
           s.name as service_name,
           (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id AND receiver_id = ? AND is_read = 0) as unread_messages,
           (SELECT MAX(created_at) FROM job_messages WHERE job_id = j.id) as last_message_time
    FROM jobs j
    JOIN users c ON j.customer_id = c.id
    LEFT JOIN services s ON j.service_id = s.id
    WHERE j.laborer_id = ?
    ORDER BY 
        CASE j.status
            WHEN 'assigned' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'cancelled' THEN 4
            WHEN 'rejected' THEN 5
            ELSE 6
        END,
        j.updated_at DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$all_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group jobs by status
$jobs = [
    'active' => [],
    'completed' => [],
    'cancelled' => []
];

// Categorize jobs
foreach ($all_jobs as $job) {
    if ($job['status'] == 'assigned' || $job['status'] == 'in_progress') {
        $jobs['active'][] = $job;
    } elseif ($job['status'] == 'completed') {
        $jobs['completed'][] = $job;
    } else {
        $jobs['cancelled'][] = $job;
    }
}

// Job statistics
$total_jobs = count($all_jobs);
$active_jobs = count($jobs['active']);
$completed_jobs = count($jobs['completed']);
$cancelled_jobs = count($jobs['cancelled']);

// Calculate earnings
$stmt = $conn->prepare("
    SELECT SUM(budget) as total_earnings
    FROM jobs
    WHERE laborer_id = ? AND status = 'completed'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$total_earnings = $result['total_earnings'] ? $result['total_earnings'] : 0;

// Get average rating
try {
    // First check if the ratings table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'ratings'");
    if ($tableCheck->num_rows === 0) {
        // Ratings table doesn't exist - create it
        $conn->query("
            CREATE TABLE IF NOT EXISTS ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_id INT NOT NULL,
                laborer_id INT NOT NULL,
                customer_id INT NOT NULL,
                rating DECIMAL(2,1) NOT NULL,
                comment TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                FOREIGN KEY (laborer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $avg_rating = 0;
        $rating_count = 0;
    } else {
        // Check if laborer_id column exists in the ratings table
        $columnCheck = $conn->query("SHOW COLUMNS FROM ratings LIKE 'laborer_id'");
        if ($columnCheck->num_rows === 0) {
            // The laborer_id column doesn't exist - handle this case
            $avg_rating = 0;
            $rating_count = 0;
        } else {
            // Column exists, proceed with query
            $stmt = $conn->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count
                FROM ratings
                WHERE laborer_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $rating_data = $stmt->get_result()->fetch_assoc();
            $avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
            $rating_count = $rating_data['rating_count'] ? $rating_data['rating_count'] : 0;
        }
    }
} catch (Exception $e) {
    // If any error occurs, default to zero rating
    error_log("Error in laborer_my_jobs.php: " . $e->getMessage());
    $avg_rating = 0;
    $rating_count = 0;
}

// Filter jobs if search term provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = sanitize_input($_GET['search']);
    
    foreach ($jobs as $status => $job_list) {
        $filtered_jobs = [];
        
        foreach ($job_list as $job) {
            if (
                stripos($job['title'], $search) !== false ||
                stripos($job['description'], $search) !== false ||
                stripos($job['customer_name'], $search) !== false ||
                stripos($job['location'], $search) !== false
            ) {
                $filtered_jobs[] = $job;
            }
        }
        
        $jobs[$status] = $filtered_jobs;
    }
}

// Set the active tab
$active_tab = 'active';
if (isset($_GET['tab']) && in_array($_GET['tab'], ['active', 'completed', 'cancelled'])) {
    $active_tab = $_GET['tab'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Jobs | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .search-box {
            display: flex;
            max-width: 300px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            background: #fff;
            color: #333;
        }
        
        .search-box button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 0 15px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .tab-nav {
            display: flex;
            background: #f1f1f1;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .tab-link {
            padding: 12px 20px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .tab-link.active {
            background: #4CAF50;
            color: white;
        }
        
        .tab-count {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            margin-left: 8px;
        }
        
        .tab-content {
            margin-bottom: 30px;
        }
        
        .job-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .job-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .job-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        
        .job-status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
        }
        
        .status-assigned { background: #007bff; }
        .status-in_progress { background: #6610f2; }
        .status-completed { background: #28a745; }
        .status-cancelled { background: #dc3545; }
        
        .job-content {
            padding: 20px;
        }
        
        .job-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .job-info {
            margin-bottom: 5px;
        }
        
        .job-info-label {
            font-weight: bold;
            color: #666;
        }
        
        .job-description {
            grid-column: 1 / -1;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .job-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-purple {
            background: #6f42c1;
            color: white;
        }
        
        .empty-state {
            background: white;
            padding: 40px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 20px;
        }
        
        .price-tag {
            background: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .message-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>My Jobs</h1>
            
            <form class="search-box" method="GET" action="laborer_my_jobs.php">
                <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                <input type="text" name="search" placeholder="Search jobs..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-briefcase"></i>
                <div class="stat-value"><?php echo $total_jobs; ?></div>
                <div class="stat-label">Total Jobs</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-tasks"></i>
                <div class="stat-value"><?php echo $active_jobs; ?></div>
                <div class="stat-label">Active Jobs</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-rupee-sign"></i>
                <div class="stat-value">₹<?php echo number_format($total_earnings, 0); ?></div>
                <div class="stat-label">Total Earnings</div>
            </div>
            
            <div class="stat-card">
                <div class="rating-stars">
                    <?php 
                    $full_stars = floor($avg_rating);
                    $half_star = $avg_rating - $full_stars >= 0.5;
                    
                    for($i = 1; $i <= 5; $i++) {
                        if($i <= $full_stars) {
                            echo '<i class="fas fa-star"></i>';
                        } elseif($i == $full_stars + 1 && $half_star) {
                            echo '<i class="fas fa-star-half-alt"></i>';
                        } else {
                            echo '<i class="far fa-star"></i>';
                        }
                    }
                    ?>
                </div>
                <div class="stat-value"><?php echo $avg_rating; ?> <small>(<?php echo $rating_count; ?>)</small></div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>
        
        <!-- Tab navigation -->
        <div class="tab-nav">
            <a href="laborer_my_jobs.php?tab=active<?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="tab-link <?php echo $active_tab == 'active' ? 'active' : ''; ?>">
                Active Jobs
                <?php if($active_jobs > 0): ?>
                    <span class="tab-count"><?php echo $active_jobs; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="laborer_my_jobs.php?tab=completed<?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="tab-link <?php echo $active_tab == 'completed' ? 'active' : ''; ?>">
                Completed Jobs
                <?php if($completed_jobs > 0): ?>
                    <span class="tab-count"><?php echo $completed_jobs; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="laborer_my_jobs.php?tab=cancelled<?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="tab-link <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>">
                Cancelled/Rejected Jobs
                <?php if($cancelled_jobs > 0): ?>
                    <span class="tab-count"><?php echo $cancelled_jobs; ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- Active jobs -->
        <?php if($active_tab == 'active'): ?>
            <div class="tab-content">
                <?php if(empty($jobs['active'])): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No active jobs found</h3>
                        <p>Your ongoing jobs will appear here</p>
                        <a href="laborer_available_jobs.php" class="btn btn-primary">Find Jobs</a>
                    </div>
                <?php else: ?>
                    <?php foreach($jobs['active'] as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <span class="job-status-badge status-<?php echo $job['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                </span>
                            </div>
                            
                            <div class="job-content">
                                <div class="job-grid">
                                    <div>
                                        <div class="job-info">
                                            <span class="job-info-label">Customer:</span> 
                                            <?php echo htmlspecialchars($job['customer_name']); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Service:</span> 
                                            <?php echo htmlspecialchars($job['service_name'] ?? 'Not specified'); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Start Date:</span> 
                                            <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="job-info">
                                            <span class="job-info-label">Phone:</span> 
                                            <?php echo htmlspecialchars($job['customer_phone']); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Location:</span> 
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Budget:</span> 
                                            <span class="price-tag">₹<?php echo number_format($job['budget'], 0); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="job-description">
                                        <strong>Requirements:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                    </div>
                                </div>
                                
                                <div class="job-actions">
                                    <?php if($job['status'] == 'assigned'): ?>
                                        <form method="POST" action="update_job_status.php">
                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to start this job?');">
                                                <i class="fas fa-play"></i> Start Work
                                            </button>
                                        </form>
                                    <?php elseif($job['status'] == 'in_progress'): ?>
                                        <form method="POST" action="update_job_status.php">
                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to mark this job as completed? This action cannot be undone.');">
                                                <i class="fas fa-check"></i> Mark as Completed
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="job_messages.php?job_id=<?php echo $job['id']; ?>" class="btn btn-purple">
                                        <i class="fas fa-comments"></i> Messages
                                        <?php if($job['unread_messages'] > 0): ?>
                                            <span class="message-badge"><?php echo $job['unread_messages']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    
                                    <a href="tel:<?php echo $job['customer_phone']; ?>" class="btn btn-info">
                                        <i class="fas fa-phone"></i> Call Customer
                                    </a>
                                    
                                    <a href="https://maps.google.com/?q=<?php echo urlencode($job['location']); ?>" target="_blank" class="btn btn-warning">
                                        <i class="fas fa-map-marker-alt"></i> View on Map
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Completed jobs -->
        <?php if($active_tab == 'completed'): ?>
            <div class="tab-content">
                <?php if(empty($jobs['completed'])): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No completed jobs yet</h3>
                        <p>Jobs you complete will be listed here</p>
                    </div>
                <?php else: ?>
                    <?php foreach($jobs['completed'] as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <span class="job-status-badge status-completed">
                                    Completed
                                </span>
                            </div>
                            
                            <div class="job-content">
                                <div class="job-grid">
                                    <div>
                                        <div class="job-info">
                                            <span class="job-info-label">Customer:</span> 
                                            <?php echo htmlspecialchars($job['customer_name']); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Service:</span> 
                                            <?php echo htmlspecialchars($job['service_name'] ?? 'Not specified'); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Completed On:</span> 
                                            <?php echo date('M j, Y', strtotime($job['updated_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="job-info">
                                            <span class="job-info-label">Location:</span> 
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Earnings:</span> 
                                            <span class="price-tag">₹<?php echo number_format($job['budget'], 0); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="job-actions">
                                    <a href="job_messages.php?job_id=<?php echo $job['id']; ?>" class="btn btn-purple">
                                        <i class="fas fa-comments"></i> Messages
                                        <?php if($job['unread_messages'] > 0): ?>
                                            <span class="message-badge"><?php echo $job['unread_messages']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    
                                    <a href="view_job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-info">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Cancelled jobs -->
        <?php if($active_tab == 'cancelled'): ?>
            <div class="tab-content">
                <?php if(empty($jobs['cancelled'])): ?>
                    <div class="empty-state">
                        <i class="fas fa-ban"></i>
                        <h3>No cancelled or rejected jobs</h3>
                        <p>Any cancelled or rejected jobs will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach($jobs['cancelled'] as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <span class="job-status-badge status-cancelled">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                </span>
                            </div>
                            
                            <div class="job-content">
                                <div class="job-grid">
                                    <div>
                                        <div class="job-info">
                                            <span class="job-info-label">Customer:</span> 
                                            <?php echo htmlspecialchars($job['customer_name']); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Date:</span> 
                                            <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="job-info">
                                            <span class="job-info-label">Location:</span> 
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        
                                        <div class="job-info">
                                            <span class="job-info-label">Budget:</span> 
                                            <span class="price-tag">₹<?php echo number_format($job['budget'], 0); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="job-actions">
                                    <a href="view_job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-info">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
