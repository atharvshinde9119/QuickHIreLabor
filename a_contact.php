<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Check if form_type column exists in contacts table, add it if not
$column_check = $conn->query("SHOW COLUMNS FROM contacts LIKE 'form_type'");
if ($column_check->num_rows == 0) {
    // Add form_type column to contacts table
    $alter_table_sql = "ALTER TABLE contacts ADD COLUMN form_type ENUM('index', 'contact') DEFAULT 'contact' NOT NULL";
    
    if ($conn->query($alter_table_sql) === TRUE) {
        $_SESSION['success'] = "Contacts table updated successfully with form_type column.";
    } else {
        $_SESSION['error'] = "Error updating contacts table: " . $conn->error;
    }
}

// Handle form submission for editing entries
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = (int)$_POST['id'];
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $message = sanitize_input($_POST['message']);
    
    $stmt = $conn->prepare("UPDATE contacts SET name = ?, email = ?, phone = ?, message = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $email, $phone, $message, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Contact entry updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating contact entry: " . $conn->error;
    }
    
    // Redirect to refresh the page
    header("Location: a_contact.php");
    exit();
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Contact entry deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting contact entry: " . $conn->error;
    }
    
    // Redirect to refresh the page
    header("Location: a_contact.php");
    exit();
}

// Get contact entries for display
$query = "SELECT * FROM contacts ORDER BY created_at DESC";
$result = $conn->query($query);
$entries = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get selected entry for editing
$edit_entry = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $edit_result = $stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $edit_entry = $edit_result->fetch_assoc();
    }
}

// Add sample data functionality
if (isset($_GET['sample_data']) && count($entries) == 0) {
    $sample_data = [
        [
            'form_type' => 'index',
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'phone' => '1234567890',
            'message' => 'I need help with a home renovation project. Please contact me.'
        ],
        [
            'form_type' => 'contact',
            'name' => 'Jane Smith',
            'email' => 'janesmith@example.com',
            'phone' => '9876543210',
            'message' => 'I have a question about your plumbing services.'
        ],
        [
            'form_type' => 'index',
            'name' => 'Robert Johnson',
            'email' => 'robert@example.com',
            'phone' => '5551234567',
            'message' => 'Looking for someone to help with lawn maintenance.'
        ]
    ];
    
    // Check if form_type column exists
    $column_check = $conn->query("SHOW COLUMNS FROM contacts LIKE 'form_type'");
    if ($column_check->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO contacts (form_type, name, email, phone, message) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($sample_data as $data) {
            $stmt->bind_param("sssss", $data['form_type'], $data['name'], $data['email'], $data['phone'], $data['message']);
            $stmt->execute();
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, phone, message) VALUES (?, ?, ?, ?)");
        
        foreach ($sample_data as $data) {
            $stmt->bind_param("ssss", $data['name'], $data['email'], $data['phone'], $data['message']);
            $stmt->execute();
        }
    }
    
    $_SESSION['success'] = "Sample contact entries added successfully.";
    header("Location: a_contact.php");
    exit();
}

// Count total contacts
$total_contacts = count($entries);

// Count contacts by form type
$index_contacts = 0;
$contact_contacts = 0;

foreach ($entries as $entry) {
    if (isset($entry['form_type'])) {
        if ($entry['form_type'] == 'index') {
            $index_contacts++;
        } else {
            $contact_contacts++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Entries | Admin Dashboard</title>
    <!-- Include the same CSS files as other admin pages -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Additional specific styles for this page only */
        .message-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Fix for buttons and badges to ensure text is visible */
        .btn-primary, .btn-success, .btn-danger, .btn-secondary {
            color: white !important;
        }
        
        .badge-primary {
            background-color: #007bff;
            color: white;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        
        /* Simplified card styling */
        /* .card {
            background-color: #fff;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            width: 80%;
        } */
        
        /* .card-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .card-body {
            padding: 15px;
        } */
        
        /* Enhanced table styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        
        .data-table th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .data-table tr:hover {
            background-color: #e9ecef;
        }
        
        /* Action buttons styling */
        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            display: inline-block;
            text-align: center;
            line-height: 1.5;
            transition: all 0.2s;
        }
        
        .edit-btn {
            background-color: #007bff;
            color: white;
            border: 1px solid #0069d9;
        }
        
        .edit-btn:hover {
            background-color: #0069d9;
            color: white;
        }
        
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: 1px solid #c82333;
        }
        
        .delete-btn:hover {
            background-color: #c82333;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
        }
    </style>
</head>
<body>
    <!-- Include the standard admin sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <!-- Main content area - use the same layout structure as other admin pages -->
    <div class="main-content">
        <div class="container">
            <div class="dashboard-header">
                <h1>Contact Entries Management</h1>
                <div class="action-buttons">
                    <?php if (empty($entries)): ?>
                    <a href="?sample_data=1" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Sample Data
                    </a>
                    <?php endif; ?>
                </div>
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
                    <h2 class="card-title">Edit Contact Entry #<?php echo $edit_entry['id']; ?></h2>
                    <a href="a_contact.php" class="btn btn-secondary">Cancel</a>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $edit_entry['id']; ?>">
                        
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($edit_entry['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_entry['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($edit_entry['phone']); ?>">
                        </div>
                        
                        <?php if (isset($edit_entry['form_type'])): ?>
                        <div class="form-group">
                            <label for="form_type">Form Type</label>
                            <input type="text" id="form_type" value="<?php echo htmlspecialchars(ucfirst($edit_entry['form_type'])); ?>" disabled>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group full-width">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" required><?php echo htmlspecialchars($edit_entry['message']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="submitted_at">Submitted At</label>
                            <input type="text" id="submitted_at" value="<?php echo date('M j, Y g:i A', strtotime($edit_entry['created_at'])); ?>" disabled>
                        </div>
                        
                        <div class="form-group full-width">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            
            <?php else: ?>
            
            
                <div class="card-header">
                    <h2 class="card-title">All Contact Entries</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($entries)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No contact entries found</h3>
                            <p>Contact form submissions will appear here.</p>
                            <a href="?sample_data=1" class="btn btn-primary mt-3">Add Sample Data</a>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <?php if ($column_check->num_rows > 0): ?>
                                    <th>Form Type</th>
                                    <?php endif; ?>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td><?php echo $entry['id']; ?></td>
                                        <?php if (isset($entry['form_type'])): ?>
                                        <td>
                                            <span class="badge <?php echo $entry['form_type'] == 'index' ? 'badge-primary' : 'badge-info'; ?>">
                                                <?php echo ucfirst($entry['form_type']); ?>
                                            </span>
                                        </td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($entry['name']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['email']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['phone']); ?></td>
                                        <td>
                                            <div class="message-preview" title="<?php echo htmlspecialchars($entry['message']); ?>">
                                                <?php echo htmlspecialchars($entry['message']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($entry['created_at'])); ?></td>
                                        <td class="action-btns">
                                            <a href="?action=edit&id=<?php echo $entry['id']; ?>" class="action-btn edit-btn">Edit</a>
                                            <a href="?action=delete&id=<?php echo $entry['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this entry?')">Delete</a>
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
