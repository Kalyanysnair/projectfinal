<?php
include 'header.php';
include 'connect.php';

$search_query = "";
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Fetch schedules based on driver name
$sql = "SELECT * FROM tbl_scheduled_drivers 
        WHERE (driver1_name LIKE ? OR driver2_name LIKE ?) 
        AND schedule_date >= CURDATE()
        ORDER BY schedule_date ASC";

$stmt = $conn->prepare($sql);
$like_search = "%$search_query%";
$stmt->bind_param("ss", $like_search, $like_search);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/main.css" rel="stylesheet">

    <style>
        body {
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            font-family: 'Poppins', sans-serif;
            color: #222; 
        }
        .container-box {
            background: rgba(255, 255, 255, 0.3); 
            padding: 25px;
            border-radius: 12px;
            max-width: 900px;
            margin: 85px auto; 
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(12px);
        }
        .table-container {
            background: rgba(255, 255, 255, 0.25);
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            backdrop-filter: blur(10px);
        }
        .table th {
            background: rgba(0, 131, 39, 0.85); 
            color: #fff;
        }
        .table td {
            color: #222;
        }
        .btn-search {
            background:rgb(0, 131, 0);
            color: white;
            border: none;
        }
        .btn-search:hover {
            background:rgb(0, 162, 32);
        }
        @media (max-width: 768px) {
            .container-box {
                width: 95%;
            }
        }
    </style>
</head>
<body>

<div class="container-box">
<button type="button" class="btn back-btn" onclick="window.history.back()"> 
  &#8592; Back
</button>

    <h2 class="text-center text-dark">🚑 My Schedule</h2>

    <!-- Search Bar -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" class="form-control" name="search" 
                value="<?= htmlspecialchars($search_query); ?>" 
                placeholder="Enter your name to search..." required>
            <button type="submit" class="btn btn-search"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>

    <div class="table-container">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Driver 1</th>
                    <th>Driver 2</th>
                    <th>Scheduled Date</th>
                    <th>Day</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['driver1_name']); ?></td>
                            <td><?= htmlspecialchars($row['driver2_name']); ?></td>
                            <td><?= $row['schedule_date']; ?></td>
                            <td><?= date('l', strtotime($row['schedule_date'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-danger">No schedules found for this name.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
