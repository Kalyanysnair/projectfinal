Emergencyyy(abin)
<?php
session_start();
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_name = trim($_POST['patient_name'] ?? ''); // Capture patient/booker name
    $pickup_location = trim($_POST['pickup_location'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $ambulance_type = trim($_POST['ambulance_type'] ?? '');

    // Ensure required fields are provided
    if (empty($patient_name) || empty($pickup_location) || empty($ambulance_type)) {
        echo "<div class='alert alert-danger'>Patient Name, Pickup Location, and Ambulance Type are required.</div>";
    } else {
        $status = 'Pending'; // Default status
        $driver_id = NULL; // Assigned later
        $user_id = NULL; // User will sign up later

        // Prepare SQL query (userid is NULL)
        $stmt = $conn->prepare("INSERT INTO tbl_emergency 
            (userid, patient_name, pickup_location, contact_phone, ambulance_type, status) 
            VALUES (?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            die("<div class='alert alert-danger'>Prepare failed: " . $conn->error . "</div>");
        }

        // Bind parameters (use NULL for userid)
        $stmt->bind_param("isssss", $user_id, $patient_name, $pickup_location, $contact_phone, $ambulance_type, $status);

        // Execute and check for success
        if ($stmt->execute()) {
            // Store request_id in session to update it later
            $_SESSION['pending_request_id'] = $stmt->insert_id;

            // Redirect to signup page with success message
            $_SESSION['message'] = "Your emergency request has been submitted. Please sign up to track it.";
            header("Location: signup.php");
            exit();
        } else {
            echo "<div class='alert alert-danger'>Execution failed: " . $stmt->error . "</div>";
            echo "<div class='alert alert-danger'>Query: INSERT INTO tbl_emergency (userid, patient_name, pickup_location, contact_phone, ambulance_type, status) VALUES ('$user_id', '$patient_name', '$pickup_location', '$contact_phone', '$ambulance_type', '$status')</div>";
        }

        $stmt->close();
    }
    $conn->close();
}
?>








    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Emergency Booking</title>

        <!-- Google Maps API Key (Replace with your own API key) -->
        
        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDo-B6eOHsgGobdykDX7jkMBl8NcEuwZ_k&libraries=places&callback=initMap" async defer></script>
        <!-- <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script> -->

    
        <!-- Stylesheets -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
        <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
        <link href="assets/vendor/aos/aos.css" rel="stylesheet">
        <link href="assets/css/main.css" rel="stylesheet">

        <style>
          #map {
            height: 300px; /* Ensure the map has a height */
            width: 100%;
        }
        .dropdown-options {
            display: none;
        }
        .btn-warning {
            background: rgb(208, 13, 13);
            color: #fff;
        }
        .btn-warning:hover {
            background: rgb(243, 119, 18);
            opacity: 0.9;
            transform: scale(1.05);
        }
        .btn-custom {
            width: 100%;
            font-size: 18px;
            padding: 15px;
            margin-top: 15px;
            border-radius: 12px;
            font-weight: bold;
            text-transform: uppercase;
            transition: 0.3s ease-in-out;
        }
        .form-container {
            background-color: rgba(151, 147, 147, 0.5); /* Background color for the box */
            border-radius: 10px; /* Rounded corners */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Shadow effect */
            padding: 20px; /* Inner padding */
            margin: auto; /* Center align */
            max-width: 800px; /* Limit width of the form */
        }
        .form-container h2 {
            text-align: center; /* Center the heading */
        }  
            .btn-warning {
        background:rgb(208, 13, 13);
        color: #fff;
        }

        .btn-warning:hover {
        background:rgb(243, 119, 18);
        opacity: 0.9;
        transform: scale(1.05);
        }
            
            
            .dropdown-options {
                display: none;
            }

            .header,
            .btn-getstarted {
                background-color:rgb(72, 78, 72); /* Green matching header */
                color: white;
            }

            .btn-primary {
                background-color:rgb(72, 194, 76); /* Green for buttons */
                border: none;
            }

            .btn-primary:hover {
                background-color: #45a049;
            }
            
        ::placeholder {
            color: green; /* Change placeholder color to green */
            opacity: 1; /* Ensure the placeholder is fully visible */
        }
        
        /* For input fields specifically */
        input::placeholder {
            color: green;
        }

        /* Optional: For select and other form elements, if needed */
        select::placeholder {
            color: green;
        }
        .form-container {
            background-color:rgba(151, 147, 147, 0.5); /* Background color for the box */
            border-radius: 10px; /* Rounded corners */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Shadow effect */
            padding: 20px; /* Inner padding */
            margin: auto; /* Center align */
            max-width: 800px; /* Limit width of the form */
        }
        .form-container h2 {
            text-align: center; /* Center the heading */
        }

        </style>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        .error
        {
            color:red;
        }
        </style>
    </head>
    <body>

    <header id="header" class="header d-flex align-items-center fixed-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center">
            <a href="index.html" class="logo d-flex align-items-center me-auto">
            <img src="assets/img/SWIFTAID2.png" alt="SWIFTAID Logo" style="height: 70px; margin-right: 10px;">
                <h1 class="sitename">SWIFTAID</h1>
            </a>
            <nav id="navmenu" class="navmenu">
                <ul>
                    <li><a href="index.html#hero">Home</a></li>
                    <li><a href="index.html#about">About</a></li>
                    <li><a href="index.html#services">Services</a></li>
                    <li><a href="index.html#ambulanceservice">Ambulance Services</a></li>
                    <li><a href="index.html#contact">Contact</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="signup.php">Sign Up</a></li>
                </ul>
            </nav>
            <a class="btn-getstarted" href="emergency.php">Emergency Booking</a>
        </div>
    </header>
    </head>
    <body>
    <?php
        // Initialize error variables
        $nameErr = $emailErr = $dobErr = $mobileErr = $genderErr = $passwordErr = "";
        $name = $email = $dob = $mobile = $gender = $password = "";

        // Validate inputs after form submission
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Name validation
            if (empty($_POST["name"])) {
                $nameErr = "Name is required";
            } else {
                $name = test_input($_POST["name"]);
                if (!preg_match("/^[a-zA-Z-' ]*$/", $name)) {
                    $nameErr = "Only letters and white space allowed";
                }
            }

            // Mobile validation
            if (empty($_POST["contact_phone"])) {
                $mobileErr = "Mobile number is required";
            } else {
                $mobile = test_input($_POST["contact_phone"]);
                if (!preg_match("/^[0-9]{10}$/", $mobile)) {
                    $mobileErr = "Invalid mobile number, must be 10 digits";
                }
            }

            
        }

        // Function to sanitize input
        function test_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }
        ?>
    <section id="hero" class="hero section dark-background">
    <div id="hero-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-item active">
        <img src="assets/assets/img/template/Groovin/hero-carousel/road.jpg" alt="" class="hero-image">
        <div class="carousel-container">
            <div class="container">
    <!-- Emergency Booking Form -->

    <div class="container mt-5">
        <div class="form-container">
            <h2 style="color:red">Emergency Booking </h2><br>
            <form method="post" class="php-email-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row gy-4">
                    <!-- Patient/Booker Name -->
                    <div class="col-md-6">
                        <b>Patient's/Booker Name</b>
                        <input type="text" name="patient_name" class="form-control" placeholder="Name of Patient/Booker" required>
                        <!-- <span class="error">*<?php echo $nameErr; ?></span> -->
                    </div>
                    
                    
                    <!-- Location Input Method Selection -->
                    <div class="col-md-6">
                                            <label for="location-method"><b>Select Location Input Method</b></label>
                                            <select id="location-method" class="form-control" onchange="showLocationOptions()" required>
                                                <option value="">Choose an option</option>
                                                <option value="current">Share Current Location</option>
                                                <option value="map">Use Google Maps</option>
                                            </select>
                                        </div>

                                        <!-- Current Location -->
                                        <div id="current-location" class="dropdown-options col-md-12">
                                            <!-- <button type="button" class="btn btn-primary mt-2" onclick="getCurrentLocation()">Use Current Location</button> -->
                                            <button type="button" class="btn btn-primary mt-2" onclick="showLocationOptions()">Use Current Location</button>
                                            <input type="text" id="current-location-input" class="form-control mt-2" readonly>
                                        </div>

                                        <!-- Google Maps Input -->
                                        <div id="map-location" class="dropdown-options col-md-12">
                                            <input type="text" id="map-input" class="form-control mt-2" placeholder="Search Location">
                                            <div id="map"></div>
                                        </div>

                                        <!-- Hidden Field to Store Pickup Location (Sent to DB) -->
                                        <input type="hidden" id="pickup_location" name="pickup_location">


                    <!-- Type of Ambulance -->
                    <div class="col-md-6">
                        <b>Ambulance Type</b>
                        <select name="ambulance_type" class="form-control" required>
                            <option value="">Select Ambulance Type</option>
                            <option value="Basic">Basic Ambulance Service</option>
                            <option value="Advanced">Advanced Life Support </option>
                            <option value="Neonatal">Critical Care Ambulance</option>
                            <option value="Neonatal">Neonatal Ambulance</option>
                            <option value="Bariatric">Bariatric Ambulance</option> 
                        </select>
                    </div>

                    <!-- Phone Number -->
                    <div class="col-md-6">
                        <b>Phone Number</b>
                        <input type="tel" name="contact_phone" class="form-control" placeholder="Phone Number" required>
                        
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
                        <a href="tel:+1234567890" class="btn btn-warning btn-custom">📞 Call Ambulance</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>
    </div>
    </div>
    </div>
    <section>

    <!-- JavaScript -->

    <script>
        let map, marker, autocomplete;

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 9.5280, lng: 76.8227 }, // Default center (adjustable)
                zoom: 12
            });

            const input = document.getElementById("map-input");
            autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.setFields(["geometry", "formatted_address"]);

            // Add marker when a place is selected
            autocomplete.addListener("place_changed", () => {
                const place = autocomplete.getPlace();
                if (!place.geometry) {
                    alert("No details available for input: '" + place.name + "'");
                    return;
                }

                // Get latitude and longitude from selected place
                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();
                const formattedAddress = place.formatted_address;

                // Update form inputs
                document.getElementById("pickup_location").value = `${lat}, ${lng}`;
                document.getElementById("map-input").value = formattedAddress; // Show formatted address in input field

                // Update map
                map.setCenter(place.geometry.location);
                if (marker) marker.setMap(null); // Remove existing marker
                marker = new google.maps.Marker({
                    position: place.geometry.location,
                    map: map
                });

                console.log("Selected Location: ", formattedAddress, lat, lng);
            });

            // Add click event listener to the map
            map.addListener("click", (event) => {
                const lat = event.latLng.lat();
                const lng = event.latLng.lng();

                // Update form inputs
                document.getElementById("pickup_location").value = `${lat}, ${lng}`;
                document.getElementById("map-input").value = `Lat: ${lat}, Lng: ${lng}`;

                // Update map
                if (marker) marker.setMap(null); // Remove existing marker
                marker = new google.maps.Marker({
                    position: event.latLng,
                    map: map
                });

                console.log("Clicked Location: ", lat, lng);
            });
        }

        function getCurrentLocation() { 
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const latLng = new google.maps.LatLng(lat, lng);

                // Store in hidden field for form submission
                document.getElementById("pickup_location").value = `${lat}, ${lng}`;
                document.getElementById("current-location-input").value = `Lat: ${lat}, Lng: ${lng}`;

                // Update map
                map.setCenter(latLng);
                if (marker) marker.setMap(null); // Remove existing marker
                marker = new google.maps.Marker({
                    position: latLng,
                    map: map
                });

                console.log("Current Location: ", lat, lng);
            },
            (error) => {
                alert("Error getting current location: " + error.message);
                console.error("Geolocation error:", error);
            }
        );
    } else {
        alert("Geolocation is not supported by your browser.");
    }
}
     
