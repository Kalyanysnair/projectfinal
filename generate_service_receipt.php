<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['request_id']) || !isset($_GET['request_type'])) {
    header("Location: driverpreviousJob.php");
    exit();
}

$request_id = $_GET['request_id'];
$request_type = $_GET['request_type'];
$driver_id = $_SESSION['user_id'];

try {
    // Query to get service details based on request type
    switch($request_type) {
        case 'emergency':
            $query = "SELECT e.*, 
                     u.username as user_name, u.email as user_email, u.phoneno as user_phone,
                     d.username as driver_name, d.email as driver_email, d.phoneno as driver_phone,
                     e.pickup_location, e.created_at, e.amount, e.status,
                     COALESCE(e.payment_status, 'Pending') as payment_status
                     FROM tbl_emergency e
                     JOIN tbl_user u ON e.userid = u.userid
                     JOIN tbl_user d ON e.driver_id = d.userid
                     WHERE e.request_id = ? AND e.driver_id = ?";
            break;
            
        case 'prebooking':
            $query = "SELECT p.*, 
                     u.username as user_name, u.email as user_email, u.phoneno as user_phone,
                     d.username as driver_name, d.email as driver_email, d.phoneno as driver_phone,
                     p.pickup_location, p.created_at, p.amount, p.status,
                     COALESCE(p.payment_status, 'Pending') as payment_status
                     FROM tbl_prebooking p
                     JOIN tbl_user u ON p.userid = u.userid
                     JOIN tbl_user d ON p.driver_id = d.userid
                     WHERE p.prebookingid = ? AND p.driver_id = ?";
            break;
            
        case 'palliative':
            $query = "SELECT p.*, 
                     u.username as user_name, u.email as user_email, u.phoneno as user_phone,
                     d.username as driver_name, d.email as driver_email, d.phoneno as driver_phone,
                     p.address as pickup_location, p.created_at, p.amount, p.status,
                     COALESCE(p.payment_status, 'Pending') as payment_status
                     FROM tbl_palliative p
                     JOIN tbl_user u ON p.userid = u.userid
                     JOIN tbl_user d ON p.driver_id = d.userid
                     WHERE p.palliativeid = ? AND p.driver_id = ?";
            break;
            
        default:
            throw new Exception("Invalid request type");
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("ii", $request_id, $driver_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $service = $result->fetch_assoc();

    if (!$service) {
        throw new Exception("Service not found");
    }

    // Generate receipt number
    $receipt_number = 'SWIFTAID-' . strtoupper(substr($request_type, 0, 3)) . '-' . 
                     str_pad($request_id, 6, '0', STR_PAD_LEFT);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Service Receipt - <?php echo htmlspecialchars($receipt_number); ?></title>
        <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                padding: 20px;
                max-width: 800px;
                margin: 0 auto;
                background: #f8f9fa;
            }
            .receipt-container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .receipt-header {
                text-align: center;
                margin: -30px -30px 30px -30px;
                padding: 30px 20px;
                background: #28a745;
                color: white;
                border-radius: 10px 10px 0 0;
                position: relative;
            }
            .receipt-header img {
                max-width: 50px;
                height: auto;
                margin-bottom: 10px;
            }
            .receipt-header h1 {
                font-size: 24px;
                margin: 0;
                padding: 5px 0;
            }
            .receipt-header h2 {
                font-size: 20px;
                margin: 0;
                padding: 5px 0;
                opacity: 0.9;
            }
            .receipt-header p {
                margin: 5px 0;
                font-size: 14px;
                opacity: 0.9;
            }
            .receipt-section {
                margin-bottom: 25px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
            }
            .receipt-section h3 {
                color: #28a745;
                margin-bottom: 15px;
                font-size: 1.2rem;
                font-weight: bold;
            }
            .receipt-row {
                display: flex;
                margin-bottom: 8px;
            }
            .receipt-label {
                font-weight: bold;
                width: 150px;
                color: #495057;
            }
            .receipt-value {
                color: #212529;
            }
            .receipt-footer {
                text-align: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 2px solid #eee;
                font-size: 0.9rem;
                color: #6c757d;
            }
            .print-button {
                display: block;
                width: 200px;
                margin: 20px auto;
                padding: 10px;
                background: #28a745;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                text-align: center;
                text-decoration: none;
            }
            .print-button:hover {
                background: #218838;
                color: white;
                text-decoration: none;
            }
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                .receipt-container {
                    box-shadow: none;
                }
                .print-button {
                    display: none;
                }
            }
            .logo-container {
                background: white;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 10px;
                padding: 5px;
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="receipt-header">
                <div class="logo-container">
                    <img src="assets/img/SWIFTAID2.png" alt="SwiftAid Logo">
                </div>
                <h1>SwiftAid Ambulance Service</h1>
                <h2>Service Receipt</h2>
                <p>Receipt Number: <?php echo htmlspecialchars($receipt_number); ?></p>
                <p>Date: <?php echo date('d M Y, h:i A', strtotime($service['created_at'])); ?></p>
            </div>

            <div class="receipt-section">
                <h3>Service Information</h3>
                <div class="receipt-row">
                    <div class="receipt-label">Service Type:</div>
                    <div class="receipt-value"><?php echo ucfirst(htmlspecialchars($request_type)); ?></div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Service Status:</div>
                    <div class="receipt-value">
                        <span class="status-badge <?php echo strtolower($service['status']); ?>">
                            <?php echo htmlspecialchars($service['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Payment Status:</div>
                    <div class="receipt-value">
                        <span class="status-badge <?php echo strtolower($service['payment_status'] ?? 'pending'); ?>">
                            <?php echo htmlspecialchars($service['payment_status'] ?? 'Pending'); ?>
                        </span>
                    </div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Amount:</div>
                    <div class="receipt-value">₹<?php echo number_format($service['amount'], 2); ?></div>
                </div>
            </div>

            <div class="receipt-section">
                <h3>User Information</h3>
                <div class="receipt-row">
                    <div class="receipt-label">Name:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($service['user_name']); ?></div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Email:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($service['user_email']); ?></div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Phone:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($service['user_phone']); ?></div>
                </div>
            </div>

            <div class="receipt-section">
                <h3>Driver Information</h3>
                <div class="receipt-row">
                    <div class="receipt-label">Name:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($service['driver_name']); ?></div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Email:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($service['driver_email']); ?></div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Phone:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($service['driver_phone']); ?></div>
                </div>
            </div>

            <div class="receipt-section">
                <h3>Service Details</h3>
                <div class="receipt-row">
                    <div class="receipt-label">Location:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($service['pickup_location']); ?></div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Service Date:</div>
                    <div class="receipt-value"><?php echo date('d M Y, h:i A', strtotime($service['created_at'])); ?></div>
                </div>
            </div>

            <div class="receipt-footer">
                <p>This is a computer-generated receipt and does not require a signature.</p>
                <p>Thank you for using SwiftAid Ambulance Service</p>
            </div>
        </div>

        <button onclick="window.print()" class="print-button">Print Receipt</button>

    </body>
    </html>
    <?php

} catch (Exception $e) {
    error_log("Receipt generation error: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to generate receipt: " . $e->getMessage();
    header("Location: driverpreviousJob.php");
    exit();
}
?> 