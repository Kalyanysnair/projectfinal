<?php
session_start();
include 'connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthcare Services Dashboard</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --header-height: 110px;
            --sidebar-width: 250px;
            --primary-color: rgb(5, 30, 16);
            --secondary-color: rgb(40, 186, 18);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-image: url('assets/assets/img//template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            padding-top: var(--header-height);
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: var(--header-height);
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background-color: rgba(218, 214, 214, 0.46);
            color: #333;
            padding: 25px 20px;
            overflow-y: auto;
            z-index: 999;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            border-right: 1px solid rgba(0, 0, 0, 0.1);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav li {
            margin-bottom: 10px;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #444;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            background-color: rgba(218, 214, 214, 0.46);
        }

        .sidebar-nav li a:hover {
            background-color: rgba(8, 218, 43, 0.1);
            color: rgb(8, 218, 43);
            transform: translateX(5px);
        }

        .sidebar-nav li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .user-avatar {
            margin-right: 10px;
        }

        .logout-btn {
            color: #dc3545 !important;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .logout-btn:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            width: calc(100% - var(--sidebar-width));
            min-height: calc(100vh - var(--header-height));
        }

        .hero-header {
            height: 120px;
            background: transparent;
            position: relative;
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }

        .hero-header::before {
            display: none;
        }

        .header-content {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 0 20px;
        }

        .header-content h1 {
            font-size: 2rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            color: #012970;
        }

        .header-content h3 {
            color: #012970;
            font-size: 1.8rem;
            font-weight: 600;
        }

        /* Services Grid Styles */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .service-box {
            background: rgba(238, 236, 236, 0.77);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .service-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, #007bff, #00ff88);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .service-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .service-box:hover::before {
            transform: scaleX(1);
        }

        .service-box .icon {
            font-size: 40px;
            margin-bottom: 20px;
            display: inline-block;
            padding: 20px;
            border-radius: 50%;
            background-color: #f8f9fa;
        }

        .service-box h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .service-box p {
            color: #666;
            margin-bottom: 20px;
        }

        .learn-more {
            color: rgb(8, 79, 17);
            font-weight: bold;
            display: inline-block;
            padding: 8px 20px;
            border: 2px solid rgb(11, 138, 22);
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .service-box:hover .learn-more {
            background-color: rgb(26, 199, 55);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .hero-header {
                height: 100px;
                margin-bottom: 20px;
            }

            .header-content h1 {
                font-size: 1.8rem;
            }
            
            .header-content h3 {
                font-size: 1.4rem;
            }
        }

        /* Remove text decoration from service box links */
        .services-grid a {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <?php include 'header.php';?>

    <!-- Sidebar -->
    <aside class="sidebar">
    <nav class="sidebar-menu">
        <ul class="sidebar-nav">
            <li>
                <a href="user_profile.php">
                    <i class="fas fa-user-circle"></i>
                    My Profile
                </a>
            </li>
            <li>
                <a href="status.php">
                    <i class="fas fa-clipboard-list"></i>
                    My Bookings
                </a>
            </li>
            <li>
                <a href="feedback.php">
                    <i class="fas fa-comments"></i>
                    Feedback
                </a>
            </li>
            <li>
                <a href="security.php">
                    <i class="fas fa-shield-alt"></i>
                    Security
                </a>
            </li>
            <li>
                <a href="manual.php">
                    <i class="fas fa-shield-alt"></i>
                    Emergency Manual
                </a>
            </li>
            <li>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </li>
        </ul>
    </nav>
</aside>

    <!-- Main Content -->
    <div class="main-content">
        <header class="hero-header">
            <div class="header-content">
                <h1>SwiftAid - User Dashboard</h1>
            </div>
        </header>

        <main class="container">
            <div class="services-grid">
                <!-- Emergency Care Box -->
                 <a href="emergency_login.php">
                <div class="service-box" onclick="navigateTo('emergency')">
                    <div class="icon">🚑</div>
                    <h2>Urgent Care Services</h2>
                    <p>24/7 emergency medical assistance with priority response.</p>
                    <div class="learn-more">Emergency Services→</div>
                </div>
               
                </a>
                <!-- Pre-booking Box -->
                 <a href="user.php">
                <div class="service-box" onclick="navigateTo('prebooking')">
                    <div class="icon">📅</div>
                    <h2>Pre-booking Services</h2>
                    <p>Schedule your appointments in advance for routine checkups.</p>
                    <div class="learn-more">Prebook Now →</div>
                </div>
                </a>
                <!-- Palliative Care Box -->
                 <a href="palliative.php">
                <div class="service-box" onclick="navigateTo('palliative')">
                    <div class="icon">💝</div>
                    <h2>Palliative Care</h2>
                    <p>Specialized care focusing on relief from serious illness.</p>
                    <div class="learn-more">Palliative Care →</div>
                 </div>
                </a>
            </div>
        </main>
    </div>

    <script>
        function navigateTo(page) {
            document.body.style.opacity = '0.5';
            setTimeout(() => {
                alert(Navigating to ${page} page...);
                document.body.style.opacity = '1';
            }, 300);
        }

        function logout() {
            if(confirm('Are you sure you want to logout?')) {
                alert('Logging out...');
                // Add your logout logic here
            }
        }

        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scroll effect
            window.addEventListener('scroll', function() {
                const header = document.querySelector('.hero-header');
                if (window.scrollY > 50) {
                    header.style.transform = 'translateY(-10px)';
                } else {
                    header.style.transform = 'translateY(0)';
                }
            });

            // Handle mobile menu
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    menuItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>