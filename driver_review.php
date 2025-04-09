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

// Fetch all reviews
$query = "SELECT r.review_id, u.username, r.message, r.rating, r.created_at 
          FROM tbl_review r
          JOIN tbl_user u ON r.user_id = u.userid
          ORDER BY r.created_at DESC";

$result = $conn->query($query);

// Count the number of reviews
$review_count = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - User Reviews</title>

    <!-- External Stylesheets -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/road.jpg');
            background-size: cover;
            background-position: center;
        }

        .admin-container {
            max-width: 800px;
            margin: 100px auto;
            background: rgba(255, 255, 255, 0.2); /* Transparent box */
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        h2 {
            color: brown;
            margin-bottom: 20px;
        }

        .review-box {
            background: rgba(0, 0, 0, 0.6);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            color: #fff;
        }

        .review-box strong {
            font-size: 18px;
            color: #ffcc00;
        }

        .rating {
            color: #ffcc00;
        }

        .back-btn {
            display: inline-block;
            background: #4CAF50;
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
            background: #2E7D32;
        }

        .review-count {
            font-size: 18px;
            margin-bottom: 20px;
            color: white;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

    <div class="admin-container">
        <h2>All User Reviews</h2>
        <a href="driver.php" class="back-btn">Back </a>
        <!-- Display the count of reviews -->
        <div class="review-count" style="color:brown">
            Total Reviews: <?php echo $review_count; ?>  
        </div>

        <?php if ($review_count > 0) { ?>
            <?php while ($row = $result->fetch_assoc()) { ?>
                <div class="review-box">
                    <strong><?php echo htmlspecialchars($row['username']); ?></strong> 
                    <p><?php echo htmlspecialchars($row['message']); ?></p>
                    <span class="rating">⭐ <?php echo $row['rating']; ?>/5</span>
                    <p style="font-size: 12px; color: #ccc;"><?php echo $row['created_at']; ?></p>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p>No reviews yet.</p>
        <?php } ?>

     
    </div>

</body>
</html>

<?php $conn->close(); ?>
