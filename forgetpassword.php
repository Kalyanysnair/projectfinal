<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();
require 'vendor/autoload.php'; // Load PHPMailer

date_default_timezone_set('Asia/Kolkata');

$error = "";
$success = "";



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Database Connection
    $servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $dbname = "groovin";

    $conn = new mysqli($servername, $db_username, $db_password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "❌ Invalid email format.";
    } else {
        // Check if email exists in the database
        $stmt = $conn->prepare("SELECT email FROM tbl_user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $error = "❌ No account found with this email.";
        } else {
            // Generate a unique reset token
            $token = bin2hex(random_bytes(32)); // Generate raw token
            $hashedToken = password_hash($token, PASSWORD_DEFAULT); // Hash token
            $expiry = date('Y-m-d H:i:s', strtotime('+2 hours')); // Token expires in 2 hours

            // Store reset token & expiry in database
            $update_stmt = $conn->prepare("UPDATE tbl_user SET reset_token = ?, reset_expiry = ? WHERE email = ?");
            $update_stmt->bind_param("sss", $hashedToken, $expiry, $email);

            if ($update_stmt->execute()) {
                $_SESSION['reset_token'] = $token;
                $reset_link = "http://localhost/Groovin/new_password.php?email=" . urlencode($email) . "&token=" . urlencode($token);

                // Send email using PHPMailer
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'kalyanys2004@gmail.com';  // Your Gmail address           
                    $mail->Password = 'ooqs zxti mult tlcb';   // Use Google App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Email Content
                    $mail->setFrom('your-email@gmail.com', 'SWIFTAID Team');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = "Password Reset - SWIFTAID";
                    $mail->Body = "
                        <h3>Password Reset Request</h3>
                        <p>You requested to reset your password.</p>
                        <p>Click the button below to reset your password:</p>
                        <a href='$reset_link' style='display: inline-block; padding: 10px 20px; background:rgb(32, 198, 60); color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a>
                        <p>This link will expire in 2 hours.</p>
                        <p>If you didn't request this, please ignore this email.</p>
                        <p>Best regards,<br>SWIFTAID Team</p>
                    ";

                    if ($mail->send()) {
                        $success = "✅ Password reset instructions have been sent to your email.";
                    } else {
                        $error = "❌ Failed to send email. Error: " . $mail->ErrorInfo;
                    }
                } catch (Exception $e) {
                    $error = "❌ Mail could not be sent. Error: " . $mail->ErrorInfo;
                }
            } else {
                $error = "❌ Something went wrong. Please try again.";
            }

            $update_stmt->close();
        }

        $stmt->close();
    }

    $conn->close();
}
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SWIFTAID</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('assets/img/hero-carousel/road.jpg') no-repeat center center/cover;
            color: white;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/road.jpg');
        }
        .forgot-container {
            background: rgba(171, 168, 168, 0.6);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            width: 400px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group input {
            width: 90%;
            padding: 10px;
            margin-top: 4px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .submit-btn {
            background:rgb(41, 235, 61);
            color: #fff;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .submit-btn:hover {
            background:rgb(16, 230, 130);
        }
        .alert-message {
            background-color: rgba(220, 53, 69, 0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
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

    <div class="forgot-container">
        <h2>Reset Your Password</h2>
        <?php if($error): ?>
            <div class="alert-message"><?php echo $error; ?></div>
        <?php elseif($success): ?>
            <div class="alert-message" style="background-color: rgba(40, 167, 69, 0.8);"><?php echo $success; ?></div>
        <?php endif; ?>
        <form action="forgetpassword.php" method="post">
            <div class="form-group">
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="submit-btn">Send Reset Link</button>
        </form>
        <a href="login.php" style="color: #fff; display: block; margin-top: 10px;">Back to Login</a>
    </div>
</body>
</html>
