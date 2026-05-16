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
    <title>UTMSPACE - Staff Pay and Claim System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Blurry Background */
        .landing-page {
            position: relative;
            min-height: 100vh;
        }
        
        .blurry-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* CORRECT PATH - images folder is INSIDE css folder */
            background-image: url('css/images/utm.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(8px);
            transform: scale(1.05);
            z-index: 0;
        }
        
        .blurry-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 19, 43, 0.65);
        }
        
        .content-wrapper {
            position: relative;
            z-index: 1;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .feature-box {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .btn-custom-primary {
            background: linear-gradient(135deg, #5BC0BE 0%, #3a9e9c 100%);
            color: #0B132B;
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-custom-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(91, 192, 190, 0.4);
            color: #0B132B;
        }
        
        .btn-custom-secondary {
            background: #3A506B;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-custom-secondary:hover {
            background: #2d3f55;
            transform: translateY(-2px);
            color: white;
        }
        
        .utm-logo h1 {
            font-size: 42px;
            font-weight: 800;
            color: #0B132B;
            letter-spacing: 3px;
            margin-bottom: 5px;
        }
        
        .utm-logo .estd {
            color: #5BC0BE;
            font-size: 11px;
            letter-spacing: 5px;
            font-weight: 500;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
    </style>
</head>
<body class="landing-page">
    <!-- Blurry Background Image -->
    <div class="blurry-bg"></div>
    
    <!-- Content -->
    <div class="content-wrapper">
        <nav class="navbar navbar-expand-lg" style="background: rgba(11, 19, 43, 0.95); backdrop-filter: blur(10px); border-bottom: 3px solid #5BC0BE;">
            <div class="container">
                <a class="navbar-brand text-white" href="index.php">
                    <i class="fas fa-coins me-2" style="color: #5BC0BE;"></i>
                    <strong>UTMSPACE</strong> | Pay & Claim
                </a>
            </div>
        </nav>
        
        <div class="container d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 76px); padding: 40px 0;">
            <div class="row justify-content-center w-100">
                <div class="col-lg-10 text-center">
                    <div class="glass-card p-5 fade-in-up">
                        <div class="utm-logo">
                            <h1>UTMSPACE</h1>
                            <p class="estd">ESTD 1993</p>
                        </div>
                        
                        <div class="mb-4">
                            <span style="background: rgba(91, 192, 190, 0.15); border-radius: 30px; padding: 8px 20px; display: inline-block;">
                                <i class="fas fa-receipt" style="color: #5BC0BE;"></i>
                                <span style="color: #0B132B; font-weight: 500;"> Staff Pay and Claim System</span>
                            </span>
                        </div>
                        
                        <h2 class="h3 mb-4" style="color: #3A506B;">Efficient Claim Management System</h2>
                        <p class="lead mb-5" style="color: #6c757d;">Submit, track, and manage your expense claims with ease and transparency</p>
                        
                        <div class="d-flex justify-content-center gap-3 mb-5 flex-wrap">
                            <a href="login.php" class="btn btn-custom-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                            <a href="register.php" class="btn btn-custom-secondary">
                                <i class="fas fa-user-plus me-2"></i>Register Now
                            </a>
                        </div>
                        
                        <div class="row g-4 mt-3">
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box p-3 rounded h-100" style="background: #f8f9fa;">
                                    <i class="fas fa-file-invoice-dollar fs-2 mb-2" style="color: #5BC0BE;"></i>
                                    <p class="mb-0 fw-bold" style="color: #0B132B;">Submit Claims</p>
                                    <small style="color: #3A506B;">With receipt uploads</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box p-3 rounded h-100" style="background: #f8f9fa;">
                                    <i class="fas fa-chart-line fs-2 mb-2" style="color: #5BC0BE;"></i>
                                    <p class="mb-0 fw-bold" style="color: #0B132B;">Track Status</p>
                                    <small style="color: #3A506B;">Pending → Paid</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box p-3 rounded h-100" style="background: #f8f9fa;">
                                    <i class="fas fa-chart-pie fs-2 mb-2" style="color: #5BC0BE;"></i>
                                    <p class="mb-0 fw-bold" style="color: #0B132B;">Finance Dashboard</p>
                                    <small style="color: #3A506B;">Review & approve</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box p-3 rounded h-100" style="background: #f8f9fa;">
                                    <i class="fas fa-chart-bar fs-2 mb-2" style="color: #5BC0BE;"></i>
                                    <p class="mb-0 fw-bold" style="color: #0B132B;">Reports</p>
                                    <small style="color: #3A506B;">By department</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-5">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-check-circle" style="color: #5BC0BE;"></i> Easy submission
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-clock" style="color: #5BC0BE;"></i> Fast approval
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-lock" style="color: #5BC0BE;"></i> Secure system
                                </small>
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