<?php
include 'connect.php';

$database_name = "groovin";

// Select the database
mysqli_select_db($conn, $database_name);

$sql="CREATE TABLE tbl_driver (
    driver_id INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    lisenceno VARCHAR(255) NOT NULL,
    service_area VARCHAR(255) NOT NULL,
    vehicle_no VARCHAR(50) NOT NULL,
    ambulance_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (userid) REFERENCES tbl_user(userid) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql)) {
    echo "Table tbl_driver created successfully";
} else {
    echo "Error creating table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>