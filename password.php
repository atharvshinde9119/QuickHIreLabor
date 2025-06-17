<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

// Get current user information
$stmt = $conn->prepare("SELECT first_name, last_name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New password and confirm password do not match";
    }
    
    if (empty($errors)) {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user_data = $result->fetch_assoc();
            if (password_verify($current_password, $user_data['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $success = true;
                    
                    // Log the password change
                    $tableExists = $conn->query("SHOW TABLES LIKE 'activity_log'")->num_rows > 0;
                    if ($tableExists) {
                        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'password_change', 'User changed their password')");
                        $log_stmt->bind_param("i", $user_id);
                        $log_stmt->execute();
                    }
                    
                    // Send email notification
                    $to = $user['email'];
                    $subject = "Password Changed - QuickHire Labor";
                    $message = "Hello " . $user['first_name'] . ",\n\n";
                    $message .= "Your password was recently changed on your QuickHire Labor account.\n\n";
                    $message .= "If you did not make this change, please contact support immediately.\n\n";
                    $message .= "Regards,\nQuickHire Labor Team";
                    $headers = "From: noreply@quickhirelabor.com";
                    
                    // Comment out actual mail sending in development
                    // mail($to, $subject, $message, $headers);
                } else {
                    $errors[] = "Error updating password: " . $update_stmt->error;
                }
            } else {
                $errors[] = "Current password is incorrect";
            }
        } else {
            $errors[] = "User account not found";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php 
    // Include appropriate sidebar based on user role
    if (isAdmin()) {
        include 'includes/admin_sidebar.php';
    } elseif (isLaborer()) {
        include 'includes/laborer_sidebar.php';
    } else {
        include 'includes/customer_sidebar.php';
    }
    ?>

    <div class="content">
        <header>
            <h2>Change Password</h2>
        </header>

        <?php if ($success): ?>
            <div class="alert success">
                <p>Password changed successfully!</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="password.php" class="password-form">
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small>Password must be at least 6 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li>At least 6 characters long</li>
                        <li>Should not be the same as your current password</li>
                        <li>Avoid using easily guessable information like birthdays</li>
                    </ul>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                    <a href="profile.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .password-form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group small {
            color: #666;
            display: block;
            margin-top: 5px;
        }
        .password-requirements {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .password-requirements h4 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #0355cc;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>

    <script>
        // Client-side validation for password confirmation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirm password do not match');
            }
        });
    </script>
</body>
</html>
