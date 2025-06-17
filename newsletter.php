<?php
require_once 'config.php';

// Check if user is admin - if so, show the admin interface
if (isLoggedIn() && isAdmin() && !isset($_POST['email'])) {
    // Admin interface for newsletter entries
    
    // Handle edit submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = (int)$_POST['id'];
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Invalid email format";
        } else {
            // Check if email already exists (except for this ID)
            $stmt = $conn->prepare("SELECT id FROM newsletters WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Email already exists in the newsletter list";
            } else {
                // Update the email
                $stmt = $conn->prepare("UPDATE newsletters SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $email, $id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Newsletter email updated successfully";
                } else {
                    $_SESSION['error'] = "Error updating newsletter email: " . $conn->error;
                }
            }
        }
        
        // Redirect to refresh
        header("Location: newsletter.php");
        exit();
    }
    
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $stmt = $conn->prepare("DELETE FROM newsletters WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Newsletter subscription deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting subscription: " . $conn->error;
        }
        
        // Redirect to refresh
        header("Location: newsletter.php");
        exit();
    }
    
    // Get newsletter entries
    $query = "SELECT * FROM newsletters ORDER BY created_at DESC";
    $result = $conn->query($query);
    $entries = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    // Get entry for editing
    $edit_entry = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $stmt = $conn->prepare("SELECT * FROM newsletters WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $edit_entry = $result->fetch_assoc();
        }
    }
    
    // Display admin interface - removed the include for admin_header.php
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Newsletter Subscriptions | Admin Dashboard</title>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/admin.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    </head>
    <body>
        <!-- Include the admin sidebar -->
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <!-- Main content area -->
        <div class="main-content">
            <div class="container">
                <div class="dashboard-header">
                    <h1>Newsletter Subscriptions</h1>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($edit_entry): ?>
                
                    <div class="card-header">
                        <h2 class="card-title">Edit Newsletter Subscription</h2>
                        <a href="newsletter.php" class="btn btn-secondary">Cancel</a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo $edit_entry['id']; ?>">
                            
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($edit_entry['name']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_entry['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <input type="text" id="status" value="<?php echo htmlspecialchars(ucfirst($edit_entry['status'])); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="created_at">Subscribed At</label>
                                <input type="text" id="created_at" value="<?php echo date('M j, Y g:i A', strtotime($edit_entry['created_at'])); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
               
                <?php else: ?>
                
                
                    <div class="card-header">
                        <h2 class="card-title">All Newsletter Subscriptions</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($entries)): ?>
                            <div class="empty-state">
                                <i class="fas fa-envelope"></i>
                                <h3>No newsletter subscriptions found</h3>
                                <p>No users have subscribed to your newsletter yet.</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Date Subscribed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entries as $entry): ?>
                                        <tr>
                                            <td><?php echo $entry['id']; ?></td>
                                            <td><?php echo htmlspecialchars($entry['name']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['email']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $entry['status'] == 'subscribed' ? 'badge-primary' : 'badge-info'; ?>">
                                                    <?php echo ucfirst($entry['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($entry['created_at'])); ?></td>
                                            <td class="action-btns">
                                                <a href="?action=edit&id=<?php echo $entry['id']; ?>" class="action-btn edit-btn">Edit</a>
                                                <a href="?action=delete&id=<?php echo $entry['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this subscription?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                
                
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit; // Stop execution after displaying admin page
}

// This part is executed only for form submissions from the front-end
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $response = array();
    
    try {
        // Sanitize inputs
        $name = sanitize_input($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM newsletters WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Email already subscribed');
        }

        // Insert new subscription
        $stmt = $conn->prepare("INSERT INTO newsletters (name, email) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $email);
        
        if ($stmt->execute()) {
            // Send confirmation email
            $to = $email;
            $subject = "Newsletter Subscription Confirmation";
            $message = "Dear $name,\n\nThank you for subscribing to our newsletter!";
            $headers = "From: noreply@quickhirelabor.com";
            
            mail($to, $subject, $message, $headers);
            
            $response = array(
                'status' => 'success',
                'message' => 'Thank you for subscribing to our newsletter!'
            );
        } else {
            throw new Exception('Database error occurred');
        }
        
    } catch (Exception $e) {
        $response = array(
            'status' => 'error',
            'message' => $e->getMessage()
        );
    }
    
    echo json_encode($response);
    exit;
}

// If neither admin view nor form submission, redirect to home
header("Location: index.php");
exit;
?>
