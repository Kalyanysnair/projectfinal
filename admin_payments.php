<?php
include 'connect.php'; 

$paymentType = isset($_GET['payment_type']) ? htmlspecialchars($_GET['payment_type']) : 'emergency'; 
$paymentStatus = isset($_GET['payment_status']) ? htmlspecialchars($_GET['payment_status']) : 'all'; 
$searchQuery = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; // Search query

// Define table structure and columns based on payment type
switch ($paymentType) {
    case 'prebookings':
        $table = 'tbl_prebooking';
        $idColumn = 'prebookingid';
        $statusValue = "'Completed'";
        $patientColumn = "COALESCE(u_req.username, 'Not Specified') AS patient_name";
        $locationColumn = 'pickup_location';
        $driverIdColumn = 'driver_id';
        break;
    case 'palliative':
        $table = 'tbl_palliative';
        $idColumn = 'palliativeid';
        $statusValue = "'Completed'";
        $patientColumn = "COALESCE(u_req.username, 'Not Specified') AS patient_name";
        $locationColumn = 'address';
        $driverIdColumn = 'driver_id';
        break;
    default: // emergency
        $table = 'tbl_emergency';
        $idColumn = 'request_id';
        $statusValue = "'Completed'";
        $patientColumn = "COALESCE(patient_name, COALESCE(u_req.username, 'Not Specified')) AS patient_name";
        $locationColumn = 'pickup_location';
        $driverIdColumn = 'driver_id';
        break;
}

// First get driver details
$driverQuery = "SELECT 
    username as driver_name
FROM tbl_user 
WHERE role = 'driver'";

$driverStmt = $conn->prepare($driverQuery);
$driverStmt->execute();
$driverResult = $driverStmt->get_result();
$drivers = [];

while ($row = $driverResult->fetch_assoc()) {
    $drivers[] = $row;
}
$driverStmt->close();

// Main query for payments
$sql = "SELECT 
    p.$idColumn AS id,
    p.userid,
    $patientColumn,
    p.$locationColumn AS location,
    p.amount,
    p.payment_status,
    p.created_at,
    p.ambulance_type as request_ambulance_type,
    p.$driverIdColumn as driver_id,
    u.username as driver_name,
    d.vehicle_no
FROM $table p
LEFT JOIN tbl_user u_req ON p.userid = u_req.userid
LEFT JOIN tbl_user u ON p.$driverIdColumn = u.userid AND u.role = 'driver'
LEFT JOIN tbl_driver d ON d.userid = u.userid
WHERE p.status = $statusValue 
AND p.amount > 0";

$params = [];
$paramTypes = "";
if ($paymentStatus !== 'all') {
    $sql .= " AND p.payment_status = ?";
    $params[] = $paymentStatus;
    $paramTypes .= "s";
}
if (!empty($searchQuery)) {
    $sql .= " AND (p.$idColumn LIKE ? OR u_req.username LIKE ? OR u.username LIKE ? OR d.vehicle_no LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $paramTypes .= "ssss";
}

// Order by creation date (newest first)
$sql .= " ORDER BY p.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
    }
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

$conn->close();

function isSelected($current, $check) {
    return $current === $check ? 'selected' : '';
}

// Helper function to format payment status
function getStatusClass($status) {
    if (strtolower($status) === 'paid') {
        return 'status-paid';
    } elseif (strtolower($status) === 'pending') {
        return 'status-pending';
    } else {
        return 'status-other';
    }
}

// Calculate totals
$totalAmount = 0;
$paidAmount = 0;
$pendingAmount = 0;

foreach ($payments as $payment) {
    $totalAmount += (float)$payment['amount'];
    if (strtolower($payment['payment_status']) === 'paid') {
        $paidAmount += (float)$payment['amount'];
    } else {
        $pendingAmount += (float)$payment['amount'];
    }
}