// function getCurrentLocation() {
//     if (navigator.geolocation) {
//         navigator.geolocation.getCurrentPosition(
//             (position) => {
//                 const lat = position.coords.latitude;
//                 const lng = position.coords.longitude;
//                 const latLng = new google.maps.LatLng(lat, lng);

//                 // Use Google Geocoder to get the place name
//                 const geocoder = new google.maps.Geocoder();
//                 geocoder.geocode({ 'location': latLng }, function(results, status) {
//                     if (status === google.maps.GeocoderStatus.OK) {
//                         if (results[0]) {
//                             // Get the address from the first result
//                             const placeName = results[0].formatted_address;
//                             console.log("Current Location Place Name: ", placeName);

//                             // Update the input field with the place name
//                             document.getElementById("current-location-input").value = placeName;

//                             // Optionally store the place name in the hidden field for form submission
//                             document.getElementById("pickup_location").value = placeName;
//                         } else {
//                             alert('No results found for this location.');
//                         }
//                     } else {
//                         alert('Geocoder failed due to: ' + status);
//                     }
//                 });
//             },
//             (error) => {
//                 alert("Error getting current location: " + error.message);
//                 console.error("Geolocation error:", error);
//             }
//         );
//     } else {
//         alert("Geolocation is not supported by your browser.");
//     }
// }

        function showLocationOptions() {
            const method = document.getElementById("location-method").value;
            document.querySelectorAll(".dropdown-options").forEach(option => option.style.display = "none");

            if (method === "current") {
                document.getElementById("current-location").style.display = "block";
                getCurrentLocation(); // Automatically get current location when this option is selected
            }
            if (method === "map") {
                document.getElementById("map-location").style.display = "block";
            }
        }
    </script>

    </body>
    </html>
    <!-- <script>
    let map, marker, autocomplete; -->

    <!-- // Initialize the map
    function initMap() {
        // Create a new map centered at a default location
        map = new google.maps.Map(document.getElementById("map"), {
            center: { lat: 9.5280, lng: 76.8227 }, // Default center (adjustable)
            zoom: 12
        });

        // Initialize the autocomplete for the search input
        const input = document.getElementById("map-input");
        autocomplete = new google.maps.places.Autocomplete(input);
        autocomplete.setFields(["geometry", "formatted_address"]);

        // Add listener for when a place is selected from the autocomplete
        autocomplete.addListener("place_changed", () => {
            const place = autocomplete.getPlace();
            if (!place.geometry) {
                alert("No details available for input: '" + place.name + "'");
                return;
            }

            // Get latitude and longitude from the selected place
            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();
            const formattedAddress = place.formatted_address;

            // Update form inputs with the selected location
            document.getElementById("pickup_location").value = `${lat}, ${lng}`;
            document.getElementById("map-input").value = formattedAddress; // Show formatted address in the input field

            // Update the map to center on the selected location
            map.setCenter(place.geometry.location);
            if (marker) marker.setMap(null); // Remove existing marker
            marker = new google.maps.Marker({
                position: place.geometry.location,
                map: map
            });

            console.log("Selected Location: ", formattedAddress, lat, lng);
        });

        // Add click event listener to the map to place a marker
        map.addListener("click", (event) => {
            const lat = event.latLng.lat();
            const lng = event.latLng.lng();

            // Update form inputs with the clicked location
            document.getElementById("pickup_location").value = `${lat}, ${lng}`;
            document.getElementById("map-input").value = `Lat: ${lat}, Lng: ${lng}`;

            // Update the map to place a marker at the clicked location
            if (marker) marker.setMap(null); // Remove existing marker
            marker = new google.maps.Marker({
                position: event.latLng,
                map: map
            });

            console.log("Clicked Location: ", lat, lng);
        });
    }

    // Get the user's current location
    // function getCurrentLocation() {
    //     if (navigator.geolocation) {
    //         navigator.geolocation.getCurrentPosition(
    //             (position) => {
    //                 const lat = position.coords.latitude;
    //                 const lng = position.coords.longitude;
    //                 const latLng = new google.maps.LatLng(lat, lng);

    //                 // Store the current location in the hidden field for form submission
    //                 document.getElementById("pickup_location").value = `${lat}, ${lng}`;
    //                 document.getElementById("current-location-input").value = `Lat: ${lat}, Lng: ${lng}`;

    //                 // Update the map to center on the current location
    //                 map.setCenter(latLng);
    //                 if (marker) marker.setMap(null); // Remove existing marker
    //                 marker = new google.maps.Marker({
    //                     position: latLng,
    //                     map: map
    //                 });

    //                 console.log("Current Location: ", lat, lng);
    //             },
    //             (error) => {
    //                 alert("Error getting current location: " + error.message);
    //                 console.error("Geolocation error:", error);
    //             }
    //         );
    //     } else {
    //         alert("Geolocation is not supported by your browser.");
    //     }
    // }

    // Function to get current location and reverse geocode it
