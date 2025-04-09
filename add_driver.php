<?php
session_start();
include 'connect.php';
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure only admin can add drivers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$success = $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $license_no = trim($_POST['license_number']);
    $service_area = trim($_POST['service_area']);
    $dname = trim($_POST['dname']);
    $vehicle_no = trim($_POST['vehicle_no']);
    $ambulance_type = trim($_POST['ambulance_type']);
    $status = trim($_POST['status']);
    
    if (empty($contact) || empty($email) || empty($license_no) || empty($service_area) || empty($dname) || empty($vehicle_no) || empty($ambulance_type) || empty($status)) {
        $error = "All fields are required.";
    } else {
        // **Check if License Number or Vehicle Number Already Exists**
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_driver WHERE lisenceno = ? OR vehicle_no = ?");
        $checkStmt->bind_param("ss", $license_no, $vehicle_no);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        if ($count > 0) {
            $error = "License number or vehicle number is already registered!";
        } else {
            // **Proceed with Registration if No Duplicates Found**
            $conn->begin_transaction();

            try {
                // Auto-generate a password
                $auto_password = bin2hex(random_bytes(4)); // Generates an 8-character random password
                $hashed_password = password_hash($auto_password, PASSWORD_DEFAULT);

                // Insert into tbl_user
                $stmt = $conn->prepare("INSERT INTO tbl_user (username, password, email, phoneno, role, status, created_at) VALUES (?, ?, ?, ?, 'driver', 'active', NOW())");
                $stmt->bind_param("ssss", $dname, $hashed_password, $email, $contact);

                if (!$stmt->execute()) {
                    throw new Exception("Error adding driver to tbl_user: " . $stmt->error);
                }

                // Get the last inserted userid
                $userid = $conn->insert_id;

                // Insert into tbl_driver
                $stmt = $conn->prepare("INSERT INTO tbl_driver (userid, lisenceno, service_area, vehicle_no, ambulance_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("issss", $userid, $license_no, $service_area, $vehicle_no, $ambulance_type);

                if (!$stmt->execute()) {
                    throw new Exception("Error adding driver to tbl_driver: " . $stmt->error);
                }

                // Send email to the driver
                try {
                    $mail = new PHPMailer(true);
                    
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username = 'kalyanys2004@gmail.com'; // Your Gmail ID
                    $mail->Password = 'ooqs zxti mult tlcb'; // Use App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                
                    // Recipients
                    $mail->setFrom('your-email@gmail.com', 'SWIFTAID');
                    $mail->addAddress($email, $dname);
                
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = "Your SWIFTAID Account Credentials";
                    
                    // Email body
                    $htmlBody = "
                    <html>
                    <body>
                        <h2>Welcome to SWIFTAID!</h2>
                        <p>Your login credentials are:</p>
                        <p><strong>Username:</strong> {$dname}</p>
                        <p><strong>Password:</strong> {$auto_password}</p>
                        <p style='color: red;'><strong>Important:</strong> Please change your password after logging in.</p>
                        <p>Thank you for joining our team!</p>
                    </body>
                    </html>";
                    
                    $mail->Body    = $htmlBody;
                    $mail->AltBody = "Welcome to SWIFTAID!\n\nUsername: {$dname}\nPassword: {$auto_password}\n\nPlease change your password after logging in.";
                
                    $mail->send();
                    $success = "Driver added successfully! An email with login credentials has been sent.";
                } catch (Exception $e) {
                    throw new Exception("Failed to send email: " . $mail->ErrorInfo);
                }

                // Commit transaction
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Ambulance Driver</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: url('assets/assets/img/template/Groovin/hero-carousel/road.jpg') no-repeat center center fixed;
            background-size: cover;
        }
        .navbar {
            background: rgb(53, 56, 58);
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
        }
        .container {
            max-width: 720px;
            background: rgba(216, 213, 213, 0.85);
            padding: 40px;
            margin: 40px auto;
            border-radius: 10px;
            box-shadow: 2px 2px 15px rgba(0, 0, 0, 0.2);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: rgba(62, 195, 25, 0.85);
        }
       
        input, select {
            width: 90%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .input-icon {
            position: absolute;
            top: 38px;
            left: 10px;
            font-size: 18px;
            color: gray;
        }
        button {
            width: 100%;
            padding: 12px;
            background: rgb(36, 204, 33);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background: rgb(34, 170, 86);
        }
        .success {
            color: green;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .error {
            color: red;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
        }
       
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        
        .form-row {
            display: flex;
            gap: 30px;
            flex-wrap: nowrap; 
        }
        .form-row .form-group {
            flex: 1;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .error-message {
            color: red;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        input.invalid, select.invalid {
            border: 1px solid red;
        }
        
        input.valid, select.valid {
            border: 1px solid green;
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
    <h2>Add Ambulance Driver</h2>
    <a href="admin.php" class="back-to-dashboard-btn" style="text-decoration: none; color: white; background-color:rgb(33, 208, 92); padding: 10px 20px; border-radius: 5px; font-size: 16px;">
            Back
        </a>
    <?php if ($success) echo "<p class='success'>$success</p>"; ?>
    <?php if ($error) echo "<p class='error'>$error</p>"; ?>

    <div class="form-container">
        <form method="POST" action="" id="driverForm" novalidate>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="dname">Driver Name</label>
                    <input type="text" id="dname" name="dname" required>
                    <span class="error-message" id="dname-error">Please enter a valid name (only letters and spaces)</span>
                </div>
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="text" id="contact" name="contact" required>
                    <span class="error-message" id="contact-error">Please enter a valid 10-digit phone number</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                    <span class="error-message" id="email-error">Please enter a valid email address</span>
                </div>
                <div class="form-group">
                    <label for="license_number">License Number</label>
                    <input type="text" id="license_number" name="license_number" required>
                    <span class="error-message" id="license-error">Please enter a valid license number</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="service_area">Service Area</label>
                    <input type="text" id="service_area" name="service_area" required>
                    <span class="error-message" id="service-error">Service area cannot be empty</span>
                </div>
                <div class="form-group">
                    <label for="vehicle_no">Ambulance Vehicle Number</label>
                    <input type="text" id="vehicle_no" name="vehicle_no" required>
                    <span class="error-message" id="vehicle-error">Please enter a valid vehicle number</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="ambulance_type">Ambulance Type</label>
                    <select id="ambulance_type" name="ambulance_type" required>
                        <option value="">Select Ambulance Type</option>
                        <option value="Basic">Basic Ambulance Service</option>
                        <option value="Advanced">Advanced Life Support</option>
                        <option value="Critical">Critical Care Ambulance</option>
                        <option value="Neonatal">Neonatal Ambulance</option>
                        <option value="Bariatric">Bariatric Ambulance</option> 
                        <option value="Palliative">Palliative Care</option>
                        <option value="Mortuary">Mortuary Ambulance</option> 
                    </select>
                    <span class="error-message" id="ambulance-error">Please select an ambulance type</span>
                </div>
                <div class="form-group">
                    <label for="status">Availability Status</label>
                    <select id="status" name="status" required>
                        <option value="">Select Status</option>
                        <option value="Available">Available</option>
                        <option value="Unavailable">Unavailable</option>
                    </select>
                    <span class="error-message" id="status-error">Please select a status</span>
                </div>
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <input type="text" id="role" name="role" value="Driver" readonly>
            </div>

            <button type="submit" id="submitBtn"><i class="fa fa-plus"></i> Add Driver</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('driverForm');
    const inputs = form.querySelectorAll('input[required], select[required]');
    
    // Validation patterns
    const patterns = {
        dname: /^[A-Za-z\s]{3,50}$/,
        contact: /^[6-9]\d{9}$/,
        email: /^[a-zA-Z0-9._-]+@((gmail\.com|yahoo\.com)|([a-zA-Z0-9-]+\.[a-zA-Z0-9-]+\.[a-zA-Z]{2,}))$/,
        license_number: /^KL-\d{2} \d{11}$/
,
        vehicle_no: /^[A-Z]{2}-\d{1,2}-[A-Z]{1,2}-\d{4}$/


    };
    
    // Error messages
    const errorMessages = {
        dname: "Please enter a valid name (only letters and spaces, min 3 characters)",
        contact: "Please enter a valid 10-digit phone number and the first number should be between 6-9",
        email: "Please enter a valid email address",
        license_number: "Please enter a valid license number (eg. KL-12 12345678901)",
        service_area: "Service area cannot be empty",
        vehicle_no: "Please enter a valid vehicle number ",
        ambulance_type: "Please select an ambulance type",
        status: "Please select a status"
    };
    
    // Live validation for each input field
    inputs.forEach(input => {
        input.addEventListener('input', validateInput);
        input.addEventListener('blur', validateInput);
    });
    
    // Validate form on submit
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        inputs.forEach(input => {
            if (!validateFieldOnSubmit(input)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
    
    function validateInput(e) {
        const field = e.target;
        validateField(field);
    }
    
    function validateField(field) {
        const fieldName = field.id;
        const fieldValue = field.value.trim();
        let errorElement = document.getElementById(`${fieldName.replace('_', '-')}-error`);
        
        // Create error element if it doesn't exist
        if (!errorElement) {
            errorElement = document.createElement('span');
            errorElement.className = 'error-message';
            errorElement.id = `${fieldName.replace('_', '-')}-error`;
            field.parentNode.appendChild(errorElement);
        }
        
        // Validation for select fields
        if (field.tagName === 'SELECT') {
            if (fieldValue === '') {
                field.classList.add('invalid');
                field.classList.remove('valid');
                errorElement.textContent = errorMessages[fieldName];
                errorElement.style.display = 'block';
                return false;
            } else {
                field.classList.remove('invalid');
                field.classList.add('valid');
                errorElement.style.display = 'none';
                return true;
            }
        }
        
        // Validation for text input fields
        if (fieldName === 'service_area') {
            if (fieldValue === '') {
                field.classList.add('invalid');
                field.classList.remove('valid');
                errorElement.textContent = errorMessages[fieldName];
                errorElement.style.display = 'block';
                return false;
            } else {
                field.classList.remove('invalid');
                field.classList.add('valid');
                errorElement.style.display = 'none';
                return true;
            }
        }
        
        // Pattern validation for other fields
        if (patterns[fieldName]) {
            if (!patterns[fieldName].test(fieldValue)) {
                field.classList.add('invalid');
                field.classList.remove('valid');
                errorElement.textContent = errorMessages[fieldName];
                errorElement.style.display = 'block';
                return false;
            } else {
                field.classList.remove('invalid');
                field.classList.add('valid');
                errorElement.style.display = 'none';
                return true;
            }
        }
        
        return true;
    }
    
    function validateFieldOnSubmit(field) {
        return validateField(field);
    }
});
</script>

</body>
</html>