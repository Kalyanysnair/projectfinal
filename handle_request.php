<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include 'connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "driver") {
    header('Location: login.php');
    exit();
}

$request_data = null;
$error_message = '';
$success_message = '';
$request_time = '';

// Handle Emergency Request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["request_type"]) && $_POST["request_type"] === "emergency") {
    if (isset($_POST["request_id"])) {
        $request_id = (int)$_POST["request_id"];
        $driver_id = (int)$_SESSION["user_id"];
        $user_phone = $_POST["user_phone"];
        $user_name = $_POST["user_name"];

        if ($request_id === 0) {
            $error_message = "Invalid emergency request ID";
        } else {
            try {
                $mysqli->begin_transaction();

                // Get all emergency request details before updating
                $details_stmt = $mysqli->prepare("
                    SELECT request_id, userid, pickup_location, contact_phone, 
                           status, created_at, ambulance_type, patient_name
                    FROM tbl_emergency 
                    WHERE request_id = ?
                ");
                if ($details_stmt) {
                    $details_stmt->bind_param("i", $request_id);
                    $details_stmt->execute();
                    $request_data = $details_stmt->get_result()->fetch_assoc();
                    $request_time = $request_data['created_at'];
                }

                $update_stmt = $mysqli->prepare("UPDATE tbl_emergency SET status = ?, driver_id = ? WHERE request_id = ? AND status = ?");
                if (!$update_stmt) {
                    throw new Exception("Prepare failed: " . $mysqli->error);
                }

                $status = 'Accepted';
                $pending = 'Pending';
                $update_stmt->bind_param("siis", $status, $driver_id, $request_id, $pending);

                if (!$update_stmt->execute()) {
                    throw new Exception("Execute failed: " . $update_stmt->error);
                }

                if ($update_stmt->affected_rows > 0) {
                    $success_message = "Emergency request accepted successfully.";

                    // Refresh request data after update
                    $details_stmt->execute();
                    $request_data = $details_stmt->get_result()->fetch_assoc();
                } else {
                    $error_message = "Emergency request not found or already accepted.";
                }

                $mysqli->commit();
            } catch (Exception $e) {
                $mysqli->rollback();
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Missing emergency request ID";
    }
}

// Handle Prebooking Request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["request_type"]) && $_POST["request_type"] === "prebooking") {
    if (isset($_POST["request_id"])) {
        $request_id = (int)$_POST["request_id"];
        $driver_id = (int)$_SESSION["user_id"];
        $user_phone = $_POST["user_phone"];
        $user_name = $_POST["user_name"];

        if ($request_id === 0) {
            $error_message = "Invalid prebooking request ID";
        } else {
            try {
                $mysqli->begin_transaction();

                // Get all prebooking request details with patient_name from tbl_user
                $details_stmt = $mysqli->prepare("
                    SELECT p.prebookingid, p.userid, p.pickup_location, p.destination, 
                           p.service_type, p.service_time, p.ambulance_type, 
                           p.additional_requirements, p.comments, p.created_at, p.status,
                           u.username AS patient_name
                    FROM tbl_prebooking p
                    INNER JOIN tbl_user u ON p.userid = u.userid
                    WHERE p.prebookingid = ?
                ");
                if ($details_stmt) {
                    $details_stmt->bind_param("i", $request_id);
                    $details_stmt->execute();
                    $result = $details_stmt->get_result();
                    if ($result->num_rows > 0) {
                        $request_data = $result->fetch_assoc();
                        $request_time = $request_data['created_at'];
                    } else {
                        $error_message = "Prebooking request not found.";
                    }
                } else {
                    throw new Exception("Prepare failed: " . $mysqli->error);
                }

                $update_stmt = $mysqli->prepare("UPDATE tbl_prebooking SET status = ?, driver_id = ? WHERE prebookingid = ? AND status = ?");
                if (!$update_stmt) {
                    throw new Exception("Prepare failed: " . $mysqli->error);
                }

                $status = 'Accepted';
                $pending = 'Pending';
                $update_stmt->bind_param("siis", $status, $driver_id, $request_id, $pending);

                if (!$update_stmt->execute()) {
                    throw new Exception("Execute failed: " . $update_stmt->error);
                }
                if ($update_stmt->affected_rows > 0) {
                    $success_message = "Prebooking request accepted successfully.";
                
                    // Send email confirmation
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'kalyanys2004@gmail.com'; // Replace with your email
                        $mail->Password = 'ooqs zxti mult tlcb'; // Replace with your email password
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                
                        $mail->setFrom('kalyanys2004@gmail.com', 'SWIFTAID');
                
                        // Check if email and user_name exist in $request_data
                        $email = $request_data['email'] ?? 'no-email@example.com';
                        $user_name = $request_data['patient_name'] ?? 'Unknown User';
                
                        $mail->addAddress($email, $user_name);
                        $mail->Subject = "Prebooking Request Accepted";
                        $mail->Body = "Hello " . $user_name . ",\n\n" .
                                    "Your prebooking request has been accepted.\n\n" .
                                    "Service Time: " . $request_data['service_time'] . "\n" .
                                    "Ambulance Type: " . $request_data['ambulance_type'] . "\n" .
                                    "Pickup Location: " . $request_data['pickup_location'] . "\n" .
                                    "Destination: " . $request_data['destination'] . "\n" .
                                    "Service Type: " . $request_data['service_type'] . "\n\n" .
                                    "Best Regards,\nSWIFTAID";
                
                        $mail->send();
                        $success_message .= " Email confirmation sent.";
                    } catch (Exception $e) {
                        $error_message = "Mail could not be sent. Error: " . $mail->ErrorInfo;
                    }
                } else {
                    $error_message = "Prebooking request not found or already accepted.";
                }

                $mysqli->commit();
            } catch (Exception $e) {
                $mysqli->rollback();
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Missing prebooking request ID";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - SWIFTAID</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
        }
        .container-box {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            margin-top: 190px;
            border-radius: 15px;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .details-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        .details-table th,
        .details-table td {
            padding: 12px;
            border: 1px solid #dee2e6;
        }
        .details-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            width: 40%;
        }
        .dashboard-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 30px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .dashboard-btn:hover {
            background-color: #218838;
            color: white;
            text-decoration: none;
        }
        .btn-container {
            text-align: center;
            margin-top: 20px;
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

<div class="container-box">
    <h2 class="text-center mb-4">Request Details</h2>

    <?php if ($error_message): ?>
        <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($request_data): ?>
        <table class="details-table">
            <tr>
                <th>Patient Name</th>
                <td><?php echo htmlspecialchars($request_data['patient_name']); ?></td>
            </tr>
            <?php if (isset($_POST["request_type"]) && $_POST["request_type"] === "emergency"): ?>
                <tr>
                    <th>Request ID</th>
                    <td><?php echo htmlspecialchars($request_data['request_id']); ?></td>
                </tr>
                <tr>
                    <th>Contact Phone</th>
                    <td><?php echo htmlspecialchars($request_data['contact_phone']); ?></td>
                </tr>
                <tr>
                    <th>Pickup Location</th>
                    <td><?php echo htmlspecialchars($request_data['pickup_location']); ?></td>
                </tr>
                <tr>
                    <th>Ambulance Type</th>
                    <td><?php echo htmlspecialchars($request_data['ambulance_type']); ?></td>
                </tr>
                <tr>
                    <th>Request Time</th>
                    <td><?php echo htmlspecialchars($request_data['created_at']); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <th>Booking ID</th>
                    <td><?php echo htmlspecialchars($request_data['prebookingid']); ?></td>
                </tr>
                <tr>
                    <th>Pickup Location</th>
                    <td><?php echo htmlspecialchars($request_data['pickup_location']); ?></td>
                </tr>
                <tr>
                    <th>Destination</th>
                    <td><?php echo htmlspecialchars($request_data['destination']); ?></td>
                </tr>
                <tr>
                    <th>Service Type</th>
                    <td><?php echo htmlspecialchars($request_data['service_type']); ?></td>
                </tr>
                <tr>
                    <th>Service Time</th>
                    <td><?php echo htmlspecialchars($request_data['service_time']); ?></td>
                </tr>
                <tr>
                    <th>Ambulance Type</th>
                    <td><?php echo htmlspecialchars($request_data['ambulance_type']); ?></td>
                </tr>
                <tr>
                    <th>Additional Requirements</th>
                    <td><?php echo htmlspecialchars($request_data['additional_requirements'] ?: 'None'); ?></td>
                </tr>
                <tr>
                    <th>Comments</th>
                    <td><?php echo htmlspecialchars($request_data['comments'] ?: 'None'); ?></td>
                </tr>
                <tr>
                    <th>Request Time</th>
                    <td><?php echo htmlspecialchars($request_data['created_at']); ?></td>
                </tr>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    <div class="btn-container">
        <a href="driver.php" class="dashboard-btn">Return to Dashboard</a>
    </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>