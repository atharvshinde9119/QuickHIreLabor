<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '';
$db_name = 'quickversion';

// Start the session only if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Common functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

function isLaborer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'laborer';
}

// Sanitize user input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Create notification
function createNotification($user_id, $title, $message, $type = 'general') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    return $stmt->execute();
}

// Get count of unread notifications for current user
function getUnreadNotificationsCount() {
    if (!isLoggedIn()) return 0;
    
    global $conn;
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'];
}

// Get count of unread messages for current user
function getUnreadMessagesCount() {
    if (!isLoggedIn()) return 0;
    
    global $conn;
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'];
}

// Sanitize email input
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Check if password meets requirements
function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

// Get service fee from platform settings
function getServiceFee() {
    global $conn;
    $fee = 10; // Default value
    
    $result = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'service_fee'");
    if ($result && $result->num_rows > 0) {
        $fee = (float)$result->fetch_assoc()['setting_value'];
    }
    
    return $fee;
}

// ... other helper functions as needed ...
?>
