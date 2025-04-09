<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== "driver") {
    header("Location: login.php");
    exit();
}

include 'connect.php';

// Step 1: Get the logged-in user's `userid` from `tbl_user`
$driver_username = $_SESSION['username'];
$user_query = "SELECT userid FROM tbl_user WHERE username = ?";
$stmt = $mysqli->prepare($user_query);

if (!$stmt) {
    die("User Query Preparation Failed: " . $mysqli->error);
}

$stmt->bind_param("s", $driver_username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found in tbl_user.");
}

$userid = $user['userid'];

// Step 2: Get the ambulance type from `tbl_driver` using `userid`
$ambulance_query = "SELECT ambulance_type FROM tbl_driver WHERE userid = ?";
$stmt = $mysqli->prepare($ambulance_query);

if (!$stmt) {
    die("Driver Query Preparation Failed: " . $mysqli->error);
}

$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if (!$driver) {
    die("Driver data not found for user ID $userid.");
}

$ambulance_type = $driver['ambulance_type'] ?? ''; // Default to empty if not found

// Step 3: Fetch ALL Emergency requests regardless of ambulance type
$emergency_query = "SELECT e.*, u.username as user_name, u.phoneno, u.email 
                   FROM tbl_emergency e
                   LEFT JOIN tbl_user u ON e.userid = u.userid
                   WHERE e.status = 'Pending'";
$stmt = $mysqli->prepare($emergency_query);

if (!$stmt) {
    die("Emergency Query Preparation Failed: " . $mysqli->error);
}

$stmt->execute();
$emergency_result = $stmt->get_result();

// Step 4: Fetch Prebooking requests that match the driver's ambulance type
if ($ambulance_type !== "Palliative") {
    $prebooking_query = "SELECT p.*, u.username as user_name, u.phoneno, u.email 
                         FROM tbl_prebooking p 
                         LEFT JOIN tbl_user u ON p.userid = u.userid 
                         WHERE p.status = 'Pending' AND p.ambulance_type = ?";
    $stmt = $mysqli->prepare($prebooking_query);

    if (!$stmt) {
        die("Prebooking Query Preparation Failed: " . $mysqli->error);
    }

    $stmt->bind_param("s", $ambulance_type);
    $stmt->execute();
    $prebooking_result = $stmt->get_result();
}

// Step 5: Fetch Palliative requests for palliative drivers only
if ($ambulance_type === "Palliative") {
    $palliative_query = "SELECT p.*, u.username as user_name, u.phoneno, u.email 
                         FROM tbl_palliative p 
                         LEFT JOIN tbl_user u ON p.userid = u.userid 
                         WHERE p.status = 'Pending'";
    $stmt = $mysqli->prepare($palliative_query);

    if (!$stmt) {
        die("Palliative Query Preparation Failed: " . $mysqli->error);
    }

    $stmt->execute();
    $palliative_result = $stmt->get_result();
}

// Function to send email
function sendConfirmationEmail($userEmail, $userName, $requestType, $requestId) {
    $to = $userEmail;
    $subject = "SWIFTAID - Request Accepted";
    $message = "Dear $userName,\n\n";
    $message .= "Your $requestType request (ID: $requestId) has been accepted by one of our drivers.\n";
    $message .= "We will arrive at your location as specified in your request.\n\n";
    $message .= "Thank you for choosing SWIFTAID.\n";
    $headers = "From: swiftaid@gmail.com";

    return mail($to, $subject, $message, $headers);
}

