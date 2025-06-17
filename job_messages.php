<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if job_id is provided
if (!isset($_GET['job_id']) || empty($_GET['job_id'])) {
    // Redirect based on role
    if ($user_role === 'customer') {
        header("Location: customer_dashboard.php");
    } else if ($user_role === 'laborer') {
        header("Location: laborer_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$job_id = (int)$_GET['job_id'];

// Check if the job_messages table exists, create it if it doesn't
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'job_messages'");
    if ($table_check->num_rows === 0) {
        // Table doesn't exist, create it
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS job_messages (
                id INT AUTO_INCREMENT PRIMARY KEY, -- Ensure AUTO_INCREMENT is set for the primary key
                job_id INT NOT NULL,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX (job_id),
                INDEX (sender_id),
                INDEX (receiver_id),
                INDEX (is_read)
            )
        ";
        $conn->query($create_table_sql);
    }
} catch (Exception $e) {
    error_log("Error creating job_messages table: " . $e->getMessage());
    $_SESSION['error'] = "Error: Could not set up messaging system. Please contact admin.";
    
    // Redirect based on role
    if ($user_role === 'customer') {
        header("Location: customer_dashboard.php");
    } else if ($user_role === 'laborer') {
        header("Location: laborer_dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

// Get job details and verify user has access to this job
if ($user_role === 'customer') {
    $stmt = $conn->prepare("
        SELECT j.*, l.id as laborer_id, 
               CONCAT(l.first_name, ' ', l.last_name) as laborer_name
        FROM jobs j
        LEFT JOIN users l ON j.laborer_id = l.id
        WHERE j.id = ? AND j.customer_id = ?
    ");
    $stmt->bind_param("ii", $job_id, $user_id);
} else if ($user_role === 'laborer') {
    $stmt = $conn->prepare("
        SELECT j.*, c.id as customer_id,
               CONCAT(c.first_name, ' ', c.last_name) as customer_name
        FROM jobs j
        JOIN users c ON j.customer_id = c.id
        WHERE j.id = ? AND j.laborer_id = ?
    ");
    $stmt->bind_param("ii", $job_id, $user_id);
} else {
    // Admin can view any conversation
    $stmt = $conn->prepare("
        SELECT j.*, 
               c.id as customer_id, 
               l.id as laborer_id,
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               CONCAT(l.first_name, ' ', l.last_name) as laborer_name
        FROM jobs j
        JOIN users c ON j.customer_id = c.id
        LEFT JOIN users l ON j.laborer_id = l.id
        WHERE j.id = ?
    ");
    $stmt->bind_param("i", $job_id);
}

$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    $_SESSION['error'] = "Job not found or you don't have permission to view it.";
    // Redirect based on role
    if ($user_role === 'customer') {
        header("Location: customer_dashboard.php");
    } else if ($user_role === 'laborer') {
        header("Location: laborer_dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

// Determine sender and receiver based on role
if ($user_role === 'customer') {
    $sender_id = $user_id;
    $receiver_id = $job['laborer_id'];
    $receiver_name = $job['laborer_name'] ?? 'Unassigned';
    $is_assigned = !empty($job['laborer_id']);
} else if ($user_role === 'laborer') {
    $sender_id = $user_id;
    $receiver_id = $job['customer_id'];
    $receiver_name = $job['customer_name'];
    $is_assigned = true;
} else {
    // Admin is viewing, not sending
    $sender_id = null;
    $receiver_id = null;
    $is_assigned = !empty($job['laborer_id']);
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty($_POST['message']) && $user_role !== 'admin') {
    $message = sanitize_input($_POST['message']);
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO job_messages (job_id, sender_id, receiver_id, message, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiis", $job_id, $sender_id, $receiver_id, $message);
    
    if ($stmt->execute()) {
        // Update job to mark new message activity
        $update = $conn->prepare("UPDATE jobs SET updated_at = NOW() WHERE id = ?");
        $update->bind_param("i", $job_id);
        $update->execute();
        
        // Create notification for the receiver
        if (function_exists('createNotification')) {
            // Get sender name for the notification
            $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
            $stmt->bind_param("i", $sender_id);
            $stmt->execute();
            $sender = $stmt->get_result()->fetch_assoc();
            $sender_name = $sender ? $sender['name'] : 'Someone';
            
            // Create the notification
            createNotification(
                $receiver_id,
                "New message from $sender_name",
                "You have received a new message regarding job '{$job['title']}'",
                'message'
            );
        }
        
        $_SESSION['success'] = "Message sent successfully!";
    } else {
        $_SESSION['error'] = "Error sending message: " . $conn->error;
    }
    
    // Redirect to avoid form resubmission
    header("Location: job_messages.php?job_id=" . $job_id);
    exit();
}

// Mark messages as read
if ($user_role !== 'admin') {
    $stmt = $conn->prepare("
        UPDATE job_messages 
        SET is_read = 1 
        WHERE job_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
}

// Get messages
$stmt = $conn->prepare("
    SELECT m.*, 
        CONCAT(s.first_name, ' ', s.last_name) as sender_name,
        s.role as sender_role
    FROM job_messages m
    JOIN users s ON m.sender_id = s.id
    WHERE m.job_id = ?
    ORDER BY m.created_at ASC
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get the job title for display
$job_title = htmlspecialchars($job['title']);

// Determine back URL based on user role
if ($user_role === 'customer') {
    $back_url = "c_messages.php"; // Changed from customer_dashboard.php to c_dashboard.php
} else if ($user_role === 'laborer') {
    $back_url = "laborer_messages.php"; // Changed from laborer_dashboard.php to l_dashboard.php
} else {
    $back_url = "admin_messages.php";
}

/*
Usage:
1. This file powers the messaging system between customers and laborers
2. It's accessed via URLs like: job_messages.php?job_id=123
3. It's linked from:
   - Customer dashboard/jobs page
   - Laborer dashboard/jobs page
   - Job detail pages
   - Notification links
   
The job_messages table stores all communications between parties about a specific job.
When a customer or laborer needs to communicate about job details, payment, scheduling,
or any other job-related matters, they use this messaging interface.

Typical workflow:
1. Customer posts a job
2. Laborer applies for the job
3. Customer selects the laborer
4. Both parties use this messaging system to coordinate details
5. Admin can view all messages for moderation/support purposes
*/
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo $job_title; ?></title>
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
        
        .message-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .message-title {
            margin: 0;
            color: #333;
        }
        
        .job-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
            color: white;
        }
        
        .status-open { background: #17a2b8; }
        .status-assigned { background: #007bff; }
        .status-in_progress { background: #6610f2; }
        .status-completed { background: #28a745; }
        .status-cancelled { background: #dc3545; }
        .status-pending_approval { background: #ffc107; color: #212529; }
        .status-rejected { background: #6c757d; }
        
        .message-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            min-height: 300px;
            max-height: 600px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .message {
            margin-bottom: 15px;
            max-width: 80%;
        }
        
        .message.sent {
            margin-left: auto;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .sender-name {
            font-weight: bold;
        }
        
        .sender-role {
            font-style: italic;
            color: #888;
        }
        
        .message-time {
            font-size: 10px;
        }
        
        .message-content {
            padding: 10px 15px;
            border-radius: 8px;
            position: relative;
        }
        
        .message.sent .message-content {
            background: #4CAF50;
            color: white;
            border-radius: 8px 0 8px 8px;
        }
        
        .message.received .message-content {
            background: #f1f1f1;
            color: #333;
            border-radius: 0 8px 8px 8px;
        }
        
        .message-form-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .message-form {
            display: flex;
            flex-direction: column;
        }
        
        .message-form textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            height: 100px;
            margin-bottom: 10px;
        }
        
        .message-form button {
            padding: 10px 15px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            align-self: flex-end;
        }
        
        .message-form button:hover {
            background: #45a049;
        }
        
        .no-messages {
            text-align: center;
            padding: 40px 0;
            color: #666;
        }
        
        .admin-view-note {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <?php 
    // Include appropriate sidebar based on role
    if ($user_role === 'customer') {
        include 'includes/customer_sidebar.php';
    } else if ($user_role === 'laborer') {
        include 'includes/laborer_sidebar.php';
    } else {
        include 'includes/admin_sidebar.php';
    }
    ?>
    
    <div class="container">
        <div class="back-link">
            <a href="<?php echo $back_url; ?>">
                <i class="fas fa-arrow-left"></i> Back to <?php echo ucfirst($user_role) === 'Admin' ? 'Messages' : 'Dashboard'; ?>
            </a>
        </div>
        
        <div class="message-header">
            <h2 class="message-title">
                <?php echo $job_title; ?>
                <span class="job-status status-<?php echo $job['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                </span>
            </h2>
            <?php if ($user_role === 'customer'): ?>
                <p>Conversation with <?php echo htmlspecialchars($receiver_name); ?></p>
            <?php elseif ($user_role === 'laborer'): ?>
                <p>Conversation with <?php echo htmlspecialchars($receiver_name); ?></p>
            <?php else: ?>
                <p>Conversation between <?php echo htmlspecialchars($job['customer_name']); ?> and <?php echo $job['laborer_name'] ? htmlspecialchars($job['laborer_name']) : 'Unassigned'; ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($user_role === 'admin'): ?>
            <div class="admin-view-note">
                <strong>Admin View:</strong> You are viewing the conversation between customer and laborer. You cannot send messages.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="message-container">
            <?php if (empty($messages)): ?>
                <div class="no-messages">
                    <p>No messages yet. Start the conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                        <div class="message-header">
                            <span class="sender-name">
                                <?php echo htmlspecialchars($message['sender_name']); ?>
                                <span class="sender-role">(<?php echo ucfirst($message['sender_role']); ?>)</span>
                            </span>
                            <span class="message-time">
                                <?php echo date('M j, g:i a', strtotime($message['created_at'])); ?>
                            </span>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($user_role !== 'admin' && ($is_assigned || $user_role === 'laborer')): ?>
            <div class="message-form-container">
                <form method="POST" action="" class="message-form">
                    <textarea name="message" placeholder="Type your message here..." required></textarea>
                    <button type="submit">Send Message</button>
                </form>
            </div>
        <?php elseif ($user_role === 'customer' && !$is_assigned): ?>
            <div class="alert error">
                No laborer has been assigned to this job yet. You'll be able to send messages once a laborer is assigned.
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Scroll to bottom of messages on page load
        window.onload = function() {
            var messageContainer = document.querySelector('.message-container');
            messageContainer.scrollTop = messageContainer.scrollHeight;
        };
    </script>
</body>
</html>
