<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/stylee.css">
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
    <link rel="stylesheet" type="text/css" href="css/font-awesome.min.css" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/responsive.css" rel="stylesheet" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frequently Asked Questions</title>
    <style>
        /* Replace the static sticky header CSS with our dynamic one */
        .header_section {
            position: relative;
            width: 100%;
            z-index: 999;
            background-color: #fff;
        }
        
        .header_bottom {
            background-color: #fff;
        }
        
        /* Keep existing styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            color: #333;
        }

        .container {
            width: 80%;
            margin: 0 auto;
            padding: 30px 0;
        }

        header h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 20px;
            font-size: 36px;
            font-weight: 700;
        }

        .faq-intro {
            text-align: center;
            margin-bottom: 30px;
            color: #6c757d;
            font-size: 18px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .faq-container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-top: 30px;
        }

        .faq-item {
            border-bottom: 1px solid #e6e6e6;
            margin-bottom: 15px;
            padding-bottom: 15px;
        }

        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 18px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            font-weight: 600;
            color: #2c3e50;
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
        }

        .faq-question h2 {
            font-size: 18px;
            margin: 0;
            font-weight: 600;
        }

        .icon {
            font-size: 22px;
            font-weight: bold;
            transition: transform 0.3s ease;
        }

        .faq-question.open {
            background-color: #007bff;
            color: white;
        }

        .faq-question.open .icon {
            transform: rotate(45deg);
        }

        .faq-answer {
            display: none;
            padding: 20px;
            background-color: #f9f9f9;
            border-left: 4px solid #007bff;
            border-radius: 0 8px 8px 0;
            margin-top: 10px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .faq-answer p, .faq-answer li {
            font-size: 16px;
            line-height: 1.7;
            color: #4a4a4a;
        }

        @media (max-width: 768px) {
            .container {
                width: 90%;
            }

            .faq-question h2 {
                font-size: 16px;
            }

            .faq-answer p {
                font-size: 14px;
            }
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

    
        <header>
            <h1>Frequently Asked Questions</h1>
            <p class="faq-intro">Find answers to the most common questions about our services, account management, and hiring process.</p>
        </header>
        
        <div class="faq-container">
            <!-- FAQ 1 -->
            <div class="faq-item">
                <div class="faq-question" onclick="toggleAnswer(1)">
                    <h2>How do I create an account and get started?</h2>
                    <span class="icon">&#43;</span>
                </div>
                <div class="faq-answer" id="answer-1">
                    <p>Creating an account on QuickHire Labor is quick and simple:</p>
                    <ol>
                        <li>Click the <strong>"Sign Up"</strong> button on the homepage</li>
                        <li>Enter your personal details including email, phone number, and full name</li>
                        <li>Select your account type: Customer (if you need services) or Laborer (if you provide services)</li>
                        <li>Create a secure password and verify your email address</li>
                        <li>Complete your profile with additional details to get the best experience</li>
                    </ol>
                    <p>Once registered, you'll have access to all platform features based on your account type.</p>
                </div>
            </div>
            
            <!-- FAQ 2 -->
            <div class="faq-item">
                <div class="faq-question" onclick="toggleAnswer(2)">
                    <h2>What professional services are available through QuickHire Labor?</h2>
                    <span class="icon">&#43;</span>
                </div>
                <div class="faq-answer" id="answer-2">
                    <p>Our platform connects you with skilled professionals in various categories:</p>
                    <ul>
                        <li><strong>Construction & Repairs:</strong> General contracting, handyman services, and home repairs</li>
                        <li><strong>Electrical Work:</strong> Installation, repairs, wiring, and lighting solutions</li>
                        <li><strong>Plumbing:</strong> Leak repairs, installation, drain cleaning, and water systems</li>
                        <li><strong>Home Maintenance:</strong> Regular upkeep, inspections, and preventative care</li>
                        <li><strong>Renovation:</strong> Kitchen, bathroom, and whole-home remodeling</li>
                        <li><strong>Specialized Services:</strong> Painting, flooring, roofing, and landscaping</li>
                    </ul>
                    <p>Browse our <a href="services.php">Services</a> page for a complete listing with detailed descriptions.</p>
                </div>
            </div>

            <!-- FAQ 3 -->
            <div class="faq-item">
                <div class="faq-question" onclick="toggleAnswer(3)">
                    <h2>How can I pay for a job?</h2>
                    <span class="icon">&#43;</span>
                </div>
                <div class="faq-answer" id="answer-3">
                    <p>Payments can be made securely through the platform using the following steps:</p>
                    <ol>
                        <li>Once the job is completed, go to the "Payments" section in your dashboard.</li>
                        <li>Choose the payment method (credit card, debit card, or online wallet).</li>
                        <li>Enter your payment details and confirm the transaction.</li>
                        <li>You will receive a payment receipt via email for your records.</li>
                    </ol>
                </div>
            </div>

            <!-- FAQ 4 -->
            <div class="faq-item">
                <div class="faq-question" onclick="toggleAnswer(4)">
                    <h2>How do I rate a laborer?</h2>
                    <span class="icon">&#43;</span>
                </div>
                <div class="faq-answer" id="answer-4">
                    <p>To rate a laborer, follow these steps:</p>
                    <ol>
                        <li>Go to the "My Jobs" section after the job is completed.</li>
                        <li>Find the job you wish to rate and click on it.</li>
                        <li>Click on the "Rate Laborer" button.</li>
                        <li>Give the laborer a rating (1 to 5 stars) and write a brief review based on the service provided.</li>
                        <li>Click "Submit" to finalize your rating.</li>
                    </ol>
                </div>
            </div>

            <!-- FAQ 5 -->
            <div class="faq-item">
                <div class="faq-question" onclick="toggleAnswer(5)">
                    <h2>How do I change my account password?</h2>
                    <span class="icon">&#43;</span>
                </div>
                <div class="faq-answer" id="answer-5">
                    <p>To change your account password, do the following:</p>
                    <ol>
                        <li>Log in to your account and go to "Account Settings".</li>
                        <li>Click on the "Change Password" option.</li>
                        <li>Enter your current password and then input your new password.</li>
                        <li>Confirm the new password and click "Save Changes".</li>
                    </ol>
                </div>
            </div>

            <!-- FAQ 6 -->
            <div class="faq-item">
                <div class="faq-question" onclick="toggleAnswer(6)">
                    <h2>Can I post a job as a customer?</h2>
                    <span class="icon">&#43;</span>
                </div>
                <div class="faq-answer" id="answer-6">
                    <p>Yes, customers can post jobs. Follow these steps:</p>
                    <ol>
                        <li>Login to your account and go to the "Post a Job" section.</li>
                        <li>Fill in the job details such as description, location, required skills, and timeline.</li>
                        <li>Click "Submit" to publish your job listing and wait for laborers to apply.</li>
                        <li>You can review applicants, check their profiles, and select the best fit for the job.</li>
                    </ol>
                </div>
            </div>

            <!-- FAQ 7 -->
            <div class="faq-item">
                <div class="faq-question" onclick="toggleAnswer(7)">
                    <h2>How do I apply for a job as a laborer?</h2>
                    <span class="icon">&#43;</span>
                </div>
                <div class="faq-answer" id="answer-7">
                    <p>To apply for a job as a laborer, do the following:</p>
                    <ol>
                        <li>Log in to your account and go to the "Browse Jobs" section.</li>
                        <li>Find jobs that match your skills and location.</li>
                        <li>Click "Apply" on the job listing you are interested in.</li>
                        <li>You will receive a notification if your application is accepted.</li>
                    </ol>
                </div>
            </div>

            <!-- FAQ 8 -->
            <div class="faq-item">
                <div class="faq-question" onclick="toggleAnswer(8)">
                    <h2>How do I contact customer support?</h2>
                    <span class="icon">&#43;</span>
                </div>
                <div class="faq-answer" id="answer-8">
                    <p>If you need assistance, follow these steps:</p>
                    <ol>
                        <li>Go to the "Support Center" in the footer of the website.</li>
                        <li>Click "Contact Support" and fill in the form with your query.</li>
                        <li>Alternatively, you can email us directly at <?php 
                            include 'includes/dbconn.php';
                            $sql = "SELECT email FROM users WHERE role='admin' LIMIT 1";
                            $result = mysqli_query($conn, $sql);
                            if ($row = mysqli_fetch_assoc($result)) {
                                echo $row['email'];
                            } else {
                                echo "support@quickhirelabor.com";
                            }
                            mysqli_close($conn);
                        ?>.</li>
                    </ol>
                </div>
            </div>

        </div>
    

    <script>
        function toggleAnswer(faqId) {
            const answer = document.getElementById(`answer-${faqId}`);
            const question = document.querySelector(`#answer-${faqId}`).previousElementSibling;
            
            // Close all other FAQs first
            document.querySelectorAll('.faq-answer').forEach(item => {
                if (item.id !== `answer-${faqId}` && item.style.display === 'block') {
              item.style.display = 'none';
                    item.previousElementSibling.classList.remove('open');
                }
            });
            
            // Toggle the visibility of the answer
            if (answer.style.display === "block") {
                answer.style.display = "none";
                question.classList.remove('open');
            } else {
                answer.style.display = "block";
                question.classList.add('open');
                
                // Smooth scroll to the question after a brief delay
                setTimeout(() => {
                    question.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
        }
    </script>
      
    <link rel="stylesheet" href="css/sticky-header.css">
    <script src="js/sticky-header.js"></script>
</body>
</html>
