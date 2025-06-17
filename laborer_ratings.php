<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laborer') {
    header("Location: login.php");
    exit();
}

$laborer_id = $_SESSION['user_id'];

// Get overall rating stats
$stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
    FROM ratings WHERE ratee_id = ?
");
$stmt->bind_param("i", $laborer_id);
$stmt->execute();
$rating_stats = $stmt->get_result()->fetch_assoc();

// Get recent reviews
$stmt = $conn->prepare("
    SELECT r.id, r.job_id, r.rating, r.comment, r.created_at,
           j.title AS job_title,
           CONCAT(u.first_name, ' ', u.last_name) AS customer_name
    FROM ratings r
    JOIN jobs j ON r.job_id = j.id
    JOIN users u ON r.rater_id = u.id
    WHERE r.ratee_id = ?
    ORDER BY r.created_at DESC
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
    <title>Ratings & Reviews | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/laborer.css">
    <style>
        .ratings-overview {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .rating-score {
            font-size: 48px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
        }
        .stars {
            color: #ffc107;
            font-size: 24px;
            margin: 10px 0;
        }
        .review {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .review .date {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="content">
        <header>
            <h1>Ratings & Reviews</h1>
        </header>

        <!-- Ratings Overview -->
        <section class="ratings-overview">
            <h2>Overall Rating</h2>
            <div class="rating-score"><?php echo number_format($rating_stats['avg_rating'], 1); ?></div>
            <div class="stars">
                <?php
                $stars = round($rating_stats['avg_rating']);
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= $stars ? '★' : '☆';
                }
                ?>
            </div>
            <p>Based on <?php echo $rating_stats['total_reviews']; ?> reviews</p>
        </section>

        <!-- Reviews List -->
        <section class="reviews-container">
            <h2>Recent Feedback</h2>
            <?php foreach ($reviews as $review): ?>
            <div class="review">
                <h3><?php echo htmlspecialchars($review['customer_name']); ?></h3>
                <p><strong>Job:</strong> <?php echo htmlspecialchars($review['job_title']); ?></p>
                <div class="stars">
                    <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                </div>
                <p><?php echo htmlspecialchars($review['feedback']); ?></p>
                <span class="date"><?php echo $review['review_date']; ?></span>
            </div>
            <?php endforeach; ?>

            <?php if (empty($reviews)): ?>
            <p>No reviews yet.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
