<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== "driver") {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get the driver's userid
$driver_username = $_SESSION['username'];
$user_query = "SELECT userid FROM tbl_user WHERE username = ?";
$stmt = $mysqli->prepare($user_query);
$stmt->bind_param("s", $driver_username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userid = $user['userid'];

// Get upcoming prebookings for notifications
$notification_query = "SELECT p.*, u.username, u.phoneno 
                      FROM tbl_prebooking p 
                      JOIN tbl_user u ON p.userid = u.userid 
                      WHERE p.driver_id = ? 
                      AND p.status = 'Accepted'  
                      AND DATE(p.service_time) = CURDATE()
                      AND TIME(p.service_time) >= CURRENT_TIME()
                      ORDER BY p.service_time ASC";

$stmt = $mysqli->prepare($notification_query);
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($booking = $result->fetch_assoc()) {
    $service_time = strtotime($booking['service_time']);
    $current_time = time();
    $time_diff = $service_time - $current_time;
    
    // Check for both 30 minutes before and exact service time
    if ($time_diff > 0 && $time_diff <= 1800) { // 30 minutes before
        $notifications[] = [
            'type' => 'upcoming',
            'booking' => $booking,
            'minutes_left' => floor($time_diff / 60),
            'is_immediate' => false
        ];
    } elseif ($time_diff >= -60 && $time_diff <= 60) { // Within 1 minute of service time
        $notifications[] = [
            'type' => 'immediate',
            'booking' => $booking,
            'minutes_left' => 0,
            'is_immediate' => true
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['notifications' => $notifications]);
?> 