<?php
require_once 'config.php';

// Check if user is logged in and is a laborer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laborer') {
    header("Location: login.php");
    exit();
}

$laborer_id = $_SESSION['user_id'];
$error_msg = $success_msg = '';

// Handle job application withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_job'])) {
    try {
        $job_id = (int)$_POST['job_id'];
        
        $stmt = $conn->prepare("UPDATE jobs SET laborer_id = NULL, status = 'pending' 
                               WHERE id = ? AND laborer_id = ? AND status = 'assigned'");
        $stmt->bind_param("ii", $job_id, $laborer_id);
        
        if ($stmt->execute()) {
            $success_msg = "Successfully withdrew from the job.";
        } else {
            throw new Exception("Failed to withdraw from the job.");
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

// Get all job applications for the laborer
$stmt = $conn->prepare("
    SELECT j.id, j.title, j.description, j.location, j.budget, j.status, 
           CONCAT(u.first_name, ' ', u.last_name) AS customer_name, 
           u.email AS customer_email, 
           j.created_at
    FROM jobs j
    JOIN users u ON j.customer_id = u.id
    WHERE j.laborer_id = ? AND j.status IN ('assigned', 'in_progress')
    ORDER BY j.created_at DESC
");
$stmt->bind_param("i", $laborer_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent updates
$stmt = $conn->prepare("
    SELECT j.title, j.status, 
           DATE_FORMAT(j.created_at, '%b %d, %Y') as update_date
    FROM jobs j
    WHERE j.laborer_id = ?
    ORDER BY j.created_at DESC LIMIT 5
");
$stmt->bind_param("i", $laborer_id);
$stmt->execute();
$updates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/laborer.css">
    <style>
        .status-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .withdraw-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .view-details {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .job-updates {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .job-updates ul {
            list-style: none;
            padding: 0;
        }
        .job-updates li {
            padding: 10px;
            border-bottom: 1px solid #eee;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="content">
        <header>
            <h1>Application Status</h1>
        </header>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Status Table -->
        <section class="status-container">
            <table>
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Employer</th>
                        <th>Date Applied</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['title']); ?></td>
                        <td><?php echo htmlspecialchars($app['customer_name']); ?></td>
                        <td><?php echo $app['created_at']; ?></td>
                        <td class="<?php echo strtolower($app['status']); ?>">
                            <?php echo ucfirst($app['status']); ?>
                        </td>
                        <td>
                            <?php if ($app['status'] === 'assigned'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="job_id" value="<?php echo $app['id']; ?>">
                                    <button type="submit" name="withdraw_job" class="withdraw-btn"
                                            onclick="return confirm('Are you sure you want to withdraw from this job?')">
                                        Withdraw
                                    </button>
                                </form>
                            <?php elseif ($app['status'] === 'pending'): ?>
                                <button class="view-details">View</button>
                            <?php else: ?>
                                <button class="disabled" disabled><?php echo ucfirst($app['status']); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($applications)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No job applications found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Job Application Updates -->
        <section class="job-updates">
            <h2>Recent Updates</h2>
            <ul>
                <?php foreach ($updates as $update): ?>
                <li>
                    <?php echo $update['update_date']; ?> - 
                    Your application for "<?php echo htmlspecialchars($update['title']); ?>" is 
                    <?php echo strtolower($update['status']); ?>.
                </li>
                <?php endforeach; ?>

                <?php if (empty($updates)): ?>
                <li>No recent updates.</li>
                <?php endif; ?>
            </ul>
        </section>
    </main>
</body>
</html>
