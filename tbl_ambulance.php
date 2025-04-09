<?php
include 'connect.php';

$database_name = "groovin";

// Select the database
mysqli_select_db($conn, $database_name);

$sql="CREATE TABLE tbl_ambulance (
    ambulance_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicleno VARCHAR(20) UNIQUE NOT NULL,
    ambulance_type VARCHAR(50) NOT NULL,
    status ENUM('available', 'on_duty', 'maintenance', 'unavailable') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (mysqli_query($conn, $sql)) {
    echo "Table tbl_ambulance created successfully";
} else {
    echo "Error creating table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>