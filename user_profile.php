<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== "user") {
    header("Location: login.php");
    exit();
}

include 'connect.php';

// Get username from session
$username = $_SESSION['username'];
$success_message = "";
$error_message = "";

// Handle form submission for profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get form data
    $email = trim($_POST['email']);
    $phone = trim($_POST['phoneno']);
    
    // Input validation
    $errors = [];
    
    // Email validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Phone validation (basic)
    if (!empty($phone) && !preg_match("/^[6-9]\d{9}$/", $phone)) {
        $errors[] = "Phone number must be 10 digits and should start with numbers from 6-9";
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        // Update user info
        $update_query = "UPDATE tbl_user SET email = ?, phoneno = ? WHERE username = ?";
        $stmt = $mysqli->prepare($update_query);
        
        if ($stmt) {
            $stmt->bind_param("sss", $email, $phone, $username);
            $stmt->execute();
            $success_message = "Profile updated successfully!";
        } else {
            $error_message = "Update failed: " . $mysqli->error;
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get user details
$user_query = "SELECT userid, username, email, phoneno, created_at, status FROM tbl_user WHERE username = ?";
$stmt = $mysqli->prepare($user_query);

if (!$stmt) {
    die("Query Preparation Failed: " . $mysqli->error);
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found in the database.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - SWIFTAID</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 90px;
            --primary-color:rgb(58, 180, 30); /* Updated to Sea Green */
            --secondary-color:rgb(12, 121, 29); /* Updated to Medium Sea Green */
            --accent-color:rgb(57, 129, 27); /* Light Green */
            --dark-green:rgb(8, 114, 8); /* Dark Green */
            --light-green: #e0f7e6; /* Very Light Green for backgrounds */
        }
        #header {
            background: rgba(34, 39, 34, 0.9);
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            width: 100%;
            z-index: 1000;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-image: url('assets/assets/img//template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
        }

        .sitename {
            color: var(--primary-color);
            font-size: 24px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .navmenu ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 20px;
        }

        .navmenu a {
            color: rgb(155, 156, 157);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .navmenu a:hover {
            color: var(--accent-color);
        }

        .btn-getstarted {
            background: var(--primary-color);
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .btn-getstarted:hover {
            background: var(--dark-green);
        }

        /* Sidebar Styles */
        /* .sidebar {
            position: fixed;
            left: 0;
            top: var(--header-height);
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: rgba(230, 245, 230, 0.9);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px 0;
        }

        .sidebar-nav {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .sidebar-nav li {
            padding: 10px 20px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }

        .sidebar-nav li:hover {
            background-color: var(--light-green);
            border-left: 4px solid var(--primary-color);
        }

        .sidebar-nav a {
            color: var(--dark-green);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .sidebar-nav a:hover {
            color: var(--primary-color);
        }
        
        .sidebar-nav i {
            font-size: 18px;
            color: var(--primary-color);
        } */
        
        /* Main Content Area */
        .main {
            /* margin-left: var(--sidebar-width); */
            padding: 20px;
            margin-top: var(--header-height);
        }
        
        .profile-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            max-width: 700px;
            margin: 20px auto;
            border-top: 5px solid var(--primary-color);
        }
        
        .profile-header {
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }
        
        .profile-header:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--primary-color);
            margin: 15px auto 0;
            border-radius: 2px;
        }
        
        .profile-header h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .profile-section {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 25px;
        }
        
        .profile-section h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 22px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #e0e0e0;
        }
        
        .profile-info {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .profile-info:hover {
            background-color: var(--light-green);
        }
        
        .profile-label {
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--dark-green);
            font-size: 15px;
        }
        
        .profile-value {
            color: #333;
            padding: 5px 0;
            font-size: 16px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: border 0.3s ease;
            font-size: 16px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background-color: var(--primary-color);
            color: white;
            margin-top: 15px;
        }
        
        .btn-edit:hover {
            background-color: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-save {
            background-color: var(--secondary-color);
            color: white;
            margin-right: 15px;
        }
        
        .btn-save:hover {
            background-color: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Toggle between view and edit mode */
        .edit-mode {
            display: none;
        }
        
        .view-mode {
            display: block;
        }
        
        .actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-start;
        }
        
        .required {
            color: #dc3545;
            margin-left: 3px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .profile-container {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Sidebar Navigation
    <aside class="sidebar">
        <ul class="sidebar-nav">
            <li>
                <a href="user1.php">
                    <i class="bi bi-grid"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="user_profile.php">
                    <i class="bi bi-person"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="booking_history.php">
                    <i class="bi bi-clock-history"></i>
                    <span>Booking History</span>
                </a>
            </li>
        
            <li>
                <a href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside> -->

    <!-- Main Content -->
    <main class="main">
        <div class="profile-container">
            <div class="profile-header">
                <h2>User Profile</h2>
                <p>View and manage your account information</p>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>

            <form id="profile-form" method="POST" action="">
                <div class="profile-section">
                    <h3>Account Information</h3>
                    
                    <!-- View Mode -->
                    <div class="view-mode">
                        <div class="profile-info">
                            <div class="profile-label">Username</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Email</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Phone Number</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['phoneno']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Account Status</div>
                            <div class="profile-value"><?php echo htmlspecialchars(ucfirst($user['status'])); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Member Since</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['created_at']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Edit Mode -->
                    <div class="edit-mode">
                        <div class="profile-info">
                            <div class="profile-label">Username</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Email <span class="required">*</span></div>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Phone Number <span class="required">*</span></div>
                            <input type="text" name="phoneno" class="form-control" value="<?php echo htmlspecialchars($user['phoneno']); ?>" required>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Account Status</div>
                            <div class="profile-value"><?php echo htmlspecialchars(ucfirst($user['status'])); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Member Since</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['created_at']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Edit/Save Buttons -->
                <div class="view-mode">
                    <button type="button" id="edit-button" class="btn btn-edit">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </button>
                </div>
                
                <div class="edit-mode actions">
                    <button type="submit" name="update_profile" class="btn btn-save">
                        <i class="bi bi-check"></i> Save Changes
                    </button>
                    <button type="button" id="cancel-button" class="btn btn-cancel">
                        <i class="bi bi-x"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const editButton = document.getElementById('edit-button');
            const cancelButton = document.getElementById('cancel-button');
            const viewModes = document.querySelectorAll('.view-mode');
            const editModes = document.querySelectorAll('.edit-mode');
            
            // Toggle between view and edit mode
            editButton.addEventListener('click', function() {
                viewModes.forEach(mode => mode.style.display = 'none');
                editModes.forEach(mode => mode.style.display = 'block');
            });
            
            // Cancel editing
            cancelButton.addEventListener('click', function() {
                viewModes.forEach(mode => mode.style.display = 'block');
                editModes.forEach(mode => mode.style.display = 'none');
                
                // Reset form to prevent keeping any changes
                document.getElementById('profile-form').reset();
            });
        });
    </script>
</body>
</html>