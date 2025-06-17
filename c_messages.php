<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all jobs with conversation activity
$stmt = $conn->prepare("
    SELECT DISTINCT j.id, j.title, j.status, j.updated_at, 
        CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
        (SELECT COUNT(*) FROM job_messages 
         WHERE job_id = j.id AND receiver_id = ? AND is_read = 0) as unread_count,
        (SELECT MAX(created_at) FROM job_messages 
         WHERE job_id = j.id) as last_message_time
    FROM jobs j
    LEFT JOIN users l ON j.laborer_id = l.id
    LEFT JOIN job_messages m ON j.id = m.job_id
    WHERE j.customer_id = ? 
    AND j.laborer_id IS NOT NULL
    AND EXISTS (SELECT 1 FROM job_messages WHERE job_id = j.id)
    ORDER BY last_message_time DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get assigned jobs without messages yet
$stmt = $conn->prepare("
    SELECT j.id, j.title, j.status, j.updated_at, 
        CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
        0 as unread_count
    FROM jobs j
    LEFT JOIN users l ON j.laborer_id = l.id
    WHERE j.customer_id = ? 
    AND j.laborer_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM job_messages WHERE job_id = j.id)
    ORDER BY j.updated_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$jobs_without_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Combine both lists
$all_conversations = array_merge($conversations, $jobs_without_messages);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .page-title {
            margin-bottom: 20px;
            color: #333;
        }
        
        .conversation-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }
        
        .conversation-item:last-child {
            border-bottom: none;
        }
        
        .conversation-item:hover {
            background-color: #f9f9f9;
        }
        
        .conversation-item.unread {
            background-color: #e8f4fd;
        }
        
        .conversation-item.unread:hover {
            background-color: #d7edf9;
        }
        
        .conversation-details {
            flex: 1;
        }
        
        .conversation-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .conversation-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85em;
            color: #666;
        }
        
        .job-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
            margin-left: 10px;
        }
        
        .status-open { background: #17a2b8; }
        .status-assigned { background: #007bff; }
        .status-in_progress { background: #6610f2; }
        .status-completed { background: #28a745; }
        .status-cancelled { background: #dc3545; }
        .status-pending_approval { background: #ffc107; color: #212529; }
        
        .conversation-actions {
            margin-left: 15px;
        }
        
        .btn-view {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view:hover {
            background: #45a049;
        }
        
        .unread-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 3px 8px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>
    
    <div class="container">
        <h1 class="page-title">My Messages</h1>
        
        <div class="conversation-list">
            <?php if (empty($all_conversations)): ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>You don't have any conversations yet.</p>
                    <p>When you have active jobs with laborers, your conversations will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($all_conversations as $conversation): ?>
                    <div class="conversation-item <?php echo $conversation['unread_count'] > 0 ? 'unread' : ''; ?>">
                        <div class="conversation-details">
                            <div class="conversation-title">
                                <?php echo htmlspecialchars($conversation['title']); ?>
                                <span class="job-status status-<?php echo $conversation['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $conversation['status'])); ?>
                                </span>
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="conversation-meta">
                                <span>With: <?php echo htmlspecialchars($conversation['laborer_name']); ?></span>
                                <span>
                                    <?php 
                                    $last_time = isset($conversation['last_message_time']) ? 
                                        strtotime($conversation['last_message_time']) : 
                                        strtotime($conversation['updated_at']);
                                    
                                    echo date('M j, g:i a', $last_time); 
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="conversation-actions">
                            <a href="job_messages.php?job_id=<?php echo $conversation['id']; ?>" class="btn-view">
                                View Messages
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
