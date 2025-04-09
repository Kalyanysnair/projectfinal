<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'connect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get user ID from URL safely
if (!isset($_GET['userid']) || !filter_var($_GET['userid'], FILTER_VALIDATE_INT)) {
    die("Invalid or missing user ID.");
}
$userid = intval($_GET['userid']);

// Initialize date filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$date_filter_applied = !empty($start_date) && !empty($end_date);

// Function to safely execute prepared statements
function executeQuery($conn, $query, $param_types = null, $param_values = []) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed for query: $query. Error: " . $conn->error);
    }
    
    if ($param_types && !empty($param_values)) {
        // Dynamically bind parameters
        $bind_params = [$param_types];
        foreach ($param_values as $key => $value) {
            $bind_params[] = &$param_values[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
    
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    return $stmt->get_result();
}

// Fetch user details
try {
    $user_result = executeQuery($conn, 
        "SELECT userid, username, email, phoneno, role, status, created_at, 
                latitude, longitude, google_id" 
        . " FROM tbl_user WHERE userid = ?", "i", [$userid]);
    $user = $user_result->fetch_assoc();

    if (!$user) {
        die("User not found.");
    }

    // Prepare date filter conditions
    $date_condition = "";
    $date_params = [];
    $param_types = "i";
    $param_values = [$userid];
    
    if ($date_filter_applied) {
        $date_condition = " AND created_at BETWEEN ? AND ?";
        $param_types .= "ss";
        $param_values[] = $start_date . " 00:00:00";
        $param_values[] = $end_date . " 23:59:59";
    }

    // Fetch all requests into a single array
    $all_requests = [];
    
    // Emergency requests
    $query = "SELECT request_id as id, 'Emergency' as request_type, pickup_location, 
            ambulance_type, status, amount, payment_status, created_at
            FROM tbl_emergency 
            WHERE userid = ?" . $date_condition;
    $emergency_result = executeQuery($conn, $query, $param_types, $param_values);
    while ($row = $emergency_result->fetch_assoc()) {
        $all_requests[] = $row;
    }
    
    // Prebooking requests
    $query = "SELECT prebookingid as id, 'Prebooking' as request_type, pickup_location,
             ambulance_type, status, amount, payment_status, service_time as created_at
            FROM tbl_prebooking 
            WHERE userid = ?" . $date_condition;
    $prebooking_result = executeQuery($conn, $query, $param_types, $param_values);
    while ($row = $prebooking_result->fetch_assoc()) {
        $all_requests[] = $row;
    }
    
    // Palliative requests
    $query = "SELECT palliativeid as id, 'Palliative' as request_type, address as pickup_location,
            ambulance_type, status, amount, payment_status, created_at
            FROM tbl_palliative 
            WHERE userid = ?" . $date_condition;
    $palliative_result = executeQuery($conn, $query, $param_types, $param_values);
    while ($row = $palliative_result->fetch_assoc()) {
        $all_requests[] = $row;
    }
    
    // Sort by created_at date, newest first
    usort($all_requests, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

} catch (Exception $e) {
    die("Error fetching user data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - SwiftAid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            background: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg') no-repeat center center/cover;
            background-color: #f4f6f9;
        }
        .profile-container {
            max-width: 1000px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .section-header {
            background: linear-gradient(135deg, rgb(43, 174, 23), rgb(0, 131, 50));
            color: white;
            padding: 12px 15px;
            margin-top: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-header h4 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        .action-buttons {
            position: absolute;
            top: 30px;
            right: 30px;
            display: flex;
            gap: 10px;
        }
        .action-button {
            background: linear-gradient(135deg, rgb(43, 174, 23), rgb(0, 131, 50));
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .action-button:hover {
            background: linear-gradient(135deg, rgb(38, 156, 20), rgb(0, 116, 44));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .table {
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .table thead th {
            background: linear-gradient(135deg, rgb(43, 174, 23), rgb(0, 131, 50));
            color: white;
            font-weight: 500;
            border: none;
            padding: 12px 15px;
        }
        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,0.02);
        }
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 15px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        .info-value {
            color: #212529;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        .date-filter {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        .badge {
            font-size: 0.9em;
            padding: 6px 10px;
        }
        .badge-emergency {
            background-color: #dc3545;
        }
        .badge-prebooking {
            background-color: #0d6efd;
        }
        .badge-palliative {
            background-color: #6c757d;
        }
        @media print {
            .action-buttons, .date-filter, .user-info-section {
                display: none;
            }
            body {
                background: none;
            }
            .profile-container {
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="profile-container">
        <div class="text-center mb-4 position-relative">
        <a href="UserManagement.php" class="action-button" style="position: absolute; top: 20px; left: 20px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            <h1 class="display-5 fw-bold text-success mb-3">User Profile Report</h1>
            <div class="action-buttons">
                
                <button onclick="generatePDF()" class="action-button">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        
        <!-- Date Filter Form -->
        <div class="date-filter">
            <form method="GET" action="" class="row g-3 align-items-center">
                <input type="hidden" name="userid" value="<?php echo $userid; ?>">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-success me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <?php if ($date_filter_applied): ?>
                    <a href="?userid=<?php echo $userid; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear Filter
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- User Information Section (will be hidden in PDF) -->
        <div class="user-info-section">
            <div class="section-header">
                <h4><i class="fas fa-user-circle me-2"></i>User Information</h4>
            </div>
            <div class="user-info-grid">
                <div class="info-item">
                    <span class="info-label">User ID</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['userid']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Username</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['phoneno']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Created</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['created_at']); ?></span>
                </div>
            </div>
        </div>

        <div class="section-header">
            <h4><i class="fas fa-history me-2"></i>Service Request History</h4>
            <?php if ($date_filter_applied): ?>
            <span class="badge bg-info">
                Filtered: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>
            </span>
            <?php endif; ?>
        </div>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Request Type</th>
                    <th>Ambulance/Service Type</th>
                    <th>Service Status</th>
                    <th>Amount</th>
                    <th>Payment Status</th>
                    <th>Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (count($all_requests) > 0) {
                    foreach ($all_requests as $request) {
                        // Define badge color based on request type
                        $badge_class = '';
                        switch($request['request_type']) {
                            case 'Emergency':
                                $badge_class = 'badge-emergency';
                                break;
                            case 'Prebooking':
                                $badge_class = 'badge-prebooking';
                                break;
                            case 'Palliative':
                                $badge_class = 'badge-palliative';
                                break;
                        }
                        
                        echo "<tr>
                                <td>" . htmlspecialchars($request['id']) . "</td>
                                <td><span class='badge " . $badge_class . "'>" . htmlspecialchars($request['request_type']) . "</span></td>
                                <td>" . htmlspecialchars($request['ambulance_type'] ?? 'N/A') . "</td>
                                <td>" . htmlspecialchars($request['status']) . "</td>
                                <td>" . htmlspecialchars($request['amount'] ?? 'N/A') . "</td>
                                <td>" . htmlspecialchars($request['payment_status']) . "</td>
                                <td>" . htmlspecialchars($request['created_at']) . "</td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='8'>No requests found";
                    if ($date_filter_applied) {
                        echo " for the selected date range";
                    }
                    echo "</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <?php if (count($all_requests) > 0): ?>
        <div class="mt-4">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> 
                <strong>Summary:</strong> Total <?php echo count($all_requests); ?> requests
                <?php if ($date_filter_applied): ?>
                from <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function generatePDF() {
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
    loadingDiv.innerHTML = '<div style="background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.2);"><h3 style="margin:0; color:#28a745;"><i class="fas fa-spinner fa-spin" style="margin-right: 10px;"></i> Generating PDF...</h3></div>';
    document.body.appendChild(loadingDiv);

    // Create a temporary container for PDF content
    const pdfContainer = document.createElement('div');
    pdfContainer.style.width = '210mm';
    pdfContainer.style.padding = '15mm';
    pdfContainer.style.backgroundColor = 'white';
    pdfContainer.style.margin = '0 auto';
    
    // Add the professional header
    const headerContent = `
        <div class="pdf-header" style="text-align: center; margin-bottom: 30px;">
            <div style="text-align: center;">
                <img src="assets/img/logo.png" alt="SwiftAid Logo" style="height: 60px; margin-bottom: 10px;">
            </div>
            <h1 style="font-size: 24px; color: #28a745; margin-bottom: 5px; font-weight: bold;">SwiftAid Ambulance Service</h1>
            <h2 style="font-size: 20px; color: #333; margin: 10px 0; font-weight: bold;">User Activity Report</h2>
            <p style="color: #666; font-size: 12px;">User ID: <?php echo htmlspecialchars($user['userid']); ?> | Username: <?php echo htmlspecialchars($user['username']); ?></p>
            <p style="color: #666; font-size: 12px;">
                <?php if ($date_filter_applied): ?>
                Report Period: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>
                <?php else: ?>
                Complete Activity Report
                <?php endif; ?>
            </p>
            <p style="color: #666; font-size: 12px;">Generated on: ${new Date().toLocaleString()}</p>
            <div style="width: 100%; height: 2px; background: #28a745; margin: 15px auto;"></div>
        </div>
    `;
    
    pdfContainer.innerHTML = headerContent;
    
    // Clone only the table and summary section
    const table = document.querySelector('.table').cloneNode(true);
    const summary = document.querySelector('.mt-4') ? document.querySelector('.mt-4').cloneNode(true) : null;
    
    // Add to PDF container
    pdfContainer.appendChild(table);
    if (summary) {
        pdfContainer.appendChild(summary);
    }

    // Add PDF-specific styles
    const style = document.createElement('style');
    style.textContent = `
        .pdf-header {
            text-align: center !important;
            margin-bottom: 30px !important;
            page-break-after: avoid !important;
        }
        .pdf-header h1 {
            font-size: 24px !important;
            color: #28a745 !important;
            margin-bottom: 5px !important;
            font-weight: bold !important;
        }
        .pdf-header h2 {
            font-size: 20px !important;
            color: #333 !important;
            margin: 10px 0 !important;
            font-weight: bold !important;
        }
        .pdf-header p {
            color: #666 !important;
            font-size: 12px !important;
            margin: 5px 0 !important;
        }
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 8px !important;
            margin-bottom: 15px !important;
            font-size: 10px !important;
            page-break-inside: auto !important;
        }
        .table th, .table td {
            padding: 6px !important;
            border: 1px solid #000000 !important;
            color: #000000 !important;
            line-height: 1.2 !important;
        }
        .table th {
            background: #f8f9fa !important;
            font-weight: bold !important;
            color: #000000 !important;
            font-size: 11px !important;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa !important;
        }
        .badge {
            padding: 2px 5px !important;
            border-radius: 3px !important;
            font-size: 9px !important;
            font-weight: normal !important;
            text-transform: uppercase !important;
            color: #fff !important;
        }
        .badge-emergency {
            background-color: #dc3545 !important;
        }
        .badge-prebooking {
            background-color: #0d6efd !important;
        }
        .badge-palliative {
            background-color: #6c757d !important;
        }
        tr {
            page-break-inside: avoid !important;
        }
        .mt-4 {
            margin-top: 15px !important;
        }
        .alert {
            padding: 8px !important;
            border: 1px solid #ccc !important;
            border-radius: 4px !important;
            margin-top: 15px !important;
            font-size: 11px !important;
            background-color: #f8f9fa !important;
            color: #000 !important;
        }
        .alert-info {
            border-left: 4px solid #0dcaf0 !important;
        }
        .me-2 {
            margin-right: 6px !important;
        }
    `;
    
    pdfContainer.appendChild(style);
    document.body.appendChild(pdfContainer);

    // Use html2canvas to capture the content
    html2canvas(pdfContainer, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        logging: false,
        backgroundColor: '#ffffff'
    }).then(canvas => {
        // Initialize jsPDF
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');

        // Calculate dimensions
        const imgData = canvas.toDataURL('image/png');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
        
        // If content is too tall for one page, split it
        const pageHeight = pdf.internal.pageSize.getHeight();
        let position = 0;
        let page = 1;
        
        // Add first page
        pdf.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
        
        // Add page numbers
        pdf.setFontSize(8);
        pdf.setTextColor(150);
        pdf.text(`Page ${page}`, pdfWidth - 20, pageHeight - 10);
        
        // If content exceeds page height, add more pages
        while (position - pdfHeight < -pageHeight) {
            position = position - pageHeight;
            page++;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
            pdf.text(`Page ${page}`, pdfWidth - 20, pageHeight - 10);
        }

        // Add footer with company info on each page
        for (let i = 1; i <= page; i++) {
            pdf.setPage(i);
            pdf.setFontSize(8);
            pdf.setTextColor(150);
            pdf.text('SwiftAid Ambulance Service | +123-456-7890 | www.swiftaid.com', 10, pageHeight - 10);
        }

        // Save the PDF
        const filename = 'SwiftAid_User_Report_<?php echo $userid; ?>_' + new Date().toISOString().split('T')[0] + '.pdf';
        pdf.save(filename);

        // Clean up
        document.body.removeChild(pdfContainer);
        document.body.removeChild(loadingDiv);
    }).catch(error => {
        console.error('Error generating PDF:', error);
        alert('There was an error generating the PDF. Please try again.');
        document.body.removeChild(pdfContainer);
        document.body.removeChild(loadingDiv);
    });
}
</script>
</body>
</html>
<?php
$conn->close();
?>