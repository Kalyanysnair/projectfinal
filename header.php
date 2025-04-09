<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize default values
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Tips | SwiftAid Ambulance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>

<header id="header" class="header d-flex align-items-center fixed-top">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
        <!-- Left side - Logo -->
        <a href="" class="logo d-flex align-items-center">
            <img src="assets/img/SWIFTAID2.png" alt="SWIFTAID Logo" style="height: 70px; margin-right: 10px;">
            <h1 class="sitename">SWIFTAID</h1>
        </a>

        <!-- Right side - Username with icon -->
        <?php if (!empty($username)): ?>
            <div class="user-info d-flex align-items-center">
                <i class="fas fa-user me-1"></i>
                <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            </div>
        <?php endif; ?>
    </div>
</header>

<style>
    .user-info {
        background: rgba(255, 255, 255, 0.1);
        padding: 6px 12px;
        border-radius: 20px;
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
        margin-right: -15px;
        display: flex;
        align-items: center;
        width: auto;
        min-width: 100px; /* Set minimum width */
        max-width: 150px; /* Set maximum width */
    }

    .user-info:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .user-info i {
        color: white;
        font-size: 0.8rem;
        opacity: 0.9;
    }

    .user-name {
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Adjust the container to ensure proper spacing */
    .container-fluid {
        padding: 0 30px 0 25px;
    }

    /* Make sure the logo doesn't overflow */
    .logo {
        flex-shrink: 0;
    }

    /* Add subtle animation to icon on hover */
    .user-info:hover i {
        transform: scale(1.1);
        transition: transform 0.3s ease;
    }
</style>

</body>
</html>
