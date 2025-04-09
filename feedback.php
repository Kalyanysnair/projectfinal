<?php
session_start();
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "groovin";

// Establish database connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = ""; // Default empty email

// Check if the user is logged in and fetch the email
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    $query = "SELECT email, userid FROM tbl_user WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $email = $row['email'];  // Fetch user's email
    $user_id = $row['userid'];  // Fetch user ID using the correct column name
}
$stmt->close();
}

// Create tbl_review table if not exists with foreign key constraints
$create_table_query = "
   CREATE TABLE IF NOT EXISTS tbl_review (
    review_id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    driver_id INT(11) UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating INT(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES tbl_user(userid) ON DELETE CASCADE
);
";

$conn->query($create_table_query);
//FOREIGN KEY (driver_id) REFERENCES tbl_driver(driver_id) ON DELETE SET NULL
/// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $message = $_POST['message'];
    $rating = $_POST['rating'];

    // Assuming $user_id and $driver_id are correctly assigned
    $driver_id = NULL;  // Set driver_id as NULL for now
    $stmt = $conn->prepare("INSERT INTO tbl_review (user_id, name, message, rating) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $user_id, $name, $message, $rating);
    

    if ($stmt->execute()) {
        $success = "Thank you for your feedback!";
    } else {
        $error = "Error submitting feedback. Please try again.";
    }

    $stmt->close();
}


$conn->close();
?>

<!-- 

<div class="form-group">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required readonly>
</div> -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - SWIFTAID</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

    <style>
        /* Keeping your existing styles */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
          background-image: url('assets/assets/img/template/Groovin/hero-carousel/road.jpg');
          background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            color: #fff;
            justify-content: center;
            align-items: center;
           
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 128, 0, 0.8);
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        
        .feedback-container {
            background: rgba(243, 239, 239, 0.46);
            border-radius: 15px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            padding: 30px;
            width: 500px;
            text-align: center;
            color: #333;
            margin: 120px auto;
        }

        .feedback-container h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: rgb(247, 253, 247);
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            font-size: 16px;
            color: rgb(73, 74, 73);
            display: block;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            background: white;
            color: #333;
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
        }

        .submit-btn {
            background: #4CAF50;
            color: #fff;
            padding: 12px 30px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: #2E7D32;
        }

        .rating-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
        }

        .star-rating {
            display: none;
        }

        .star-label {
            color: #ddd;
            font-size: 24px;
            cursor: pointer;
        }

        .star-rating:checked ~ .star-label {
            color: #ffd700;
        }

        .success-message {
            background-color: rgba(76, 175, 80, 0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }

        .error-message {
            background-color: rgba(220, 53, 69, 0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
        .back-btn {
    display: inline-block;
    background: #4CAF50; /* Green */
    color: white;
    padding: 12px 30px;
    font-size: 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.3s ease;
    text-align: center;
    margin-top: 20px;
}

.back-btn:hover {
    background: #2E7D32; /* Darker green */
}

     
</style>



   
</head>
<body>
<?php include 'header.php'; ?>

    <div class="feedback-container">
        <h2 style="color:brown">Provide Your Feedback</h2>
        
        <?php if(isset($success)) { ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php } ?>
        
        <?php if(isset($error)) { ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php } ?>

        <form action="feedback.php" method="post">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required readonly>
                        </div>


            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" required></textarea>
            </div>

            <div class="form-group">
                <label>Rating</label>
                <div class="rating-group">
                    <?php for($i = 5; $i >= 1; $i--) { ?>
                        <input type="radio" name="rating" value="<?php echo $i; ?>" class="star-rating" id="star<?php echo $i; ?>" <?php echo $i == 5 ? 'checked' : ''; ?>>
                        <label for="star<?php echo $i; ?>" class="star-label">★</label>
                    <?php } ?>
                </div>
            </div>
           
            <button type="submit" class="submit-btn">Submit Feedback</button>
            <br> 
            <a href="user1.php" class="back-btn">Back</a>

        </form>
    </div>
</body>
</html>