<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a laborer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laborer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = false;
$error = null;

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    $priority = sanitize_input($_POST['priority']);
    
    // Validate input
    if (empty($subject) || empty($message)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert new ticket
        $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, message, priority) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $subject, $message, $priority);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Failed to create ticket: " . $conn->error;
        }
    }
}

// Get user's tickets
$stmt = $conn->prepare("
    SELECT t.*, 
           CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM support_tickets t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/support.css">
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>
    
    <div class="support-container">
        <div class="support-header">
            <h1>Support Tickets</h1>
            <button id="createTicketBtn" class="create-ticket-btn">Create New Ticket</button>
        </div>

        <?php if ($success): ?>
            <div class="alert success">Your ticket has been submitted successfully!</div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div id="ticketForm" class="ticket-form" style="display: <?php echo ($success || $error) ? 'block' : 'none'; ?>">
            <h3>Create New Support Ticket</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">Submit Ticket</button>
            </form>
        </div>
        
        <div class="ticket-filters">
            <select id="statusFilter" class="filter-select">
                <option value="">All Statuses</option>
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="closed">Closed</option>
            </select>
            
            <select id="priorityFilter" class="filter-select">
                <option value="">All Priorities</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
            </select>
        </div>
        
        <div class="ticket-list">
            <?php if (empty($tickets)): ?>
                <p>You have no support tickets yet.</p>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card" data-status="<?php echo $ticket['status']; ?>" data-priority="<?php echo $ticket['priority']; ?>">
                        <div class="ticket-header">
                            <div class="ticket-id">Ticket #<?php echo $ticket['id']; ?></div>
                            <div>
                                <span class="status-badge status-<?php echo str_replace('_', '-', $ticket['status']); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                                <span class="status-badge status-<?php echo $ticket['priority']; ?>">
                                    <?php echo ucfirst($ticket['priority']); ?> Priority
                                </span>
                            </div>
                        </div>
                        
                        <div class="ticket-content">
                            <div class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></div>
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
                                        <strong>Response:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['responded_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Toggle ticket creation form
        document.getElementById('createTicketBtn').addEventListener('click', function() {
            const form = document.getElementById('ticketForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
        
        // Filter tickets
        function filterTickets() {
            const statusFilter = document.getElementById('statusFilter').value;
            const priorityFilter = document.getElementById('priorityFilter').value;
            const tickets = document.querySelectorAll('.ticket-card');
            
            tickets.forEach(ticket => {
                let visible = true;
                
                if (statusFilter && ticket.dataset.status !== statusFilter) {
                    visible = false;
                }
                
                if (priorityFilter && ticket.dataset.priority !== priorityFilter) {
                    visible = false;
                }
                
                ticket.style.display = visible ? 'block' : 'none';
            });
        }
        
        document.getElementById('statusFilter').addEventListener('change', filterTickets);
        document.getElementById('priorityFilter').addEventListener('change', filterTickets);
    </script>
</body>
</html>