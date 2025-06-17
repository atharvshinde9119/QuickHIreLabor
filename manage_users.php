<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Handle delete user if requested
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Don't allow deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        $delete_error = "You cannot delete your own account.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete user's related data - updated with additional tables
            $tables = [
                "DELETE FROM support_tickets WHERE user_id = ?", // Add support tickets first
                "DELETE FROM reviews WHERE reviewer_id = ? OR reviewed_id = ?", // Handle reviews
                "DELETE FROM ratings WHERE rater_id = ? OR ratee_id = ?", // Handle ratings
                "DELETE FROM laborer_settings WHERE user_id = ?",
                "DELETE FROM notification_preferences WHERE user_id = ?",
                "DELETE FROM notifications WHERE user_id = ?",
                "DELETE FROM applications WHERE laborer_id = ?",
                "UPDATE jobs SET laborer_id = NULL WHERE laborer_id = ?"
            ];
            
            // Execute each statement
            foreach ($tables as $index => $sql) {
                $stmt = $conn->prepare($sql);
                
                // Special binding for tables with multiple occurrences of user_id
                if (strpos($sql, "OR") !== false) {
                    $stmt->bind_param("ii", $user_id, $user_id);
                } else {
                    $stmt->bind_param("i", $user_id);
                }
                
                $stmt->execute();
            }
            
            // Delete jobs created by this user or reassign them
            // Option 1: Delete jobs created by this user
            // $deleteJobsStmt = $conn->prepare("DELETE FROM jobs WHERE customer_id = ?");
            // $deleteJobsStmt->bind_param("i", $user_id);
            // $deleteJobsStmt->execute();
            
            // Option 2: Reassign jobs to admin (safer if jobs have payments/applications)
            $adminId = null;
            $adminQuery = $conn->query("SELECT id FROM users WHERE role = 'admin' AND id != $user_id LIMIT 1");
            if ($adminQuery && $adminRow = $adminQuery->fetch_assoc()) {
                $adminId = $adminRow['id'];
                $reassignStmt = $conn->prepare("UPDATE jobs SET customer_id = ? WHERE customer_id = ?");
                $reassignStmt->bind_param("ii", $adminId, $user_id);
                $reassignStmt->execute();
            }
            
            // Finally delete the user
            $deleteUser = $conn->prepare("DELETE FROM users WHERE id = ?");
            $deleteUser->bind_param("i", $user_id);
            $deleteUser->execute();
            
            $conn->commit();
            $delete_success = "User deleted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $delete_error = "Error deleting user: " . $e->getMessage();
            error_log("Error deleting user ID $user_id: " . $e->getMessage());
        }
    }
}

// Get list of all users - Improved query with error logging
$users = [];
$sql = "SELECT * FROM users ORDER BY role, first_name, last_name";
$result = $conn->query($sql);

if ($result) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    // Debug information
    if (empty($users)) {
        error_log("Query returned no users: $sql");
    } else {
        error_log("Found " . count($users) . " users in database");
    }
} else {
    $error = "Error fetching users: " . $conn->error;
    error_log("Database error in manage_users.php: " . $conn->error);
}

// Get user counts by role
$userCounts = [
    'total' => 0,
    'admin' => 0,
    'customer' => 0,
    'laborer' => 0
];

$countSql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$countResult = $conn->query($countSql);

if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $userCounts[$row['role']] = $row['count'];
        $userCounts['total'] += $row['count'];
    }
}

