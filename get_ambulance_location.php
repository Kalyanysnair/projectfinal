<?php
// Prevent any output before headers
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Start session and include database
session_start();
require_once 'connect.php';

// Clear any previous output
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Log the incoming request
error_log("Received request for ambulance location - request_id: " . $_GET['request_id'] . ", booking_type: " . $_GET['booking_type']);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if (!isset($_GET['request_id']) || !isset($_GET['booking_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$request_id = $_GET['request_id'];
$booking_type = $_GET['booking_type'];
$user_id = $_SESSION['user_id'];

error_log("Processing request - User ID: " . $user_id . ", Request ID: " . $request_id . ", Booking Type: " . $booking_type);

try {
    // Check database connection
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }

    // Get the driver ID based on booking type
    $driver_id = null;
    $query = "";
    
    switch ($booking_type) {
        case 'emergency':
            $query = "SELECT driver_id FROM tbl_emergency WHERE request_id = ? AND (userid = ? OR driver_id = ?)";
            break;
        case 'prebooking':
            $query = "SELECT driver_id FROM tbl_prebooking WHERE prebookingid = ? AND (userid = ? OR driver_id = ?)";
            break;
        case 'palliative':
            $query = "SELECT driver_id FROM tbl_palliative WHERE palliativeid = ? AND (userid = ? OR driver_id = ?)";
            break;
        default:
            throw new Exception('Invalid booking type');
    }

    error_log("Executing query: " . $query . " with params: request_id=" . $request_id . ", user_id=" . $user_id);
    
    // First, check if the booking exists and get driver_id
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed for booking query: " . $conn->error);
    }
    
    $stmt->bind_param("iii", $request_id, $user_id, $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for booking query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("No booking found for request_id: " . $request_id);
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }

    $row = $result->fetch_assoc();
    $driver_id = $row['driver_id'];
    error_log("Found driver_id: " . $driver_id);

    if (!$driver_id) {
        error_log("No driver assigned to booking");
        echo json_encode(['success' => false, 'message' => 'No driver assigned']);
        exit();
    }

    // Get driver's current location
    $driver_query = "SELECT latitude, longitude FROM tbl_user WHERE userid = ?";
    error_log("Checking driver location with query: " . $driver_query . " and driver_id: " . $driver_id);
    
    $stmt = $conn->prepare($driver_query);
    if (!$stmt) {
        throw new Exception("Prepare failed for driver query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $driver_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for driver query: " . $stmt->error);
    }
    
    $driver_result = $stmt->get_result();

    if ($driver_result->num_rows === 0) {
        error_log("Driver not found - driver_id: " . $driver_id);
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
        exit();
    }

    $driver_data = $driver_result->fetch_assoc();
    error_log("Driver data retrieved: " . print_r($driver_data, true));

    // Validate coordinates
    if (!isset($driver_data['latitude']) || !isset($driver_data['longitude']) || 
        !is_numeric($driver_data['latitude']) || !is_numeric($driver_data['longitude'])) {
        error_log("Invalid driver location data");
        echo json_encode(['success' => false, 'message' => 'Driver location not available']);
        exit();
    }

    $response = [
        'success' => true,
        'driver_logged_in' => true,
        'latitude' => floatval($driver_data['latitude']),
        'longitude' => floatval($driver_data['longitude'])
    ];
    error_log("Sending response: " . print_r($response, true));
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_ambulance_location.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 