<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate request parameters
if (!isset($_POST['latitude']) || !isset($_POST['longitude'])) {
    echo json_encode(['success' => false, 'message' => 'Missing location parameters']);
    exit();
}

$latitude = floatval($_POST['latitude']);
$longitude = floatval($_POST['longitude']);

try {
    // First verify that this user is a driver
    $role_query = "SELECT role FROM tbl_user WHERE userid = ?";
    $stmt = $conn->prepare($role_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['role'] !== 'driver' && $user['role'] !== 'palliative') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    // Update only location coordinates and last active time
    $update_query = "UPDATE tbl_user SET 
                    latitude = ?, 
                    longitude = ?, 
                    last_active = NOW() 
                    WHERE userid = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ddi", $latitude, $longitude, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
    } else {
        throw new Exception("Failed to update location");
    }

} catch (Exception $e) {
    error_log("Error in update_driver_location.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 