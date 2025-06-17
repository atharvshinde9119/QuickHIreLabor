<?php
$dbError = false;
try {
    require_once 'config.php';

    if (!isLoggedIn() || !isLaborer()) {
        header("Location: login.php");
        exit();
    }

    // Function to create tables with error handling
    function createTableIfNotExists($conn, $tableName, $createSql) {
        // Check if table exists
        $tableExists = $conn->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0;
        
        if (!$tableExists) {
            // Create the table
            if (!$conn->query($createSql)) {
                error_log("Error creating $tableName table: " . $conn->error);
                die("Error creating required tables. Please contact administrator.");
            } else {
                error_log("Successfully created $tableName table.");
            }
        }
        return true;
    }

    // Create laborer_settings table
    $laborer_settings_sql = "CREATE TABLE IF NOT EXISTS `laborer_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `is_available` tinyint(1) DEFAULT 1,
        `max_distance` int(11) DEFAULT 50,
        `min_pay` decimal(10,2) DEFAULT 0.00,
        `notification_email` tinyint(1) DEFAULT 1,
        `notification_sms` tinyint(1) DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`)
    )";
    createTableIfNotExists($conn, 'laborer_settings', $laborer_settings_sql);

    // Create notification_preferences table
    $notification_preferences_sql = "CREATE TABLE IF NOT EXISTS `notification_preferences` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `email_enabled` tinyint(1) DEFAULT 1,
        `sms_enabled` tinyint(1) DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`)
    )";
    createTableIfNotExists($conn, 'notification_preferences', $notification_preferences_sql);

    $user_id = $_SESSION['user_id'];

    // Handle settings updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = ['success' => false, 'message' => ''];
        
        try {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'update_preferences':
                        $availability = isset($_POST['availability']) ? 1 : 0;
                        $max_distance = (int)$_POST['max_distance'];
                        $min_pay = (float)$_POST['min_pay'];
                        
                        $stmt = $conn->prepare("
                            UPDATE laborer_settings 
                            SET is_available = ?, max_distance = ?, min_pay = ?
                            WHERE user_id = ?
                        ");
                        $stmt->bind_param("iidi", $availability, $max_distance, $min_pay, $user_id);
                        break;

                    case 'update_notifications':
                        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
                        
                        $stmt = $conn->prepare("
                            UPDATE notification_preferences 
                            SET email_enabled = ?, sms_enabled = ?
                            WHERE user_id = ?
                        ");
                        $stmt->bind_param("iii", $email_notifications, $sms_notifications, $user_id);
                        break;
                }

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Settings updated successfully';
                }
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            echo json_encode($response);
            exit();
        }
    }

    // Create default settings if they don't exist
    $check_stmt = $conn->prepare("SELECT id FROM laborer_settings WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        $stmt = $conn->prepare("
            INSERT INTO laborer_settings 
            (user_id, is_available, max_distance, min_pay) 
            VALUES (?, 1, 50, 0.00)
        ");
        if (!$stmt) {
            error_log("Prepare error: " . $conn->error);
            die("Database error. Please contact administrator.");
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Execute error: " . $stmt->error);
            die("Database error. Please contact administrator.");
        }
    }

    // Check if the user has notification preferences
    $check_stmt = $conn->prepare("SELECT id FROM notification_preferences WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        $stmt = $conn->prepare("
            INSERT INTO notification_preferences 
            (user_id, email_enabled, sms_enabled) 
            VALUES (?, 1, 0)
        ");
        if (!$stmt) {
            error_log("Prepare error: " . $conn->error);
            die("Database error. Please contact administrator.");
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Execute error: " . $stmt->error);
            die("Database error. Please contact administrator.");
        }
    }

    // Get current settings
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(ls.is_available, 1) as is_available,
            COALESCE(ls.max_distance, 50) as max_distance,
            COALESCE(ls.min_pay, 0.00) as min_pay,
            COALESCE(np.email_enabled, 1) as email_enabled,
            COALESCE(np.sms_enabled, 0) as sms_enabled
        FROM users u
        LEFT JOIN laborer_settings ls ON u.id = ls.user_id
        LEFT JOIN notification_preferences np ON u.id = np.user_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();

} catch (Exception $e) {
    $dbError = true;
    $errorMessage = $e->getMessage();
}

// If there's a database error, show an error page with a reset option
if ($dbError) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Error - QuickHire Labor</title>
        <link rel="stylesheet" href="css/style.css">
        <style>
            .error-container {
                max-width: 600px;
                margin: 100px auto;
                padding: 30px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
            }
            .error-title {
                color: #e74c3c;
                font-size: 24px;
                margin-bottom: 20px;
            }
            .error-message {
                color: #555;
                margin-bottom: 30px;
            }
            .reset-button {
                display: inline-block;
                background: #3498db;
                color: white;
                padding: 10px 20px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: bold;
            }
            .reset-button:hover {
                background: #2980b9;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1 class="error-title">Database Error</h1>
            <p class="error-message">There was a problem with the database tables required for laborer settings.</p>
            <p>Error details: <?php echo htmlspecialchars($errorMessage); ?></p>
            <a href="reset_laborer_tables.php" class="reset-button">Reset Laborer Tables</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin-left: 280px;
            padding: 20px;
        }

        .settings-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .settings-section {
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
        }

        .form-group input[type="number"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #4CAF50;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .save-btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .save-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <div class="container">
        <h1>Settings</h1>

        <div class="settings-card">
            <h2>Work Preferences</h2>
            <form id="preferencesForm">
                <input type="hidden" name="action" value="update_preferences">
                
                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="availability" 
                               <?php echo $settings['is_available'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <label>Available for Work</label>
                </div>

                <div class="form-group">
                    <label for="max_distance">Maximum Travel Distance (km)</label>
                    <input type="number" id="max_distance" name="max_distance" 
                           value="<?php echo $settings['max_distance']; ?>" min="1" max="100">
                </div>

                <div class="form-group">
                    <label for="min_pay">Minimum Pay Rate (₹/hour)</label>
                    <input type="number" id="min_pay" name="min_pay" 
                           value="<?php echo $settings['min_pay']; ?>" min="0" step="0.01">
                </div>

                <button type="submit" class="save-btn">Save Preferences</button>
            </form>
        </div>

        <!-- <div class="settings-card">
            <h2>Notification Settings</h2>
            <form id="notificationsForm">
                <input type="hidden" name="action" value="update_notifications">
                
                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="email_notifications" 
                               <?php echo $settings['email_enabled'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <label>Email Notifications</label>
                </div>

                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="sms_notifications" 
                               <?php echo $settings['sms_enabled'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <label>SMS Notifications</label>
                </div>

                <button type="submit" class="save-btn">Save Notification Settings</button>
            </form>
        </div> -->
    </div>

    <script>
        ['preferencesForm', 'notificationsForm'].forEach(formId => {
            document.getElementById(formId).addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('laborer_settings.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving settings');
                });
            });
        });
    </script>
</body>
</html>
