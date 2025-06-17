<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

$errors = [];
$success = false;
$job = null;

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get job details
$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: job_management.php");
    exit();
}

$job = $result->fetch_assoc();

// Ensure 'budget' key exists in the array
if (isset($job) && is_array($job)) {
    if (!isset($job['budget'])) {
        $job['budget'] = ''; // Set default empty value
    }
}

// Ensure all expected keys exist in the job array
$defaults = [
    'budget' => '',
    'title' => '',
    'description' => '',
    'location' => '',
    'service_id' => '',
    'status' => 'open'
];
$job = array_merge($defaults, $job);

// Get all customers and laborers
$customers = [];
$stmt = $conn->prepare("SELECT id, first_name, email FROM users WHERE role = 'customer'");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

$laborers = [];
$stmt = $conn->prepare("SELECT id, first_name, email FROM users WHERE role = 'laborer'");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $laborers[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $customer_id = (int)$_POST['customer_id'];
    $laborer_id = !empty($_POST['laborer_id']) ? (int)$_POST['laborer_id'] : null;
    // Fix the undefined budget key - use 'price' instead which is the field name in the form
    $price = !empty($_POST['price']) ? (float)$_POST['price'] : 0;
    $status = sanitize_input($_POST['status']);

    // Validate input
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($customer_id)) $errors[] = "Customer is required";
    if (empty($price)) $errors[] = "Price is required";

    if (empty($errors)) {
        // Fix the column name - use 'budget' instead of 'price' to match the database column
        $stmt = $conn->prepare("UPDATE jobs SET title = ?, description = ?, customer_id = ?, laborer_id = ?, status = ?, budget = ? WHERE id = ?");
        $stmt->bind_param("ssiisdi", $title, $description, $customer_id, $laborer_id, $status, $price, $job_id);
        
        if ($stmt->execute()) {
            $success = true;
            // Refresh job data
            $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $job = $stmt->get_result()->fetch_assoc();
            $job = array_merge($defaults, $job);
        } else {
            $errors[] = "Error updating job: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Edit Job #<?php echo $job_id; ?></h2>
        </header>

        <?php if ($success): ?>
            <div class="alert success">Job updated successfully!</div>
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
                    <label for="title">Job Title:</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="customer_id">Customer:</label>
                    <select id="customer_id" name="customer_id" required>
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo $customer['id'] == $job['customer_id'] ? 'selected' : ''; ?>>
                                <?php echo $customer['first_name'] . ' (' . $customer['email'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="laborer_id">Laborer:</label>
                    <select id="laborer_id" name="laborer_id">
                        <option value="">Select Laborer</option>
                        <?php foreach ($laborers as $laborer): ?>
                            <option value="<?php echo $laborer['id']; ?>" <?php echo $laborer['id'] == $job['laborer_id'] ? 'selected' : ''; ?>>
                                <?php echo $laborer['first_name'] . ' (' . $laborer['email'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="price">Price ($):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo isset($job['budget']) ? $job['budget'] : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <?php 
                        $statuses = ['pending', 'assigned', 'completed', 'cancelled'];
                        foreach ($statuses as $status): 
                        ?>
                            <option value="<?php echo $status; ?>" <?php echo $job['status'] == $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Update Job</button>
                    <a href="job_management.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <style>
        .form-section {
            max-width: 800px;
            margin: 20px;
        }
        .admin-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-actions {
            margin-top: 20px;
        }
        .btn-submit, .btn-cancel {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-submit { background: #4CAF50; color: white; }
        .btn-cancel { background: #f44336; color: white; }
    </style>
</body>
</html>
