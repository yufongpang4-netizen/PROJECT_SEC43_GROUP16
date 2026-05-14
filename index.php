<?php
session_start();
if(isset($_SESSION['user_id'])) {
    if($_SESSION['role'] == 'staff') {
        header("Location: staff/dashboard_Staff.php");
    } elseif($_SESSION['role'] == 'finance') {
        header("Location: finance/dashboard_Finance.php");
    } elseif($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard_Admin.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTMSpace - Staff Pay and Claim System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background: linear-gradient(135deg, #0B132B 0%, #1C2541 50%, #3A506B 100%); min-height: 100vh;">

    <nav class="navbar navbar-expand-lg" style="background-color: #0B132B; border-bottom: 3px solid #5BC0BE;">
        <div class="container">
            <a class="navbar-brand text-white" href="index.php">
                <i class="fas fa-coins me-2" style="color: #5BC0BE;"></i>
                <strong>UTMSpace</strong> | Pay & Claim
            </a>
        </div>
    </nav>
    
    <div class="container d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 76px);">
        <div class="row justify-content-center w-100">
            <div class="col-lg-8 text-center">
                <div class="card border-0 shadow-lg" style="background: rgba(255,255,255,0.95); border-radius: 20px;">
                    <div class="card-body p-5">
                        <div class="mb-4">
                            <i class="fas fa-receipt" style="font-size: 60px; color: #5BC0BE;"></i>
                        </div>
                        <h1 class="display-4 fw-bold" style="color: #0B132B;">UTMSpace</h1>
                        <h2 class="h3 mb-4" style="color: #3A506B;">Staff Pay and Claim System</h2>
                        <p class="lead mb-5" style="color: #6c757d;">Submit, track, and manage your expense claims efficiently</p>
                        
                        <div class="d-flex justify-content-center gap-3 mb-5">
                            <a href="login.php" class="btn btn-lg px-4" style="background: #5BC0BE; color: white; border-radius: 50px;">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                            <a href="register.php" class="btn btn-lg px-4" style="background: #3A506B; color: white; border-radius: 50px;">
                                <i class="fas fa-user-plus me-2"></i>Register as Staff
                            </a>
                        </div>
                        
                        <div class="row g-4 mt-3">
                            <div class="col-md-3">
                                <div class="feature-box p-3 rounded h-100" style="background: #f8f9fa;">
                                    <i class="fas fa-file-invoice-dollar fs-2 mb-2" style="color: #5BC0BE;"></i>
                                    <p class="mb-0 fw-bold" style="color: #0B132B;">Submit Claims</p>
                                    <small style="color: #3A506B;">With receipts</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="feature-box p-3 rounded h-100" style="background: #f8f9fa;">
                                    <i class="fas fa-chart-line fs-2 mb-2" style="color: #5BC0BE;"></i>
                                    <p class="mb-0 fw-bold" style="color: #0B132B;">Track Status</p>
                                    <small style="color: #3A506B;">Pending → Paid</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="feature-box p-3 rounded h-100" style="background: #f8f9fa;">
                                    <i class="fas fa-chart-pie fs-2 mb-2" style="color: #5BC0BE;"></i>
                                    <p class="mb-0 fw-bold" style="color: #0B132B;">Finance Dashboard</p>
                                    <small style="color: #3A506B;">Manage claims</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="feature-box p-3 rounded h-100" style="background: #f8f9fa;">
                                    <i class="fas fa-chart-bar fs-2 mb-2" style="color: #5BC0BE;"></i>
                                    <p class="mb-0 fw-bold" style="color: #0B132B;">Reports</p>
                                    <small style="color: #3A506B;">By department</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>