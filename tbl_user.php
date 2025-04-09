<?php
include 'connect.php';

$database_name = "groovin";

// Select the database
mysqli_select_db($conn, $database_name);

$sql = "CREATE TABLE tbl_user(
    userid INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,username VARCHAR(50) NOT NULL,password VARCHAR(50) NOT NULL,
    email VARCHAR(50),phoneno int,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "Table user created successfully";
} else {
    echo "Error creating table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>