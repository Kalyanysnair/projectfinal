<?php
session_start();
require 'connect.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}

$user_id = $_SESSION['user_id'];

// Check if user is a driver
$role_query = "SELECT role FROM tbl_user WHERE userid = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$user_role = $role_stmt->get_result()->fetch_assoc()['role'];

if ($user_role !== 'driver') {
    die(json_encode(['success' => false, 'message' => 'User is not a driver']));
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$request_id = isset($data['request_id']) ? intval($data['request_id']) : 0;
$booking_type = isset($data['booking_type']) ? $data['booking_type'] : '';

if (!$request_id || !$booking_type) {
    die(json_encode(['success' => false, 'message' => 'Invalid request data']));
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Update booking status based on type
    switch ($booking_type) {
        case 'emergency':
            $update_query = "UPDATE tbl_emergency SET status = 'Completed' WHERE request_id = ? AND driver_id = ?";
            break;
        case 'prebooking':
            $update_query = "UPDATE tbl_prebooking SET status = 'Completed' WHERE prebookingid = ? AND driver_id = ?";
            break;
        case 'palliative':
            $update_query = "UPDATE tbl_palliative SET status = 'Completed' WHERE palliativeid = ? AND driver_id = ?";
            break;
        default:
            throw new Exception('Invalid booking type');
    }

    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $request_id, $user_id);
    $stmt->execute();

    // Remove from active bookings
    $remove_query = "DELETE FROM tbl_active_bookings WHERE request_id = ? AND booking_type = ? AND driver_id = ?";
    $stmt = $conn->prepare($remove_query);
    $stmt->bind_param("isi", $request_id, $booking_type, $user_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error completing booking: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to complete booking']);
}
?> 