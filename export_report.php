<?php
session_start();
require 'connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Function to get all the data
function getReportData($conn) {
    $data = [];
    
    // Get total users and drivers
    $data['total_users'] = $conn->query("SELECT COUNT(*) as count FROM tbl_user")->fetch_assoc()['count'];
    $data['total_drivers'] = $conn->query("SELECT COUNT(*) as count FROM tbl_driver")->fetch_assoc()['count'];
    
    // Get total income
    $data['total_income'] = $conn->query("SELECT SUM(amount) as total FROM tbl_payments WHERE payment_status = 'completed'")->fetch_assoc()['total'] ?? 0;
    
    // Get service requests
    $data['service_requests'] = [
        'prebookings' => $conn->query("SELECT COUNT(*) as count FROM tbl_prebooking")->fetch_assoc()['count'],
        'emergency' => $conn->query("SELECT COUNT(*) as count FROM tbl_emergency")->fetch_assoc()['count'],
        'palliative' => $conn->query("SELECT COUNT(*) as count FROM tbl_palliative")->fetch_assoc()['count']
    ];
    
    // Get reviews
    $data['reviews'] = [
        'total' => $conn->query("SELECT COUNT(*) as count FROM tbl_review")->fetch_assoc()['count'],
        'average' => $conn->query("SELECT AVG(rating) as avg FROM tbl_review")->fetch_assoc()['avg'] ?? 0
    ];
    
    return $data;
}

$data = getReportData($conn);

// Set headers for PDF download
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="swiftaid_report_' . date('Y-m-d') . '.html"');
header('Cache-Control: max-age=0');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SwiftAid Admin Dashboard Report</title>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
        @media print {
            body {
                width: 297mm;
                height: 210mm;
                margin: 0;
                padding: 20mm;
                font-size: 12pt;
            }
            .no-print {
                display: none;
            }
            .page-break {
                page-break-before: always;
            }
            .chart-container {
                height: 400px !important;
                width: 100% !important;
                page-break-inside: avoid;
            }
            .content-wrapper {
                background: rgba(255, 255, 255, 0.95) !important;
            }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            color: #333;
            min-height: 100vh;
            background: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: -1;
        }
        .content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4CAF50;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: rgba(255, 255, 255, 0.9);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: rgba(249, 249, 249, 0.9);
        }
        h1, h2 {
            color: #4CAF50;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            page-break-inside: avoid;
        }
        .metric {
            margin: 15px 0;
            padding: 10px;
            background: rgba(245, 245, 245, 0.9);
            border-radius: 4px;
        }
        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin: 20px 0;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <div class="header">
            <div class="logo">SwiftAid</div>
            <h1>Admin Dashboard Report</h1>
            <div class="subtitle">Generated on: <?php echo date('F d, Y'); ?></div>
        </div>

        <div class="section">
            <h2>Overview</h2>
            <div class="metric">
                <strong>Total Users:</strong>
                <span class="metric-value"><?php echo number_format($data['total_users']); ?></span>
            </div>
            <div class="metric">
                <strong>Total Drivers:</strong>
                <span class="metric-value"><?php echo number_format($data['total_drivers']); ?></span>
            </div>
            <div class="metric">
                <strong>Total Revenue:</strong>
                <span class="metric-value">₹<?php echo number_format($data['total_income'], 2); ?></span>
            </div>
        </div>

        <div class="section">
            <h2>Service Requests</h2>
            <div class="chart-container">
                <canvas id="serviceRequestsChart"></canvas>
            </div>
            <table>
                <tr>
                    <th>Service Type</th>
                    <th>Count</th>
                </tr>
                <tr>
                    <td>Pre-bookings</td>
                    <td><?php echo number_format($data['service_requests']['prebookings']); ?></td>
                </tr>
                <tr>
                    <td>Emergency</td>
                    <td><?php echo number_format($data['service_requests']['emergency']); ?></td>
                </tr>
                <tr>
                    <td>Palliative</td>
                    <td><?php echo number_format($data['service_requests']['palliative']); ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>Customer Reviews</h2>
            <div class="metric">
                <strong>Total Reviews:</strong>
                <span class="metric-value"><?php echo number_format($data['reviews']['total']); ?></span>
            </div>
            <div class="metric">
                <strong>Average Rating:</strong>
                <span class="metric-value"><?php echo number_format($data['reviews']['average'], 1); ?>/5</span>
            </div>
        </div>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> SwiftAid. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Service Requests Chart
        new Chart(document.getElementById('serviceRequestsChart'), {
            type: 'bar',
            data: {
                labels: ['Pre-bookings', 'Emergency', 'Palliative'],
                datasets: [{
                    label: 'Number of Requests',
                    data: [
                        <?php echo $data['service_requests']['prebookings']; ?>,
                        <?php echo $data['service_requests']['emergency']; ?>,
                        <?php echo $data['service_requests']['palliative']; ?>
                    ],
                    backgroundColor: '#4CAF50',
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Auto-trigger print dialog when the page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html> 