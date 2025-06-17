<!DOCTYPE html>
<html>

<head>
  <!-- Basic -->
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <!-- Mobile Metas -->
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <!-- Site Metas -->
  <meta name="keywords" content="" />
  <meta name="description" content="" />
  <meta name="author" content="" />

  <title>Quick-Hire Labor</title>

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
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f8f9fa;
            color: #333;
        }
        h2 {
            text-align: center;
            color: #2a9d8f;
            margin-bottom: 10px;
        }
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
        }

        /* Hero Section */
        .hero {
            background: url('your-image.jpg') center/cover no-repeat;
            color: rgb(37, 13, 219);
            text-align: center;
            padding: 60px 20px;
        }
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .hero p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* Vision & Mission */
        .section {
            display: flex;
            align-items: center;
            background: white;
            padding: 40px;
            margin: 20px 0;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .section img {
            width: 40%;
            border-radius: 8px;
            margin-right: 20px;
        }
        .section div {
            width: 60%;
        }
        .section h2 {
            color: #264653;
        }
        .section p {
            font-size: 1.1rem;
            line-height: 1.6;
        }

        /* Why Choose Us */
        .why-choose {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            text-align: center;
        }
        .why-choose div {
            flex: 1;
            padding: 20px;
            min-width: 250px;
            background: #e9ecef;
            margin: 10px;
            border-radius: 8px;
            transition: 0.3s;
        }
        .why-choose div:hover {
            background: #2a9d8f;
            color: white;
        }

        /* Stats Section */
        .stats {
            display: flex;
            justify-content: space-between;
            text-align: center;
            background: #264653;
            color: white;
            padding: 30px 20px;
            border-radius: 8px;
        }
        .stats div {
            flex: 1;
        }
        .stats h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        /* Testimonials */
        .testimonials {
            
        
            width: 100%;
            text-align: center;
        }
        .testimonial-container {
          
            
            width: 200%;
        }
        .testimonial {
            width: 50%;
            padding: 20px;
            text-align: center;
            background: #e9ecef;
            border-radius: 8px;
            margin: 10px 0;
        }
        .testimonial img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .stats {
                flex-direction: column;
            }
            .why-choose {
                flex-direction: column;
            }
            .section {
                flex-direction: column;
                text-align: center;
            }
            .section img {
                width: 80%;
                margin-bottom: 15px;
            }
        }
    </style>

    <!-- Hero Section -->
    <div class="hero">
        <b><h1>About QuickHire Labor</h1>
        <p>Your trusted platform for hiring skilled laborers instantly.</p>
    </div>

    <!-- Vision -->
    <div class="container">
        <div class="section">
            <img src="images/vision.png" alt="Vision">
            <div>
                <h2>Our Vision</h2>
                <p>Empowering skilled laborers with digital solutions, bridging the gap between talent and demand, and ensuring every job is completed with efficiency and trust.</p>
            </div>
        </div>
    </div>

    <!-- Mission -->
    <div class="container">
        <div class="section">
            <div>
                <h2>Our Mission</h2>
                <p>To provide a seamless and secure platform where laborers and customers connect effortlessly, fostering economic growth and job opportunities.</p>
            </div>
            <img src="images/mission.png" alt="Mission">
        </div>
    </div>

    <!-- Why Choose Us -->
    <div class="container">
        <h2>Why Choose QuickHire?</h2>
        <div class="why-choose">
            <div>
                <h3>Fast & Easy</h3>
                <p>Post jobs and get matched with skilled laborers instantly.</p>
            </div>
            <div>
                <h3>Verified Professionals</h3>
                <p>All laborers are background-checked for reliability.</p>
            </div>
            <div>
                <h3>Secure Payments</h3>
                <p>Safe and transparent transactions with QR code payments.</p>
            </div>
        </div>
    </div>

    <!-- By the Numbers -->
    <div class="container">
        <h2>By the Numbers</h2>
        <div class="stats">
            <div>
                <h3>100+</h3>
                <p>Registered Laborers</p>
            </div>
            <div>
                <h3>500+</h3>
                <p>Jobs Completed</p>
            </div>
            <div>
                <h3>92%</h3>
                <p>Customer Satisfaction</p>
            </div>
        </div>
    </div>
<br>
    <!-- Customer Testimonials -->
    <!-- What Our Customers Say Section -->
<div class="container">
  <h2>What Our Customers Say</h2>
  <p style="text-align: center;">See how QuickHire customers are growing their businesses and getting incredible results.</p>
  
  <div class="testimonial-slider">
      <div class="testimonial-container">
          <!-- Testimonial 1 -->
          <div class="testimonial">
              <img src="images/client-1.jpg" alt="Customer">
              <p>“The biggest benefit of QuickHire is that all your data lives in it, you see the same customer information as the workers and vice versa. It gives us a new level of confidence.”</p>
              <h4>Pratik Shinde</h4>
              <p>National Sales Operations / E-Marketing Manager</p>
              <p>ARC Document Solutions</p>
          </div>

          <!-- Testimonial 2 -->
          <div class="testimonial">
              <img src="images/client-2.jpg" alt="Customer">
              <p>“QuickHire has transformed the way we connect with skilled laborers. The seamless process and verified workers make our job easier.”</p>
              <h4>ATHARV SHINDE</h4>
              <p>Project Manager</p>
              <p>BuildTech Solutions</p>
          </div>

          <!-- Testimonial 3 -->
          <div class="testimonial">
              <img src="images/aayan_pic.png" alt="Customer">
              <p>“With QuickHire, we can hire instantly and track job completion in real time. It’s the best platform for hiring labor.”</p>
              <h4>AAYAN MULLA</h4>
              <p>HR Manager</p>
              <p>FastFix Services</p>
          </div>
      </div>
  </div>

  
 
</div>

<!-- CSS for Testimonials -->
<style>
  .testimonial-slider {
      width: 100%;
      position: relative;
  }
  .testimonial-container {
      width: 100%;

  }
  .testimonial-container div:hover{

    background-color: aqua;

  }
  .testimonial {
      width: 100%;
      text-align: center;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }
  .testimonial img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      margin-bottom: 10px;
  }
  
  
</style>

<link rel="stylesheet" href="css/sticky-header.css">
<script src="js/sticky-header.js"></script>

</body>
</html>