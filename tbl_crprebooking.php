<?php
include 'connect.php';

$database_name = "groovin";

// Select the database
mysqli_select_db($conn, $database_name);

$sql = "CREATE TABLE tbl_prebooking (
    prebookingid INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    userid INT(6) UNSIGNED,
    pickup_location VARCHAR(50) NOT NULL,
    destination VARCHAR(50) NOT NULL,
    service_type VARCHAR(50),
    service_time DATETIME,
    ambulance_type VARCHAR(50),
    additional_requirements VARCHAR(50),
    comments TEXT,
    status ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userid) REFERENCES tbl_user(userid)
)";

if (mysqli_query($conn, $sql)) {
    echo "Table tbl_prebooking created successfully";
} else {
    echo "Error creating table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>