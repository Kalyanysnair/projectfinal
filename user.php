<?php
session_start();
include 'connect.php';

// Ensure user is logged in and is a 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT username, phoneno FROM tbl_user WHERE userid = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$message = ""; // Message to display after form submission

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pickup_location = trim($_POST['Pickup_Location'] ?? '');
    $pickup_latitude = trim($_POST['pickup_latitude'] ?? '');
    $pickup_longitude = trim($_POST['pickup_longitude'] ?? '');
    $service_type = trim($_POST['Service_Type'] ?? '');
    $service_time = trim($_POST['Service_Time'] ?? '');
    $destination = trim($_POST['Destination'] ?? '');
    $destination_latitude = trim($_POST['destination_latitude'] ?? '');
    $destination_longitude = trim($_POST['destination_longitude'] ?? '');
    $ambulance_type = trim($_POST['Ambulance_Type'] ?? '');
    $additional_requirements = trim($_POST['Additional_Requirements'] ?? '');
    $comments = trim($_POST['Comments'] ?? '');

    if (empty($pickup_location) || empty($service_type) || empty($service_time) || empty($destination) || empty($ambulance_type)) {
        $message = "<div class='alert alert-danger'>All required fields must be filled.</div>";
    } else {
        $comments = !empty($comments) ? $comments : NULL;
        
        // Check if your database table has the latitude and longitude columns
        // If not, revert to using the original query
        $table_info = $conn->query("SHOW COLUMNS FROM tbl_prebooking");
        $columns = [];
        while($row = $table_info->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        if (in_array('pickup_latitude', $columns) && in_array('pickup_longitude', $columns) && 
            in_array('destination_latitude', $columns) && in_array('destination_longitude', $columns)) {
            // Use the new query with latitude and longitude
            $stmt = $conn->prepare("INSERT INTO tbl_prebooking 
                (userid, pickup_location, pickup_latitude, pickup_longitude, destination, destination_latitude, destination_longitude,
                service_type, service_time, ambulance_type, additional_requirements, comments) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param("isssssssssss", 
                $user_id, $pickup_location, $pickup_latitude, $pickup_longitude, 
                $destination, $destination_latitude, $destination_longitude,
                $service_type, $service_time, $ambulance_type, $additional_requirements, $comments);
        } else {
            // Use the original query without latitude and longitude
            $stmt = $conn->prepare("INSERT INTO tbl_prebooking 
                (userid, pickup_location, service_type, service_time, destination, ambulance_type, additional_requirements, comments) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param("isssssss", 
                $user_id, $pickup_location, $service_type, $service_time, 
                $destination, $ambulance_type, $additional_requirements, $comments);
        }
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Request submitted successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
      
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftAid - User Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <!-- Add Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.css" />
    <style>
        /* General Styling */
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-image: url('assets/assets/img//template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100%;
        }
        
        /* Header Styling */
        #header {
            background: rgba(34, 39, 34, 0.9);
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            width: 100%;
            z-index: 1000;
        }

        .sidebar {
            width: 250px;
            color: white;
            padding: 20px;
            position: fixed;
            top: 70px;
            bottom: 0;
            left: 0;
        }

        .sidebar h2 {
            font-size: 18px;
            text-align: center;
            color:rgb(206, 129, 20);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
        }

        .sidebar-nav li {
            margin: 15px 0;
        }

        .sidebar-nav li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .sidebar-nav li a i {
            margin-right: 10px;
        }

        /* Form Container Styling */
        .form-container {
            background: rgba(218, 214, 214, 0.46);
            border-radius: 10px;
            padding: 70px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            margin-top: 40px;
        }

        label {
            font-weight: 500;
        }

        .form-control {
            margin-bottom: 20px;
            border: 1px solid #ccc;
        }
        
        .btn-primary {
            background-color:rgb(52, 219, 113);
            border-color:rgb(55, 224, 17);
            color: white;
        }

        .btn-primary:hover {
            background-color:rgb(41, 185, 77);
        }
        
        .sidebar-nav li a.logout-btn {
            color: white;
            font-weight: bold;
        }

        .sidebar-nav li a.logout-btn:hover {
            color: darkred;
            text-decoration: underline;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }

        .user-info i {
            font-size: 30px;
        }

        .user-info h2 {
            font-size: 18px;
            margin: 0;
            font-weight: normal;
        }
        
        /* Map styling */
        .map-container {
            height: 300px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        
        .location-controls {
            margin-bottom: 15px;
        }
        
        .location-display {
            margin-top: 5px;
            font-weight: normal;
            font-style: italic;
            color: #555;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'?>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="user1.php">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <h2><?php echo $_SESSION['username']; ?></h2>
            </div>
        </a>

        <ul class="sidebar-nav">
            <li><a href="user_profile.php"><i class="fas fa-user"></i>  Profile</a></li>
            <li><a href="status.php"><i class="fas fa-list"></i> My Bookings</a></li>
            <li><a href="feedback.php"><i class="fas fa-comment"></i> Give Feedback</a></li>
            <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" style="margin-left: 270px;">
        <div class="container">
            <div class="form-container">
            <?php if (!empty($message)) echo $message; ?>
                <h2>Pre-Book Ambulance</h2>
                <form action=" " method="post" onsubmit="return validateForm()">
                
                    <div class="row">
                        <div class="col-md-6">
                            <label for="username">Username</label>
                            <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>

                            <label for="Pickup_Location">Pickup Location</label>
                            <div class="location-controls">
                                <button type="button" class="btn btn-sm btn-info" onclick="getCurrentLocation('pickup')">
                                    <i class="fas fa-map-marker-alt"></i> Use Current Location
                                </button>
                            </div>
                            <div id="pickup-map" class="map-container"></div>
                            <textarea name="Pickup_Location" id="Pickup_Location" class="form-control" rows="2" required></textarea>
                            <div class="location-display" id="pickup-coords-display"></div>
                            <input type="hidden" name="pickup_latitude" id="pickup_latitude">
                            <input type="hidden" name="pickup_longitude" id="pickup_longitude">

                            <label for="Service_Type">Service Type</label>
                            <select name="Service_Type" id="Service_Type" class="form-control" required>
                                <option value="">--Select Service Type--</option>
                                <option value="Hospital Transport">Hospital Transport</option>
                                <option value="Mortuary Transport">Mortuary Transport</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="Phone_Number">Phone Number</label>
                            <input type="tel" name="Phone_Number" id="Phone_Number" class="form-control" pattern="[0-9]{10}" value="<?php echo htmlspecialchars($user['phoneno']); ?>" readonly>

                            <label for="Destination">Destination</label>
                            <div class="location-controls">
                                <button type="button" class="btn btn-sm btn-info" onclick="getCurrentLocation('destination')">
                                    <i class="fas fa-map-marker-alt"></i> Use Current Location
                                </button>
                            </div>
                            <div id="destination-map" class="map-container"></div>
                            <textarea name="Destination" id="Destination" class="form-control" rows="2" required></textarea>
                            <div class="location-display" id="destination-coords-display"></div>
                            <input type="hidden" name="destination_latitude" id="destination_latitude">
                            <input type="hidden" name="destination_longitude" id="destination_longitude">

                            <label for="Service_Time">Service Date and Time</label>
                            <input type="datetime-local" name="Service_Time" id="Service_Time" class="form-control" required>
                            <small id="datetimeError" style="color: red; display: none;">Please select a future date and time.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="Ambulance_Type">Ambulance Type</label>
                            <select name="Ambulance_Type" id="Ambulance_Type" class="form-control" required>
                                <option value="">Select Ambulance Type</option>
                                <option value="Basic">Basic Ambulance Service</option>
                                <option value="Advanced">Advanced Life Support </option>
                                <option value="Critical">Critical Care Ambulance</option>
                                <option value="Neonatal">Neonatal Ambulance</option>
                                <option value="Bariatric">Bariatric Ambulance</option> 
                                <option value="Mortuary">Mortuary Ambulance</option> 
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="Additional_Requirements">Additional Requirements</label>
                            <select name="Additional_Requirements" id="Additional_Requirements" class="form-control">
                                <option value="">--Select Option--</option>
                                <option value="Wheelchair">Wheelchair</option>
                                <option value="Oxygen Cylinder">Oxygen Cylinder</option>
                                <option value="Stretcher">Stretcher</option>
                                <option value="None">No Additional Requirements</option>
                            </select>
                        </div>
                    </div>

                    <label for="Comments">Comments</label>
                    <textarea name="Comments" id="Comments" class="form-control" rows="1"></textarea>

                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Leaflet JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.js"></script>
    <script>
        // Initialize maps
        let pickupMap = L.map('pickup-map').setView([10.850516, 76.271080], 6); // Default center on Kerala
        let destinationMap = L.map('destination-map').setView([10.850516, 76.271080], 6);
        
        // Add tile layer to both maps
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(pickupMap);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(destinationMap);
        
        // Markers for locations
        let pickupMarker = null;
        let destinationMarker = null;
        
        // Function to get current location
        function getCurrentLocation(type) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        if (type === 'pickup') {
                            updatePickupLocation(lat, lng);
                            // Use reverse geocoding to get address
                            reverseGeocode(lat, lng, 'pickup');
                        }
                        if (type === 'destination') {
                            updateDestinationLocation(lat, lng);
                            // Use reverse geocoding to get address
                            reverseGeocode(lat, lng, 'destination');
                        }
                    },
                    (error) => {
                        console.error("Error getting location: ", error);
                        alert("Could not get your location. Please select on the map or enter manually.");
                    }
                );
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        }
        
        // Update pickup location on map
        function updatePickupLocation(lat, lng) {
            if (pickupMarker) {
                pickupMap.removeLayer(pickupMarker);
            }
            
            pickupMap.setView([lat, lng], 15);
            pickupMarker = L.marker([lat, lng], {draggable: true}).addTo(pickupMap);
            
            // Update hidden form fields
            document.getElementById('pickup_latitude').value = lat;
            document.getElementById('pickup_longitude').value = lng;
            document.getElementById('pickup-coords-display').textContent = `Selected: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            
            // Handle marker drag
            pickupMarker.on('dragend', function(e) {
                const position = pickupMarker.getLatLng();
                document.getElementById('pickup_latitude').value = position.lat;
                document.getElementById('pickup_longitude').value = position.lng;
                document.getElementById('pickup-coords-display').textContent = `Selected: ${position.lat.toFixed(6)}, ${position.lng.toFixed(6)}`;
                reverseGeocode(position.lat, position.lng, 'pickup');
            });
        }
        
        // Update destination location on map
        function updateDestinationLocation(lat, lng) {
            if (destinationMarker) {
                destinationMap.removeLayer(destinationMarker);
            }
            
            destinationMap.setView([lat, lng], 15);
            destinationMarker = L.marker([lat, lng], {draggable: true}).addTo(destinationMap);
            
            // Update hidden form fields
            document.getElementById('destination_latitude').value = lat;
            document.getElementById('destination_longitude').value = lng;
            document.getElementById('destination-coords-display').textContent = `Selected: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            
            // Handle marker drag
            destinationMarker.on('dragend', function(e) {
                const position = destinationMarker.getLatLng();
                document.getElementById('destination_latitude').value = position.lat;
                document.getElementById('destination_longitude').value = position.lng;
                document.getElementById('destination-coords-display').textContent = `Selected: ${position.lat.toFixed(6)}, ${position.lng.toFixed(6)}`;
                reverseGeocode(position.lat, position.lng, 'destination');
            });
        }
        
        // Simple reverse geocoding using Nominatim API
       // Simple reverse geocoding using Nominatim API
function reverseGeocode(lat, lng, type) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(response => response.json())
        .then(data => {
            const address = data.display_name;
            if (type === 'pickup') {
                document.getElementById('Pickup_Location').value = address;
            } else {
                document.getElementById('Destination').value = address; // Corrected ID
            }
        })
        .catch(error => {
            console.error("Error fetching address: ", error);
        });
}
        
        // Add click event to both maps
        pickupMap.on('click', function(e) {
            updatePickupLocation(e.latlng.lat, e.latlng.lng);
            reverseGeocode(e.latlng.lat, e.latlng.lng, 'pickup');
        });
        
        destinationMap.on('click', function(e) {
            updateDestinationLocation(e.latlng.lat, e.latlng.lng);
            reverseGeocode(e.latlng.lat, e.latlng.lng, 'destination');
        });
        
        // Form validation
        function validateForm() {
            // Check if date is in future
            let dateTime = document.getElementById("Service_Time").value;
            let now = new Date();
            let selectedTime = new Date(dateTime);
            if (selectedTime <= now) {
                document.getElementById("datetimeError").style.display = "block";
                return false;
            }
            
            return true;
        }
        
        // Date-time validation event
        document.getElementById("Service_Time").addEventListener("change", function() {
            let inputDateTime = new Date(this.value);
            let currentDateTime = new Date();

            if (inputDateTime <= currentDateTime) {
                document.getElementById("datetimeError").style.display = "block";
                this.value = ""; // Reset input field
            } else {
                document.getElementById("datetimeError").style.display = "none";
            }
        });
        
        // Fix for maps not rendering correctly
        setTimeout(function() {
            pickupMap.invalidateSize();
            destinationMap.invalidateSize();
        }, 100);
    </script>
</body>
</html>