<?php
require_once 'config.php';

// Check if user is logged in as laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all notifications for this laborer
$stmt = $conn->prepare("
    SELECT n.id, n.title, n.message, n.type, n.is_read, n.created_at
    FROM notifications n
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process notifications to remove transaction IDs
foreach ($notifications as $key => $notification) {
    // Check if this is a payment notification
    if ($notification['type'] === 'payment') {
        // Remove Transaction ID information
        $notifications[$key]['message'] = preg_replace('/Transaction ID: [a-zA-Z0-9]+/', '', $notification['message']);
        $notifications[$key]['message'] = str_replace('  ', ' ', $notifications[$key]['message']); // Clean up extra spaces
        $notifications[$key]['message'] = trim($notifications[$key]['message']); // Trim any trailing spaces
    }
}

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header("Location: laborer_notifications.php");
    exit();
}

// Mark specific notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    header("Location: laborer_notifications.php");
    exit();
}

// Delete notification if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    header("Location: laborer_notifications.php");
    exit();
}

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    if ($notification['is_read'] == 0) {
        $unread_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 900px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .notification-counter {
            background: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
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
        
        .notification-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: background-color 0.2s;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: #f9f9f9;
        }
        
        .notification-item.unread {
            background-color: #f0f7ff;
        }
        
        .notification-item.unread:hover {
            background-color: #e5f1ff;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-icon.job {
            background: #4CAF50;
            color: white;
        }
        
        .notification-icon.message {
            background: #6f42c1;
            color: white;
        }
        
        .notification-icon.payment {
            background: #ffc107;
            color: #000;
        }
        
        .notification-icon.admin {
            background: #dc3545;
            color: white;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .notification-message {
            color: #666;
            font-size: 14px;
        }
        
        .notification-time {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-link {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        
        .action-link:hover {
            text-decoration: underline;
        }
        
        .action-link.delete {
            color: #dc3545;
        }
        
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>
                Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="notification-counter"><?php echo $unread_count; ?> unread</span>
                <?php endif; ?>
            </h1>
            
            <div class="action-buttons">
                <?php if ($unread_count > 0): ?>
                    <a href="laborer_notifications.php?mark_all_read=1" class="btn btn-primary">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="notification-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You don't have any notifications at the moment</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-icon <?php echo $notification['type']; ?>">
                            <?php
                            $icon = 'fa-bell';
                            switch ($notification['type']) {
                                case 'job':
                                    $icon = 'fa-briefcase';
                                    break;
                                case 'message':
                                    $icon = 'fa-comment';
                                    break;
                                case 'payment':
                                    $icon = 'fa-money-bill';
                                    break;
                                case 'admin':
                                    $icon = 'fa-shield-alt';
                                    break;
                            }
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        
                        <div class="notification-content">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-time">
                                <?php
                                $notification_time = strtotime($notification['created_at']);
                                $now = time();
                                $diff = $now - $notification_time;
                                
                                if ($diff < 60) {
                                    echo "Just now";
                                } elseif ($diff < 3600) {
                                    echo floor($diff / 60) . " minutes ago";
                                } elseif ($diff < 86400) {
                                    echo floor($diff / 3600) . " hours ago";
                                } elseif ($diff < 604800) {
                                    echo floor($diff / 86400) . " days ago";
                                } else {
                                    echo date('M j, Y', $notification_time);
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="notification-actions">
                            <?php if (!$notification['is_read']): ?>
                                <a href="laborer_notifications.php?mark_read=<?php echo $notification['id']; ?>" class="action-link">
                                    Mark as Read
                                </a>
                            <?php endif; ?>
                            <a href="laborer_notifications.php?delete=<?php echo $notification['id']; ?>" class="action-link delete"
                               onclick="return confirm('Are you sure you want to delete this notification?');">
                                Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>