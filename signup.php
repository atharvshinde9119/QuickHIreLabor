<?php
require_once 'config.php';

$errors = [];
$success = false;
$error_fields = [];

// Store input values to repopulate form
$input = [
    'name' => '',
    'last_name' => '',
    'email' => '',
    'mobile' => '',
    'role' => 'customer'
];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $input['name'] = sanitize_input($_POST['name']);
    $input['last_name'] = sanitize_input($_POST['last_name']);
    $input['email'] = sanitize_input($_POST['email']);
    $input['mobile'] = sanitize_input($_POST['mobile']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $input['role'] = isset($_POST['role']) ? sanitize_input($_POST['role']) : 'customer';
    
    // Validate form data
    if (empty($input['name'])) {
        $errors[] = "First name is required";
        $error_fields[] = 'name';
    }
    
    if (empty($input['last_name'])) {
        $errors[] = "Last name is required";
        $error_fields[] = 'last_name';
    }
    
    if (empty($input['email'])) {
        $errors[] = "Email is required";
        $error_fields[] = 'email';
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
        $error_fields[] = 'email';
    }
    
    if (empty($input['mobile'])) {
        $errors[] = "Mobile number is required";
        $error_fields[] = 'mobile';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $input['mobile'])) {
        $errors[] = "Invalid mobile number format";
        $error_fields[] = 'mobile';
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
        $error_fields[] = 'password';
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
        $error_fields[] = 'password';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
        $error_fields[] = 'confirm_password';
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $input['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email already exists";
        $error_fields[] = 'email';
    }
    
    // If no errors, insert user into database
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $input['name'], $input['last_name'], $input['email'], $input['mobile'], $hashed_password, $input['role']);
        
        if ($stmt->execute()) {
            // Don't automatically log in the user
            // Instead, store a registration success message in session
            $_SESSION['registration_success'] = "Registration successful! Please log in with your email and password.";
            
            // Redirect to login page
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Error: " . $stmt->error;
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" width="device-width, initial-scale=1.0">
    <title>Registration - Quick-Hire Labor</title> 
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
    <link rel="stylesheet" type="text/css" href="css/font-awesome.min.css" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/responsive.css" rel="stylesheet" />
    <style>
      .form-control.error {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
      }
      .error-message {
        color: #dc3545;
        font-size: 12px;
        margin-top: 5px;
      }
    </style>
  </head>
<body>
  <div class="hero_area">
    <?php include 'includes/header.php'; ?>
  </div>
  
  <div class="registration-container">
    <img src="images/signup-bg.png" alt="Background" class="bg-image">
    
    <div class="registration-wrapper">
      <h2>Create Your Account</h2>
      
      <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
              <ul>
                  <?php foreach ($errors as $error): ?>
                      <li><?php echo $error; ?></li>
                  <?php endforeach; ?>
              </ul>
          </div>
      <?php endif; ?>
      
      <?php if ($success): ?>
          <div class="alert alert-success">
              Registration successful! You are now logged in.
          </div>
      <?php endif; ?>
      
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
        <div class="form-group">
          
          <input type="text" name="name" class="form-control <?php echo in_array('name', $error_fields) ? 'error' : ''; ?>" 
                 placeholder="First Name" value="<?php echo htmlspecialchars($input['name']); ?>" required>
        </div>
        <div class="form-group">
          <input type="text" name="last_name" class="form-control <?php echo in_array('last_name', $error_fields) ? 'error' : ''; ?>" 
                 placeholder="Last Name" value="<?php echo htmlspecialchars($input['last_name']); ?>" required>
        </div>
        <div class="form-group">
          <input type="email" name="email" class="form-control <?php echo in_array('email', $error_fields) ? 'error' : ''; ?>" 
                 placeholder="Email Address" value="<?php echo htmlspecialchars($input['email']); ?>" required>
        </div>
        <div class="form-group">
          <input type="text" name="mobile" class="form-control <?php echo in_array('mobile', $error_fields) ? 'error' : ''; ?>" 
                 placeholder="Mobile Number" value="<?php echo htmlspecialchars($input['mobile']); ?>" required>
        </div>
        <div class="form-group">
          <input type="password" name="password" class="form-control <?php echo in_array('password', $error_fields) ? 'error' : ''; ?>" 
                 placeholder="Create Password" required>
        </div>
        <div class="form-group">
          <input type="password" name="confirm_password" class="form-control <?php echo in_array('confirm_password', $error_fields) ? 'error' : ''; ?>" 
                 placeholder="Confirm Password" required>
        </div>
        <div class="form-group">
          <label class="select-label">Select Account Type</label>
          <select name="role" class="role-select">
            <option value="customer" <?php echo $input['role'] == 'customer' ? 'selected' : ''; ?>>I need services (Customer)</option>
            <option value="laborer" <?php echo $input['role'] == 'laborer' ? 'selected' : ''; ?>>I provide services (Laborer)</option>
          </select>
        </div>
        <div class="policy-check">
          <input type="checkbox" id="terms" name="terms" required>
          <h3>I accept all <a href="terms.php">terms & conditions</a></h3>
        </div>
        <button type="submit" class="btn-register">Create Account</button>
        <div class="login-link">
          <h3>Already have an account? <a href="login.php">Login now</a></h3>
        </div>
      </form>
    </div>
  </div>
  
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.js"></script>
  <script src="js/custom.js"></script>
</body>
</html>