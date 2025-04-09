<?php
session_start();
include('connect.php'); // Ensure database connection is included

// Fetch users for dropdown
$users = mysqli_query($conn, "SELECT userid, username FROM tbl_user");

// Fetch user details and their requests if a user is selected
$userDetails = null;
$requests = [];
if (isset($_POST['selected_user'])) {
    $selectedUser = $_POST['selected_user'];
    
    // Fetch user details
    $userQuery = mysqli_query($conn, "SELECT * FROM tbl_user WHERE userid = '$selectedUser'");
    $userDetails = mysqli_fetch_assoc($userQuery);
    
    // Fetch user requests from different tables
  // Fetch user requests from different tables
  $query = "
    SELECT 'Prebooking' as type, 
           prebookingid as request_id, 
           CAST(pickup_location AS CHAR CHARACTER SET utf8) AS location, 
           CAST(destination AS CHAR CHARACTER SET utf8) AS details, 
           status, amount, payment_status, created_at 
    FROM tbl_prebooking WHERE userid = '$selectedUser'
    UNION ALL
    SELECT 'Palliative' as type, 
           palliativeid as request_id, 
           CAST(address AS CHAR CHARACTER SET utf8) AS location, 
           CAST(medical_condition AS CHAR CHARACTER SET utf8) AS details, 
           status, amount, payment_status, created_at 
    FROM tbl_palliative WHERE userid = '$selectedUser'
    UNION ALL
    SELECT 'Emergency' as type, 
           request_id, 
           CAST(pickup_location AS CHAR CHARACTER SET utf8) AS location, 
           CAST(patient_name AS CHAR CHARACTER SET utf8) AS details, 
           status, amount, payment_status, created_at 
    FROM tbl_emergency WHERE userid = '$selectedUser'";


$requests = mysqli_query($conn, $query);

// Check if the query was successful
if (!$requests) {
die("Query Error: " . mysqli_error($conn));
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - User Requests</title>
    <style>
        body {
            background: url('assets/assets/img//template/Groovin/hero-carousel/ambulance2.jpg')no-repeat center center fixed;
            background-size: cover;
            font-family: Arial, sans-serif;
        }
        .container {
            width: 80%;
            margin: 50px auto;
            background: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }
        select, button {
            padding: 10px;
            margin: 10px 0;
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Admin - View User Requests</h2>
        <form method="POST">
            <label>Select User:</label>
            <select name="selected_user" required>
                <option value="">--Select--</option>
                <?php while ($user = mysqli_fetch_assoc($users)) { ?>
                    <option value="<?php echo $user['userid']; ?>" <?php echo (isset($selectedUser) && $selectedUser == $user['userid']) ? 'selected' : ''; ?>>
                        <?php echo $user['username']; ?>
                    </option>
                <?php } ?>
            </select>
            <button type="submit">View Requests</button>
        </form>

        <?php if ($userDetails) { ?>
            <h3>User Details</h3>
            <p><strong>Name:</strong> <?php echo $userDetails['username']; ?></p>
            <p><strong>Email:</strong> <?php echo $userDetails['email']; ?></p>
            <p><strong>Phone:</strong> <?php echo $userDetails['phoneno']; ?></p>
            <p><strong>Status:</strong> <?php echo $userDetails['status']; ?></p>
            
            <h3>Requests</h3>
            <table>
                <tr>
                    <th>Type</th>
                    <th>Request ID</th>
                    <th>Location/Details</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Payment Status</th>
                    <th>Created At</th>
                </tr>
                <?php while ($row = mysqli_fetch_assoc($requests)) { ?>
                    <tr>
                        <td><?php echo $row['type']; ?></td>
                        <td><?php echo $row['request_id']; ?></td>
                        <td><?php echo $row['pickup_location'] ?? $row['address']; ?></td>
                        <td><?php echo $row['status']; ?></td>
                        <td><?php echo $row['amount'] ? '$' . $row['amount'] : 'N/A'; ?></td>
                        <td><?php echo $row['payment_status']; ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>
</body>
</html>
