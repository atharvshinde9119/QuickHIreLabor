<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $laborer_id = isset($_POST['laborer_id']) ? (int)$_POST['laborer_id'] : 0;
    $job_title = sanitize_input($_POST['job_title']);
    $job_description = sanitize_input($_POST['job_description']);
    $job_location = sanitize_input($_POST['job_location']);
    $job_budget = (float)$_POST['job_budget'];
    $service_id = (int)$_POST['service_id'];
    
    // Get platform settings for budget validation
    $min_budget = 500; // Default value
    $max_budget = 50000; // Default value
    
    // Try to get actual values from platform_settings table
    $settings_query = $conn->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ('min_job_price', 'max_job_price')");
    if ($settings_query) {
        while ($setting = $settings_query->fetch_assoc()) {
            if ($setting['setting_key'] === 'min_job_price') {
                $min_budget = (float)$setting['setting_value'];
            } elseif ($setting['setting_key'] === 'max_job_price') {
                $max_budget = (float)$setting['setting_value'];
            }
        }
    }
    
    // Validate inputs
    $errors = [];
    
    if (empty($job_title)) {
        $errors[] = "Job title is required";
    }
    
    if (empty($job_description)) {
        $errors[] = "Job description is required";
    }
    
    if (empty($job_location)) {
        $errors[] = "Job location is required";
    }
    
    if ($job_budget < $min_budget || $job_budget > $max_budget) {
        $errors[] = "Budget must be between ₹" . number_format($min_budget, 2) . " and ₹" . number_format($max_budget, 2);
    }
    
    if ($service_id <= 0) {
        $errors[] = "Please select a service category";
    }
    
    // If laborer_id is provided, verify the laborer exists
    if ($laborer_id > 0) {
        $laborer_check = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'laborer'");
        $laborer_check->bind_param("i", $laborer_id);
        $laborer_check->execute();
        if ($laborer_check->get_result()->num_rows === 0) {
            $errors[] = "Invalid laborer selected";
        }
    }
    
    // If no errors, insert the job
    if (empty($errors)) {
        // Status will be 'pending_approval'
        $stmt = $conn->prepare("
            INSERT INTO jobs (
                title, description, customer_id, laborer_id, service_id, 
                location, budget, status, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 'pending_approval', NOW(), NOW()
            )
        ");
        
        // If no specific laborer was selected, set laborer_id to NULL
        if ($laborer_id <= 0) {
            $laborer_id = null;
        }
        
        $stmt->bind_param("ssiissd", $job_title, $job_description, $user_id, $laborer_id, $service_id, $job_location, $job_budget);
        
        if ($stmt->execute()) {
            $job_id = $conn->insert_id;
            
            // Add entry to job status history
            $history_stmt = $conn->prepare("
                INSERT INTO job_status_history (job_id, status, notes)
                VALUES (?, 'pending_approval', 'Job submitted for approval')
            ");
            $history_stmt->bind_param("i", $job_id);
            $history_stmt->execute();
            
            // Create admin notification about new job
            $admin_query = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            if ($admin = $admin_query->fetch_assoc()) {
                $admin_id = $admin['id'];
                
                $notification_title = "New Job Request";
                $notification_message = "A new job '{$job_title}' has been submitted for approval";
                
                createNotification($admin_id, $notification_title, $notification_message, 'job');
            }
            
            // Set success message
            $_SESSION['success_msg'] = "Job request submitted successfully! It is now awaiting admin approval.";
            
            // Redirect to job requests page
            header("Location: c_job_requests.php");
            exit();
        } else {
            $errors[] = "Error submitting job: " . $conn->error;
        }
    }
    
    // If there were errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['error_msg'] = implode("<br>", $errors);
        header("Location: browse_laborers.php");
        exit();
    }
} else {
    // If accessed directly without POST data
    header("Location: browse_laborers.php");
    exit();
}
?>
