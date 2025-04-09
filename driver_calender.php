<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'driver') {
    header("Location: login.php");
    exit();
}

// Enable detailed error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'connect.php';

// Verify database connection
try {
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'Connection not established'));
    }

    // Get driver details
$username = $_SESSION['username'];
    $query = "SELECT d.*, u.userid, u.username, u.email, u.phoneno 
              FROM tbl_driver d 
              JOIN tbl_user u ON d.userid = u.userid 
              WHERE u.username = ?";

    if (!($stmt = $conn->prepare($query))) {
        throw new Exception("Failed to prepare driver query");
    }

    if (!$stmt->bind_param("s", $username)) {
        throw new Exception("Failed to bind parameters for driver query");
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute driver query");
    }

    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();

    if (!$driver) {
        throw new Exception("No driver record found for the current user");
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Driver Calendar Error: " . $e->getMessage());
    
    // Show a user-friendly error page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - SWIFTAID</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
        <style>
            body {
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f8f9fa;
            }
            .error-container {
                text-align: center;
                padding: 40px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                max-width: 500px;
                width: 90%;
            }
            .error-icon {
                font-size: 48px;
                color: #dc3545;
                margin-bottom: 20px;
            }
            .error-message {
                color: #6c757d;
                margin-bottom: 20px;
            }
            .btn-return {
                background: #0d6efd;
                color: white;
                padding: 10px 30px;
                border-radius: 5px;
                text-decoration: none;
                transition: background-color 0.3s;
            }
            .btn-return:hover {
                background: #0b5ed7;
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h2 class="mb-4">Oops! Something went wrong</h2>
            <p class="error-message">
                We're experiencing technical difficulties. Please try again in a few minutes.
            </p>
            <a href="driver.php" class="btn-return">
                Return to Dashboard
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// 2. Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_availability'])) {
    try {
        $availableDates = isset($_POST['available_dates']) ? $_POST['available_dates'] : [];
        
        // Convert to array if it's not already
        if (!is_array($availableDates)) {
            $availableDates = explode(",", $availableDates);
        }
        
        // Filter out empty values
        $availableDates = array_filter($availableDates);
        
        if (count($availableDates) !== 14) {
            throw new Exception("Please select exactly 14 days (2 weeks) of availability.");
        }
        
        if (!validateDateSequence($availableDates)) {
            throw new Exception("Please select 14 consecutive days starting from today or a future date.");
        }
        
        $conn->begin_transaction();
        
        // Delete existing availability
        $deleteQuery = "DELETE FROM tbl_driver_availability WHERE driver_id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        if (!$deleteStmt) {
            throw new Exception("Database error occurred while preparing delete statement.");
        }
        
        if (!$deleteStmt->bind_param("i", $driver['driver_id'])) {
            throw new Exception("Database error occurred while binding delete parameters.");
        }
        
        if (!$deleteStmt->execute()) {
            throw new Exception("Database error occurred while deleting existing availability.");
        }
        
        // Insert new availability
        $insertQuery = "INSERT INTO tbl_driver_availability (driver_id, available_date) VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        if (!$insertStmt) {
            throw new Exception("Database error occurred while preparing insert statement.");
        }
        
        foreach ($availableDates as $date) {
            if (!$insertStmt->bind_param("is", $driver['driver_id'], $date)) {
                throw new Exception("Database error occurred while binding insert parameters.");
            }
            
            if (!$insertStmt->execute()) {
                throw new Exception("Database error occurred while inserting availability.");
            }
        }
        
        $conn->commit();
        $_SESSION['successMessage'] = "Your 2-week availability has been updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Availability update failed: " . $e->getMessage());
        $errorMessage = $e->getMessage();
    }
}

// Check for success message
if (isset($_SESSION['successMessage'])) {
    $successMessage = $_SESSION['successMessage'];
    unset($_SESSION['successMessage']);
}

// Get driver's current availability
$availabilityQuery = "SELECT available_date FROM tbl_driver_availability 
                     WHERE driver_id = ? ORDER BY available_date";
$availabilityStmt = $conn->prepare($availabilityQuery);
if (!$availabilityStmt) {
    error_log("Prepare availability query failed: " . $conn->error);
    die("System error occurred. Please try again later.");
}

if (!$availabilityStmt->bind_param("i", $driver['driver_id'])) {
    error_log("Bind availability query failed: " . $availabilityStmt->error);
    die("System error occurred. Please try again later.");
}

if (!$availabilityStmt->execute()) {
    error_log("Execute availability query failed: " . $availabilityStmt->error);
    die("System error occurred. Please try again later.");
}

