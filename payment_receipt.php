<?php
// payment_receipt.php
session_start();
require 'connect.php';

// Debug logging
error_log("Payment Receipt: Processing started");

// Initialize variables with default values to prevent undefined variable errors
$razorpay_payment_id = '';
$userid = 0;
$payment = null;
$booking = null;
$formatted_date = '';
$formatted_booking_date = '';
$receipt_number = '';

// Check if required session and GET parameters exist
if (!isset($_SESSION['user_id']) || !isset($_GET['payment_id'])) {
    error_log("Payment Receipt: Missing required parameters");
    $_SESSION['error_message'] = "Invalid request - Missing parameters";
    // Instead of die(), store error and redirect to a friendly error page
    header("Location: status.php?error=missing_parameters");
    exit();
} else {
    $razorpay_payment_id = $_GET['payment_id'];
    $userid = $_SESSION['user_id'];

    try {
        // Get payment details with user information
        $payment_query = "SELECT p.*, 
                                 u.username, u.email, u.phoneno,
                                 CASE 
                                    WHEN p.request_type = 'emergency' THEN e.pickup_location
                                    WHEN p.request_type = 'prebooking' THEN pr.pickup_location
                                    WHEN p.request_type = 'palliative' THEN pa.address
                                 END as location,
                                 CASE 
                                    WHEN p.request_type = 'emergency' THEN e.ambulance_type
                                    WHEN p.request_type = 'prebooking' THEN pr.ambulance_type
                                    WHEN p.request_type = 'palliative' THEN 'Palliative Care'
                                 END as ambulance_type,
                                 CASE 
                                    WHEN p.request_type = 'emergency' THEN e.created_at
                                    WHEN p.request_type = 'prebooking' THEN pr.created_at
                                    WHEN p.request_type = 'palliative' THEN pa.created_at
                                 END as booking_date,
                                 CASE 
                                    WHEN p.request_type = 'emergency' THEN d1.username
                                    WHEN p.request_type = 'prebooking' THEN d2.username
                                    WHEN p.request_type = 'palliative' THEN d3.username
                                 END as driver_name
                          FROM tbl_payments p
                          JOIN tbl_user u ON p.userid = u.userid
                          LEFT JOIN tbl_emergency e ON p.request_type = 'emergency' AND p.request_id = e.request_id
                          LEFT JOIN tbl_prebooking pr ON p.request_type = 'prebooking' AND p.request_id = pr.prebookingid
                          LEFT JOIN tbl_palliative pa ON p.request_type = 'palliative' AND p.request_id = pa.palliativeid
                          LEFT JOIN tbl_user d1 ON e.driver_id = d1.userid
                          LEFT JOIN tbl_user d2 ON pr.driver_id = d2.userid
                          LEFT JOIN tbl_user d3 ON pa.driver_id = d3.userid
                          WHERE p.razorpay_payment_id = ? AND p.userid = ?";
        
        error_log("Payment Receipt: Query: " . $payment_query);
        error_log("Payment Receipt: Parameters - payment_id: $razorpay_payment_id, userid: $userid");
        
        $stmt = $conn->prepare($payment_query);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("si", $razorpay_payment_id, $userid);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        if (!$payment) {
            // Try without userid constraint in case there's a session mismatch
            $fallback_query = "SELECT p.*, 
                                u.username, u.email, u.phoneno,
                                CASE 
                                   WHEN p.request_type = 'emergency' THEN e.pickup_location
                                   WHEN p.request_type = 'prebooking' THEN pr.pickup_location
                                   WHEN p.request_type = 'palliative' THEN pa.address
                                END as location,
                                CASE 
                                   WHEN p.request_type = 'emergency' THEN e.ambulance_type
                                   WHEN p.request_type = 'prebooking' THEN pr.ambulance_type
                                   WHEN p.request_type = 'palliative' THEN 'Palliative Care'
                                END as ambulance_type,
                                CASE 
                                   WHEN p.request_type = 'emergency' THEN e.created_at
                                   WHEN p.request_type = 'prebooking' THEN pr.created_at
                                   WHEN p.request_type = 'palliative' THEN pa.created_at
                                END as booking_date,
                                CASE 
                                   WHEN p.request_type = 'emergency' THEN d1.username
                                   WHEN p.request_type = 'prebooking' THEN d2.username
                                   WHEN p.request_type = 'palliative' THEN d3.username
                                END as driver_name
                         FROM tbl_payments p
                         JOIN tbl_user u ON p.userid = u.userid
                         LEFT JOIN tbl_emergency e ON p.request_type = 'emergency' AND p.request_id = e.request_id
                         LEFT JOIN tbl_prebooking pr ON p.request_type = 'prebooking' AND p.request_id = pr.prebookingid
                         LEFT JOIN tbl_palliative pa ON p.request_type = 'palliative' AND p.request_id = pa.palliativeid
                         LEFT JOIN tbl_user d1 ON e.driver_id = d1.userid
                         LEFT JOIN tbl_user d2 ON pr.driver_id = d2.userid
                         LEFT JOIN tbl_user d3 ON pa.driver_id = d3.userid
                         WHERE p.razorpay_payment_id = ?";
            
            $fallback_stmt = $conn->prepare($fallback_query);
            if (!$fallback_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $fallback_stmt->bind_param("s", $razorpay_payment_id);
            $fallback_stmt->execute();
            $fallback_result = $fallback_stmt->get_result();
            $payment = $fallback_result->fetch_assoc();
            
            if (!$payment) {
                error_log("Payment Receipt: No payment found for payment_id: $razorpay_payment_id");
                throw new Exception("Could not retrieve payment details: Payment not found");
            }
        }
        
        error_log("Payment Receipt: Payment details retrieved: " . print_r($payment, true));
        
        // Format dates
        if (isset($payment['payment_date'])) {
            $payment_date = new DateTime($payment['payment_date']);
            $formatted_date = $payment_date->format('d M Y, h:i A');
        }
        
        if (isset($payment['booking_date'])) {
            $booking_date = new DateTime($payment['booking_date']);
            $formatted_booking_date = $booking_date->format('d M Y, h:i A');
        }
        
        // Generate receipt number
        if (isset($payment['payment_id']) && isset($payment['request_type'])) {
            $receipt_number = 'SWIFTAID-' . strtoupper(substr($payment['request_type'], 0, 3)) . '-' . 
                            str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {
        error_log("Payment Receipt Error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: status.php?error=payment_failed&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// Function to safely get array value
function getValue($array, $key) {
    return (isset($array) && is_array($array) && isset($array[$key])) ? $array[$key] : '';
}

// Function to safely format currency
function formatCurrency($amount) {
    return is_numeric($amount) ? number_format($amount, 2) : '0.00';
}

// Function to safely get service type name
function getServiceTypeName($type) {
    if (!$type) return 'Unknown';
    
    switch($type) {
        case 'emergency':
            return "Emergency Ambulance Service";
        case 'prebooking':
            return "Pre-booked Ambulance Service";
        case 'palliative':
            return "Palliative Care Service";
        default:
            return "Unknown Service";
    }
}

// Continue with the rest of your receipt display code here...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - SwiftAid Ambulance Service</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5; 
            font-family: 'Arial', sans-serif;
            color: #333;
            
        }
        .receipt-container {
            max-width: 800px;
            margin: 40px auto;
            background-color: #fff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }
        .receipt-header {
            background-color:rgb(15, 141, 6);
            color: white;
            padding: 25px 30px;
            position: relative;
            border-bottom: 4px solidrgb(41, 185, 65);
        }
        .receipt-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            padding-left: 70px;
        }
        .receipt-logo {
            position: absolute;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            height: 40px;
            width: auto;
        }
        .receipt-subtitle {
            margin-top: 5px;
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            padding-left: 70px;
        }
        .receipt-id {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            text-align: right;
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
        }
        .receipt-body {
            padding: 40px 30px 20px;
        }
        .receipt-section {
            margin-bottom: 30px;
        }
        .receipt-section-title {
            font-size: 18px;
            color:rgb(55, 136, 26);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .receipt-row {
            display: flex;
            margin-bottom: 12px;
        }
        .receipt-label {
            font-weight: 600;
            width: 180px;
            color: #555;
        }
        .receipt-value {
            flex-grow: 1;
        }
        .receipt-total {
            background-color: #f8f9fa;
            padding: 20px 30px;
            font-size: 20px;
            font-weight: 600;
            text-align: right;
            border-top: 2px solid #e0e0e0;
            color:rgb(26, 118, 29);
        }
        .payment-success {
            color: #27ae60;
            font-weight: 600;
        }
        .receipt-footer {
            padding: 25px 30px;
            text-align: center;
            font-size: 14px;
            color: #777;
            border-top: 1px solid #e0e0e0;
            background-color: #fafafa;
        }
        .receipt-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }
        .action-button {
            padding: 12px 25px;
            background-color:rgb(15, 184, 66);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .action-button:hover {
            background-color:rgb(41, 185, 99);
            color: white;
            text-decoration: none;
        }
        .action-button i {
            font-size: 16px;
        }
        .payment-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 15px;
            border-radius: 20px;
            background-color: #d4edda;
            color: #155724;
            font-size: 15px;
            font-weight: 600;
        }
        .back-button {
            margin-top: 20px;
            margin-bottom: 0;
            background-color: #6c757d;
            border-color: #6c757d;
            transition: all 0.3s ease;
        }
        .back-button:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .service-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solidrgb(26, 118, 51);
        }
        .service-details strong {
            color: #1a5276;
            min-width: 140px;
            display: inline-block;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            border-left: 4px solid #721c24;
        }
        @media print {
            .receipt-actions, .back-button, .error-message {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
                border: none;
            }
            body {
                background-color: white;
            }
        }
    </style>
</head>
<body>

    
    <div class="receipt-container" id="receipt">
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $_SESSION['error_message']; ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <div class="receipt-header">
            <img src="assets/img/SWIFTAID2.png" alt="SwiftAid Logo" class="receipt-logo">
            <h1>SwiftAid</h1>
            <p class="receipt-subtitle">Ambulance Service Payment Receipt</p>
            <div class="receipt-id">
                Receipt No: <?php echo htmlspecialchars($receipt_number ?: 'N/A'); ?><br>
                Date: <?php echo htmlspecialchars($formatted_date ?: date('d M Y, h:i A')); ?>
            </div>
        </div>
        
        <div class="receipt-body">
            <div class="receipt-section">
                <div class="receipt-section-title">Payment Information</div>
                <div class="receipt-row">
                    <div class="receipt-label">Status:</div>
                    <div class="receipt-value">
                        <span class="payment-status-badge">
                            <i class="fas fa-<?php echo $payment ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo $payment ? 'Payment Successful' : 'Payment Status Unknown'; ?>
                        </span>
                    </div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Transaction ID:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($razorpay_payment_id ?: 'N/A'); ?></div>
                </div>
            </div>
            
            <div class="receipt-section">
                <div class="receipt-section-title">Customer Information</div>
                <div class="receipt-row">
                    <div class="receipt-label">Name:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars(getValue($payment, 'username') ?: 'N/A'); ?></div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Email:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars(getValue($payment, 'email') ?: 'N/A'); ?></div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Phone Number:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars(getValue($payment, 'phoneno') ?: 'N/A'); ?></div>
                </div>
            </div>
            
            <div class="receipt-section">
                <div class="receipt-section-title">Service Details</div>
                <div class="service-details">
                    <div class="receipt-row">
                        <div class="receipt-label">Service Type:</div>
                        <div class="receipt-value">
                            <strong>
                            <?php echo htmlspecialchars(getServiceTypeName(getValue($payment, 'request_type'))); ?>
                            </strong>
                        </div>
                    </div>
                    
                    <div class="receipt-row">
                        <div class="receipt-label">Driver:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars(getValue($payment, 'driver_name') ?: 'N/A'); ?></div>
                    </div>
                    
                 
                    
                    <div class="receipt-row">
                        <div class="receipt-label">Booking ID:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars(getValue($payment, 'request_id') ?: 'N/A'); ?></div>
                    </div>
                    
                    <div class="receipt-row">
                        <div class="receipt-label">Booking Date:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($formatted_booking_date ?: 'N/A'); ?></div>
                    </div>
                    
                    <?php if(isset($payment['ambulance_type']) && $payment['ambulance_type']): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Ambulance Type:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($payment['ambulance_type']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($payment['location']) && $payment['location']): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Pickup Location:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($payment['location']); ?></div>
                    </div>
                    <?php elseif(isset($payment['address']) && $payment['address']): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Address:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($payment['address']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($payment['destination']) && $payment['destination']): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Destination:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($payment['destination']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($payment['service_time']) && $payment['service_time']): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Service Time:</div>
                        <div class="receipt-value"><?php echo date('d M Y, h:i A', strtotime($payment['service_time'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($payment['patient_name']) && $payment['patient_name']): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Patient Name:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($payment['patient_name']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($payment['medical_condition']) && $payment['medical_condition']): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Medical Condition:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($payment['medical_condition']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="receipt-total">
            <div>TOTAL AMOUNT PAID: <span style="font-size: 22px;">₹<?php echo formatCurrency(getValue($payment, 'amount')); ?></span></div>
        </div>
        
        <div class="receipt-footer">
            <p><strong>SwiftAid Ambulance Service</strong></p>
            <p>This is an electronically generated receipt and does not require a physical signature.</p>
            <p>For any queries regarding this transaction, please contact our support team.</p>
            
            <div class="receipt-actions">
                <button class="action-button" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button class="action-button" onclick="downloadPDF()">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <button class="action-button" onclick="window.location.href='status.php'">
                    <i class="fas fa-chevron-left"></i> Back to Status
                    
                </button>

            </div>
        </div>
        
       

    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function downloadPDF() {
            // Show loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.style.position = 'fixed';
            loadingDiv.style.top = '0';
            loadingDiv.style.left = '0';
            loadingDiv.style.width = '100%';
            loadingDiv.style.height = '100%';
            loadingDiv.style.backgroundColor = 'rgba(0,0,0,0.7)';
            loadingDiv.style.display = 'flex';
            loadingDiv.style.justifyContent = 'center';
            loadingDiv.style.alignItems = 'center';
            loadingDiv.style.zIndex = '9999';
            loadingDiv.innerHTML = '<div style="background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.2);"><h3 style="margin:0; color:#1a5276;"><i class="fas fa-spinner fa-spin" style="margin-right: 10px;"></i> Generating PDF...</h3></div>';
            document.body.appendChild(loadingDiv);
            
            // Remove action buttons temporarily for PDF generation
            const actionButtons = document.querySelector('.receipt-actions');
            const backButton = document.querySelector('.back-button');
            const errorMessage = document.querySelector('.error-message');
            
            if (actionButtons) actionButtons.style.display = 'none';
            if (backButton) backButton.style.display = 'none';
            if (errorMessage) errorMessage.style.display = 'none';
            
            // Get the receipt container
            const receipt = document.getElementById('receipt');
            
            // Use html2canvas to capture the receipt as an image
            html2canvas(receipt, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                logging: false
            }).then(canvas => {
                // Initialize jsPDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                // Calculate dimensions
                const imgData = canvas.toDataURL('image/png');
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
                
                // Add image to PDF
                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                
                // Save the PDF
                const receiptNumber = '<?php echo htmlspecialchars($receipt_number ?: "receipt"); ?>';
                pdf.save('SwiftAid_' + receiptNumber + '.pdf');
                
                // Restore elements
                if (actionButtons) actionButtons.style.display = 'flex';
                if (backButton) backButton.style.display = 'block';
                if (errorMessage) errorMessage.style.display = 'block';
                
                // Remove loading indicator
                document.body.removeChild(loadingDiv);
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('There was an error generating the PDF. Please try again.');
                
                // Restore elements
                if (actionButtons) actionButtons.style.display = 'flex';
                if (backButton) backButton.style.display = 'block';
                if (errorMessage) errorMessage.style.display = 'block';
                
                // Remove loading indicator
                document.body.removeChild(loadingDiv);
            });
        }
    </script>
</body>
</html>