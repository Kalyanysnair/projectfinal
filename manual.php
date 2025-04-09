<?php
// Include database connection file instead of direct connection
include 'connect.php';

// Handle form submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $emergencyType = $_POST['emergencyType'];
        $tipContent = $_POST['tipContent'];
        $role = $_POST['role'] ?? null;
        $isPersonal = isset($_POST['isPersonal']) ? 1 : 0;
        
        $sql = "INSERT INTO tbl_tips (emergency_type, tip_content, user_role, is_personal, status) 
                VALUES (?, ?, ?, ?, 'pending')";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssi", $emergencyType, $tipContent, $role, $isPersonal);
        $stmt->execute();
        
        $tipId = $mysqli->insert_id;
        
        echo json_encode([
            'success' => true,
            'tip_id' => $tipId,
            'message' => 'Your tip has been submitted and is pending approval.'
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Fetch existing tips
try {
    $stmt = $mysqli->query("SELECT * FROM tbl_tips WHERE status = 'approved' ORDER BY created_at DESC");
    $tips = $stmt->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Handle database query error
    $tips = [];
    $error_message = $e->getMessage();
}

include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Tips</title>
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-image: url('assets/assets/img/template/Groovin/hero-carousel/ambulance2.jpg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        margin-top: 80px;
        padding: 0px;
    }

    .main-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        color: white;
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.7);
        margin-bottom: 1rem;
    }

    .unified-container {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        padding: 25px;
        border: 2px solid #2E8B57;
        position: relative;
        overflow: hidden;
    }

    .container-header {
        background: #2E8B57;
        color: white;
        padding: 15px 25px;
        margin: -25px -25px 20px -25px;
        border-radius: 13px 13px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .container-header h2 {
        margin: 0;
        font-size: 1.5rem;
    }

    .content-wrapper {
        display: flex;
        gap: 25px;
        justify-content: space-between;
    }

    .tips-section, .form-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        width: 48%;
        flex: 1;
    }

    .section-title {
        color: #2E8B57;
        font-size: 1.3rem;
        border-bottom: 2px solid #2E8B57;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .tip-card {
        background: #ffffff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .tip-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    }

    .tip-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .emergency-type {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2E8B57;
    }

    .tip-meta {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .tip-content {
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .tip-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 10px;
        border-top: 1px solid #e9ecef;
    }

    .btn {
        padding: 8px 16px;
        background-color: #2E8B57;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn:hover {
        background-color: #3CB371;
        transform: translateY(-1px);
    }

    .btn-primary {
        background-color: #2E8B57;
        width: 100%;
        padding: 10px;
        font-weight: 500;
    }

    .btn-action {
        background: transparent;
        color: #495057;
        border: 1px solid #ced4da;
        margin-left: 8px;
        padding: 4px 10px;
        font-size: 0.85rem;
    }

    .btn-outline-success {
        color: #2E8B57;
        border-color: #2E8B57;
    }

    .btn-outline-success:hover {
        background-color: #2E8B57;
        color: white;
    }

    .btn-outline-secondary:hover {
        background-color: #6c757d;
        color: white;
    }

    .form-control, .form-select {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 0.75rem;
        width: 100%;
        margin-bottom: 15px;
    }

    .form-control:focus, .form-select:focus {
        border-color: #2E8B57;
        box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
        outline: none;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        color: #495057;
        font-weight: 500;
    }

    .form-check {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .form-check-input {
        margin-right: 8px;
    }

    .role-badge {
        background: #e9ecef;
        color: #495057;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
    }

    .role-badge i {
        margin-right: 5px;
    }

    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: white;
        text-decoration: none;
        font-weight: 500;
        background-color: #2E8B57;
        padding: 8px 16px;
        border-radius: 4px;
        margin-bottom: 1rem;
        transition: all 0.2s;
    }

    .back-button:hover {
        background-color: #3CB371;
        color: white;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    .spinner-border {
        vertical-align: middle;
    }

    /* Added decorative elements */
    .corner-decoration {
        position: absolute;
        width: 80px;
        height: 80px;
        border-radius: 0;
    }

    .top-left {
        top: 0;
        left: 0;
        border-top: 4px solid #2E8B57;
        border-left: 4px solid #2E8B57;
    }

    .top-right {
        top: 0;
        right: 0;
        border-top: 4px solid #2E8B57;
        border-right: 4px solid #2E8B57;
    }

    .bottom-left {
        bottom: 0;
        left: 0;
        border-bottom: 4px solid #2E8B57;
        border-left: 4px solid #2E8B57;
    }

    .bottom-right {
        bottom: 0;
        right: 0;
        border-bottom: 4px solid #2E8B57;
        border-right: 4px solid #2E8B57;
    }

    /* Responsive styles */
    @media (max-width: 992px) {
        .main-container {
            padding: 1rem;
        }
    }

    @media (max-width: 768px) {
        .content-wrapper {
            flex-direction: column;
        }

        .tips-section, .form-section {
            width: 100%;
        }
        
        .unified-container {
            padding: 15px;
        }
        
        .container-header {
            margin: -15px -15px 15px -15px;
            padding: 10px 15px;
        }
    }
</style>
</head>
<body>

<div class="main-container">
    

    <!-- Unified container with outline -->
    <div class="unified-container">
        <!-- Corner decorations -->
        <div class="corner-decoration top-left"></div>
        <div class="corner-decoration top-right"></div>
        <div class="corner-decoration bottom-left"></div>
        <div class="corner-decoration bottom-right"></div>
        
        <!-- Container header -->
        <div class="container-header">
        <a href="user1.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        Back
    </a>
            <h2><i class="fas fa-heartbeat"></i> Emergency Tips Community</h2>
            
        </div>
        
        <!-- Content wrapper -->
        <div class="content-wrapper">
            <!-- Tips section -->
            <section class="tips-section">
                <h3 class="section-title"><i class="fas fa-list-ul"></i> Community Tips</h3>
                <div id="tipsContainer">
                    <?php if (empty($tips)): ?>
                        <div class="alert alert-info">No approved tips found. Be the first to contribute!</div>
                    <?php else: ?>
                        <?php foreach ($tips as $tip): ?>
                            <div class="tip-card" data-tip-id="<?php echo htmlspecialchars($tip['id']); ?>">
                                <div class="tip-header">
                                    <span class="emergency-type"><?php echo htmlspecialchars($tip['emergency_type']); ?></span>
                                    <span class="tip-meta"><?php echo date('M j, Y', strtotime($tip['created_at'])); ?></span>
                                </div>
                                <div class="tip-content">
                                    <?php echo nl2br(htmlspecialchars($tip['tip_content'])); ?>
                                </div>
                                <div class="tip-footer">
                                    <div class="user-info">
                                        <?php if (!empty($tip['user_role'])): ?>
                                            <span class="role-badge">
                                                <i class="fas fa-user-md"></i> <?php echo htmlspecialchars($tip['user_role']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- <div class="action-buttons">
                                        <button class="btn btn-action btn-outline-success">
                                            <i class="fas fa-heart"></i>
                                            <span><?php echo $tip['likes_count']; ?></span>
                                        </button>
                                        <button class="btn btn-action btn-outline-secondary">
                                            <i class="fas fa-comment"></i>
                                            <span><?php echo $tip['comments_count']; ?></span>
                                        </button>
                                    </div> -->
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Form section -->
            <section class="form-section">
                <h3 class="section-title"><i class="fas fa-share-alt"></i> Share Your Experience</h3>
                <div id="alertContainer"></div>
                <form id="tipForm">
                    <div class="mb-3">
                        <label class="form-label">Emergency Type</label>
                        <select class="form-select" name="emergencyType" required>
                            <option value="">Select type</option>
                            <option>Low Blood Pressure</option>
                            <option>Heart Attack</option>
                            <option>Choking</option>
                            <option>Bleeding</option>
                            <option>Burns</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Your Tip</label>
                        <textarea class="form-control" name="tipContent" rows="4" required 
                                  placeholder="Share what worked in this emergency situation..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Your Role (optional)</label>
                        <input type="text" name="role" class="form-control" 
                               placeholder="E.g. Paramedic, Nurse, First Responder">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="experienceCheck" name="isPersonal">
                        <label class="form-check-label" for="experienceCheck">
                            This is from personal experience
                        </label>
                    </div>
                    <button type="submit" id="submitBtn" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Tip
                    </button>
                </form>
            </section>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Form validation and AJAX submission
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('tipForm');
        const submitBtn = document.getElementById('submitBtn');
        const alertContainer = document.getElementById('alertContainer');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                e.stopPropagation();
                form.classList.add('was-validated');
                return;
            }
            
            // Disable button to prevent multiple submissions
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            
            // Collect form data
            const formData = new FormData(form);
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Clear previous alerts
                alertContainer.innerHTML = '';
                
                if (data.success) {
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="fas fa-check-circle"></i> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    alertContainer.appendChild(alertDiv);
                    
                    // Reset form
                    form.reset();
                    form.classList.remove('was-validated');
                    
                    // Auto-dismiss success message after 5 seconds
                    setTimeout(() => {
                        alertDiv.classList.remove('show');
                        setTimeout(() => alertDiv.remove(), 300);
                    }, 5000);
                } else {
                    // Show error message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-circle"></i> Error: ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    alertContainer.appendChild(alertDiv);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Show error alert
                alertContainer.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> An error occurred while submitting your tip. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Tip';
            });
        });
    });
</script>
</body>
</html>