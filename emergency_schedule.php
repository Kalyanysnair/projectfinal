<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'connect.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch Scheduled Drivers
$search_query = "";
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// SQL query to order upcoming dates first, then past dates
$sql = "SELECT * FROM tbl_scheduled_drivers 
        WHERE driver1_name LIKE ? 
        OR driver2_name LIKE ? 
        OR DATE_FORMAT(schedule_date, '%Y-%m-%d') LIKE ? 
        ORDER BY (schedule_date >= CURDATE()) DESC, schedule_date ASC";

$stmt = $conn->prepare($sql);
$like_search = "%$search_query%";
$stmt->bind_param("sss", $like_search, $like_search, $like_search);
$stmt->execute();
$result = $stmt->get_result();

// Pagination
$rows_per_page = 10;
$total_rows = $result->num_rows;
$total_pages = ceil($total_rows / $rows_per_page);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $rows_per_page;

// Fetch limited records per page
$sql .= " LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $like_search, $like_search, $like_search, $offset, $rows_per_page);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Schedule - SwiftAid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #008374;
            --hover-color: #00a28a;
            --text-color: #ffffff;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg') no-repeat center center/cover;
        }

        #content {
            width: 80%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            backdrop-filter: blur(5px);
        }

        .container-box {
            background: rgba(213, 210, 210, 0.57);
            padding: 20px;
            border-radius: 10px;
            max-width: 800px;
            margin: 0 auto;
            backdrop-filter: blur(15px);
        }

        .table-container {
            background: rgba(255, 255, 255, 0.52);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            max-width: 1000px;
            width: 90%;
            overflow: hidden;
        }

        .table-responsive {
            max-height: 60vh;
            overflow-y: auto;
        }

        .table thead {
            background: var(--primary-color);
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table thead th {
            border-bottom: 2px solid #dee2e6;
        }

        .pagination a {
            margin: 5px;
            padding: 5px 10px;
            background: var(--primary-color);
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
        }

        .pagination a:hover {
            background: var(--hover-color);
        }

        /* Back Button Styling */
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 24px;
            color: #fff;
            text-decoration: none;
            background: transparent;
            padding: 5px 15px;
            border-radius: 5px;
        }

        .back-btn:hover {
            color: var(--hover-color);
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="admin.php" class="back-btn"><i class="fas fa-chevron-left"></i></a>

    <div id="content"><br>
        <h1>Emergency Driver Schedule</h1>

        <!-- Search Bar -->
        <div class="container-box">
            <h3>Search Scheduled Drivers</h3>
            <form method="GET">
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search_query); ?>" placeholder="Search by driver name or date (YYYY-MM-DD)...">
            </form>
        </div>

        <br>

        <!-- Scheduled Drivers Table -->
        <div class="table-container">
            <h3>Scheduled Drivers</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Driver 1</th>
                            <th>Driver 2</th>
                            <th>Scheduled Date</th>
                            <th>Day</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['driver1_name']); ?></td>
                                <td><?= htmlspecialchars($row['driver2_name']); ?></td>
                                <td><?= $row['schedule_date']; ?></td>
                                <td><?= date('l', strtotime($row['schedule_date'])); ?></td> <!-- Display Day -->
                                <td><?= $row['created_at']; ?></td>
                                <td>
                                    <a href="edit_schedule.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i; ?>&search=<?= urlencode($search_query); ?>"><?= $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</body>
</html>