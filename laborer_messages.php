<?php
require_once 'config.php';

// Check if user is logged in as laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = "";
$success_message = "";

try {
    // First check if job_messages table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'job_messages'");
    if ($table_check->num_rows === 0) {
        // Table doesn't exist - create it
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS job_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_id INT NOT NULL,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        $conn->query($create_table_sql);
    }

    // Get conversations with improved query including message preview
    $stmt = $conn->prepare("
        SELECT 
            j.id AS job_id,
            j.title AS job_title,
            j.status AS job_status,
            c.id AS customer_id,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
            (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id AND receiver_id = ? AND is_read = 0) AS unread_count,
            (SELECT MAX(created_at) FROM job_messages WHERE job_id = j.id) AS last_message_time,
            (SELECT message FROM job_messages WHERE job_id = j.id ORDER BY created_at DESC LIMIT 1) AS last_message
        FROM jobs j
        JOIN users c ON j.customer_id = c.id
        WHERE j.laborer_id = ? 
        AND EXISTS (SELECT 1 FROM job_messages WHERE job_id = j.id)
        ORDER BY 
            (SELECT MAX(created_at) FROM job_messages WHERE job_id = j.id) DESC,
            (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id AND receiver_id = ? AND is_read = 0) DESC
    ");
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Handle search functionality with better input validation
    $search_query = "";
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_query = sanitize_input($_GET['search']);
        
        // Improved search query with better prioritization
        $stmt = $conn->prepare("
            SELECT 
                j.id AS job_id,
                j.title AS job_title,
                j.status AS job_status,
                c.id AS customer_id,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id AND receiver_id = ? AND is_read = 0) AS unread_count,
                (SELECT MAX(created_at) FROM job_messages WHERE job_id = j.id) AS last_message_time,
                (SELECT message FROM job_messages WHERE job_id = j.id ORDER BY created_at DESC LIMIT 1) AS last_message
            FROM jobs j
            JOIN users c ON j.customer_id = c.id
            WHERE j.laborer_id = ? 
            AND EXISTS (SELECT 1 FROM job_messages WHERE job_id = j.id)
            AND (
                j.title LIKE ? 
                OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?
                OR EXISTS (
                    SELECT 1 FROM job_messages 
                    WHERE job_id = j.id AND message LIKE ?
                )
            )
            ORDER BY 
                CASE 
                    WHEN j.title LIKE ? THEN 1
                    WHEN CONCAT(c.first_name, ' ', c.last_name) LIKE ? THEN 2
                    ELSE 3
                END,
                last_message_time DESC
        ");
        $exact_search = $search_query;
        $search_param = "%$search_query%";
        $stmt->bind_param("iissssss", $user_id, $user_id, $search_param, $search_param, $search_param, $exact_search, $exact_search);
        $stmt->execute();
        $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Mark older unread messages as read (if they're more than a week old)
    $mark_old_read = $conn->prepare("
        UPDATE job_messages 
        SET is_read = 1 
        WHERE receiver_id = ? 
        AND is_read = 0 
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $mark_old_read->bind_param("i", $user_id);
    $mark_old_read->execute();

    // Get total unread messages with optimized query
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM job_messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_unread = $stmt->get_result()->fetch_assoc()['total'];

    // Get conversation statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT job_id) as total_conversations,
            COUNT(DISTINCT CASE WHEN is_read = 0 AND receiver_id = ? THEN job_id END) as unread_conversations,
            COUNT(*) as total_messages
        FROM job_messages
        WHERE job_id IN (SELECT id FROM jobs WHERE laborer_id = ?)
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Error in laborer_messages.php: " . $e->getMessage());
    $conversations = [];
    $total_unread = 0;
    $stats = ['total_conversations' => 0, 'unread_conversations' => 0, 'total_messages' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | QuickHire Labor</title>
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
        }
        
        .search-box button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 0 15px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        .conversations-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            transition: background 0.3s;
        }
        
        .conversation-item:last-child {
            border-bottom: none;
        }
        
        .conversation-item:hover {
            background: #f9f9f9;
        }
        
        .conversation-item.unread {
            background: #e8f5e9;
        }
        
        .conversation-item.unread:hover {
            background: #d7edd8;
        }
        
        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #4CAF50;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            margin-right: 15px;
        }
        
        .conversation-details {
            flex: 1;
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .conversation-name {
            font-weight: bold;
        }
        
        .conversation-time {
            color: #888;
            font-size: 12px;
        }
        
        .conversation-preview {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 500px;
        }
        
        .conversation-job-title {
            font-size: 12px;
            background: #f1f1f1;
            padding: 3px 8px;
            border-radius: 10px;
            color: #666;
            margin-top: 5px;
            display: inline-block;
        }
        
        .unread-badge {
            background: #f44336;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .empty-state {
            padding: 50px;
            text-align: center;
            color: #666;
        }
        
        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            color: white;
            margin-left: 10px;
        }
        
        .status-open { background: #17a2b8; }
        .status-assigned { background: #007bff; }
        .status-in_progress { background: #6610f2; }
        .status-completed { background: #28a745; }
        .status-cancelled { background: #dc3545; }
        
        .message-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #4CAF50;
        }
        
        .message-preview {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
        }
        
        .message-alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .message-alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message-alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .refresh-link {
            margin-left: 15px;
            font-size: 14px;
            color: #4CAF50;
            text-decoration: none;
        }
        
        .refresh-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>
                Messages 
                <?php if ($total_unread > 0): ?>
                    <span class="unread-badge"><?php echo $total_unread; ?></span>
                <?php endif; ?>
                <a href="laborer_messages.php" class="refresh-link"><i class="fas fa-sync-alt"></i> Refresh</a>
            </h1>
            
            <form class="search-box" method="GET" action="">
                <input type="text" name="search" placeholder="Search messages, jobs or customers..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="message-alert error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="message-alert success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message-alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message-alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Message statistics -->
        <div class="message-stats">
            <div class="stat-box">
                <div class="stat-value"><?php echo $stats['total_conversations']; ?></div>
                <div>Total Conversations</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo $stats['unread_conversations']; ?></div>
                <div>Unread Conversations</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo $stats['total_messages']; ?></div>
                <div>Total Messages</div>
            </div>
        </div>
        
        <div class="conversations-list">
            <?php if (empty($conversations)): ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>No messages yet</h3>
                    <p>When you have conversations with customers, they will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conversation): ?>
                    <a href="job_messages.php?job_id=<?php echo $conversation['job_id']; ?>" class="conversation-item <?php echo $conversation['unread_count'] > 0 ? 'unread' : ''; ?>">
                        <div class="conversation-avatar">
                            <?php echo substr($conversation['customer_name'], 0, 1); ?>
                        </div>
                        <div class="conversation-details">
                            <div class="conversation-header">
                                <div class="conversation-name">
                                    <?php echo htmlspecialchars($conversation['customer_name']); ?>
                                    <span class="status-badge status-<?php echo $conversation['job_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $conversation['job_status'])); ?>
                                    </span>
                                </div>
                                <div class="conversation-time">
                                    <?php 
                                    $time = strtotime($conversation['last_message_time']);
                                    $now = time();
                                    $diff = $now - $time;
                                    
                                    if ($diff < 60) {
                                        echo "Just now";
                                    } elseif ($diff < 3600) {
                                        echo floor($diff / 60) . "m ago";
                                    } elseif ($diff < 86400) {
                                        echo floor($diff / 3600) . "h ago";
                                    } elseif (date('Y-m-d', $time) == date('Y-m-d', strtotime('yesterday'))) {
                                        echo "Yesterday";
                                    } else {
                                        echo date('M j', $time);
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="conversation-preview">
                                <span class="conversation-job-title"><?php echo htmlspecialchars($conversation['job_title']); ?></span>
                                <span class="message-preview"><?php echo htmlspecialchars(substr($conversation['last_message'], 0, 80) . (strlen($conversation['last_message']) > 80 ? '...' : '')); ?></span>
                            </div>
                            <?php if ($conversation['unread_count'] > 0): ?>
                                <div class="unread-badge"><?php echo $conversation['unread_count']; ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Highlight the search text if present
        const searchQuery = "<?php echo $search_query; ?>";
        if (searchQuery) {
            const regex = new RegExp(searchQuery, 'gi');
            document.querySelectorAll('.conversation-name, .conversation-job-title, .message-preview').forEach(element => {
                const originalText = element.textContent;
                element.innerHTML = originalText.replace(regex, match => `<mark>${match}</mark>`);
            });
        }
        
        // Auto-refresh the page every 60 seconds
        setTimeout(() => {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>
