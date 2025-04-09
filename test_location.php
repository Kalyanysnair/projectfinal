<?php
include 'connect.php';
session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }

// Check if tbl_emergency exists, if not create it
$check_table = "SHOW TABLES LIKE 'tbl_emergency'";
$table_exists = $conn->query($check_table);

if ($table_exists->num_rows == 0) {
    $create_table = "CREATE TABLE `tbl_emergency` (
        `request_id` int(11) NOT NULL AUTO_INCREMENT,
        `userid` int(6) unsigned NOT NULL,
        `pickup_location` varchar(255) NOT NULL,
        `contact_phone` varchar(15) NOT NULL,
        `driver_id` int(11) DEFAULT NULL,
        `status` enum('Pending','Accepted','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        `ambulance_type` varchar(50) NOT NULL,
        `patient_name` varchar(255) NOT NULL,
        PRIMARY KEY (`request_id`),
        KEY `userid` (`userid`),
        KEY `driver_id` (`driver_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($create_table)) {
        echo "<script>console.log('Table tbl_emergency created successfully');</script>";
    } else {
        die("Error creating table: " . $conn->error);
    }
}

// Process form submission
$success = "";
$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_name = trim($_POST['patient_name']);
    $location = trim($_POST['location']);
    $ambulance_type = trim($_POST['ambulance_type']);
    $phone_number = trim($_POST['phone_number']);
    $date = trim($_POST['date']);
    $time = trim($_POST['time']);

    // Validate inputs
    if (empty($patient_name) || empty($location) || empty($ambulance_type) || empty($phone_number)) {
        $error = "All fields are required";
    } else {
        // Insert into database
        $query = "INSERT INTO tbl_emergency (userid, pickup_location, contact_phone, ambulance_type, patient_name, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("issss", $_SESSION['user_id'], $location, $phone_number, $ambulance_type, $patient_name);
            if ($stmt->execute()) {
                $success = "Emergency request submitted successfully!";
            } else {
                $error = "Error submitting request: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparing statement: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Booking</title>
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBMp8OdSdsgcXMnNKWjKRPhLCWRA-Yy0EI&libraries=places"></script>
    <!-- Stylesheets -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        /* Your existing styles */
    </style>
</head>
<body>
    <header id="header" class="header d-flex align-items-center fixed-top">
        <!-- Your existing header content -->
    </header>

    <section id="hero" class="hero section dark-background">
        <div id="hero-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-item active">
                <img src="assets/assets/img/template/Groovin/hero-carousel/road.jpg" alt="" class="hero-image">
                <div class="carousel-container">
                    <div class="container">
                        <div class="form-container">
                            <h2 style="color:red">Emergency Booking</h2><br>
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>

                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>

                            <form method="post" class="php-email-form">
                                <div class="row gy-4">
                                    <!-- Patient/Booker Name -->
                                    <div class="col-md-6">
                                        <b>Patient's/Booker Name</b>
                                        <input type="text" name="patient_name" class="form-control" placeholder="Name of Patient/Booker" required>
                                    </div>

                                    <!-- Location Input Method -->
                                    <div class="col-md-6">
                                        <label for="location-method"><b>Select Location Input Method</b></label>
                                        <select id="location-method" class="form-control" onchange="showLocationOptions()" required>
                                            <option value="">Choose an option</option>
                                            <option value="current">Share Current Location</option>
                                            <option value="map">Use Google Maps</option>
                                            <option value="manual">Manually Type Location</option>
                                        </select>
                                    </div>

                                    <!-- Current Location -->
                                    <div id="current-location" class="dropdown-options col-md-12">
                                        <button type="button" class="btn btn-primary mt-2" onclick="getLocation()">Use Current Location</button>
                                        <input type="text" id="current-location-input" name="location" class="form-control mt-2" readonly>
                                        <div id="location-status" class="mt-2"></div>
                                    </div>

                                    <!-- Google Maps -->
                                    <div id="map-location" class="dropdown-options col-md-12">
                                        <input type="text" id="map-input" name="location" class="form-control mt-2" placeholder="Search Location">
                                        <div id="map"></div>
                                    </div>

                                    <!-- Manual Location -->
                                    <div id="manual-location" class="dropdown-options col-md-12">
                                        <input type="text" name="location" class="form-control mt-2" placeholder="Type Your Location">
                                    </div>

                                    <!-- Type of Ambulance -->
                                    <div class="col-md-6">
                                        <b>Ambulance Type</b>
                                        <select name="ambulance_type" class="form-control" required>
                                            <option value="">Select Ambulance Type</option>
                                            <option value="Basic">Basic Ambulance Service</option>
                                            <option value="Advanced">Advanced Life Support</option>
                                            <option value="Critical">Critical Care Ambulance</option>
                                            <option value="Neonatal">Neonatal Ambulance</option>
                                            <option value="Bariatric">Bariatric Ambulance</option>
                                        </select>
                                    </div>

                                    <!-- Phone Number -->
                                    <div class="col-md-6">
                                        <b>Phone Number</b>
                                        <input type="tel" name="phone_number" class="form-control" placeholder="Phone Number" required>
                                    </div>

                                    <!-- Date (Auto-filled with Current Date) -->
                                    <div class="col-md-6">
                                        <b>Date</b>
                                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <!-- Time (Auto-filled with Current Time) -->
                                    <div class="col-md-6">
                                        <b>Time</b>
                                        <input type="time" name="time" class="form-control" value="<?php echo date('H:i'); ?>" required>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="col-md-12 text-center">
                                        <button type="submit" class="btn btn-primary">Book Now</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript -->
     <script>
    let map, marker, geocoder;

// Initialize the map and geocoder
function initMap() {
    console.log("Initializing map...");
    geocoder = new google.maps.Geocoder();
    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 0, lng: 0 },
        zoom: 12,
    });

    // Check if geolocation is supported
    if (navigator.geolocation) {
        console.log("Geolocation is supported.");
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                console.log("User's location:", pos);
                map.setCenter(pos);
                marker = new google.maps.Marker({
                    position: pos,
                    map: map,
                    draggable: true,
                });
                getAddressFromCoordinates(pos.lat, pos.lng);
            },
            (error) => {
                console.error("Geolocation error:", error);
                document.getElementById("location-status").innerHTML =
                    `<span style="color:red">Error: ${getGeolocationErrorMessage(error)}</span>`;
            }
        );
    } else {
        console.error("Geolocation is not supported by this browser.");
        document.getElementById("location-status").innerHTML =
            `<span style="color:red">Your browser does not support geolocation.</span>`;
    }
}

// Get the user's current location
function getLocation() {
    console.log("Getting current location...");
    if (navigator.geolocation) {
        document.getElementById("location-status").innerHTML =
            `<span style="color:green">Fetching your location...</span>`;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                console.log("Coordinates:", lat, lng);
                document.getElementById("current-location-input").value = "Fetching address...";
                getAddressFromCoordinates(lat, lng);
            },
            (error) => {
                console.error("Geolocation error:", error);
                document.getElementById("location-status").innerHTML =
                    `<span style="color:red">Error: ${getGeolocationErrorMessage(error)}</span>`;
            }
        );
    } else {
        console.error("Geolocation is not supported by this browser.");
        document.getElementById("location-status").innerHTML =
            `<span style="color:red">Your browser does not support geolocation.</span>`;
    }
}

// Convert coordinates to address using Geocoding API
function getAddressFromCoordinates(lat, lng) {
    console.log("Geocoding coordinates:", lat, lng);
    const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };
    geocoder.geocode({ location: latlng }, (results, status) => {
        if (status === "OK" && results[0]) {
            const address = results[0].formatted_address;
            console.log("Address found:", address);
            document.getElementById("current-location-input").value = address;
            document.getElementById("location-status").innerHTML =
                `<span style="color:green">Address: ${address}</span>`;
        } else {
            console.error("Geocoder failed:", status);
            document.getElementById("location-status").innerHTML =
                `<span style="color:red">Could not retrieve address. Using coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}</span>`;
            document.getElementById("current-location-input").value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
    });
}

// Show the selected location input method
function showLocationOptions() {
    const method = document.getElementById("location-method").value;
    document.querySelectorAll(".dropdown-options").forEach(option => {
        option.style.display = "none";
    });

    if (method === "current") {
        document.getElementById("current-location").style.display = "block";
    } else if (method === "map") {
        document.getElementById("map-location").style.display = "block";
        document.getElementById("map").style.display = "block";
        initMap();
    } else if (method === "manual") {
        document.getElementById("manual-location").style.display = "block";
    }
}

// Get a readable error message for geolocation errors
function getGeolocationErrorMessage(error) {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return "Location access denied. Please enable location services.";
        case error.POSITION_UNAVAILABLE:
            return "Location information is unavailable.";
        case error.TIMEOUT:
            return "The request to get location timed out.";
        case error.UNKNOWN_ERROR:
            return "An unknown error occurred.";
        default:
            return "Error getting location.";
    }
}

// Initialize when the DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
    console.log("DOM fully loaded.");
    if (typeof google !== 'undefined' && google.maps) {
        console.log("Google Maps API loaded.");
    } else {
        console.error("Google Maps API not available. Please check your API key.");
    }
});
</script>
</body>
</html>