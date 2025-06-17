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

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $subject = sanitize_input($_POST['subject']);
        $message = sanitize_input($_POST['message']);
        $priority = sanitize_input($_POST['priority']);
        
        $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, message, priority) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $subject, $message, $priority);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Support ticket submitted successfully';
        } else {
            throw new Exception('Failed to submit support ticket');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode($response);
        exit();
    }
}

// Get user's support tickets
$stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Support - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }

        .support-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .ticket-form {
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .ticket-list {
            list-style: none;
            padding: 0;
        }

        .ticket-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-open { background: #ffc107; }
        .status-in_progress { background: #17a2b8; color: white; }
        .status-closed { background: #28a745; color: white; }

        .priority-high { color: #dc3545; }
        .priority-medium { color: #ffc107; }
        .priority-low { color: #28a745; }

        .submit-btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #45a049;
        }

        .admin-response {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="container">
        <div class="support-section">
            <h2>Create New Support Ticket</h2>
            <form id="supportForm" class="ticket-form">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" required>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <button type="submit" class="submit-btn">Submit Ticket</button>
            </form>
        </div>

        <div class="support-section">
            <h2>Your Support Tickets</h2>
            <ul class="ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                    <li class="ticket-item">
                        <div class="ticket-header">
                            <h3><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                            <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                            </span>
                        </div>
                        <p class="priority-<?php echo $ticket['priority']; ?>">
                            Priority: <?php echo ucfirst($ticket['priority']); ?>
                        </p>
                        <p><?php echo htmlspecialchars($ticket['message']); ?></p>
                        <?php if ($ticket['admin_response']): ?>
                            <div class="admin-response">
                                <strong>Admin Response:</strong>
                                <p><?php echo htmlspecialchars($ticket['admin_response']); ?></p>
                                <small>Responded at: <?php echo date('M j, Y H:i', strtotime($ticket['responded_at'])); ?></small>
                            </div>
                        <?php endif; ?>
                        <small>Created: <?php echo date('M j, Y H:i', strtotime($ticket['created_at'])); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <script>
        document.getElementById('supportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('c_support.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the ticket');
            });
        });
    </script>
</body>
</html>
