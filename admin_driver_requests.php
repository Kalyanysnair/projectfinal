<?php
session_start();
include 'connect.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get all drivers
$drivers_query = "SELECT u.userid, u.username FROM tbl_user u WHERE u.role = 'driver' ORDER BY u.username";
$drivers_result = $conn->query($drivers_query);
$drivers = [];
while ($row = $drivers_result->fetch_assoc()) {
    $drivers[] = $row;
}

// Get selected filters
$selected_driver = isset($_GET['driver']) ? $_GET['driver'] : 'all';
$selected_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$selected_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Get date filters with default values (last 30 days)
$default_start_date = date('Y-m-d', strtotime('-30 days'));
$default_end_date = date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;

// Build the query with date filter
$requests_query = "
    (SELECT 
        'emergency' COLLATE utf8mb4_unicode_ci as request_type,
        e.request_id as id,
        e.patient_name COLLATE utf8mb4_unicode_ci as patient_name,
        e.pickup_location COLLATE utf8mb4_unicode_ci as location,
        e.contact_phone COLLATE utf8mb4_unicode_ci as phone,
        e.status COLLATE utf8mb4_unicode_ci as status,
        e.created_at,
        e.amount,
        COALESCE(e.payment_status, 'Pending') COLLATE utf8mb4_unicode_ci as payment_status,
        u.username COLLATE utf8mb4_unicode_ci as driver_name,
        u.userid as driver_id
    FROM tbl_emergency e
    LEFT JOIN tbl_user u ON e.driver_id = u.userid
    WHERE e.status IN ('Accepted', 'Approved', 'Completed')
    AND DATE(e.created_at) BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT 
        'palliative' COLLATE utf8mb4_unicode_ci as request_type,
        p.palliativeid as id,
        u_req.username COLLATE utf8mb4_unicode_ci as patient_name,
        p.address COLLATE utf8mb4_unicode_ci as location,
        u_req.phoneno COLLATE utf8mb4_unicode_ci as phone,
        p.status COLLATE utf8mb4_unicode_ci as status,
        p.created_at,
        p.amount,
        COALESCE(p.payment_status, 'Pending') COLLATE utf8mb4_unicode_ci as payment_status,
        u_dr.username COLLATE utf8mb4_unicode_ci as driver_name,
        u_dr.userid as driver_id
    FROM tbl_palliative p
    LEFT JOIN tbl_user u_req ON p.userid = u_req.userid
    LEFT JOIN tbl_user u_dr ON p.driver_id = u_dr.userid
    WHERE p.status IN ('Accepted', 'Approved', 'Completed')
    AND DATE(p.created_at) BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT 
        'prebooking' COLLATE utf8mb4_unicode_ci as request_type,
        p.prebookingid as id,
        u.username COLLATE utf8mb4_unicode_ci as patient_name,
        p.pickup_location COLLATE utf8mb4_unicode_ci as location,
        u.phoneno COLLATE utf8mb4_unicode_ci as phone,
        p.status COLLATE utf8mb4_unicode_ci as status,
        p.created_at,
        p.amount,
        COALESCE(p.payment_status, 'Pending') COLLATE utf8mb4_unicode_ci as payment_status,
        u_dr.username COLLATE utf8mb4_unicode_ci as driver_name,
        u_dr.userid as driver_id
    FROM tbl_prebooking p
    LEFT JOIN tbl_user u ON p.userid = u.userid
    LEFT JOIN tbl_user u_dr ON p.driver_id = u_dr.userid
    WHERE p.status IN ('Accepted', 'Approved', 'Completed')
    AND DATE(p.created_at) BETWEEN '$start_date' AND '$end_date')
    ORDER BY created_at DESC";

// Execute the query
$requests_result = $conn->query($requests_query);
if (!$requests_result) {
    die("Query failed: " . $conn->error);
}

$requests = [];
while ($row = $requests_result->fetch_assoc()) {
    // Modified filtering logic to handle combined status and request type
    $status_match = $selected_status === 'all' || 
                   $row['status'] === $selected_status || 
                   ($selected_status === 'active' && ($row['status'] === 'Accepted' || $row['status'] === 'Approved'));
    
    $type_match = $selected_type === 'all' || $row['request_type'] === $selected_type;
    
    if (($selected_driver === 'all' || $row['driver_id'] == $selected_driver) && 
        $status_match && $type_match) {
        $requests[] = $row;
    }
}

