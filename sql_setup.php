<?php
require_once 'config.php';

// Database connection parameters from config.php are already loaded
// $conn is already established from config.php

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
$conn->query($sql);

// Select the database
$conn->select_db($db_name);

// Array of SQL statements to create all necessary tables
$tables = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50),
        address VARCHAR(255),
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(15) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'customer', 'laborer') DEFAULT 'customer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Newsletter Subscriptions Table
    "CREATE TABLE IF NOT EXISTS newsletters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        status ENUM('subscribed', 'unsubscribed') DEFAULT 'subscribed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    // Support Tickets Table
    "CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        status ENUM('open', 'in_progress', 'closed') DEFAULT 'open',
        admin_response TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    
    // Services Table
    "CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Jobs Table
    "CREATE TABLE IF NOT EXISTS jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        customer_id INT NOT NULL,
        laborer_id INT,
        service_id INT,
        location VARCHAR(255) NOT NULL,
        budget DECIMAL(10,2),
        status ENUM('pending_approval', 'admin_approval', 'open', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending_approval',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES users(id),
        FOREIGN KEY (service_id) REFERENCES services(id),
        FOREIGN KEY (laborer_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    
    // Applications Table
    "CREATE TABLE IF NOT EXISTS applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,

        laborer_id INT NOT NULL,
        cover_letter TEXT,
        price_quote DECIMAL(10,2),
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES jobs(id),
        FOREIGN KEY (laborer_id) REFERENCES users(id)
    )",
    
    // Job Applications Table
    "CREATE TABLE IF NOT EXISTS job_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        laborer_id INT NOT NULL,
        cover_letter TEXT,
        price_quote DECIMAL(10,2),
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
        FOREIGN KEY (laborer_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY `unique_application` (`job_id`, `laborer_id`)
    )",
    
    // Notifications Table
    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('general', 'job', 'application', 'payment', 'admin') DEFAULT 'general',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    
    // Reviews Table
    "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        reviewer_id INT NOT NULL,
        reviewed_id INT NOT NULL,
        rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES jobs(id),
        FOREIGN KEY (reviewer_id) REFERENCES users(id),
        FOREIGN KEY (reviewed_id) REFERENCES users(id)
    )",
    
    // Payments Table
    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        transaction_id VARCHAR(100),
        status ENUM('pending', 'completed', 'refunded') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES jobs(id)
    )",
    
    // Contacts Table - updated to include form_type
    "CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_type ENUM('index', 'contact') DEFAULT 'contact' NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Laborer Settings Table
    "CREATE TABLE IF NOT EXISTS `laborer_settings` (
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
    )",
    
    // Notification Preferences Table
    "CREATE TABLE IF NOT EXISTS `notification_preferences` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `email_enabled` tinyint(1) DEFAULT 1,
        `sms_enabled` tinyint(1) DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`)
    )",
    
    // Ratings Table
    "CREATE TABLE IF NOT EXISTS ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        rater_id INT NOT NULL,
        ratee_id INT NOT NULL,
        rating INT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES jobs(id),
        FOREIGN KEY (rater_id) REFERENCES users(id),
        FOREIGN KEY (ratee_id) REFERENCES users(id)
    )",
    
    // Skills Table
    "CREATE TABLE IF NOT EXISTS skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Laborer Skills Table
    "CREATE TABLE IF NOT EXISTS laborer_skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        laborer_id INT NOT NULL,
        skill_id INT NOT NULL,
        years_experience INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (laborer_id, skill_id),
        FOREIGN KEY (laborer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
    )",
    
    // Job Status History Table
    "CREATE TABLE IF NOT EXISTS job_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        status ENUM('pending_approval', 'admin_approval', 'open', 'assigned', 'in_progress', 'completed', 'cancelled') NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
    )"
];

// Remove contact_entries table from the list since we're using contacts instead
$tables = array_filter($tables, function($sql) {
    return strpos($sql, 'contact_entries') === false;
});

// Execute each SQL statement
$errors = [];
$success = true;

echo "<h2>Database Setup Progress</h2>";
echo "<ul>";

foreach ($tables as $sql) {
    // Extract table name for reporting
    preg_match('/CREATE TABLE IF NOT EXISTS ([^\s(]+)/', $sql, $matches);
    $tableName = isset($matches[1]) ? $matches[1] : "unknown";
    
    if ($conn->query($sql) === TRUE) {
        echo "<li>✅ Table '{$tableName}' created or already exists</li>";
    } else {
        echo "<li>❌ Error creating table '{$tableName}': " . $conn->error . "</li>";
        $errors[] = "Error creating table '{$tableName}': " . $conn->error;
        $success = false;
    }
}

echo "</ul>";

// After successful table creation, add a notice about the job status field
echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeeba;'>";
echo "<h3>⚠️ Important: Job Status Update</h3>";
echo "<p>The jobs table has been updated with additional status options: 'pending_approval' and 'admin_approval'.</p>";
echo "<p>If you're upgrading from a previous version, you may need to update existing job entries or modify your code to handle these new status values.</p>";
echo "<p>New job requests now follow this approval workflow:</p>";
echo "<ol>";
echo "<li><strong>pending_approval</strong> - Initial status when a job is created by a customer</li>";
echo "<li><strong>admin_approval</strong> - Optional intermediate review stage</li>";
echo "<li><strong>open</strong> - Job approved by admin and visible to laborers</li>";
echo "</ol>";
echo "</div>";

// For existing jobs table, we might need to update the enum values
try {
    // Check if jobs table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'jobs'")->num_rows > 0;
    
    if ($tableExists) {
        // Get current enum values for status
        $result = $conn->query("SHOW COLUMNS FROM jobs LIKE 'status'");
        $statusColumn = $result->fetch_assoc();
        
        if ($statusColumn) {
            $type = $statusColumn['Type'];
            
            // Check if it already has the new statuses
            if (strpos($type, 'pending_approval') === false || strpos($type, 'admin_approval') === false) {
                // Update the enum to include the new values
                $newEnum = "ENUM('pending_approval', 'admin_approval', 'open', 'assigned', 'in_progress', 'completed', 'cancelled')";
                $sql = "ALTER TABLE jobs MODIFY COLUMN status $newEnum DEFAULT 'pending_approval'";
                
                if ($conn->query($sql) === TRUE) {
                    echo "<div class='alert alert-success'>";
                    echo "Successfully updated jobs table status field to include new approval statuses.";
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-danger'>";
                    echo "Error updating jobs table: " . $conn->error;
                    echo "</div>";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "Error checking/updating jobs table: " . $e->getMessage();
    echo "</div>";
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// Function to check if table has data
function tableHasData($conn, $tableName) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $tableName");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['count'] > 0;
    }
    return false;
}

// Function to insert demo data and report results
function insertDemoData($conn, $tableName, $demoData, $columns) {
    $results = [
        'success' => 0,
        'error' => 0,
        'errors' => []
    ];
    
    // Check if table exists and is empty
    if (tableHasData($conn, $tableName)) {
        echo "<li>ℹ️ Table '$tableName' already has data - skipping demo data insertion</li>";
        return $results;
    }
    
    // Prepare columns and placeholders for the query
    $columnNames = implode("`, `", array_keys($columns));
    $placeholders = implode(", ", array_fill(0, count($columns), "?"));
    $types = "";
    
    // Build the type string for bind_param
    foreach ($columns as $type) {
        $types .= $type;
    }
    
    // Prepare the statement
    $stmt = $conn->prepare("INSERT INTO `$tableName` (`$columnNames`) VALUES ($placeholders)");
    
    if (!$stmt) {
        echo "<li>❌ Error preparing statement for '$tableName': " . $conn->error . "</li>";
        $results['errors'][] = "Preparation error: " . $conn->error;
        return $results;
    }
    
    // Insert each row of demo data
    foreach ($demoData as $dataRow) {
        $values = [];
        foreach (array_keys($columns) as $column) {
            $values[] = &$dataRow[$column];
        }
        
        // Bind parameters and execute
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $results['success']++;
            echo "<li>✅ Added demo data row to '$tableName'</li>";
        } else {
            $results['error']++;
            $results['errors'][] = $stmt->error;
            echo "<li>❌ Error adding demo data to '$tableName': " . $stmt->error . "</li>";
        }
    }
    
    $stmt->close();
    return $results;
}

// Insert default data
if ($success) {
    echo "<h2>Inserting Default Data</h2>";
    echo "<ul>";
    
    // Check the structure of the users table before inserting
    $checkTableStructure = $conn->query("DESCRIBE users");
    $columns = [];
    if ($checkTableStructure) {
        while ($row = $checkTableStructure->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        echo "<li>ℹ️ Found users table with columns: " . implode(", ", $columns) . "</li>";
        
        // Verify if the required name columns exist
        if (!in_array('first_name', $columns)) {
            echo "<li>⚠️ Warning: 'first_name' column not found in users table</li>";
        }
        if (!in_array('last_name', $columns)) {
            echo "<li>⚠️ Warning: 'last_name' column not found in users table</li>";
        }
    }

    // Default admin user - adjusted to handle different column names
    $adminExists = $conn->query("SELECT id FROM users WHERE email = 'admin@quickhirelabor.com' LIMIT 1");
    
    if ($adminExists && $adminExists->num_rows == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        
        // Always use first_name and last_name for consistency
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role) 
                VALUES ('Admin', 'User', 'admin@quickhirelabor.com', '1234567890', '$admin_password', 'admin')";
        
        try {
            if ($conn->query($sql) === TRUE) {
                echo "<li>✅ Default admin user created</li>";
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            echo "<li>❌ Error creating admin user: " . $e->getMessage() . "</li>";
            echo "<li>Debug: SQL used was: " . $sql . "</li>";
            $errors[] = "Error creating admin user: " . $e->getMessage();
        }
    } else {
        echo "<li>ℹ️ Admin user already exists or couldn't check</li>";
    }
    
    // Add a support admin user
    $supportAdminExists = $conn->query("SELECT id FROM users WHERE email = 'support@quickhirelabor.com' LIMIT 1");
    
    if ($supportAdminExists && $supportAdminExists->num_rows == 0) {
        $support_password = password_hash('support123', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role) 
                VALUES ('Support', 'Team', 'support@quickhirelabor.com', '9876543210', '$support_password', 'admin')";
        
        try {
            if ($conn->query($sql) === TRUE) {
                echo "<li>✅ Support admin user created (Email: support@quickhirelabor.com, Password: support123)</li>";
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            echo "<li>❌ Error creating support admin user: " . $e->getMessage() . "</li>";
            $errors[] = "Error creating support admin user: " . $e->getMessage();
        }
    } else {
        echo "<li>ℹ️ Support admin user already exists or couldn't check</li>";
    }
    
    // Default services
    $servicesCount = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
    
    if ($servicesCount == 0) {
        // Check if the services table has image and price_range columns
        $hasImageAndPriceRange = false;
        $tableInfo = $conn->query("DESCRIBE services");
        $columns = [];
        if ($tableInfo) {
            while ($row = $tableInfo->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            $hasImageAndPriceRange = in_array('image', $columns) && in_array('price_range', $columns);
        }
        
        $services = [
            ['Painters', 'Professional painting services for interior and exterior walls'],
            ['Electrical', 'Expert electrical repair and installation services'],
            ['Plumbing', 'Reliable plumbing services for all your needs'],
            ['Carpentry', 'Custom carpentry work and furniture making'],
            ['Cleaning', 'Thorough home and office cleaning services']
        ];
        
        if ($hasImageAndPriceRange) {
            // If the table has image and price_range columns, use the original insert
            $servicesWithImageAndPrice = [
                ['Painters', 'Professional painting services for interior and exterior walls', 'images/Painters.png', '$100-$500'],
                ['Electrical', 'Expert electrical repair and installation services', 'images/s2.png', '$80-$300'],
                ['Plumbing', 'Reliable plumbing services for all your needs', 'images/s3.png', '$90-$400'],
                ['Carpentry', 'Custom carpentry work and furniture making', 'images/renew.png', '$150-$700'],
                ['Cleaning', 'Thorough home and office cleaning services', 'images/cleaning.png', '$50-$200']
            ];
            
            $stmt = $conn->prepare("INSERT INTO services (name, description, image, price_range) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $description, $image, $price_range);
            
            foreach ($servicesWithImageAndPrice as $service) {
                $name = $service[0];
                $description = $service[1];
                $image = $service[2];
                $price_range = $service[3];
                if ($stmt->execute()) {
                    echo "<li>✅ Service '{$name}' created with image and price range</li>";
                } else {
                    echo "<li>❌ Error creating service '{$name}': " . $stmt->error . "</li>";
                    $errors[] = "Error creating service '{$name}': " . $stmt->error;
                }
            }
        } else {
            // If the table doesn't have those columns, only insert name and description
            $stmt = $conn->prepare("INSERT INTO services (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            
            foreach ($services as $service) {
                $name = $service[0];
                $description = $service[1];
                if ($stmt->execute()) {
                    echo "<li>✅ Service '{$name}' created (without image and price range)</li>";
                } else {
                    echo "<li>❌ Error creating service '{$name}': " . $stmt->error . "</li>";
                    $errors[] = "Error creating service '{$name}': " . $stmt->error;
                }
            }
        }
        $stmt->close();
    } else {
        echo "<li>ℹ️ Services already exist in the database</li>";
    }
    
    // Default skills
    $skillsCount = $conn->query("SELECT COUNT(*) as count FROM skills")->fetch_assoc()['count'];
    
    if ($skillsCount == 0) {
        $skills = [
            ['Plumbing', 'Installation and repair of pipes and fixtures'],
            ['Carpentry', 'Work with wood to construct, repair, or restore structures'],
            ['Electrical', 'Installation and maintenance of electrical systems'],
            ['Painting', 'Interior and exterior painting services'],
            ['Gardening', 'Planting and maintaining gardens'],
            ['Cleaning', 'Residential and commercial cleaning services'],
            ['Moving', 'Help with moving furniture and belongings'],
            ['Masonry', 'Work with concrete, brick, and stone']
        ];
        
        $stmt = $conn->prepare("INSERT INTO skills (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        
        foreach ($skills as $skill) {
            $name = $skill[0];
            $description = $skill[1];
            if ($stmt->execute()) {
                echo "<li>✅ Skill '{$name}' created</li>";
            } else {
                echo "<li>❌ Error creating skill '{$name}': " . $stmt->error . "</li>";
                $errors[] = "Error creating skill '{$name}': " . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        echo "<li>ℹ️ Skills already exist in the database</li>";
    }
    
    echo "</ul>";
}

// Add demo data for Jobs and Users (before Payments and Ratings sections)
if (tableExists($conn, 'jobs') && !tableHasData($conn, 'jobs')) {
    echo "<h2>Adding Job Demo Data</h2>";
    echo "<ul>";
    
    // First ensure we have customer and laborer users
    $customerExists = $conn->query("SELECT id FROM users WHERE role = 'customer' LIMIT 1");
    $laborerExists = $conn->query("SELECT id FROM users WHERE role = 'laborer' LIMIT 1");
    
    // Add demo customers if none exist
    if ($customerExists && $customerExists->num_rows == 0) {
        $customer_password = password_hash('password123', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role) 
                VALUES ('Demo', 'Customer', 'customer@example.com', '5551234567', '$customer_password', 'customer')";
        
        if ($conn->query($sql) === TRUE) {
            echo "<li>✅ Demo customer user created (Email: customer@example.com, Password: password123)</li>";
        } else {
            echo "<li>❌ Error creating demo customer: " . $conn->error . "</li>";
        }
        
        // Add a second customer
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role) 
                VALUES ('Jane', 'Doe', 'jane@example.com', '5559876543', '$customer_password', 'customer')";
        
        if ($conn->query($sql) === TRUE) {
            echo "<li>✅ Demo customer Jane created (Email: jane@example.com, Password: password123)</li>";
        } else {
            echo "<li>❌ Error creating demo customer Jane: " . $conn->error . "</li>";
        }
    } else {
        echo "<li>ℹ️ Customer users already exist</li>";
    }
    
    // Add demo laborers if none exist
    if ($laborerExists && $laborerExists->num_rows == 0) {
        $laborer_password = password_hash('password123', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role) 
                VALUES ('Demo', 'Laborer', 'laborer@example.com', '5552345678', '$laborer_password', 'laborer')";
        
        if ($conn->query($sql) === TRUE) {
            echo "<li>✅ Demo laborer user created (Email: laborer@example.com, Password: password123)</li>";
        } else {
            echo "<li>❌ Error creating demo laborer: " . $conn->error . "</li>";
        }
        
        // Add a second laborer
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role) 
                VALUES ('John', 'Smith', 'john@example.com', '5558765432', '$laborer_password', 'laborer')";
        
        if ($conn->query($sql) === TRUE) {
            echo "<li>✅ Demo laborer John created (Email: john@example.com, Password: password123)</li>";
        } else {
            echo "<li>❌ Error creating demo laborer John: " . $conn->error . "</li>";
        }
    } else {
        echo "<li>ℹ️ Laborer users already exist</li>";
    }
    
    // Get customer and service IDs for job creation
    $customerResult = $conn->query("SELECT id FROM users WHERE role = 'customer' ORDER BY id ASC LIMIT 2");
    $customers = [];
    if ($customerResult) {
        while ($row = $customerResult->fetch_assoc()) {
            $customers[] = $row['id'];
        }
    }
    
    $laborerResult = $conn->query("SELECT id FROM users WHERE role = 'laborer' ORDER BY id ASC LIMIT 2");
    $laborers = [];
    if ($laborerResult) {
        while ($row = $laborerResult->fetch_assoc()) {
            $laborers[] = $row['id'];
        }
    }
    
    $serviceResult = $conn->query("SELECT id FROM services ORDER BY id ASC LIMIT 4");
    $services = [];
    if ($serviceResult) {
        while ($row = $serviceResult->fetch_assoc()) {
            $services[] = $row['id'];
        }
    }
    
    // Create demo jobs if we have the necessary IDs
    if (!empty($customers) && !empty($services)) {
        // Different job statuses for variety
        $statuses = ['open', 'assigned', 'in_progress', 'completed', 'cancelled'];
        
        // Demo jobs data
        $demoJobs = [
            [
                'title' => 'Kitchen Painting',
                'description' => 'Need to paint my kitchen walls and ceiling. Approximately 200 sq ft.',
                'customer_id' => $customers[0],
                'service_id' => $services[0], // Assuming Painters is first service
                'location' => '123 Main St, Anytown, USA',
                'budget' => 350.00,
                'status' => 'completed',
                'laborer_id' => !empty($laborers) ? $laborers[0] : NULL
            ],
            [
                'title' => 'Bathroom Plumbing Repair',
                'description' => 'Leaking sink and toilet needs fixing urgently.',
                'customer_id' => $customers[0],
                'service_id' => isset($services[2]) ? $services[2] : $services[0], // Plumbing if available
                'location' => '123 Main St, Anytown, USA',
                'budget' => 200.00,
                'status' => 'in_progress',
                'laborer_id' => !empty($laborers) ? $laborers[1] : NULL
            ],
            [
                'title' => 'Living Room Light Installation',
                'description' => 'Need ceiling fan with light installed in living room.',
                'customer_id' => isset($customers[1]) ? $customers[1] : $customers[0],
                'service_id' => isset($services[1]) ? $services[1] : $services[0], // Electrical if available
                'location' => '456 Oak St, Anytown, USA',
                'budget' => 150.00,
                'status' => 'open',
                'laborer_id' => NULL
            ],
            [
                'title' => 'House Deep Cleaning',
                'description' => 'Need deep cleaning for 3 bedroom house, including bathrooms and kitchen.',
                'customer_id' => isset($customers[1]) ? $customers[1] : $customers[0],
                'service_id' => isset($services[4]) ? $services[4] : $services[0], // Cleaning if available
                'location' => '456 Oak St, Anytown, USA',
                'budget' => 250.00,
                'status' => 'assigned',
                'laborer_id' => !empty($laborers) ? $laborers[0] : NULL
            ],
            [
                'title' => 'Kitchen Cabinet Installation',
                'description' => 'Need help installing new kitchen cabinets.',
                'customer_id' => $customers[0],
                'service_id' => isset($services[3]) ? $services[3] : $services[0], // Carpentry if available
                'location' => '123 Main St, Anytown, USA',
                'budget' => 500.00,
                'status' => 'cancelled',
                'laborer_id' => NULL
            ]
        ];
        
        // Prepare and execute job inserts
        $stmt = $conn->prepare("INSERT INTO jobs (title, description, customer_id, laborer_id, service_id, location, budget, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ssiissds", $title, $description, $customer_id, $laborer_id, $service_id, $location, $budget, $status);
            
            foreach ($demoJobs as $job) {
                $title = $job['title'];
                $description = $job['description'];
                $customer_id = $job['customer_id'];
                $laborer_id = $job['laborer_id'];
                $service_id = $job['service_id'];
                $location = $job['location'];
                $budget = $job['budget'];
                $status = $job['status'];
                
                if ($stmt->execute()) {
                    echo "<li>✅ Demo job '{$title}' created</li>";
                } else {
                    echo "<li>❌ Error creating demo job '{$title}': " . $stmt->error . "</li>";
                }
            }
        } else {
            echo "<li>❌ Error preparing job insert statement: " . $conn->error . "</li>";
        }
        
        $stmt->close();
    } else {
        echo "<li>❌ Cannot create demo jobs: missing customer or service data</li>";
    }
    
    echo "</ul>";
}

// Add demo data for Payments
if (tableExists($conn, 'payments') && !tableHasData($conn, 'payments')) {
    echo "<h2>Adding Payment Demo Data</h2>";
    echo "<ul>";
    
    // Get job IDs
    $jobResult = $conn->query("SELECT id FROM jobs ORDER BY id ASC LIMIT 5");
    $jobs = [];
    if ($jobResult) {
        while ($row = $jobResult->fetch_assoc()) {
            $jobs[] = $row['id'];
        }
        echo "<li>ℹ️ Retrieved job IDs for payments: " . implode(", ", $jobs) . "</li>";
    }
    
    if (!empty($jobs)) {
        $paymentColumns = [
            'job_id' => 'i',
            'amount' => 'd',
            'transaction_id' => 's',
            'status' => 's'
        ];
        
        // Make sure we don't try to access jobs that don't exist
        $jobCount = count($jobs);
        
        $paymentDemoData = [
            [
                'job_id' => $jobs[0],
                'amount' => 300.00,
                'transaction_id' => 'TXN'.mt_rand(1000000, 9999999),
                'status' => 'completed'
            ]
        ];
        
        // Only add more payment records if we have enough jobs
        if ($jobCount > 1) {
            $paymentDemoData[] = [
                'job_id' => $jobs[1],
                'amount' => 75.00,
                'transaction_id' => 'TXN'.mt_rand(1000000, 9999999),
                'status' => 'pending'
            ];
        }
        
        if ($jobCount > 2) {
            $paymentDemoData[] = [
                'job_id' => $jobs[2],
                'amount' => 120.00,
                'transaction_id' => 'TXN'.mt_rand(1000000, 9999999),
                'status' => 'completed'
            ];
        }
        
        if ($jobCount > 3) {
            $paymentDemoData[] = [
                'job_id' => $jobs[3],
                'amount' => 250.00,
                'transaction_id' => 'TXN'.mt_rand(1000000, 9999999),
                'status' => 'refunded'
            ];
        }
        
        if ($jobCount > 4) {
            $paymentDemoData[] = [
                'job_id' => $jobs[4],
                'amount' => 85.00,
                'transaction_id' => 'TXN'.mt_rand(1000000, 9999999),
                'status' => 'pending'
            ];
        }
        
        // Fix for the issue in insertDemoData function
        // Check each row to ensure all required fields exist
        foreach ($paymentDemoData as $index => $data) {
            foreach (array_keys($paymentColumns) as $column) {
                if (!isset($data[$column])) {
                    echo "<li>⚠️ Warning: Missing '{$column}' in payment data record {$index}, fixing...</li>";
                    // Set a default value based on column type
                    if ($column == 'job_id' && !empty($jobs)) {
                        $paymentDemoData[$index][$column] = $jobs[0]; // Use first job ID if missing
                    } elseif ($column == 'amount') {
                        $paymentDemoData[$index][$column] = 100.00; // Default amount
                    } elseif ($column == 'transaction_id') {
                        $paymentDemoData[$index][$column] = 'TXN'.mt_rand(1000000, 9999999); // Generate random ID
                    } elseif ($column == 'status') {
                        $paymentDemoData[$index][$column] = 'pending'; // Default status
                    }
                }
            }
        }
        
        $paymentResult = insertDemoData($conn, 'payments', $paymentDemoData, $paymentColumns);
        if ($paymentResult['success'] > 0) {
            echo "<li>✅ Successfully added {$paymentResult['success']} payments as demo data</li>";
        } else {
            echo "<li>❌ Failed to add payment demo data: " . implode("; ", $paymentResult['errors']) . "</li>";
        }
    } else {
        echo "<li>❌ Cannot add payment demo data: no jobs found in database</li>";
        echo "<li>ℹ️ Try adding job data first before payments</li>";
    }
    
    echo "</ul>";
}

// Also fix the ratings data - similar protections
if (tableExists($conn, 'ratings') && !tableHasData($conn, 'ratings')) {
    echo "<h2>Adding Ratings Demo Data</h2>";
    echo "<ul>";
    
    // Get job IDs
    $jobResult = $conn->query("SELECT id FROM jobs ORDER BY id ASC LIMIT 3");
    $jobs = [];
    if ($jobResult) {
        while ($row = $jobResult->fetch_assoc()) {
            $jobs[] = $row['id'];
        }
        echo "<li>ℹ️ Retrieved job IDs for ratings: " . implode(", ", $jobs) . "</li>";
    }
    
    // Get user IDs (for rater and ratee)
    $userResult = $conn->query("SELECT id FROM users ORDER BY id ASC LIMIT 4");
    $users = [];
    if ($userResult) {
        while ($row = $userResult->fetch_assoc()) {
            $users[] = $row['id'];
        }
        echo "<li>ℹ️ Retrieved user IDs for ratings: " . implode(", ", $users) . "</li>";
    }
    
    if (!empty($jobs) && count($users) >= 2) {
        $ratingColumns = [
            'job_id' => 'i',
            'rater_id' => 'i',
            'ratee_id' => 'i',
            'rating' => 'i',
            'comment' => 's'
        ];
        
        $jobCount = count($jobs);
        $userCount = count($users);
        
        $ratingDemoData = [];
        
        // First rating - always add if we have at least 1 job and 2 users
        if ($jobCount > 0 && $userCount >= 2) {
            $ratingDemoData[] = [
                'job_id' => $jobs[0],
                'rater_id' => $users[0],
                'ratee_id' => $users[1],
                'rating' => 5,
                'comment' => 'Excellent work! Very professional and completed the job ahead of schedule.'
            ];
            
            // Second rating - for the same job, reverse rater/ratee
            $ratingDemoData[] = [
                'job_id' => $jobs[0],
                'rater_id' => $users[1],
                'ratee_id' => $users[0],
                'rating' => 5,
                'comment' => 'Great customer! Clear instructions and paid promptly.'
            ];
        }
        
        // Only add more if we have enough jobs and users
        if ($jobCount > 1 && $userCount >= 3) {
            $ratingDemoData[] = [
                'job_id' => $jobs[1],
                'rater_id' => $users[0],
                'ratee_id' => $users[2],
                'rating' => 4,
                'comment' => 'Good work overall. Could have cleaned up a bit better afterwards.'
            ];
            
            $ratingDemoData[] = [
                'job_id' => $jobs[1],
                'rater_id' => $users[2],
                'ratee_id' => $users[0],
                'rating' => 5,
                'comment' => 'Very clear instructions and fair compensation.'
            ];
        }
        
        if ($jobCount > 2 && $userCount >= 4) {
            $ratingDemoData[] = [
                'job_id' => $jobs[2],
                'rater_id' => $users[3],
                'ratee_id' => $users[0],
                'rating' => 3,
                'comment' => 'Job requirements changed midway through the project.'
            ];
        }
        
        // Verify all data is present
        foreach ($ratingDemoData as $index => $data) {
            foreach (array_keys($ratingColumns) as $column) {
                if (!isset($data[$column])) {
                    echo "<li>⚠️ Warning: Missing '{$column}' in rating data record {$index}, fixing...</li>";
                    // Set a default value based on column type
                    if ($column == 'job_id' && !empty($jobs)) {
                        $ratingDemoData[$index][$column] = $jobs[0];
                    } elseif (($column == 'rater_id' || $column == 'ratee_id') && !empty($users)) {
                        $ratingDemoData[$index][$column] = $users[0];
                    } elseif ($column == 'rating') {
                        $ratingDemoData[$index][$column] = 5;
                    } elseif ($column == 'comment') {
                        $ratingDemoData[$index][$column] = 'Good service';
                    }
                }
            }
        }
        
        $ratingResult = insertDemoData($conn, 'ratings', $ratingDemoData, $ratingColumns);
        if ($ratingResult['success'] > 0) {
            echo "<li>✅ Successfully added {$ratingResult['success']} ratings as demo data</li>";
        } else {
            echo "<li>❌ Failed to add rating demo data: " . implode("; ", $ratingResult['errors']) . "</li>";
        }
    } else {
        echo "<li>❌ Cannot add rating demo data: missing job or user data</li>";
    }
    
    echo "</ul>";
}

// Final status message
if (empty($errors)) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3>✅ Database setup completed successfully!</h3>";
    echo "<p>All required tables and default data have been created.</p>";
    echo "<p><a href='index.php' style='display: inline-block; background-color: #0355cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Go to Homepage</a></p>";
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3>⚠️ Database setup completed with errors</h3>";
    echo "<p>Please check the error messages above and fix the issues before continuing.</p>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" width="device-width, initial-scale=1.0">
    <title>QuickHire Labor - Database Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        h1, h2, h3 {
            color: #0355cc;
        }
        ul {
            background-color: #f8f9fa;
            padding: 15px 30px;
            list-style-type: none;
            border-radius: 5px;
        }
        li {
            padding: 5px;
            margin-bottom: 8px;
        }
        a {
            color: #0355cc;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .button {
            display: inline-block;
            background-color: #0355cc;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #0243a3;
        }
        .option-box {
            background-color: #e9f5ff;
            border: 1px solid #b8daff;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        .option-links {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        .option-links a {
            padding: 10px 15px;
            background-color: #0355cc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .option-links a:hover {
            background-color: #0243a3;
        }
        .option-links a.secondary {
            background-color: #6c757d;
        }
        .option-links a.secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <h1>QuickHire Labor - Database Setup</h1>
    <?php if (empty($errors)): ?>
    <div class="option-box">
        <h3>Setup Options</h3>
        <p>Your database has been set up successfully. What would you like to do next?</p>
        <div class="option-links">
            <a href="sql_setup.php?with_sample_data=1">Import Sample Data</a>
            <a href="index.php" class="secondary">Go to Homepage</a>
        </div>
        <p><small>Note: The "Import Sample Data" option will create test accounts with username:password123 for testing purposes.</small></p>
    </div>
    <?php endif; ?>
</body>
</html>