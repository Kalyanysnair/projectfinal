<?php
// Initialize session
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['request_id']) || !isset($_GET['booking_type'])) {
    die("Invalid request");
}

$userid = $_SESSION['user_id'];
$request_id = $_GET['request_id'];
$booking_type = $_GET['booking_type'];

// Add this at the beginning of payment.php after getting $request_id
error_log("Payment Debug: Starting payment process");
error_log("Payment Debug: REQUEST parameters: " . print_r($_GET, true));
error_log("Payment Debug: SESSION user_id: " . $_SESSION['user_id']);

// Fetch booking details and calculate amount
try {
    // First verify if the booking exists and get its status
    switch($booking_type) {
        case 'emergency':
            $verify_query = "SELECT status, ambulance_type FROM tbl_emergency 
                           WHERE request_id = ? AND (userid = ? OR userid IS NULL)";
            break;
        case 'prebooking':
            $verify_query = "SELECT status, ambulance_type FROM tbl_prebooking 
                           WHERE prebookingid = ? AND userid = ?";
            break;
        case 'palliative':
            $verify_query = "SELECT status, ambulance_type FROM tbl_palliative 
                           WHERE palliativeid = ? AND userid = ?";
            break;
        default:
            error_log("Payment Debug: Invalid booking type: " . $booking_type);
            die("Invalid booking type: " . htmlspecialchars($booking_type));
    }

    error_log("Payment Debug: Verify query: " . $verify_query);
    error_log("Payment Debug: Request ID: " . $request_id . ", User ID: " . $userid);

    // Check if booking exists and its status
    $stmt = $conn->prepare($verify_query);
    if (!$stmt) {
        error_log("Payment Debug: Prepare failed: " . $conn->error);
        die("Database error: Prepare failed");
    }

    $stmt->bind_param("ii", $request_id, $userid);
    $stmt->execute();
    $verify_result = $stmt->get_result();
    $booking_check = $verify_result->fetch_assoc();

    if (!$booking_check) {
        error_log("Payment Debug: No booking found with these parameters:");
        error_log("Payment Debug: SQL: " . str_replace('?', "'%s'", $verify_query));
        error_log("Payment Debug: Values: request_id=" . $request_id . ", userid=" . $userid);
        die("Booking not found. Please check the booking ID. (Type: " . htmlspecialchars($booking_type) . ", ID: " . htmlspecialchars($request_id) . ")");
    }

    error_log("Payment Debug: Booking found with status: " . $booking_check['status']);

    if ($booking_check['status'] !== 'Completed') {
        error_log("Payment Debug: Invalid booking status: " . $booking_check['status']);
        die("This booking is not eligible for payment. Current status: " . htmlspecialchars($booking_check['status']) . " (must be 'Completed')");
    }

    // Now proceed with the main query
    switch($booking_type) {
        case 'emergency':
            $query = "SELECT request_id, ambulance_type, pickup_location, amount FROM tbl_emergency 
                     WHERE request_id = ? AND (userid = ? OR userid IS NULL)";
            break;
        case 'prebooking':
            $query = "SELECT prebookingid as request_id, ambulance_type, pickup_location, amount FROM tbl_prebooking 
                     WHERE prebookingid = ? AND userid = ? AND status = 'Completed'";
            break;
        case 'palliative':
            $query = "SELECT palliativeid as request_id, 'Palliative Care' as ambulance_type, address as pickup_location, amount 
                     FROM tbl_palliative 
                     WHERE palliativeid = ? AND userid = ? AND status = 'Completed'";
            break;
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $request_id, $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    // Get amount directly from the booking
    $amount = $booking['amount'];

    // If amount is not set in the database, use default pricing
    if (!$amount) {
        switch ($booking['ambulance_type']) {
            case 'Basic':
                $amount = 1500;
                break;
            case 'Advanced':
                $amount = 2500;
                break;
            case 'Palliative Care':
                $amount = 3000;
                break;
            default:
                $amount = 2000;
        }
    }

} catch (Exception $e) {
    error_log("Payment Debug: Exception - " . $e->getMessage());
    die("Error: " . $e->getMessage());
}

// Display error message if it exists
$error_message = '';
if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear the message
}

