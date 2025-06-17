<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

$errors = [];
$success = false;

// Get all customers
$customers = [];
$stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, email FROM users WHERE role = 'customer'");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

// Get all laborers
$laborers = [];
$stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, email FROM users WHERE role = 'laborer'");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $laborers[] = $row;
}

// Get all services
$services = [];
$stmt = $conn->prepare("SELECT id, name FROM services");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $customer_id = (int)$_POST['customer_id'];
    $laborer_id = !empty($_POST['laborer_id']) ? (int)$_POST['laborer_id'] : null;
    $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $location = sanitize_input($_POST['location']);
    $budget = (float)$_POST['price'];
    $status = sanitize_input($_POST['status']);

    // Validate input
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($customer_id)) $errors[] = "Customer is required";
    if (empty($budget)) $errors[] = "Budget is required";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO jobs (title, description, customer_id, laborer_id, service_id, location, budget, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiissds", $title, $description, $customer_id, $laborer_id, $service_id, $location, $budget, $status);
        
        if ($stmt->execute()) {
            $success = true;
            header("Location: job_management.php?success=1");
            exit();
        } else {
            $errors[] = "Error creating job: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Job - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Add New Job</h2>
        </header>

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
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label for="customer_id">Customer:</label>
                    <select id="customer_id" name="customer_id" required>
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo $customer['full_name'] . ' (' . $customer['email'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="laborer_id">Laborer (Optional):</label>
                    <select id="laborer_id" name="laborer_id">
                        <option value="">Select Laborer</option>
                        <?php foreach ($laborers as $laborer): ?>
                            <option value="<?php echo $laborer['id']; ?>">
                                <?php echo $laborer['full_name'] . ' (' . $laborer['email'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service_id">Service Type (For Reference):</label>
                    <select id="service_id" name="service_id">
                        <option value="">Select Service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['id']; ?>">
                                <?php echo $service['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="location">Location:</label>
                    <input type="text" id="location" name="location" required>
                </div>

                <div class="form-group">
                    <label for="price">Budget ($):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="pending">Pending</option>
                        <option value="assigned">Assigned</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-submit">Add Job</button>
                    <a href="job_management.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <style>
        .admin-form {
            max-width: 800px;
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
        .form-group textarea {
            resize: vertical;
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
</body>
</html>
