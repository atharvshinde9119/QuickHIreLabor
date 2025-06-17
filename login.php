<?php
require_once 'config.php';

$errors = [];
$setup_needed = false;

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect based on role
    if (isAdmin()) {
        header("Location: dashboard.php");
    } elseif (isCustomer()) {
        header("Location: c_dashboard.php");
    } elseif (isLaborer()) {
        header("Location: laborer_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

// Check if the database is properly set up
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'users'");
    if (!$table_check || $table_check->num_rows == 0) {
        $setup_needed = true;
        $errors[] = "Database tables not found. Please run the setup script first.";
    }
} catch (Exception $e) {
    $setup_needed = true;
    $errors[] = "Database error: " . $e->getMessage();
}

// Process form submission only if database is properly set up
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$setup_needed) {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    // Validate form data
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no errors, check login credentials
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id, first_name, last_name, password, role FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name']; // Combine first and last name
                    $_SESSION['role'] = $user['role'];
                    // Redirect based on role
                    if ($user['role'] == 'admin') {
                        header("Location: dashboard.php");
                    } elseif ($user['role'] == 'customer') {
                        header("Location: c_dashboard.php");
                    } elseif ($user['role'] == 'laborer') {
                        header("Location: laborer_dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                } else {
                    $errors[] = "Invalid email or password";
                }
            } else {
                $errors[] = "Invalid email or password";
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "Login error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Quick-Hire Labor</title> 
    <link rel="stylesheet" href="css/stylee.css">
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
    <link rel="stylesheet" type="text/css" href="css/font-awesome.min.css" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/responsive.css" rel="stylesheet" />
    <style>
      .setup-message {
        background-color: #f8d7da;
        color: #721c24;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid #f5c6cb;
        border-radius: 5px;
      }
      .setup-button {
        display: inline-block;
        background-color: #0355cc;
        color: white;
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 5px;
        margin-top: 10px;
      }
      .setup-button:hover {
        background-color: #0243a3;
        text-decoration: none;
        color: white;
      }
    </style>
  </head>
<body>
<div class="hero_area">
    <?php include 'includes/header.php'; ?>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav ">
        <li class="nav-item">
          <a class="nav-link" href="index.php">Home</a>
        </li>
        <li class="nav-item active">
          <a class="nav-link" href="aboutus.php">About us <span class="sr-only">(current)</span></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="services.php">Services</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="contact.php">Contact Us</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="newsletter.php">NewsLetter</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="faq.php">FAQs</a>
        </li>
      </ul>
    </div>
  </div>
  </header>
  <div class="container">
    <img src="images/slider-img.png" alt="" id="bg-img">
    
  <div class="wrapper">
    <h2>Login</h2>
    <?php if ($setup_needed): ?>
        <div class="setup-message">
            <h4>Database Setup Required</h4>
            <p>The application database has not been set up yet. Please run the setup script to create the necessary tables.</p>
            <a href="sql_setup.php" class="setup-button">Run Database Setup</a>
        </div>
    <?php elseif (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" <?php echo $setup_needed ? 'style="display:none;"' : ''; ?>>
      <div class="input-box">
        <input type="text" name="email" placeholder="Enter your email" required>
      </div>
      <div class="input-box">
        <input type="password" name="password" placeholder="Enter password" required>
      </div>
      
      <div class="policy">
        <input type="checkbox">
        <h3>Remember me</h3>
      </div>
      <div class="input-box button">
        <input type="submit" value="Login" style="background-color: #4CAF50; color: white; padding: 12px 20px; width: 100%; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
      </div>
      
      <div class="text">
        <center>
        <h3>Don't have an account? <a href="signup.php" style="color: #4CAF50; font-weight: bold;">Register now</a></h3>
        </center>
      </div>
    </form>
  </div>
</div>
</body>
</html>