// Process the form only if it was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize response array
    $response = array(
        'success' => false,
        'message' => ''
    );

    // Get form data and sanitize inputs
    $cardName = isset($_POST['cardName']) ? filter_var($_POST['cardName'], FILTER_SANITIZE_STRING) : '';
    $cardNumber = isset($_POST['cardNumber']) ? preg_replace('/\D/', '', $_POST['cardNumber']) : '';
    $expiry = isset($_POST['expiry']) ? filter_var($_POST['expiry'], FILTER_SANITIZE_STRING) : '';
    $cvv = isset($_POST['cvv']) ? filter_var($_POST['cvv'], FILTER_SANITIZE_STRING) : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    
    // Validate inputs
    $errors = array();
    
    // Validation logic remains the same...
    // [All your validation code]
    
    // Process payment if no errors
    if (empty($errors)) {
        // In a real application, you would integrate with a payment gateway here
        // This is just a simulation
        
        // For demo purposes, we'll pretend the payment was successful
        $paymentSuccessful = true;
        
        if ($paymentSuccessful) {
            // Record transaction in database (in a real application)
            // generateTransactionRecord($cardName, $email, 149.99);
            
            // Prepare success response
            $response['success'] = true;
            $response['message'] = 'Payment processed successfully! An email confirmation has been sent.';
            
            // Send email confirmation (in a real application)
            // sendConfirmationEmail($email);
            
            // Redirect to success page
            header('Location: payment_success.php');
            exit;
        } else {
            $response['message'] = 'Payment failed. Please try again or contact support.';
        }
    } else {
        // Errors found, return first error
        $response['message'] = $errors[0];
    }
    
    // Redirect to error page if there are issues
    if (!$response['success']) {
        $_SESSION['error_message'] = $response['message'];
        header('Location: payment.php?error=1');
        exit;
    }
}

// Helper functions
function validateCardNumber($number) {
    // Your existing function
}

function checkExpiryDate($month, $year) {
    // Your existing function
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Ambulance Service</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        body {
            
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/road.jpg');
            background-size: cover;
            background-position: center;
            padding-top: 50px;
        }
        .payment-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            margin-top: 60px;
            background-color:  rgba(240, 237, 237, 0.59);
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .booking-details {
            margin-bottom: 20px;
            padding: 15px;
            background-color:rgba(248, 249, 250, 0.63);
            border-radius: 5px;
        }
        .payment-button {
            background-color: #2E8B57;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .payment-button:hover {
            background-color: #3CB371;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="payment-container">
        <h2 class="mb-4">Payment Details</h2>
        
        <div class="booking-details">
            <h4>Booking Information</h4>
            <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking['request_id']); ?></p>
            <p><strong>Service Type:</strong> <?php echo htmlspecialchars($booking['ambulance_type']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($booking['pickup_location']); ?></p>
            <p><strong>Amount:</strong> ₹<?php echo number_format($amount, 2); ?></p>
        </div>

        <button id="payButton" class="payment-button">Pay Now</button>
    </div>

    <script>
        document.getElementById('payButton').onclick = function(e) {
            var options = {
                "key": "rzp_test_3C5rU9ZqNfv2Y8", // Your Razorpay test key
                "amount": "<?php echo $amount * 100; ?>",
                "currency": "INR",
                "name": "Ambulance Service",
                "description": "Payment for <?php echo htmlspecialchars($booking['ambulance_type']); ?> Service",
                "handler": function (response) {
                    console.log('Payment response:', response); // Debug log
                    if (response.razorpay_payment_id) {
                        console.log('Payment successful, redirecting to payment_success.php'); // Debug log
                        window.location.href = "payment_success.php?payment_id=" + response.razorpay_payment_id 
                            + "&booking_id=<?php echo $booking['request_id']; ?>"
                            + "&booking_type=<?php echo $booking_type; ?>";
                    } else {
                        console.log('Payment failed, redirecting to status page'); // Debug log
                        window.location.href = "status.php?error=payment_failed&message=Payment failed";
                    }
                },
                "prefill": {
                    "name": "<?php echo $_SESSION['username']; ?>",
                },
                "theme": {
                    "color": "#2E8B57"
                },
                "modal": {
                    "ondismiss": function() {
                        window.location.href = "status.php?error=payment_cancelled&message=Payment cancelled";
                    }
                }
            };
            var rzp1 = new Razorpay(options);
            rzp1.open();
            e.preventDefault();
        }
    </script>
</body>
</html>