<?php
require_once 'config.php';

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

// Check if payment_history table exists, if not create it
$sql = "CREATE TABLE IF NOT EXISTS payment_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    job_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('online', 'cash', 'qr') DEFAULT 'online',
    transaction_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (job_id) REFERENCES jobs(id)
)";
$conn->query($sql);

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    try {
        $job_id = (int)$_POST['job_id'];
        $amount = floatval($_POST['amount']);
        $transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
        
        // Validate transaction ID
        if (empty($transaction_id)) {
            throw new Exception('Transaction ID is required');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Check if transaction ID already exists
        $stmt = $conn->prepare("SELECT id FROM payment_history WHERE transaction_id = ?");
        $stmt->bind_param("s", $transaction_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Transaction ID already used. Please enter a unique transaction ID.');
        }
        
        // Modified query to check job status
        $stmt = $conn->prepare("
            SELECT j.*, CONCAT(u.first_name, ' ', u.last_name) AS laborer_name, u.id as laborer_id
            FROM jobs j 
            JOIN users u ON j.laborer_id = u.id 
            WHERE j.id = ? AND j.customer_id = ? 
            AND j.status IN ('assigned', 'in_progress', 'completed')
            AND NOT EXISTS (
                SELECT 1 FROM payments p 
                WHERE p.job_id = j.id 
                AND p.status = 'completed'
            )
        ");
        $stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        
        if (!$job) {
            throw new Exception('Invalid job or payment already processed');
        }
        
        // Process payment only if amount matches job budget
        if ($job['budget'] != $amount) {
            throw new Exception('Payment amount does not match job budget');
        }
        
        // Create payment record
        $stmt = $conn->prepare("
            INSERT INTO payments (job_id, amount, status, transaction_id) 
            VALUES (?, ?, 'completed', ?)
            ON DUPLICATE KEY UPDATE status = 'completed', transaction_id = ?
        ");
        $stmt->bind_param("idss", $job_id, $amount, $transaction_id, $transaction_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to process payment');
        }
        http://localhost/Quickhirelabor2/payments.php
        
        // Update job status
        $stmt = $conn->prepare("UPDATE jobs SET status = 'completed' WHERE id = ?");
        $stmt->bind_param("i", $job_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update job status');
        }
        
        // Add payment transaction record with user-provided transaction ID
        $stmt = $conn->prepare("
            INSERT INTO payment_history (
                user_id, job_id, amount, payment_method, transaction_id
            ) VALUES (?, ?, ?, 'online', ?)
        ");
        $stmt->bind_param("iids", $user_id, $job_id, $amount, $transaction_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to record payment history');
        }
        
        // Create notifications for both parties
        createNotification(
            $job['laborer_id'],
            'Payment Received',
            "Payment of ₹{$amount} received for job: {$job['title']}. Transaction ID: {$transaction_id}",
            'payment'
        );
        
        createNotification(
            $_SESSION['user_id'],
            'Payment Sent',
            "Payment of ₹{$amount} sent for job: {$job['title']}. Transaction ID: {$transaction_id}",
            'payment'
        );
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Payment processed successfully. Transaction ID: ' . $transaction_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
        error_log("Payment error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit();
}

// Add payment statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,COUNT(*) as total_jobs,
        SUM(CASE WHEN p.status = 'completed' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN p.status = 'pending' THEN amount ELSE 0 END) as total_pending
    FROM payments p 
    JOIN jobs j ON p.job_id = j.id 
    WHERE j.customer_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment_stats = $stmt->get_result()->fetch_assoc();

// Get pending payments with additional details
$stmt = $conn->prepare("
 SELECT j.id as job_id, j.title, j.status,
        l.id as laborer_id, 
        CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
        l.phone as laborer_phone,  
        j.budget as price,
        j.created_at
 FROM jobs j
 JOIN users l ON j.laborer_id = l.id
 WHERE j.customer_id = ? 
 AND j.status IN ('assigned', 'in_progress', 'completed')
 AND NOT EXISTS (
     SELECT 1 FROM payments p 
     WHERE p.job_id = j.id 
     AND p.status = 'completed'
 )
 ORDER BY j.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get payment history with detailed information
$stmt = $conn->prepare("
    SELECT p.*, j.title as job_title, j.description,
           CONCAT(u.first_name, ' ', u.last_name) AS laborer_name, NULL as profile_pic, /* Use NULL instead of u.profile_pic */
           u.phone as laborer_phone, u.email as laborer_email
    FROM payments p 
    JOIN jobs j ON p.job_id = j.id 
    JOIN users u ON j.laborer_id = u.id 
    WHERE j.customer_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$payment_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get payment statistics
$total_spent = 0;
$total_payments = 0;
foreach ($payment_history as $payment) {
    if ($payment['status'] === 'completed') {
        $total_spent += $payment['amount'];
        $total_payments++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }

        .payment-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .payment-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .amount {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
        }

        .pay-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .pay-btn:hover {
            background: #218838;
        }

        .payment-details {
            color: #666;
            font-size: 0.9em;
        }

        .completed {
            color: #28a745;
            font-weight: bold;
        }

        .pending {
            color: #ffc107;
            font-weight: bold;
        }

        .payment-statistics {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            flex: 1;
            margin: 0 10px;
        }

        .stat-card h3 {
            margin-bottom: 10px;
            font-size: 1.2em;
            color: #333;
        }

        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="container">
        <div class="payment-statistics">
            <div class="stat-card">
                <h3>Total Paid</h3>
                <div class="stat-value">₹<?php echo number_format($payment_stats['total_paid'], 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Payments</h3>
                <div class="stat-value">₹<?php echo number_format($payment_stats['total_pending'], 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Transactions</h3>
                <div class="stat-value"><?php echo $payment_stats['total_transactions']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total JOB</h3>
                <div class="stat-value"><?php echo $payment_stats['total_jobs']; ?></div>
            </div>
        </div>

        <div class="payment-section">
            <h2>Pending Payments</h2>
            <?php if (empty($pending_payments)): ?>
                <p>No pending payments</p>
            <?php else: ?>
                <?php foreach ($pending_payments as $payment): ?>
                    <div class="payment-card">
                        <div class="payment-header">
                            <h3><?php echo htmlspecialchars($payment['title']); ?></h3>
                            <span class="amount">₹<?php echo number_format($payment['price'], 2); ?></span>
                        </div>
                        <div class="payment-details">
                            <p><strong>Laborer:</strong> <?php echo htmlspecialchars($payment['laborer_name']); ?></p>
                            <p><strong>Phone:</strong> <?php echo isset($payment['laborer_phone']) ? htmlspecialchars($payment['laborer_phone']) : 'Not available'; ?></p>
                            <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($payment['created_at'])); ?></p>
                        </div>
                        <button class="btn btn-primary pay-btn" data-job-id="<?php echo $payment['job_id']; ?>" data-amount="<?php echo isset($payment['price']) ? $payment['price'] : 0; ?>">
                            Pay Now (₹<?php echo number_format(isset($payment['price']) ? $payment['price'] : 0, 2); ?>)
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="payment-section">
            <h2>Payment History</h2>
            <?php if (empty($payment_history)): ?>
                <p>No payment history</p>
            <?php else: ?>
                <?php foreach ($payment_history as $payment): ?>
                    <div class="payment-card">
                        <div class="payment-header">
                            <h3><?php echo htmlspecialchars($payment['job_title']); ?></h3>
                            <span class="amount">₹<?php echo number_format($payment['amount'], 2); ?></span>
                        </div>
                        <div class="payment-details">
                            <p><strong>Laborer:</strong> <?php echo htmlspecialchars($payment['laborer_name']); ?></p>
                            <p><strong>Status:</strong> <span class="completed">Paid</span></p>
                            <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($payment['created_at'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all pay buttons
            const payButtons = document.querySelectorAll('.pay-btn');
            payButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const jobId = this.getAttribute('data-job-id');
                    const amount = this.getAttribute('data-amount');
                    
                    // Prompt for transaction ID
                    const transactionId = prompt('Please enter the transaction ID:', '');
                    
                    // Validate transaction ID
                    if (!transactionId || transactionId.trim() === '') {
                        alert('Transaction ID is required to process the payment.');
                        return;
                    }
                    
                    // Confirm the payment
                    if (confirm('Confirm payment of ₹' + amount + ' with Transaction ID: ' + transactionId + '?')) {
                        // Disable button and show processing state
                        this.disabled = true;
                        this.innerHTML = 'Processing...';
                        
                        fetch('c_payments.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `job_id=${jobId}&amount=${amount}&transaction_id=${encodeURIComponent(transactionId)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.message);
                            if (data.success) {
                                location.reload();
                            } else {
                                // Re-enable button if there was an error
                                this.disabled = false;
                                this.innerHTML = 'Pay Now (₹' + amount + ')';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while processing the payment');
                            // Re-enable button
                            this.disabled = false;
                            this.innerHTML = 'Pay Now (₹' + amount + ')';
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
