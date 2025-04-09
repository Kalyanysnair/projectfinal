<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'connect.php';

// Add the missing executeQuery function
function executeQuery($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        error_log("Query error: " . $conn->error . " in query: " . $query);
        return false;
    }
    return $result;
}

// Function to get all service receipts
function getServiceReceipts($conn) {
    $query = "SELECT 
        'prebooking' as service_type,
        p.id,
        p.name,
        p.payment_status,
        p.amount,
        p.booking_date,
        p.pickup_location,
        p.drop_location,
        p.service_date,
        p.phone
    FROM tbl_prebooking p
    WHERE p.payment_status = 'Paid'
    UNION ALL
    SELECT 
        'emergency' as service_type,
        e.id,
        e.name,
        e.payment_status,
        e.amount,
        e.booking_date,
        e.pickup_location,
        e.drop_location,
        e.service_date,
        e.phone
    FROM tbl_emergency e
    WHERE e.payment_status = 'Paid'
    UNION ALL
    SELECT 
        'palliative' as service_type,
        pl.id,
        pl.name,
        pl.payment_status,
        pl.amount,
        pl.booking_date,
        pl.pickup_location,
        pl.drop_location,
        pl.service_date,
        pl.phone
    FROM tbl_palliative pl
    WHERE pl.payment_status = 'Paid'
    ORDER BY booking_date DESC";

    return executeQuery($conn, $query);
}

// Add function to generate receipt content
function generateReceiptHTML($receipt) {
    $receiptHTML = '
    <div class="receipt-container" style="padding: 20px; max-width: 800px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 20px;">
            <h2>Service Receipt</h2>
            <p>Receipt #' . $receipt['id'] . '</p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h4>Customer Details</h4>
            <p>Name: ' . $receipt['name'] . '</p>
            <p>Phone: ' . $receipt['phone'] . '</p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h4>Service Details</h4>
            <p>Service Type: ' . ucfirst($receipt['service_type']) . '</p>
            <p>Pickup Location: ' . $receipt['pickup_location'] . '</p>
            <p>Drop Location: ' . $receipt['drop_location'] . '</p>
            <p>Service Date: ' . date('F d, Y', strtotime($receipt['service_date'])) . '</p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h4>Payment Details</h4>
            <p>Amount: $' . number_format($receipt['amount'], 2) . '</p>
            <p>Status: ' . $receipt['payment_status'] . '</p>
            <p>Booking Date: ' . date('F d, Y', strtotime($receipt['booking_date'])) . '</p>
        </div>
    </div>';
    
    return $receiptHTML;
}

