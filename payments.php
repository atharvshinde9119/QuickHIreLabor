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

// Update payment status if requested
if (isset($_POST['update_status'])) {
    $payment_id = (int)$_POST['payment_id'];
    $status = sanitize_input($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $payment_id);
    
    if ($stmt->execute()) {
        $update_success = "Payment status updated successfully.";
    } else {
        $update_error = "Error updating payment status.";
    }
}

// Get all payments with job and user details
$payments = [];

// Check if required tables exist
$requiredTables = ['payments', 'jobs', 'users'];
$allTablesExist = true;

foreach ($requiredTables as $table) {
    if (!tableExists($conn, $table)) {
        $allTablesExist = false;
        $setup_error = "Table '$table' doesn't exist. Please run the SQL setup script.";
        break;
    }
}

if ($allTablesExist) {
    $sql = "SELECT p.*, j.title as job_title, u.first_name as customer_name, l.first_name as laborer_name 
            FROM payments p 
            JOIN jobs j ON p.job_id = j.id 
            JOIN users u ON j.customer_id = u.id 
            LEFT JOIN users l ON j.laborer_id = l.id 
            ORDER BY p.created_at DESC";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Payment Management</h2>
        </header>

        <?php if (isset($update_success)): ?>
            <div class="alert success"><?php echo $update_success; ?></div>
        <?php endif; ?>

        <?php if (isset($update_error)): ?>
            <div class="alert error"><?php echo $update_error; ?></div>
        <?php endif; ?>

        <?php if (isset($setup_error)): ?>
            <div class="alert error"><?php echo $setup_error; ?></div>
        <?php endif; ?>

        <div class="table-section">
            <h3>All Payments</h3>
            <?php if (!empty($payments)): ?>
                <table>
                    <tr>
                        <th>Payment ID</th>
                        <th>Job</th>
                        <th>Customer</th>
                        <th>Laborer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>#<?php echo $payment['id']; ?></td>
                            <td><?php echo $payment['job_title']; ?></td>
                            <td><?php echo $payment['customer_name']; ?></td>
                            <td><?php echo $payment['laborer_name'] ?? 'Not Assigned'; ?></td>
                            <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo ucfirst($payment['status']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="status-select">
                                        <option value="pending" <?php echo $payment['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="completed" <?php echo $payment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="refunded" <?php echo $payment['status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                <a href="view_payment.php?id=<?php echo $payment['id']; ?>" class="btn-view">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No payments found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