// If there are no users or very few, let's add some demo users
if (count($users) < 5 && !isset($_GET['users_added'])) {
    // Check if we can add demo users
    $demoUsers = [
        ['John', 'Doe', 'john.doe@example.com', '123-456-7890', 'customer'],
        ['Jane', 'Smith', 'jane.smith@example.com', '234-567-8901', 'customer'],
        ['Bob', 'Johnson', 'bob.johnson@example.com', '345-678-9012', 'laborer'],
        ['Alice', 'Williams', 'alice.williams@example.com', '456-789-0123', 'laborer'],
        ['Mike', 'Brown', 'mike.brown@example.com', '567-890-1234', 'customer'],
        ['Sarah', 'Davis', 'sarah.davis@example.com', '678-901-2345', 'laborer'],
        ['David', 'Miller', 'david.miller@example.com', '789-012-3456', 'customer'],
        ['Emily', 'Wilson', 'emily.wilson@example.com', '890-123-4567', 'laborer']
    ];
    
    $usersAdded = 0;
    
    foreach ($demoUsers as $user) {
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $user[2]);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows == 0) {
            // Email doesn't exist, add the user
            $password = password_hash('password123', PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $user[0], $user[1], $user[2], $user[3], $password, $user[4]);
            
            if ($stmt->execute()) {
                $usersAdded++;
            }
        }
    }
    
    if ($usersAdded > 0) {
        // Refresh the page to show the new users, but avoid an infinite loop
        header("Location: manage_users.php?users_added=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .user-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex: 1;
            text-align: center;
        }
        .stat-card h3 {
            margin-top: 0;
            margin-bottom: 5px;
            color: #555;
        }
        .stat-card .count {
            font-size: 24px;
            font-weight: bold;
            color: #0355cc;
        }
        .role-admin { background-color: #fff8dc; }
        .role-customer { background-color: #f0f8ff; }
        .role-laborer { background-color: #f0fff0; }
        .actions {
            display: flex;
            gap: 10px;
        }
        .filter-options {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .filter-options button {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            color: #333; /* Darker text color for better contrast */
            font-weight: 500; /* Slightly bolder text */
            transition: all 0.2s ease; /* Smooth transition for hover effects */
        }
        
        .filter-options button:hover {
            background: #e9ecef; /* Slightly darker background on hover */
            border-color: #adb5bd;
        }
        
        .filter-options button.active {
            background: #0355cc;
            color: white;
            border-color: #0355cc;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Add shadow for emphasis */
        }
        
        .search-box {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 250px;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Manage Users</h2>
        </header>

        <?php if (isset($delete_success)): ?>
            <div class="alert success"><?php echo $delete_success; ?></div>
        <?php endif; ?>

        <?php if (isset($delete_error)): ?>
            <div class="alert error"><?php echo $delete_error; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="user-stats">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="count"><?php echo $userCounts['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Admins</h3>
                <div class="count"><?php echo $userCounts['admin']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Customers</h3>
                <div class="count"><?php echo $userCounts['customer']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Laborers</h3>
                <div class="count"><?php echo $userCounts['laborer']; ?></div>
            </div>
        </div>

        <div class="action-bar">
            <a href="add_user.php" class="btn">Add New User</a>
            
            <div class="filter-options">
                <input type="text" id="searchInput" class="search-box" placeholder="Search users...">
                <button class="filter-btn active" data-role="all">All</button>
                <button class="filter-btn" data-role="admin">Admins</button>
                <button class="filter-btn" data-role="customer">Customers</button>
                <button class="filter-btn" data-role="laborer">Laborers</button>
            </div>
        </div>

        <div class="table-section">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="role-<?php echo $user['role']; ?>">
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-edit">Edit</a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="manage_users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                           class="btn-delete"
                                           onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.')">
                                            Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Filter and search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('#usersTable tbody tr');
            
            // Filter by role
            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Update active button
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    
                    const role = button.getAttribute('data-role');
                    
                    // Filter table rows
                    tableRows.forEach(row => {
                        if (role === 'all' || row.classList.contains('role-' + role)) {
                            // Also apply search filter
                            const searchTerm = searchInput.value.toLowerCase();
                            const rowText = row.textContent.toLowerCase();
                            row.style.display = searchTerm === '' || rowText.includes(searchTerm) ? '' : 'none';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
            
            // Search functionality
            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.toLowerCase();
                const activeRole = document.querySelector('.filter-btn.active').getAttribute('data-role');
                
                tableRows.forEach(row => {
                    const roleMatch = activeRole === 'all' || row.classList.contains('role-' + activeRole);
                    const textMatch = row.textContent.toLowerCase().includes(searchTerm);
                    row.style.display = roleMatch && textMatch ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>
