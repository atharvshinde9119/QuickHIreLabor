<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// Add proper HTML structure
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratings Table Structure - Quick-Hire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .code {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            margin: 10px 0;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .success {
            color: green;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="content">
        <header>
            <h2>Ratings Table Structure Check</h2>
        </header>

        <?php if (!tableExists($conn, 'ratings')): ?>
            <div class="alert error">Table 'ratings' doesn't exist in the database.</div>
            
            <h3>SQL to Create Ratings Table</h3>
            <div class="code">
CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    rater_id INT NOT NULL,
    ratee_id INT NOT NULL,
    rating INT NOT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id),
    FOREIGN KEY (rater_id) REFERENCES users(id),
    FOREIGN KEY (ratee_id) REFERENCES users(id)
)
            </div>
            
            <a href="sql_setup.php" class="btn">Run SQL Setup</a>
        <?php else: ?>
            <div class="alert success">Table 'ratings' exists in the database.</div>
            
            <h3>Table Structure</h3>
            <table>
                <tr>
                    <th>Column</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>
                <?php
                $result = $conn->query("DESCRIBE ratings");
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$row['Field']}</td>";
                    echo "<td>{$row['Type']}</td>";
                    echo "<td>{$row['Null']}</td>";
                    echo "<td>{$row['Key']}</td>";
                    echo "<td>{$row['Default']}</td>";
                    echo "<td>{$row['Extra']}</td>";
                    echo "</tr>";
                }
                ?>
            </table>
            
            <h3>Fixed SQL Query for ratings.php</h3>
            <div class="code">
SELECT r.*, 
       j.title as job_title,
       c.first_name as customer_first_name,
       c.last_name as customer_last_name,
       l.first_name as laborer_first_name,
       l.last_name as laborer_last_name,
       j.id as job_id
FROM ratings r
JOIN jobs j ON r.job_id = j.id
JOIN users c ON r.rater_id = c.id
JOIN users l ON r.ratee_id = l.id
ORDER BY r.created_at DESC
            </div>
            
            <h3>Sample Data in Ratings Table</h3>
            <?php
            $sample = $conn->query("SELECT * FROM ratings LIMIT 5");
            if ($sample && $sample->num_rows > 0) {
                echo "<table>";
                $firstRow = $sample->fetch_assoc();
                echo "<tr>";
                foreach ($firstRow as $column => $value) {
                    echo "<th>{$column}</th>";
                }
                echo "</tr>";
                
                // Display the first row
                echo "<tr>";
                foreach ($firstRow as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
                
                // Display the rest of the rows
                while ($row = $sample->fetch_assoc()) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No data found in the ratings table.</p>";
            }
            ?>
            
            <a href="ratings.php" class="btn">Return to Ratings</a>
        <?php endif; ?>
    </div>
</body>
</html>
