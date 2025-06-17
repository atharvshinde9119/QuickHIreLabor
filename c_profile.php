<?php
require_once 'config.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || !isCustomer()) {
    header("Location: login.php");
    exit();
}

// Initialize response array
$response = ['success' => false, 'message' => ''];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = $_SESSION['user_id'];
        $name = sanitize_input($_POST['fullName']);
        $phone = sanitize_input($_POST['phone']);
        $address = sanitize_input($_POST['address']);
        
        // Start transaction
        $conn->begin_transaction();
        
        $sql = "UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $name, $phone, $address, $user_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully';
        } else {
            throw new Exception('Failed to update profile');
        }
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode($response);
        exit();
    }
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.querySelector('form');
    profileForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('c_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the profile');
        });
    });
});
</script>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Profile</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .container {
      max-width: 900px;
      margin-left: 280px; /* Changed from margin: auto */
      background: #ffffff;
      padding: 20px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      border-radius: 10px;
    }
    h2 {
      text-align: center;
      color: #edf4ed;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      font-size: 16px;
      color: #333;
      margin-bottom: 8px;
      display: block;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px;
      font-size: 16px;
      border: 2px solid #ddd;
      border-radius: 5px;
      box-sizing: border-box;
    }

    .form-group input[type="file"] {
      padding: 5px;
      background-color: #f5f5f5;
      color: #333;
    }

    .form-group .upload-btn {
      background-color: #4CAF50;
      color: white;
      padding: 10px 15px;
      border-radius: 5px;
      cursor: pointer;
      border: none;
      transition: background-color 0.3s;
    }

    .form-group .upload-btn:hover {
      background-color: #45a049;
    }

    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }

    .form-group input[type="submit"] {
      background-color: #4CAF50;
      color: white;
      padding: 15px;
      font-size: 16px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      width: 100%;
      margin-top: 20px;
      transition: background-color 0.3s;
    }

    .form-group input[type="submit"]:hover {
      background-color: #45a049;
    }

    .form-group .info-text {
      font-size: 14px;
      color: #777;
      margin-top: 5px;
    }

    .profile-picture {
      display: flex;
      justify-content: center;
      margin-bottom: 20px;
    }

    .profile-picture img {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
    }

    .header {
      background-color: #4CAF50;
      color: white;
      padding: 15px;
      text-align: center;
      border-radius: 10px 10px 0 0;
    }

    .footer {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: #888;
    }

    .footer a {
      color: #4CAF50;
      text-decoration: none;
    }

    .footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
    <?php include 'includes/customer_sidebar.php'; ?>

    <div class="container">
      <div class="header">
        <h2>Customer Profile</h2>
      </div>
      <form action="#" method="post" enctype="multipart/form-data">
  
        <div class="form-group">
          <label for="fullName">Full Name</label>
          <input type="text" id="fullName" name="fullName" required placeholder="Enter your full name" value="<?php echo isset($user['name']) ? htmlspecialchars($user['name']) : ''; ?>">
        </div>
  
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required placeholder="Enter your email" value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" readonly>
        </div>
  
        <div class="form-group">
          <label for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" required placeholder="Enter your phone number" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>">
        </div>
  
        <div class="form-group">
          <label for="address">Address</label>
          <textarea id="address" name="address" placeholder="Enter your address" required><?php echo isset($user['address']) ? htmlspecialchars($user['address']) : ''; ?></textarea>
        </div>
  
        <div class="form-group">
          <label for="services">Services Required</label>
          <select id="services" name="services" required>
            <option value="Cleaning">Cleaning</option>
            <option value="Plumbing">Plumbing</option>
            <option value="Electrical">Electrical</option>
            <option value="Painting">Painting</option>
            <option value="Carpentry">Carpentry</option>
          </select>
        </div>
  
        <div class="form-group">
          <label for="paymentDetails">Payment Information</label>
          <textarea id="paymentDetails" name="paymentDetails" placeholder="Enter your payment details" required></textarea>
        </div>
  
        <div class="form-group">
          <label for="otherRequirements">Other Requirements</label>
          <textarea id="otherRequirements" name="otherRequirements" placeholder="Enter any other requirements or preferences"></textarea>
        </div>
  
        <div class="form-group">
          <input type="submit" value="Save Profile">
        </div>
      </form>
      <div class="footer">
        <p>Need help? <a href="c_support.php">Contact Support</a></p>
      </div>
    </div>
</body>
</html>