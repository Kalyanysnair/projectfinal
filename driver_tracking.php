<?php
session_start();
require 'connect.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}

$user_id = $_SESSION['user_id'];

// Check if user is a driver
$role_query = "SELECT role FROM tbl_user WHERE userid = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$user_role = $role_result->fetch_assoc()['role'];

if ($user_role !== 'driver') {
    die(json_encode(['success' => false, 'message' => 'User is not a driver']));
}

// Get active booking for this driver
$active_booking_query = "SELECT request_id, request_type FROM tbl_active_bookings WHERE driver_id = ? AND status = 'Accepted'";
$stmt = $conn->prepare($active_booking_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_booking = $stmt->get_result()->fetch_assoc();

if (!$active_booking) {
    die(json_encode(['success' => false, 'message' => 'No active booking found']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Tracking</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status {
            margin: 20px 0;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .tracking-active {
            background-color: #d4edda;
            color: #155724;
        }
        .tracking-stopped {
            background-color: #f8d7da;
            color: #721c24;
        }
        .location-info {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin: 5px 0;
        }
        .btn-primary {
            background-color: #2E8B57;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Driver Location Tracking</h2>
        <div id="trackingStatus" class="status tracking-active">
            Tracking Active
        </div>
        <div class="location-info">
            <h3>Current Location</h3>
            <p>Latitude: <span id="latitude">--</span></p>
            <p>Longitude: <span id="longitude">--</span></p>
            <p>Last Updated: <span id="lastUpdate">--</span></p>
        </div>
        <button id="completeBtn" class="btn btn-primary" onclick="completeBooking()">Mark as Complete</button>
    </div>

    <script>
        let trackingInterval;
        const requestId = <?php echo $active_booking['request_id']; ?>;
        const bookingType = '<?php echo $active_booking['request_type']; ?>';

        function startTracking() {
            if (navigator.geolocation) {
                trackingInterval = setInterval(updateLocation, 3000);
                updateLocation(); // Get initial location immediately
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }

        function updateLocation() {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;
                    
                    document.getElementById('latitude').textContent = latitude.toFixed(6);
                    document.getElementById('longitude').textContent = longitude.toFixed(6);
                    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();

                    // Send location to server
                    fetch('update_driver_location.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            latitude: latitude,
                            longitude: longitude
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Failed to update location:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating location:', error);
                    });
                },
                (error) => {
                    console.error('Error getting location:', error);
                    document.getElementById('trackingStatus').className = 'status tracking-stopped';
                    document.getElementById('trackingStatus').textContent = 'Error getting location';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                }
            );
        }

        function completeBooking() {
            if (confirm('Are you sure you want to mark this booking as complete?')) {
                // Stop tracking
                clearInterval(trackingInterval);
                document.getElementById('trackingStatus').className = 'status tracking-stopped';
                document.getElementById('trackingStatus').textContent = 'Tracking Stopped';
                document.getElementById('completeBtn').disabled = true;

                // Update booking status
                fetch('complete_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        booking_type: bookingType
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking marked as complete. Please enter the amount.');
                        window.location.href = 'driverpreviousJob.php';
                    } else {
                        alert('Failed to mark booking as complete: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error completing booking:', error);
                    alert('An error occurred while completing the booking');
                });
            }
        }

        // Start tracking automatically when page loads
        startTracking();
    </script>
</body>
</html> 