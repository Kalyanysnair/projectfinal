<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration with correct variable names
$host = "localhost";
$username = "root";  // Your database username
$password = "";      // Your database password
$database = "groovin"; // Your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Keep the connection object for use in other files
$mysqli = $conn;
?>