// Function to send SMS notification
function sendSMS($phoneNumber, $message) {
    // Replace with your preferred SMS gateway API
    // This is a placeholder for Twilio API integration
    $account_sid = 'YOUR_TWILIO_ACCOUNT_SID';
    $auth_token = 'YOUR_TWILIO_AUTH_TOKEN';
    $twilio_number = 'YOUR_TWILIO_PHONE_NUMBER';

    // You can use cURL to send the request to the SMS gateway
    $url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";

    $data = array(
        'From' => $twilio_number,
        'To' => $phoneNumber,
        'Body' => $message
    );

    $post = http_build_query($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$auth_token");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

// Handle accept request actions here if needed
if (isset($_POST['accept_emergency'])) {
    // Logic to accept emergency request
    $emergency_id = $_POST['emergency_id'];
    $user_phone = $_POST['user_phone'];
    $user_name = $_POST['user_name'];

    // Send SMS notification
    $sms_message = "SWIFTAID: Hello $user_name, your emergency request (ID: $emergency_id) has been accepted. Our driver is on the way to your location.";
    sendSMS($user_phone, $sms_message);

    // Additional logic to update database status, etc.
}

if (isset($_POST['accept_prebooking'])) {
    // Logic to accept prebooking request
}

if (isset($_POST['accept_palliative'])) {
    // Logic to accept palliative request
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - SWIFTAID</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="assets/css/main.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <!-- Firebase -->
    <script src="https://www.gstatic.com/firebasejs/9.6.11/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.11/firebase-database-compat.js"></script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 90px;
            --primary-color: rgb(5, 30, 16);
            --secondary-color: rgb(40, 186, 18);
        }
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
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
        }
        .sitename {
            color: var(--primary-color);
            font-size: 24px;
        }
        .navmenu ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 20px;
        }
        .navmenu a {
            color: rgb(155, 156, 157);
            text-decoration: none;
            font-weight: 500;
        }
        .btn-getstarted {
            background: var(--primary-color);
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: var(--header-height);
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: rgba(218, 214, 214, 0.46);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px 0;
        }
        .sidebar-nav {
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .sidebar-nav li {
            padding: 10px 20px;
        }
        .sidebar-nav a {
            color: #012970;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-nav a:hover {
            color: var(--primary-color);
        }
        .main {
            margin-left: var(--sidebar-width);
            padding: 20px;
            margin-top: var(--header-height);
        }
        .dashboard-card {
            background: rgba(246, 236, 236, 0.46);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-pending {
            color: #ffa500;
            font-weight: bold;
        }
        .status-active {
            color: #00a65a;
            font-weight: bold;
        }
        .request-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            justify-content: center;
            margin: 10px auto;
            max-width: 1200px;
        }
        .request-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #eee;
            margin: 0 auto;
            width: 100%;
            max-width: 350px;
        }
        .design {
            background-color: rgba(243, 34, 34, 0.87);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
        }
        .design:hover {
            background-color: darkred;
        }
        .highlighted-type {
            font-size: 1.1em;
            margin: 10px 0;
        }
        .ambulance-type {
            background-color: rgb(61, 186, 69);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <aside class="sidebar">
        <ul class="sidebar-nav">
        <li><a href="driver.php"><i class="bi bi-person-circle"></i> <span><?php echo htmlspecialchars($_SESSION['username']); ?></span></a></li>
        <li><a href="driver_profile.php"><i class="bi bi-person"></i> <span>My Profile</span></a></li>
        <li><a href="driver_notes.php"><i class="bi bi-person"></i> <span>Assigned Task</span></a></li>
        <li><a href="driverPreviousJob.php"><i class="bi bi-clock-history"></i> <span>Previous Jobs</span></a></li>
        <li><a href="driver_schedule.php"><i class="bi bi-calendar-event"></i> <span>Emergency Schedule</span></a></li>
        <li><a href="driver_leave.php"><i class="bi bi-calendar-check"></i> <span>Leave</span></a></li>
        <li><a href="driver_review.php"><i class="bi bi-chat-dots"></i> <span>Feedback</span></a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a></li>

        </ul>
    </aside>

    <main class="main">
        <div class="dashboard-card">
            <h3>Emergency Requests</h3>
            <div class="request-grid">
                <?php if (isset($emergency_result) && $emergency_result && $emergency_result->num_rows > 0): ?>
                    <?php while ($request = $emergency_result->fetch_assoc()): ?>
                        <div class="request-card emergency">
                            <h4>Emergency Request : <?php echo htmlspecialchars($request['request_id']); ?></h4>
                            <p><strong>Patient:</strong> <?php echo htmlspecialchars($request['patient_name']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($request['pickup_location']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($request['contact_phone']); ?></p>
                            <p class="highlighted-type"><strong>Type:</strong> <span class="ambulance-type"><?php echo htmlspecialchars($request['ambulance_type']); ?></span></p>
                            <form action="handle_request.php" method="POST" class="accept-form">
                                <input type="hidden" name="request_type" value="emergency">
                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                <input type="hidden" name="user_phone" value="<?php echo htmlspecialchars($request['contact_phone']); ?>">
                                <input type="hidden" name="user_name" value="<?php echo htmlspecialchars($request['patient_name']); ?>">
                                <button type="submit" class="design">Accept Request</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-requests">No emergency requests at the moment.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($ambulance_type !== "Palliative") : ?>
        <div class="dashboard-card">
            <h3>Prebooking Requests (<?php echo htmlspecialchars($ambulance_type); ?> Type)</h3>
            <div class="request-grid">
                <?php if (isset($prebooking_result) && $prebooking_result && $prebooking_result->num_rows > 0) : ?>
                    <?php while ($request = $prebooking_result->fetch_assoc()) : ?>
                        <div class="request-card prebooking">
                            <h4>Prebooking Request <?php echo htmlspecialchars($request['prebookingid']); ?></h4>
                            <p><strong>User:</strong> <?php echo htmlspecialchars($request['user_name'] ?? 'Unknown User'); ?></p>
                            <p><strong>From:</strong> <?php echo htmlspecialchars($request['pickup_location']); ?></p>
                            <p><strong>To:</strong> <?php echo htmlspecialchars($request['destination']); ?></p>
                            <p><strong>Service Time:</strong> <?php echo htmlspecialchars($request['service_time']); ?></p>
                            <p><strong>Type:</strong> <?php echo htmlspecialchars($request['ambulance_type']); ?></p>
                            <p><strong>Additional:</strong> <?php echo htmlspecialchars($request['additional_requirements']); ?></p>
                            <p><strong>Phone No:</strong> <?php echo htmlspecialchars($request['phoneno']); ?></p>
                            <form action="handle_request.php" method="POST" class="accept-form">
                                <input type="hidden" name="request_type" value="prebooking">
                                <input type="hidden" name="request_id" value="<?php echo $request['prebookingid']; ?>">
                                <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($request['email'] ?? ''); ?>">
                                <input type="hidden" name="user_phone" value="<?php echo htmlspecialchars($request['phoneno']); ?>">
                                <input type="hidden" name="user_name" value="<?php echo htmlspecialchars($request['user_name'] ?? 'User'); ?>">
                                <button type="submit" class="design">Accept Request</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else : ?>
                    <p class="no-requests">No prebooking requests for <?php echo htmlspecialchars($ambulance_type); ?> ambulance at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($ambulance_type === "Palliative") : ?>
        <div class="dashboard-card">
            <h3>Palliative Care Requests</h3>
            <div class="request-grid">
                <?php if (isset($palliative_result) && $palliative_result && $palliative_result->num_rows > 0): ?>
                    <?php while ($request = $palliative_result->fetch_assoc()): ?>
                        <div class="request-card emergency">
                            <h4>Palliative Request : <?php echo htmlspecialchars($request['palliativeid']); ?></h4>
                            <p><strong>Patient:</strong> <?php echo htmlspecialchars($request['user_name']); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($request['address']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($request['phoneno']); ?></p>
                            <p><strong>Additional Requirements:</strong> <?php echo htmlspecialchars($request['additional_requirements']); ?></p>
                            <p><strong>Medical Condition:</strong> <?php echo htmlspecialchars($request['medical_condition']); ?></p>
                            <form action="handle_palliative.php" method="POST" class="accept-form">
                                <input type="hidden" name="request_type" value="palliative">
                                <input type="hidden" name="request_id" value="<?php echo $request['palliativeid']; ?>">
                                <input type="hidden" name="user_phone" value="<?php echo htmlspecialchars($request['phoneno']); ?>">
                                <input type="hidden" name="user_name" value="<?php echo htmlspecialchars($request['user_name']); ?>">
                                <button type="submit" class="design">Accept Request</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-requests">No Palliative Care requests at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Firebase configuration
        const firebaseConfig = {
            apiKey: "AIzaSyDYYyUORNusV_DVD3senGL0dkEtDpUhjvs",
            authDomain: "swiftaidtracking.firebaseapp.com",
            databaseURL: "https://swiftaidtracking-default-rtdb.asia-southeast1.firebasedatabase.app",
            projectId: "swiftaidtracking",
            storageBucket: "swiftaidtracking.firebasestorage.app",
            messagingSenderId: "304104708742",
            appId: "1:304104708742:web:d1e1d833e0286661080f07"
        };

        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);

        // Function to start tracking the driver's location
        function startTracking(requestType, requestId) {
            if (navigator.geolocation) {
                const watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        const locationData = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            timestamp: firebase.database.ServerValue.TIMESTAMP,
                            driverId: '<?php echo $_SESSION["user_id"]; ?>'
                        };

                        console.log('Updating driver location:', locationData); // Add debugging

                        // Store location in Firebase under request type and ID
                        firebase.database().ref(`${requestType}_${requestId}`).set(locationData)
                            .then(() => {
                                console.log('Location updated successfully');
                            })
                            .catch(error => {
                                console.error("Error updating location:", error);
                                alert("Failed to update location. Please check your connection.");
                            });
                    },
                    (error) => {
                        console.error("Geolocation error:", error);
                        alert("Please enable location services to accept requests");
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 30000,
                        timeout: 27000
                    }
                );

                // Store watch ID to stop tracking when needed
                localStorage.setItem('locationWatchId', watchId);
                console.log('Started tracking with watchId:', watchId);
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        }

        // Add event listeners to accept forms
        document.querySelectorAll('.accept-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm("Are you sure you want to accept this request?")) {
                    e.preventDefault();
                    return;
                }

                const requestType = this.querySelector('input[name="request_type"]').value;
                const requestId = this.querySelector('input[name="request_id"]').value;
                
                // Start tracking when request is accepted
                startTracking(requestType, requestId);
            });
        });
    </script>
</body>
</html>