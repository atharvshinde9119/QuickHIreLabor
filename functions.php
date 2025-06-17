<?php
require_once 'config.php';

// Job management functions
function createJob($title, $description, $customer_id, $service_id, $price) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO jobs (title, description, customer_id, service_id, price) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiid", $title, $description, $customer_id, $service_id, $price);
    return $stmt->execute();
}

function updateJobStatus($job_id, $status) {
    global $conn;
    $stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $job_id);
    return $stmt->execute();
}

function assignLaborer($job_id, $laborer_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE jobs SET laborer_id = ?, status = 'assigned' WHERE id = ?");
    $stmt->bind_param("ii", $laborer_id, $job_id);
    return $stmt->execute();
}

// Payment functions
function createPayment($job_id, $amount) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO payments (job_id, amount) VALUES (?, ?)");
    $stmt->bind_param("id", $job_id, $amount);
    return $stmt->execute();
}

function updatePaymentStatus($payment_id, $status) {
    global $conn;
    $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $payment_id);
    return $stmt->execute();
}

// Rating functions
function addRating($job_id, $customer_id, $laborer_id, $rating, $feedback) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO ratings (job_id, customer_id, laborer_id, rating, feedback) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiis", $job_id, $customer_id, $laborer_id, $rating, $feedback);
    return $stmt->execute();
}

// Service functions
function getAvailableServices() {
    global $conn;
    $result = $conn->query("SELECT * FROM services ORDER BY name");
    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    return $services;
}

// User functions
function getUserById($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, name, email, role, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function updateUserProfile($user_id, $name, $phone) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $phone, $user_id);
    return $stmt->execute();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function handle_file_upload($file, $upload_dir = 'uploads') {
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_extension, $allowed)) {
        throw new Exception('Invalid file type');
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . '/' . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $upload_path;
    }
    
    throw new Exception('Failed to upload file');
}

function get_user_notifications($user_id, $limit = 5) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
