<?php
require_once 'config.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Function to check table schema
function checkTableSchema($conn, $tableName) {
    $issues = [];
    
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0;
    if (!$tableExists) {
        return ["Table '$tableName' does not exist in the database"];
    }
    
    // Get table columns
    $columns = [];
    $result = $conn->query("DESCRIBE $tableName");
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row;
    }
    
    // For users table, specifically check name-related columns
    if ($tableName == 'users') {
        if (!isset($columns['first_name'])) {
            $issues[] = "Missing 'first_name' column in users table";
        }
        
        if (!isset($columns['last_name'])) {
            $issues[] = "Missing 'last_name' column in users table";
        }
        
        if (isset($columns['name'])) {
            $issues[] = "Found 'name' column in users table - should use 'first_name' and 'last_name' instead";
        }
        
        if (isset($columns['username']) && !isset($columns['first_name'])) {
            $issues[] = "Table uses 'username' but is missing 'first_name' field";
        }
    }
    
    // For all tables with name columns, check for consistency
    $nameColumns = ['name', 'first_name', 'last_name', 'customer_name', 'laborer_name'];
    $foundNameColumns = array_intersect(array_keys($columns), $nameColumns);
    
    if (count($foundNameColumns) > 0) {
        $issues[] = "Table '$tableName' uses these name columns: " . implode(", ", $foundNameColumns);
    }
    
    return $issues;
}

// Get list of all tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Check each table
$allIssues = [];
foreach ($tables as $table) {
    $issues = checkTableSchema($conn, $table);
    if (!empty($issues)) {
        $allIssues[$table] = $issues;
    }
}

// Find PHP files that might be referencing wrong column names
$phpIssues = [];
$phpFiles = glob(__DIR__ . '/*.php');
foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    
    // Look for SQL queries that might be using the wrong name columns
    if (preg_match('/SELECT.*name\b.*FROM\s+users/i', $content) && !strpos($file, 'sql_setup.php')) {
        $phpIssues[$file] = "Possible use of 'name' column from users table";
    }
    
    if (preg_match('/SELECT.*customer_name.*FROM/i', $content)) {
        $phpIssues[$file] = "Using 'customer_name' - should likely use JOIN with users table and first_name/last_name";
    }
    
    if (preg_match('/SELECT.*laborer_name.*FROM/i', $content)) {
        $phpIssues[$file] = "Using 'laborer_name' - should likely use JOIN with users table and first_name/last_name";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Schema Check - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .issue-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .issue {
            padding: 8px;
            margin-bottom: 5px;
            border-left: 3px solid #dc3545;
            background: #fff;
        }
        .warning {
            color: #856404;
            background-color: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .fix-suggestion {
            background: #d1ecf1;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <div class="content">
        <header>
            <h2>Database Schema Check</h2>
        </header>
        
        <div class="section">
            <h3>Database Table Issues</h3>
            <?php if (empty($allIssues)): ?>
                <p>✅ No schema issues found with database tables!</p>
            <?php else: ?>
                <div class="warning">
                    Found potential issues with database schema. These may cause errors in your application.
                </div>
                
                <?php foreach ($allIssues as $table => $issues): ?>
                    <div class="issue-list">
                        <h4><?php echo $table; ?> Table</h4>
                        <?php foreach ($issues as $issue): ?>
                            <div class="issue"><?php echo $issue; ?></div>
                        <?php endforeach; ?>
                        
                        <?php if ($table == 'users' && in_array("Missing 'first_name' column in users table", $issues)): ?>
                            <div class="fix-suggestion">
                                <strong>Suggested Fix:</strong> Run the following SQL:<br>
                                <code>ALTER TABLE users ADD COLUMN first_name VARCHAR(50) AFTER id;</code><br>
                                <code>ALTER TABLE users ADD COLUMN last_name VARCHAR(50) AFTER first_name;</code>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h3>PHP Code Issues</h3>
            <?php if (empty($phpIssues)): ?>
                <p>✅ No name column issues found in PHP files!</p>
            <?php else: ?>
                <div class="warning">
                    Found potential issues with name columns in PHP files. These may cause errors when accessing the database.
                </div>
                
                <div class="issue-list">
                    <?php foreach ($phpIssues as $file => $issue): ?>
                        <div class="issue">
                            <strong><?php echo basename($file); ?></strong>: <?php echo $issue; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="fix-suggestion">
                        <strong>Suggested Fix:</strong> When accessing user names, always use first_name and last_name fields 
                        from the users table. If you need a full name, concatenate these fields.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <a href="sql_setup.php" class="btn">Go to Database Setup</a>
        </div>
    </div>
</body>
</html>
