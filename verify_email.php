<?php
session_start();
include 'connect.php';

if (!isset($_GET['email']) || !isset($_GET['token'])) {
    die("Invalid verification link.");
}

$email = urldecode($_GET['email']);
$token = urldecode($_GET['token']);

// Fetch token from database
$stmt = $conn->prepare("SELECT token FROM email_verifications WHERE email=? AND expires_at > NOW()");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $hashedToken = $row['token'];

    if (password_verify($token, $hashedToken)) {
        // Mark email as verified
        $updateStmt = $conn->prepare("UPDATE tbl_user SET email_verified = 1 WHERE email=?");
        $updateStmt->bind_param("s", $email);
        $updateStmt->execute();
        $updateStmt->close();

        $_SESSION['verified_email'] = $email;
        $_SESSION['email_verified'] = 1;

        // Redirect to signup with email retained
        header("Location: signup.php?email=" . urlencode($email) . "&verified=true");
        exit();
    }
}
die("Invalid verification link or expired.");
?>
