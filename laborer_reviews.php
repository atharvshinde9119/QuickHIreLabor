<?php
require_once 'config.php';

if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
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
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate average rating
$total_rating = 0;
$review_count = count($reviews);
foreach ($reviews as $review) {
    $total_rating += $review['rating'];
}
$avg_rating = $review_count > 0 ? round($total_rating / $review_count, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }

        .rating-summary {
            text-align: center;
            margin-bottom: 30px;
        }

        .average-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .rating-number {
            font-size: 48px;
            font-weight: bold;
            color: #4CAF50;
        }

        .stars {
            font-size: 24px;
            color: #ffc107;
        }

        .total-reviews {
            font-size: 18px;
            color: #666;
        }

        .reviews-list {
            display: grid;
            gap: 20px;
        }

        .review-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .review-text {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #666;
        }

        .customer-name {
            font-weight: bold;
        }

        .review-date {
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <div class="container">
        <div class="rating-summary">
            <h2>My Reviews</h2>
            <div class="average-rating">
                <span class="rating-number"><?php echo $avg_rating; ?></span>
                <div class="stars">
                    <?php echo str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)); ?>
                </div>
                <span class="total-reviews"><?php echo $review_count; ?> reviews</span>
            </div>
        </div>

        <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <h3><?php echo htmlspecialchars($review['job_title']); ?></h3>
                        <div class="rating-stars">
                            <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                        </div>
                    </div>
                    <p class="review-text"><?php echo htmlspecialchars($review['feedback']); ?></p>
                    <div class="review-footer">
                        <span class="customer-name">By: <?php echo htmlspecialchars($review['customer_name']); ?></span>
                        <span class="review-date">
                            <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>