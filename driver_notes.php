<?php
session_start();
require 'connect.php';

// Check if user is logged in with all required session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header('Location: login.php');
    exit();
}

// Get user ID and role from session
$driver_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get username safely from database
$verify_query = "SELECT username FROM tbl_user WHERE userid = ? AND role = 'driver' LIMIT 1";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$driver_data = $result->fetch_assoc();
$username = $driver_data['username'];
$stmt->close();

// Get filter date (default to today)
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');

try {
    $query = "SELECT 
        p.prebookingid,
        p.userid,
        p.pickup_location,
        p.destination,
        p.service_time,
        DATE(p.service_time) as service_date,
        p.service_type,
        p.ambulance_type,
        p.additional_requirements,
        p.comments,
        u.username as patient_name,
        u.phoneno as contact_phone
    FROM tbl_prebooking p
    LEFT JOIN tbl_user u ON p.userid = u.userid
    WHERE p.driver_id = ? 
    AND p.status IN ('Accepted', 'Approved')
    AND DATE(p.service_time) = ?
    ORDER BY p.service_time ASC";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("is", $driver_id, $filter_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $prebookings = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    error_log("Database error in driver_notes.php: " . $e->getMessage());
    $prebookings = array();
    $error_message = "An error occurred while fetching the bookings.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Service Notes</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            padding: 20px;
            padding-top: 100px;
        }

        .glass-container {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin: 20px auto;
            max-width: 1200px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .notes-header {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .back-button {
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .back-button:hover {
            transform: translateX(-3px);
            background: white;
            color: #007bff;
        }

        .notes-header h2 {
            margin: 0;
            padding: 0;
            text-align: center;
            flex-grow: 1;
        }

        .date-filter {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 300px;
            justify-content: flex-end;
        }

        .date-filter label {
            font-weight: 600;
            color: #495057;
            margin: 0;
        }

        .date-filter input {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .prebooking-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 5px solid #007bff;
            transition: all 0.3s ease;
        }

        .prebooking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .time-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 15px;
            background: #e3f2fd;
            color: #0d47a1;
        }

        .detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .detail-item {
            background: rgba(255, 255, 255, 0.7);
            padding: 12px;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .detail-item:hover {
            background: rgba(255, 255, 255, 0.9);
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            color: #212529;
            word-break: break-word;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                align-items: center;
            }
            
            .date-filter {
                width: 100%;
                justify-content: center;
                min-width: unset;
            }

            .notes-header h2 {
                order: -1;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="glass-container">
        <div class="notes-header">
            <div class="header-content">
                <a href="driver.php" class="back-button">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <h2><i class="fas fa-clipboard-list"></i> My Service Notes</h2>
                <div class="date-filter">
                    <label for="filter_date">Filter by date:</label>
                    <input type="date" 
                           id="filter_date" 
                           name="filter_date" 
                           value="<?php echo $filter_date; ?>" 
                           class="form-control" 
                           onchange="window.location.href='?filter_date=' + this.value"
                           max="2025-12-31"
                           min="<?php echo date('Y-m-d', strtotime('-1 month')); ?>">
                </div>
            </div>
        </div>

        <?php if (empty($prebookings)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x mb-3 text-muted"></i>
                <p class="h5 text-muted">No prebookings found for this date.</p>
            </div>
        <?php else: ?>
            <?php foreach ($prebookings as $booking): ?>
                <div class="prebooking-card">
                    <div class="time-badge">
                        <i class="far fa-clock"></i>
                        <?php echo date('h:i A', strtotime($booking['service_time'])); ?>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fas fa-user"></i> Patient Name
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($booking['patient_name']); ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fas fa-phone"></i> Contact
                            </div>
                            <div class="detail-value">
                                <a href="tel:<?php echo htmlspecialchars($booking['contact_phone']); ?>" 
                                   class="btn btn-call btn-sm">
                                    <i class="fas fa-phone-alt"></i>
                                    <?php echo htmlspecialchars($booking['contact_phone']); ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fas fa-map-marker-alt text-danger"></i> Pickup Location
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($booking['pickup_location']); ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fas fa-map-pin text-success"></i> Drop Location
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($booking['destination']); ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fas fa-ambulance"></i> Service Details
                            </div>
                            <div class="detail-value">
                                Type: <?php echo htmlspecialchars($booking['service_type']); ?><br>
                                Ambulance: <?php echo htmlspecialchars($booking['ambulance_type']); ?>
                            </div>
                        </div>

                        <?php if (!empty($booking['additional_requirements'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fas fa-list"></i> Additional Requirements
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($booking['additional_requirements']); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($booking['comments'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fas fa-comment-alt"></i> Notes
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($booking['comments']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="action-buttons">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($booking['destination']); ?>&origin=<?php echo urlencode($booking['pickup_location']); ?>" 
                           target="_blank" 
                           class="btn btn-primary">
                            <i class="fas fa-route"></i> Get Directions
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html> 