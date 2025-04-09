<?php
require_once "connect.php";
require 'vendor/autoload.php'; // Composer autoload for PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Handle OTP verification and user registration
if (isset($_POST['verify_otp'])) {
    header('Content-Type: application/json');

    $user_otp = trim($_POST['otp'] ?? '');

    if (empty($user_otp)) {
        echo json_encode(['status' => 'error', 'message' => 'OTP is required']);
        exit();
    }

    if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time'])) {
        echo json_encode(['status' => 'error', 'message' => 'OTP session expired']);
        exit();
    }

    // Check OTP attempts
    if ($_SESSION['otp_attempts'] >= 3) {
        echo json_encode(['status' => 'error', 'message' => 'Too many OTP attempts. Please try again later.']);
        exit();
    }

    // Validate OTP
    if ($user_otp == $_SESSION['otp'] && (time() - $_SESSION['otp_time']) <= 600) { // OTP valid for 10 minutes
        // OTP is correct, register the user
        $username = $_SESSION['registration_data']['username'];
        $phone = $_SESSION['registration_data']['phone'];
        $email = $_SESSION['registration_data']['email'];
        $password = $_SESSION['registration_data']['password'];

        // Insert user into the database
        $stmt = $conn->prepare("INSERT INTO tbl_user (username, password, phoneno, email) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $password, $phone, $email);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $_SESSION['user_id'] = $user_id;

            // Check if there is emergency form data in session
            if (isset($_SESSION['form_data'])) {
                $form_data = $_SESSION['form_data'];
                $stmt = $conn->prepare("INSERT INTO tbl_prebooking 
                    (userid, pickup_location, service_type, service_time,  
                    destination, ambulance_type, additional_requirements, comments) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param("isssssss", 
                    $user_id, $form_data['pickup_location'], $form_data['service_type'], $form_data['service_time'], 
                    $form_data['destination'], $form_data['ambulance_type'], $form_data['additional_requirements'], $form_data['comments']);

                if ($stmt->execute()) {
                    // Clear the form data from session
                    unset($_SESSION['form_data']);
                } else {
                    // Log the error (optional)
                    error_log("Error inserting prebooking data: " . $stmt->error);
                }

                $stmt->close();
            }

            // Clear OTP-related session data
            unset($_SESSION['otp']);
            unset($_SESSION['otp_time']);
            unset($_SESSION['otp_attempts']);
            unset($_SESSION['registration_data']);

            // Return success response
            echo json_encode([
                'status' => 'success',
                'message' => 'Registration and request submitted successfully'
            ]);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error registering user: ' . $stmt->error]);
            exit();
        }
    } else {
        // Increment OTP attempts
        $_SESSION['otp_attempts'] += 1;
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP']);
        exit();
    }
}

// Handle signup form submission
if (isset($_POST['submit'])) {
    header('Content-Type: application/json');

    try {
        // Sanitize and validate input
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

        // Generate 6-digit OTP
        $otp = sprintf("%06d", rand(100000, 999999));
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_time'] = time(); // Store OTP generation time
        $_SESSION['otp_attempts'] = 0; // Track OTP attempts
        $_SESSION['registration_data'] = [
            'username' => $username,
            'phone' => $phone,
            'email' => $email,
            'password' => $password
        ];

        // Send OTP via email using PHPMailer
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'kalyanys2004@gmail.com'; // Your Gmail address
        $mail->Password = 'ooqs zxti mult tlcb'; // Your Gmail app password
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        $mail->setFrom('kalyanys2004@gmail.com', 'SWIFTAID'); // Sender details
        $mail->addAddress($email); // Recipient email
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification OTP';

        // Email template
        $emailTemplate = "
        <div style='background-color: #f6f9fc; padding: 40px 0; font-family: Arial, sans-serif;'>
            <div style='background-color: #ffffff; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color:rgb(26, 232, 74); margin: 0; font-size: 24px;'>Email Verification</h1>
                </div>
                <div style='color: #4a4a4a; font-size: 16px; line-height: 1.6;'>
                    <p>Hello,</p>
                    <p>Thank you for registering with us. Please use the following OTP to verify your email address:</p>
                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 32px; font-weight: bold; color:rgb(26, 232, 71); letter-spacing: 5px;'>$otp</span>
                    </div>
                    <p>This OTP will expire in 10 minutes.</p>
                    <p>If you didn't request this verification, please ignore this email.</p>
                </div>
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px; text-align: center;'>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </div>";

        $mail->Body = $emailTemplate;
        $mail->AltBody = "Your OTP for email verification is: $otp";

        $mail->send(); // Send email

        // Store OTP-related session data
        $_SESSION['otp_sent'] = true;
        $_SESSION['verification_email_sent'] = time();
        $_SESSION['verification_email'] = $email;

        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'OTP sent successfully',
            'email' => $email
        ]);
        exit();
    } catch (Exception $e) {
        // Handle errors
        echo json_encode([
            'status' => 'error',
            'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"
        ]);
        exit();
    }
    exit(); // Ensure no further output
}

