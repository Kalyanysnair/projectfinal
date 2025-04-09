<?php
session_start();
require 'connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    die("<div class='alert alert-danger'>Error: User not logged in. Please <a href='login.php'>log in</a> to view booking details.</div>");
}

$userid = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$request_type = isset($_GET['type']) ? $_GET['type'] : '';

if ($request_id <= 0 || empty($request_type)) {
    die("<div class='alert alert-danger'>Invalid request parameters. Please provide valid booking ID and type.</div>");
}

$booking_details = [];
$driver_details = [];
$error_message = "";

try {
    // Determine which table to query based on request type
    $table_info = [
        'emergency' => ['table' => 'tbl_emergency', 'id_field' => 'request_id'],
        'prebooking' => ['table' => 'tbl_prebooking', 'id_field' => 'prebookingid'],
        'palliative' => ['table' => 'tbl_palliative', 'id_field' => 'palliativeid']
    ];
    
    if (!isset($table_info[$request_type])) {
        die("<div class='alert alert-danger'>Invalid request type specified.</div>");
    }
    
    $table = $table_info[$request_type]['table'];
    $id_field = $table_info[$request_type]['id_field'];
    
    // Fetch booking details
    $query = "SELECT b.*, u.username as patient_name, u.phoneno as contact_phone 
              FROM $table b 
              LEFT JOIN tbl_user u ON b.userid = u.userid
              WHERE b.$id_field = ? 
              AND (b.userid = ? OR b.userid IS NULL)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Database error: " . $conn->error);
    
    $stmt->bind_param("ii", $request_id, $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Booking not found or you don't have permission to view it.");
    }
    
    $booking_details = $result->fetch_assoc();
    $stmt->close();

    // Fetch driver details based on the driver_id from booking
    if (!empty($booking_details['driver_id'])) {
        $driver_query = "SELECT d.*, u.username as driver_name, u.phoneno as driver_phone,
                        u.email as driver_email, u.latitude, u.longitude, u.status as user_status
                        FROM tbl_driver d
                        JOIN tbl_user u ON d.userid = u.userid
                        WHERE d.driver_id = ?";
        
        $stmt = $conn->prepare($driver_query);
        if (!$stmt) throw new Exception("Database error preparing driver query: " . $conn->error);
        
        $stmt->bind_param("i", $booking_details['driver_id']);
        $stmt->execute();
        $driver_result = $stmt->get_result();
        
        if ($driver_result->num_rows > 0) {
            $driver_details = $driver_result->fetch_assoc();
        } else {
            // If no results using driver_id, try an alternative approach
            $stmt->close();
            
            $fallback_query = "SELECT d.*, u.username as driver_name, u.phoneno as driver_phone,
                              u.email as driver_email, u.latitude, u.longitude, u.status as user_status
                              FROM tbl_user u
                              JOIN tbl_driver d ON u.userid = d.userid
                              WHERE u.userid = ? AND u.role = 'driver'";
            
            $stmt = $conn->prepare($fallback_query);
            $stmt->bind_param("i", $booking_details['driver_id']);
            $stmt->execute();
            $fallback_result = $stmt->get_result();
            
            if ($fallback_result->num_rows > 0) {
                $driver_details = $fallback_result->fetch_assoc();
            } else {
                $error_message = "Driver details not found. Please check if the driver is properly registered.";
            }
        }
        $stmt->close();
    } else {
        $error_message = "No driver has been assigned to this booking.";
    }
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

$request_type_name = match($request_type) {
    'emergency' => 'Emergency Request',
    'prebooking' => 'Pre-Booking',
    'palliative' => 'Palliative Care',
    default => 'Booking'
};

$booking_status = $booking_details['status'] ?? 'Pending';

// Format locations for Google Maps
$pickup_location = $booking_details['pickup_location'] ?? '';
$destination = $booking_details['destination'] ?? '';

// Format driver location for Google Maps
$driver_lat = !empty($driver_details['latitude']) ? $driver_details['latitude'] : '';
$driver_lng = !empty($driver_details['longitude']) ? $driver_details['longitude'] : '';
$driver_location = (!empty($driver_lat) && !empty($driver_lng)) ? "$driver_lat,$driver_lng" : '';

// Generate map links
$map_link = (!empty($driver_location)) 
          ? "https://www.google.com/maps?q=$driver_location" 
          : "#";

$directions_link = (!empty($pickup_location) && !empty($destination))
                 ? "https://www.google.com/maps/dir/?api=1&origin=" . urlencode($pickup_location) . "&destination=" . urlencode($destination)
                 : "#";

$tracking_link = (!empty($driver_location) && !empty($pickup_location))
               ? "https://www.google.com/maps/dir/?api=1&origin=$driver_location&destination=" . urlencode($pickup_location)
               : "#";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($request_type_name) ?> Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2E8B57;
            --secondary-color: #3CB371;
            --accent-color: #FF6B6B;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .main-content {
            padding-top: 20px;
            min-height: 100vh;
        }
        .booking-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .content-wrapper {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 30px;
            margin-top: 60px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border: none;
            overflow: hidden;
            background-color: rgba(255, 255, 255, 0.95);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 18px 25px;
            border-bottom: none;
            font-weight: 600;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: capitalize;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .status-pending { background-color: #FFF3CD; color: #856404; }
        .status-accepted { background-color: #D4EDDA; color: #155724; }
        .status-completed { background-color: #D1ECF1; color: #0C5460; }
        .status-cancelled { background-color: #F8D7DA; color: #721C24; }
        .driver-card {
            border-left: 5px solid var(--primary-color);
        }
        .driver-avatar {
            width: 90px;
            height: 90px;
            background: linear-gradient(145deg, #e6e6e6, #ffffff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: var(--primary-color);
            margin-right: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 3px solid rgba(46, 139, 87, 0.2);
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }
        .info-label i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        .info-value {
            color: var(--dark-color);
            margin-bottom: 18px;
            font-size: 1.05rem;
            padding: 10px 15px;
            background-color: rgba(240, 240, 240, 0.5);
            border-radius: 8px;
            border-left: 3px solid var(--secondary-color);
        }
        .btn {
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
        }
        .btn-light {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #495057;
            border: none;
        }
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #0dcaf0);
            color: white;
            border: none;
        }
        .driver-contact {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background-color: rgba(255,255,255,0.95);
            border-left: 4px solid #28a745;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .toast-body {
            color: #155724;
            font-weight: 500;
        }
        .section-title {
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 25px;
            font-weight: 600;
            color: var(--dark-color);
        }
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
        }
        .map-container {
            position: relative;
            height: 400px;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        #map {
            height: 100%;
            width: 100%;
        }
        .map-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 10;
            max-width: 200px;
        }
        .map-legend {
            font-size: 0.9rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        .map-legend i {
            margin-right: 8px;
        }
        .map-preview {
            height: 250px;
            width: 100%;
            background-color: #f0f0f0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .map-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .map-overlay-preview {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        .map-overlay-preview:hover {
            background: rgba(0,0,0,0.5);
        }
        .eta-display {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .eta-time {
            font-size: 1.8rem;
            font-weight: 600;
        }
        .eta-label {
            font-size: 1rem;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
            margin-bottom: 15px;
        }
        .action-buttons .btn {
            flex: 1;
            min-width: 200px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
        }
        .action-buttons .btn i {
            font-size: 1.2rem;
        }
        @media (max-width: 768px) {
            .driver-avatar {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
                margin-right: 15px;
            }
            .content-wrapper {
                padding: 20px;
                margin-top: 40px;
            }
            .driver-contact, .action-buttons {
                flex-direction: column;
            }
            .map-container {
                height: 300px;
            }
            .action-buttons .btn {
                width: 100%;
            }
            .eta-display {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="booking-container">
            <div class="content-wrapper">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><?= htmlspecialchars($request_type_name) ?> Details</h3>
                        <a href="status.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Bookings
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-warning mb-4"><?= htmlspecialchars($error_message) ?></div>
                        <?php endif; ?>
                        
                        <h4 class="section-title"><i class="fas fa-info-circle"></i> Booking Information</h4>
                        
                        <div class="row">
                            <?php 
                            $exclude_fields = ['userid', 'driver_id', 'request_id', 'prebookingid', 'palliativeid', 
                                            'created_at', 'updated_at', 'payment_status'];
                            
                            foreach ($booking_details as $key => $value): 
                                if (!in_array($key, $exclude_fields) && !empty($value)):
                                    $display_key = ucwords(str_replace('_', ' ', $key));
                            ?>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label"><i class="fas fa-circle-info"></i> <?= $display_key ?></div>
                                    <div class="info-value">
                                        <?php if ($key === 'status'): ?>
                                            <span class="status-badge status-<?= strtolower($value) ?>">
                                                <?= $value ?>
                                            </span>
                                        <?php elseif (in_array($key, ['created_at', 'updated_at', 'service_time'])): ?>
                                            <?= date('d M Y, h:i A', strtotime($value)) ?>
                                        <?php elseif ($key === 'amount'): ?>
                                            ₹<?= number_format($value, 2) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($value) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>
                        
                        <!-- Map and Direction buttons -->
                        <!-- <div class="action-buttons">
                            <a href="<?= $directions_link ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-route"></i> Get Directions
                            </a>
                            <?php if (!empty($driver_location) && !empty($pickup_location)): ?>
                            <a href="<?= $tracking_link ?>" target="_blank" class="btn btn-info">
                                <i class="fas fa-map-marked-alt"></i> Track Driver
                            </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-success" id="refreshLocationBtn">
                                <i class="fas fa-sync-alt"></i> Refresh Driver Location
                            </button>
                        </div> -->
                        
                        <?php if (!empty($driver_location) && !empty($pickup_location)): ?>
                        <!-- ETA Display -->
                        <div class="eta-display">
                            <div>
                                <div class="eta-label">Estimated Time of Arrival</div>
                                <div class="eta-time" id="eta-value">Calculating...</div>
                            </div>
                            <div>
                                <i class="fas fa-ambulance fa-2x"></i>
                            </div>
                        </div>
                        
                        <!-- Live Map -->
                        <div class="map-container">
                            <div id="map"></div>
                            <div class="map-overlay">
                                <div class="map-legend"><i class="fas fa-map-marker-alt text-danger"></i> Pickup Location</div>
                                <div class="map-legend"><i class="fas fa-ambulance text-primary"></i> Driver Location</div>
                                <?php if (!empty($destination)): ?>
                                <div class="map-legend"><i class="fas fa-hospital text-success"></i> Destination</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card driver-card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-user-shield"></i> Driver Information</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($driver_details)): ?>
                            <div class="d-flex align-items-center mb-4">
                                <div class="driver-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?= htmlspecialchars($driver_details['driver_name']) ?></h3>
                                    <div class="driver-contact">
                                        <button id="copyPhoneBtn" class="btn btn-success" onclick="copyToClipboard('<?= htmlspecialchars($driver_details['driver_phone']) ?>')">
                                            <i class="fas fa-copy"></i> Copy Phone Number
                                        </button>
                                        <?php if (!empty($driver_location)): ?>
                                        <a href="<?= $map_link ?>" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-map-marker-alt"></i> View Driver Location
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="info-label"><i class="fas fa-phone"></i> Phone Number</div>
                                    <div class="info-value"><?= htmlspecialchars($driver_details['driver_phone']) ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                                    <div class="info-value"><?= htmlspecialchars($driver_details['driver_email']) ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label"><i class="fas fa-ambulance"></i> Vehicle Number</div>
                                    <div class="info-value"><?= htmlspecialchars($driver_details['vehicle_no']) ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label"><i class="fas fa-hospital"></i> Ambulance Type</div>
                                    <div class="info-value"><?= htmlspecialchars($driver_details['ambulance_type']) ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label"><i class="fas fa-id-card"></i> License Number</div>
                                    <div class="info-value"><?= htmlspecialchars($driver_details['lisenceno']) ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label"><i class="fas fa-map-marker-alt"></i> Service Area</div>
                                    <div class="info-value"><?= htmlspecialchars($driver_details['service_area']) ?></div>
                                </div>
                            </div>

                            <?php if (!empty($driver_location)): ?>
                            <div class="col-12 mt-3">
                                <div class="info-label"><i class="fas fa-map-pin"></i> Current Location</div>
                                <a href="<?= $map_link ?>" target="_blank" class="text-decoration-none">
                                    <div class="map-preview">
                                        <img src="https://maps.googleapis.com/maps/api/staticmap?center=<?= $driver_location ?>&zoom=14&size=600x300&markers=color:red%7C<?= $driver_location ?>&key=YOUR_API_KEY" alt="Driver Location Map">
                                        <div class="map-overlay-preview">
                                            <i class="fas fa-external-link-alt me-2"></i> Click to open in Google Maps
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="alert alert-info">
                                <p><i class="fas fa-info-circle"></i> No driver information available.</p>
                                <p>This may be because:</p>
                                <ul>
                                    <li>No driver has been assigned to this booking yet</li>
                                    <li>The assigned driver's details are incomplete</li>
                                    <li>There was an error retrieving driver information</li>
                                </ul>
                                <p>If you need immediate assistance, please contact support.</p>
                                
                                <?php if (!empty($booking_details['driver_id'])): ?>
                                <div class="mt-3">
                                    <strong>Debug Info:</strong> 
                                    <p>Looking for driver with ID: <?= htmlspecialchars($booking_details['driver_id']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast for copy notification -->
    <div class="toast align-items-center" role="alert" aria-live="assertive" aria-atomic="true" id="copyToast">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle text-success"></i> Phone number copied to clipboard!
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Google Maps API Script -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places,geometry&callback=initMap" async defer></script>
    
    <script>
    // Copy to clipboard function
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            const toast = new bootstrap.Toast(document.getElementById('copyToast'));
            toast.show();
        }).catch(function() {
            // Fallback for browsers that don't support clipboard API
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            
            const toast = new bootstrap.Toast(document.getElementById('copyToast'));
            toast.show();
        });
    }
    
    // Map variables
    let map;
    let driverMarker;
    let pickupMarker;
    let destinationMarker;
    let directionsService;
    let directionsRenderer;
    let infoWindow;
    let geocoder;
    let driverLocation = <?= !empty($driver_lat) && !empty($driver_lng) ? "{lat: $driver_lat, lng: $driver_lng}" : 'null' ?>;
    let pickupLocation = null;
    let destinationLocation = null;
    let etaInterval;
    
    // Initialize the map
    function initMap() {
        if (!driverLocation) {
            console.log("No driver location available");
            return;
        }
        
        // Create map instance
        map = new google.maps.Map(document.getElementById('map'), {
            zoom: 13,
            center: driverLocation,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            zoomControl: true,
        });
        
        // Create directions service and renderer
        directionsService = new google.maps.DirectionsService();
        directionsRenderer = new google.maps.DirectionsRenderer({
            map: map,
            suppressMarkers: true,
            polylineOptions: {
                strokeColor: '#2E8B57',
                strokeWeight: 5,
                strokeOpacity: 0.7
            }
        });
        
        // Create info window
        infoWindow = new google.maps.InfoWindow();
        
        // Create a geocoder
        geocoder = new google.maps.Geocoder();
        
        // Create driver marker
        driverMarker = new google.maps.Marker({
            position: driverLocation,
            map: map,
            title: "Driver Location",
            icon: {
                path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
                scale: 6,
                fillColor: '#3498db',
                fillOpacity: 1,
                strokeWeight: 2,
                strokeColor: '#ffffff',
                rotation: 0
            }
        });
        
        // Try to geocode pickup location
        geocodeAddress('<?= addslashes($pickup_location) ?>', function(location) {
            pickupLocation = location;
            if (location) {
                // Create pickup marker
                pickupMarker = new google.maps.Marker({
                    position: location,
                    map: map,
                    title: "Pickup Location",
                    icon: {
                        url: "https://maps.google.com/mapfiles/ms/icons/red-dot.png"
                    }
                });
                
                // Try to geocode destination if exists
                <?php if (!empty($destination)): ?>
                geocodeAddress('<?= addslashes($destination) ?>', function(destLocation) {
                    destinationLocation = destLocation;
                    if (destLocation) {
                        // Create destination marker
                        destinationMarker = new google.maps.Marker({
                            position: destLocation,
                            map: map,
                            title: "Destination",
                            icon: {
                                url: "https://maps.google.com/mapfiles/ms/icons/green-dot.png"
                            }
                        });
                    }
                    
                    // Calculate route and ETA
                    calculateRouteAndETA();
                });
                <?php else: ?>
                // Calculate route and ETA without destination
                calculateRouteAndETA();
                <?php endif; ?>
            }
        });
        
        // Set up refresh button
        document.getElementById('refreshLocationBtn').addEventListener('click', function() {
            refreshDriverLocation();
        });
    }
    
    // Geocode an address string to lat/lng
    function geocodeAddress(address, callback) {
        if (!address || address.trim() === '') {
            callback(null);
            return;
        }
        
        geocoder.geocode({ address: address }, function(results, status) {
            if (status === 'OK' && results[0]) {
                callback(results[0].geometry.location);
            } else {
                console.log('Geocode was not successful for the following reason: ' + status);
                callback(null);
            }
        });
    }
    
    // Calculate route and ETA
    function calculateRouteAndETA() {
        if (!driverLocation || !pickupLocation) {
            document.getElementById('eta-value').textContent = "Location data missing";
            return;
        }
        
        const request = {
            origin: driverLocation,
            destination: pickupLocation,
            travelMode: 'DRIVING',
            provideRouteAlternatives: false,
            drivingOptions: {
                departureTime: new Date(),
                trafficModel: 'bestguess'
            }
        };
        
        directionsService.route(request, function(response, status) {
            if (status === 'OK') {
                directionsRenderer.setDirections(response);
                
                // Calculate and display ETA
                const route = response.routes[0];
                let totalSeconds = 0;
                let totalDistance = 0;
                
                route.legs.forEach(leg => {
                    totalSeconds += leg.duration.value;
                    totalDistance += leg.distance.value;
                });
                
                const etaMinutes = Math.ceil(totalSeconds / 60);
                document.getElementById('eta-value').textContent = etaMinutes + ' minutes';
                
                // Fit bounds to show the entire route
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(driverLocation);
                bounds.extend(pickupLocation);
                if (destinationLocation) bounds.extend(destinationLocation);
                map.fitBounds(bounds);
                
                // Set up periodic refresh of ETA
                if (etaInterval) clearInterval(etaInterval);
                etaInterval = setInterval(function() {
                    refreshETA();
                }, 30000); // Refresh every 30 seconds
            } else {
                document.getElementById('eta-value').textContent = "Could not calculate route";
                console.log('Directions request failed due to ' + status);
            }
        });
    }
    
    // Refresh ETA calculation
    function refreshETA() {
        if (!driverLocation || !pickupLocation) return;
        
        const request = {
            origin: driverLocation,
            destination: pickupLocation,
            travelMode: 'DRIVING',
            drivingOptions: {
                departureTime: new Date(),
                trafficModel: 'bestguess'
            }
        };
        
        directionsService.route(request, function(response, status) {
            if (status === 'OK') {
                const route = response.routes[0];
                let totalSeconds = 0;
                
                route.legs.forEach(leg => {
                    totalSeconds += leg.duration.value;
                });
                
                const etaMinutes = Math.ceil(totalSeconds / 60);
                document.getElementById('eta-value').textContent = etaMinutes + ' minutes';
            }
        });
    }
    
    // Refresh driver location
    function refreshDriverLocation() {
        // In a real app, you would make an AJAX call to your server to get updated driver location
        // For this demo, we'll just simulate a small position change
        if (driverMarker) {
            // Simulate movement (in a real app, get actual new coordinates from server)
            const latChange = (Math.random() - 0.5) * 0.01;
            const lngChange = (Math.random() - 0.5) * 0.01;
            
            driverLocation = {
                lat: driverLocation.lat + latChange,
                lng: driverLocation.lng + lngChange
            };
            
            // Move the marker
            driverMarker.setPosition(driverLocation);
            
            // Recalculate route and ETA
            calculateRouteAndETA();
            
            // Show toast notification
            const toast = new bootstrap.Toast(document.getElementById('copyToast'));
            document.querySelector('.toast-body').innerHTML = '<i class="fas fa-check-circle text-success"></i> Driver location refreshed!';
            toast.show();
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (map) {
            google.maps.event.trigger(map, 'resize');
        }
    });
    </script>
</body>
</html>