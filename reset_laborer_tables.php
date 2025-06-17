<?php
require_once 'config.php';

// Only allow admin or the actual laborer to reset their own tables
if (!isLoggedIn() || (!isAdmin() && !isLaborer())) {
    header("Location: login.php");
    exit();
}

// Drop existing tables
$conn->query("DROP TABLE IF EXISTS laborer_settings");
$conn->query("DROP TABLE IF EXISTS notification_preferences");

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

if (!$conn->query($laborer_settings_sql)) {
    die("Error creating laborer_settings table: " . $conn->error);
}

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

if (!$conn->query($notification_preferences_sql)) {
    die("Error creating notification_preferences table: " . $conn->error);
}

// Insert default settings for all laborers
$conn->query("INSERT INTO laborer_settings (user_id, is_available, max_distance, min_pay)
              SELECT id, 1, 50, 0.00 FROM users WHERE role = 'laborer'");

// Insert notification preferences for all laborers
$conn->query("INSERT INTO notification_preferences (user_id, email_enabled, sms_enabled)
              SELECT id, 1, 0 FROM users WHERE role = 'laborer'");

// Redirect back to settings page
header("Location: laborer_settings.php?reset=success");
exit();
?>
