<?php
require_once 'config.php';

// Check if user is logged in and is a laborer
if (!isLoggedIn() || !isLaborer()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user profile data - Remove u.bio and u.status from the query
$stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
           u.created_at, u.role,
           ls.max_distance, ls.min_pay, ls.is_available
    FROM users u
    LEFT JOIN laborer_settings ls ON u.id = ls.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get laborer skills
$skills = [];
try {
    $skillsQuery = $conn->query("SHOW TABLES LIKE 'laborer_skills'");
    if ($skillsQuery->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT s.id, s.name
            FROM skills s
            JOIN laborer_skills ls ON s.id = ls.skill_id
            WHERE ls.laborer_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $skillsResult = $stmt->get_result();
            while ($skill = $skillsResult->fetch_assoc()) {
                $skills[] = $skill;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error retrieving skills: " . $e->getMessage());
}

// Get available skills for selection
$available_skills = [];
try {
    $stmt = $conn->prepare("SELECT id, name FROM skills ORDER BY name");
    $stmt->execute();
    $skillsResult = $stmt->get_result();
    while ($skill = $skillsResult->fetch_assoc()) {
        $available_skills[] = $skill;
    }
} catch (Exception $e) {
    error_log("Error retrieving available skills: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $phone = sanitize_input($_POST['phone']);
    $bio = sanitize_input($_POST['bio'] ?? '');
    $new_skills = isset($_POST['skills']) ? $_POST['skills'] : [];
    
    // Validation
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update user
            $stmt = $conn->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, phone = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssi", $first_name, $last_name, $phone, $user_id);
            $stmt->execute();
            
            // Update skills
            // First, remove all current skills
            $stmt = $conn->prepare("DELETE FROM laborer_skills WHERE laborer_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Then add selected skills
            if (!empty($new_skills)) {
                $stmt = $conn->prepare("INSERT INTO laborer_skills (laborer_id, skill_id) VALUES (?, ?)");
                foreach ($new_skills as $skill_id) {
                    $stmt->bind_param("ii", $user_id, $skill_id);
                    $stmt->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Profile updated successfully!";
            header("Location: laborer_profile.php");
            exit();
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | QuickHire Labor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container {
            max-width: 800px;
            margin-left: 280px;
            padding: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #4CAF50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
        }
        
        .profile-info h1 {
            margin: 0 0 5px 0;
        }
        
        .profile-info p {
            margin: 0;
            color: #666;
        }
        
        .profile-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .profile-card-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .profile-card-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .profile-card-header .edit-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .profile-card-body {
            padding: 20px;
        }
        
        .profile-field {
            margin-bottom: 15px;
        }
        
        .profile-field-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        
        .profile-field-value {
            color: #333;
        }
        
        .bio {
            white-space: pre-line;
        }
        
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .skill-tag {
            background: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group select[multiple] {
            min-height: 150px;
        }
        
        .submit-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-left: 10px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/laborer_sidebar.php'; ?>

    <div class="container">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <p>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>
        
        <div class="profile-card">
            <div class="profile-card-header">
                <h3>Basic Information</h3>
                <button class="edit-btn" onclick="toggleEditBasicInfo()">Edit</button>
            </div>
            <div class="profile-card-body">
                <div id="basicInfoDisplay">
                    <div class="profile-field">
                        <div class="profile-field-label">Phone</div>
                        <div class="profile-field-value"><?php echo htmlspecialchars($user['phone']); ?></div>
                    </div>
                    
                    <div class="profile-field">
                        <div class="profile-field-label">About Me</div>
                        <div class="profile-field-value bio">No bio available.</div>
                    </div>
                </div>
                
                <div id="basicInfoEdit" style="display: none;">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">About Me</label>
                            <textarea id="bio" name="bio"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="skills">Skills</label>
                            <select id="skills" name="skills[]" multiple>
                                <?php foreach($available_skills as $skill): ?>
                                    <option value="<?php echo $skill['id']; ?>" 
                                        <?php 
                                        foreach($skills as $user_skill) {
                                            if($user_skill['id'] === $skill['id']) {
                                                echo 'selected';
                                                break;
                                            }
                                        }
                                        ?>
                                    >
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Hold Ctrl (or Cmd on Mac) to select multiple skills</small>
                        </div>
                        
                        <button type="submit" class="submit-btn">Save Changes</button>
                        <button type="button" class="cancel-btn" onclick="toggleEditBasicInfo()">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="profile-card">
            <div class="profile-card-header">
                <h3>Skills & Preferences</h3>
            </div>
            <div class="profile-card-body">
                <div class="profile-field">
                    <div class="profile-field-label">Available for Work</div>
                    <div class="profile-field-value">
                        <?php echo isset($user['is_available']) && $user['is_available'] ? 'Yes' : 'No'; ?>
                    </div>
                </div>
                
                <div class="profile-field">
                    <div class="profile-field-label">Maximum Travel Distance</div>
                    <div class="profile-field-value">
                        <?php echo isset($user['max_distance']) ? $user['max_distance'] . ' km' : 'Not specified'; ?>
                    </div>
                </div>
                
                <div class="profile-field">
                    <div class="profile-field-label">Minimum Pay Rate</div>
                    <div class="profile-field-value">
                        <?php echo isset($user['min_pay']) ? '₹' . $user['min_pay'] . '/hour' : 'Not specified'; ?>
                    </div>
                </div>
                
                <div class="profile-field">
                    <div class="profile-field-label">Skills</div>
                    <div class="profile-field-value">
                        <?php if(empty($skills)): ?>
                            No skills specified.
                        <?php else: ?>
                            <div class="skills-list">
                                <?php foreach($skills as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill['name']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="laborer_settings.php" class="edit-btn" style="text-decoration: none;">Update Preferences</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleEditBasicInfo() {
            const displayElement = document.getElementById('basicInfoDisplay');
            const editElement = document.getElementById('basicInfoEdit');
            
            if (displayElement.style.display === 'none') {
                displayElement.style.display = 'block';
                editElement.style.display = 'none';
            } else {
                displayElement.style.display = 'none';
                editElement.style.display = 'block';
            }
        }
    </script>
</body>
</html>