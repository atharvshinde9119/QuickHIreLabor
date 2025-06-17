<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

// Get user data for sidebar
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT NULL AS profile_pic, 
                              CONCAT(first_name, ' ', last_name) AS name, 
                              email, phone, id, role
                       FROM users 
                       WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle job actions
if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    try {
        $job_id = (int)$_POST['job_id'];
        
        switch ($_POST['action']) {
            case 'cancel_job':
                // Verify job status
                $stmt = $conn->prepare("
                    SELECT j.*, u.id as laborer_id 
                    FROM jobs j 
                    LEFT JOIN users u ON j.laborer_id = u.id 
                    WHERE j.id = ? AND j.customer_id = ? 
                    AND j.status IN ('pending', 'assigned')
                ");
                $stmt->bind_param("ii", $job_id, $user_id);
                $stmt->execute();
                $job = $stmt->get_result()->fetch_assoc();
                
                if (!$job) {
                    throw new Exception('Job not found or cannot be cancelled');
                }
                
                $conn->begin_transaction();
                
                // Update job status
                $stmt = $conn->prepare("UPDATE jobs SET status = 'cancelled' WHERE id = ?");
                $stmt->bind_param("i", $job_id);
                $stmt->execute();
                
                // Notify laborer if assigned
                if ($job['laborer_id']) {
                    $notification_message = "Job cancelled: " . $job['title'];
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Job Cancelled', ?)");
                    $stmt->bind_param("is", $job['laborer_id'], $notification_message);
                    $stmt->execute();
                }
                
                // Add notification for laborer
                if ($job['laborer_id']) {
                    createNotification(
                        $job['laborer_id'],
                        'Job Cancelled',
                        "Job '{$job['title']}' has been cancelled by the customer.",
                        'job_status'
                    );
                }
                
                // Add notification for customer
                createNotification(
                    $user_id,
                    'Job Cancelled',
                    "You have cancelled the job '{$job['title']}'",
                    'job_status'
                );
                
                $conn->commit();
                $response['success'] = true;
                $response['message'] = 'Job cancelled successfully';
                break;
                
            case 'send_message':
                $message = sanitize_input($_POST['message']);
                
                // Verify job and get laborer
                $stmt = $conn->prepare("SELECT laborer_id, title FROM jobs WHERE id = ? AND customer_id = ?");
                $stmt->bind_param("ii", $job_id, $user_id);
                $stmt->execute();
                $job = $stmt->get_result()->fetch_assoc();
                
                if (!$job || !$job['laborer_id']) {
                    throw new Exception('Invalid job or no laborer assigned');
                }
                
                // Create notification
                $notification_message = "Message regarding job '{$job['title']}': $message";
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'New Message', ?)");
                $stmt->bind_param("is", $job['laborer_id'], $notification_message);
                $stmt->execute();
                
                $response['success'] = true;
                $response['message'] = 'Message sent successfully';
                break;
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollback();
        }
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get jobs data with laborer info
$stmt = $conn->prepare("SELECT j.*, CONCAT(u.first_name, ' ', u.last_name) AS laborer_name, u.phone as laborer_phone 
                       FROM jobs j 
                       LEFT JOIN users u ON j.laborer_id = u.id 
                       WHERE j.customer_id = ? 
                       ORDER BY j.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Status - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .header {
            margin-left: 280px;
            background-color: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 24px;
        }

        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
            background: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .search-bar {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        .job-list {
            list-style: none;
            padding: 0;
        }

        .job-item {
            background: #e9ecef;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: background 0.3s;
        }

        .job-item:hover {
            background: #d6d8db;
        }

        .status {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        .pending { background: #ffc107; color: #212529; }
        .in-progress { background: #17a2b8; color: white; }
        .completed { background: #28a745; color: white; }

        .job-details {
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-top: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn {
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
        }

        .btn-message { background: #007bff; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .btn-message:hover { background: #0056b3; }
        .btn-cancel:hover { background: #bd2130; }

        .footer {
            margin-left: 280px;
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }

        .footer a {
            color: #007bff;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="header">
        <h1>Job Status</h1>
    </div>

    <div class="container">
        <input type="text" id="searchBar" class="search-bar" placeholder="Search Job by Title or Status...">

        <ul class="job-list">
            <?php foreach ($jobs as $job): ?>
                <li class="job-item" data-status="<?php echo htmlspecialchars($job['status']); ?>">
                    <span>
                        <strong><?php echo htmlspecialchars($job['title']); ?></strong> - 
                        <?php echo date('jS M, Y', strtotime($job['created_at'])); ?>
                    </span>
                    <span class="status <?php echo htmlspecialchars($job['status']); ?>">
                        <?php echo ucfirst($job['status']); ?>
                    </span>
                </li>
                <div class="job-details">
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                    <p><strong>Assigned Laborer:</strong> 
                        <?php echo htmlspecialchars($job['laborer_name'] ?? 'Not Assigned Yet'); ?>
                    </p>
                    <p><strong>Price:</strong> ₹<?php echo htmlspecialchars($job['price']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($job['description']); ?></p>
                    <?php if ($job['laborer_phone']): ?>
                        <p><strong>Laborer Phone:</strong> <?php echo htmlspecialchars($job['laborer_phone']); ?></p>
                    <?php endif; ?>
                    <div class="action-buttons">
                        <?php if ($job['status'] == 'pending' || $job['status'] == 'assigned'): ?>
                            <button class="btn btn-cancel" data-job-id="<?php echo $job['id']; ?>">Cancel Job</button>
                        <?php endif; ?>
                        <?php if ($job['laborer_id']): ?>
                            <button class="btn btn-message" data-job-id="<?php echo $job['id']; ?>">Message Laborer</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="footer">
        <p>Need help? <a href="c_support.html">Contact Support</a></p>
    </div>

    <script>
        $(document).ready(function() {
            // Toggle job details
            $(".job-item").click(function() {
                $(this).next(".job-details").slideToggle();
            });

            // Search functionality
            $("#searchBar").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $(".job-item").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });

            // Cancel job functionality
            $(".btn-cancel").click(function(e) {
                e.stopPropagation();
                var jobId = $(this).data("job-id");
                if (confirm("Are you sure you want to cancel this job?")) {
                    $.ajax({
                        url: 'c_job_status.php',
                        method: 'POST',
                        data: {
                            action: 'cancel_job',
                            job_id: jobId
                        },
                        success: function(response) {
                            const data = JSON.parse(response);
                            alert(data.message);
                            if (data.success) {
                                location.reload();
                            }
                        },
                        error: function() {
                            alert('An error occurred while cancelling the job');
                        }
                    });
                }
            });

            // Message laborer functionality
            $(".btn-message").click(function(e) {
                e.stopPropagation();
                const jobId = $(this).data("job-id");
                const message = prompt("Enter your message for the laborer:");
                
                if (message) {
                    $.ajax({
                        url: 'c_job_status.php',
                        method: 'POST',
                        data: {
                            action: 'send_message',
                            job_id: jobId,
                            message: message
                        },
                        success: function(response) {
                            const data = JSON.parse(response);
                            alert(data.message);
                        },
                        error: function() {
                            alert('An error occurred while sending the message');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>