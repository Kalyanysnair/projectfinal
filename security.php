<?php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php"); // Redirect to login page if not logged in
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$errorMessage = '';
$successMessage = '';

// Include database connection
include 'connect.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $existingPassword = $_POST['existingPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    
    try {
        $username = $_SESSION['username'];
        
        // Use prepared statements to prevent SQL injection
        $query = "SELECT * FROM tbl_user WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
        
            // Verify existing password
            if (!password_verify($existingPassword, $user['password'])) {
                $errorMessage = "Existing password is incorrect.";
            } elseif ($newPassword === $existingPassword) {
                $errorMessage = "New password cannot be the same as the existing password.";
            } elseif ($newPassword !== $confirmPassword) {
                $errorMessage = "New password and confirm password do not match.";
            } else {
                // Password validation
                $minLength = 8;
                $hasUppercase = preg_match('/[A-Z]/', $newPassword);
                $hasLowercase = preg_match('/[a-z]/', $newPassword);
                $hasNumber = preg_match('/[0-9]/', $newPassword);
                $hasSpecialChar = preg_match('/[!@#$%^&*]/', $newPassword);
                
                if (strlen($newPassword) < $minLength) {
                    $errorMessage = "Password must be at least $minLength characters long.";
                } elseif (!$hasUppercase) {
                    $errorMessage = "Password must contain at least one uppercase letter.";
                } elseif (!$hasLowercase) {
                    $errorMessage = "Password must contain at least one lowercase letter.";
                } elseif (!$hasNumber) {
                    $errorMessage = "Password must contain at least one number.";
                } elseif (!$hasSpecialChar) {
                    $errorMessage = "Password must contain at least one special character (!@#$%^&*).";
                } else {
                    // Password validation passed, update the password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $updateQuery = "UPDATE tbl_user SET password = ? WHERE username = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("ss", $hashedPassword, $username);
                    
                    if ($updateStmt->execute()) {
                        $successMessage = "Password updated successfully.";
                        // Clear the form values
                        $_POST = array();
                    } else {
                        $errorMessage = "Failed to update password. Please try again.";
                    }
                }
            }
        } else {
            $errorMessage = "User not found in database.";
        }
    } catch (Exception $e) {
        $errorMessage = "An error occurred. Please try again later.";
        // For development only, comment out for production:
        // $errorMessage .= ": " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security - Change Password</title>
    <link href="assets/css/main.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Bootstrap -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #28a745;
            --primary-hover: #218838;
            --secondary-color: #6c757d;
            --secondary-hover: #5a6268;
            --error-color: #dc3545;
            --success-color: #28a745;
            --background-overlay: rgba(255, 255, 255, 0.85);
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 60px;
        }

        .card-container {
            background: var(--background-overlay);
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--box-shadow);
            width: 450px;
            max-width: 95%;
            margin: 30px auto;
            margin-top: 90px;
            transition: all 0.3s ease;
        }

        .card-container:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            margin-bottom: 25px;
            text-align: center;
        }

        .card-header h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 0;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 42px;
            cursor: pointer;
            color: #6c757d;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background-color: var(--secondary-hover);
        }

        .message {
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
            margin-top: 15px;
            display: none;
        }

        .message.error {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(220, 53, 69, 0.2);
            display: block;
        }

        .message.success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(40, 167, 69, 0.2);
            display: block;
        }

        .password-strength {
            margin-top: 5px;
            height: 5px;
            border-radius: 3px;
            background-color: #e9ecef;
        }

        .password-strength-meter {
            height: 100%;
            border-radius: 3px;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }

        .password-requirements {
            margin-top: 8px;
            font-size: 13px;
            color: #6c757d;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
        }

        .requirement i {
            margin-right: 5px;
            font-size: 12px;
        }

        .requirement.valid i {
            color: var(--success-color);
        }

        .requirement.invalid i {
            color: var(--error-color);
        }

        @media (max-width: 576px) {
            .card-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="card-container">
        <div class="card-header">
            <h2><i class="fas fa-lock mr-2"></i> Change Password</h2>
            <p class="text-muted">Update your account password</p>
        </div>
        <form id="changePasswordForm" method="POST" novalidate>
            <div class="form-group">
                <label for="existingPassword">Current Password</label>
                <div class="password-field">
                    <input type="password" id="existingPassword" name="existingPassword" class="form-control" required>
                    <span class="password-toggle" onclick="togglePassword('existingPassword')">
                        <i class="far fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label for="newPassword">New Password</label>
                <div class="password-field">
                    <input type="password" id="newPassword" name="newPassword" class="form-control" required>
                    <span class="password-toggle" onclick="togglePassword('newPassword')">
                        <i class="far fa-eye"></i>
                    </span>
                </div>
                <div class="password-strength">
                    <div id="passwordStrengthMeter" class="password-strength-meter"></div>
                </div>
                <div class="password-requirements">
                    <div id="lengthReq" class="requirement">
                        <i class="fas fa-times-circle"></i> At least 8 characters
                    </div>
                    <div id="uppercaseReq" class="requirement">
                        <i class="fas fa-times-circle"></i> At least one uppercase letter
                    </div>
                    <div id="lowercaseReq" class="requirement">
                        <i class="fas fa-times-circle"></i> At least one lowercase letter
                    </div>
                    <div id="numberReq" class="requirement">
                        <i class="fas fa-times-circle"></i> At least one number
                    </div>
                    <div id="specialReq" class="requirement">
                        <i class="fas fa-times-circle"></i> At least one special character
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm New Password</label>
                <div class="password-field">
                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" required>
                    <span class="password-toggle" onclick="togglePassword('confirmPassword')">
                        <i class="far fa-eye"></i>
                    </span>
                </div>
                <div id="passwordMatch" class="requirement mt-2" style="display: none;">
                    <i class="fas fa-times-circle"></i> Passwords match
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-key mr-2"></i>Update Password
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </button>
        </form>
        <?php if (!empty($errorMessage)): ?>
        <div id="errorMessage" class="message error">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
        <div id="successMessage" class="message success">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Function to check password strength
        function checkPasswordStrength(password) {
            const meter = document.getElementById('passwordStrengthMeter');
            const lengthReq = document.getElementById('lengthReq');
            const uppercaseReq = document.getElementById('uppercaseReq');
            const lowercaseReq = document.getElementById('lowercaseReq');
            const numberReq = document.getElementById('numberReq');
            const specialReq = document.getElementById('specialReq');
            
            // Reset classes
            lengthReq.className = 'requirement';
            uppercaseReq.className = 'requirement';
            lowercaseReq.className = 'requirement';
            numberReq.className = 'requirement';
            specialReq.className = 'requirement';
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*]/.test(password);
            
            // Update classes based on validation
            if (hasLength) {
                lengthReq.classList.add('valid');
                lengthReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lengthReq.classList.add('invalid');
                lengthReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            if (hasUppercase) {
                uppercaseReq.classList.add('valid');
                uppercaseReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                uppercaseReq.classList.add('invalid');
                uppercaseReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            if (hasLowercase) {
                lowercaseReq.classList.add('valid');
                lowercaseReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lowercaseReq.classList.add('invalid');
                lowercaseReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            if (hasNumber) {
                numberReq.classList.add('valid');
                numberReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                numberReq.classList.add('invalid');
                numberReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            if (hasSpecial) {
                specialReq.classList.add('valid');
                specialReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                specialReq.classList.add('invalid');
                specialReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            // Calculate strength percentage
            let strength = 0;
            if (hasLength) strength += 20;
            if (hasUppercase) strength += 20;
            if (hasLowercase) strength += 20;
            if (hasNumber) strength += 20;
            if (hasSpecial) strength += 20;
            
            // Update meter
            meter.style.width = strength + '%';
            
            // Change color based on strength
            if (strength <= 40) {
                meter.style.backgroundColor = '#dc3545'; // Red - Weak
            } else if (strength <= 80) {
                meter.style.backgroundColor = '#ffc107'; // Yellow - Medium
            } else {
                meter.style.backgroundColor = '#28a745'; // Green - Strong
            }
            
            return strength;
        }

        // Function to check if passwords match
        function checkPasswordMatch() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchElement = document.getElementById('passwordMatch');
            
            if (confirmPassword) {
                matchElement.style.display = 'flex';
                
                if (newPassword === confirmPassword) {
                    matchElement.classList.add('valid');
                    matchElement.classList.remove('invalid');
                    matchElement.querySelector('i').className = 'fas fa-check-circle';
                    matchElement.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                } else {
                    matchElement.classList.add('invalid');
                    matchElement.classList.remove('valid');
                    matchElement.querySelector('i').className = 'fas fa-times-circle';
                    matchElement.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
                }
            } else {
                matchElement.style.display = 'none';
            }
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('newPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const form = document.getElementById('changePasswordForm');
            
            newPasswordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                if (confirmPasswordInput.value) {
                    checkPasswordMatch();
                }
            });
            
            confirmPasswordInput.addEventListener('input', function() {
                checkPasswordMatch();
            });
            
            form.addEventListener('submit', function(event) {
                const existingPassword = document.getElementById('existingPassword').value;
                const newPassword = document.getElementById('newPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                
                // Front-end validation
                if (!existingPassword || !newPassword || !confirmPassword) {
                    event.preventDefault();
                    alert('All fields are required');
                    return;
                }
                
                // Check password strength
                const strength = checkPasswordStrength(newPassword);
                if (strength < 100) {
                    // If the password doesn't meet all requirements
                    event.preventDefault();
                    alert('Please ensure your new password meets all the requirements');
                    return;
                }
                
                // Check if passwords match
                if (newPassword !== confirmPassword) {
                    event.preventDefault();
                    alert('New password and confirm password do not match');
                    return;
                }
                
                // If we made it here, the form will submit
            });
            
            // If there are success messages, set a timeout to hide them
            const successMessage = document.getElementById('successMessage');
            if (successMessage && successMessage.style.display !== 'none') {
                setTimeout(function() {
                    successMessage.style.opacity = '0';
                    setTimeout(function() {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>