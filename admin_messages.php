<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Get all conversations grouped by job
$query = "
    SELECT 
        j.id AS job_id,
        j.title AS job_title, 
        j.status AS job_status,
        CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
        CONCAT(l.first_name, ' ', l.last_name) AS laborer_name,
        (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id) AS message_count,
        (SELECT MAX(created_at) FROM job_messages WHERE job_id = j.id) AS last_message_time
    FROM jobs j
    JOIN users c ON j.customer_id = c.id
    LEFT JOIN users l ON j.laborer_id = l.id
    WHERE EXISTS (SELECT 1 FROM job_messages WHERE job_id = j.id)
    ORDER BY last_message_time DESC
";

$result = $conn->query($query);
$conversations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Search functionality
$search_query = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = sanitize_input($_GET['search']);
    
    $stmt = $conn->prepare("
        SELECT 
            j.id AS job_id,
            j.title AS job_title, 
            j.status AS job_status,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
            CONCAT(l.first_name, ' ', l.last_name) AS laborer_name,
            (SELECT COUNT(*) FROM job_messages WHERE job_id = j.id) AS message_count,
            (SELECT MAX(created_at) FROM job_messages WHERE job_id = j.id) AS last_message_time
        FROM jobs j
        JOIN users c ON j.customer_id = c.id
        LEFT JOIN users l ON j.laborer_id = l.id
        WHERE EXISTS (SELECT 1 FROM job_messages WHERE job_id = j.id)
        AND (
            j.title LIKE ? OR
            CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR
            CONCAT(l.first_name, ' ', l.last_name) LIKE ? OR
            EXISTS (SELECT 1 FROM job_messages WHERE job_id = j.id AND message LIKE ?)
        )
        ORDER BY last_message_time DESC
    ");
    
    $search_param = "%$search_query%";
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Message statistics
$query = "
    SELECT 
        COUNT(DISTINCT job_id) AS total_conversations,
        COUNT(*) AS total_messages,
        COUNT(DISTINCT sender_id) AS total_users,
        DATE(MAX(created_at)) AS latest_message_date
    FROM job_messages
";
$result = $conn->query($query);
$stats = $result ? $result->fetch_assoc() : [
    'total_conversations' => 0,
    'total_messages' => 0,
    'total_users' => 0,
    'latest_message_date' => 'N/A'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Management | Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .search-box {
            display: flex;
            width: 300px;
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
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        
        .conversation-list {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .conversation-item:last-child {
            border-bottom: none;
        }
        
        .conversation-item:hover {
            background: #f5f5f5;
        }
        
        .conversation-icon {
            width: 50px;
            height: 50px;
            background: #4CAF50;
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            margin-right: 15px;
        }
        
        .conversation-details {
            flex: 1;
        }
        
        .conversation-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .conversation-meta {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .view-button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-left: auto;
        }
        
        .view-button i {
            margin-right: 5px;
        }
        
        .status-badge {
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
    </style>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <div class="container">
        <div class="header-section">
            <h1>Message Management</h1>
            
            <form class="search-box" method="GET" action="">
                <input type="text" name="search" placeholder="Search conversations..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        
        <div class="stats-section">
            <div class="stat-card">
                <div>Total Conversations</div>
                <div class="stat-value"><?php echo $stats['total_conversations']; ?></div>
            </div>
            <div class="stat-card">
                <div>Total Messages</div>
                <div class="stat-value"><?php echo $stats['total_messages']; ?></div>
            </div>
            <div class="stat-card">
                <div>Active Users</div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            </div>
            <div class="stat-card">
                <div>Latest Activity</div>
                <div class="stat-value">
                    <?php 
                    echo $stats['latest_message_date'] !== 'N/A' 
                        ? date('M j, Y', strtotime($stats['latest_message_date'])) 
                        : 'N/A'; 
                    ?>
                </div>
            </div>
        </div>
        
        <?php if (empty($conversations)): ?>
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>No conversations found</h3>
                <p>When users start messaging each other about jobs, their conversations will appear here.</p>
            </div>
        <?php else: ?>
            <div class="conversation-list">
                <?php foreach ($conversations as $conversation): ?>
                    <div class="conversation-item">
                        <div class="conversation-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="conversation-details">
                            <div class="conversation-title">
                                <?php echo htmlspecialchars($conversation['job_title']); ?>
                                <span class="status-badge status-<?php echo $conversation['job_status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $conversation['job_status'])); ?>
                                </span>
                            </div>
                            <div class="conversation-meta">
                                <div>
                                    Between 
                                    <strong><?php echo htmlspecialchars($conversation['customer_name']); ?></strong> 
                                    and 
                                    <strong><?php echo $conversation['laborer_name'] ? htmlspecialchars($conversation['laborer_name']) : 'Unassigned'; ?></strong>
                                </div>
                                <div>
                                    <?php echo $conversation['message_count']; ?> messages | 
                                    Last active: <?php 
                                        $time = strtotime($conversation['last_message_time']);
                                        $now = time();
                                        $diff = $now - $time;
                                        
                                        if ($diff < 3600) {
                                            echo floor($diff / 60) . " mins ago";
                                        } elseif ($diff < 86400) {
                                            echo floor($diff / 3600) . " hours ago";
                                        } else {
                                            echo date('M j', $time);
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <a href="job_messages.php?job_id=<?php echo $conversation['job_id']; ?>" class="view-button">
                            <i class="fas fa-eye"></i> View Conversation
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
