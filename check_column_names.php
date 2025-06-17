<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

echo "<h1>Database Column Name Check Utility</h1>";

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// Get list of all columns in the users table
$userColumns = [];
echo "<h2>Users Table Columns</h2>";
if (tableExists($conn, 'users')) {
    $result = $conn->query("DESCRIBE users");
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        $userColumns[] = $row['Field'];
        echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Users table doesn't exist!</p>";
}

// Scan PHP files for SQL queries that might be using incorrect column names
echo "<h2>Potential SQL Issues in PHP Files</h2>";

$directory = __DIR__;
$phpFiles = glob($directory . '/*.php');
$issues = [];

// Get all table structures to check column references
$tableStructures = [];
$tables = $conn->query("SHOW TABLES");
while ($table = $tables->fetch_row()) {
    $tableName = $table[0];
    $tableStructures[$tableName] = [];
    
    $columns = $conn->query("DESCRIBE `$tableName`");
    while ($column = $columns->fetch_assoc()) {
        $tableStructures[$tableName][] = $column['Field'];
    }
}

foreach ($phpFiles as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);
    
    // Skip this utility file
    if ($filename === 'check_column_names.php') {
        continue;
    }
    
    // Extract SQL queries
    preg_match_all('/SELECT.*?FROM.*?(?:;|\$)/is', $content, $queries);
    
    foreach ($queries[0] as $query) {
        // Check for name column usage
        if (preg_match('/\b(?:u|c|l|users)\.name\b/i', $query)) {
            $issues[] = [
                'file' => $filename,
                'issue' => "Uses 'name' column from users table which doesn't exist",
                'suggestion' => "Replace with CONCAT(first_name, ' ', last_name) or use first_name/last_name separately",
                'query' => htmlspecialchars(substr($query, 0, 200) . '...')
            ];
        }
        
        // Check for incorrect ratings columns
        if (preg_match('/\br\.(?:customer_id|laborer_id)\b/i', $query)) {
            $issues[] = [
                'file' => $filename,
                'issue' => "Uses 'customer_id' or 'laborer_id' from ratings table - should use 'rater_id' and 'ratee_id'",
                'suggestion' => "Change r.customer_id to r.rater_id and r.laborer_id to r.ratee_id",
                'query' => htmlspecialchars(substr($query, 0, 200) . '...')
            ];
        }
        
        // Check for each referenced table and column
        foreach ($tableStructures as $tableName => $columns) {
            if (preg_match('/\b' . $tableName . '\b/i', $query) || 
                preg_match('/\b' . substr($tableName, 0, 1) . '\b/i', $query)) {
                
                // Get all potential column references
                preg_match_all('/\b[a-z]\.[a-z_]+\b/i', $query, $columnRefs);
                
                foreach ($columnRefs[0] as $columnRef) {
                    $parts = explode('.', $columnRef);
                    $tableAlias = $parts[0];
                    $columnName = $parts[1];
                    
                    // Skip known good columns
                    if ($columnName === 'id' || $columnName === 'title' || 
                        $columnName === 'first_name' || $columnName === 'last_name') {
                        continue;
                    }
                    
                    // Check if it might be referring to this table and the column doesn't exist
                    if (($tableAlias === substr($tableName, 0, 1) || 
                         preg_match('/\b' . $tableAlias . '\s+AS\s+' . $tableName . '\b/i', $query) ||
                         preg_match('/\b' . $tableName . '\s+AS\s+' . $tableAlias . '\b/i', $query)) && 
                        !in_array($columnName, $columns)) {
                        
                        $issues[] = [
                            'file' => $filename,
                            'issue' => "Column '$columnName' doesn't exist in table '$tableName'",
                            'suggestion' => "Check the table structure and use correct column names",
                            'query' => htmlspecialchars(substr($query, 0, 200) . '...')
                        ];
                    }
                }
            }
        }
    }
}

if (empty($issues)) {
    echo "<p>No issues found! All files appear to be using correct column names.</p>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>File</th><th>Issue</th><th>Suggestion</th><th>Query</th></tr>";
    
    foreach ($issues as $issue) {
        echo "<tr>";
        echo "<td>" . $issue['file'] . "</td>";
        echo "<td>" . $issue['issue'] . "</td>";
        echo "<td>" . $issue['suggestion'] . "</td>";
        echo "<td>" . $issue['query'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<h2>Recommendations</h2>";
echo "<p>If your database uses 'first_name' and 'last_name' columns, all SQL queries should reference these columns directly.</p>";
echo "<p>For displaying full names, use: CONCAT(u.first_name, ' ', u.last_name) AS user_name</p>";
echo "<p><a href='sql_setup.php'>Go to SQL Setup</a></p>";
?>
