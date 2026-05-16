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
    <style>
        :root {
            --utm-navy: #0B3B5E;
            --utm-red: #C1272D;
            --utm-gray: #475569;
            --utm-light: #F8FAFC;
            --utm-dark: #082c47;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Blurry Background */
        .landing-page { position: relative; min-height: 100vh; }
        
        .blurry-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('css/images/utm.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(8px);
            transform: scale(1.05);
            z-index: 0;
        }
        
        .blurry-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 59, 94, 0.75);
        }
        
        .content-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        /* Logo Styles */
        .utm-logo {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(193, 39, 45, 0.15);
        }
        
        .utm-logo-img {
            max-width: 180px;
            height: auto;
            margin-bottom: 10px;
        }
        
        .logo-divider {
            width: 60px;
            height: 3px;
            background: var(--utm-red);
            margin: 12px auto 0;
            border-radius: 3px;
        }
        
        .feature-box {
            transition: all 0.3s ease;
            cursor: pointer;
            background: var(--utm-light);
            border-radius: 15px;
            padding: 20px;
        }
        
        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            background: white;
        }
        
        .btn-primary-custom {
            background: var(--utm-navy);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 35px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            background: var(--utm-red);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(193, 39, 45, 0.4);
            color: white;
        }
        
        .btn-secondary-custom {
            background: transparent;
            border: 2px solid var(--utm-navy);
            color: var(--utm-navy);
            border-radius: 50px;
            padding: 12px 35px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary-custom:hover {
            background: var(--utm-navy);
            color: white;
            transform: translateY(-2px);
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in-up { animation: fadeInUp 0.6s ease-out; }
        
        .role-badge {
            background: rgba(193, 39, 45, 0.1);
            border-radius: 30px;
            padding: 8px 20px;
            display: inline-block;
        }
        
        hr { border-color: #e2e8f0; }
        
        .feature-icon {
            color: var(--utm-red);
            font-size: 28px;
            margin-bottom: 12px;
        }
        
        .footer-icon {
            color: var(--utm-red);
            margin-right: 6px;
        }
    </style>
</head>
<body class="landing-page">
    <div class="blurry-bg"></div>
    
    <div class="content-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="glass-card p-5 fade-in-up">
                        <!-- UTMSPACE Logo Image -->
                        <div class="utm-logo">
                            <img src="css/images/utm-logo.png" alt="UTMSPACE Logo" class="utm-logo-img" 
                                 onerror="this.src='css/images/utm space1.jpg'">
                            <div class="logo-divider"></div>
                        </div>
                        
                        <div class="text-center mb-4">
                            <div class="role-badge">
                                <i class="fas fa-receipt" style="color: var(--utm-red);"></i>
                                <span style="color: var(--utm-navy); font-weight: 500;"> Staff Pay and Claim System</span>
                            </div>
                        </div>
                        
                        <h2 class="h3 text-center mb-3" style="color: var(--utm-navy);">Efficient Claim Management System</h2>
                        <p class="text-center text-muted mb-5">Submit, track, and manage your expense claims with ease and transparency</p>
                        
                        <div class="d-flex justify-content-center gap-3 mb-5 flex-wrap">
                            <a href="login.php" class="btn btn-primary-custom">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                            <a href="register.php" class="btn btn-secondary-custom">
                                <i class="fas fa-user-plus me-2"></i>Register Now
                            </a>
                        </div>
                        
                        <div class="row g-4 mt-2">
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center">
                                    <i class="fas fa-file-invoice-dollar feature-icon"></i>
                                    <p class="mb-0 fw-bold" style="color: var(--utm-navy);">Submit Claims</p>
                                    <small class="text-muted">With receipt uploads</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center">
                                    <i class="fas fa-chart-line feature-icon"></i>
                                    <p class="mb-0 fw-bold" style="color: var(--utm-navy);">Track Status</p>
                                    <small class="text-muted">Pending → Paid</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center">
                                    <i class="fas fa-chart-pie feature-icon"></i>
                                    <p class="mb-0 fw-bold" style="color: var(--utm-navy);">Finance Dashboard</p>
                                    <small class="text-muted">Review & approve</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center">
                                    <i class="fas fa-chart-bar feature-icon"></i>
                                    <p class="mb-0 fw-bold" style="color: var(--utm-navy);">Reports</p>
                                    <small class="text-muted">By department</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-5">
                        
                        <div class="row text-center">
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-check-circle footer-icon"></i> Easy submission
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-clock footer-icon"></i> Fast approval
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-lock footer-icon"></i> Secure system
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