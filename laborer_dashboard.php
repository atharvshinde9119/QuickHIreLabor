<?php
require_once 'config.php';

// Check if user is logged in as laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get laborer details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$laborer = $stmt->get_result()->fetch_assoc();

// Get count of available jobs
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM jobs 
    WHERE status = 'open' AND laborer_id IS NULL OR laborer_id = 0
");
$stmt->execute();
$available_jobs = $stmt->get_result()->fetch_assoc()['count'];

// Get current jobs (assigned, in_progress)
$stmt = $conn->prepare("
    SELECT j.*, 
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           s.name as service_name,
           (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id AND receiver_id = ? AND is_read = 0) as unread_messages
    FROM jobs j
    JOIN users c ON j.customer_id = c.id
    LEFT JOIN services s ON j.service_id = s.id
    WHERE j.laborer_id = ? AND j.status IN ('assigned', 'in_progress')
    ORDER BY j.updated_at DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$current_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get job count statistics
$job_stats = [
    'assigned' => 0, 
    'in_progress' => 0,
    'completed' => 0
];

$stmt = $conn->prepare("
    SELECT status, COUNT(*) as count 
    FROM jobs 
    WHERE laborer_id = ? 
    GROUP BY status
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if (isset($job_stats[$row['status']])) {
        $job_stats[$row['status']] = $row['count'];
    }
}

// Get total earnings
$stmt = $conn->prepare("
    SELECT SUM(budget) as total 
    FROM jobs 
    WHERE laborer_id = ? AND status = 'completed'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_earnings = $stmt->get_result()->fetch_assoc()['total'] ?: 0;

// Get unread messages
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM job_messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_messages = $stmt->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laborer Dashboard | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 36px;
            margin-bottom: 10px;
            color: #4CAF50;
        }
        
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .action-card i {
            font-size: 36px;
            margin-bottom: 15px;
            color: #4CAF50;
        }
        
        .action-card.messages i {
            color: #9C27B0;
        }
        
        .action-card.jobs i {
            color: #2196F3;
        }
        
        .action-card.settings i {
            color: #FF9800;
        }
        
        .action-card a {
            display: block;
            text-decoration: none;
            color: #333;
        }
        
        .action-card h3 {
            margin: 0 0 10px 0;
        }
        
        .action-card p {
            color: #666;
            margin: 0;
        }
        
        .section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .section-title {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .job-card {
            background: #f9f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #4CAF50;
        }
        
        .job-card.assigned {
            border-left-color: #2196F3;
        }
        
        .job-card.in-progress {
            border-left-color: #FF9800;
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .job-title {
            font-weight: bold;
            font-size: 18px;
            margin: 0;
        }
        
        .job-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 12px;
            background: #4CAF50;
            color: white;
        }
        
        .job-status.assigned {
            background: #2196F3;
        }
        
        .job-status.in-progress {
            background: #FF9800;
        }
        
        .job-details {
            margin-bottom: 15px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .detail-item {
            padding: 5px 0;
        }
        
        .detail-label {
            font-weight: bold;
            color: #666;
            margin-right: 5px;
        }
        
        .job-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
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
            background: #2196F3;
            color: white;
        }
        
        .btn-warning {
            background: #FF9800;
            color: white;
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-message {
            background: #9C27B0;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            background: #f5f5f5;
            border-radius: 8px;
            color: #666;
        }
        
        .message-badge {
            display: inline-block;
            background: #f44336;
            color: white;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            text-align: center;
            line-height: 18px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>
    
    <div class="container">
        <div class="welcome-banner">
            <h1>Welcome, <?php echo $laborer['first_name']; ?>!</h1>
            <p>Here's your activity overview</p>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Messaging feature notification -->
        <div class="alert info" style="background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; margin-bottom: 20px; padding: 15px; border-radius: 4px;">
            <h4 style="margin-top: 0;"><i class="fas fa-info-circle"></i> Coming Soon!</h4>
            <p>Enhanced messaging functionality will be implemented soon, allowing you to communicate with customers more effectively. Stay tuned for updates!</p>
        </div>
        
        <div class="quick-actions">
            <div class="action-card jobs">
                <a href="laborer_available_jobs.php">
                    <i class="fas fa-briefcase"></i>
                    <h3>Available Jobs</h3>
                    <p>Browse new job opportunities</p>
                </a>
            </div>
            
            <div class="action-card messages">
                <a href="laborer_messages.php">
                    <i class="fas fa-comments"></i>
                    <h3>Messages</h3>
                    <p>
                        Check your conversations
                        <?php if ($unread_messages > 0): ?>
                            <span class="message-badge"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </p>
                </a>
            </div>
            
            <div class="action-card settings">
                <a href="laborer_profile.php">
                    <i class="fas fa-user-cog"></i>
                    <h3>Update Profile</h3>
                    <p>Manage your skills and profile</p>
                </a>
            </div>
        </div>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <i class="fas fa-clipboard-list"></i>
                <div class="value"><?php echo $available_jobs; ?></div>
                <div class="label">Available Jobs</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-tasks"></i>
                <div class="value"><?php echo $job_stats['assigned'] + $job_stats['in_progress']; ?></div>
                <div class="label">Active Jobs</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="value"><?php echo $job_stats['completed']; ?></div>
                <div class="label">Completed Jobs</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-rupee-sign"></i>
                <div class="value">₹<?php echo number_format($total_earnings, 0); ?></div>
                <div class="label">Total Earnings</div>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">Current Jobs</h2>
            
            <?php if (empty($current_jobs)): ?>
                <div class="empty-state">
                    <p>You don't have any active jobs at the moment.</p>
                    <a href="laborer_available_jobs.php" class="btn btn-primary">Find Jobs</a>
                </div>
            <?php else: ?>
                <?php foreach ($current_jobs as $job): ?>
                    <div class="job-card <?php echo strtolower($job['status']); ?>">
                        <div class="job-header">
                            <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                            <span class="job-status <?php echo strtolower($job['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                            </span>
                        </div>
                        
                        <div class="job-details">
                            <div class="detail-item">
                                <span class="detail-label">Customer:</span>
                                <?php echo htmlspecialchars($job['customer_name']); ?>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Budget:</span>
                                ₹<?php echo number_format($job['budget'], 2); ?>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Location:</span>
                                <?php echo htmlspecialchars($job['location']); ?>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Service:</span>
                                <?php echo htmlspecialchars($job['service_name'] ?? 'Not specified'); ?>
                            </div>
                        </div>
                        
                        <div class="job-actions">
                            <?php if ($job['status'] === 'assigned'): ?>
                                <form method="POST" action="update_job_status.php">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-play"></i> Start Work
                                    </button>
                                </form>
                            <?php elseif ($job['status'] === 'in_progress'): ?>
                                <form method="POST" action="update_job_status.php">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Mark as Completed
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="job_messages.php?job_id=<?php echo $job['id']; ?>" class="btn btn-message">
                                <i class="fas fa-comment"></i> Message Customer
                                <?php if ($job['unread_messages'] > 0): ?>
                                    <span class="message-badge"><?php echo $job['unread_messages']; ?></span>
                                <?php endif; ?>
                            </a>
                            
                            <a href="view_job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
