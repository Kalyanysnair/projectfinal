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

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid request.");
}

$schedule_id = $_GET['id'];

// Fetch schedule details
$sql = "SELECT * FROM tbl_scheduled_drivers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Schedule not found.");
}

$schedule = $result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $driver1_name = trim($_POST['driver1_name']);
    $driver2_name = trim($_POST['driver2_name']);
    $schedule_date = $_POST['schedule_date'];

    if (empty($driver1_name) || empty($driver2_name) || empty($schedule_date)) {
        $error = "All fields are required.";
    } else {
        // Update query
        $update_sql = "UPDATE tbl_scheduled_drivers SET driver1_name = ?, driver2_name = ?, schedule_date = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $driver1_name, $driver2_name, $schedule_date, $schedule_id);

        if ($update_stmt->execute()) {
            header("Location: emergency_schedule.php?success=1");
            exit();
        } else {
            $error = "Failed to update schedule.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule - SwiftAid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg') no-repeat center center/cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container-box {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            max-width: 500px;
            width: 100%;
        }

        .btn-primary {
            background-color: #008374;
            border: none;
        }

        .btn-primary:hover {
            background-color: #00a28a;
        }
    </style>
</head>
<body>
    <div class="container-box">
        <h2>Edit Schedule</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="driver1_name" class="form-label">Driver 1 Name</label>
                <input type="text" class="form-control" name="driver1_name" value="<?= htmlspecialchars($schedule['driver1_name']); ?>" required>
            </div>

            <div class="mb-3">
                <label for="driver2_name" class="form-label">Driver 2 Name</label>
                <input type="text" class="form-control" name="driver2_name" value="<?= htmlspecialchars($schedule['driver2_name']); ?>" required>
            </div>

            <div class="mb-3">
                <label for="schedule_date" class="form-label">Schedule Date</label>
                <input type="date" class="form-control" name="schedule_date" value="<?= $schedule['schedule_date']; ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Update Schedule</button>
            <a href="emergency_schedule.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
