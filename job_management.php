<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Handle job status updates
if (isset($_POST['update_status'])) {
    $job_id = (int)$_POST['job_id'];
    $status = sanitize_input($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $job_id);
    
    if ($stmt->execute()) {
        $update_success = "Job status updated successfully.";
    } else {
        $update_error = "Error updating job status.";
    }
}

// Handle job deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $job_id = (int)$_GET['id'];
    
    // First check for payments and ratings
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM payments WHERE job_id = ?) as payment_count,
            (SELECT COUNT(*) FROM ratings WHERE job_id = ?) as rating_count
    ");
    $stmt->bind_param("ii", $job_id, $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts = $result->fetch_assoc();
    
    if ($counts['payment_count'] > 0) {
        $delete_error = "Cannot delete job with associated payments.";
    } elseif ($counts['rating_count'] > 0) {
        $delete_error = "Cannot delete job with associated ratings.";
    } else {
        $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->bind_param("i", $job_id);
        
        if ($stmt->execute()) {
            $delete_success = "Job deleted successfully.";
        } else {
            $delete_error = "Error deleting job: " . $stmt->error;
        }
    }
}

// Get all jobs with related information
$jobs = [];
$sql = "SELECT j.*, 
        c.first_name as customer_name,
        l.first_name as laborer_name
        FROM jobs j
        JOIN users c ON j.customer_id = c.id
        LEFT JOIN users l ON j.laborer_id = l.id
        ORDER BY j.created_at DESC";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Management - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">

</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Job Management</h2>
             <a href="add_job.php" class="btn-add">Add New Job</a>
        </header>

        <?php if (isset($update_success)): ?>
            <div class="alert success"><?php echo $update_success; ?></div>
        <?php endif; ?>

        <?php if (isset($update_error)): ?>
            <div class="alert error"><?php echo $update_error; ?></div>
        <?php endif; ?>

        <?php if (isset($delete_success)): ?>
            <div class="alert success"><?php echo $delete_success; ?></div>
        <?php endif; ?>

        <?php if (isset($delete_error)): ?>
            <div class="alert error"><?php echo $delete_error; ?></div>
        <?php endif; ?>

        <div class="table-section">
            <h3>All Job Requests</h3>
            <?php if (!empty($jobs)): ?>
                <table>
                    <tr>
                        <th>Job ID</th>
                        <th>Title</th>
                        <th>Customer</th>
                        <th>Laborer</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td>#<?php echo $job['id']; ?></td>
                            <td><?php echo $job['title']; ?></td>
                            <td><?php echo $job['customer_name']; ?></td>
                            <td><?php echo $job['laborer_name'] ?? 'Not Assigned'; ?></td>
                            <td>₹<?php 
                                $jobPrice = isset($job['price']) ? $job['price'] : (isset($job['budget']) ? $job['budget'] : 0.00);
                                echo number_format($jobPrice, 2); 
                            ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="status-select">
                                        <option value="pending" <?php echo $job['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="assigned" <?php echo $job['status'] == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                        <option value="completed" <?php echo $job['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $job['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                            <td>
                                <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn-edit">Edit</a>
                                <a href="view_job.php?id=<?php echo $job['id']; ?>" class="btn-view">View</a>
                                <a href="job_management.php?action=delete&id=<?php echo $job['id']; ?>" 
                                   class="btn-delete"
                                   onclick="return confirm('Are you sure you want to delete this job?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No jobs found.</p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .status-select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .btn-edit, .btn-view, .btn-delete {
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-edit { background: #4CAF50; color: white; }
        .btn-view { background: #2196F3; color: white; }
        .btn-delete { background: #f44336; color: white; }
    </style>
</body>
</html>