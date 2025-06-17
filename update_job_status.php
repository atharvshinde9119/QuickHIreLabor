<?php
require_once 'config.php';

// Check if user is logged in and is a laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle job status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id']) && isset($_POST['status'])) {
    $job_id = (int)$_POST['job_id'];
    $new_status = sanitize_input($_POST['status']);
    
    // Verify this is a valid status
    $valid_statuses = ['in_progress', 'completed'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error'] = "Invalid status provided.";
        header("Location: laborer_my_jobs.php");
        exit();
    }
    
    // Verify the laborer has access to this job
    $stmt = $conn->prepare("SELECT status FROM jobs WHERE id = ? AND laborer_id = ?");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "You don't have permission to update this job.";
        header("Location: laborer_my_jobs.php");
        exit();
    }
    
    $current_job = $result->fetch_assoc();
    $current_status = $current_job['status'];
    
    // Check if the status transition is valid
    if (($current_status === 'assigned' && $new_status === 'in_progress') || 
        ($current_status === 'in_progress' && $new_status === 'completed')) {
        
        // Update job status
        $update_stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $job_id);
        
        if ($update_stmt->execute()) {
            // Add entry to job status history
            $history_stmt = $conn->prepare("
                INSERT INTO job_status_history (job_id, status, notes) 
                VALUES (?, ?, ?)
            ");
            
            $notes = "Status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . " by laborer.";
            $history_stmt->bind_param("iss", $job_id, $new_status, $notes);
            $history_stmt->execute();
            
            // Get job details to create notification
            $job_stmt = $conn->prepare("
                SELECT j.title, j.customer_id, CONCAT(u.first_name, ' ', u.last_name) as laborer_name
                FROM jobs j
                JOIN users u ON j.laborer_id = u.id
                WHERE j.id = ?
            ");
            $job_stmt->bind_param("i", $job_id);
            $job_stmt->execute();
            $job = $job_stmt->get_result()->fetch_assoc();
            
            if ($job) {
                // Create notification for customer
                $title = $new_status === 'in_progress' ? 
                    "Job Started" : "Job Completed";
                    
                $message = $new_status === 'in_progress' ? 
                    "Laborer {$job['laborer_name']} has started work on your job: {$job['title']}" :
                    "Laborer {$job['laborer_name']} has marked your job as completed: {$job['title']}";
                
                createNotification($job['customer_id'], $title, $message, 'job');
            }
            
            $_SESSION['success'] = "Job status updated successfully to " . ucfirst(str_replace('_', ' ', $new_status));
        } else {
            $_SESSION['error'] = "Error updating job status. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Invalid status transition. Current status: " . ucfirst(str_replace('_', ' ', $current_status));
    }
    
    // Redirect back to laborer_my_jobs.php, not l_dashboard.php
    header("Location: laborer_my_jobs.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: laborer_my_jobs.php");
    exit();
}
?>
