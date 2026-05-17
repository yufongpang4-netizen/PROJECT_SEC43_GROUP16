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
        .landing-page { position: relative; min-height: 100vh; overflow-x: hidden; }
        
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
            filter: blur(12px);
            transform: scale(1.1);
            z-index: 0;
        }
        
        .blurry-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 59, 94, 0.65);
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
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
        }
        
        /* Logo Styles */
        .utm-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .utm-logo-img {
            max-width: 220px;
            height: auto;
            margin-bottom: 15px;
            filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.1));
        }
        
        .logo-divider {
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--utm-red), #ff4b52);
            margin: 0 auto;
            border-radius: 4px;
        }
        
        .main-title {
            color: var(--utm-navy);
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        /* Feature Boxes */
        .feature-box {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 25px 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        
        .feature-box:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(11, 59, 94, 0.1);
            background: white;
            border-color: var(--utm-navy);
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: var(--utm-navy);
            color: white;
            border: 2px solid var(--utm-navy);
            border-radius: 50px;
            padding: 12px 40px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(11, 59, 94, 0.2);
        }
        
        .btn-primary-custom:hover {
            background: var(--utm-dark);
            border-color: var(--utm-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(11, 59, 94, 0.4);
            color: white;
        }
        
        .btn-secondary-custom {
            background: transparent;
            border: 2px solid var(--utm-navy);
            color: var(--utm-navy);
            border-radius: 50px;
            padding: 12px 40px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-secondary-custom:hover {
            background: var(--utm-navy);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(11, 59, 94, 0.3);
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        
        .role-badge {
            background: rgba(193, 39, 45, 0.08);
            border: 1px solid rgba(193, 39, 45, 0.2);
            border-radius: 30px;
            padding: 8px 25px;
            display: inline-block;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        
        hr { border-color: rgba(11, 59, 94, 0.1); border-width: 2px; }
        
        .feature-icon {
            color: var(--utm-red);
            font-size: 32px;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }
        
        .feature-box:hover .feature-icon {
            transform: scale(1.1);
        }
        
        .footer-icon {
            color: var(--utm-red);
            margin-right: 8px;
            font-size: 1.1em;
        }
    </style>
</head>
<body class="landing-page">
    <div class="blurry-bg"></div>
    
    <div class="content-wrapper">
        <div class="container w-100">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-9"> <div class="glass-card p-4 p-md-5 fade-in-up"> <div class="utm-logo">
                            <img src="css/images/utm-logo.png" alt="UTMSPACE Logo" class="utm-logo-img" 
                                 onerror="this.src='css/images/utm_space1.jpg'">
                            <div class="logo-divider"></div>
                        </div>
                        
                        <div class="text-center mb-4">
                            <div class="role-badge">
                                <i class="fas fa-receipt me-1" style="color: var(--utm-red);"></i>
                                <span style="color: var(--utm-navy); font-weight: 600;">Staff Pay and Claim System</span>
                            </div>
                        </div>
                        
                        <h2 class="h2 text-center mb-3 main-title">Efficient Claim Management System</h2>
                        <p class="text-center text-muted mb-5 fs-5">Submit, track, and manage your expense claims with ease and transparency</p>
                        
                        <div class="d-flex justify-content-center gap-3 mb-5 flex-wrap">
                            <a href="login.php" class="btn btn-primary-custom">
                                <i class="fas fa-sign-in-alt me-2"></i>Login Here
                            </a>
                            <a href="register.php" class="btn btn-secondary-custom">
                                <i class="fas fa-user-plus me-2"></i>Register Now
                            </a>
                        </div>
                        
                        <div class="row g-4 mt-2">
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-file-invoice-dollar feature-icon"></i>
                                    <p class="mb-1 fw-bold fs-6" style="color: var(--utm-navy);">Submit Claims</p>
                                    <small class="text-muted">With receipt uploads</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-chart-line feature-icon"></i>
                                    <p class="mb-1 fw-bold fs-6" style="color: var(--utm-navy);">Track Status</p>
                                    <small class="text-muted">Pending to Paid</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-chart-pie feature-icon"></i>
                                    <p class="mb-1 fw-bold fs-6" style="color: var(--utm-navy);">Dashboard</p>
                                    <small class="text-muted">Review & approve</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-file-export feature-icon"></i>
                                    <p class="mb-1 fw-bold fs-6" style="color: var(--utm-navy);">Export Reports</p>
                                    <small class="text-muted">PDF & CSV formats</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-5">
                        
                        <div class="row text-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <span class="text-muted fw-medium">
                                    <i class="fas fa-bolt footer-icon"></i> Easy submission
                                </span>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <span class="text-muted fw-medium">
                                    <i class="fas fa-check-double footer-icon"></i> Fast approval
                                </span>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted fw-medium">
                                    <i class="fas fa-shield-alt footer-icon"></i> Secure system
                                </span>
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