<?php
require_once 'config.php';

if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle AJAX filtering
if (isset($_POST['filter'])) {
    $response = ['success' => false, 'jobs' => [], 'message' => ''];
    try {
        $status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';
        $date_filter = isset($_POST['date_filter']) ? sanitize_input($_POST['date_filter']) : '';
        
        $sql = "SELECT j.*, 
                CONCAT(u.first_name, ' ', u.last_name) as laborer_name, 
                u.phone as laborer_phone,
                u.profile_pic as laborer_pic,
                COUNT(DISTINCT r.id) as total_reviews,
                AVG(r.rating) as avg_rating
                FROM jobs j 
                LEFT JOIN users u ON j.laborer_id = u.id
                LEFT JOIN ratings r ON u.id = r.laborer_id
                WHERE j.customer_id = ?";
        
        $params = [$user_id];
        $types = "i";
        
        if ($status) {
            $sql .= " AND j.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($date_filter) {
            switch ($date_filter) {
                case 'today':
                    $sql .= " AND DATE(j.created_at) = CURDATE()";
                    break;
                case 'week':
                    $sql .= " AND j.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $sql .= " AND j.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
            }
        }
        
        $sql .= " GROUP BY j.id ORDER BY j.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $filtered_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $response['success'] = true;
        $response['jobs'] = $filtered_jobs;
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get all jobs with statistics
$hasSkillsTable = $conn->query("SHOW TABLES LIKE 'laborer_skills'")->num_rows > 0;

if ($hasSkillsTable) {
    $stmt = $conn->prepare("
        SELECT 
            j.*,
            CONCAT(u.first_name, ' ', u.last_name) as laborer_name,
            u.phone as laborer_phone,
            u.profile_pic as laborer_pic,
            u.address as laborer_address,
            COUNT(DISTINCT r.id) as total_reviews,
            AVG(r.rating) as avg_rating,
            GROUP_CONCAT(DISTINCT s.name) as required_skills
        FROM jobs j
        LEFT JOIN users u ON j.laborer_id = u.id
        LEFT JOIN ratings r ON u.id = r.laborer_id
        LEFT JOIN laborer_skills ls ON u.id = ls.laborer_id
        LEFT JOIN skills s ON ls.skill_id = s.id
        WHERE j.customer_id = ?
        GROUP BY j.id
        ORDER BY j.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $jobs = []; // Empty array as fallback
    error_log("Warning: laborer_skills table does not exist in the database");
}

// Get statistics
$total_jobs = count($jobs);
$active_jobs = count(array_filter($jobs, function($job) {
    return in_array($job['status'], ['pending', 'assigned']);
}));
$completed_jobs = count(array_filter($jobs, function($job) {
    return $job['status'] === 'completed';
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Posted Jobs - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }

        .job-filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
        }

        .filter-select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            flex: 1;
        }

        .job-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .job-title {
            font-size: 1.2em;
            color: #333;
            margin: 0;
        }

        .job-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .status-pending { background: #ffc107; color: #000; }
        .status-assigned { background: #17a2b8; color: white; }
        .status-completed { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }

        .job-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: 500;
            color: #333;
        }

        .laborer-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 15px;
        }

        .laborer-pic {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .rating-stars {
            color: #ffc107;
        }

        @media (max-width: 768px) {
            .container {
                margin-left: 0;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="container">
        <h1>My Posted Jobs</h1>
        
        <div class="job-filters">
            <select class="filter-select" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="assigned">Assigned</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select class="filter-select" id="dateFilter">
                <option value="">All Dates</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
            </select>
        </div>

        <div class="jobs-container">
            <?php foreach ($jobs as $job): ?>
                <div class="job-card" data-status="<?php echo $job['status']; ?>">
                    <div class="job-header">
                        <h2 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h2>
                        <span class="job-status status-<?php echo $job['status']; ?>">
                            <?php echo ucfirst($job['status']); ?>
                        </span>
                    </div>

                    <div class="job-info">
                        <div class="info-item">
                            <span class="info-label">Location</span>
                            <span class="info-value"><?php echo htmlspecialchars($job['location']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Price</span>
                            <span class="info-value">₹<?php echo number_format($job['price'], 2); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Posted Date</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Scheduled Date</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($job['scheduled_date'])); ?></span>
                        </div>
                    </div>

                    <?php if ($job['laborer_id']): ?>
                        <div class="laborer-info">
                            <img src="<?php echo $job['laborer_pic'] ?: 'images/default_profile.png'; ?>" 
                                 alt="Laborer" class="laborer-pic">
                            <div>
                                <h3><?php echo htmlspecialchars($job['laborer_name']); ?></h3>
                                <div class="rating-stars">
                                    <?php
                                    $rating = round($job['avg_rating'] ?? 0);
                                    echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                                    ?> 
                                    (<?php echo $job['total_reviews']; ?> reviews)
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.getElementById('statusFilter').addEventListener('change', filterJobs);
        document.getElementById('dateFilter').addEventListener('change', filterJobs);

        function filterJobs() {
            const status = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            
            fetch('c_my_jobs.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `filter=1&status=${status}&date_filter=${dateFilter}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateJobsList(data.jobs);
                } else {
                    alert(data.message || 'Error filtering jobs');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while filtering jobs');
            });
        }

        function updateJobsList(jobs) {
            const container = document.querySelector('.jobs-container');
            container.innerHTML = '';
            
            jobs.forEach(job => {
                const jobCard = createJobCard(job);
                container.appendChild(jobCard);
            });
        }

        function createJobCard(job) {
            const div = document.createElement('div');
            div.className = 'job-card';
            div.dataset.status = job.status;
            
            // Format date properly
            const createdDate = new Date(job.created_at).toLocaleDateString();
            const scheduledDate = job.scheduled_date ? new Date(job.scheduled_date).toLocaleDateString() : 'Not scheduled';
            
            // Create rating stars
            const rating = Math.round(job.avg_rating || 0);
            const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
            
            div.innerHTML = `
                <div class="job-header">
                    <h2 class="job-title">${job.title}</h2>
                    <span class="job-status status-${job.status}">${job.status.charAt(0).toUpperCase() + job.status.slice(1)}</span>
                </div>
                <div class="job-info">
                    <div class="info-item">
                        <span class="info-label">Location</span>
                        <span class="info-value">${job.location || 'Not specified'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Price</span>
                        <span class="info-value">₹${parseFloat(job.price).toFixed(2)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Posted Date</span>
                        <span class="info-value">${createdDate}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Scheduled Date</span>
                        <span class="info-value">${scheduledDate}</span>
                    </div>
                </div>
                ${job.laborer_name ? `
                    <div class="laborer-info">
                        <img src="${job.laborer_pic || 'images/default_profile.png'}" alt="Laborer" class="laborer-pic">
                        <div>
                            <h3>${job.laborer_name}</h3>
                            <div class="rating-stars">
                                ${stars} (${job.total_reviews || 0} reviews)
                            </div>
                        </div>
                    </div>
                ` : ''}
            `;
            
            return div;
        }
    </script>
</body>
</html>
