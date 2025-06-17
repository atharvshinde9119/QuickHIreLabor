<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';

// Handle response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    
    if ($_POST['action'] === 'respond') {
        $response = sanitize_input($_POST['response']);
        if (empty($response)) {
            $error = "Response cannot be empty";
        } else {
            $stmt = $conn->prepare("UPDATE support_tickets SET admin_response = ?, status = 'closed', responded_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $response, $ticket_id);
            if ($stmt->execute()) {
                $success = "Response sent successfully";
            } else {
                $error = "Failed to send response: " . $conn->error;
            }
        }
    } elseif ($_POST['action'] === 'change_status') {
        $status = sanitize_input($_POST['status']);
        $stmt = $conn->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $ticket_id);
        if ($stmt->execute()) {
            $success = "Status updated successfully";
        } else {
            $error = "Failed to update status: " . $conn->error;
        }
    } elseif ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM support_tickets WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        if ($stmt->execute()) {
            $success = "Ticket deleted successfully";
        } else {
            $error = "Failed to delete ticket: " . $conn->error;
        }
    }
}

// Get ticket counts
$counts = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'closed' => 0,
    'high' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM support_tickets");
if ($result) {
    $counts['total'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
if ($result) {
    $counts['open'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'in_progress'");
if ($result) {
    $counts['in_progress'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'closed'");
if ($result) {
    $counts['closed'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE priority = 'high'");
if ($result) {
    $counts['high'] = $result->fetch_assoc()['count'];
}

// Apply filters
$where_conditions = [];
$params = [];
$types = '';

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "t.status = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

if (isset($_GET['priority']) && !empty($_GET['priority'])) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $_GET['priority'];
    $types .= 's';
}

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $where_conditions[] = "t.user_id = ?";
    $params[] = (int)$_GET['user_id'];
    $types .= 'i';
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(t.subject LIKE ? OR t.message LIKE ?)";
    $search_term = "%" . $_GET['search'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all users for filter
$stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users ORDER BY name");
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get tickets
$query = "
    SELECT t.*, 
           CONCAT(u.first_name, ' ', u.last_name) as user_name,
           u.email as user_email
    FROM support_tickets t
    JOIN users u ON t.user_id = u.id
    $where_clause
    ORDER BY 
        CASE 
            WHEN t.priority = 'high' THEN 1
            WHEN t.priority = 'medium' THEN 2
            ELSE 3
        END,
        CASE 
            WHEN t.status = 'open' THEN 1
            WHEN t.status = 'in_progress' THEN 2
            ELSE 3
        END,
        t.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Management | Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/support.css">
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <div class="support-container">
        <div class="support-header">
            <h1>Support Ticket Management</h1>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="admin-ticket-counts">
            <div class="count-card">
                <div class="count-label">Total Tickets</div>
                <div class="count-number"><?php echo $counts['total']; ?></div>
            </div>
            <div class="count-card">
                <div class="count-label">Open Tickets</div>
                <div class="count-number"><?php echo $counts['open']; ?></div>
            </div>
            <div class="count-card">
                <div class="count-label">In Progress</div>
                <div class="count-number"><?php echo $counts['in_progress']; ?></div>
            </div>
            <div class="count-card">
                <div class="count-label">Closed Tickets</div>
                <div class="count-number"><?php echo $counts['closed']; ?></div>
            </div>
            <div class="count-card">
                <div class="count-label">High Priority</div>
                <div class="count-number"><?php echo $counts['high']; ?></div>
            </div>
        </div>
        
        <div class="admin-filters">
            <form method="GET" action="" id="filterForm">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search tickets..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                
                <select name="status" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Statuses</option>
                    <option value="open" <?php echo (isset($_GET['status']) && $_GET['status'] === 'open') ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo (isset($_GET['status']) && $_GET['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                    <option value="closed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                </select>
                
                <select name="priority" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Priorities</option>
                    <option value="low" <?php echo (isset($_GET['priority']) && $_GET['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo (isset($_GET['priority']) && $_GET['priority'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo (isset($_GET['priority']) && $_GET['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                </select>
                
                <select name="user_id" class="filter-select user-list" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="action-btn btn-respond">Apply Filters</button>
                <a href="support.php" class="action-btn btn-close">Reset</a>
            </form>
        </div>
        
        <div class="ticket-list">
            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <p>No support tickets found. All customer issues have been resolved!</p>
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card ticket-priority-<?php echo $ticket['priority']; ?>">
                        <div class="ticket-header">
                            <div>
                                <div class="ticket-id">Ticket #<?php echo $ticket['id']; ?></div>
                                <div class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                            </div>
                            <div>
                                <span class="status-badge status-<?php echo str_replace('_', '-', $ticket['status']); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                                <span class="status-badge status-<?php echo $ticket['priority']; ?>">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="ticket-content">
                            <p><strong>From:</strong> <?php echo htmlspecialchars($ticket['user_name']); ?> (<?php echo htmlspecialchars($ticket['user_email']); ?>)</p>
                            <div class="ticket-message"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></div>
                            
                            <?php if (!empty($ticket['admin_response'])): ?>
                                <div class="ticket-response">
                                    <div class="ticket-response-header">Admin Response:</div>
                                    <p><?php echo nl2br(htmlspecialchars($ticket['admin_response'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="ticket-meta">
                                <div>
                                    <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                </div>
                                <?php if ($ticket['responded_at']): ?>
                                    <div>
                                        <strong>Responded:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['responded_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="admin-actions">
                                <?php if ($ticket['status'] !== 'closed'): ?>
                                    <button class="action-btn btn-respond" onclick="showResponseForm(<?php echo $ticket['id']; ?>)">
                                        Respond & Close
                                    </button>
                                    
                                    <form method="POST" action="" style="display: inline-block;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <input type="hidden" name="action" value="change_status">
                                        
                                        <?php if ($ticket['status'] === 'open'): ?>
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" class="action-btn btn-respond">Mark In Progress</button>
                                        <?php else: ?>
                                            <input type="hidden" name="status" value="open">
                                            <button type="submit" class="action-btn btn-respond">Reopen Ticket</button>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="" style="display: inline-block;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="status" value="open">
                                        <button type="submit" class="action-btn btn-respond">Reopen Ticket</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" action="" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this ticket?');">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="action-btn btn-delete">Delete</button>
                                </form>
                            </div>
                            
                            <div id="response-form-<?php echo $ticket['id']; ?>" class="admin-response-form" style="display: none;">
                                <form method="POST" action="">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <input type="hidden" name="action" value="respond">
                                    
                                    <div class="form-group">
                                        <label for="response-<?php echo $ticket['id']; ?>">Your Response:</label>
                                        <textarea id="response-<?php echo $ticket['id']; ?>" name="response" rows="5" required></textarea>
                                    </div>
                                    
                                    <button type="submit" class="submit-btn">Send Response & Close Ticket</button>
                                    <button type="button" class="action-btn btn-close" onclick="hideResponseForm(<?php echo $ticket['id']; ?>)">Cancel</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function showResponseForm(ticketId) {
            document.getElementById('response-form-' + ticketId).style.display = 'block';
        }
        
        function hideResponseForm(ticketId) {
            document.getElementById('response-form-' + ticketId).style.display = 'none';
        }
    </script>
</body>
</html>
