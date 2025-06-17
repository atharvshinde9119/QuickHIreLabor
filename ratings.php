<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// Handle rating deletion if requested
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $rating_id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM ratings WHERE id = ?");
    $stmt->bind_param("i", $rating_id);
    
    if ($stmt->execute()) {
        $delete_success = "Rating deleted successfully.";
    } else {
        $delete_error = "Error deleting rating.";
    }
}

// Get all ratings with related information
$ratings = [];

// Check if ratings table exists
if (!tableExists($conn, 'ratings')) {
    $setup_error = "Table 'ratings' doesn't exist. Please run the SQL setup script.";
} else {
    // First, let's check the actual structure of the ratings table
    $tableInfo = [];
    $checkColumns = $conn->query("DESCRIBE ratings");
    if ($checkColumns) {
        while ($col = $checkColumns->fetch_assoc()) {
            $tableInfo[] = $col['Field'];
        }
    }
    
    // Now use the correct column names based on the actual table structure
    // The SQL setup file shows ratings table has rater_id and ratee_id, not customer_id and laborer_id
    $sql = "SELECT r.*, 
            j.title as job_title,
            c.first_name as customer_first_name,
            c.last_name as customer_last_name,
            l.first_name as laborer_first_name,
            l.last_name as laborer_last_name,
            j.id as job_id
            FROM ratings r
            JOIN jobs j ON r.job_id = j.id
            JOIN users c ON r.rater_id = c.id
            JOIN users l ON r.ratee_id = l.id
            ORDER BY r.created_at DESC";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format the names for display
            $row['customer_name'] = $row['customer_first_name'] . ' ' . $row['customer_last_name'];
            $row['laborer_name'] = $row['laborer_first_name'] . ' ' . $row['laborer_last_name'];
            $ratings[] = $row;
        }
    } else if ($result === false) {
        // Display the SQL error for debugging
        $setup_error = "SQL Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratings & Feedback - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Ratings & Feedback Management</h2>
        </header>

        <?php if (isset($delete_success)): ?>
            <div class="alert success"><?php echo $delete_success; ?></div>
        <?php endif; ?>

        <?php if (isset($delete_error)): ?>
            <div class="alert error"><?php echo $delete_error; ?></div>
        <?php endif; ?>

        <?php if (isset($setup_error)): ?>
            <div class="alert error"><?php echo $setup_error; ?></div>
        <?php endif; ?>

        <div class="table-section">
            <h3>All Ratings</h3>
            <?php if (!empty($ratings)): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Job</th>
                        <th>Customer</th>
                        <th>Laborer</th>
                        <th>Rating</th>
                        <th>Feedback</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($ratings as $rating): ?>
                        <tr>
                            <td>#<?php echo $rating['id']; ?></td>
                            <td>
                                <a href="view_job.php?id=<?php echo $rating['job_id']; ?>">
                                    <?php echo $rating['job_title']; ?>
                                </a>
                            </td>
                            <td><?php echo $rating['customer_name']; ?></td>
                            <td><?php echo $rating['laborer_name']; ?></td>
                            <td>
                                <div class="rating-stars">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating['rating']) {
                                            echo '★';
                                        } else {
                                            echo '☆';
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <?php $feedback = isset($rating['feedback']) ? $rating['feedback'] : ''; ?>
                            <td><?php echo htmlspecialchars($feedback); ?></td>
                            <td><?php echo date('M d, Y', strtotime($rating['created_at'])); ?></td>
                            <td>
                                <a href="ratings.php?action=delete&id=<?php echo $rating['id']; ?>" 
                                   class="btn-delete"
                                   onclick="return confirm('Are you sure you want to delete this rating?')">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p><?php echo isset($setup_error) ? "Fix database setup to view ratings." : "No ratings found."; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .rating-stars {
            color: #FFD700;
            font-size: 18px;
        }
    </style>
</body>
</html>
