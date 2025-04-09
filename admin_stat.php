<?php
// admin_report.php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'connect.php';

// Error handling function
function executeQuery($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        error_log("Query error: " . $conn->error . " in query: " . $query);
        return false;
    }
    return $result;
}

// Function to get total count of records in a table
function getTotalRecords($conn, $table) {
    $result = executeQuery($conn, "SELECT COUNT(*) as total FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Function to get total income from payments table
function getTotalIncome($conn) {
    $result = executeQuery($conn, "SELECT SUM(amount) as total_income FROM tbl_payments WHERE payment_status = 'completed'");
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total_income'] ?: 0; // Return 0 if null
    }
    return 0;
}

// Function to get enhanced payment status
function getEnhancedPaymentStatus($conn) {
    // Get completed payments from payments table
    $completedResult = executeQuery($conn, "SELECT COUNT(*) as completed FROM tbl_payments WHERE payment_status = 'completed'");
    $completedCount = $completedResult ? $completedResult->fetch_assoc()['completed'] : 0;

    // Get failed payments from payments table
    $failedResult = executeQuery($conn, "SELECT COUNT(*) as failed FROM tbl_payments WHERE payment_status = 'failed'");
    $failedCount = $failedResult ? $failedResult->fetch_assoc()['failed'] : 0;

    // Collect pending payments from service-specific tables
    $pendingPrebookings = executeQuery($conn, "SELECT COUNT(*) as pending FROM tbl_prebooking WHERE payment_status = 'Pending'");
    $pendingEmergency = executeQuery($conn, "SELECT COUNT(*) as pending FROM tbl_emergency WHERE payment_status = 'Pending'");
    $pendingPalliative = executeQuery($conn, "SELECT COUNT(*) as pending FROM tbl_palliative WHERE payment_status = 'Pending'");

    $pendingPrebookingsCount = $pendingPrebookings ? $pendingPrebookings->fetch_assoc()['pending'] : 0;
    $pendingEmergencyCount = $pendingEmergency ? $pendingEmergency->fetch_assoc()['pending'] : 0;
    $pendingPalliativeCount = $pendingPalliative ? $pendingPalliative->fetch_assoc()['pending'] : 0;

    $pendingCount = $pendingPrebookingsCount + $pendingEmergencyCount + $pendingPalliativeCount;

    return [
        'success' => $completedCount, 
        'failed' => $failedCount,
        'pending' => $pendingCount
    ];
}

// Function to get total number of service requests by type
function getServiceRequests($conn) {
    $prebookings = executeQuery($conn, "SELECT COUNT(*) as prebookings FROM tbl_prebooking");
    $emergency = executeQuery($conn, "SELECT COUNT(*) as emergency FROM tbl_emergency");
    $palliative = executeQuery($conn, "SELECT COUNT(*) as palliative FROM tbl_palliative");
    
    $prebookingsCount = $prebookings ? $prebookings->fetch_assoc()['prebookings'] : 0;
    $emergencyCount = $emergency ? $emergency->fetch_assoc()['emergency'] : 0;
    $palliativeCount = $palliative ? $palliative->fetch_assoc()['palliative'] : 0;
    
    return [
        'prebookings' => $prebookingsCount, 
        'emergency' => $emergencyCount, 
        'palliative' => $palliativeCount
    ];
}

// Function to get payment status by service type
function getPaymentsByServiceType($conn) {
    $prebookingsPaid = executeQuery($conn, "SELECT COUNT(*) as count FROM tbl_prebooking WHERE payment_status = 'Paid'");
    $prebookingsPending = executeQuery($conn, "SELECT COUNT(*) as count FROM tbl_prebooking WHERE payment_status = 'Pending'");
    
    $emergencyPaid = executeQuery($conn, "SELECT COUNT(*) as count FROM tbl_emergency WHERE payment_status = 'Paid'");
    $emergencyPending = executeQuery($conn, "SELECT COUNT(*) as count FROM tbl_emergency WHERE payment_status = 'Pending'");
    
    $palliativePaid = executeQuery($conn, "SELECT COUNT(*) as count FROM tbl_palliative WHERE payment_status = 'Paid'");
    $palliativePending = executeQuery($conn, "SELECT COUNT(*) as count FROM tbl_palliative WHERE payment_status = 'Pending'");
    
    return [
        'prebookings' => [
            'paid' => $prebookingsPaid ? $prebookingsPaid->fetch_assoc()['count'] : 0,
            'pending' => $prebookingsPending ? $prebookingsPending->fetch_assoc()['count'] : 0
        ],
        'emergency' => [
            'paid' => $emergencyPaid ? $emergencyPaid->fetch_assoc()['count'] : 0,
            'pending' => $emergencyPending ? $emergencyPending->fetch_assoc()['count'] : 0
        ],
        'palliative' => [
            'paid' => $palliativePaid ? $palliativePaid->fetch_assoc()['count'] : 0,
            'pending' => $palliativePending ? $palliativePending->fetch_assoc()['count'] : 0
        ]
    ];
}

// Function to get total number of reviews and average rating
function getReviews($conn) {
    $totalReviews = executeQuery($conn, "SELECT COUNT(*) as total_reviews FROM tbl_review");
    $averageRating = executeQuery($conn, "SELECT AVG(rating) as avg_rating FROM tbl_review");
    
    $totalReviewsCount = $totalReviews ? $totalReviews->fetch_assoc()['total_reviews'] : 0;
    $avgRating = $averageRating ? $averageRating->fetch_assoc()['avg_rating'] : 0;
    
    return [
        'total_reviews' => $totalReviewsCount, 
        'avg_rating' => round($avgRating, 1) // Round to 1 decimal place
    ];
}

// Function to calculate ETA based on coordinates
function calculateETA($driverLat, $driverLong, $destinationLat, $destinationLong) {
    // Simple distance calculation - in a real app, this would use Google Maps API
    // This is just a placeholder calculation
    if (!$driverLat || !$driverLong || !$destinationLat || !$destinationLong) {
        return 15; // Default 15 minutes if coordinates are missing
    }
    
    // Calculate distance using Haversine formula
    $earthRadius = 6371; // in kilometers
    
    $latDelta = deg2rad($destinationLat - $driverLat);
    $longDelta = deg2rad($destinationLong - $driverLong);
    
    $a = sin($latDelta/2) * sin($latDelta/2) +
         cos(deg2rad($driverLat)) * cos(deg2rad($destinationLat)) *
         sin($longDelta/2) * sin($longDelta/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;
    
    // Estimate time: assume average speed of 40 km/h
    $timeInHours = $distance / 40;
    $timeInMinutes = ceil($timeInHours * 60);
    
    return min(max($timeInMinutes, 5), 120); // Between 5 and 120 minutes
}

// Get all metrics
try {
    $totalUsers = getTotalRecords($conn, 'tbl_user');
    $totalDrivers = getTotalRecords($conn, 'tbl_driver');
    $totalIncome = getTotalIncome($conn);
    $paymentStatus = getEnhancedPaymentStatus($conn);
    $serviceRequests = getServiceRequests($conn);
    $paymentsByService = getPaymentsByServiceType($conn);
    $reviews = getReviews($conn);
} catch (Exception $e) {
    error_log("Error in admin report: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: rgb(83, 223, 78);
            --secondary-color: #1cc88a;
            --dark-color: #5a5c69;
            --light-color: #f8f9fc;
        }
        
        body {
            background-color: var(--light-color);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding-top: 20px;
            color: var(--dark-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .report-header {
            background-color: #f8f9fc;
            border-bottom: 2px solid #4CAF50;
            padding: 20px 0;
            margin-bottom: 30px;
            width: 100%;
            text-align: center;
        }
        
        .report-title {
            color: #333;
            font-weight: bold;
            text-align: center;
        }
        
        .report-date {
            color: #6c757d;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .export-btn {
            background-color: #4CAF50;
            color: white;
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            background-color: #45a049;
            transform: scale(1.05);
        }
        
        .metric-card {
            border-left: 4px solid;
            border-radius: 4px;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.15);
            background-color: white;
            margin-bottom: 24px;
            padding: 20px 15px;
            position: relative;
            transition: transform 0.2s;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
        
        .metric-card.primary { border-left-color: var(--primary-color); }
        .metric-card.success { border-left-color: var(--secondary-color); }
        .metric-card.warning { border-left-color: #f6c23e; }
        .metric-card.danger { border-left-color: #e74a3b; }
        .metric-card.info { border-left-color: #36b9cc; }
        
        .metric-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: #2e59d9;
        }
        
        .metric-card p {
            font-size: 0.9rem;
            color: #858796;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .metric-card i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            color: #dddfeb;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.15);
            margin-bottom: 24px;
            padding: 20px;
        }
        
        .chart-container h4 {
            color: var(--dark-color);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .chart-container canvas {
            max-height: 250px;
        }
        
        .no-data-message {
            text-align: center;
            padding: 50px 20px;
            color: #858796;
        }
        
        .no-data-message i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dddfeb;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                margin-bottom: 15px;
            }
            
            .metric-card {
                margin-bottom: 15px;
            }
        }

        /* Remove all sidebar-related styles */
        .main-content {
            width: 100%;
            padding: 20px;
            background-color: var(--light-color);
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Report Header -->
        <div class="report-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-12">
                        <h1 class="report-title">Overall Statistics</h1>
                        <p class="report-date">
                            Generated on: <?php echo date('F d, Y '); ?>
                        </p>
                    </div>
                    <div class="col-12 text-center mt-3">
                        <a href="admin.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="export_report.php?format=pdf" class="btn export-btn">
                            <i class="fas fa-file-export"></i> Export Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Metrics Section -->
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="metric-card primary">
                        <h3><?php echo number_format($totalUsers); ?></h3>
                        <p>Total Users</p>
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="metric-card success">
                        <h3><?php echo number_format($totalDrivers); ?></h3>
                        <p>Total Drivers</p>
                        <i class="fas fa-car"></i>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="metric-card warning">
                        <h3>$<?php echo number_format($totalIncome, 2); ?></h3>
                        <p>Total Revenue</p>
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="metric-card info">
                        <h3><?php echo number_format($reviews['total_reviews']); ?></h3>
                        <p>Customer Reviews</p>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h4><i class="fas fa-chart-pie me-2"></i> Payment Status Overview</h4>
                        <?php if ($paymentStatus['success'] == 0 && $paymentStatus['failed'] == 0 && $paymentStatus['pending'] == 0): ?>
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>No payment data available</p>
                            </div>
                        <?php else: ?>
                            <canvas id="paymentStatusChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h4><i class="fas fa-chart-bar me-2"></i> Service Requests Distribution</h4>
                        <?php if ($serviceRequests['prebookings'] == 0 && $serviceRequests['emergency'] == 0 && $serviceRequests['palliative'] == 0): ?>
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>No service request data available</p>
                            </div>
                        <?php else: ?>
                            <canvas id="serviceRequestsChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Service Metrics -->
            <div class="row">
                <div class="col-md-4">
                    <div class="metric-card primary">
                        <h3><?php echo number_format($serviceRequests['prebookings']); ?></h3>
                        <p>Pre-bookings</p>
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card danger">
                        <h3><?php echo number_format($serviceRequests['emergency']); ?></h3>
                        <p>Emergency Requests</p>
                        <i class="fas fa-ambulance"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card info">
                        <h3><?php echo number_format($serviceRequests['palliative']); ?></h3>
                        <p>Palliative Services</p>
                        <i class="fas fa-heartbeat"></i>
                    </div>
                </div>
            </div>

            <!-- User and Driver Details Section -->
            <div class="row mt-4">
                <!-- User Statistics -->
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h4><i class="fas fa-users me-2"></i> User Statistics</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User Type</th>
                                        <th>Count</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Query to get user statistics
                                    $userQuery = "SELECT 
                                                CASE 
                                                    WHEN role = 'user' THEN 'Regular Users'
                                                    WHEN role = 'driver' THEN 'Drivers'
                                                    WHEN role = 'admin' THEN 'Administrators'
                                                    ELSE role
                                                END as user_type,
                                                COUNT(*) as count,
                                                status
                                                FROM tbl_user 
                                                GROUP BY role, status
                                                ORDER BY role, status";
                                    
                                    $userResult = $conn->query($userQuery);
                                    if ($userResult && $userResult->num_rows > 0) {
                                        while ($user = $userResult->fetch_assoc()) {
                                            $statusClass = $user['status'] == 'active' ? 'success' : 'warning';
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($user['user_type']) . "</td>";
                                            echo "<td>" . number_format($user['count']) . "</td>";
                                            echo "<td><span class='badge bg-{$statusClass}'>" . ucfirst($user['status']) . "</span></td>";
                                            echo "<td>
                                                    <a href='admin_users.php?type=" . strtolower($user['user_type']) . "' 
                                                       class='btn btn-sm btn-primary'>
                                                        <i class='fas fa-eye'></i> View
                                                    </a>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Driver Details -->
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h4><i class="fas fa-car me-2"></i> Driver Details</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Driver Name</th>
                                        <th>Vehicle</th>
                                        <th>Status</th>
                                        <th>Current Location</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Query to get driver details
                                    $driverQuery = "SELECT 
                                                d.driver_id,
                                                u.username,
                                                d.vehicle_no,
                                                d.service_area,
                                                u.status,
                                                u.latitude,
                                                u.longitude,
                                                (
                                                    SELECT COUNT(*) 
                                                    FROM (
                                                        SELECT request_id FROM tbl_emergency WHERE driver_id = d.driver_id AND status IN ('Accepted', 'Pending')
                                                        UNION ALL
                                                        SELECT prebookingid FROM tbl_prebooking WHERE driver_id = d.driver_id AND status NOT IN ('Completed', 'Cancelled')
                                                        UNION ALL
                                                        SELECT palliativeid FROM tbl_palliative WHERE driver_id = d.driver_id AND status NOT IN ('Completed', 'Rejected')
                                                    ) as active_requests
                                                ) as active_requests
                                                FROM tbl_driver d
                                                JOIN tbl_user u ON d.userid = u.userid
                                                WHERE u.role = 'driver'
                                                ORDER BY u.username";
                                    
                                    $driverResult = $conn->query($driverQuery);
                                    if ($driverResult && $driverResult->num_rows > 0) {
                                        while ($driver = $driverResult->fetch_assoc()) {
                                            $statusClass = $driver['status'] == 'active' ? 'success' : 'warning';
                                            $location = $driver['latitude'] && $driver['longitude'] ? 
                                                "<i class='fas fa-map-marker-alt text-danger'></i> " . 
                                                number_format($driver['latitude'], 4) . ", " . 
                                                number_format($driver['longitude'], 4) : 
                                                "<span class='text-muted'>Not available</span>";
                                            
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($driver['username']) . "</td>";
                                            echo "<td>" . htmlspecialchars($driver['vehicle_no']) . "</td>";
                                            echo "<td>
                                                    <span class='badge bg-{$statusClass}'>" . ucfirst($driver['status']) . "</span>
                                                    " . ($driver['active_requests'] > 0 ? 
                                                        "<span class='badge bg-info ms-1'>" . $driver['active_requests'] . " active</span>" : "") . "
                                                  </td>";
                                            echo "<td>" . $location . "</td>";
                                            echo "<td>
                                                    <a href='user_view.php?driver_id=" . $driver['driver_id'] . "' 
                                                       class='btn btn-sm btn-primary me-1'>
                                                        <i class='fas fa-eye'></i>
                                                    </a>
                                                    <a href='admin_driver_requests.php?driver_id=" . $driver['driver_id'] . "' 
                                                       class='btn btn-sm btn-info'>
                                                        <i class='fas fa-list'></i>
                                                    </a>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Request Statistics -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="chart-container">
                        <h4><i class="fas fa-chart-line me-2"></i> Service Request Statistics</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Emergency Requests</h5>
                                        <h2 class="text-danger"><?php echo number_format($serviceRequests['emergency']); ?></h2>
                                        <p class="card-text">Active emergency service requests</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Pre-bookings</h5>
                                        <h2 class="text-primary"><?php echo number_format($serviceRequests['prebookings']); ?></h2>
                                        <p class="card-text">Scheduled pre-bookings</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Palliative Services</h5>
                                        <h2 class="text-info"><?php echo number_format($serviceRequests['palliative']); ?></h2>
                                        <p class="card-text">Active palliative care requests</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Status by Service Type -->
            <div class="row mt-4">
                <div class="col-lg-12">
                    <div class="chart-container">
                        <h4><i class="fas fa-money-bill-wave me-2"></i> Payment Status by Service Type</h4>
                        <canvas id="paymentByServiceChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Customer Satisfaction -->
            <div class="row mt-4">
                <div class="col-lg-12">
                    <div class="chart-container">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0"><i class="fas fa-smile me-2"></i> Customer Satisfaction</h4>
                            <div class="badge bg-success p-2">
                                <i class="fas fa-star me-1"></i>
                                <?php echo number_format($reviews['avg_rating'], 1); ?>/5
                            </div>
                        </div>
                        <?php if ($reviews['total_reviews'] > 0): ?>
                            <div class="progress" style="height: 25px;">
                                <?php 
                                    $ratingPercentage = ($reviews['avg_rating'] / 5) * 100;
                                    $ratingClass = 'bg-danger';
                                    
                                    if ($ratingPercentage >= 80) {
                                        $ratingClass = 'bg-success';
                                    } elseif ($ratingPercentage >= 60) {
                                        $ratingClass = 'bg-info';
                                    } elseif ($ratingPercentage >= 40) {
                                        $ratingClass = 'bg-warning';
                                    }
                                ?>
                                <div class="progress-bar <?php echo $ratingClass; ?>" role="progressbar" 
                                     style="width: <?php echo $ratingPercentage; ?>%" 
                                     aria-valuenow="<?php echo $ratingPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo number_format($reviews['avg_rating'], 1); ?> / 5
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <small class="text-muted">Based on <?php echo number_format($reviews['total_reviews']); ?> customer reviews</small>
                            </div>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>No review data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Add html2canvas and jsPDF libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <!-- Chart.js Scripts -->
    <script>
        // Set Chart.js defaults
        Chart.defaults.font.family = "'Nunito', 'Segoe UI', 'Roboto', 'Arial', sans-serif";
        Chart.defaults.color = '#5a5c69';
        
        // Add export functionality
        function exportToPDF() {
            // Show loading overlay
            const loadingOverlay = document.createElement('div');
            loadingOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            `;
            loadingOverlay.innerHTML = `
                <div style="text-align: center;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Generating PDF...</p>
                </div>
            `;
            document.body.appendChild(loadingOverlay);

            // Get the main content
            const element = document.querySelector('.main-content');
            
            // Configure html2canvas
            const options = {
                scale: 2,
                useCORS: true,
                logging: false,
                allowTaint: true,
                backgroundColor: '#ffffff',
                windowWidth: element.scrollWidth,
                windowHeight: element.scrollHeight
            };

            // Generate PDF
            html2canvas(element, options).then(canvas => {
                const imgData = canvas.toDataURL('image/jpeg', 1.0);
                const pdf = new jspdf.jsPDF({
                    orientation: 'portrait',
                    unit: 'px',
                    format: [canvas.width, canvas.height]
                });

                pdf.addImage(imgData, 'JPEG', 0, 0, canvas.width, canvas.height);
                pdf.save('admin_report.pdf');
                
                // Remove loading overlay
                document.body.removeChild(loadingOverlay);
            });
        }

        // Update the export button click handler
        document.querySelector('.export-btn').addEventListener('click', function(e) {
            e.preventDefault();
            exportToPDF();
        });
        
        <?php if ($paymentStatus['success'] > 0 || $paymentStatus['failed'] > 0 || $paymentStatus['pending'] > 0): ?>
        // Payment Status Chart
        const paymentStatusChart = new Chart(document.getElementById('paymentStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Failed', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $paymentStatus['success']; ?>, 
                        <?php echo $paymentStatus['failed']; ?>,
                        <?php echo $paymentStatus['pending']; ?>
                    ],
                    backgroundColor: ['#1cc88a', '#e74a3b', '#f6c23e'],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        <?php if ($serviceRequests['prebookings'] > 0 || $serviceRequests['emergency'] > 0 || $serviceRequests['palliative'] > 0): ?>
        // Service Requests Chart
        const serviceRequestsChart = new Chart(document.getElementById('serviceRequestsChart'), {
            type: 'bar',
            data: {
                labels: ['Pre-bookings', 'Emergency', 'Palliative'],
                datasets: [{
                    label: 'Number of Requests',
                    data: [
                        <?php echo $serviceRequests['prebookings']; ?>, 
                        <?php echo $serviceRequests['emergency']; ?>, 
                        <?php echo $serviceRequests['palliative']; ?>
                    ],
                    backgroundColor: ['#4e73df', '#e74a3b', '#36b9cc'],
                    borderWidth: 0,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Payment Status by Service Type Chart
        const paymentByServiceChart = new Chart(document.getElementById('paymentByServiceChart'), {
            type: 'bar',
            data: {
                labels: ['Pre-bookings', 'Emergency', 'Palliative'],
                datasets: [
                    {
                        label: 'Paid',
                        data: [
                            <?php echo $paymentsByService['prebookings']['paid']; ?>,
                            <?php echo $paymentsByService['emergency']['paid']; ?>,
                            <?php echo $paymentsByService['palliative']['paid']; ?>
                        ],
                        backgroundColor: '#1cc88a',
                        borderWidth: 0,
                        borderRadius: 5
                    },
                    {
                        label: 'Pending',
                        data: [
                            <?php echo $paymentsByService['prebookings']['pending']; ?>,
                            <?php echo $paymentsByService['emergency']['pending']; ?>,
                            <?php echo $paymentsByService['palliative']['pending']; ?>
                        ],
                        backgroundColor: '#f6c23e',
                        borderWidth: 0,
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: false,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        stacked: false,
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    </script>
</body>
</html>