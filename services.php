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
  </div>
    <style>
        /* Global Styles */
        
        /* Home Renovation Services Section */
        .service-section {
            padding: 80px 20px;
            text-align: center;
            background-color: #fff;
        }
        .service-section h2 {
            font-size: 3.5rem;
            color: #333;
            margin-bottom: 40px;
            font-weight: bold;
        }

        /* Service Cards Layout */
        .services {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            justify-content: center;
            margin: 0 auto;
        }

        /* Service Card Styling */
        .service-card {
            background-color: #f9f9f9; /* Light background color */
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease-in-out;
            text-align: center;
            cursor: pointer;
        }

        /* Hover Effect */
        .service-card:hover {
            background-color: #FF9800; /* Change to orange on hover */
            transform: scale(1.05);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease-in-out;
        }

        .service-card img {
            width: 50%;
            height: 170px;
            object-fit: cover;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .service-card:hover img {
            transform: scale(1.1);
        }

        .service-card h3 {
            font-size: 2.5rem;
            color: #000;
            margin: 20px 0;
            font-weight: bold;
        }

        .service-card p {
            font-size: 1.2rem;
            color: #555;
            margin-bottom: 20px;
        }

        .cta-button {
            background-color: #FF9800;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 30px;
            font-size: 1.3rem;
            transition: background-color 0.3s ease;
        }

        .cta-button:hover {
            background-color: #e68900;
        }

        /* Call to Action Section */
        .cta-section {
            background-color: #f7f7f7;
            padding: 80px;
            text-align: center;
            margin-top: 50px;
        }
        .cta-section h3 {
            font-size: 2.8rem;
            color: #333;
            margin-bottom: 20px;
        }
        .cta-section p {
            font-size: 1.3rem;
            color: #666;
            margin-bottom: 30px;
        }
        .cta-section a {
            background-color: #1A73E8;
            color: white;
            padding: 18px 50px;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.3rem;
            transition: background-color 0.3s ease;
        }

        .cta-section a:hover {
            background-color: #005cbf;
        }

        /* Footer Section */
        footer {
            background-color: #333;
            color: white;
            padding: 30px 0;
            text-align: center;
            font-size: 1rem;
        }

        footer a {
            color: #FF9800;
            text-decoration: none;
        }
        footer a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media screen and (max-width: 900px) {
            .services {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media screen and (max-width: 600px) {
            .services {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <!-- Header Section -->
    <header>
        <h1>Our Home Renovation Services</h1>
        <p>Transform your space with expert renovations. From complete home makeovers to stylish updates, we deliver quality results.</p>
    </header>

    <!-- Services Section -->
    <section class="service-section">
        <h2>Our Renovation Services</h2>

        <div class="services">
            <!-- Service 1: Painters -->
            <div class="service-card">
                <img src="images/Painters.png" alt="Painters">
                <h3>Painters</h3>
                <p>Revamp your home with a fresh coat of paint, adding color, vibrancy, and protection to your walls and ceilings.</p>
                <a href="login.php" class="cta-button">Learn More</a>
            </div>

            <!-- Service 2: Plumbing -->
            <div class="service-card">
                <img src="images/s2.png" alt="Plumbing">
                <h3>Plumbing</h3>
                <p>Get expert plumbing services to repair leaks, install fixtures, and ensure your water systems function flawlessly.</p>
                <a href="login.php" class="cta-button">Learn More</a>
            </div>

            <!-- Service 3: Electrical -->
            <div class="service-card">
                <img src="images/s3.png" alt="Electrical">
                <h3>Electrical</h3>
                <p>From rewiring to electrical fixture installations, we provide top-quality electrical solutions for safety and efficiency.</p>
                <a href="login.php" class="cta-button">Learn More</a>
            </div>

            <!-- Service 4: Kitchen Renovation -->
            <div class="service-card">
                <img src="images/renew.png" alt="Kitchen Renovation">
                <h3>Kitchen Renovation</h3>
                <p>Transform your kitchen into a modern, functional space with top-of-the-line appliances, cabinetry, and flooring options.</p>
                <a href="login.php" class="cta-button">Learn More</a>
            </div>

            <!-- Service 5: Bathroom Renovation -->
            <div class="service-card">
                <img src="images/renew.png" alt="Bathroom Renovation">
                <h3>Bathroom Renovation</h3>
                <p>Upgrade your bathroom with luxurious fixtures, new tile work, and space-efficient designs to improve comfort and style.</p>
                <a href="login.php" class="cta-button">Learn More</a>
            </div>

            <!-- Service 6: Basement Finishing -->
            <div class="service-card">
                <img src="images/renew.png" alt="Basement Finishing">
                <h3>Basement Finishing</h3>
                <p>Turn your basement into a functional living area with modern finishes, ample storage, and personalized design elements.</p>
                <a href="login.php" class="cta-button">Learn More</a>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta-section">
        <h3>Ready to Renovate Your Home?</h3>
        <p>Contact us today and get started on transforming your living space. Our team of experts is ready to bring your vision to life!</p>
        <a href="contactus.php">Get in Touch</a>
    </section>

    <!-- Footer Section -->
    <footer>
        <p>&copy; 2025 Home Renovation Company. All Rights Reserved.</p>
        <p>Follow us on <a href="#">Facebook</a>, <a href="#">Twitter</a>, and <a href="#">LinkedIn</a>.</p>
    </footer>

    <section class="services-section">
        <div class="container">
            <h1 class="text-center">Our Services</h1>
            <div class="services-grid">
                <div class="service-card">
                    <img src="images/cleaning.png" alt="Cleaning">
                    <h3>Cleaning Services</h3>
                    <p>Professional home and office cleaning services</p>
                    <a href="login.php" class="hire-btn">Hire Now</a>
                </div>
                
                <div class="service-card">
                    <img src="images/plumbing.png" alt="Plumbing">
                    <h3>Plumbing</h3>
                    <p>Expert plumbing repair and installation</p>
                    <a href="login.php" class="hire-btn">Hire Now</a>
                </div>

                <div class="service-card">
                    <img src="images/electrical.png" alt="Electrical">
                    <h3>Electrical Work</h3>
                    <p>Licensed electricians for all electrical needs</p>
                    <a href="login.php" class="hire-btn">Hire Now</a>
                </div>

                <div class="service-card">
                    <img src="images/carpentry.png" alt="Carpentry">
                    <h3>Carpentry</h3>
                    <p>Skilled carpenters for woodwork and repairs</p>
                    <a href="login.php" class="hire-btn">Hire Now</a>
                </div>
            </div>
        </div>
    </section>

    <style>
        .services-section {
            padding: 80px 0;
            background: #f8f9fa;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .service-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .service-card:hover {
            transform: translateY(-10px);
        }
        
        .service-card img {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
        }
        
        .hire-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
            transition: background 0.3s;
        }
        
        .hire-btn:hover {
            background: #45a049;
        }
    </style>

  <link rel="stylesheet" href="css/sticky-header.css">
  <script src="js/sticky-header.js"></script>
</body>
</html>
