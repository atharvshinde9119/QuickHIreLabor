<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Create settings table if it doesn't exist
$create_settings_table = "
CREATE TABLE IF NOT EXISTS `platform_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` VARCHAR(255) NOT NULL,
    `setting_description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($create_settings_table);

// Default settings if they don't exist
$default_settings = [
    ['service_fee', '10', 'Percentage fee charged on each job payment'],
    ['min_job_price', '500', 'Minimum price allowed for a job'],
    ['max_job_price', '50000', 'Maximum price allowed for a job']
];

// Check and insert default settings
foreach ($default_settings as $setting) {
    $key = $setting[0];
    $value = $setting[1];
    $description = $setting[2];
    
    $check = $conn->prepare("SELECT id FROM platform_settings WHERE setting_key = ?");
    $check->bind_param("s", $key);
    $check->execute();
    
    if ($check->get_result()->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $key, $value, $description);
        $insert->execute();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get submitted values with validation
    $service_fee = max(0, min(100, (float)$_POST['service_fee'])); // Between 0-100%
    $min_job_price = max(0, (float)$_POST['min_job_price']);
    $max_job_price = max($min_job_price, (float)$_POST['max_job_price']);
    
    // Update service fee
    $stmt = $conn->prepare("UPDATE platform_settings SET setting_value = ? WHERE setting_key = 'service_fee'");
    $stmt->bind_param("s", $service_fee);
    $stmt->execute();
    
    // Update min job price
    $stmt = $conn->prepare("UPDATE platform_settings SET setting_value = ? WHERE setting_key = 'min_job_price'");
    $stmt->bind_param("s", $min_job_price);
    $stmt->execute();
    
    // Update max job price
    $stmt = $conn->prepare("UPDATE platform_settings SET setting_value = ? WHERE setting_key = 'max_job_price'");
    $stmt->bind_param("s", $max_job_price);
    $stmt->execute();
    
    // Set success message
    $_SESSION['success_message'] = "Platform settings updated successfully.";
    
    // Redirect to avoid form resubmission
    header("Location: settings.php");
    exit();
}

// Get current settings
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM platform_settings");
$stmt->execute();
$result = $stmt->get_result();

$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .content {
            margin-left: 280px;
            padding: 20px;
        }
        
        .settings-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            max-width: 300px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-group .help-text {
            margin-top: 5px;
            color: #666;
            font-size: 0.85em;
        }
        
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .submit-btn:hover {
            background-color: #45a049;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <h1>Platform Settings</h1>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-card">
            <h2>Financial Settings</h2>
            <form method="post" action="">
                <div class="form-group">
                    <label for="service_fee">Service Fee (%)</label>
                    <input type="number" id="service_fee" name="service_fee" value="<?php echo $settings['service_fee'] ?? 10; ?>" min="0" max="100" step="0.1" required>
                    <div class="help-text">Percentage fee charged to laborers on each job payment</div>
                </div>
                
                <div class="form-group">
                    <label for="min_job_price">Minimum Job Price (₹)</label>
                    <input type="number" id="min_job_price" name="min_job_price" value="<?php echo $settings['min_job_price'] ?? 500; ?>" min="0" step="1" required>
                    <div class="help-text">Minimum amount that can be set for any job</div>
                </div>
                
                <div class="form-group">
                    <label for="max_job_price">Maximum Job Price (₹)</label>
                    <input type="number" id="max_job_price" name="max_job_price" value="<?php echo $settings['max_job_price'] ?? 50000; ?>" min="0" step="1" required>
                    <div class="help-text">Maximum amount that can be set for any job</div>
                </div>
                
                <button type="submit" class="submit-btn">Save Settings</button>
            </form>
        </div>
    </div>
    
    <script>
        // Client-side validation to ensure max price is always greater than min price
        document.getElementById('min_job_price').addEventListener('change', function() {
            const minPrice = parseFloat(this.value);
            const maxPriceField = document.getElementById('max_job_price');
            const maxPrice = parseFloat(maxPriceField.value);
            
            if (maxPrice < minPrice) {
                maxPriceField.value = minPrice;
            }
        });
    </script>
</body>
</html>
