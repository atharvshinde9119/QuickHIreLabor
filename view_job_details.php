<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if job id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    if ($user_role === 'laborer') {
        header("Location: laborer_my_jobs.php");
    } else if ($user_role === 'customer') {
        header("Location: c_job_requests.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

$job_id = (int)$_GET['id'];

// Get job details based on user role to ensure they can only view appropriate jobs
if ($user_role === 'laborer') {
    $stmt = $conn->prepare("
        SELECT j.*, 
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               c.phone as customer_phone,
               c.email as customer_email,
               s.name as service_name,
               (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id) as message_count,
               (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM jobs j
        JOIN users c ON j.customer_id = c.id
        LEFT JOIN services s ON j.service_id = s.id
        WHERE j.id = ? AND j.laborer_id = ?
    ");
    $stmt->bind_param("iii", $user_id, $job_id, $user_id);
} else if ($user_role === 'customer') {
    $stmt = $conn->prepare("
        SELECT j.*, 
               CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
               l.phone as laborer_phone,
               l.email as laborer_email,
               s.name as service_name,
               (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id) as message_count,
               (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM jobs j
        LEFT JOIN users l ON j.laborer_id = l.id
        LEFT JOIN services s ON j.service_id = s.id
        WHERE j.id = ? AND j.customer_id = ?
    ");
    $stmt->bind_param("iii", $user_id, $job_id, $user_id);
} else {
    // Admin can view any job
    $stmt = $conn->prepare("
        SELECT j.*, 
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               c.phone as customer_phone,
               c.email as customer_email,
               CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
               l.phone as laborer_phone,
               l.email as laborer_email,
               s.name as service_name,
               (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id) as message_count
        FROM jobs j
        JOIN users c ON j.customer_id = c.id
        LEFT JOIN users l ON j.laborer_id = l.id
        LEFT JOIN services s ON j.service_id = s.id
        WHERE j.id = ?
    ");
    $stmt->bind_param("i", $job_id);
}

$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

// If job not found or user doesn't have access, redirect to appropriate page
if (!$job) {
    $_SESSION['error'] = "Job not found or you don't have permission to view it.";
    if ($user_role === 'laborer') {
        header("Location: laborer_my_jobs.php");
    } else if ($user_role === 'customer') {
        header("Location: c_job_requests.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

// Calculate job duration
$start_date = new DateTime($job['created_at']);
$now = new DateTime();
if ($job['status'] === 'completed') {
    $end_date = new DateTime($job['updated_at']);
    $duration = $start_date->diff($end_date);
} else {
    $duration = $start_date->diff($now);
}

// Get job timeline events
$timeline = [];

// Always add job creation
$timeline[] = [
    'date' => $job['created_at'],
    'event' => 'Job Created',
    'description' => 'Job was posted by the customer'
];

// Check for status history
$has_history_table = false;

// First check if the job_status_history table exists
$table_check = $conn->query("SHOW TABLES LIKE 'job_status_history'");
if ($table_check && $table_check->num_rows > 0) {
    $has_history_table = true;
    
    $stmt = $conn->prepare("
        SELECT * FROM job_status_history 
        WHERE job_id = ? 
        ORDER BY created_at ASC
    ");

    if ($stmt) {
        $stmt->bind_param("i", $job_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($status = $result->fetch_assoc()) {
                $timeline[] = [
                    'date' => $status['created_at'],
                    'event' => 'Status Changed to ' . ucfirst($status['status']),
                    'description' => $status['notes'] ?? 'Job status was updated'
                ];
            }
        }
    }
}

// If the table doesn't exist or there was an error, add current status
if (!$has_history_table) {
    // If the job_status_history table doesn't exist, add current status
    if ($job['status'] !== 'pending_approval' && $job['status'] !== 'open') {
        $timeline[] = [
            'date' => $job['updated_at'],
            'event' => 'Status Changed to ' . ucfirst(str_replace('_', ' ', $job['status'])),
            'description' => 'Job status was updated'
        ];
    }
}

// Sort timeline by date
usort($timeline, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Get job rating if completed
$rating = null;
if ($job['status'] === 'completed') {
    try {
        // Check if ratings table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'ratings'");
        if ($tableCheck->num_rows > 0) {
            // Check if job has rating
            $ratingStmt = $conn->prepare("SELECT * FROM ratings WHERE job_id = ? LIMIT 1");
            $ratingStmt->bind_param("i", $job_id);
            $ratingStmt->execute();
            $rating = $ratingStmt->get_result()->fetch_assoc();
        }
    } catch (Exception $e) {
        // Ignore rating if table doesn't exist
    }
}

// Determine back URL based on role
if ($user_role === 'laborer') {
    $back_url = "laborer_my_jobs.php";
} else if ($user_role === 'customer') {
    $back_url = "c_job_requests.php";
} else {
    $back_url = "admin_job_requests.php";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .back-link {
            margin-bottom: 20px;
        }
        
        .back-link a {
            display: inline-flex;
            align-items: center;
            color: #666;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            color: #4CAF50;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        .job-header {
            background: #4CAF50;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .job-status {
            position: absolute;
            top: 0;
            right: 0;
            padding: 5px 15px;
            background: rgba(0,0,0,0.2);
            border-radius: 0 0 0 8px;
            font-size: 12px;
        }
        
        .job-title {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        
        .job-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
        }
        
        .job-meta-item {
            display: flex;
            align-items: center;
        }
        
        .job-meta-item i {
            margin-right: 5px;
        }
        
        .job-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .job-card-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .job-card-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .job-card-body {
            padding: 20px;
        }
        
        .job-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .job-detail {
            margin-bottom: 15px;
        }
        
        .job-detail-label {
            font-weight: bold;
            color: #666;
            display: block;
            margin-bottom: 5px;
        }
        
        .job-description {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 8px;
            width: 2px;
            height: 100%;
            background: #ddd;
        }
        
        .timeline-item {
            margin-bottom: 20px;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            top: 5px;
            left: -22px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4CAF50;
        }
        
        .timeline-date {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .timeline-event {
            font-weight: bold;
        }
        
        .timeline-description {
            font-size: 14px;
            color: #555;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
        
        .btn-purple {
            background: #6f42c1;
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
        
        .rating-display {
            display: flex;
            align-items: center;
            margin-top: 15px;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 18px;
            margin-right: 10px;
        }
        
        .rating-value {
            font-weight: bold;
            margin-right: 5px;
        }
        
        .rating-comment {
            font-style: italic;
            color: #666;
            margin-top: 5px;
        }
        
        .price-tag {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
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
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php 
    if ($user_role === 'laborer') {
        include 'includes/laborer_sidebar.php';
    } else if ($user_role === 'customer') {
        include 'includes/customer_sidebar.php';
    } else {
        include 'includes/admin_sidebar.php';
    }
    ?>

    <div class="container">
        <div class="back-link">
            <a href="<?php echo $back_url; ?>">
                <i class="fas fa-arrow-left"></i> Back to <?php echo $user_role === 'laborer' ? 'My Jobs' : ($user_role === 'customer' ? 'Dashboard' : 'Job Requests'); ?>
            </a>
        </div>
        
        <div class="job-header">
            <div class="job-status">
                <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
            </div>
            <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>
            <div class="job-meta">
                <div class="job-meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    Posted: <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                </div>
                <div class="job-meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($job['location']); ?>
                </div>
                <div class="job-meta-item">
                    <i class="fas fa-rupee-sign"></i>
                    Budget: <strong>₹<?php echo number_format($job['budget'], 0); ?></strong>
                </div>
                <?php if ($job['service_name']): ?>
                <div class="job-meta-item">
                    <i class="fas fa-cog"></i>
                    Service: <?php echo htmlspecialchars($job['service_name']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="job-card">
            <div class="job-card-header">
                <h3>Job Description</h3>
            </div>
            <div class="job-card-body">
                <div class="job-description">
                    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                </div>
            </div>
        </div>
        
        <div class="job-card">
            <div class="job-card-header">
                <h3><?php echo $user_role === 'laborer' ? 'Customer' : 'Contact'; ?> Information</h3>
            </div>
            <div class="job-card-body">
                <div class="job-details-grid">
                    <?php if ($user_role === 'laborer'): ?>
                    <div class="job-detail">
                        <span class="job-detail-label">Customer Name:</span>
                        <?php echo htmlspecialchars($job['customer_name']); ?>
                    </div>
                    <div class="job-detail">
                        <span class="job-detail-label">Phone:</span>
                        <a href="tel:<?php echo $job['customer_phone']; ?>"><?php echo htmlspecialchars($job['customer_phone']); ?></a>
                    </div>
                    <div class="job-detail">
                        <span class="job-detail-label">Email:</span>
                        <a href="mailto:<?php echo $job['customer_email']; ?>"><?php echo htmlspecialchars($job['customer_email']); ?></a>
                    </div>
                    <?php elseif ($user_role === 'customer' && $job['laborer_id']): ?>
                    <div class="job-detail">
                        <span class="job-detail-label">Laborer Name:</span>
                        <?php echo htmlspecialchars($job['laborer_name']); ?>
                    </div>
                    <div class="job-detail">
                        <span class="job-detail-label">Phone:</span>
                        <a href="tel:<?php echo $job['laborer_phone']; ?>"><?php echo htmlspecialchars($job['laborer_phone']); ?></a>
                    </div>
                    <div class="job-detail">
                        <span class="job-detail-label">Email:</span>
                        <a href="mailto:<?php echo $job['laborer_email']; ?>"><?php echo htmlspecialchars($job['laborer_email']); ?></a>
                    </div>
                    <?php elseif ($user_role === 'admin'): ?>
                    <div class="job-detail">
                        <span class="job-detail-label">Customer:</span>
                        <?php echo htmlspecialchars($job['customer_name']); ?> (<?php echo htmlspecialchars($job['customer_phone']); ?>)
                    </div>
                    <div class="job-detail">
                        <span class="job-detail-label">Laborer:</span>
                        <?php echo $job['laborer_name'] ? htmlspecialchars($job['laborer_name']) . ' (' . htmlspecialchars($job['laborer_phone']) . ')' : 'Not assigned'; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="job-card">
            <div class="job-card-header">
                <h3>Job Timeline & Status</h3>
            </div>
            <div class="job-card-body">
                <div class="job-detail">
                    <span class="job-detail-label">Current Status:</span>
                    <strong><?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></strong>
                </div>
                
                <div class="job-detail">
                    <span class="job-detail-label">Job Duration:</span>
                    <?php
                    if ($duration->days > 0) {
                        echo $duration->format('%a days');
                    } elseif ($duration->h > 0) {
                        echo $duration->format('%h hours');
                    } else {
                        echo $duration->format('%i minutes');
                    }
                    ?>
                </div>
                
                <div class="timeline">
                    <?php foreach ($timeline as $event): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y g:i A', strtotime($event['date'])); ?></div>
                        <div class="timeline-event"><?php echo $event['event']; ?></div>
                        <div class="timeline-description"><?php echo $event['description']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($rating): ?>
                <div class="job-detail">
                    <span class="job-detail-label">Customer Rating:</span>
                    <div class="rating-display">
                        <div class="rating-stars">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating['rating']) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($i - 0.5 <= $rating['rating']) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <span class="rating-value"><?php echo $rating['rating']; ?>/5</span>
                    </div>
                    <?php if (!empty($rating['comment'])): ?>
                    <div class="rating-comment">"<?php echo htmlspecialchars($rating['comment']); ?>"</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="action-buttons">
            <?php if ($user_role === 'laborer'): ?>
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Mark as Completed
                        </button>
                    </form>
                <?php endif; ?>
                
                <a href="job_messages.php?job_id=<?php echo $job['id']; ?>" class="btn btn-purple">
                    <i class="fas fa-comments"></i> Messages
                    <?php if (isset($job['unread_count']) && $job['unread_count'] > 0): ?>
                        <span class="message-badge"><?php echo $job['unread_count']; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="tel:<?php echo $job['customer_phone']; ?>" class="btn btn-info">
                    <i class="fas fa-phone"></i> Call Customer
                </a>
                
                <a href="https://maps.google.com/?q=<?php echo urlencode($job['location']); ?>" target="_blank" class="btn btn-warning">
                    <i class="fas fa-map-marker-alt"></i> View on Map
                </a>
            <?php elseif ($user_role === 'customer'): ?>
                <?php if ($job['laborer_id']): ?>
                    <a href="job_messages.php?job_id=<?php echo $job['id']; ?>" class="btn btn-purple">
                        <i class="fas fa-comments"></i> Messages
                        <?php if (isset($job['unread_count']) && $job['unread_count'] > 0): ?>
                            <span class="message-badge"><?php echo $job['unread_count']; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if ($job['status'] === 'completed' && !$rating): ?>
                        <a href="rate_laborer.php?job_id=<?php echo $job['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-star"></i> Rate Laborer
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($job['status'] !== 'completed' && $job['status'] !== 'cancelled'): ?>
                    <form method="POST" action="cancel_job.php" onsubmit="return confirm('Are you sure you want to cancel this job?');">
                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times-circle"></i> Cancel Job
                        </button>
                    </form>
                <?php endif; ?>
            <?php elseif ($user_role === 'admin'): ?>
                <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Job
                </a>
                
                <a href="job_messages.php?job_id=<?php echo $job['id']; ?>" class="btn btn-purple">
                    <i class="fas fa-comments"></i> View Messages
                    <?php if (isset($job['message_count']) && $job['message_count'] > 0): ?>
                        <span class="message-badge"><?php echo $job['message_count']; ?></span>
                    <?php endif; ?>
                </a>
                
                <?php if ($job['status'] !== 'cancelled'): ?>
                    <form method="POST" action="admin_cancel_job.php" onsubmit="return confirm('Are you sure you want to cancel this job?');">
                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times-circle"></i> Cancel Job
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
