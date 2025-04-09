<?php
session_start();
include 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$search = "";
$drivers = [];

// Handle search
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search = trim($_POST['search']);
    $stmt = $conn->prepare("SELECT 
                                d.driver_id AS driverid, 
                                u.username AS dname, 
                                u.email, 
                                u.phoneno AS phone, 
                                u.status, 
                                d.lisenceno, 
                                d.service_area, 
                                d.ambulance_type, 
                                d.vehicle_no AS vehicleno
                            FROM tbl_driver d
                            JOIN tbl_user u ON d.userid = u.userid
                            WHERE u.username LIKE ? 
                               OR u.email LIKE ? 
                               OR u.phoneno LIKE ? 
                               OR u.status LIKE ? 
                               OR d.lisenceno LIKE ? 
                               OR d.service_area LIKE ? 
                               OR d.ambulance_type LIKE ? 
                               OR d.vehicle_no LIKE ?");
    $search_term = "%$search%";
    $stmt->bind_param("ssssssss", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term);
} else {
    // Fetch all driver details
    $stmt = $conn->prepare("SELECT 
                                d.driver_id AS driverid, 
                                u.username AS dname, 
                                u.email, 
                                u.phoneno AS phone, 
                                u.status, 
                                d.lisenceno, 
                                d.service_area, 
                                d.ambulance_type, 
                                d.vehicle_no AS vehicleno
                            FROM tbl_driver d
                            JOIN tbl_user u ON d.userid = u.userid");
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $drivers[] = $row;
}
$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver and Ambulance Details</title>
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        body {
            background-image: url('assets/assets/img//template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            font-family: Arial, sans-serif;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.7); /* Transparent header */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        header img {
            width: 50px; /* Adjust logo size */
            height: 50px;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
        }

        .container {
            text-align: center;
            padding: 80px 50px 50px; /* Padding to account for fixed header */
        }

        table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            background-color: rgba(185, 181, 181, 0.7); /* Transparent background */
        }

        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:nth-child(even) {
            background-color: rgb(181, 178, 178);
        }

        .search-box {
            margin-bottom: 20px;
            padding: 10px;
            width: 300px;
            font-size: 16px;
        }

        .status-inactive {
            color: red;
            font-weight: bold;
        }

                .toggle-status-btn {
            background-color: #4CAF50; /* Green for active */
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 5px;
        }

        .toggle-status-btn.inactive {
            background-color: #f44336; /* Red for inactive */
        }
        .back-to-dashboard-btn {
            position: absolute;
            top: 30px;
            right: 30px;
            background-color: rgba(12, 190, 6, 0.67);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        .back-to-dashboard-btn:hover {
            background-color: rgb(0, 105, 12);
            color: white;
        }
    </style>
</head>
<body>



<div class="container">
    <form method="POST">
        <input type="text" name="search" class="search-box" placeholder="Search Drivers Here" value="<?php echo htmlspecialchars($search); ?>" />
        <input type="submit" value="Search" />
    </form>
    <a href="admin.php" class="back-to-dashboard-btn" style="text-decoration: none; color: white; background-color:rgb(45, 92, 54); padding: 10px 20px; border-radius: 5px; font-size: 16px;">
            Back
        </a>
    <table>
        <tr>
            <th>Driver ID</th>
            <th>Driver Name</th>
            <th>License No.</th>
            <th>Service Area</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Status</th>
            <!-- <th>Ambulance ID</th> -->
            <th>Vehicle No.</th>
            <th>Ambulance Type</th>
            <!-- <th>Ambulance Status</th> -->
            <th>Action</th>
        </tr>
        <?php foreach ($drivers as $driver): ?>
        <tr data-driverid="<?php echo htmlspecialchars($driver['driverid']); ?>">
            <td><?php echo htmlspecialchars($driver['driverid']); ?></td>
            <td><?php echo htmlspecialchars($driver['dname']); ?></td>
            <td><?php echo htmlspecialchars($driver['lisenceno']); ?></td>
            <td><?php echo htmlspecialchars($driver['service_area']); ?></td>
            <td><?php echo htmlspecialchars($driver['phone']); ?></td>
            <td><?php echo htmlspecialchars($driver['email']); ?></td>
            <td class="<?php echo $driver['status'] === 'inactive' ? 'status-inactive' : ''; ?>">
                <?php echo htmlspecialchars($driver['status']); ?>
            </td>
            
            <td><?php echo htmlspecialchars($driver['vehicleno']); ?></td>
            <td><?php echo htmlspecialchars($driver['ambulance_type']); ?></td>
            
            <td>
    <button class="toggle-status-btn <?php echo $driver['status'] === 'inactive' ? 'inactive' : ''; ?>" data-driverid="<?php echo htmlspecialchars($driver['driverid']); ?>">
        <?php echo $driver['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
    </button>
</td>

        </tr>
        <?php endforeach; ?>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Handle toggle status button click
    $('.toggle-status-btn').on('click', function() {
        const driverId = $(this).data('driverid');
        const row = $(this).closest('tr');
        const statusCell = row.find('td:nth-child(7)');
        const button = $(this);

        if (confirm('Are you sure you want to toggle this driver\'s status?')) {
            $.ajax({
                url: 'delete_driver.php', // Updated to use the same file
                type: 'POST',
                data: { driver_id: driverId },
                success: function(response) {
                    if (response === 'active' || response === 'inactive') {
                        // Update the status in the table
                        statusCell.text(response);
                        if (response === 'inactive') {
                            statusCell.addClass('status-inactive');
                        } else {
                            statusCell.removeClass('status-inactive');
                        }

                        // Update the button text
                        button.text(response === 'active' ? 'Deactivate' : 'Activate');
                        alert('Driver status updated successfully');
                    } else {
                        alert('Error: ' + response);
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX Error: ' + error);
                }
            });
        }
    });
});
</script>
</body>
</html>


