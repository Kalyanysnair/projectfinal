<?php
// process_payment.php
session_start();

// Set error reporting for debugging (remove in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Initialize response array
$response = array(
    'success' => false,
    'message' => ''
);

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data and sanitize inputs
    $cardName = isset($_POST['cardName']) ? filter_var($_POST['cardName'], FILTER_SANITIZE_STRING) : '';
    $cardNumber = isset($_POST['cardNumber']) ? preg_replace('/\D/', '', $_POST['cardNumber']) : '';
    $expiry = isset($_POST['expiry']) ? filter_var($_POST['expiry'], FILTER_SANITIZE_STRING) : '';
    $cvv = isset($_POST['cvv']) ? filter_var($_POST['cvv'], FILTER_SANITIZE_STRING) : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    
    // Validate inputs
    $errors = array();
    
    // Validate cardholder name
    if (empty($cardName) || strlen($cardName) < 3) {
        $errors[] = 'Please enter a valid cardholder name';
    }
    
    // Validate card number with Luhn algorithm
    if (!validateCardNumber($cardNumber)) {
        $errors[] = 'Please enter a valid card number';
    }
    
    // Validate expiry date
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry)) {
        $errors[] = 'Please enter a valid expiry date (MM/YY)';
    } else {
        list($month, $year) = explode('/', $expiry);
        $year = '20' . $year;
        
        if (!checkExpiryDate($month, $year)) {
            $errors[] = 'The card has expired';
        }
    }
    
    // Validate CVV
    if (!preg_match('/^[0-9]{3}$/', $cvv)) {
        $errors[] = 'Please enter a valid CVV';
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
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
}

// Redirect to error page if there are issues
if (!$response['success']) {
    $_SESSION['error_message'] = $response['message'];
    header('Location: payment.php?error=1');
    exit;
}

// Helper functions
function validateCardNumber($number) {
    // Remove spaces and non-numeric characters
    $number = preg_replace('/\D/', '', $number);
    
    // Check length
    $length = strlen($number);
    if ($length < 13 || $length > 19) {
        return false;
    }
    
    // Luhn algorithm
    $sum = 0;
    $shouldDouble = false;
    
    for ($i = $length - 1; $i >= 0; $i--) {
        $digit = (int)$number[$i];
        
        if ($shouldDouble) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        
        $sum += $digit;
        $shouldDouble = !$shouldDouble;
    }
    
    return ($sum % 10) === 0;
}

function checkExpiryDate($month, $year) {
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');
    
    $year = (int)$year;
    $month = (int)$month;
    
    if ($year < $currentYear) {
        return false;
    }
    
    if ($year === $currentYear && $month < $currentMonth) {
        return false;
    }
    
    return true;
}
?>