// function getCurrentLocations() {
//     if (navigator.geolocation) {
//         navigator.geolocation.getCurrentPosition(function(position) {
//             const lat = position.coords.latitude;
//             const lng = position.coords.longitude;
            
//             // Create a Geocoder instance
//             const geocoder = new google.maps.Geocoder();
//             const latLng = new google.maps.LatLng(lat, lng);
            
//             // Geocode the coordinates to get the place name
//             geocoder.geocode({ 'location': latLng }, function(results, status) {
//                 if (status === google.maps.GeocoderStatus.OK) {
//                     if (results[0]) {
//                         // Get the address and set it to the input field
//                         const address = results[0].formatted_address;
//                         document.getElementById('current-location-input').value = address;
//                     } else {
//                         alert('No results found');
//                     }
//                 } else {
//                     alert('Geocoder failed due to: ' + status);
//                 }
//             });
//         }, function() {
//             alert("Geolocation service failed.");
//         });
//     } else {
//         alert("Geolocation is not supported by this browser.");
//     }
// }

// Function to show location options based on selected method
// function showLocationOptions() {
//     const method = document.getElementById("location-method").value;
//     document.querySelectorAll(".dropdown-options").forEach(option => option.style.display = "none");

//     if (method === "current") {
//         document.getElementById("current-location").style.display = "block";
//         getCurrentLocations(); // Automatically get current location when this option is selected
//     }
//     if (method === "map") {
//         document.getElementById("map-location").style.display = "block";
//     }
// }

// Load the map when the page is loaded
// google.maps.event.addDomListener(window, "load", initMap);

    // // Show the appropriate location input options based on the selected method
    // function showLocationOptions() {
    //     const method = document.getElementById("location-method").value;
    //     document.querySelectorAll(".dropdown-options").forEach(option => option.style.display = "none");

    //     if (method === "current") {
    //         document.getElementById("current-location").style.display = "block";
    //         getCurrentLocation(); // Automatically get current location when this option is selected
    //     }
    //     if (method === "map") {
    //         document.getElementById("map-location").style.display = "block";
    //     }
    // }

    // // Load the map when the page is loaded
    // google.maps.event.addDomListener(window, "load", initMap);
</script> -->

