<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

include 'connect.php';

// Ensure the 'status' column exists in tbl_user
$check_column = "SHOW COLUMNS FROM tbl_user LIKE 'status'";
$result = $conn->query($check_column);
if ($result->num_rows === 0) {
    $add_column = "ALTER TABLE tbl_user ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'";
    $conn->query($add_column);
}

// User status update handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_status'])) {
    $userid = filter_input(INPUT_POST, 'userid', FILTER_VALIDATE_INT);
    $current_status = filter_input(INPUT_POST, 'current_status', FILTER_SANITIZE_STRING);
    
    if ($userid) {
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';
        $sql = "UPDATE tbl_user SET status = ? WHERE userid = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            die("Error preparing statement: " . $conn->error);
        }

        $stmt->bind_param("si", $new_status, $userid);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'User status updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update user status: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = 'Invalid user ID provided.';
    }
}

// Fetch only users with role 'user' along with their request details
$sql = "SELECT 
            u.userid, 
            u.username, 
            u.email, 
            u.phoneno, 
            u.role, 
            u.status,
            (SELECT COUNT(*) FROM tbl_emergency e WHERE e.userid = u.userid) as emergency_requests,
            (SELECT COUNT(*) FROM tbl_prebooking p WHERE p.userid = u.userid) as prebooking_requests,
            (SELECT COUNT(*) FROM tbl_palliative pl WHERE pl.userid = u.userid) as palliative_requests
        FROM tbl_user u
        WHERE u.role = 'user'";

$result = $conn->query($sql);
$total_users = $result ? $result->num_rows : 0;

// Handle query errors
if (!$result) {
    $_SESSION['error'] = 'Error fetching users: ' . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftAid Admin Dashboard - User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
  --primary:rgb(31, 131, 0);
  --secondary: #f8f9fa;
  --danger: #dc3545;
  --success: #28a745;
  --warning: #ffc107;
  --info: #17a2b8;
  --light: #f8f9fa;
  --dark: #343a40;
  --shadow: 0 4px 12px rgba(0,0,0,0.1);
}

body {
  background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), 
              url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg') no-repeat center center/cover;
  min-height: 100vh;
  padding: 20px;
  font-family: 'Arial', sans-serif;
}

.user-management-card {
  background: rgba(255,255,255,0.97);
  border-radius: 12px;
  padding: 30px;
  box-shadow: var(--shadow);
  margin: 30px auto;
  max-width: 1200px;
  position: relative;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  padding-bottom: 15px;
  border-bottom: 2px solid var(--secondary);
}

.dashboard-header h1 {
  color: var(--primary);
  font-weight: 600;
  margin: 0;
}

.back-to-dashboard-btn {
  background: var(--primary);
  color: white;
  padding: 8px 16px;
  border-radius: 6px;
  text-decoration: none;
  transition: all 0.3s;
  font-weight: 500;
}

.back-to-dashboard-btn:hover {
  background: #006a5e;
  box-shadow: var(--shadow);
  transform: translateY(-2px);
  color: white;
}

.user-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin-top: 15px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border-radius: 8px;
  overflow: hidden;
}

.user-table th {
  background-color: var(--primary);
  color: white;
  font-weight: 500;
  text-align: left;
  padding: 12px 15px;
}

.user-table td {
  padding: 12px 15px;
  border-bottom: 1px solid #e9ecef;
  vertical-align: middle;
}

.user-table tr:last-child td {
  border-bottom: none;
}

.user-table tbody tr {
  transition: background-color 0.2s;
}

.user-table tbody tr:hover {
  background-color: #f8f9fa;
}

.inactive-row {
  background-color: #fff8f8;
}

.inactive-row td {
  color: #721c24;
}

.btn-action {
  padding: 5px 10px;
  font-size: 13px;
  border-radius: 4px;
  margin-right: 5px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.alert {
  padding: 12px 15px;
  margin-bottom: 20px;
  border-radius: 6px;
  font-weight: 500;
}

.alert-success {
  background-color: #d4edda;
  color: #155724;
  border-left: 4px solid #28a745;
}

.alert-danger {
  background-color: #f8d7da;
  color: #721c24;
  border-left: 4px solid #dc3545;
}

@media (max-width: 768px) {
  .user-management-card {
    margin: 15px;
    padding: 20px;
  }
  
  .dashboard-header {
    flex-direction: column;
    gap: 10px;
    align-items: flex-start;
  }
  
  .back-to-dashboard-btn {
    margin-top: 10px;
  }
  
  .user-table {
    display: block;
    overflow-x: auto;
  }
}
    </style>
</head>
<body>
<div class="user-management-card">
    <div class="dashboard-header">
        <h1>User Management</h1>
        <a href="admin.php" class="back-to-dashboard-btn">Back </a>
    </div>

    <!-- Display any messages -->
    <?php
    if (isset($_SESSION['message'])) {
        echo "<div class='alert alert-success'>" . $_SESSION['message'] . "</div>";
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error'])) {
        echo "<div class='alert alert-danger'>" . $_SESSION['error'] . "</div>";
        unset($_SESSION['error']);
    }
    ?>

    <div class="mt-3 mb-3">
        <strong>Total Users:</strong> <?php echo $total_users; ?>
    </div>

    <table class="user-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Requests</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $userid = $row['userid'] ?? '';
                    $username = $row['username'] ?? '';
                    $email = $row['email'] ?? '';
                    $phone = $row['phoneno'] ?? '';
                    $status = $row['status'] ?? 'active';
                    $total_requests = $row['emergency_requests'] + 
                                      $row['prebooking_requests'] + 
                                      $row['palliative_requests'];

                    $row_class = $status == 'inactive' ? 'inactive-row' : '';
                    
                    echo "<tr class='{$row_class}' data-userid='" . htmlspecialchars($userid) . "'>";
                    echo "<td>" . htmlspecialchars($username) . "</td>";
                    echo "<td>" . htmlspecialchars($email) . "</td>";
                    echo "<td>" . htmlspecialchars($phone) . "</td>";
                    echo "<td>" . htmlspecialchars($total_requests) . "</td>";
                    echo "<td>" . htmlspecialchars(ucfirst($status)) . "</td>";
                    echo "<td>
                            <button class='btn btn-sm btn-info btn-action view-profile-btn' 
                                    data-userid='" . htmlspecialchars($userid) . "'>
                                View Profile
                            </button>
                            <form method='POST' style='display:inline;'>
                                <input type='hidden' name='userid' value='" . htmlspecialchars($userid) . "'>
                                <input type='hidden' name='current_status' value='" . htmlspecialchars($status) . "'>
                                <input type='hidden' name='toggle_user_status' value='1'>
                                <button type='submit' class='btn btn-sm " . 
                                    ($status == 'active' ? 'btn-danger' : 'btn-success') . " btn-action'>
                                    " . ($status == 'active' ? 'Deactivate' : 'Activate') . "
                                </button>
                            </form>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No users found</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('.view-profile-btn').on('click', function() {
        const userId = $(this).data('userid');
        window.location.href = 'admin_user_profile.php?userid=' + userId;
    });
});
</script>
</body>
</html>
<?php
$conn->close();
?>