$availabilityResult = $availabilityStmt->get_result();
if (!$availabilityResult) {
    error_log("Get availability result failed: " . $availabilityStmt->error);
    die("System error occurred. Please try again later.");
}

$availableDates = [];
while ($row = $availabilityResult->fetch_assoc()) {
    $availableDates[] = $row['available_date'];
}

// Get today's assigned trips
$today = date('Y-m-d');
$assignedTripsQuery = "SELECT * FROM (
                         SELECT 'Emergency' as type, request_id as id, 
                                pickup_location, status, created_at 
                         FROM tbl_emergency 
                         WHERE driver_id = ? AND DATE(created_at) = ? AND status != 'Rejected'
                         UNION
                         SELECT 'Pre-booking' as type, prebookingid as id, 
                                pickup_location, status, created_at 
                         FROM tbl_prebooking 
                         WHERE driver_id = ? AND DATE(service_time) = ? AND status != 'Rejected'
                         UNION
                         SELECT 'Palliative' as type, palliativeid as id, 
                                address as pickup_location, status, created_at 
                         FROM tbl_palliative 
                         WHERE driver_id = ? AND DATE(created_at) = ? AND status != 'Rejected'
                       ) AS trips ORDER BY created_at DESC";

$tripsStmt = $conn->prepare($assignedTripsQuery);
if (!$tripsStmt) {
    error_log("Prepare trips query failed: " . $conn->error);
    die("System error occurred. Please try again later.");
}

if (!$tripsStmt->bind_param("isiisi", $driver['driver_id'], $today, $driver['driver_id'], $today, $driver['driver_id'], $today)) {
    error_log("Bind trips query failed: " . $tripsStmt->error);
    die("System error occurred. Please try again later.");
}

if (!$tripsStmt->execute()) {
    error_log("Execute trips query failed: " . $tripsStmt->error);
    die("System error occurred. Please try again later.");
}

$tripsResult = $tripsStmt->get_result();
if (!$tripsResult) {
    error_log("Get trips result failed: " . $tripsStmt->error);
    die("System error occurred. Please try again later.");
}

function validateDateSequence($dates) {
    if (count($dates) !== 14) return false;
    
    // Convert all dates to Y-m-d format and sort them
    $sortedDates = array_map(function($date) {
        return date('Y-m-d', strtotime($date));
    }, $dates);
    
    sort($sortedDates);
    
    // Check dates are sequential with no gaps
    $firstDate = new DateTime($sortedDates[0]);
    for ($i = 1; $i < 14; $i++) {
        $firstDate->modify('+1 day');
        if ($firstDate->format('Y-m-d') !== $sortedDates[$i]) {
            return false;
        }
    }
    return true;
}

