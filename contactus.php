<?php
require_once 'config.php';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
    $message = sanitize_input($_POST['message']);
    
    // Set form type as 'contact' for submissions from contact page
    $form_type = 'contact';
    
    // Check if form_type column exists in contacts table
    $column_check = $conn->query("SHOW COLUMNS FROM contacts LIKE 'form_type'");
    
    if ($column_check->num_rows > 0) {
        // If form_type column exists, include it in the query
        $stmt = $conn->prepare("INSERT INTO contacts (form_type, name, email, phone, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $form_type, $name, $email, $phone, $message);
    } else {
        // If form_type column doesn't exist, use the original schema
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, phone, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $phone, $message);
    }
    
    if ($stmt->execute()) {
        $success_message = "Thank you for contacting us! We'll get back to you soon.";
    } else {
        $error_message = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
  <!-- Basic -->
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <!-- Mobile Metas -->
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <!-- Site Metas -->
  <meta name="keywords" content="contact, construction services, labor hire, quick hire labor" />
  <meta name="description" content="Get in touch with QuickHire Labor for your construction and home service needs. Our team is ready to assist you." />
  <meta name="author" content="QuickHire Labor" />

  <title>Contact Us - QuickHire Labor</title>

  <!-- slider stylesheet -->
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" />
  <!-- bootstrap core css -->
  <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
  <!-- font awesome style -->
  <link rel="stylesheet" type="text/css" href="css/font-awesome.min.css" />

  <!-- Custom styles for this template -->
  <link href="css/style.css" rel="stylesheet" />
  <!-- responsive style -->
  <link href="css/responsive.css" rel="stylesheet" />

  <style>
    /* Contact Hero Section */
    .contact-hero {
        background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('images/contact-hero-bg.jpg');
        background-size: cover;
        background-position: center;
        color: white;
        padding: 80px 0;
        text-align: center;
        margin-bottom: 40px;
        border-radius: 10px;
    }
    
    .contact-hero h1 {
        font-size: 48px;
        margin-bottom: 15px;
        font-weight: 700;
    }
    
    .contact-hero p {
        font-size: 18px;
        max-width: 700px;
        margin: 0 auto;
        line-height: 1.6;
    }
    
    /* Breadcrumb */
    .breadcrumb-container {
        background-color: #f8f9fa;
        padding: 10px 20px;
        border-radius: 5px;
        margin-bottom: 30px;
    }
    
    .breadcrumb {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .breadcrumb li {
        display: inline;
        margin-right: 10px;
    }
    
    .breadcrumb li:after {
        content: '>';
        margin-left: 10px;
        color: #6c757d;
    }
    
    .breadcrumb li:last-child:after {
        content: '';
    }
    
    .breadcrumb a {
        color: #007bff;
        text-decoration: none;
    }
    
    .breadcrumb .active {
        color: #6c757d;
    }
    
    /* Contact Section Layout */
    .contact-section {
        padding: 50px 0;
        background: #f8f9fa;
    }

    .contact-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 40px;
    }
    
    @media (max-width: 768px) {
        .contact-container {
            grid-template-columns: 1fr;
        }
    }
    
    /* Contact Form Styling */
    .contact-form {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }
    
    .contact-form:hover {
        transform: translateY(-5px);
    }
    
    .contact-form h3 {
        margin-bottom: 20px;
        color: #333;
        font-weight: 600;
        font-size: 24px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #555;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 15px;
        transition: border 0.3s ease, box-shadow 0.3s ease;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
        outline: none;
    }
    
    .form-group textarea {
        height: 120px;
        resize: vertical;
    }
    
    .form-group button {
        background: #4CAF50;
        color: white;
        border: none;
        padding: 14px 24px;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s ease;
        width: 100%;
    }
    
    .form-group button:hover {
        background: #3d8b40;
    }
    
    /* Contact Info Styling */
    .contact-info {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .contact-info h3 {
        margin-bottom: 25px;
        color: #333;
        font-weight: 600;
        font-size: 24px;
    }
    
    .info-item {
        display: flex;
        margin-bottom: 25px;
        align-items: flex-start;
    }
    
    .info-item i {
        font-size: 22px;
        color: #4CAF50;
        margin-right: 15px;
        width: 25px;
        text-align: center;
        margin-top: 5px;
    }
    
    .info-content h4 {
        font-size: 18px;
        margin-bottom: 5px;
        color: #333;
    }
    
    .info-content p {
        color: #666;
        line-height: 1.6;
        margin: 0;
    }
    
    /* Business Hours */
    .business-hours {
        margin-top: 30px;
        border-top: 1px solid #eee;
        padding-top: 20px;
    }
    
    .business-hours h4 {
        margin-bottom: 15px;
        font-size: 18px;
        color: #333;
    }
    
    .hours-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    
    .hours-item span {
        color: #666;
    }
    
    /* Map Container */
    .map_container {
        height: 300px;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .map {
        height: 100%;
    }
    
    /* FAQ Section */
    .contact-faq {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-top: 40px;
    }
    
    .contact-faq h3 {
        margin-bottom: 25px;
        color: #333;
        font-weight: 600;
        text-align: center;
    }
    
    .faq-item {
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
        padding-bottom: 20px;
    }
    
    .faq-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    
    .faq-question {
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
        font-size: 18px;
    }
    
    .faq-answer {
        color: #666;
        line-height: 1.6;
    }
    
    /* Social Media Links */
    .social-links {
        display: flex;
        margin-top: 20px;
    }
    
    .social-links a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: #f5f5f5;
        border-radius: 50%;
        margin-right: 10px;
        color: #333;
        transition: all 0.3s ease;
    }
    
    .social-links a:hover {
        background: #4CAF50;
        color: white;
        transform: translateY(-3px);
    }
    
    /* Alert Styles */
    .alert {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border: 1px solid transparent;
    }
    
    .alert-success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }
  </style>
</head>

<body>
  <div class="hero_area">
    <?php include 'includes/header.php'; ?>
  </div>

  <!-- Breadcrumb navigation -->
  <div class="container breadcrumb-container">
    <ul class="breadcrumb">
      <li><a href="index.php">Home</a></li>
      <li class="active">Contact Us</li>
    </ul>
  </div>

  <!-- Contact Hero Section -->
  <div class="container">
    <div class="contact-hero">
      <h1>Get In Touch With Us</h1>
      <p>Have questions or need assistance? We're here to help with all your labor and service needs. Reach out to our friendly team today.</p>
    </div>
  </div>

  <section class="contact-section">
    <div class="container">
      <div class="contact-container">
        <div class="contact-form">
          <h3>Send Us a Message</h3>
          
          <?php if (isset($success_message)): ?>
              <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
              </div>
          <?php endif; ?>
          
          <?php if (isset($error_message)): ?>
              <div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> <?php echo $error_message; ?>
              </div>
          <?php endif; ?>

          <form method="POST" action="">
              <div class="form-group">
                  <label for="name"><i class="fa fa-user"></i> Your Name</label>
                  <input type="text" id="name" name="name" placeholder="Enter your full name" required>
              </div>
              
              <div class="form-group">
                  <label for="email"><i class="fa fa-envelope"></i> Your Email</label>
                  <input type="email" id="email" name="email" placeholder="Enter your email address" required>
              </div>
              
              <div class="form-group">
                  <label for="phone"><i class="fa fa-phone"></i> Your Phone</label>
                  <input type="text" id="phone" name="phone" placeholder="Enter your phone number (optional)">
              </div>
              
              <div class="form-group">
                  <label for="message"><i class="fa fa-comment"></i> Your Message</label>
                  <textarea id="message" name="message" placeholder="What can we help you with?" rows="5" required></textarea>
              </div>
              
              <div class="form-group">
                  <button type="submit" name="contact_submit">
                    <i class="fa fa-paper-plane"></i> Send Message
                  </button>
              </div>
          </form>
        </div>
        
        <div class="contact-info">
          <h3>Contact Information</h3>
          
          <div class="info-item">
              <i class="fa fa-map-marker"></i>
              <div class="info-content">
                <h4>Our Location</h4>
                <p>123 Labor Street, Construction District<br>Miraj, Maharashtra 416410<br>India</p>
              </div>
          </div>
          
          <div class="info-item">
              <i class="fa fa-phone"></i>
              <div class="info-content">
                <h4>Phone Number</h4>
                <p>+91 1234 567 890</p>
                <p>Toll Free: 1800 123 4567</p>
              </div>
          </div>
          
          <div class="info-item">
              <i class="fa fa-envelope"></i>
              <div class="info-content">
                <h4>Email Address</h4>
                <p>info@quickhirelabor.com</p>
                <p>support@quickhirelabor.com</p>
              </div>
          </div>
          
          <div class="business-hours">
            <h4><i class="fa fa-clock-o"></i> Business Hours</h4>
            <div class="hours-item">
              <span>Monday - Friday:</span>
              <span>9:00 AM - 6:00 PM</span>
            </div>
            <div class="hours-item">
              <span>Saturday:</span>
              <span>10:00 AM - 4:00 PM</span>
            </div>
            <div class="hours-item">
              <span>Sunday:</span>
              <span>Closed</span>
            </div>
          </div>
          
          <div class="social-links">
            <a href="#" title="Facebook"><i class="fa fa-facebook"></i></a>
            <a href="#" title="Twitter"><i class="fa fa-twitter"></i></a>
            <a href="#" title="Instagram"><i class="fa fa-instagram"></i></a>
            <a href="#" title="LinkedIn"><i class="fa fa-linkedin"></i></a>
          </div>
        </div>
      </div>
      
      <!-- Map Section -->
      <div class="map_container">
        <div class="map">
          <div id="googleMap" style="width:100%;height:100%;"></div>
        </div>
      </div>
      
      <!-- FAQ Section -->
      <div class="contact-faq">
        <h3>Frequently Asked Questions</h3>
        
        <div class="faq-item">
          <div class="faq-question">What is the typical response time for inquiries?</div>
          <div class="faq-answer">We typically respond to all inquiries within 24 hours during business days. For urgent matters, we recommend calling our customer support line.</div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question">Can I request a quote through the contact form?</div>
          <div class="faq-answer">Yes, you can request a quote by providing details about your project in the message field. Our team will review your requirements and get back to you with an estimate.</div>
        </div>
        
        <div class="faq-item">
          <div class="faq-question">How do I report an issue with a service?</div>
          <div class="faq-answer">If you've experienced any issues with our services, please mention your order/job number in the contact form, and describe the problem in detail. Our customer support team will address your concerns promptly.</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Add Font Awesome for Icons -->
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="css/sticky-header.css">
  <script src="js/sticky-header.js"></script>
  
  <!-- Google Map -->
  <script>
    function myMap() {
      // Create a custom map marker
      const customMarker = {
        url: 'images/map-marker.png',
        size: new google.maps.Size(40, 60),
        origin: new google.maps.Point(0, 0),
        anchor: new google.maps.Point(20, 60),
        scaledSize: new google.maps.Size(40, 60)
      };
      
      // Set map properties
      var mapProp = {
        center: new google.maps.LatLng(16.8302, 74.6539), // Coordinates for Miraj, Maharashtra
        zoom: 15,
        mapTypeId: google.maps.MapTypeId.ROADMAP
      };
      
      // Create the map
      var map = new google.maps.Map(document.getElementById("googleMap"), mapProp);
      
      // Add a marker with info window
      var marker = new google.maps.Marker({
        position: new google.maps.LatLng(16.8302, 74.6539),
        map: map,
        title: 'QuickHire Labor Headquarters',
        animation: google.maps.Animation.DROP
      });
      
      // Info window content
      var infoContent = '<div style="width:250px;padding:10px;"><h5 style="margin-top:0;color:#4CAF50;">QuickHire Labor</h5><p style="margin-bottom:5px;">123 Labor Street, Construction District<br>Miraj, Maharashtra 416410</p><a href="https://goo.gl/maps/123" target="_blank" style="color:#4CAF50;">Get Directions</a></div>';
      
      // Create info window
      var infowindow = new google.maps.InfoWindow({
        content: infoContent
      });
      
      // Open info window when marker is clicked
      marker.addListener('click', function() {
        infowindow.open(map, marker);
      });
      
      // Open info window by default
      infowindow.open(map, marker);
    }
  </script>
  
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCh39n5U-4IoWpsVGUHWdqB6puEkhRLdmI&callback=myMap"></script>
  <!-- End Google Map -->

</body>

</html>
