<?php
session_start();
include 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo "unauthorized";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['driver_id'])) {
    $driver_id = $_POST['driver_id'];
    
    // Get the user ID and current status associated with this driver
    $stmt = $conn->prepare("SELECT u.userid, u.status 
                            FROM tbl_driver d 
                            JOIN tbl_user u ON d.userid = u.userid 
                            WHERE d.driver_id = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['userid'];
        $current_status = $row['status'];
        
        // Toggle the status
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';
        
        // Update the user status
        $updateStmt = $conn->prepare("UPDATE tbl_user SET status = ? WHERE userid = ?");
        $updateStmt->bind_param("si", $new_status, $user_id);
        
        if ($updateStmt->execute()) {
            echo $new_status; // Return the new status
        } else {
            echo "Error updating status: " . $conn->error;
        }
        
        $updateStmt->close();
    } else {
        echo "Driver not found";
    }
    
    $stmt->close();
} else {
    echo "Invalid request";
}

$conn->close();
?>