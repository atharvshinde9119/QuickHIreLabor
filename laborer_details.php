<?php
require_once 'config.php';

if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: c_search_labor.php");
    exit();
}

$laborer_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get laborer details with ratings and skills
$stmt = $conn->prepare("
    SELECT u.*, 
           AVG(r.rating) as avg_rating,
           COUNT(DISTINCT r.id) as total_ratings,
           COUNT(DISTINCT j.id) as completed_jobs,
           GROUP_CONCAT(DISTINCT s.name) as skills_list
    FROM users u 
    LEFT JOIN ratings r ON u.id = r.laborer_id
    LEFT JOIN jobs j ON u.id = j.laborer_id AND j.status = 'completed'
    LEFT JOIN laborer_skills ls ON u.id = ls.laborer_id
    LEFT JOIN skills s ON ls.skill_id = s.id
    WHERE u.id = ? AND u.role = 'laborer'
    GROUP BY u.id"
);

$stmt->bind_param("i", $laborer_id);
$stmt->execute();
$laborer = $stmt->get_result()->fetch_assoc();

if (!$laborer) {
    header("Location: c_search_labor.php");
    exit();
}

// Get recent reviews
$stmt = $conn->prepare("
    SELECT r.*, j.title as job_title, u.name as customer_name
    FROM ratings r
    JOIN jobs j ON r.job_id = j.id
    JOIN users u ON r.customer_id = u.id
    WHERE r.laborer_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $laborer_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laborer Details - <?php echo htmlspecialchars($laborer['name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }

        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-info h1 {
            margin: 0;
            color: #333;
        }

        .rating-stars {
            color: #ffc107;
            font-size: 24px;
            margin: 10px 0;
        }

        .stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }

        .skill-tag {
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
        }

        .reviews-section h2 {
            margin: 20px 0;
        }

        .review-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .hire-btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }

        .hire-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="container">
        <div class="profile-card">
            <div class="profile-header">
                <img src="<?php echo htmlspecialchars($laborer['profile_pic'] ?: 'images/default_profile.png'); ?>" 
                     alt="Profile Picture" class="profile-pic">
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($laborer['name']); ?></h1>
                    <div class="rating-stars">
                        <?php
                        $rating = round($laborer['avg_rating'] ?: 0);
                        echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                        ?>
                    </div>
                    <p><?php echo htmlspecialchars($laborer['address']); ?></p>
                </div>
            </div>

            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $laborer['completed_jobs']; ?></div>
                    <div>Jobs Completed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $laborer['total_ratings']; ?></div>
                    <div>Total Reviews</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($laborer['avg_rating'], 1); ?></div>
                    <div>Average Rating</div>
                </div>
            </div>

            <div class="skills-list">
                <?php
                if ($laborer['skills_list']) {
                    foreach (explode(',', $laborer['skills_list']) as $skill) {
                        echo '<span class="skill-tag">' . htmlspecialchars($skill) . '</span>';
                    }
                }
                ?>
            </div>

            <a href="c_post_job.php?laborer_id=<?php echo $laborer_id; ?>" class="hire-btn">Hire Now</a>
        </div>

        <div class="reviews-section">
            <h2>Recent Reviews</h2>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <strong><?php echo htmlspecialchars($review['customer_name']); ?></strong>
                        <div class="rating-stars">
                            <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                        </div>
                    </div>
                    <p><?php echo htmlspecialchars($review['feedback']); ?></p>
                    <small>Job: <?php echo htmlspecialchars($review['job_title']); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
