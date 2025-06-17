<?php
require_once 'config.php';

// Check if user is logged in as customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['user_id'];

// Handle job completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_job') {
    $job_id = (int)$_POST['job_id'];
    
    // Verify job belongs to customer and is in assigned status
    $stmt = $conn->prepare("
        SELECT j.*, l.id as laborer_id, CONCAT(l.first_name, ' ', l.last_name) as laborer_name
        FROM jobs j 
        JOIN users l ON j.laborer_id = l.id 
        WHERE j.id = ? AND j.customer_id = ? AND j.status = 'assigned'
    ");
    $stmt->bind_param("ii", $job_id, $customer_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if ($job) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update job status to completed
            $update = $conn->prepare("UPDATE jobs SET status = 'completed' WHERE id = ?");
            $update->bind_param("i", $job_id);
            $update->execute();
            
            // Create payment record
            $stmt = $conn->prepare("
                INSERT INTO payments (job_id, amount, status) 
                VALUES (?, ?, 'pending')
            ");
            $stmt->bind_param("id", $job_id, $job['budget']);
            $stmt->execute();
            
            // Notify laborer
            createNotification(
                $job['laborer_id'],
                'Job Completed',
                "Job '{$job['title']}' has been marked as completed. Payment is pending.",
                'job_status'
            );
            
            $conn->commit();
            $_SESSION['success'] = "Job marked as completed. Please proceed to payment.";
            header("Location: c_payments.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error completing job: " . $e->getMessage();
            header("Location: c_job_details.php?id=$job_id");
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid job or wrong status.";
        header("Location: c_dashboard.php");
        exit();
    }
}

// Get assigned jobs for the customer
$stmt = $conn->prepare("
    SELECT j.*, 
           CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
           l.phone as laborer_phone,
           s.name as service_name
    FROM jobs j
    JOIN users l ON j.laborer_id = l.id
    LEFT JOIN services s ON j.service_id = s.id
    WHERE j.customer_id = ? AND j.status = 'assigned'
    ORDER BY j.created_at DESC
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$assigned_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Completion | QuickHire Labor</title>
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
            padding: 15px 20px;
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
        
        .job-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-complete {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-message {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
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
    <?php include 'includes/customer_sidebar.php'; ?>
    
    <div class="container">
        <h1>Ongoing Jobs</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (empty($assigned_jobs)): ?>
            <div class="empty-state">
                <h3>No ongoing jobs</h3>
                <p>You don't have any active jobs at the moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($assigned_jobs as $job): ?>
                <div class="job-card">
                    <div class="job-header">
                        <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                        <span class="job-budget">₹<?php echo number_format($job['budget'], 2); ?></span>
                    </div>
                    
                    <div class="job-content">
                        <div class="job-details">
                            <div class="detail-row">
                                <div class="detail-label">Laborer:</div>
                                <div><?php echo htmlspecialchars($job['laborer_name']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Phone:</div>
                                <div><?php echo htmlspecialchars($job['laborer_phone']); ?></div>
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
                                <div class="detail-label">Start Date:</div>
                                <div><?php echo date('M j, Y', strtotime($job['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="job-actions">
                            <form method="POST" action="" onsubmit="return confirm('Are you sure the job is complete? This will initiate payment.');">
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                <input type="hidden" name="action" value="complete_job">
                                <button type="submit" class="btn-complete">Mark as Complete</button>
                            </form>
                            
                            <a href="job_messages.php?job_id=<?php echo $job['id']; ?>" class="btn-message">Message Laborer</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
