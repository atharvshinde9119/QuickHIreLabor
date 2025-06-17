<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
$min_rating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'rating';

// Check the structure of ratings table first
$ratingsColumns = [];
$ratingsResult = $conn->query("SHOW COLUMNS FROM ratings");
if ($ratingsResult) {
    while ($row = $ratingsResult->fetch_assoc()) {
        $ratingsColumns[] = $row['Field'];
    }
}

// Build SQL query for laborers with the correct JOIN condition
$sql = "
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, 
           ls.max_distance, ls.min_pay, ls.is_available,
           COUNT(DISTINCT j.id) as completed_jobs,
           AVG(r.rating) as avg_rating,
           COUNT(DISTINCT r.id) as rating_count,
           GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as skills
    FROM users u
    LEFT JOIN laborer_settings ls ON u.id = ls.user_id
    LEFT JOIN jobs j ON u.id = j.laborer_id AND j.status = 'completed'";

// Use the correct column for joining with ratings based on what's in the database
if (in_array('laborer_id', $ratingsColumns)) {
    $sql .= " LEFT JOIN ratings r ON u.id = r.laborer_id";
} else {
    // If laborer_id doesn't exist, try with other potential column names
    if (in_array('user_id', $ratingsColumns)) {
        $sql .= " LEFT JOIN ratings r ON u.id = r.user_id";
    } else {
        // Fallback to a simple count with no join condition to avoid errors
        $sql .= " LEFT JOIN ratings r ON FALSE";
    }
}

$sql .= "
    LEFT JOIN laborer_skills lsk ON u.id = lsk.laborer_id
    LEFT JOIN skills s ON lsk.skill_id = s.id
    WHERE u.role = 'laborer' 
    AND (ls.is_available = 1 OR ls.is_available IS NULL)
";

// Add search filters
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = &$search_param;
    $params[] = &$search_param;
    $params[] = &$search_param;
    $types .= "sss";
}

if ($service_id > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM laborer_skills ls2 JOIN skills s2 ON ls2.skill_id = s2.id WHERE ls2.laborer_id = u.id AND s2.id = ?)";
    $params[] = &$service_id;
    $types .= "i";
}

// Group by user ID
$sql .= " GROUP BY u.id";

// Add minimum rating filter (after GROUP BY since it uses aggregate)
if ($min_rating > 0) {
    $sql .= " HAVING AVG(r.rating) >= ?";
    $params[] = &$min_rating;
    $types .= "i";
}

// Add sorting
switch ($sort_by) {
    case 'name':
        $sql .= " ORDER BY u.first_name, u.last_name";
        break;
    case 'experience':
        $sql .= " ORDER BY completed_jobs DESC";
        break;
    case 'rate_low':
        $sql .= " ORDER BY ls.min_pay ASC";
        break;
    case 'rate_high':
        $sql .= " ORDER BY ls.min_pay DESC";
        break;
    case 'rating':
    default:
        $sql .= " ORDER BY avg_rating DESC, rating_count DESC";
        break;
}

// Get all available services for filter dropdown
$services_query = "SELECT id, name FROM services ORDER BY name";
$services_result = $conn->query($services_query);
$services = [];
while ($service = $services_result->fetch_assoc()) {
    $services[] = $service;
}