// Add download receipt handler
if (isset($_GET['download']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $type = $_GET['type'];
    
    // Get receipt details
    $query = "SELECT * FROM tbl_" . $type . " WHERE id = " . $id;
    $result = executeQuery($conn, $query);
    if ($result && $row = $result->fetch_assoc()) {
        $receiptHTML = generateReceiptHTML($row);
        
        // Generate PDF (requires TCPDF or similar library)
        require_once('tcpdf/tcpdf.php');
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->writeHTML($receiptHTML);
        $pdf->Output('receipt_' . $id . '.pdf', 'D');
        exit;
    }
}

$receipts = getServiceReceipts($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Receipts - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Updated sidebar styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 250px;
            background-color: rgba(83, 223, 78, 0.9);
            padding-top: 20px;
            transition: all 0.3s;
            z-index: 999;
            backdrop-filter: blur(10px);
        }

        .sidebar-brand {
            color: white;
            text-align: center;
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 15px;
        }

        .sidebar-nav {
            padding: 0;
            list-style: none;
        }

        .sidebar-nav li a {
            color: white;
            opacity: 0.8;
            text-decoration: none;
            font-size: 1rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            padding: 10px 15px;
        }

        .sidebar-nav li a:hover {
            opacity: 1;
            padding-left: 20px;
            background-color: rgba(255,255,255,0.1);
        }

        .sidebar-nav li.active {
            background-color: rgba(255,255,255,0.2);
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }

        /* Receipt card styles */
        .receipt-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }

        .receipt-card:hover {
            transform: translateY(-5px);
        }

        .receipt-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            background-color: #f8f9fc;
            border-radius: 8px 8px 0 0;
        }

        .receipt-body {
            padding: 15px;
        }

        .receipt-footer {
            padding: 15px;
            background-color: #f8f9fc;
            border-radius: 0 0 8px 8px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .status-paid {
            background-color: #1cc88a;
            color: white;
        }

        .status-pending {
            background-color: #f6c23e;
            color: white;
        }

        /* Search and filter section */
        .search-filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h3>Admin Panel</h3>
        </div>
        <ul class="sidebar-nav">
            <li>
                <a href="admin_stat.php">
                    <i class="fas fa-chart-line"></i>
                    Statistics
                </a>
            </li>
            <li class="active">
                <a href="admin_receipts.php">
                    <i class="fas fa-receipt"></i>
                    Service Receipts
                </a>
            </li>
            <li>
                <a href="admin_users.php">
                    <i class="fas fa-users"></i>
                    User Reports
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Service Receipts</h2>
            
            <!-- Search and Filter -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchReceipt" placeholder="Search by name or ID...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterService">
                        <option value="">All Services</option>
                        <option value="prebooking">Pre-booking</option>
                        <option value="emergency">Emergency</option>
                        <option value="palliative">Palliative</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>
            </div>

            <!-- Receipts List -->
            <div class="row">
                <?php if ($receipts && $receipts->num_rows > 0): ?>
                    <?php while($receipt = $receipts->fetch_assoc()): ?>
                        <div class="col-md-6">
                            <div class="receipt-card">
                                <div class="receipt-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Receipt #<?php echo $receipt['id']; ?></h5>
                                    <span class="status-badge <?php echo $receipt['payment_status'] == 'Paid' ? 'status-paid' : 'status-pending'; ?>">
                                        <?php echo $receipt['payment_status']; ?>
                                    </span>
                                </div>
                                <div class="receipt-body">
                                    <p><strong>Service:</strong> <?php echo ucfirst($receipt['service_type']); ?></p>
                                    <p><strong>Customer:</strong> <?php echo $receipt['name']; ?></p>
                                    <p><strong>Pickup:</strong> <?php echo $receipt['pickup_location']; ?></p>
                                    <p><strong>Drop:</strong> <?php echo $receipt['drop_location']; ?></p>
                                    <p><strong>Amount:</strong> $<?php echo number_format($receipt['amount'], 2); ?></p>
                                    <p><strong>Service Date:</strong> <?php echo date('F d, Y', strtotime($receipt['service_date'])); ?></p>
                                </div>
                                <div class="receipt-footer text-end">
                                    <a href="admin_receipts.php?download=true&id=<?php echo $receipt['id']; ?>&type=<?php echo $receipt['service_type']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-download"></i> Download Receipt
                                    </a>
                                    <button class="btn btn-sm btn-secondary ms-2" onclick="previewReceipt(<?php echo htmlspecialchars(json_encode($receipt)); ?>)">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p>No receipts found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add receipt preview modal -->
    <div class="modal fade" id="receiptPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receipt Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptPreviewContent">
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewReceipt(receipt) {
            const previewHTML = generateReceiptHTML(receipt);
            document.getElementById('receiptPreviewContent').innerHTML = previewHTML;
            new bootstrap.Modal(document.getElementById('receiptPreviewModal')).show();
        }

        function generateReceiptHTML(receipt) {
            return `
                <div class="receipt-preview">
                    <div class="text-center mb-4">
                        <h3>Service Receipt</h3>
                        <p class="text-muted">Receipt #${receipt.id}</p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Customer Details</h5>
                        <p>Name: ${receipt.name}</p>
                        <p>Phone: ${receipt.phone}</p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Service Details</h5>
                        <p>Service Type: ${receipt.service_type.charAt(0).toUpperCase() + receipt.service_type.slice(1)}</p>
                        <p>Pickup Location: ${receipt.pickup_location}</p>
                        <p>Drop Location: ${receipt.drop_location}</p>
                        <p>Service Date: ${new Date(receipt.service_date).toLocaleDateString()}</p>
                    </div>
                    
                    <div>
                        <h5>Payment Details</h5>
                        <p>Amount: $${parseFloat(receipt.amount).toFixed(2)}</p>
                        <p>Status: ${receipt.payment_status}</p>
                        <p>Booking Date: ${new Date(receipt.booking_date).toLocaleDateString()}</p>
                    </div>
                </div>
            `;
        }

        // Add search and filter functionality
        $(document).ready(function() {
            $('#searchReceipt').on('keyup', function() {
                // Implement search functionality
            });

            $('#filterService, #filterStatus').on('change', function() {
                // Implement filter functionality
            });
        });
    </script>
</body>
</html> 