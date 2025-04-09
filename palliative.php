<?php
session_start();
include 'connect.php';

// Create table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS tbl_palliative (
    palliativeid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT(6) UNSIGNED NOT NULL,
    comments TEXT,
    additional_requirements TEXT,
    address TEXT NOT NULL,
    medical_condition TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ambulance_type ENUM('palliative') NOT NULL DEFAULT 'palliative',
    driver_id INT(6) UNSIGNED DEFAULT NULL,
    FOREIGN KEY (userid) REFERENCES tbl_user(userid),
    FOREIGN KEY (driver_id) REFERENCES tbl_user(userid),
    INDEX idx_status (status),
    INDEX idx_userid (userid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($create_table_query)) {
    die("Error creating table: " . $conn->error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$query = "SELECT userid, username, phoneno, email FROM tbl_user WHERE userid = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error preparing query: " . $conn->error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $comments = !empty($_POST['comments']) ? $_POST['comments'] : null;
    $requirements = !empty($_POST['requirements']) ? $_POST['requirements'] : null;
    $address = $_POST['address'];
    $medical_condition = $_POST['medical_condition'];
    $ambulance_type = 'palliative'; // Default type

    $insert_query = "INSERT INTO tbl_palliative (
        userid, comments, additional_requirements, address, 
        medical_condition, status, ambulance_type
    ) VALUES (?, ?, ?, ?, ?, 'Pending', ?)";

    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isssss", 
        $user_id, $comments, $requirements, $address, 
        $medical_condition, $ambulance_type
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Palliative care request submitted successfully!";
    } else {
        $_SESSION['message'] = "Error submitting request. Please try again.";
    }
    $stmt->close();

    // Refresh page to display message
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Palliative Care Request - SwiftAid</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-image: url('assets/assets/img//template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            padding-top: 80px;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background: rgba(206, 205, 205, 0.51);
            color: white;
            padding: 20px;
            height: calc(100vh - 80px); /* Full height minus header height */
            position: fixed;
            top: 80px; /* Same as header height */
            left: 0;
            overflow-y: auto; /* Add scrollbar if content overflows */
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar ul li {
            margin: 15px 0;
        }

        .sidebar ul li a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
        }

        .sidebar ul li a:hover {
            color: #2E8B57;
        }

        .sidebar ul li a i {
            margin-right: 10px;
            font-size: 18px;
        }

        .container {
            margin-left: 250px; /* Adjust for sidebar width */
            padding: 20px;
            flex: 1;
        }

        .form-container {
            background-color: rgba(184, 180, 180, 0.46);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 20px rgba(5, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 800px;
        }

        .alert {
            margin-bottom: 20px;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px; /* Adjust spacing */
        }

        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .btn-back {
            background-color: rgb(50, 117, 69);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-back:hover {
            background-color: rgb(0, 179, 66);
        }

        .btn-submit {
            background-color: rgb(14, 192, 41); /* Breen color */
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background-color: #588F63; /* Slightly darker breen color on hover */
            transform: translateY(-2px);
        }
    </style>
        </head>
        <body>
            <?php include 'header.php'; ?>

            <!-- Sidebar -->
            <div class="sidebar">
            <div class="user-info"><a href="user1.php">
            <i class="fas fa-user-circle"></i>
            <?php echo $_SESSION['username']; ?></a>
        </div>
                <ul class="sidebar-nav">
                    <li><a href="user_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="status.php"><i class="fas fa-list"></i> My Bookings</a></li>
                    <li><a href="feedback.php"><i class="fas fa-comment"></i> Give Feedback</a></li>
                    <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>

    <!-- Main Content -->
                    <div class="container">
                        <div class="form-container">
                            <h1 style="color:white">Palliative Care Request</h1>
                            <form method="POST" action="" id="palliativeForm">
                            <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success text-center">
                        <?php 
                            echo $_SESSION['message']; 
                            unset($_SESSION['message']); // Remove message after displaying it
                        ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control readonly-field" 
                               value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" readonly>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control readonly-field" 
                               value="<?php echo htmlspecialchars($user_data['phoneno'] ?? ''); ?>" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="form-label">Additional Requirements</label>
                        <textarea name="requirements" id="requirements" class="form-control" 
                                  placeholder="Max 100 characters..."></textarea>
                        <small id="requirementsError" class="text-danger"></small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="form-label">Medical Condition <span style="color:red">*</span></label>
                        <textarea name="medical_condition" id="medical_condition" class="form-control" required 
                                  placeholder="Please describe the medical condition..."></textarea>
                        <small id="medicalConditionError" class="text-danger"></small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="form-label">Additional Comments</label>
                        <textarea name="comments" id="comments" class="form-control" 
                                  placeholder="Max 100 characters..."></textarea>
                        <small id="commentsError" class="text-danger"></small>
                    </div>

                    <div class="col-md-6 form-group">
                        <label class="form-label">Complete Address <span style="color:red">*</span></label>
                        <textarea name="address" id="address" class="form-control" required
                                  placeholder="Enter your complete address..."></textarea>
                        <small id="addressError" class="text-danger"></small>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" id="submitBtn" class="btn btn-submit" disabled>Submit Request</button>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="user1.php" class="btn-back">Back</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const medicalConditionField = document.getElementById("medical_condition");
            const addressField = document.getElementById("address");
            const requirementsField = document.getElementById("requirements");
            const commentsField = document.getElementById("comments");
            const submitBtn = document.getElementById("submitBtn");

            function validateField(field, errorField, minLength = 3, maxLength = null) {
                let value = field.value.trim();
                if (value.length < minLength) {
                    errorField.textContent = "This field is required.";
                    return false;
                } else if (maxLength && value.length > maxLength) {
                    errorField.textContent = `Max ${maxLength} characters allowed.`;
                    return false;
                } else {
                    errorField.textContent = "";
                    return true;
                }
            }

            function validateForm() {
                let medicalValid = validateField(medicalConditionField, document.getElementById("medicalConditionError"));
                let addressValid = validateField(addressField, document.getElementById("addressError"));
                let requirementsValid = validateField(requirementsField, document.getElementById("requirementsError"), 0, 100);
                let commentsValid = validateField(commentsField, document.getElementById("commentsError"), 0, 100);

                submitBtn.disabled = !(medicalValid && addressValid && requirementsValid && commentsValid);
            }

            medicalConditionField.addEventListener("input", validateForm);
            addressField.addEventListener("input", validateForm);
            requirementsField.addEventListener("input", validateForm);
            commentsField.addEventListener("input", validateForm);
        });
    </script>
</body>
</html>