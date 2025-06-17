<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// First, check if the ratings table exists with the correct structure
$tableCheck = $conn->query("SHOW TABLES LIKE 'ratings'");
if ($tableCheck->num_rows == 0) {
    // Include the setup file to create the ratings table
    include 'setup_ratings.php';
    // Redirect to refresh the page after creating the table
    header("Location: c_ratings.php");
    exit();
}

// Check the structure of the ratings table to ensure it has the correct columns
$columnsCheck = $conn->query("SHOW COLUMNS FROM ratings LIKE 'review'");
if ($columnsCheck->num_rows == 0) {
    // The 'review' column doesn't exist, so we need to create it
    $conn->query("ALTER TABLE ratings ADD COLUMN review TEXT NOT NULL AFTER rating");
    // Redirect to refresh the page after modifying the table
    header("Location: c_ratings.php");
    exit();
}

// Handle new rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $job_id = (int)$_POST['job_id'];
    $laborer_id = (int)$_POST['laborer_id'];
    $rating = (int)$_POST['rating'];
    $review = sanitize_input($_POST['review']);
    
    // Check if valid job
    $stmt = $conn->prepare("
        SELECT j.* FROM jobs j 
        WHERE j.id = ? AND j.customer_id = ? AND j.status = 'completed' 
        AND j.laborer_id = ?
    ");
    $stmt->bind_param("iii", $job_id, $user_id, $laborer_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    // Validate rating value
    if ($rating < 1 || $rating > 5) {
        $error = "Rating must be between 1 and 5";
    } else if (!$job) {
        $error = "Invalid job selected";
    } else {
        // Check if already rated
        $stmt = $conn->prepare("SELECT id FROM ratings WHERE job_id = ? AND rater_id = ? AND ratee_id = ?");
        $stmt->bind_param("iii", $job_id, $user_id, $laborer_id);
        $stmt->execute();
        $existing_rating = $stmt->get_result()->fetch_assoc();
        
        if ($existing_rating) {
            // Update existing rating
            $stmt = $conn->prepare("
                UPDATE ratings 
                SET rating = ?, review = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("isi", $rating, $review, $existing_rating['id']);
            
            if ($stmt->execute()) {
                $success = "Your rating has been updated successfully!";
            } else {
                $error = "Failed to update rating: " . $conn->error;
            }
        } else {
            // Insert new rating
            $stmt = $conn->prepare("
                INSERT INTO ratings (job_id, rater_id, ratee_id, rating, review) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiiis", $job_id, $user_id, $laborer_id, $rating, $review);
            
            if ($stmt->execute()) {
                $success = "Thank you for your rating!";
                
                // Notify laborer
                createNotification(
                    $laborer_id,
                    'New Rating Received',
                    "You've received a {$rating}-star rating for job: '{$job['title']}'",
                    'rating'
                );
            } else {
                $error = "Failed to submit rating: " . $conn->error;
            }
        }
    }
}

// Get completed jobs that can be rated
$stmt = $conn->prepare("
    SELECT j.id, j.title, j.updated_at as completed_at, j.laborer_id,
           CONCAT(l.first_name, ' ', l.last_name) as laborer_name,
           r.rating, r.review, r.created_at as review_date
    FROM jobs j
    JOIN users l ON j.laborer_id = l.id
    LEFT JOIN ratings r ON r.job_id = j.id AND r.rater_id = ? AND r.ratee_id = j.laborer_id
    WHERE j.customer_id = ? AND j.status = 'completed'
    ORDER BY CASE WHEN r.id IS NULL THEN 0 ELSE 1 END, j.updated_at DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$ratable_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get my submitted reviews history
$stmt = $conn->prepare("
    SELECT r.*, j.title as job_title, 
           CONCAT(u.first_name, ' ', u.last_name) as laborer_name
    FROM ratings r
    JOIN jobs j ON r.job_id = j.id
    JOIN users u ON r.ratee_id = u.id
    WHERE r.rater_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratings & Reviews | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container {
            max-width: 1000px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .notification {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
        }
        
        .tab {
            padding: 15px 20px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            text-align: center;
            flex: 1;
        }
        
        .tab.active {
            background: white;
            border-bottom: 3px solid #4CAF50;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .rate-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #4CAF50;
        }
        
        .rate-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .rate-card-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        
        .rate-card-date {
            color: #6c757d;
            font-size: 14px;
        }
        
        .rating-form {
            margin-top: 15px;
        }
        
        .star-rating {
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
        }
        
        .star-rating > input {
            display: none;
        }
        
        .star-rating > label {
            color: #ddd;
            font-size: 30px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .star-rating > input:checked ~ label,
        .star-rating > input:hover ~ label {
            color: #ffb400;
        }
        
        .review-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 100px;
            resize: vertical;
            margin-bottom: 15px;
        }
        
        .submit-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .submit-btn:hover {
            background: #45a049;
        }
        
        .laborer-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .laborer-name {
            font-weight: 500;
            margin-left: 10px;
        }
        
        .existing-review {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .review-stars {
            color: #ffb400;
        }
        
        .review-date {
            font-size: 14px;
            color: #6c757d;
        }
        
        .review-text {
            line-height: 1.5;
        }
        
        .edit-review-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .review-history-item {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .history-title {
            font-weight: bold;
        }
        
        .history-date {
            font-size: 14px;
            color: #6c757d;
        }
        
        .history-rating {
            margin: 10px 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .completed-job-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .not-rated-badge {
            display: inline-block;
            background: #ffc107;
            color: #212529;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Ratings & Reviews</h1>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="notification success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="notification error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" data-tab="pending-ratings">Rate Jobs</div>
            <div class="tab" data-tab="my-reviews">My Reviews</div>
        </div>
        
        <!-- Pending Ratings Tab -->
        <div id="pending-ratings" class="tab-content active">
            <?php if (empty($ratable_jobs)): ?>
                <div class="empty-state">
                    <h3>No completed jobs to rate</h3>
                    <p>After you complete a job, you'll be able to rate your laborer here.</p>
                </div>
            <?php else: ?>
                <h2>Rate Your Laborers</h2>
                <p>Share your experience with laborers who have completed jobs for you. Your feedback helps other customers make informed decisions.</p>
                
                <?php foreach ($ratable_jobs as $job): ?>
                    <div class="rate-card">
                        <div class="rate-card-header">
                            <h3 class="rate-card-title">
                                <?php echo htmlspecialchars($job['title']); ?>
                                <?php if (isset($job['rating'])): ?>
                                    <span class="completed-job-badge">Rated</span>
                                <?php else: ?>
                                    <span class="not-rated-badge">Not Rated</span>
                                <?php endif; ?>
                            </h3>
                            <span class="rate-card-date">
                                Completed: <?php echo date('M j, Y', strtotime($job['completed_at'] ?? $job['completed_at'])); ?>
                            </span>
                        </div>
                        
                        <div class="laborer-info">
                            <i class="fas fa-user-circle fa-2x"></i>
                            <span class="laborer-name"><?php echo htmlspecialchars($job['laborer_name']); ?></span>
                        </div>
                        
                        <?php if (isset($job['rating'])): ?>
                            <div class="existing-review">
                                <div class="review-header">
                                    <div class="review-stars">
                                        <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $job['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                        }
                                        ?>
                                    </div>
                                    <span class="review-date">
                                        <?php echo date('M j, Y', strtotime($job['review_date'])); ?>
                                    </span>
                                </div>
                                <p class="review-text"><?php echo nl2br(htmlspecialchars($job['review'])); ?></p>
                                <button class="edit-review-btn" onclick="showEditForm(<?php echo $job['id']; ?>)">Edit Review</button>
                                
                                <div id="edit-form-<?php echo $job['id']; ?>" style="display: none;" class="rating-form">
                                    <form method="POST" action="">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="laborer_id" value="<?php echo $job['laborer_id']; ?>">
                                        
                                        <div class="star-rating">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <input type="radio" id="star<?php echo $job['id']; ?>-<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php echo ($job['rating'] == $i) ? 'checked' : ''; ?>>
                                                <label for="star<?php echo $job['id']; ?>-<?php echo $i; ?>">
                                                    <i class="fas fa-star"></i>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                        
                                        <textarea name="review" class="review-textarea" placeholder="Share your experience with this laborer..."><?php echo htmlspecialchars($job['review']); ?></textarea>
                                        
                                        <button type="submit" name="submit_rating" class="submit-btn">Update Review</button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="rating-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <input type="hidden" name="laborer_id" value="<?php echo $job['laborer_id']; ?>">
                                    
                                    <div class="star-rating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" id="star<?php echo $job['id']; ?>-<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>">
                                            <label for="star<?php echo $job['id']; ?>-<?php echo $i; ?>">
                                                <i class="fas fa-star"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <textarea name="review" class="review-textarea" placeholder="Share your experience with this laborer..." required></textarea>
                                    
                                    <button type="submit" name="submit_rating" class="submit-btn">Submit Review</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- My Reviews Tab -->
        <div id="my-reviews" class="tab-content">
            <h2>My Review History</h2>
            
            <?php if (empty($my_reviews)): ?>
                <div class="empty-state">
                    <h3>No reviews submitted yet</h3>
                    <p>When you rate laborers, your reviews will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($my_reviews as $review): ?>
                    <div class="review-history-item">
                        <div class="history-header">
                            <div class="history-title"><?php echo htmlspecialchars($review['job_title']); ?></div>
                            <div class="history-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></div>
                        </div>
                        
                        <div class="laborer-info">
                            <i class="fas fa-user-circle"></i>
                            <span class="laborer-name"><?php echo htmlspecialchars($review['laborer_name']); ?></span>
                        </div>
                        
                        <div class="history-rating">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $review['rating'] ? '<i class="fas fa-star" style="color: #ffb400;"></i>' : '<i class="far fa-star" style="color: #ddd;"></i>';
                            }
                            ?>
                        </div>
                        
                        <p><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                document.getElementById(this.getAttribute('data-tab')).classList.add('active');
            });
        });
        
        // Function to show edit form
        function showEditForm(jobId) {
            const formElement = document.getElementById('edit-form-' + jobId);
            formElement.style.display = formElement.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>