// Calculate summary statistics
$summary = [
    'total_bookings' => count($requests),
    'total_amount' => array_sum(array_map(function($req) { return $req['amount'] ?? 0; }, $requests)),
    'paid_amount' => array_sum(array_map(function($req) { 
        return ($req['payment_status'] === 'Paid') ? ($req['amount'] ?? 0) : 0; 
    }, $requests)),
    'pending_amount' => array_sum(array_map(function($req) { 
        return ($req['payment_status'] === 'Pending') ? ($req['amount'] ?? 0) : 0; 
    }, $requests)),
    'type_breakdown' => ['emergency' => 0, 'palliative' => 0, 'prebooking' => 0],
    'status_breakdown' => ['Completed' => 0, 'Accepted' => 0, 'Approved' => 0],
    'payment_breakdown' => ['Paid' => 0, 'Pending' => 0]
];

// Calculate breakdowns
foreach ($requests as $request) {
    $summary['type_breakdown'][$request['request_type']]++;
    $summary['status_breakdown'][$request['status']]++;
    $summary['payment_breakdown'][$request['payment_status']]++;
}

// Determine which columns to show based on filters
$show_driver_col = $selected_driver === 'all';
$show_type_col = $selected_type === 'all';
$show_status_col = $selected_status === 'all';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Requests - Admin Dashboard</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            min-height: 100vh;
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            padding: 20px;
            padding-top: 100px;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .filters-section {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .filters {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .filter-group {
            flex: 1;
            min-width: 180px;
            max-width: 250px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .summary-card h3 {
            font-size: 1rem;
            color: #666;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .summary-card .subtext {
            font-size: 0.9rem;
            color: #666;
        }
        .requests-section {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
        }
        .section-title {
            color: #2c3e50;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid #eee;
        }
        .export-button, .filter-button, .cancel-filter-button {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        .cancel-filter-button {
            background-color: #6c757d;
            margin-left: 10px;
        }
        .export-button {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .export-button:hover, .filter-button:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .cancel-filter-button:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .back-button:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: white;
        }
        .report-table-container {
            overflow-x: auto;
            margin: 0;
            max-width: 100%;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 0.95rem;
        }
        .report-table th, .report-table td {
            padding: 12px 15px;
            border: 1px solid #e9ecef;
            text-align: left;
        }
        .report-table thead th {
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        .report-table tbody tr:hover { background-color: #f8f9fa; }
        .request-type-badge, .status-badge, .payment-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: left;
            min-width: 100px;
        }
        .type-emergency { background: #ffd7d7; color: #c41e3a; }
        .type-palliative { background: #d7ffd7; color: #1e8449; }
        .type-prebooking { background: #d7d7ff; color: #1e3a8a; }
        .status-Completed { background: #d4edda; color: #155724; }
        .status-Accepted, .status-Approved { background: #cce5ff; color: #004085; }
        .payment-Paid { background: #d4edda; color: #155724; }
        .payment-Pending { background: #fff3cd; color: #856404; }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            text-align: center;
        }
        .pdf-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .pdf-header h1 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .pdf-header p {
            color: #666;
            margin: 5px 0;
        }
        @media print {
            .filters-section, .back-button, .export-button { 
                display: none !important; 
            }
            .requests-section { 
                background: white !important; 
                padding: 0 !important; 
                margin-top: 0 !important;
            }
            .report-table th, .report-table td { 
                border-color: #000 !important; 
            }
            .report-table thead th { 
                background: #f8f9fa !important; 
                -webkit-print-color-adjust: exact; 
            }
            .pdf-header {
                display: block !important;
            }
        }
        @media (max-width: 768px) {
            .filters { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; max-width: 100%; }
            .summary-cards { grid-template-columns: 1fr; }
            .report-table-container { margin: 0 -15px; }
        }
    </style>
</head>
<body>
    <a href="admin.php" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <button onclick="generatePDF()" class="export-button">
        <i class="fas fa-file-pdf"></i> Export PDF
    </button>

    <div class="container">
        <div class="glass-card">
            <div class="pdf-header" style="display: none;">
                <h1>Driver Requests Report - SwiftAid</h1>
                <p>Report Period: <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?></p>
                <p>Generated on <?php echo date('F j, Y, g:i a'); ?></p>
                <?php if ($selected_driver !== 'all'): ?>
                    <p>Driver: <?php 
                        $selected_driver_name = '';
                        foreach ($drivers as $driver) {
                            if ($driver['userid'] == $selected_driver) {
                                $selected_driver_name = $driver['username'];
                                break;
                            }
                        }
                        echo htmlspecialchars($selected_driver_name);
                    ?></p>
                <?php endif; ?>
                <?php if ($selected_type !== 'all'): ?>
                    <p>Request Type: <?php echo ucfirst(htmlspecialchars($selected_type)); ?></p>
                <?php endif; ?>
                <?php if ($selected_status !== 'all'): ?>
                    <p>Status: <?php echo ucfirst(htmlspecialchars($selected_status)); ?></p>
                <?php endif; ?>
            </div>

            <!-- Summary Cards Section -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Bookings</h3>
                    <div class="value"><?php echo $summary['total_bookings']; ?></div>
                    <div class="subtext">All request types</div>
                </div>
                
                <div class="summary-card">
                    <h3>Total Amount</h3>
                    <div class="value">₹<?php echo number_format($summary['total_amount'], 2); ?></div>
                    <div class="subtext">Combined value</div>
                </div>
                
                <div class="summary-card">
                    <h3>Paid Amount</h3>
                    <div class="value">₹<?php echo number_format($summary['paid_amount'], 2); ?></div>
                    <div class="subtext"><?php echo $summary['payment_breakdown']['Paid']; ?> completed payments</div>
                </div>
                
                <div class="summary-card">
                    <h3>Pending Amount</h3>
                    <div class="value">₹<?php echo number_format($summary['pending_amount'], 2); ?></div>
                    <div class="subtext"><?php echo $summary['payment_breakdown']['Pending']; ?> pending payments</div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section mb-4">
                <h3 class="section-title"><i class="fas fa-filter"></i> Report Filters</h3>
                <form id="filter-form" method="get">
                    <div class="filters">
                        <div class="filter-group">
                            <label for="start-date">Start Date:</label>
                            <input type="date" id="start-date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="end-date">End Date:</label>
                            <input type="date" id="end-date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="type-filter">Request Type:</label>
                            <select id="type-filter" name="type">
                                <option value="all">All Types</option>
                                <option value="emergency" <?php echo $selected_type == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                <option value="prebooking" <?php echo $selected_type == 'prebooking' ? 'selected' : ''; ?>>Pre-booking</option>
                                <option value="palliative" <?php echo $selected_type == 'palliative' ? 'selected' : ''; ?>>Palliative</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="driver-filter">Driver:</label>
                            <select id="driver-filter" name="driver">
                                <option value="all">All Drivers</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?php echo $driver['userid']; ?>" 
                                        <?php echo $selected_driver == $driver['userid'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($driver['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="status-filter">Status:</label>
                            <select id="status-filter" name="status">
                                <option value="all">All Statuses</option>
                                <option value="active" <?php echo $selected_status == 'active' ? 'selected' : ''; ?>>Accepted & Approved</option>
                                <option value="Completed" <?php echo $selected_status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div class="filter-group d-flex align-items-end">
                            <button type="submit" class="filter-button mt-4">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="admin_driver_requests.php" class="cancel-filter-button mt-4">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Detailed Requests Section -->
            <div class="requests-section">
                <h3 class="section-title"><i class="fas fa-list"></i> Service Requests Report</h3>
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                        <p class="text-muted">No requests found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="report-table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <?php if ($show_type_col): ?><th>Type</th><?php endif; ?>
                                    <th>Patient Name</th>
                                    <?php if ($show_driver_col): ?><th>Driver</th><?php endif; ?>
                                    <?php if ($show_status_col): ?><th>Status</th><?php endif; ?>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['id']); ?></td>
                                        <?php if ($show_type_col): ?>
                                        <td>
                                            <span class="request-type-badge type-<?php echo htmlspecialchars($request['request_type']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?>
                                            </span>
                                        </td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                        <?php if ($show_driver_col): ?>
                                        <td><?php echo htmlspecialchars($request['driver_name'] ?? 'Not Assigned'); ?></td>
                                        <?php endif; ?>
                                        <?php if ($show_status_col): ?>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($request['status']); ?>">
                                                <?php echo htmlspecialchars($request['status']); ?>
                                            </span>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php echo $request['amount'] ? '₹' . number_format($request['amount'], 2) : 'Not Set'; ?>
                                        </td>
                                        <td>
                                            <span class="payment-badge payment-<?php echo htmlspecialchars($request['payment_status'] ?? 'Pending'); ?>">
                                                <?php echo htmlspecialchars($request['payment_status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        async function generatePDF() {
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'loading-overlay';
            loadingDiv.innerHTML = `
                <div class="loading-content">
                    <h3><i class="fas fa-spinner fa-spin"></i> Generating PDF...</h3>
                    <p>Please wait while we prepare your report</p>
                </div>
            `;
            document.body.appendChild(loadingDiv);

            try {
                // Create a temporary container for PDF content
                const pdfContainer = document.createElement('div');
                pdfContainer.style.width = '210mm';
                pdfContainer.style.padding = '20px';
                pdfContainer.style.backgroundColor = 'white';
                pdfContainer.style.margin = '0 auto';
                
                // Add the PDF header
                const pdfHeader = document.querySelector('.pdf-header').cloneNode(true);
                pdfHeader.style.display = 'block';
                pdfContainer.appendChild(pdfHeader);
                
                // Clone the summary cards
                const summaryCards = document.querySelector('.summary-cards').cloneNode(true);
                summaryCards.style.marginBottom = '20px';
                pdfContainer.appendChild(summaryCards);
                
                // Clone the requests table
                const requestsSection = document.querySelector('.requests-section').cloneNode(true);
                requestsSection.style.background = 'white';
                requestsSection.style.boxShadow = 'none';
                requestsSection.style.padding = '0';
                requestsSection.style.marginTop = '0';
                
                // Remove the section title from the PDF
                const sectionTitle = requestsSection.querySelector('.section-title');
                if (sectionTitle) {
                    sectionTitle.remove();
                }
                
                pdfContainer.appendChild(requestsSection);

                // Add PDF-specific styles
                const style = document.createElement('style');
                style.textContent = `
                    body {
                        background: white !important;
                        font-family: Arial, sans-serif;
                    }
                    .summary-cards {
                        display: grid;
                        grid-template-columns: repeat(4, 1fr);
                        gap: 15px;
                        margin-bottom: 20px;
                    }
                    .summary-card {
                        background: #f8f9fa;
                        border-radius: 8px;
                        padding: 15px;
                        box-shadow: none;
                        border: 1px solid #e9ecef;
                    }
                    .summary-card h3 {
                        font-size: 10pt;
                        margin-bottom: 8px;
                    }
                    .summary-card .value {
                        font-size: 16pt;
                        margin-bottom: 5px;
                    }
                    .summary-card .subtext {
                        font-size: 9pt;
                    }
                    .report-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 10pt;
                    }
                    .report-table th, .report-table td {
                        padding: 8px 10px;
                        border: 1px solid #ddd;
                    }
                    .report-table th {
                        background-color: #f8f9fa;
                        font-weight: bold;
                    }
                    .request-type-badge, .status-badge, .payment-badge {
                        padding: 3px 6px;
                        font-size: 9pt;
                    }
                    .pdf-header {
                        text-align: center;
                        margin-bottom: 20px;
                        padding-bottom: 10px;
                        border-bottom: 1px solid #eee;
                    }
                    .pdf-header h1 {
                        font-size: 18pt;
                        margin-bottom: 5px;
                    }
                    .pdf-header p {
                        font-size: 10pt;
                        margin: 3px 0;
                    }
                `;
                pdfContainer.appendChild(style);
                document.body.appendChild(pdfContainer);

                // Generate PDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const margin = 15;
                const contentWidth = pageWidth - (2 * margin);

                // Generate canvas from the content
                const canvas = await html2canvas(pdfContainer, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    scrollX: 0,
                    scrollY: 0,
                    windowWidth: pdfContainer.scrollWidth,
                    windowHeight: pdfContainer.scrollHeight
                });

                // Calculate image dimensions
                const imgData = canvas.toDataURL('image/png', 1.0);
                const imgWidth = contentWidth;
                const imgHeight = (canvas.height * contentWidth) / canvas.width;

                // Add image to PDF
                pdf.addImage(imgData, 'PNG', margin, margin, imgWidth, imgHeight);

                // Add footer
                const totalPages = pdf.internal.getNumberOfPages();
                for (let i = 1; i <= totalPages; i++) {
                    pdf.setPage(i);
                    pdf.setFontSize(8);
                    pdf.setTextColor(100);
                    pdf.text(`Page ${i} of ${totalPages}`, pageWidth - margin, pageHeight - 10, { align: 'right' });
                    pdf.text('SwiftAid Ambulance Service', margin, pageHeight - 10, { align: 'left' });
                }

                // Save PDF
                const startDateStr = '<?php echo date('Ymd', strtotime($start_date)); ?>';
                const endDateStr = '<?php echo date('Ymd', strtotime($end_date)); ?>';
                const filename = `SwiftAid_Requests_${startDateStr}_to_${endDateStr}.pdf`;
                pdf.save(filename);

            } catch (error) {
                console.error('PDF generation error:', error);
                alert('Failed to generate PDF. Please try again later.');
            } finally {
                // Clean up
                const pdfContainer = document.querySelector('div[style*="210mm"]');
                if (pdfContainer) {
                    document.body.removeChild(pdfContainer);
                }
                document.body.removeChild(loadingDiv);
            }
        }
    </script>
</body>
</html>