<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo 'unauthorized';
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "groovin";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo 'connection_error';
    exit();
}

// Handle user deletion (status update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userid = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if ($userid) {
        $sql = "UPDATE tbl_user SET status = 'inactive' WHERE userid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userid);
        
        if ($stmt->execute()) {
            echo 'success';
        } else {
            echo 'failed';
        }
        $stmt->close();
    } else {
        echo 'invalid_id';
    }
} else {
    echo 'no_id_provided';
}

$conn->close();
?>