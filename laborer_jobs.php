<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a laborer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laborer') {
    header("Location: login.php");
    exit();
}

// Handle job application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
    $job_id = $_POST['job_id'];
    $laborer_id = $_SESSION['user_id'];
    $cover_letter = sanitize_input($_POST['cover_letter'] ?? '');
    $price_quote = floatval($_POST['price_quote'] ?? 0);
    
    // Insert application instead of directly updating the job
    $stmt = $conn->prepare("INSERT INTO job_applications (job_id, laborer_id, cover_letter, price_quote, status) 
                          VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("issd", $job_id, $laborer_id, $cover_letter, $price_quote);
    
    if ($stmt->execute()) {
        // Create notification for the job owner
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            SELECT j.customer_id, 'New Job Application', CONCAT('New application for your job: ', j.title), 'application'
            FROM jobs j WHERE j.id = ?
        ");
        $notifStmt->bind_param("i", $job_id);
        $notifStmt->execute();
        
        $_SESSION['success'] = "Your application has been submitted successfully!";
    } else {
        $_SESSION['error'] = "Error submitting application.";
    }
    
    header("Location: laborer_jobs.php");
    exit();
}

// Get available jobs with broader criteria - keep the permissive query but remove debugging
$search = $_GET['search'] ?? '';
$skill = $_GET['skill'] ?? '';
$location = $_GET['location'] ?? '';

// Make query even more permissive by removing the WHERE clause entirely
$query = "
    SELECT j.*, 
           j.status AS job_status, 
           CONCAT(u.first_name, ' ', u.last_name) as customer_name,
           (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id AND laborer_id = ".$_SESSION['user_id'].") as has_applied
    FROM jobs j
    LEFT JOIN users u ON j.customer_id = u.id
    ORDER BY j.created_at DESC
";

// No parameters to bind, so simplified
$stmt = $conn->prepare($query);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Filter jobs to show only unassigned ones for display
$unassigned_jobs = array_filter($jobs, function($job) {
    return empty($job['laborer_id']);
});

// Use these filtered jobs for display
$jobs = $unassigned_jobs;

// Get distinct locations and skills for filters
$locations = $conn->query("SELECT DISTINCT location FROM jobs WHERE location IS NOT NULL")->fetch_all(MYSQLI_ASSOC);

$hasSkillsTable = $conn->query("SHOW TABLES LIKE 'skills'")->num_rows > 0;
$skills = [];

if ($hasSkillsTable) {
    $result = $conn->query("SELECT DISTINCT name FROM skills");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $skills[] = $row['name'];
        }
    }
} else {
    // Fallback - either set some default skills or skip this part
    $skills = ['Carpentry', 'Plumbing', 'Electrical', 'Painting', 'Cleaning']; // Default set
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Listings | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/laborer.css">
    <style>
        .job-filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .job-filters input,
        .job-filters select {
            padding: 8px;
            margin-right: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .job-filters button {
            padding: 8px 15px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .job-card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .apply-btn {
            background: #4CAF50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .submit-application-btn {
            background: #4CAF50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .no-jobs-message {
            text-align: center;
            margin-top: 20px;
        }
        .no-jobs-message h3 {
            color: #333;
        }
        .no-jobs-message p {
            color: #666;
        }
        .application-form {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        .application-form .form-group {
            margin-bottom: 10px;
        }
        .application-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .application-form textarea,
        .application-form input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 20px;
            width: 60%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .applied-badge {
            display: inline-block;
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .submit-btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <main class="content">
        <header>
            <h1>Find Job Opportunities</h1>
            <p>Browse and apply for jobs that match your skills and location.</p>
        </header>

        <!-- Search & Filter Section -->
        <div class="job-filters">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Search for jobs..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="skill">
                    <option value="">Filter by Skill</option>
                    <?php foreach ($skills as $skill): ?>
                        <option value="<?php echo htmlspecialchars($skill); ?>"><?php echo htmlspecialchars($skill); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="location">
                    <option value="">Filter by Location</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc['location']); ?>"><?php echo htmlspecialchars($loc['location']); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Apply Filters</button>
            </form>
        </div>

        <!-- Job Listings -->
        <div class="job-listings">
            <?php foreach ($jobs as $job): ?>
            <div class="job-card">
                <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                <p><strong>Pay:</strong> Rs.<?php echo number_format(isset($job['price']) ? $job['price'] : ($job['budget'] ?? 0), 2); ?></p>
                <p><?php echo htmlspecialchars($job['description']); ?></p>
                
                <?php if ($job['has_applied'] > 0): ?>
                    <div class="applied-badge">Already Applied</div>
                <?php else: ?>
                    <button class="apply-btn" onclick="openApplicationForm(<?php echo $job['id']; ?>)">Apply Now</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Add application modal form -->
        <div id="applicationModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Apply for Job</h2>
                <form method="POST" action="" id="applicationForm">
                    <input type="hidden" name="job_id" id="modal_job_id">
                    <div class="form-group">
                        <label for="cover_letter">Cover Letter:</label>
                        <textarea name="cover_letter" id="cover_letter" rows="4" required 
                                  placeholder="Explain why you're a good fit for this job..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="price_quote">Your Price Quote (Rs.):</label>
                        <input type="number" name="price_quote" id="price_quote" min="0" step="0.01" required>
                    </div>
                    <button type="submit" class="submit-btn">Submit Application</button>
                </form>
            </div>
        </div>
    </main>

    <script>
    function openApplicationForm(jobId) {
        document.getElementById('modal_job_id').value = jobId;
        document.getElementById('applicationModal').style.display = 'block';
    }

    // Close modal when X is clicked
    document.querySelector('.close').onclick = function() {
        document.getElementById('applicationModal').style.display = 'none';
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target == document.getElementById('applicationModal')) {
            document.getElementById('applicationModal').style.display = 'none';
        }
    }
    </script>
</body>
</html>