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
error_log("Received request for user location");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
error_log("User ID: " . $user_id);

try {
    // Check database connection
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }

    // Get user's location from emergency booking
    $query = "SELECT pickup_location, latitude, longitude FROM tbl_emergency WHERE userid = ? AND status IN ('Accepted', 'Approved') ORDER BY created_at DESC LIMIT 1";
    error_log("Executing query: " . $query . " with user_id: " . $user_id);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("No active emergency booking found for user: " . $user_id);
        echo json_encode(['success' => false, 'message' => 'No active booking found']);
        exit();
    }
    
    $booking_data = $result->fetch_assoc();
    error_log("Booking data retrieved: " . print_r($booking_data, true));
    
    // Use the coordinates from the booking
    if (isset($booking_data['latitude']) && isset($booking_data['longitude']) && 
        is_numeric($booking_data['latitude']) && is_numeric($booking_data['longitude'])) {
        $response = [
            'success' => true,
            'latitude' => floatval($booking_data['latitude']),
            'longitude' => floatval($booking_data['longitude'])
        ];
        error_log("Sending response: " . print_r($response, true));
        echo json_encode($response);
    } else {
        error_log("No valid coordinates found in booking data");
        echo json_encode(['success' => false, 'message' => 'Location data not available']);
    }
    
} catch (Exception $e) {
    error_log("Error in get_user_location.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 