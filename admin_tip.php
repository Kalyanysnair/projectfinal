<?php
// Include database connection
include 'connect.php';

// Handle tip status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['tip_id'])) {
    $tip_id = (int)$_POST['tip_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $mysqli->prepare("UPDATE tbl_tips SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $tip_id);
        $stmt->execute();
        $status_message = "Tip #$tip_id has been approved successfully.";
        $status_type = "success";
    } elseif ($action === 'reject') {
        $stmt = $mysqli->prepare("UPDATE tbl_tips SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $tip_id);
        $stmt->execute();
        $status_message = "Tip #$tip_id has been rejected.";
        $status_type = "danger";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$valid_statuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Fetch tips based on filter
try {
    if ($status_filter === 'all') {
        $stmt = $mysqli->query("SELECT * FROM tbl_tips ORDER BY created_at DESC");
    } else {
        $stmt = $mysqli->prepare("SELECT * FROM tbl_tips WHERE status = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $status_filter);
        $stmt->execute();
        $stmt = $stmt->get_result();
    }
    
    $tips = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Get counts for each status
    $count_pending = $mysqli->query("SELECT COUNT(*) as count FROM tbl_tips WHERE status = 'pending'")->fetch_assoc()['count'];
    $count_approved = $mysqli->query("SELECT COUNT(*) as count FROM tbl_tips WHERE status = 'approved'")->fetch_assoc()['count'];
    $count_rejected = $mysqli->query("SELECT COUNT(*) as count FROM tbl_tips WHERE status = 'rejected'")->fetch_assoc()['count'];
    $count_all = $count_pending + $count_approved + $count_rejected;
    
} catch (Exception $e) {
    $tips = [];
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SwiftAid Emergency Tips</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary:rgb(25, 184, 1);  /* Changed to green */
            --secondary: #6c757d;
            --success:rgb(25, 135, 41);
            --danger: #dc3545;
            --warning: #ffc107;
            --info:rgb(100, 240, 13);
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Main content wrapper */
        .content-wrapper {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 15px;
        }

        /* Updated back button style */
        .back-button {
            background: #198754;
            border: none;
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .back-button:hover {
            background: #157347;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: rgba(25, 135, 84, 0.1);
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        /* Filter Tabs */
        .status-filters {
            background: white;
            border-radius: 12px;
            padding: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .status-filters .nav-tabs {
            border: none;
            gap: 0.5rem;
            padding: 0.5rem;
        }
        
        .status-filters .nav-link {
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            color: var(--dark);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .status-filters .nav-link:hover {
            background: rgba(25, 135, 84, 0.05);
        }
        
        .status-filters .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        /* Tip Cards */
        .tip-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            background: white;
        }
        
        .tip-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .tip-card .card-header {
            background: rgba(25, 135, 84, 0.05);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px 12px 0 0;
            padding: 1.25rem;
        }
        
        .tip-content {
            background: rgba(0,0,0,0.02);
            border-radius: 8px;
            padding: 1.25rem;
            margin: 1rem 0;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .action-buttons .btn {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-approve {
            background: var(--success);
            border: none;
        }
        
        .btn-approve:hover {
            background:rgb(15, 215, 19);
            transform: translateY(-1px);
        }
        
        .btn-reject {
            background: var(--danger);
            border: none;
        }
        
        .btn-reject:hover {
            background: #bb2d3b;
            transform: translateY(-1px);
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        /* Meta Information */
        .meta-info {
            font-size: 0.9rem;
            color: var(--secondary);
            padding: 0.5rem 0;
        }
        
        .meta-info i {
            margin-right: 0.25rem;
        }
        
        /* Empty State */
        .no-tips-message {
            background: white;
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .no-tips-message i {
            font-size: 4rem;
            color: var(--primary);
            opacity: 0.5;
        }
        
        /* Footer */
        footer {
            margin-top: auto;
            background: white;
            padding: 1.5rem 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .status-filters .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            
            .tip-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <header class="dashboard-header py-4 mb-4">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="admin.php" class="back-button">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </header>

        <div class="container mb-5">
            <?php if (isset($status_message)): ?>
                <div class="alert alert-<?php echo $status_type; ?> alert-dismissible fade show mb-4" role="alert">
                    <i class="bi <?php echo $status_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($status_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Dashboard Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card bg-white">
                        <div class="stat-icon text-primary">
                            <i class="bi bi-database"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $count_all; ?></h3>
                        <p class="stat-label">Total Tips</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-white">
                        <div class="stat-icon text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $count_pending; ?></h3>
                        <p class="stat-label">Pending Tips</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-white">
                        <div class="stat-icon text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $count_approved; ?></h3>
                        <p class="stat-label">Approved Tips</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-white">
                        <div class="stat-icon text-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $count_rejected; ?></h3>
                        <p class="stat-label">Rejected Tips</p>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="card mb-4">
                <div class="card-body p-0">
                    <nav class="status-filters">
                        <div class="nav nav-tabs">
                            <a class="nav-link <?php echo $status_filter === 'all' ? 'active' : ''; ?>" href="?status=all">
                                All Tips <span class="badge bg-secondary ms-1"><?php echo $count_all; ?></span>
                            </a>
                            <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="?status=pending">
                                Pending <span class="badge bg-warning text-dark ms-1"><?php echo $count_pending; ?></span>
                            </a>
                            <a class="nav-link <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" href="?status=approved">
                                Approved <span class="badge bg-success ms-1"><?php echo $count_approved; ?></span>
                            </a>
                            <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" href="?status=rejected">
                                Rejected <span class="badge bg-danger ms-1"><?php echo $count_rejected; ?></span>
                            </a>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Tips List -->
            <?php if (empty($tips)): ?>
                <div class="no-tips-message">
                    <i class="bi bi-inbox text-secondary" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">No <?php echo $status_filter !== 'all' ? $status_filter : ''; ?> tips found</h4>
                    <p class="text-muted">There are currently no tips with this status.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($tips as $tip): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card tip-card <?php echo $tip['status']; ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($tip['emergency_type']); ?></h5>
                                    <span class="status-badge <?php 
                                        if ($tip['status'] === 'pending') echo 'bg-warning text-dark';
                                        elseif ($tip['status'] === 'approved') echo 'bg-success';
                                        else echo 'bg-danger';
                                    ?>">
                                        <?php echo ucfirst($tip['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="tip-content">
                                        <?php echo nl2br(htmlspecialchars($tip['tip_content'])); ?>
                                    </div>
                                    
                                    <div class="meta-info d-flex flex-wrap justify-content-between align-items-center mb-3">
                                        <div>
                                            <?php if (!empty($tip['user_role'])): ?>
                                                <span class="me-3"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($tip['user_role']); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($tip['is_personal'] == 1): ?>
                                                <span class="badge personal-badge me-2">Personal Experience</span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- <div class="d-flex align-items-center">
                                            <span class="me-3"><i class="bi bi-heart-fill text-danger"></i> <?php echo $tip['likes_count']; ?></span>
                                            <span><i class="bi bi-chat-fill text-primary"></i> <?php echo $tip['comments_count']; ?></span>
                                        </div>
                                    </div> -->
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> <?php echo date('M j, Y g:i A', strtotime($tip['created_at'])); ?>
                                            <span class="ms-2">#<?php echo $tip['id']; ?></span>
                                        </small>
                                        
                                        <div class="action-buttons">
                                            <?php if ($tip['status'] !== 'approved'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="tip_id" value="<?php echo $tip['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-approve btn-sm text-white">
                                                        <i class="bi bi-check-circle me-1"></i> Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($tip['status'] !== 'rejected'): ?>
                                                <form method="POST" class="d-inline ms-2">
                                                    <input type="hidden" name="tip_id" value="<?php echo $tip['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-reject btn-sm text-white">
                                                        <i class="bi bi-x-circle me-1"></i> Reject
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>