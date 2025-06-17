<?php
require_once 'config.php';

// Check if user is logged in and is a laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$laborer_id = $_SESSION['user_id'];

// Handle job acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $job_id = (int)$_POST['job_id'];
    $action = sanitize_input($_POST['action']);
    
    if ($action === 'accept') {
        // Get job details first
        $stmt = $conn->prepare("
            SELECT j.*, 
                  c.id as customer_id, 
                  CONCAT(c.first_name, ' ', c.last_name) as customer_name
            FROM jobs j
            JOIN users c ON j.customer_id = c.id
            WHERE j.id = ? AND j.laborer_id = ? AND j.status = 'open'
        ");
        $stmt->bind_param("ii", $job_id, $laborer_id);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        
        if ($job) {
            // Update job status to assigned
            $update = $conn->prepare("UPDATE jobs SET status = 'assigned' WHERE id = ?");
            $update->bind_param("i", $job_id);
            
            if ($update->execute()) {
                // Notify customer
                createNotification(
                    $job['customer_id'],
                    'Job Request Accepted',
                    "Your job request '{$job['title']}' has been accepted.",
                    'job_status'
                );
                
                $_SESSION['success'] = "Job accepted successfully.";
            } else {
                $_SESSION['error'] = "Error accepting job: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Job not found or already assigned.";
        }
    } elseif ($action === 'decline') {
        // Get job details first
        $stmt = $conn->prepare("
            SELECT j.*, c.id as customer_id
            FROM jobs j
            JOIN users c ON j.customer_id = c.id
            WHERE j.id = ? AND j.laborer_id = ? AND j.status = 'open'
        ");
        $stmt->bind_param("ii", $job_id, $laborer_id);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        
        if ($job) {
            // Update job status to declined
            $update = $conn->prepare("UPDATE jobs SET status = 'declined' WHERE id = ?");
            $update->bind_param("i", $job_id);
            
            if ($update->execute()) {
                // Notify customer
                createNotification(
                    $job['customer_id'],
                    'Job Request Declined',
                    "Your job request '{$job['title']}' has been declined by the laborer.",
                    'job_status'
                );
                
                $_SESSION['success'] = "Job declined successfully.";
            } else {
                $_SESSION['error'] = "Error declining job: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Job not found or already processed.";
        }
    }
    
    header("Location: laborer_available_jobs.php");
    exit();
}

// Fetch all available jobs for this laborer
$query = "
    SELECT j.*, 
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           c.phone as customer_phone,
           s.name as service_name
    FROM jobs j
    JOIN users c ON j.customer_id = c.id
    LEFT JOIN services s ON j.service_id = s.id
    WHERE j.laborer_id = ? AND j.status = 'open'
    ORDER BY j.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $laborer_id);
$stmt->execute();
$available_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Jobs | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .job-card {
            background: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .job-header {
            padding: an 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .job-title {
            margin: 0;
            font-size: 18px;
        }
        
        .job-budget {
            font-weight: bold;
            color: #28a745;
        }
        
        .job-content {
            padding: 20px;
        }
        
        .job-details {
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        
        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #495057;
        }
        
        .job-description {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .job-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-accept {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-decline {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>
    
    <div class="container">
        <h1>Available Jobs</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (empty($available_jobs)): ?>
            <div class="empty-state">
                <h3>No jobs available</h3>
                <p>There are no new job requests for you at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($available_jobs as $job): ?>
                <div class="job-card">
                    <div class="job-header">
                        <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                        <span class="job-budget">₹<?php echo number_format($job['budget'], 2); ?></span>
                    </div>
                    
                    <div class="job-content">
                        <div class="job-details">
                            <div class="detail-row">
                                <div class="detail-label">Customer:</div>
                                <div><?php echo htmlspecialchars($job['customer_name']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Phone:</div>
                                <div><?php echo htmlspecialchars($job['customer_phone']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Service Category:</div>
                                <div><?php echo htmlspecialchars($job['service_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Location:</div>
                                <div><?php echo htmlspecialchars($job['location']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Date Requested:</div>
                                <div><?php echo date('M j, Y g:i A', strtotime($job['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="job-description">
                            <h4>Requirements:</h4>
                            <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                        </div>
                        
                        <div class="job-actions">
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to accept this job?');">
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="btn-accept">Accept Job</button>
                            </form>
                            
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to decline this job?');">
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit" class="btn-decline">Decline Job</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