function getStatusBadge($status) {
    switch($status) {
        case 'Pending': return 'warning';
        case 'Accepted': return 'info';
        case 'Completed': return 'success';
        case 'Cancelled': return 'danger';
        case 'Approved': return 'primary';
        case 'Rejected': return 'danger';
        default: return 'secondary';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Availability | Ambulance Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <style>
        .content-wrapper {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .date-pill {
            display: inline-block;
            margin: 5px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .available {
            background-color: #d4edda;
            color: #155724;
        }
        .dashboard-stat {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: white;
        }
        .bg-primary-gradient {
            background: linear-gradient(45deg, #4e73df 0%, #3a57e8 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(45deg, #1cc88a 0%, #0ba360 100%);
        }
        .bg-warning-gradient {
            background: linear-gradient(45deg, #f6c23e 0%, #f9a826 100%);
        }
        .trip-card {
            border-left: 4px solid #4e73df;
            transition: transform 0.3s;
        }
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .emergency {
            border-left-color: #e74a3b;
        }
        .pre-booking {
            border-left-color: #1cc88a;
        }
        .palliative {
            border-left-color: #f6c23e;
        }
        .card {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
        .form-text {
            color: #6c757d;
            font-size: 0.875em;
        }
    </style>
</head>
<body style="background: url('assets/img/ambulance-bg.jpg') no-repeat center center fixed; background-size: cover;">
    <?php include 'header.php'; ?>
    
    <div class="container py-4">
        <div class="content-wrapper">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2><i class="fas fa-calendar-alt me-2"></i> Driver Availability Management</h2>
                    <p class="text-muted">Submit your 2-week availability schedule</p>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#profileModal">
                        <i class="fas fa-user-circle me-2"></i> Driver Profile
                    </button>
                </div>
            </div>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i> Submit 2-Week Availability</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-4">
                                    <label for="available_dates" class="form-label">Select 14 Consecutive Days</label>
                                    <input type="text" id="available_dates" name="available_dates[]" class="form-control" placeholder="Select 14 consecutive days" multiple required>
                                    <div class="form-text">Please select exactly 14 consecutive days you will be available</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="mark_availability" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Submit Availability
                                    </button>
                                </div>
                            </form>
                            
                            <hr>
                            
                            <div class="mt-4">
                                <h6>Your Current Availability:</h6>
                                <div class="availability-display mt-3">
                                    <?php if (empty($availableDates)): ?>
                                        <p class="text-muted">You haven't submitted your 2-week availability yet.</p>
                                    <?php else: ?>
                                        <p>From <?php echo date('M d, Y', strtotime($availableDates[0])); ?> to <?php echo date('M d, Y', strtotime(end($availableDates))); ?></p>
                                        <?php foreach ($availableDates as $date): ?>
                                            <span class="date-pill available">
                                                <i class="fas fa-check-circle me-1"></i>
                                                <?php echo date('D, M d', strtotime($date)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Today's Assigned Trips</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($tripsResult && $tripsResult->num_rows > 0): ?>
                                <?php while ($trip = $tripsResult->fetch_assoc()): ?>
                                    <?php 
                                        $cardClass = '';
                                        $icon = 'fa-ambulance';
                                        switch($trip['type']) {
                                            case 'Emergency':
                                                $cardClass = 'emergency';
                                                $icon = 'fa-exclamation-circle';
                                                break;
                                            case 'Pre-booking':
                                                $cardClass = 'pre-booking';
                                                $icon = 'fa-calendar-check';
                                                break;
                                            case 'Palliative':
                                                $cardClass = 'palliative';
                                                $icon = 'fa-heartbeat';
                                                break;
                                        }
                                    ?>
                                    <div class="card mb-3 trip-card <?php echo $cardClass; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-1">
                                                    <i class="fas <?php echo $icon; ?> me-2"></i>
                                                    <?php echo htmlspecialchars($trip['type']); ?> #<?php echo htmlspecialchars($trip['id']); ?>
                                                </h6>
                                                <span class="badge bg-<?php echo getStatusBadge($trip['status']); ?>">
                                                    <?php echo htmlspecialchars($trip['status']); ?>
                                                </span>
                                            </div>
                                            <p class="mb-1">
                                                <i class="fas fa-map-marker-alt me-2 text-secondary"></i>
                                                <?php echo htmlspecialchars($trip['pickup_location']); ?>
                                            </p>
                                            <p class="small text-muted mb-0">
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo date('h:i A', strtotime($trip['created_at'])); ?>
                                            </p>
                                            <div class="mt-2">
                                                <a href="trip_details.php?type=<?php echo strtolower($trip['type']); ?>&id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-day fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mt-3">No trips assigned for today.</p>
                                    <p>Check the system regularly for new assignments.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Driver Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Driver Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-5x text-primary"></i>
                        <h5 class="mt-3"><?php echo htmlspecialchars($driver['username']); ?></h5>
                        <span class="badge bg-primary">Driver</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Email:</strong> <?php echo !empty($driver['email']) ? htmlspecialchars($driver['email']) : 'Not Specified'; ?></p>
                            <p><strong>Phone:</strong> <?php echo !empty($driver['phoneno']) ? htmlspecialchars($driver['phoneno']) : 'Not Specified'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>License:</strong> <?php echo !empty($driver['lisenceno']) ? htmlspecialchars($driver['lisenceno']) : 'Not Specified'; ?></p>
                            <p><strong>Vehicle:</strong> <?php echo !empty($driver['vehicle_no']) ? htmlspecialchars($driver['vehicle_no']) : 'Not Specified'; ?></p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <p><strong>Ambulance Type:</strong> <?php echo !empty($driver['ambulance_type']) ? htmlspecialchars($driver['ambulance_type']) : 'Not Specified'; ?></p>
                        <p><strong>Service Area:</strong> <?php echo !empty($driver['service_area']) ? htmlspecialchars($driver['service_area']) : 'Not Specified'; ?></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const availableDates = <?php echo json_encode($availableDates); ?>;
            
            flatpickr("#available_dates", {
                mode: "multiple",
                dateFormat: "Y-m-d",
                minDate: "today",
                maxDate: new Date().fp_incr(90), // 3 months in future
                defaultDate: availableDates,
                maxSelection: 14,
                onReady: function(selectedDates, dateStr, instance) {
                    // Ensure we don't exceed 14 selections
                    if (selectedDates.length > 14) {
                        instance.setDate(selectedDates.slice(0, 14));
                    }
                },
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 14) {
                        instance.setDate(selectedDates.slice(0, 14));
                    }
                }
            });
    });
    </script>
</body>
</html>