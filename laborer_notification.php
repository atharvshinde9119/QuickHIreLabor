<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laborer') {
    header("Location: login.php");
    exit();
}

$laborer_id = $_SESSION['user_id'];

// Mark single notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_single_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $laborer_id);
    $stmt->execute();
}

// Mark all notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $laborer_id);
    $stmt->execute();
}

// Handle delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notification_id = (int)$_POST['notification_id'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $laborer_id);
    $stmt->execute();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total notifications count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $laborer_id);
$stmt->execute();
$total_notifications = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $limit);

// Get notifications
$stmt = $conn->prepare("SELECT id, title, message, is_read, DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as notification_date FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $laborer_id, $limit, $offset);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread count
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $laborer_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/laborer.css">
    <style>
        .notification-section {
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }
        .mark-read-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 5px 15px;
            cursor: pointer;
            border-radius: 4px;
        }
        .pagination {
            margin: 20px 0;
            text-align: center;
        }
        .pagination a, .pagination span {
            margin: 0 5px;
            padding: 8px 16px;
            text-decoration: none;
            color: #007bff;
        }
        .pagination .active {
            font-weight: bold;
            color: #000;
        }
        .delete-btn {
            float: right;
            cursor: pointer;
            border: none;
            background: none;
            color: #dc3545;
        }
        .notification-list li:hover .delete-btn {
            opacity: 1;
        }
        .notification-filters {
            margin-bottom: 20px;
        }
        .filter-btn {
            padding: 5px 15px;
            margin-right: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
        }
        .filter-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="content">
        <header>
            <h1>Notifications <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?></span><?php endif; ?></h1>
            <?php if ($unread_count > 0): ?>
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_read" class="mark-read-btn">Mark All as Read</button>
            </form>
            <?php endif; ?>
        </header>

        <section class="notification-section">
            <h2>Recent Notifications</h2>
            <ul class="notification-list">
                <?php if (empty($notifications)): ?>
                    <li>No notifications found.</li>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <li class="<?php echo $notif['is_read'] ? 'read' : 'unread'; ?>">
                            <strong><?php echo htmlspecialchars($notif['title']); ?>:</strong>
                            <?php echo htmlspecialchars($notif['message']); ?>
                            <span class="time"><?php echo $notif['notification_date']; ?></span>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                <button type="submit" name="mark_single_read" class="mark-read-btn">Mark as Read</button>
                                <button type="submit" name="delete_notification" class="delete-btn" onclick="return confirm('Delete this notification?')">×</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

            <!-- Add pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>