// Add debug information
echo "<!-- Debug Info: -->";
echo "<!-- Number of drivers found: " . count($drivers) . " -->";
echo "<!-- Number of payments found: " . count($payments) . " -->";
foreach ($payments as $payment) {
    echo "<!-- Payment ID: " . $payment['id'] . ", Driver ID: " . $payment['driver_id'] . 
         ", Driver Name: " . (isset($payment['driver_name']) ? $payment['driver_name'] : 'Not set') . " -->";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Payment Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-dark: #1b5e20;
            --secondary-color: rgb(234, 235, 236);
            --accent-color: #5cb85c;
            --warning-color: #f0ad4e;
            --danger-color: #d9534f;
            --text-color: #333;
            --light-text: #6c757d;
            --border-color: #dee2e6;
            --shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --table-hover: rgba(46, 125, 50, 0.05);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg') no-repeat center center/cover;
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            position: relative;
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
            background: rgba(249, 245, 245, 0.92);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: var(--primary-color);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header h1 i {
            font-size: 1.6rem;
        }
        
        .header-stats {
            display: flex;
            gap: 15px;
        }
        
        .stat-card {
            padding: 8px 12px;
            border-radius: 4px;
            background-color: #f8f9fa;
            border-left: 3px solid var(--primary-color);
            font-size: 0.9rem;
        }
        
        .stat-card.paid {
            border-left-color: var(--accent-color);
        }
        
        .stat-card.pending {
            border-left-color: var(--warning-color);
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--primary-dark);
        }
        
        .filters {
            background-color: var(--secondary-color);
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 5px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.9rem;
        }
        
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
        }
        
        .table-responsive {
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-radius: 6px;
            background-color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        table th {
            background-color: var(--secondary-color);
            font-weight: 600;
            position: sticky;
            top: 0;
            color: var(--primary-dark);
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background-color: var(--table-hover);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .status {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }
        
        .status-paid {
            background-color: rgba(92, 184, 92, 0.15);
            color: #2e7d32;
            border: 1px solid rgba(92, 184, 92, 0.3);
        }
        
        .status-pending {
            background-color: rgba(240, 173, 78, 0.15);
            color: #f57c00;
            border: 1px solid rgba(240, 173, 78, 0.3);
        }
        
        .status-other {
            background-color: rgba(108, 117, 125, 0.15);
            color: #546e7a;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        
        .user-info, .vehicle-info, .date-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .username {
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .user-id, .phone, .ambulance-type {
            font-size: 0.85rem;
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .vehicle-no {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .amount {
            font-weight: 600;
            color: var(--accent-color);
            font-size: 1.05rem;
        }
        
        .date-info .time {
            font-size: 0.85rem;
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .vehicle-info .ambulance-type {
            text-transform: capitalize;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 0;
            color: var(--light-text);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--border-color);
        }
        
        .not-assigned {
            color: #999;
            font-style: italic;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .user-info .username {
            color: #333;
            font-weight: 600;
        }
        
        .user-info .user-id {
            color: #666;
            font-size: 0.85rem;
        }
        
        .vehicle-info {
            color: #444;
        }
        
        @media screen and (max-width: 768px) {
            .container {
                margin: 15px;
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header h1 {
                margin-bottom: 10px;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .header-stats {
                flex-direction: column;
                gap: 8px;
                margin-top: 10px;
            }
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            text-decoration: none;
            color: #fff;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(46, 125, 50, 0.9);
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .back-button:hover {
            transform: translateX(-3px);
            background: var(--primary-dark);
        }

        .service-area {
            font-size: 0.85rem;
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .phone {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .vehicle-no {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .ambulance-type {
            color: var(--primary-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .user-info i, .vehicle-info i, .date-info i {
            color: var(--primary-color);
            font-size: 0.9rem;
            width: 14px;
            text-align: center;
        }

        .id-card {
            display: flex;
            flex-direction: column;
            padding: 8px;
            border-radius: 6px;
            background-color: #f8f9fa;
            border-left: 3px solid var(--primary-color);
        }

        .request-id {
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.95rem;
        }

        .user-id {
            font-size: 0.85rem;
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .location-cell {
            max-width: 150px;
            min-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.9rem;
            position: relative;
        }
        
        .location-cell:hover {
            white-space: normal;
            overflow: visible;
            position: absolute;
            background: white;
            z-index: 1000;
            padding: 5px 10px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: auto;
            max-width: 300px;
        }

        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.8rem;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a {
            color: var(--primary-color);
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination a:hover:not(.active) {
            background-color: var(--secondary-color);
        }

        /* Add width constraints to other columns for better balance */
        .table-responsive table th:nth-child(1) { width: 120px; } /* Request ID */
        .table-responsive table th:nth-child(2) { width: 150px; } /* Patient/Service */
        .table-responsive table th:nth-child(3) { width: 150px; } /* Location */
        .table-responsive table th:nth-child(4) { width: 180px; } /* Driver Details */
        .table-responsive table th:nth-child(5) { width: 150px; } /* Vehicle Info */
        .table-responsive table th:nth-child(6) { width: 100px; } /* Amount */
        .table-responsive table th:nth-child(7) { width: 100px; } /* Status */
        .table-responsive table th:nth-child(8) { width: 150px; } /* Date */

        .driver-details {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 4px 0;
        }
        
        .driver-id, .driver-name {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .driver-id {
            color: var(--primary-color);
            font-weight: 600;
            padding: 2px 0;
        }
        
        .driver-name {
            color: var(--text-color);
            padding: 2px 0;
            border-top: 1px dashed var(--border-color);
        }
        
        .driver-details i {
            width: 16px;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <a href="admin.php" class="back-button" title="Back to Admin Panel">
        <i class="fas fa-chevron-left"></i>
    </a>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Payment Details</h1>
            <div class="header-stats">
                <div class="stat-card">
                    <span>Total Records: </span>
                    <span class="stat-value"><?= count($payments) ?></span>
                </div>
                <div class="stat-card">
                    <span>Total Amount: </span>
                    <span class="stat-value">₹<?= number_format($totalAmount, 2) ?></span>
                </div>
                <div class="stat-card paid">
                    <span>Paid: </span>
                    <span class="stat-value">₹<?= number_format($paidAmount, 2) ?></span>
                </div>
                <div class="stat-card pending">
                    <span>Pending: </span>
                    <span class="stat-value">₹<?= number_format($pendingAmount, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="form-group">
                    <label for="payment-type">Payment Type:</label>
                    <select id="payment-type" name="payment_type" onchange="this.form.submit()">
                        <option value="emergency" <?= isSelected($paymentType, 'emergency') ?>>Emergency</option>
                        <option value="prebookings" <?= isSelected($paymentType, 'prebookings') ?>>Prebookings</option>
                        <option value="palliative" <?= isSelected($paymentType, 'palliative') ?>>Palliative</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="payment-status">Payment Status:</label>
                    <select id="payment-status" name="payment_status" onchange="this.form.submit()">
                        <option value="all" <?= isSelected($paymentStatus, 'all') ?>>All Statuses</option>
                        <option value="Paid" <?= isSelected($paymentStatus, 'Paid') ?>>Paid</option>
                        <option value="Pending" <?= isSelected($paymentStatus, 'Pending') ?>>Pending</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" 
                           placeholder="Search by ID, name, vehicle..." 
                           value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
            </form>
        </div>

        <!-- Payment Details Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Patient/Service</th>
                        <th>Location</th>
                        <th>Driver Details</th>
                        <th>Vehicle Info</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-search"></i>
                                <p>No payments found matching your criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <div class="id-card">
                                        <span class="request-id">
                                            <i class="fas fa-hashtag"></i>
                                            <?= htmlspecialchars($payment['id']) ?>
                                        </span>
                                        <span class="user-id">
                                            <i class="fas fa-user-shield"></i>
                                            User: <?= htmlspecialchars($payment['userid']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($payment['patient_name']) ?></td>
                                <td class="location-cell" title="<?= htmlspecialchars($payment['location']) ?>">
                                    <?= htmlspecialchars($payment['location']) ?>
                                </td>
                                <td>
                                    <?php if (!empty($payment['driver_id'])): ?>
                                        <div class="driver-details">
                                            <span class="driver-id">
                                                <i class="fas fa-id-badge"></i>
                                                Driver ID: <?= htmlspecialchars($payment['driver_id']) ?>
                                            </span>
                                            <span class="driver-name">
                                                <?= htmlspecialchars($payment['driver_name'] ?? 'Not Assigned') ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="not-assigned">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="vehicle-info">
                                        <?php if (!empty($payment['vehicle_no'])): ?>
                                            <span class="vehicle-no">
                                                <?= htmlspecialchars($payment['vehicle_no']) ?>
                                            </span>
                                            <?php if (!empty($payment['request_ambulance_type'])): ?>
                                            <span class="ambulance-type">
                                                <?= htmlspecialchars($payment['request_ambulance_type']) ?>
                                            </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="not-assigned">Vehicle Not Assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="amount">₹<?= number_format((float)$payment['amount'], 2) ?></span>
                                </td>
                                <td>
                                    <span class="status <?= getStatusClass($payment['payment_status']) ?>">
                                        <?= !empty($payment['payment_status']) ? htmlspecialchars($payment['payment_status']) : 'Pending' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-info">
                                        <?= date('M d, Y', strtotime($payment['created_at'])) ?>
                                        <span class="time">
                                            <?= date('h:i A', strtotime($payment['created_at'])) ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Auto-submit form when search input changes (after a short delay)
        const searchInput = document.getElementById('search');
        let timeout = null;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                document.getElementById('filterForm').submit();
            }, 500); // Wait 500ms after typing stops
        });

        // Handle search field clearing
        searchInput.addEventListener('search', function() {
            if (this.value === '') {
                document.getElementById('filterForm').submit();
            }
        });
    </script>
</body>
</html>