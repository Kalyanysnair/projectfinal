<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require 'connect.php';
require 'vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;

// Get user ID from URL safely
if (!isset($_GET['userid']) || !filter_var($_GET['userid'], FILTER_VALIDATE_INT)) {
    die("Invalid or missing user ID.");
}
$userid = intval($_GET['userid']);

// Function to safely execute prepared statements
function executeQuery($conn, $query, $param_type = null, $param_value = null) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed for query: $query. Error: " . $conn->error);
    }
    
    if ($param_type && $param_value !== null) {
        $stmt->bind_param($param_type, $param_value);
    }
    
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    return $stmt->get_result();
}

try {
    // Fetch user details
    $user_result = executeQuery($conn, 
        "SELECT userid, username, email, phoneno, role, status, created_at, 
                latitude, longitude, google_id" 
        . " FROM tbl_user WHERE userid = ?", "i", $userid);
    $user = $user_result->fetch_assoc();

    if (!$user) {
        die("User not found.");
    }

    // Fetch emergency requests
    $emergency_result = executeQuery($conn, 
        "SELECT request_id, pickup_location, status, created_at, 
                patient_name, ambulance_type, amount, payment_status" 
        . " FROM tbl_emergency WHERE userid = ?", "i", $userid);

    // Fetch prebooking requests
    $prebooking_result = executeQuery($conn, 
        "SELECT prebookingid, pickup_location, destination, status, 
                service_type, service_time, amount, payment_status" 
        . " FROM tbl_prebooking WHERE userid = ?", "i", $userid);

    // Fetch palliative requests
    $palliative_result = executeQuery($conn, 
        "SELECT palliativeid, address, medical_condition, status, 
                created_at, ambulance_type, amount, payment_status" 
        . " FROM tbl_palliative WHERE userid = ?", "i", $userid);

    // Generate HTML content for PDF
    ob_start();
    ?>
    <style>
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #28a745; color: white; }
        h1 { color: #28a745; text-align: center; }
        h2 { color: #28a745; margin-top: 20px; }
        .info-grid { margin: 15px 0; }
        .info-item { margin: 5px 0; }
        .info-label { font-weight: bold; color: #495057; }
    </style>

    <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
        <h1>User Profile - SwiftAid</h1>
        
        <h2>Personal Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">User ID:</span> <?php echo htmlspecialchars($user['userid']); ?>
            </div>
            <div class="info-item">
                <span class="info-label">Username:</span> <?php echo htmlspecialchars($user['username']); ?>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span> <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>
            </div>
            <div class="info-item">
                <span class="info-label">Phone:</span> <?php echo htmlspecialchars($user['phoneno']); ?>
            </div>
            <div class="info-item">
                <span class="info-label">Role:</span> <?php echo htmlspecialchars($user['role']); ?>
            </div>
            <div class="info-item">
                <span class="info-label">Status:</span> <?php echo htmlspecialchars($user['status']); ?>
            </div>
            <div class="info-item">
                <span class="info-label">Created At:</span> <?php echo htmlspecialchars($user['created_at']); ?>
            </div>
            <?php if ($user['latitude'] && $user['longitude']): ?>
            <div class="info-item">
                <span class="info-label">Location:</span> <?php echo htmlspecialchars($user['latitude'] . ', ' . $user['longitude']); ?>
            </div>
            <?php endif; ?>
        </div>

        <h2>Emergency Requests</h2>
        <table>
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Patient Name</th>
                    <th>Pickup Location</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Payment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($emergency_result->num_rows > 0) {
                    while ($emergency = $emergency_result->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($emergency['request_id']) . "</td>
                                <td>" . htmlspecialchars($emergency['patient_name']) . "</td>
                                <td>" . htmlspecialchars($emergency['pickup_location']) . "</td>
                                <td>" . htmlspecialchars($emergency['status']) . "</td>
                                <td>" . htmlspecialchars($emergency['amount'] ?? 'N/A') . "</td>
                                <td>" . htmlspecialchars($emergency['payment_status']) . "</td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No emergency requests found</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <h2>Pre-Booking Requests</h2>
        <table>
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Pickup Location</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Payment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($prebooking_result->num_rows > 0) {
                    while ($prebooking = $prebooking_result->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($prebooking['prebookingid']) . "</td>
                                <td>" . htmlspecialchars($prebooking['pickup_location']) . "</td>
                                <td>" . htmlspecialchars($prebooking['destination']) . "</td>
                                <td>" . htmlspecialchars($prebooking['status']) . "</td>
                                <td>" . htmlspecialchars($prebooking['amount'] ?? 'N/A') . "</td>
                                <td>" . htmlspecialchars($prebooking['payment_status']) . "</td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No pre-booking requests found</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <h2>Palliative Care Requests</h2>
        <table>
            <thead>
                <tr>
                    <th>Palliative ID</th>
                    <th>Address</th>
                    <th>Medical Condition</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Payment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($palliative_result->num_rows > 0) {
                    while ($palliative = $palliative_result->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($palliative['palliativeid']) . "</td>
                                <td>" . htmlspecialchars($palliative['address']) . "</td>
                                <td>" . htmlspecialchars($palliative['medical_condition']) . "</td>
                                <td>" . htmlspecialchars($palliative['status']) . "</td>
                                <td>" . htmlspecialchars($palliative['amount'] ?? 'N/A') . "</td>
                                <td>" . htmlspecialchars($palliative['payment_status']) . "</td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No palliative care requests found</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <page_footer>
            <div style="text-align: center; font-size: 12px; color: #666;">
                Generated on <?php echo date('F d, Y, h:i A'); ?> | SwiftAid Ambulance Service
            </div>
        </page_footer>
    </page>
    <?php
    $content = ob_get_clean();

    // Generate PDF
    $html2pdf = new Html2Pdf('P', 'A4', 'en');
    $html2pdf->writeHTML($content);
    
    // Output PDF
    $filename = 'user_profile_' . $userid . '_' . date('Y-m-d') . '.pdf';
    $html2pdf->output($filename, 'D');

} catch (Html2PdfException $e) {
    die($e->getMessage());
} catch (Exception $e) {
    die($e->getMessage());
}

$conn->close();
?> 