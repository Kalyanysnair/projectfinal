<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'driver') {
    header("Location: login.php");
    exit();
}

require_once 'connect.php';

$successMessage = '';
$errorMessage = '';

// Get driver's userid first
try {
    $username = $_SESSION['username'];
    $userQuery = "SELECT userid FROM tbl_user WHERE username = ?";
    $stmt = $conn->prepare($userQuery);
    if (!$stmt) {
        throw new Exception("Database error occurred");
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    $driver_id = $user['userid'];
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

// Fetch leave requests for the current driver
$leaveHistoryQuery = "SELECT * FROM tbl_driver_leave 
                     WHERE driver_id = ? 
                     ORDER BY created_at DESC";
$historyStmt = $conn->prepare($leaveHistoryQuery);
$historyStmt->bind_param("i", $driver_id);
$historyStmt->execute();
$leaveHistory = $historyStmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    try {
        if (!isset($driver_id)) {
            throw new Exception("Unable to identify driver");
        }

        $leave_date = $_POST['leave_date'];
        $reason = $_POST['reason'];

        // Validate leave date
        if (strtotime($leave_date) < strtotime('today')) {
            throw new Exception("Leave date cannot be in the past");
        }

        // Check if leave already exists for this date
        $checkQuery = "SELECT leave_id FROM tbl_driver_leave 
                      WHERE driver_id = ? AND leave_date = ?";
        $checkStmt = $conn->prepare($checkQuery);
        if (!$checkStmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $checkStmt->bind_param("is", $driver_id, $leave_date);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            throw new Exception("You have already applied for leave on this date");
        }

        // Insert new leave request
        $query = "INSERT INTO tbl_driver_leave (driver_id, leave_date, reason, status) 
                 VALUES (?, ?, ?, 'Pending')";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("iss", $driver_id, $leave_date, $reason);
        
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }

        if ($stmt->affected_rows > 0) {
            $successMessage = "Leave application submitted successfully!";
        } else {
            throw new Exception("Failed to submit leave application. No rows affected.");
        }

    } catch (Exception $e) {
        error_log("Leave Application Error: " . $e->getMessage());
        $errorMessage = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Leave Application - SWIFTAID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        /* Enhanced styles for leave management page */

body {
    min-height: 100vh;
    background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), 
                      url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    padding-top: 80px;
    font-family: 'Roboto', sans-serif;
}

.content-wrapper {
    background-color: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    margin: 30px auto;
    max-width: 800px;
    position: relative;
    transition: all 0.3s ease;
}

.dashboard-header {
    margin-bottom: 30px;
    text-align: center;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 20px;
}

.dashboard-header .page-title {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 10px;
}

.dashboard-header .text-muted {
    color: #7f8c8d;
    font-size: 16px;
}

.back-button {
    position: absolute;
    top: 25px;
    left: 25px;
    font-size: 20px;
    color: #3498db;
    text-decoration: none;
    transition: all 0.3s;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.back-button:hover {
    color: #fff;
    background-color: #3498db;
    transform: translateX(-5px);
}


/* Status Overview Cards */
.status-overview {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    gap: 15px;
}

.status-card {
    flex: 1;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.status-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.status-card.pending {
    background: linear-gradient(135deg, #f39c12, #f1c40f);
    color: #fff;
}

.status-card.approved {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: #fff;
}

.status-card.rejected {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: #fff;
}

.status-icon {
    font-size: 28px;
    margin-right: 15px;
}

.status-details h3 {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
    line-height: 1;
}

.status-details p {
    margin: 5px 0 0;
    font-size: 14px;
    opacity: 0.9;
}

/* Alert Messages */
.alert {
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 25px;
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-left: 5px solid #28a745;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border-left: 5px solid #dc3545;
}

/* Tabs Styling */
.content-tabs {
    margin-top: 20px;
}

.nav-tabs {
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 25px;
}

.nav-tabs .nav-link {
    border: none;
    font-weight: 600;
    color: #6c757d;
    padding: 12px 20px;
    border-radius: 0;
    position: relative;
    transition: all 0.3s;
}

.nav-tabs .nav-link.active {
    color: #3498db;
    background-color: transparent;
}

.nav-tabs .nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: #3498db;
}

.nav-tabs .nav-link:hover {
    color: #3498db;
    border-color: transparent;
}

/* Form Styling */
.form-container {
    padding: 10px;
}

.form-label {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 10px;
}

.form-control {
    border-radius: 8px;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    font-size: 15px;
    transition: all 0.3s;
    box-shadow: none;
}

.form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.15);
}

.form-text {
    color: #7f8c8d;
    font-size: 13px;
    margin-top: 6px;
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.submit-btn {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    border: none;
    padding: 14px 30px;
    color: white;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    margin-top: 10px;
    box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
}

.submit-btn:hover {
    background: linear-gradient(135deg, #27ae60, #219a52);
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(46, 204, 113, 0.4);
}

.submit-btn:active {
    transform: translateY(0);
}

/* Leave History Timeline */
.leave-timeline {
    position: relative;
    margin-top: 20px;
    padding-left: 30px;
}

.leave-timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 15px;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: #fff;
    border: 3px solid #bdc3c7;
    top: 15px;
    z-index: 1;
}

.timeline-item.status-pending .timeline-marker {
    border-color: #f39c12;
}

.timeline-item.status-approved .timeline-marker {
    border-color: #2ecc71;
}

.timeline-item.status-rejected .timeline-marker {
    border-color: #e74c3c;
}

.timeline-content {
    position: relative;
}

.leave-card {
    background-color: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s;
}

.leave-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.leave-card-header {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.date-info {
    display: flex;
    align-items: center;
}

.date-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    background-color: #3498db;
    color: white;
    border-radius: 10px;
    margin-right: 15px;
    box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
}

.date-day {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
}

.date-month {
    font-size: 14px;
    font-weight: 500;
}

.date-details {
    display: flex;
    flex-direction: column;
}

.day-name {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.full-date {
    font-size: 14px;
    color: #7f8c8d;
}

.status-badge {
    padding: 8px 12px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.status-badge.status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-badge.status-approved {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.status-rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.status-icon {
    margin-right: 6px;
}

.leave-card-body {
    padding: 20px;
}

.reason-label {
    font-weight: 600;
    margin-bottom: 10px;
    color: #2c3e50;
}

.reason-text {
    color: #34495e;
    margin-bottom: 15px;
    line-height: 1.6;
}

.request-meta {
    font-size: 13px;
    color: #7f8c8d;
    border-top: 1px solid #eee;
    padding-top: 15px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 60px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state h4 {
    font-weight: 600;
    margin-bottom: 10px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .content-wrapper {
        padding: 25px 20px;
        margin: 15px;
    }
    
    .status-overview {
        flex-direction: column;
    }
    
    .status-card {
        margin-bottom: 15px;
    }
    
    .date-badge {
        width: 50px;
        height: 50px;
    }
    
    .date-day {
        font-size: 20px;
    }
    
    .back-button {
        top: 15px;
        left: 15px;
    }
    
    .dashboard-header .page-title {
        font-size: 24px;
        margin-top: 20px;
    }
}
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="content-wrapper">
            <a href="driver.php" class="back-button">
                <i class="fas fa-chevron-left"></i>
            </a>

            <!-- Dashboard Header -->
            <div class="leave-dashboard-header">
                <div class="header-content">
                    <h2 class="leave-page-title text-center">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Leave Management
                    </h2>
                    <p class="leave-subtitle text-center">Manage and track your leave requests</p>
                </div>
            </div>

            <!-- Status Overview Cards -->
            <div class="status-overview">
                <?php
                $pending = 0;
                $approved = 0;
                $rejected = 0;
                if ($leaveHistory) {
                    while ($row = $leaveHistory->fetch_assoc()) {
                        switch($row['status']) {
                            case 'Pending': $pending++; break;
                            case 'Approved': $approved++; break;
                            case 'Rejected': $rejected++; break;
                        }
                    }
                    $leaveHistory->data_seek(0); // Reset pointer
                }
                ?>
                <div class="status-card pending">
                    <div class="status-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="status-details">
                        <h3><?php echo $pending; ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                <div class="status-card approved">
                    <div class="status-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="status-details">
                        <h3><?php echo $approved; ?></h3>
                        <p>Approved Leaves</p>
                    </div>
                </div>
                <div class="status-card rejected">
                    <div class="status-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="status-details">
                        <h3><?php echo $rejected; ?></h3>
                        <p>Rejected Requests</p>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Main Content Tabs -->
            <div class="content-tabs">
                <ul class="nav nav-tabs" id="leaveTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="new-request-tab" data-bs-toggle="tab" 
                                data-bs-target="#new-request" type="button" role="tab">
                            <i class="fas fa-plus-circle me-2"></i>New Request
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" 
                                data-bs-target="#history" type="button" role="tab">
                            <i class="fas fa-history me-2"></i>Leave History
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="leaveTabContent">
                    <!-- New Request Tab -->
                    <div class="tab-pane fade show active" id="new-request" role="tabpanel">
                        <div class="form-container">
                            <form method="POST" action="" class="leave-form">
                                <div class="mb-4">
                                    <label for="leave_date" class="form-label">Select Leave Date</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="leave_date" 
                                           name="leave_date" 
                                           required 
                                           min="<?php echo date('Y-m-d'); ?>">
                                    <div class="form-text">Choose a future date for your leave request</div>
                                </div>

                                <div class="mb-4">
                                    <label for="reason" class="form-label">Reason for Leave</label>
                                    <textarea class="form-control" 
                                              id="reason" 
                                              name="reason" 
                                              placeholder="Please provide a detailed reason for your leave request"
                                              required></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit_leave" class="submit-btn">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Leave Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- History Tab -->
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <?php if ($leaveHistory && $leaveHistory->num_rows > 0): ?>
                            <div class="leave-timeline">
                                <?php while ($leave = $leaveHistory->fetch_assoc()): ?>
                                    <div class="timeline-item status-<?php echo strtolower($leave['status']); ?>">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <div class="leave-card">
                                                <div class="leave-card-header">
                                                    <div class="date-info">
                                                        <div class="date-badge">
                                                            <span class="date-day"><?php echo date('d', strtotime($leave['leave_date'])); ?></span>
                                                            <span class="date-month"><?php echo date('M', strtotime($leave['leave_date'])); ?></span>
                                                        </div>
                                                        <div class="date-details">
                                                            <div class="day-name"><?php echo date('l', strtotime($leave['leave_date'])); ?></div>
                                                            <div class="full-date"><?php echo date('F d, Y', strtotime($leave['leave_date'])); ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="status-badge status-<?php echo strtolower($leave['status']); ?>">
                                                        <i class="status-icon fas <?php 
                                                            echo match($leave['status']) {
                                                                'Pending' => 'fa-clock',
                                                                'Approved' => 'fa-check-circle',
                                                                'Rejected' => 'fa-times-circle',
                                                                default => 'fa-info-circle'
                                                            };
                                                        ?>"></i>
                                                        <?php echo htmlspecialchars($leave['status']); ?>
                                                    </div>
                                                </div>
                                                <div class="leave-card-body">
                                                    <h6 class="reason-label">Reason for Leave</h6>
                                                    <p class="reason-text"><?php echo htmlspecialchars($leave['reason']); ?></p>
                                                    <div class="request-meta">
                                                        <span class="request-time">
                                                            <i class="fas fa-clock"></i>
                                                            Requested: <?php echo date('M d, Y h:i A', strtotime($leave['created_at'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h4>No Leave History</h4>
                                <p>You haven't submitted any leave requests yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker
        flatpickr("#leave_date", {
            minDate: "today",
            dateFormat: "Y-m-d"
        });
    </script>
</body>
</html> 