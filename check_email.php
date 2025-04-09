<?php
include 'connect.php';

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

$stmt = $conn->prepare("SELECT userid FROM tbl_user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "exists";
} else {
    echo "not_exists";
}
$stmt->close();
?>
