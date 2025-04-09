<?php
require_once 'connect.php';

try {
    // First check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'tbl_driver_leave'");
    if ($checkTable->num_rows == 0) {
        // First, let's check the structure of tbl_user
        $userTableQuery = $conn->query("SHOW CREATE TABLE tbl_user");
        if (!$userTableQuery) {
            throw new Exception("Could not check tbl_user structure: " . $conn->error);
        }

        // Drop table if it exists (to avoid conflicts)
        $conn->query("DROP TABLE IF EXISTS tbl_driver_leave");

        // Create the table with matching foreign key structure
        $sql = "CREATE TABLE tbl_driver_leave (
            leave_id INT(11) NOT NULL AUTO_INCREMENT,
            driver_id INT(6) UNSIGNED NOT NULL,
            leave_date DATE NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (leave_id),
            KEY driver_id (driver_id),
            CONSTRAINT tbl_driver_leave_ibfk_1 
            FOREIGN KEY (driver_id) REFERENCES tbl_user (userid) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if ($conn->query($sql) === TRUE) {
            echo "Table tbl_driver_leave created successfully";
        } else {
            throw new Exception("Error creating table: " . $conn->error);
        }
    } else {
        echo "Table tbl_driver_leave already exists";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    // Log the error for debugging
    error_log("Create Leave Table Error: " . $e->getMessage());
}

$conn->close();
?> 