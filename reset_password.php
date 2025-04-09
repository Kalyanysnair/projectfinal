<?php
session_start();
include 'connect.php'; // Make sure this includes your DB connection

// Check if email & token are provided in the URL
if (!isset($_GET['email']) || !isset($_GET['token'])) {
    die("Missing email or token.");
}

// Decode email and token from the URL
$email = urldecode($_GET['email']);
$token = urldecode($_GET['token']);

// Fetch stored hashed token & expiry from the database
$stmt = $conn->prepare("SELECT reset_token, reset_expiry FROM tbl_user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// If the email exists in the database
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $hashedToken = $row['reset_token'];
    $expiry = $row['reset_expiry'];

    // Check if the provided token matches the stored token
    if (password_verify($token, $hashedToken)) {
        // Check if the token has expired
        if (strtotime($expiry) > time()) {
            // Token is valid and not expired - Store email in session and redirect
            $_SESSION['reset_email'] = $email; // Store the email to reset password later
            header("Location: new_password.php"); // Redirect to new_password.php
            exit(); // Ensure the script ends after the redirect
        } else {
            // Token expired
            echo "Token expired. Please request a new password reset.";
        }
    } else {
        // Invalid token
        echo "Invalid token. Please try again.";
    }
} else {
    // No reset request found for this email
    echo "No reset request found for this email.";
}

// Clean up
$stmt->close();
$conn->close();
?>
