<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // Initialize the user role variable from the session

// Check if job id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    if ($user_role === 'laborer') {
        header("Location: laborer_dashboard.php");
    } else if ($user_role === 'customer') {
        header("Location: customer_dashboard.php");
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

if (!$job) {
    $_SESSION['error'] = "Job not found.";
    header("Location: admin_job_requests.php");
    exit();
}

// Handle job actions if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize_input($_POST['action']);
    
    if ($action === 'approve') {
        // Update job status to open and make visible to laborer
        $update = $conn->prepare("UPDATE jobs SET status = 'open', is_visible_to_laborer = 1, updated_at = NOW() WHERE id = ?");
        $update->bind_param("i", $job_id);
        
        if ($update->execute()) {
            // Notify customer
            createNotification(
                $job['customer_id'],
                'Job Request Approved',
                "Your job request '{$job['title']}' has been approved.",
                'job_status'
            );
            
            // Notify laborer
            if ($job['laborer_id']) {
                createNotification(
                    $job['laborer_id'],
                    'New Job Available',
                    "You have a new job request: '{$job['title']}' from {$job['customer_name']}. Please check your dashboard.",
                    'job_request'
                );
            }
            
            $_SESSION['success'] = "Job request approved successfully.";
            header("Location: admin_job_requests.php");
            exit();
        } else {
            $_SESSION['error'] = "Error approving job request: " . $conn->error;
        }
    } elseif ($action === 'reject') {
        $update = $conn->prepare("UPDATE jobs SET status = 'rejected' WHERE id = ?");
        $update->bind_param("i", $job_id);
        
        if ($update->execute()) {
            // Notify customer
            createNotification(
                $job['customer_id'],
                'Job Request Rejected',
                "Your job request '{$job['title']}' has been rejected by admin.",
                'job_status'
            );
            
            $_SESSION['success'] = "Job request rejected.";
            header("Location: admin_job_requests.php");
            exit();
        } else {
            $_SESSION['error'] = "Error rejecting job request: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Job | Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 900px;
            margin-left: 280px;
            padding: 20px;
        }
        
        h1 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .job-details {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .section {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .section h3 {
            background: #eee;
            padding: 5px 10px;
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        
        .info-row {
            margin-bottom: 8px;
            display: flex;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        
        .info-value {
            flex: 1;
        }
        
        .job-description {
            background: #fff;
            border: 1px solid #ddd;
            padding: 10px;
        }
        
        .contact-info {
            background: #fff;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .contact-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .action-buttons {
            margin-top: 20px;
        }
        
        .btn {
            padding: 8px 15px;
            margin-right: 10px;
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .btn-reject {
            background: #f44336;
        }
        
        .btn-back {
            background: #2196F3;
        }
        
        .alert {
            padding: 10px;
            background-color: #f44336;
            color: white;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <div class="container">
        <h1>Job Details</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="job-details">
            <div class="section">
                <h3>Job Title: <?php echo htmlspecialchars($job['title']); ?></h3>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></div>
                </div>
            </div>
            
            <div class="section">
                <h3>Job Information</h3>
                
                <div class="info-row">
                    <div class="info-label">Budget:</div>
                    <div class="info-value">₹<?php echo number_format($job['budget'], 2); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Location:</div>
                    <div class="info-value"><?php echo htmlspecialchars($job['location']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Service Category:</div>
                    <div class="info-value"><?php echo htmlspecialchars($job['service_name'] ?? 'Not specified'); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Created:</div>
                    <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($job['created_at'])); ?></div>
                </div>
                
                <?php if ($job['updated_at']): ?>
                <div class="info-row">
                    <div class="info-label">Last Updated:</div>
                    <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($job['updated_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h3>Job Description</h3>
                <div class="job-description">
                    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                </div>
            </div>
            
            <div class="section">
                <h3>Customer Information</h3>
                
                <div class="contact-info">
                    <div class="contact-name"><?php echo htmlspecialchars($job['customer_name']); ?></div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><?php echo htmlspecialchars($job['customer_email']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Phone:</div>
                        <div class="info-value"><?php echo htmlspecialchars($job['customer_phone']); ?></div>
                    </div>
                </div>
            </div>
            
            <?php if ($job['laborer_id']): ?>
            <div class="section">
                <h3>Laborer Information</h3>
                
                <div class="contact-info">
                    <div class="contact-name"><?php echo htmlspecialchars($job['laborer_name']); ?></div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><?php echo htmlspecialchars($job['laborer_email']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Phone:</div>
                        <div class="info-value"><?php echo htmlspecialchars($job['laborer_phone']); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($job['status'] == 'pending_approval' || $job['status'] == '' || $job['status'] == 'open'): ?>
        <div class="action-buttons">
            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this job request?');">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn">Approve Job</button>
            </form>
            
            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this job request?');">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-reject">Reject Job</button>
            </form>
            
            <a href="admin_job_requests.php" class="btn btn-back" style="display: inline-block; text-decoration: none;">Back to List</a>
        </div>
        <?php else: ?>
        <div class="action-buttons">
            <a href="admin_job_requests.php" class="btn btn-back" style="display: inline-block; text-decoration: none;">Back to List</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
