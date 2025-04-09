<?php
include 'connect.php';  // Database connection
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require 'vendor/autoload.php';

$email = isset($_POST['email']) ? trim($_POST['email']) : '';  // Get email from POST

if (empty($email)) {
    echo "Error: Email is required!";
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email format.";
    exit;
}

// Generate a verification token
$token = bin2hex(random_bytes(50));
$hashedToken = password_hash($token, PASSWORD_DEFAULT);
$expiry = date("Y-m-d H:i:s", strtotime("+1 day"));

// Store token in database
$stmt = $conn->prepare("INSERT INTO email_verifications (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token=?, expires_at=?");
$stmt->bind_param("sssss", $email, $hashedToken, $expiry, $hashedToken, $expiry);
$stmt->execute();

// Verification link
$verificationLink = "http://localhost/Groovin/verify_email.php?email=" . urlencode($email) . "&token=" . urlencode($token);

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'kalyanys2004@gmail.com'; // Your Gmail ID
    $mail->Password = 'ooqs zxti mult tlcb'; // Use App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('your_email@gmail.com', 'SWIFTAID');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Email Verification';
    $mail->Body = "Click the button below to verify your email:<br><br>
    <a href='$verificationLink' style='background:green; color:white; padding:10px; text-decoration:none;'>Verify Email</a>";

    if ($mail->send()) {
        echo "Verification email sent.";
    } else {
        echo "Email sending failed.";
    }
} catch (Exception $e) {
    echo "Mailer Error: " . $mail->ErrorInfo;
}
?>


