<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'connect.php';

// Ensure only admin can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = isset($_POST["payment_type"]) ? $_POST["payment_type"] : 'all';
    $statusFilter = isset($_POST["payment_status"]) ? $_POST["payment_status"] : 'all';
    $searchTerm = isset($_POST["search"]) ? $_POST["search"] : '';
    
    $html = '';
    $tables = [];
    
    // Determine which tables to query based on payment_type
    if ($type == 'emergency' || $type == 'all') {
        $tables[] = ['table' => 'tbl_emergency', 'id_column' => 'request_id', 'type' => 'Emergency'];
    }
    
    if ($type == 'prebooking' || $type == 'all') {
        $tables[] = ['table' => 'tbl_prebooking', 'id_column' => 'prebookingid', 'type' => 'Prebooking'];
    }
    
    if ($type == 'palliative' || $type == 'all') {
        $tables[] = ['table' => 'tbl_palliative', 'id_column' => 'palliativeid', 'type' => 'Palliative'];
    }
    
    $allResults = [];
    
    foreach ($tables as $tableInfo) {
        $table = $tableInfo['table'];
        $id_column = $tableInfo['id_column'];
        $service_type = $tableInfo['type'];
        
        // Build the SQL query with prepared statements for security
        $sql = "SELECT t.$id_column, '$service_type' AS service_type, u.username, u.phoneno, 
                t.pickup_location, t.amount, t.payment_status, t.created_at 
                FROM $table t
                JOIN tbl_user u ON t.userid = u.userid
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Add payment status filter if not "all"
        if ($statusFilter != "all") {
            $sql .= " AND t.payment_status = ?";
            $params[] = $statusFilter;
            $types .= "s";
        }
        
        // Add search term if provided
        if (!empty($searchTerm)) {
            $sql .= " AND (u.username LIKE ? OR u.phoneno LIKE ? OR t.pickup_location LIKE ? OR t.$id_column LIKE ?)";
            $searchParam = "%$searchTerm%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ssss";
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters if there are any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Add table info to each row for the update button
            $row['_table'] = $table;
            $row['_id_column'] = $id_column;
            $allResults[] = $row;
        }
        
        $stmt->close();
    }
    
    // Sort all results by created_at date (most recent first)
    usort($allResults, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Generate HTML output
    foreach ($allResults as $row) {
        $html .= "<tr>
                    <td>{$row[$row['_id_column']]}</td>
                    <td><span class='badge " . getBadgeClass($row['service_type']) . "'>{$row['service_type']}</span></td>
                    <td>{$row['username']}</td>
                    <td>{$row['phoneno']}</td>
                    <td>" . htmlspecialchars($row['pickup_location']) . "</td>
                    <td>₹" . number_format($row['amount'] ?? 0, 2) . "</td>
                    <td><span class='status-badge status-" . ucfirst($row['payment_status']) . "'>
                        {$row['payment_status']}
                    </span></td>
                    <td>" . date('d M Y, H:i A', strtotime($row['created_at'])) . "</td>
                    <td>
                        <button class='btn btn-sm " . ($row['payment_status'] == 'Pending' ? 'btn-success' : 'btn-warning') . " update-status action-btn'
                                data-id='{$row[$row['_id_column']]}'
                                data-table='{$row['_table']}'
                                data-status='{$row['payment_status']}'>
                            <i class='fas " . ($row['payment_status'] == 'Pending' ? 'fa-check-circle' : 'fa-clock') . "'></i> 
                            " . ($row['payment_status'] == 'Pending' ? 'Mark Paid' : 'Mark Pending') . "
                        </button>
                        <a href='view_payment_details.php?type=" . strtolower($row['service_type']) . "&id={$row[$row['_id_column']]}' 
                           class='btn btn-info btn-sm action-btn'>
                            <i class='fas fa-eye'></i> View
                        </a>
                    </td>
                  </tr>";
    }
    
    echo json_encode(['success' => true, 'data' => $html]);
    exit();
}

function getBadgeClass($serviceType) {
    switch ($serviceType) {
        case 'Emergency':
            return 'bg-danger';
        case 'Prebooking':
            return 'bg-primary';
        case 'Palliative':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}
?>