// Prepare and execute the main query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$laborers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to generate star rating HTML
function generateStarRating($rating) {
    $rating = round($rating * 2) / 2; // Round to nearest 0.5
    $fullStars = floor($rating);
    $halfStar = $rating - $fullStars >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    $html = '';
    // Full stars
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star"></i>';
    }
    // Half star
    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt"></i>';
    }
    // Empty stars
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star"></i>';
    }
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Laborers - QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .submit-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .reset-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .laborer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .laborer-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .laborer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .laborer-header {
            position: relative;
            padding: 20px;
            background: #f8f9fa;
            text-align: center;
        }
        
        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        
        .laborer-name {
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0 5px;
        }
        
        .laborer-rating {
            color: #ffc107;
            margin-bottom: 5px;
        }
        
        .rating-count {
            color: #6c757d;
            font-size: 14px;
        }
        
        .availability-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .laborer-body {
            padding: 15px;
        }
        
        .laborer-info {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            min-width: 120px;
            font-weight: bold;
            color: #555;
        }
        
        .info-value {
            flex: 1;
        }
        
        .skills-list {
            margin-top: 10px;
        }
        
        .skill-tag {
            display: inline-block;
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 4px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 12px;
            color: #495057;
        }
        
        .laborer-footer {
            padding: 15px;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .view-profile-btn {
            background: #4CAF50;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        
        .view-profile-btn:hover {
            background: #45a049;
        }
        
        .hire-now-btn {
            background: #007bff;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        
        .hire-now-btn:hover {
            background: #0069d9;
        }
        
        .empty-state {
            background: white;
            padding: 40px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .search-bar {
            display: flex;
            margin-bottom: 20px;
            max-width: 600px;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            font-size: 16px;
        }
        
        .search-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 600px;
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #555;
        }
        
        /* Form styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-hire-submit {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-hire-submit:hover {
            background: #0069d9;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Browse Laborers</h1>
        </div>
        
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Search by name or skill" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="service_id">Service</label>
                        <select id="service_id" name="service_id">
                            <option value="0">All Services</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" <?php echo $service_id == $service['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="min_rating">Minimum Rating</label>
                        <select id="min_rating" name="min_rating">
                            <option value="0" <?php echo $min_rating == 0 ? 'selected' : ''; ?>>Any Rating</option>
                            <option value="3" <?php echo $min_rating == 3 ? 'selected' : ''; ?>>3+ Stars</option>
                            <option value="4" <?php echo $min_rating == 4 ? 'selected' : ''; ?>>4+ Stars</option>
                            <option value="5" <?php echo $min_rating == 5 ? 'selected' : ''; ?>>5 Stars</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort_by">Sort By</label>
                        <select id="sort_by" name="sort_by">
                            <option value="rating" <?php echo $sort_by == 'rating' ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="experience" <?php echo $sort_by == 'experience' ? 'selected' : ''; ?>>Most Experience</option>
                            <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="rate_low" <?php echo $sort_by == 'rate_low' ? 'selected' : ''; ?>>Rate (Low to High)</option>
                            <option value="rate_high" <?php echo $sort_by == 'rate_high' ? 'selected' : ''; ?>>Rate (High to Low)</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <button type="submit" class="submit-btn">Apply Filters</button>
                    <a href="browse_laborers.php" class="reset-btn">Reset</a>
                </div>
            </form>
        </div>
        
        <?php if (empty($laborers)): ?>
            <div class="empty-state">
                <i class="fas fa-user-hard-hat"></i>
                <h3>No laborers found</h3>
                <p>Try adjusting your filters or search criteria</p>
            </div>
        <?php else: ?>
            <div class="laborer-grid">
                <?php foreach ($laborers as $laborer): ?>
                    <div class="laborer-card">
                        <div class="laborer-header">
                            
                            <h3 class="laborer-name">
                                <?php echo htmlspecialchars($laborer['first_name'] . ' ' . $laborer['last_name']); ?>
                            </h3>
                            <div class="laborer-rating">
                                <?php 
                                $rating = floatval($laborer['avg_rating']) ?: 0;
                                echo generateStarRating($rating);
                                ?>
                                <span class="rating-count">
                                    (<?php echo number_format($rating, 1); ?>/5 from <?php echo $laborer['rating_count']; ?> reviews)
                                </span>
                            </div>
                            <span class="availability-badge">Available</span>
                        </div>
                        
                        <div class="laborer-body">
                            <div class="laborer-info">
                                <div class="info-row">
                                    <div class="info-label">Completed Jobs:</div>
                                    <div class="info-value"><?php echo $laborer['completed_jobs']; ?></div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Rate:</div>
                                    <div class="info-value">
                                        <?php echo !empty($laborer['min_pay']) ? '₹' . $laborer['min_pay'] . '/hour' : 'Negotiable'; ?>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">Max Distance:</div>
                                    <div class="info-value">
                                        <?php echo !empty($laborer['max_distance']) ? $laborer['max_distance'] . ' km' : 'Not specified'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="skills-section">
                                <strong>Skills:</strong>
                                <div class="skills-list">
                                    <?php if (!empty($laborer['skills'])): ?>
                                        <?php foreach (explode(', ', $laborer['skills']) as $skill): ?>
                                            <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="skill-tag">No skills listed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="laborer-footer">
                            <button class="hire-now-btn" onclick="openHireForm(<?php echo $laborer['id']; ?>, '<?php echo htmlspecialchars(addslashes($laborer['first_name'] . ' ' . $laborer['last_name'])); ?>')">Hire Now</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
<div id="hireModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeHireModal()">&times;</span>
        <h2>Submit Job Request</h2>
        <p>You are hiring <span id="laborerName"></span></p>
        
        <form id="hireForm" method="POST" action="submit_job.php">
            <input type="hidden" id="laborer_id" name="laborer_id">
            
            <div class="form-group">
                <label for="job_title">Job Title*</label>
                <input type="text" id="job_title" name="job_title" required>
            </div>
            
            <div class="form-group">
                <label for="job_description">Job Description*</label>
                <textarea id="job_description" name="job_description" rows="4" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="job_location">Location*</label>
                <input type="text" id="job_location" name="job_location" required>
            </div>
            
            <div class="form-group">
                <label for="job_budget">Budget (₹)*</label>
                <input type="number" id="job_budget" name="job_budget" min="0" step="1" required>
            </div>
            
            <div class="form-group">
                <label for="service_id">Service Category*</label>
                <select id="service_id" name="service_id" required>
                    <option value="">Select a service</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service['id']; ?>">
                            <?php echo htmlspecialchars($service['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-hire-submit">Submit for Approval</button>
                <button type="button" class="btn-cancel" onclick="closeHireModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openHireForm(laborerId, laborerName) {
        document.getElementById('laborerName').textContent = laborerName;
        document.getElementById('laborer_id').value = laborerId;
        document.getElementById('hireModal').style.display = 'block';
    }
    
    function closeHireModal() {
        document.getElementById('hireModal').style.display = 'none';
        document.getElementById('hireForm').reset();
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('hireModal');
        if (event.target === modal) {
            closeHireModal();
        }
    }
    
    // Form validation
    document.getElementById('hireForm').addEventListener('submit', function(e) {
        const budget = document.getElementById('job_budget').value;
        
        // Get min and max values from platform settings (you can fetch these from an API)
        const minBudget = 500; // Default minimum value
        const maxBudget = 50000; // Default maximum value
        
        if (budget < minBudget || budget > maxBudget) {
            e.preventDefault();
            alert(`Budget must be between ₹${minBudget} and ₹${maxBudget}`);
            return false;
        }
    });
</script>
</html>
