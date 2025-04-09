<?php
session_start();
include 'connect.php';

if (!isset($_GET['email']) || !isset($_GET['token'])) {
    die("Invalid reset link. Please request a new password reset.");
}

$email = urldecode($_GET['email']);
$token = urldecode($_GET['token']);

$conn = new mysqli("localhost", "root", "", "groovin");

// Verify token and expiry
$stmt = $conn->prepare("SELECT reset_token FROM tbl_user WHERE email = ? AND reset_expiry > NOW()");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($token, $user['reset_token'])) {
    die("Invalid or expired reset link.");
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        $update_stmt = $conn->prepare("UPDATE tbl_user SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE email = ?");
        $update_stmt->bind_param("ss", $hashed_password, $email);

        if ($update_stmt->execute()) {
            $success = "Password reset successful. You can now <a href='login.php'>Login</a>.";
        } else {
            $error = "Password reset failed. Please try again.";
        }
        $update_stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> <!-- Include external CSS file for styles -->
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">
    <style>/* General Styles */
body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            color: #fff;
            justify-content: center;
            align-items: center;
            background: url('assets/assets/img/template/Groovin/hero-carousel/road.jpg') no-repeat center center fixed;
            background-size: cover;
        }

      /* General Header Styles */
header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: rgba(34, 37, 34, 0.8);
    padding: 10px 20px;
    box-shadow: 0 2px 5px rgba(157, 156, 156, 0.3);
    z-index: 1000;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Logo Styles */
header .logo {
    color: #fff;
    font-size: 24px;
    font-weight: bold;
    display: flex;
    align-items: center;
    text-decoration: none;
}

header .logo img {
    height: 40px; /* Adjust logo size */
    margin-right: 10px;
}

/* Site Name */
header .sitename {
    margin: 0;
    font-size: 20px;
    text-transform: uppercase;
}

/* Navigation Menu */
header .navmenu {
    
    display: flex;
    justify-content: flex-end;
}

header .navmenu ul {
    list-style: none;
    display: flex;
    margin: 0;
    padding: 0;
}

header .navmenu ul li {
    margin-left: 20px; /* Space between nav items */
}

header .navmenu ul li a {
    text-decoration: none;
    color: #fff;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.3s ease;
}

header .navmenu ul li a:hover {
    color: #cddc39;
}

/* Get Started Button */
#header .btn-getstarted {
    background: rgb(97, 255, 100);
    padding: 10px 20px;
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
}

/* Aligning the Header Components Properly */
header .container-fluid {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

/* Responsive Styles for Smaller Screens */
@media (max-width: 768px) {
    header .navmenu ul {
        display: none; /* Hide navigation menu on smaller screens */
    }
    
    #header .btn-getstarted {
        margin-left: 10px;
    }
    
    .logo {
        font-size: 18px;
    }
    
    .sitename {
        font-size: 16px;
    }
}


        .btn-getstarted {
            background: #4CAF50;
            color: #fff;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .btn-getstarted:hover {
            background: #2E7D32;
        }


/* Reset Password Box Styles */
.reset-password-container {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
}

.reset-password-box {
    background: rgba(198, 195, 195, 0.8);
    padding: 30px;
    border-radius: 10px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.reset-password-box input {
    width: 95%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.reset-password-box button {
    width: 100%;
    padding: 10px;
    background: #4CAF50;
    border: none;
    color: white;
    font-size: 16px;
    border-radius: 5px;
    cursor: pointer;
}


       

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            font-size: 16px;
            color: rgb(220, 227, 221);
        }

        .form-group input {
            width: 90%;
            padding: 10px;
            margin-top: 4px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-group input:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.5);
        }

        

.reset-password-box button:hover {
    background: #45a049;
}

.reset-password-box a {
    color: #4CAF50;
    text-decoration: none;
    font-size: 16px;
}

.reset-password-box a:hover {
    text-decoration: underline;
}

        </style>
</head>
<body>
    <!-- Header Section -->
    <header id="header" class="header d-flex align-items-center fixed-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center">
            <a href="index.html" class="logo d-flex align-items-center me-auto">
                <img src="assets/img/SWIFTAID2.png" alt="SWIFTAID Logo" style="height: 70px; margin-right: 10px;">
                <h1 class="sitename">SWIFTAID</h1>
            </a>
            <nav id="navmenu" class="navmenu">
                <ul>
                    <li><a href="index.html#hero">Home</a></li>
                    <li><a href="index.html#about">About</a></li>
                    <li><a href="index.html#services">Services</a></li>
                    <li><a href="index.html#ambulanceservice">Ambulance Services</a></li>
                    <li><a href="index.html#contact">Contact</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="signup.php">Sign Up</a></li>
                </ul>
            </nav>
            <a class="btn-getstarted" href="emergency.php">Emergency Booking</a>
        </div>
    </header>

    <!-- Main Content Section -->
    <div class="reset-password-container">
        <div class="reset-password-box">
            <h2>Reset Password</h2>
            <?php if (!empty($error)) echo "<p style='color: red;'>$error</p>"; ?>
            <?php if (!empty($success)) echo "<p style='color: green;'>$success</p>"; ?>

            <form method="post">
                <label>New Password:</label>
                <input type="password" id="new_password" name="new_password" required><br>
                <span class="error-message" id="newpasswordError"></span><br>

                <label>Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required><br>
                <span class="error-message" id="newconfirmPasswordError"></span>

                <button type="submit">Reset Password</button>
            </form>


        </div>
    </div>
    <!-- Add jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function () {
    const passwordPattern = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/;

    // Password validation
    $("#new_password").on("keyup blur", function () {
        const password = $(this).val();
        if (!passwordPattern.test(password)) {
            $("#newpasswordError").text("Password must be at least 8 characters long and contain at least one letter, one number, and one special character.");
        } else {
            $("#newpasswordError").text("");
        }
    });

    // Confirm Password validation
    $("#confirm_password").on("keyup blur", function () {
        const confirmPassword = $(this).val();
        const password = $("#new_password").val();
        if (confirmPassword !== password) {
            $("#newconfirmPasswordError").text("Passwords do not match.");
        } else {
            $("#newconfirmPasswordError").text("");
        }
    });
});
</script>
</body>
</html>