// Handle Google Sign-In
if (isset($_POST['google_signin'])) {
    header('Content-Type: application/json');
    
    try {
        // Get data from Google sign-in
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $username = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $google_id = filter_var($_POST['google_id'], FILTER_SANITIZE_STRING);
        
        // Check if user already exists with this email
        $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // User exists, log them in
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Logged in successfully',
                'is_new_user' => false
            ]);
        } else {
            // New user, register them
            // Generate a random password for Google users
            $random_password = bin2hex(random_bytes(16)); // 32 character random password
            $hashed_password = password_hash($random_password, PASSWORD_BCRYPT);
            
            $stmt = $conn->prepare("INSERT INTO tbl_user (username, password, email, google_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hashed_password, $email, $google_id);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $_SESSION['user_id'] = $user_id;
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Account created successfully',
                    'is_new_user' => true
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error creating account: ' . $stmt->error]);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle Regular Sign-In
if (isset($_POST['signin'])) {
    header('Content-Type: application/json');
    
    try {
        // Get sign-in credentials
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, username, password FROM tbl_user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct, create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Sign in successful'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid email or password'
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'User not found. Please register first.'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SWIFTAID</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Google Sign-In API -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="main.js"  defer type="module"></script>
    

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">
  <style>
        #googleSignInBtn {
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: white;
        color: #555;
        border: 1px solid #ccc;
        border-radius: 8px;
        padding: 10px 15px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease-in-out;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        width: 250px;
        margin: 10px auto;
    }
    .google-logo {
        width: 20px;
        height: 20px;
        margin-right: 10px;
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

<section id="hero" class="hero section dark-background">
    <div id="hero-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-item active">
            <img src="assets/assets/img/template/Groovin/hero-carousel/road.jpg" alt="" class="hero-image">
            <div class="carousel-container">
                <div class="container">
                    <div class="registration-container">
                        <div class="transparent-box" style="background-color: rgba(218, 214, 214, 0.46); padding: 30px; border-radius: 10px; width: 80%; max-width: 800px; margin: 0 auto;">
                            <h2 style="color:brown">Sign Up Here</h2>
                            <form class="row g-3" id="registrationForm" method="POST">
                                <div class="col-6">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" id="username" required>
                                    <span class="error-message" id="usernameError"></span>
                                </div>
                                <div class="col-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" id="phone" required>
                                    <span class="error-message" id="phoneError"></span>
                                </div>
                                <div class="col-6">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" id="password" required>
                                    <span class="error-message" id="passwordError"></span>
                                </div>
                                <div class="col-6">
                                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required>
                                    <span class="error-message" id="confirmPasswordError"></span>
                                </div>
                                <div class="col-12">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" id="email" required>
                                    <span class="error-message" id="emailError"></span>
                                </div>
                                <div class="col-12">
                                    <button class="btn w-100" type="submit" name="submit" style="background-color: rgba(13, 166, 23, 0.95); border: none;">
                                    Create Account</button>
                                </div>
                                
                                   
                                <div class="col-12">
                                    <p style="color:brown; text-align:center"><center>Already have an account? <a href="login.php" style="color:brown">Log in</a></center></p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function () {
    // Validation patterns
    const userNamePattern = /^[A-Za-z ]{3,}$/;
    const emailPattern = /^[a-zA-Z0-9._-]+@((gmail\.com|yahoo\.com)|([a-zA-Z0-9-]+\.[a-zA-Z0-9-]+\.[a-zA-Z]{2,}))$/;
    const passwordPattern = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/;
    const phonePattern = /^[6-9]\d{9}$/;

    // Username validation
    $("#username").on("keyup blur", function () {
        const username = $(this).val();
        if (!userNamePattern.test(username)) {
            $("#usernameError").text("Username must be at least 3 letters long and contain only letters");
        } else {
            $("#usernameError").text("");
        }
    });

    // Email validation on keyup and blur
    $("#email").on("keyup blur", function () {
        const email = $(this).val();
        if (!emailPattern.test(email)) {
            $("#emailError").text("Please enter a valid email.");
        } else {
            // Check if email exists in the database
            $.ajax({
                url: "check_email.php",
                type: "POST",
                data: { email: email },
                success: function (response) {
                    if (response == "exists") {
                        $("#emailError").text("This email is already registered.");
                    } else {
                        $("#emailError").text("");
                    }
                },
                error: function (xhr, status, error) {
                    console.error("AJAX Error: " + status + error);
                    $("#emailError").text("An error occurred while checking the email.");
                }
            });
        }
    });

    // Password validation
    $("#password").on("keyup blur", function () {
        const password = $(this).val();
        if (!passwordPattern.test(password)) {
            $("#passwordError").text("Password must be at least 8 characters long and contain at least one letter, one number, and one special character");
        } else {
            $("#passwordError").text("");
        }
    });

    // Confirm Password validation on keyup and blur
    $("#confirmPassword").on("keyup blur", function () {
        const confirmPassword = $(this).val();
        const password = $("#password").val();
        if (confirmPassword !== password) {
            $("#confirmPasswordError").text("Passwords do not match.");
        } else {
            $("#confirmPasswordError").text("");
        }
    });

    // Phone validation
    $("#phone").on("keyup blur", function () {
        const phone = $(this).val();
        if (!phonePattern.test(phone)) {
            $("#phoneError").text("Please enter a valid 10-digit phone number starting with 6,7,8 or 9");
        } else {
            $("#phoneError").text("");
        }
    });

    // Form submission handler
    $("#registrationForm").on("submit", function (e) {
        e.preventDefault();

        // Check for validation errors
        if ($("#usernameError").text() || $("#phoneError").text() || 
            $("#emailError").text() || $("#passwordError").text() || 
            $("#confirmPasswordError").text()) {
            Swal.fire({
                icon: "error",
                title: "Validation Error",
                text: "Please fix all errors before submitting."
            });
            return;
        }

        // Show loading state
        Swal.fire({
            title: 'Sending OTP...',
            text: 'Please wait while we process your request',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Send registration data
        $.ajax({
            url: "signup.php",
            type: "POST",
            data: {
                submit: true,
                username: $("#username").val(),
                phone: $("#phone").val(),
                email: $("#email").val(),
                password: $("#password").val()
            },
            dataType: 'json'
        })
        .done(function (response) {
            Swal.close();
            
            if (response.status === 'success') {
                // Show OTP verification dialog
                showOTPVerificationDialog(response.email);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Failed to send OTP'
                });
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            Swal.close();
            console.error('AJAX Error:', textStatus, errorThrown);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Something went wrong. Please try again.'
            });
        });
    });

    // Function to show OTP verification dialog
    function showOTPVerificationDialog(email) {
        Swal.fire({
            title: 'Email Verification',
            html: `
                <div class="text-center p-4" 
    style="background: #ffffff; border-radius: 12px; box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1); margin: 40px auto; max-width: 400px;">
    
    <!-- SWIFTAID Branding -->
    <h2 style="color: #1D8B27; font-weight: bold; margin-bottom: 15px;">SWIFTAID</h2>

    <p style="font-size: 16px; color: #555; margin-bottom: 20px;">
        Enter the 6-digit OTP sent to <strong>${email}</strong></p>
    </p>

    <input type="text" id="otp-input" 
        class="form-control mx-auto"
        style="width: 220px; letter-spacing: 8px; font-size: 22px; text-align: center; padding: 12px; border: 2px solid #1D8B27; border-radius: 8px; box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.2);"
        maxlength="6" 
        pattern="[0-9]*"
        inputmode="numeric"
        autocomplete="one-time-code"
       

    
    </div>
</div>

            `,
            showCancelButton: true,
            confirmButtonText: 'Verify',
            cancelButtonText: 'Cancel',
            allowOutsideClick: false,
            preConfirm: () => {
                const otp = document.getElementById('otp-input').value;
                if (!otp || otp.length !== 6 || !/^\d+$/.test(otp)) {
                    Swal.showValidationMessage('Please enter a valid 6-digit OTP');
                    return false;
                }
                return $.ajax({
                    url: 'verify_otp.php',
                    type: 'POST',
                    data: { otp: otp },
                    dataType: 'json'
                });
            }
        }).then((result) => {
            if (result.isConfirmed && result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Registration completed successfully!',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.href = 'login.php';
                });
            }
        });

        // Add input formatting for OTP
        const otpInput = document.getElementById('otp-input');
        otpInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        // Handle resend OTP
        $(document).on('click', '#resend-otp', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Resending OTP...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'signup.php',
                type: 'POST',
                data: {
                    submit: true,
                    resend: true
                },
                dataType: 'json'
            })
            .done(function (response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'OTP Resent',
                        text: 'A new OTP has been sent to your email',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to resend OTP'
                    });
                }
            })
            .fail(function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to resend OTP. Please try again.'
                });
            });
        });
    }
});


</script>
</body>
</html>