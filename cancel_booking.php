<?php
session_start();
require 'connect.php';

// Set the proper content type for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request parameters are set
if (!isset($_POST['request_id']) || !isset($_POST['booking_type'])) {
    // Debug the incoming request
    error_log("Missing parameters. POST data: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$request_id = (int)$_POST['request_id'];
$booking_type = $_POST['booking_type'];
$user_id = $_SESSION['user_id'];

// Debug
error_log("Cancel request - Type: $booking_type, ID: $request_id, User: $user_id");

try {
    // Determine which table to update based on booking type
    switch ($booking_type) {
        case 'emergency':
            $table = 'tbl_emergency';
            $id_field = 'request_id';
            $status_value = 'Cancelled'; // Valid enum value
            break;
        case 'prebooking':
            $table = 'tbl_prebooking';
            $id_field = 'prebookingid';
            $status_value = 'Cancelled'; // Make sure this matches your enum
            break;
        case 'palliative':
            $table = 'tbl_palliative';
            $id_field = 'palliativeid';
            $status_value = 'Rejected'; // Valid enum value
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid booking type']);
            exit;
    }

    // First, verify that the booking belongs to the logged-in user and is in a cancellable state
    $verify_query = "SELECT userid, status FROM $table WHERE $id_field = ?";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("Booking not found: $table, $id_field = $request_id");
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
    $booking = $result->fetch_assoc();
    
    // Debug
    error_log("Booking found: " . print_r($booking, true));
    
    // Check if the booking is in a cancellable state
    if ($booking['status'] !== 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending bookings can be cancelled']);
        exit;
    }
    
    // For emergency bookings, the userid might be NULL
    if ($booking_type === 'emergency' && $booking['userid'] === NULL) {
        // Allow cancellation for emergency bookings with NULL userid
    } elseif ($booking['userid'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to cancel this booking']);
        exit;
    }
    
    // Update the booking status
    $update_query = "UPDATE $table SET status = ? WHERE $id_field = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $status_value, $request_id);
    $result = $stmt->execute();
    
    if ($result) {
        error_log("Booking cancelled successfully: $table, $id_field = $request_id, new status = $status_value");
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    } else {
        error_log("Database error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>