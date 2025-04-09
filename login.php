<?php
session_start();

// Database connection
function connectDB() {
    $conn = new mysqli("localhost", "root", "", "groovin");
    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }
    return $conn;
}

// Handle Google Sign-In
if (isset($_POST['google_signin'])) {
    header('Content-Type: application/json');

    try {
        // Get data from Google sign-in
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $username = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $google_id = filter_var($_POST['google_id'], FILTER_SANITIZE_STRING);

        $conn = connectDB();

        // Check if user already exists
        $stmt = $conn->prepare("SELECT userid, username, role, status FROM tbl_user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // User exists, log them in
            $user = $result->fetch_assoc();

            // Check account status
            if (strtolower($user['status']) === 'inactive') {
                echo json_encode(['status' => 'error', 'message' => 'Your account is inactive. Please contact the administrator.']);
                exit();
            }

            // Set session variables
            $_SESSION['user_id'] = $user['userid'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user'; // Default to 'user' if role is not set

            echo json_encode([
                'status' => 'success',
                'message' => 'Logged in successfully',
                'is_new_user' => false,
                'redirect' => 'user1.php'
            ]);
        } else {
            // New user, register them
            $random_password = bin2hex(random_bytes(16)); // 32 character random password
            $hashed_password = password_hash($random_password, PASSWORD_BCRYPT);
            $default_role = 'user';
            $status = 'active';

            $stmt = $conn->prepare("INSERT INTO tbl_user (username, password, email, google_id, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $hashed_password, $email, $google_id, $default_role, $status);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $default_role;

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Account created successfully',
                    'is_new_user' => true,
                    'redirect' => 'user1.php'
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error creating account: ' . $stmt->error]);
            }
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle Regular Login
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['google_signin'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $conn = connectDB();

    // Fetch user details from tbl_user
    $stmt = $conn->prepare("SELECT userid, username, role, password, status FROM tbl_user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Check if user is inactive
        if (strtolower($user['status']) === 'inactive') {
            $error = "Your account is inactive. Please contact the administrator.";
        } elseif (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['userid'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            switch (strtolower($user['role'])) {
                case 'admin':
                    header("Location: admin.php");
                    break;
                case 'driver':
                    header("Location: driver.php");
                    break;
                default:
                    header("Location: user1.php");
                    break;
            }
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }

    $stmt->close();
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SWIFTAID</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Favicons -->
    <!-- <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon"> -->

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
    <!-- <script type="module" src="assets/js/main.js"></script> -->
    <script type="module" src="main.js"></script>

    <script src="main.js" defer></script>



    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            color: #fff;
            justify-content: center;
            align-items: center;
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 128, 0, 0.8);
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        header .sitename {
            color: #fff;
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }

        header .navmenu ul {
            list-style: none;
            display: flex;
            justify-content: flex-end;
            margin: 0;
            padding: 0;
        }

        header .navmenu ul li {
            margin: 0 10px;
        }

        header .navmenu ul li a {
            text-decoration: none;
            color: #fff;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        header .navmenu ul li a:hover {
            color: #cddc39;
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

        .login-container {
            background: rgba(218, 214, 214, 0.46);
            border-radius: 15px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            padding: 30px;
            width: 390px;
            text-align: center;
            color: #333;
        }

        .login-container h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: rgb(247, 253, 247);
        }
        #googleSignInBtn {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            color: #555;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 220px;
            margin: 15px auto;
            position: relative;
        }
        #googleSignInBtn:hover {
            background-color: #f8f8f8;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        .google-logo {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            position: relative;
            top: 1px;
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

        .login-btn {
            background: #4CAF50;
            color: #fff;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .login-btn:hover {
            background: #2E7D32;
        }

        .signup-link {
            margin-top: 15px;
            display: block;
            font-size: 14px;
            color: rgb(241, 244, 241);
            text-decoration: none;
        }

        .signup-link:hover {
            text-decoration: underline;
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .alert-error {
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

<section id="hero" class="hero section dark-background">
    <div id="hero-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-item active">
            <img src="assets/assets/img//template/Groovin/hero-carousel/road.jpg" alt="">
            <div class="carousel-container">
                <div class="container">
                    <div class="login-container">
                        <h2 style="align-items:center">Login to SwiftAid</h2>
                        <?php if(isset($error)) { ?>
                            <div class="alert-error"><?php echo $error; ?></div>
                        <?php } ?>
                        <form action="login.php" method="post">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" placeholder="Enter your username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            </div>
                            <button type="submit" class="login-btn">Login</button>
                        </form>
                      
                            <button id="googleSignInBtn">
                                <svg class="google-logo" viewBox="0 0 24 24" width="20" height="20">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                </svg>
                                Sign in with Google
                            </button>


                         
                       
                        <a href="signup.php" class="signup-link">Don't have an account? Sign up</a>
                        <a href="forgetpassword.php" class="signup-link">Forgot Password?</a>
                        </div> 
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>




</body>
</html>