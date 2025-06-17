<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Get payment ID from URL
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get payment details with related information
$sql = "SELECT p.*, transaction_id, 
        j.title as job_title, 
        j.description as job_description,
        j.status as job_status,
        c.first_name as customer_name,
        c.email as customer_email,
        c.phone as customer_phone,
        l.first_name as laborer_name,
        l.email as laborer_email,
        l.phone as laborer_phone
        
        FROM payments p
        JOIN jobs j ON p.job_id = j.id
        JOIN users c ON j.customer_id = c.id
        LEFT JOIN users l ON j.laborer_id = l.id
        WHERE p.id = ?";


$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: payments.php");
    exit();
}

$payment = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payment - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Payment Details</h2>
            <a href="payments.php" class="btn-back">Back to Payments</a>
        </header>

        <div class="payment-details">
            <div class="detail-section">
                <h3>Payment Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Payment ID:</label>
                        <span>#<?php echo $payment['id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Amount:</label>
                        <span>₹<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Status:</label>
                        <span class="status-badge <?php echo $payment['status']; ?>">
                            <?php echo ucfirst($payment['status']); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Date:</label>
                        <span><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></span>
                    </div>
                    <div class="detail-item transaction-id-container">
                        <label>Transaction ID:</label>
                        <?php if (!empty($payment['transaction_id'])): ?>
                            <span class="transaction-id"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                        <?php else: ?>
                            <span class="transaction-id empty">No Transaction ID Available</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h3>Job Details</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Job Title:</label>
                        <span><?php echo $payment['job_title']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Description:</label>
                        <span><?php echo $payment['job_description']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Job Status:</label>
                        <span class="status-badge <?php echo $payment['job_status']; ?>">
                            <?php echo ucfirst($payment['job_status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h3>Customer Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Name:</label>
                        <span><?php echo $payment['customer_name']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Email:</label>
                        <span><?php echo $payment['customer_email']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Phone:</label>
                        <span><?php echo $payment['customer_phone']; ?></span>
                    </div>
                </div>
            </div>

            <?php if ($payment['laborer_name']): ?>
            <div class="detail-section">
                <h3>Laborer Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Name:</label>
                        <span><?php echo $payment['laborer_name']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Email:</label>
                        <span><?php echo $payment['laborer_email']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Phone:</label>
                        <span><?php echo $payment['laborer_phone']; ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .payment-details {
            max-width: 1000px;
            margin: 20px;
        }
        .detail-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-item label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #666;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            display: inline-block;
        }
        .status-badge.pending { background: #f39c12; }
        .status-badge.completed { background: #27ae60; }
        .status-badge.refunded { background: #e74c3c; }
        .btn-back {
            background: #2196F3;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }
        .transaction-id-container {
            grid-column: 1 / -1;
        }
        .transaction-id {
            padding: 8px 12px;
            background-color: #e8f4fd;
            border: 1px solid #add8e6;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            display: inline-block;
        }
        .transaction-id.empty {
            background-color: #f8f8f8;
            border: 1px dashed #ccc;
            color: #888;
            font-style: italic;
        }
    </style>
</body>
</html>
