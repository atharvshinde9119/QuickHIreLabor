<?php
require_once 'config.php';

// Check if user is logged in as customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

// Enable error logging for debugging
error_log("Job request submission started");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $laborer_id = isset($_POST['laborer_id']) ? (int)$_POST['laborer_id'] : 0;
    $customer_id = $_SESSION['user_id'];
    $title = isset($_POST['job_title']) ? sanitize_input($_POST['job_title']) : '';
    $description = isset($_POST['job_description']) ? sanitize_input($_POST['job_description']) : '';
    $location = isset($_POST['job_location']) ? sanitize_input($_POST['job_location']) : '';
    $budget = isset($_POST['job_price']) ? floatval($_POST['job_price']) : 0;
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    
    error_log("Received job request: Title=$title, Laborer=$laborer_id, Customer=$customer_id");
    
    // Validate inputs
    if (empty($title) || empty($description) || empty($location) || $budget <= 0 || $laborer_id <= 0) {
        error_log("Validation failed: Missing required fields");
        $_SESSION['error'] = "All fields are required and budget must be greater than zero.";
        header("Location: browse_laborers.php");
        exit();
    }
    
    // Make sure we have the right column names
    $columns_check = $conn->query("DESCRIBE jobs");
    $has_price = false;
    $has_budget = false;
    
    while ($col = $columns_check->fetch_assoc()) {
        if ($col['Field'] == 'price') $has_price = true;
        if ($col['Field'] == 'budget') $has_budget = true;
    }
    
    // Choose the correct SQL statement based on available columns
    if ($has_budget) {
        // Create job request with explicit pending_approval status
        $stmt = $conn->prepare("
            INSERT INTO jobs (
                title, description, customer_id, laborer_id, service_id, 
                location, budget, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval', NOW())
        ");
        $stmt->bind_param("ssiissd", $title, $description, $customer_id, $laborer_id, $service_id, $location, $budget);
    } else if ($has_price) {
        // Create job request with explicit pending_approval status
        $stmt = $conn->prepare("
            INSERT INTO jobs (
                title, description, customer_id, laborer_id, service_id, 
                location, price, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval', NOW())
        ");
        $stmt->bind_param("ssiissd", $title, $description, $customer_id, $laborer_id, $service_id, $location, $budget);
    } else {
        // If neither column exists, add the budget column and try again
        $conn->query("ALTER TABLE jobs ADD COLUMN budget DECIMAL(10,2) DEFAULT 0 AFTER location");
        
        // Create job request with explicit pending_approval status
        $stmt = $conn->prepare("
            INSERT INTO jobs (
                title, description, customer_id, laborer_id, service_id, 
                location, budget, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval', NOW())
        ");
        $stmt->bind_param("ssiissd", $title, $description, $customer_id, $laborer_id, $service_id, $location, $budget);
    }
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Error preparing statement: " . $conn->error;
        header("Location: browse_laborers.php");
        exit();
    }
    
    if ($stmt->execute()) {
        $job_id = $conn->insert_id;
        error_log("Job request created successfully with ID: $job_id");
        
        // Notify admin about new job request
        $admin_result = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        if ($admin_result && $admin = $admin_result->fetch_assoc()) {
            $admin_id = $admin['id'];
            
            // Create notification for admin
            if (function_exists('createNotification')) {
                createNotification(
                    $admin_id,
                    'New Job Request',
                    "New job request '{$title}' requires your approval.",
                    'job_request'
                );
                error_log("Admin notification created");
            } else {
                error_log("createNotification function not found");
                
                // Fallback notification creation
                $conn->query("INSERT INTO notifications (user_id, title, message, type) 
                             VALUES ($admin_id, 'New Job Request', 'New job request requires approval', 'job_request')");
            }
        } else {
            error_log("Admin not found");
        }
        
        $_SESSION['success'] = "Job request submitted successfully! It is pending admin approval.";
        header("Location: c_job_requests.php");
    } else {
        error_log("Execute failed: " . $stmt->error);
        $_SESSION['error'] = "Error submitting job request: " . $stmt->error;
        header("Location: browse_laborers.php");
    }
    
    exit();
} else {
    error_log("Not a POST request");
    // Redirect if accessed directly without POST
    header("Location: browse_laborers.php");
    exit();
}
?>
