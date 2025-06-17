<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT NULL AS profile_pic, 
                              CONCAT(first_name, ' ', last_name) AS name, 
                              email, phone, id, role
                       FROM users 
                       WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Mark notification as read
if (isset($_POST['mark_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    exit();
}

// Get all notifications for the user
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications count for badge
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'];

// Add notification type icons
$notification_icons = [
    'job_status' => '🔧',
    'payment' => '💰',
    'message' => '✉️',
    'general' => '📢'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }

        .notification-list {
            list-style: none;
            padding: 0;
        }

        .notification-item {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            position: relative;
        }

        .notification-item:hover {
            transform: translateY(-2px);
        }

        .notification-item.unread {
            border-left: 4px solid #4CAF50;
            background-color: #f8fff8;
        }

        .notification-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 8px;
        }

        .notification-message {
            color: #666;
            margin-bottom: 10px;
        }

        .notification-time {
            font-size: 12px;
            color: #888;
        }

        .notification-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .status-unread {
            background-color: #4CAF50;
            color: white;
        }

        .status-read {
            background-color: #ddd;
            color: #666;
        }

        .clear-all {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .clear-all:hover {
            background-color: #45a049;
        }

        .no-notifications {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            color: #666;
        }

        @media (max-width: 768px) {
            .container {
                margin-left: 0;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="container">
        <h2>Notifications</h2>
        
        <?php if (!empty($notifications)): ?>
            <button class="clear-all" onclick="markAllRead()">Mark All as Read</button>
            <ul class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <li class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                        data-id="<?php echo $notification['id']; ?>">
                        <div class="notification-icon"><?php echo $notification_icons[$notification['type']] ?? '📢'; ?></div>
                        <div class="notification-status <?php echo !$notification['is_read'] ? 'status-unread' : 'status-read'; ?>">
                            <?php echo !$notification['is_read'] ? 'New' : 'Read'; ?>
                        </div>
                        <h3 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h3>
                        <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <span class="notification-time">
                            <?php echo date('F j, Y g:i a', strtotime($notification['created_at'])); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="no-notifications">
                <h3>No notifications yet</h3>
                <p>You'll see notifications about your job postings and messages here.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Mark individual notification as read when clicked
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.id;
                markAsRead(notificationId);
            });
        });

        function markAsRead(notificationId) {
            fetch('c_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `mark_read=1&notification_id=${notificationId}`
            })
            .then(() => {
                const item = document.querySelector(`[data-id="${notificationId}"]`);
                item.classList.remove('unread');
                item.querySelector('.notification-status').className = 'notification-status status-read';
                item.querySelector('.notification-status').textContent = 'Read';
            })
            .catch(error => console.error('Error:', error));
        }

        function markAllRead() {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                markAsRead(item.dataset.id);
            });
        }
    </script>
</body>
</html>
