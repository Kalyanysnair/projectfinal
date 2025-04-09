<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'connect.php';

$successMessage = '';
$errorMessage = '';

// Handle leave request status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $leave_id = $_POST['leave_id'];
        $status = $_POST['status'];
        
        $updateQuery = "UPDATE tbl_driver_leave SET status = ? WHERE leave_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("si", $status, $leave_id);
        
        if ($stmt->execute()) {
            $successMessage = "Leave request status updated successfully!";
        } else {
            throw new Exception("Failed to update status");
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Fetch all leave requests with driver details
$query = "SELECT dl.*, u.username, u.phoneno, u.email, d.ambulance_type 
          FROM tbl_driver_leave dl
          JOIN tbl_user u ON dl.driver_id = u.userid
          JOIN tbl_driver d ON u.userid = d.userid
          ORDER BY dl.created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Leave Requests - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            padding: 20px;
        }

        .content-wrapper {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 50px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            position: relative;
            margin-top: 80px;
        }

        .back-button {
            position: absolute;
            top: 20px;
            right: 20px;
            text-decoration: none;
            color: #333;
            font-size: 24px;
            transition: color 0.3s;
        }

        .back-button:hover {
            color: #007bff;
        }

        .leave-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }

        .leave-card:hover {
            transform: translateY(-2px);
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-Pending {
            background-color: #ffc107;
            color: #000;
        }

        .status-Approved {
            background-color: #28a745;
            color: white;
        }

        .status-Rejected {
            background-color: #dc3545;
            color: white;
        }

        .driver-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .driver-avatar {
            width: 50px;
            height: 50px;
            background: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .status-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .status-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .approve-btn {
            background-color: #28a745;
            color: white;
        }

        .reject-btn {
            background-color: #dc3545;
            color: white;
        }

        .status-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .page1 {
            margin-bottom: 30px;
            color: #333;
            text-align: center;
            padding-right: 50px;
        }

        .no-requests {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    

    <div class="container">
        <div class="content-wrapper">
            <a href="admin.php" class="back-button">
                <i class="fas fa-times"></i>
            </a>

            <h2 class="page1">
                <i class="fas fa-calendar-alt me-2"></i>
                Driver Leave Requests
            </h2>

            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="leave-card">
                        <div class="driver-info">
                            <div class="driver-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($row['username']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($row['ambulance_type']); ?> Driver</small>
                            </div>
                            <span class="ms-auto status-badge status-<?php echo $row['status']; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </div>

                        <div class="leave-details">
                            <p><strong>Leave Date:</strong> <?php echo date('F d, Y', strtotime($row['leave_date'])); ?></p>
                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($row['reason']); ?></p>
                            <p><strong>Contact:</strong> 
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($row['phoneno']); ?> | 
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($row['email']); ?>
                            </p>
                            <p><strong>Requested on:</strong> <?php echo date('F d, Y H:i A', strtotime($row['created_at'])); ?></p>
                        </div>

                        <?php if ($row['status'] === 'Pending'): ?>
                            <div class="status-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="leave_id" value="<?php echo $row['leave_id']; ?>">
                                    <input type="hidden" name="status" value="Approved">
                                    <button type="submit" name="update_status" class="status-btn approve-btn">
                                        <i class="fas fa-check me-2"></i>Approve
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="leave_id" value="<?php echo $row['leave_id']; ?>">
                                    <input type="hidden" name="status" value="Rejected">
                                    <button type="submit" name="update_status" class="status-btn reject-btn">
                                        <i class="fas fa-times me-2"></i>Reject
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-requests">
                    <i class="fas fa-calendar-times fa-3x mb-3"></i>
                    <h4>No Leave Requests</h4>
                    <p>There are currently no leave requests from drivers.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add confirmation for approve/reject actions
        document.querySelectorAll('form').forEach(form => {
            form.onsubmit = function(e) {
                const action = this.querySelector('input[name="status"]').value.toLowerCase();
                return confirm(`Are you sure you want to ${action} this leave request?`);
            };
        });
    </script>
</body>
</html> 