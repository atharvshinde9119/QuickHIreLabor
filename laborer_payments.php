<?php
require_once 'config.php';

// Check if user is logged in as laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get service fee percentage from settings
$service_fee_percentage = 10; // Default value
$fee_query = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'service_fee'");
if ($fee_query && $fee_result = $fee_query->fetch_assoc()) {
    $service_fee_percentage = (float)$fee_result['setting_value'];
}

// Get completed jobs with payment information
$stmt = $conn->prepare("
    SELECT j.id as job_id, j.title, j.budget, j.updated_at,
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           s.name as service_name,
           p.id as payment_id, p.amount, p.status as payment_status, p.created_at as payment_date
    FROM jobs j
    JOIN users c ON j.customer_id = c.id
    LEFT JOIN services s ON j.service_id = s.id
    LEFT JOIN payments p ON j.id = p.job_id
    WHERE j.laborer_id = ? AND j.status = 'completed' AND p.status = 'completed'
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate payments with service fee deduction
$total_earnings = 0;
$total_service_fees = 0;

foreach ($payments as &$payment) {
    // Calculate service fee
    $service_fee = $payment['amount'] * ($service_fee_percentage / 100);
    
    // Calculate net amount
    $net_amount = $payment['amount'] - $service_fee;
    
    // Store in payment record
    $payment['service_fee'] = $service_fee;
    $payment['net_amount'] = $net_amount;
    
    // Add to totals
    $total_earnings += $net_amount;
    $total_service_fees += $service_fee;
}

// Format currency
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .earnings-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .total-earnings, .service-fees {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            text-align: center;
            flex: 1;
        }
        
        .service-fees {
            background-color: #f8f9fa;
        }
        
        .total-amount {
            font-size: 32px;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        
        .fee-amount {
            font-size: 32px;
            font-weight: bold;
            color: #dc3545;
            margin: 10px 0;
        }
        
        .payment-table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            overflow-x: auto;
        }
        
        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payment-table th {
            background-color: #f8f9fa;
            color: #495057;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .payment-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .payment-table tr:last-child td {
            border-bottom: none;
        }
        
        .payment-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
        }
        
        .status-completed {
            background-color: #28a745;
        }
        
        .service-fee-note {
            color: #dc3545;
            font-size: 0.85em;
            font-style: italic;
        }
        
        .empty-state {
            background: white;
            padding: 40px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-top: 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .info-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            background: #17a2b8;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 16px;
            font-size: 10px;
            cursor: help;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Payment History</h1>
        </div>
        
        <div class="earnings-summary">
            <div class="total-earnings">
                <i class="fas fa-wallet fa-2x" style="color: #4CAF50;"></i>
                <div class="total-amount"><?php echo formatCurrency($total_earnings); ?></div>
                <div>Net Earnings</div>
            </div>
            
            <div class="service-fees">
                <i class="fas fa-percent fa-2x" style="color: #dc3545;"></i>
                <div class="fee-amount"><?php echo formatCurrency($total_service_fees); ?></div>
                <div>Service Fees (<?php echo $service_fee_percentage; ?>%)</div>
            </div>
        </div>
        
        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <i class="fas fa-money-check-alt"></i>
                <h3>No payments found</h3>
                <p>Completed job payments will appear here</p>
                <a href="l_find_jobs.php" class="btn btn-primary">Find Jobs</a>
            </div>
        <?php else: ?>
            <div class="payment-table-container">
                <h2>Payment History</h2>
                <p>A service fee of <?php echo $service_fee_percentage; ?>% is deducted from all payments.</p>
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Payment Date</th>
                            <th>Job Title</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Gross Amount</th>
                            <th>Service Fee</th>
                            <th>Net Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <a href="view_job_details.php?id=<?php echo $payment['job_id']; ?>">
                                        <?php echo htmlspecialchars($payment['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($payment['service_name'] ?? 'Not specified'); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td class="service-fee-note">-<?php echo formatCurrency($payment['service_fee']); ?></td>
                                <td><strong><?php echo formatCurrency($payment['net_amount']); ?></strong></td>
                                <td>
                                    <span class="payment-status status-completed">
                                        Completed
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
