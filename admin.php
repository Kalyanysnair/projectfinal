<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'connect.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch Stats
$total_users = $conn->query("SELECT COUNT(*) as total FROM tbl_user")->fetch_assoc()['total'];
$driver_count = $conn->query("SELECT COUNT(*) as total FROM tbl_driver")->fetch_assoc()['total'];
$review_count = $conn->query("SELECT COUNT(*) as total FROM tbl_review")->fetch_assoc()['total'];

// Handle Driver Scheduling Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['schedule_drivers'])) {
    $driver1_name = trim($_POST['driver1_name']);
    $driver2_name = trim($_POST['driver2_name']);
    $schedule_date = $_POST['schedule_date'];

    $name_pattern = "/^[a-zA-Z ]+$/"; // Only letters and spaces

    if (!preg_match($name_pattern, $driver1_name) || !preg_match($name_pattern, $driver2_name)) {
        $error_msg = "Driver names must only contain letters and spaces.";
    } elseif (strtotime($schedule_date) < strtotime(date('Y-m-d'))) {
        $error_msg = "Schedule date cannot be in the past.";
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_scheduled_drivers (driver1_name, driver2_name, schedule_date) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $driver1_name, $driver2_name, $schedule_date);

        if ($stmt->execute()) {
            $success_msg = "Drivers scheduled successfully!";
        } else {
            $error_msg = "Error scheduling drivers. Please try again.";
        }
        $stmt->close();
    }
}

// Get Current Page Name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftAid Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #008374;
            --hover-color: #00a28a;
            --text-color: #ffffff;
            --sidebar-width: 250px;
            --sidebar-bg: rgba(0, 0, 0, 0.3);
            --card-bg: rgba(255, 255, 255, 0.15);
        }
        
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg') no-repeat center center/cover;
        }

        .wrapper {
            display: flex;
            height: 100vh;
        }

        #sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: var(--text-color);
            height: 100%;
            position: fixed;
            backdrop-filter: blur(10px);
        }

        #sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 15px;
            display: block;
            transition: background 0.3s;
        }

        #sidebar a:hover, #sidebar a.active {
            background: var(--hover-color);
        }

        #content {
            width: calc(100% - var(--sidebar-width));
            margin-left: var(--sidebar-width);
            padding: 20px;
            backdrop-filter: blur(5px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            color: #fff;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.3);
        }

        .container-box {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 10px;
            max-width: 900px;
            margin: 0 auto;
            backdrop-filter: blur(15px);
        }

        .logout-btn {
            width: 80%;
            margin: 10px auto;
            font-size: 14px;
            padding: 8px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <!-- <h3 class="text-center p-3">SwiftAid Admin</h3> -->
            <!-- <a href="admin.php" ><i class="fas fa-tachometer-alt"></i> Dashboard</a> -->
            <a href="UserManagement.php"><i class="fas fa-users"></i> User Management</a>
            <a href="add_driver.php"><i class="fas fa-user-plus"></i> Add Drivers</a>
            <a href="driver_detail.php"><i class="fas fa-id-card"></i> Driver Details</a>
            <a href="admin_review.php"><i class="fas fa-star"></i> Feedback</a>
            <a href="admin_payments.php"><i class="fas fa-credit-card"></i> Payments</a>
            <a href="emergency_schedule.php"><i class="fas fa-calendar-alt"></i> Emergency Schedule</a>
            <a href="admin_driver_requests.php"><i class="fas fa-calendar-alt"></i> Driver Management</a>
            <a href="security.php"><i class="fas fa-credit-card"></i> Security</a>
             
             <a href="admin_leave_requests.php"><i class="fas fa-calendar-alt"></i> Leave</a>
            <a href="admin_tip.php"><i class="fas fa-calendar-alt"></i> Emergency Tips</a> 
            <a href="logout.php" class="btn btn-danger logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <h1>Dashboard Overview</h1>
            <div class="stats-grid">
                <a href="UserManagement.php" class="stat-card text-decoration-none">
                    <h3>Total Users</h3>
                    <p class="display-4"><?= $total_users; ?></p>
                </a>
                <a href="driver_detail.php" class="stat-card text-decoration-none">
                    <h3>Driver Count</h3>
                    <p class="display-4"><?= $driver_count; ?></p>
                </a>
                <a href="admin_review.php" class="stat-card text-decoration-none">
                    <h3>Review</h3>
                    <p class="display-4"><?= $review_count; ?></p>
                </a>
            </div>

            <!-- Driver Scheduling Form -->
            <div class="container-box">
                <h3>Schedule Drivers</h3>
                <?php if (isset($success_msg)) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
                <?php if (isset($error_msg)) echo "<div class='alert alert-danger'>$error_msg</div>"; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label>Driver 1 Name</label>
                        <input type="text" class="form-control" name="driver1_name" required>
                    </div>
                    <div class="mb-3">
                        <label>Driver 2 Name</label>
                        <input type="text" class="form-control" name="driver2_name" required>
                    </div>
                    <div class="mb-3">
                        <label>Schedule Date</label>
                        <input type="date" class="form-control" name="schedule_date" required>
                    </div>
                    <button type="submit" name="schedule_drivers" class="btn btn-primary">Schedule</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
