<?php
session_start();

require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Error: User not logged in. Please log in again.");
}

$userid = $_SESSION['user_id'];
$payment_success = false;
$payment_amount = 0;
$payment_failed = false;
$error_message = "";

if (isset($_SESSION['payment_success']) && $_SESSION['payment_success'] === true) {
    $payment_success = true;
    $payment_amount = isset($_SESSION['payment_amount']) ? $_SESSION['payment_amount'] : 0;
}

if (isset($_SESSION['payment_failed']) && $_SESSION['payment_failed'] === true) {
    $payment_failed = true;
    unset($_SESSION['payment_failed']);
}

try {
    // Get all paid bookings with their amounts and payment IDs
    $paid_bookings_query = "SELECT request_id, request_type, amount, razorpay_payment_id FROM tbl_payments 
                           WHERE userid = ? AND payment_status = 'completed'";
    $stmt = $conn->prepare($paid_bookings_query);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $paid_result = $stmt->get_result();
    
    // Create arrays to store paid bookings and amounts
    $paid_bookings = [];
    $paid_amounts = [];
    while($row = $paid_result->fetch_assoc()) {
        $paid_bookings[$row['request_type'] . '_' . $row['request_id']] = true;
        $paid_amounts[$row['request_type'] . '_' . $row['request_id']] = [
            'amount' => $row['amount'],
            'razorpay_payment_id' => $row['razorpay_payment_id']
        ];
    }

    // Fetch user details
    $user_query = "SELECT username, phoneno FROM tbl_user WHERE userid = ?";
    $stmt = $conn->prepare($user_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found.");
    }

    $patient_name = $user['username']; 
    $contact_phone = $user['phoneno'];

    // Fetch emergency bookings
    $emergency_query = "
        SELECT 
            request_id,
            userid,
            pickup_location,
            contact_phone,
            status,
            payment_status,
            created_at,
            ambulance_type,
            patient_name
        FROM tbl_emergency 
        WHERE userid = ? OR userid IS NULL
        ORDER BY created_at DESC";
        
    $stmt = $conn->prepare($emergency_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $emergency_bookings = $stmt->get_result();

    // Fetch prebookings
    $prebookings_query = "
        SELECT 
            prebookingid,
            userid,
            pickup_location,
            destination,
            service_type,
            service_time,
            ambulance_type,
            status,
            created_at
        FROM tbl_prebooking 
        WHERE userid = ? 
        ORDER BY created_at DESC";
        
    $stmt = $conn->prepare($prebookings_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $prebookings = $stmt->get_result();

    // Fetch palliative bookings
    $palliative_query = "
        SELECT 
            palliativeid,
            userid,
            address,
            medical_condition,
            status,
            created_at
        FROM tbl_palliative 
        WHERE userid = ? 
        ORDER BY created_at DESC";
        
    $stmt = $conn->prepare($palliative_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $palliative = $stmt->get_result();

} catch (Exception $e) {
    $error_message = "An error occurred while fetching your bookings. Please try again later.";
    error_log("Database error: " . $e->getMessage());
}

// Clear session variables after displaying success message
if ($payment_success) {
    unset($_SESSION['payment_success']);
    unset($_SESSION['payment_amount']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Status</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            margin-top: 80px;
            padding: 0px;
        }

        .container {
            padding: 20px;
            margin-top: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #2E8B57;
            border-bottom: 2px solid #2E8B57;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }

        .detail-label {
            font-weight: bold;
            color: #2E8B57;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f0f0f0;
            color: #2E8B57;
        }

        .btn {
            padding: 8px 16px;
            background-color: #2E8B57;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #3CB371;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            background-color: #FFF3CD;
            color: #856404;
        }

        .status-accepted {
            background-color: #D4EDDA;
            color: #155724;
        }

        .status-completed {
            background-color: #D1ECF1;
            color: #0C5460;
        }

        .status-cancelled {
            background-color: #F8D7DA;
            color: #721C24;
        }

        .status-paid {
            background-color: #28a745;
            color: white;
            cursor: pointer;
        }

        .status-paid:hover {
            background-color: #218838;
        }

        .error-message {
            background-color: #F8D7DA;
            color: #721C24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        @media (max-width: 768px) {
            .container {
                margin-left: 0;
            }

            .booking-details {
                grid-template-columns: 1fr;
            }
        }

        main {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'header.php'; ?>
        
        <!-- Rest of your existing content goes here -->
        <?php if ($payment_success): ?>
            <div class="alert alert-success" role="alert">
                Payment of ₹<?php echo number_format($payment_amount, 2); ?> was successful! Your booking status has been updated.
            </div>
        <?php endif; ?>

        <?php if ($payment_failed || isset($_GET['error'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php
                if (isset($_GET['message'])) {
                    echo htmlspecialchars($_GET['message']);
                } else {
                    echo "Payment failed. Please try again or contact support.";
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message && !$payment_success): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Emergency Requests -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <a href="user1.php" class="btn" style="margin-left: 10px; background-color: #2E8B57;">Back</a>
                <h2 style="margin: 0; flex-grow: 1; text-align: center;">Emergency Requests</h2>
            </div>
            <?php if ($emergency_bookings && $emergency_bookings->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Ambulance Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $emergency_bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                                <td><?php echo htmlspecialchars($booking['ambulance_type']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y, h:i A', strtotime($booking['created_at'])); ?></td>
                                <td>
                                    <?php if ($booking['status'] == 'Accepted' || $booking['status'] == 'Approved'): ?>
                                        <a href="user_view.php?request_id=<?php echo $booking['request_id']; ?>&type=emergency" class="btn btn-success btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php elseif ($booking['status'] == 'Completed'): ?>
                                        <?php if (isset($paid_bookings['emergency_' . $booking['request_id']])): ?>
                                            <span class="status-badge status-paid" 
                                                  onclick="showReceipt('<?php echo $paid_amounts['emergency_' . $booking['request_id']]['razorpay_payment_id']; ?>')">
                                                Paid (₹<?php echo number_format($paid_amounts['emergency_' . $booking['request_id']]['amount'], 2); ?>)
                                            </span>
                                        <?php else: ?>
                                            <button class="btn btn-success" onclick="proceedToPayment(<?php echo (int)$booking['request_id']; ?>, 'emergency')">
                                                Pay Now
                                            </button>
                                        <?php endif; ?>
                                    <?php elseif ($booking['status'] == 'Pending'): ?>
                                        <button class="btn btn-danger" onclick="cancelBooking(<?php echo (int)$booking['request_id']; ?>, 'emergency')">
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No emergency requests found.</p>
            <?php endif; ?>
        </div>

        <!-- Prebookings -->
        <div class="card">
            <h2>Prebookings</h2>
            <?php if ($prebookings && $prebookings->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Pickup Location</th>
                            <th>Destination</th>
                            <th>Service Type</th>
                            <th>Service Time</th>
                            <th>Ambulance Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $prebookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                                <td><?php echo htmlspecialchars($booking['destination']); ?></td>
                                <td><?php echo htmlspecialchars($booking['service_type']); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($booking['service_time'])); ?></td>
                                <td><?php echo htmlspecialchars($booking['ambulance_type']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y, h:i A', strtotime($booking['created_at'])); ?></td>
                                <td>
                                    <?php if ($booking['status'] == 'Accepted' || $booking['status'] == 'Approved'): ?>
                                        <a href="user_view.php?request_id=<?php echo $booking['prebookingid']; ?>&type=prebooking" class="btn btn-success btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php elseif ($booking['status'] == 'Completed'): ?>
                                        <?php if (isset($paid_bookings['prebooking_' . $booking['prebookingid']])): ?>
                                            <span class="status-badge status-paid" 
                                                  onclick="showReceipt('<?php echo $paid_amounts['prebooking_' . $booking['prebookingid']]['razorpay_payment_id']; ?>')">
                                                Paid (₹<?php echo number_format($paid_amounts['prebooking_' . $booking['prebookingid']]['amount'], 2); ?>)
                                            </span>
                                        <?php else: ?>
                                            <button class="btn btn-success" onclick="proceedToPayment(<?php echo (int)$booking['prebookingid']; ?>, 'prebooking')">
                                                Pay Now
                                            </button>
                                        <?php endif; ?>
                                    <?php elseif ($booking['status'] == 'Pending'): ?>
                                        <button class="btn btn-danger" onclick="cancelBooking(<?php echo (int)$booking['prebookingid']; ?>, 'prebooking')">
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No prebookings found.</p>
            <?php endif; ?>
        </div>

        <!-- Palliative Bookings -->
        <div class="card">
            <h2>Palliative Bookings</h2>
            <?php if ($palliative && $palliative->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Address</th>
                            <th>Medical Condition</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $palliative->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['address']); ?></td>
                                <td><?php echo htmlspecialchars($booking['medical_condition']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y, h:i A', strtotime($booking['created_at'])); ?></td>
                                <td>
                                    <?php if ($booking['status'] == 'Accepted' || $booking['status'] == 'Approved'): ?>
                                        <a href="user_view.php?request_id=<?php echo $booking['palliativeid']; ?>&type=palliative" class="btn btn-success btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php elseif ($booking['status'] == 'Completed'): ?>
                                        <?php if (isset($paid_bookings['palliative_' . $booking['palliativeid']])): ?>
                                            <span class="status-badge status-paid" 
                                                  onclick="showReceipt('<?php echo $paid_amounts['palliative_' . $booking['palliativeid']]['razorpay_payment_id']; ?>')">
                                                Paid (₹<?php echo number_format($paid_amounts['palliative_' . $booking['palliativeid']]['amount'], 2); ?>)
                                            </span>
                                        <?php else: ?>
                                            <button class="btn btn-success" onclick="proceedToPayment(<?php echo (int)$booking['palliativeid']; ?>, 'palliative')">
                                                Pay Now
                                            </button>
                                        <?php endif; ?>
                                    <?php elseif ($booking['status'] == 'Pending'): ?>
                                        <button class="btn btn-danger" onclick="cancelBooking(<?php echo (int)$booking['palliativeid']; ?>, 'palliative')">
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No palliative bookings found.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showReceipt(paymentId) {
            window.open('payment_receipt.php?payment_id=' + paymentId, '_blank');
        }

        function proceedToPayment(requestId, bookingType) {
            if (confirm('Do you want to proceed to payment for this completed service?')) {
                window.location.href = 'payment.php?request_id=' + requestId + '&booking_type=' + bookingType;
            }
        }

        function cancelBooking(requestId, bookingType) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                const formData = new FormData();
                formData.append('request_id', requestId);
                formData.append('booking_type', bookingType);
                
                fetch('cancel_booking.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking cancelled successfully');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to cancel booking');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the booking: ' + error.message);
                });
            }
        }

        // Refresh the page every 30 seconds to update status
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
