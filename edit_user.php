<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

$errors = [];
$success = false;

// Get user ID from URL
if (isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
} else {
    header("Location: manage_users.php");
    exit();
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: manage_users.php");
    exit();
}

// Get user data
$user = $result->fetch_assoc();

// Check if user data has the necessary fields
if (!isset($user['first_name'])) {
    $user['first_name'] = '';
}
if (!isset($user['last_name'])) {
    $user['last_name'] = '';
}
if (!isset($user['email'])) {
    $user['email'] = '';
}
if (!isset($user['phone'])) {
    $user['phone'] = '';
}
if (!isset($user['role'])) {
    $user['role'] = 'customer';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Safely get form values with defaults if keys don't exist
    $first_name = isset($_POST['first_name']) ? sanitize_input($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_input($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
    $role = isset($_POST['role']) ? sanitize_input($_POST['role']) : 'customer';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validate input
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";

    // Check if email already exists (but not for the current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists";
    }

    if (empty($errors)) {
        // If password is provided, update it; otherwise, just update other fields
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $first_name, $last_name, $email, $phone, $role, $hashed_password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $role, $user_id);
        }
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "Error updating user: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-form {
            max-width: 600px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-submit,
        .btn-cancel {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-submit {
            background: #4CAF50;
            color: white;
        }
        .btn-cancel {
            background: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Edit User</h2>
        </header>

        <?php if ($success): ?>
            <div class="alert success">User updated successfully!</div>
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

        <div class="form-section">
            <form method="POST" class="admin-form">
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password (leave empty to keep current):</label>
                    <input type="password" id="password" name="password">
                </div>

                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="customer" <?php echo ($user['role'] == 'customer') ? 'selected' : ''; ?>>Customer</option>
                        <option value="laborer" <?php echo ($user['role'] == 'laborer') ? 'selected' : ''; ?>>Laborer</option>
                        <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-submit">Update User</button>
                    <a href="manage_users.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
