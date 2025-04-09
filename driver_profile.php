<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== "driver") {
    header("Location: login.php");
    exit();
}

include 'connect.php';

// Get the driver's username from session
$driver_username = $_SESSION['username'];
$success_message = "";
$error_message = "";

// Handle form submission for profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Trim input data
    $email = trim($_POST['email']);
    $phone = trim($_POST['phoneno']);
    $license = trim($_POST['license']);
    $service_area = trim($_POST['service_area']);
    $vehicle_no = trim($_POST['vehicle_no']);
    $ambulance_type = trim($_POST['ambulance_type']);
    
    // Input validation
    $errors = [];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (!preg_match("/^[6-9]\d{9}$/", $phone)) {
        $errors[] = "Phone number must be 10 digits and start with a number from 6-9.";
    }
    
    if (!preg_match("/^KL-\d{2} \d{11}$/", $license)) {
        $errors[] = "Please enter a valid license number (e.g., KL-12 12345678901).";
    }
    
    if (!preg_match("/^[A-Z]{2}-\d{1,2}-[A-Z]{1,2}-\d{4}$/", $vehicle_no)) {
        $errors[] = "Please enter a valid vehicle number.";
    }
    
    if (empty($ambulance_type)) {
        $errors[] = "Ambulance type is required.";
    }
    
    if (empty($service_area)) {
        $errors[] = "Service area is required.";
    }
    
    if (empty($errors)) {
        // Get user ID
        $stmt = $mysqli->prepare("SELECT userid FROM tbl_user WHERE username = ?");
        $stmt->bind_param("s", $driver_username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_row = $result->fetch_assoc();
        $userid = $user_row['userid'] ?? null;
        
        if ($userid) {
            // Update user table
            $stmt = $mysqli->prepare("UPDATE tbl_user SET email = ?, phoneno = ? WHERE userid = ?");
            $stmt->bind_param("ssi", $email, $phone, $userid);
            if ($stmt->execute()) {
                // Update driver table
                $stmt = $mysqli->prepare("UPDATE tbl_driver SET lisenceno = ?, service_area = ?, vehicle_no = ?, ambulance_type = ? WHERE userid = ?");
                $stmt->bind_param("ssssi", $license, $service_area, $vehicle_no, $ambulance_type, $userid);
                if ($stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                } else {
                    $error_message = "Driver update failed: " . $stmt->error;
                }
            } else {
                $error_message = "User update failed: " . $stmt->error;
            }
        } else {
            $error_message = "User not found.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch user details
$stmt = $mysqli->prepare("SELECT userid, username, email, phoneno,status FROM tbl_user WHERE username = ?");
$stmt->bind_param("s", $driver_username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found in the database.");
}

$userid = $user['userid'];

// Fetch driver details
$stmt = $mysqli->prepare("SELECT lisenceno, service_area, vehicle_no, ambulance_type,created_at FROM tbl_driver WHERE userid = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if (!$driver) {
    $error_message = "Driver details not found for user ID $userid.";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Profile - SWIFTAID</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

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
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 90px;
            --primary-color: rgb(5, 30, 16);
            --secondary-color: rgb(40, 186, 18);
            --accent-color: #28ba12;
            --error-color: #dc3545;
            --success-color: #198754;
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
            background-attachment: fixed;
        }

        .sitename {
            color: var(--primary-color);
            font-size: 24px;
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
            transition: color 0.3s;
        }
        
        .navmenu a:hover {
            color: var(--secondary-color);
        }

        .btn-getstarted {
            background: var(--primary-color);
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-getstarted:hover {
            background: var(--secondary-color);
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: var(--header-height);
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: rgba(218, 214, 214, 0.8);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px 0;
            backdrop-filter: blur(5px);
        }

        .sidebar-nav {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .sidebar-nav li {
            padding: 10px 20px;
            transition: background 0.3s;
        }
        
        .sidebar-nav li:hover {
            background: rgba(40, 186, 18, 0.1);
        }

        .sidebar-nav a {
            color: #012970;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.3s;
        }
        .sidebar-nav a:hover {
            color: var(--secondary-color);
        }
        
        /* Main Content Area */
        .main {
            /* margin-left: var(--sidebar-width); */
            padding: 20px;
            margin-top: var(--header-height);
        }
        
        .profile-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 35px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            max-width: 800px;
            margin: 20px auto;
            transition: transform 0.3s;
        }
        
        .profile-container:hover {
            transform: translateY(-5px);
        }
        
        .profile-header {
            margin-bottom: 35px;
            text-align: center;
            position: relative;
        }
        
        .profile-header h2 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 32px;
            font-weight: 600;
        }
        
        .profile-header p {
            color: #666;
            font-size: 18px;
        }
        
        .profile-header::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--secondary-color);
            margin: 15px auto 0;
            border-radius: 5px;
        }
        
        .profile-section {
            margin-bottom: 30px;
            position: relative;
            border-radius: 8px;
            padding: 20px;
            background: rgba(40, 186, 18, 0.03);
            border-left: 4px solid var(--secondary-color);
        }
        
        .profile-section h3 {
            color: var(--primary-color);
            border-bottom: 2px solid #eee;
            padding-bottom: 12px;
            margin-bottom: 20px;
            font-size: 22px;
            font-weight: 500;
        }
        
        .profile-info {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .profile-label {
            font-weight: 600;
            color: #444;
            font-size: 16px;
        }
        
        .profile-value {
            color: #333;
            font-size: 16px;
            padding: 8px 0;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s, box-shadow 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(40, 186, 18, 0.2);
            outline: none;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 16px;
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
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-edit {
            background-color: var(--primary-color);
            color: white;
            display: block;
            margin: 25px auto 0;
            min-width: 150px;
        }
        
        .btn-edit:hover {
            background-color: #014d45;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-save {
            background-color: var(--secondary-color);
            color: white;
            margin-right: 10px;
        }
        
        .btn-save:hover {
            background-color: #20a00f;
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
        
        .actions {
            display: flex;
            justify-content: center;
            margin-top: 25px;
        }
        
        /* Toggle between view and edit mode */
        .edit-mode {
            display: none;
        }
        
        .view-mode {
            display: block;
        }
        
        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: rgba(40, 186, 18, 0.15);
            color: var(--secondary-color);
        }
        
        .status-inactive {
            background-color: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        .loading-spinner {
            display: none;
            margin-left: 10px;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .back-button {
    background-color: white;
    color: black;
    border: none; /* Removed the black border */
    padding: 10px 20px;
    font-size: 16px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: background-color 0.3s ease, color 0.3s ease;
  }

  .back-button:hover {
    background-color:rgb(216, 214, 214); /* Subtle gray on hover */
  }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <main class="main">
    <div class="profile-container">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <button onclick="window.location.href='driver.php'" class="back-button">
      &#8592; Back
    </button>
   
</div>
            <div class="profile-header">
                <h2>Driver Profile</h2>
                <p>View and manage your driver profile information</p>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?php echo $error_message; ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span><?php echo $success_message; ?></span>
            </div>
            <?php endif; ?>

            <form id="profile-form" method="POST" action="">
                <div class="profile-section">
                    <h3>Personal Information</h3>
                    
                    <!-- View Mode -->
                    <div class="view-mode">
                        <div class="profile-info">
                            <div class="profile-label">Username:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Email:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Phone Number:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['phoneno']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Account Status:</div>
                            <div class="profile-value">
                                <span class="status-badge <?php echo strtolower($user['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Mode -->
                    <div class="edit-mode">
                        <div class="profile-info">
                            <div class="profile-label">Username:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Email:</div>
                            <div class="profile-value">
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Phone Number:</div>
                            <div class="profile-value">
                                <input type="text" name="phoneno" class="form-control" value="<?php echo htmlspecialchars($user['phoneno']); ?>" required>
                            </div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Account Status:</div>
                            <div class="profile-value">
                                <span class="status-badge <?php echo strtolower($user['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($driver): ?>
                <div class="profile-section">
                    <h3>Driver Details</h3>
                    
                    <!-- View Mode -->
                    <div class="view-mode">
                        <div class="profile-info">
                            <div class="profile-label">License Number:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($driver['lisenceno']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Service Area:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($driver['service_area']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Vehicle Number:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($driver['vehicle_no']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Ambulance Type:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($driver['ambulance_type']); ?></div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Registered On:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($driver['created_at']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Edit Mode -->
                    <div class="edit-mode">
                        <div class="profile-info">
                            <div class="profile-label">License Number:</div>
                            <div class="profile-value">
                                <input type="text" name="license" class="form-control" value="<?php echo htmlspecialchars($driver['lisenceno']); ?>" required>
                            </div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Service Area:</div>
                            <div class="profile-value">
                                <input type="text" name="service_area" class="form-control" value="<?php echo htmlspecialchars($driver['service_area']); ?>" required>
                            </div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Vehicle Number:</div>
                            <div class="profile-value">
                                <input type="text" name="vehicle_no" class="form-control" value="<?php echo htmlspecialchars($driver['vehicle_no']); ?>" required>
                            </div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Ambulance Type:</div>
                            <div class="profile-value">
                                <select name="ambulance_type" class="form-control" required>
                                    <option value="Basic Ambulance Service" <?php echo $driver['ambulance_type'] === 'Basic Ambulance Service' ? 'selected' : ''; ?>>Basic Ambulance Service</option>
                                    <option value="Advanced Life Support" <?php echo $driver['ambulance_type'] === 'Advanced Life Support' ? 'selected' : ''; ?>>Advanced Life Support</option>
                                    <option value="Critical Care Ambulance" <?php echo $driver['ambulance_type'] === 'Critical Care Ambulance' ? 'selected' : ''; ?>>Critical Care Ambulance</option>
                                    <option value="Neonatal Ambulance" <?php echo $driver['ambulance_type'] === 'Neonatal Ambulance' ? 'selected' : ''; ?>>Neonatal Ambulance</option>
                                    <option value="Bariatric Ambulance" <?php echo $driver['ambulance_type'] === 'Bariatric Ambulance' ? 'selected' : ''; ?>>Bariatric Ambulance</option>
                                    <option value="Mortuary Transport" <?php echo $driver['ambulance_type'] === 'Mortuary Transport' ? 'selected' : ''; ?>>Mortuary Transport</option>
                                    <option value="Palliative" <?php echo $driver['ambulance_type'] === 'Palliative' ? 'selected' : ''; ?>>Palliative</option>
                                </select>
                            </div>
                        </div>
                        <div class="profile-info">
                            <div class="profile-label">Registered On:</div>
                            <div class="profile-value"><?php echo htmlspecialchars($driver['created_at']); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Edit/Save Buttons -->
                <div class="view-mode">
                    <button type="button" id="edit-button" class="btn btn-edit">
                        <i class="bi bi-pencil-fill"></i> Edit Profile
                    </button>
                </div>
                
                <div class="edit-mode actions">
                    <button type="submit" name="update_profile" class="btn btn-save">
                        <i class="bi bi-check-lg"></i> Save Changes
                        <span class="loading-spinner" id="save-spinner"></span>
                    </button>
                    <button type="button" id="cancel-button" class="btn btn-cancel">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const editButton = document.getElementById('edit-button');
            const cancelButton = document.getElementById('cancel-button');
            const viewModes = document.querySelectorAll('.view-mode');
            const editModes = document.querySelectorAll('.edit-mode');
            const profileForm = document.getElementById('profile-form');
            const saveSpinner = document.getElementById('save-spinner');
            
            // Toggle between view and edit mode
            editButton.addEventListener('click', function() {
                viewModes.forEach(mode => mode.style.display = 'none');
                editModes.forEach(mode => mode.style.display = 'block');
            });
            
            // Cancel editing
            cancelButton.addEventListener('click', function() {
                viewModes.forEach(mode => mode.style.display = 'block');
                editModes.forEach(mode => mode.style.display = 'none');
            });
            
            // Show loading spinner when saving
            profileForm.addEventListener('submit', function() {
                saveSpinner.style.display = 'inline-block';
            });
            
            // Animate alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 1s';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 1000);
                }, 5000);
            });
        });
    </script>
</body>
</html>