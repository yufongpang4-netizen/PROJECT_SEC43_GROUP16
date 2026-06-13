<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: index.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();
// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// CONDITION: Evaluates `if(isset($_SESSION['user_id'])) ` so the application can choose the correct business rule branch for the current user action.
if(isset($_SESSION['user_id'])) {
    // SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
    if($_SESSION['role'] == 'staff') {
        // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
        header("Location: staff/dashboard_Staff.php");
    // SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
    // CONDITION: Evaluates `} elseif($_SESSION['role'] == 'finance') ` so the application can choose the correct business rule branch for the current user action.
    } elseif($_SESSION['role'] == 'finance') {
        // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
        header("Location: finance/dashboard_Finance.php");
    // SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
    // CONDITION: Evaluates `} elseif($_SESSION['role'] == 'admin') ` so the application can choose the correct business rule branch for the current user action.
    } elseif($_SESSION['role'] == 'admin') {
        // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
        header("Location: admin/dashboard_Admin.php");
    }
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTMSPACE - Staff Pay and Claim System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --utm-navy: #0B3B5E;
            --utm-red: #C1272D;
            --utm-gray: #475569;
            --utm-light: #F8FAFC;
            --utm-dark: #082c47;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Blurry Background */
        .landing-page { position: relative; min-height: 100vh; overflow-x: hidden; }
        
        /* WHY: The blurred background preserves institutional branding while keeping foreground forms readable. */
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
        
        /* WHY: The blurred background preserves institutional branding while keeping foreground forms readable. */
        .blurry-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 59, 94, 0.65);
        }
        
        /* WHY: The content wrapper centers the authentication or landing card over the background image. */
        .content-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .glass-card {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
        }
        
        /* === SECTION: RESPONSIVE BRAND LOGO === */
        /* What: Give the UTMSPACE logo stronger visual prominence on the public landing page. */
        /* Why: The institutional identity should be immediately recognizable without overwhelming the primary actions. */
        .utm-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .utmspace-logo-img {
            max-width: 230px;
            width: 100%;
            height: auto;
            margin-bottom: 10px;
            background: transparent;
        }

        /* === SECTION: MOBILE LOGO CONSTRAINT === */
        /* What: Reduce the enlarged logo on narrow screens while preserving its natural aspect ratio. */
        /* Why: Mobile users retain clear branding without the logo crowding headings or action controls. */
        @media (max-width: 576px) {
            .utmspace-logo-img {
                max-width: 180px;
            }
        }
        
        .logo-divider {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--utm-red), #ff4b52);
            margin: 10px auto 0;
            border-radius: 3px;
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
            padding: 20px 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        
        .feature-box:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(11, 59, 94, 0.1);
            background: white;
            border-color: var(--utm-navy);
        }
        
        /* Buttons */
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-primary-custom {
            background: var(--utm-navy);
            color: white;
            border: 2px solid var(--utm-navy);
            border-radius: 50px;
            padding: 10px 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(11, 59, 94, 0.2);
        }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-primary-custom:hover {
            background: var(--utm-dark);
            border-color: var(--utm-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(11, 59, 94, 0.4);
            color: white;
        }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-secondary-custom {
            background: transparent;
            border: 2px solid var(--utm-navy);
            color: var(--utm-navy);
            border-radius: 50px;
            padding: 10px 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-secondary-custom:hover {
            background: var(--utm-navy);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(11, 59, 94, 0.3);
        }
        
        /* SECTION: ANIMATION - Keyframes add subtle entrance motion to guide attention without changing business logic. */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        
        .role-badge {
            background: rgba(193, 39, 45, 0.08);
            border: 1px solid rgba(193, 39, 45, 0.2);
            border-radius: 30px;
            padding: 6px 20px;
            display: inline-block;
        }
        
        hr { border-color: rgba(11, 59, 94, 0.1); border-width: 2px; }
        
        .feature-icon {
            color: var(--utm-red);
            font-size: 28px;
            margin-bottom: 12px;
            transition: transform 0.3s ease;
        }
        
        .feature-box:hover .feature-icon {
            transform: scale(1.1);
        }
        
        .footer-icon {
            color: var(--utm-red);
            margin-right: 8px;
            font-size: 1em;
        }
    </style>
</head>
<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->
<body class="landing-page">
    <div class="blurry-bg"></div>
    
    <div class="content-wrapper">
        <div class="container w-100">
            <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
            <div class="row justify-content-center">
                <!-- BOOTSTRAP LAYOUT: col-md-9/col-lg-10 allocates the wider content area for tables, dashboards, and forms. -->
                <div class="col-lg-10 col-xl-9">
                    <div class="glass-card p-4 p-md-5 fade-in-up">
                        
                        <!-- === SECTION: VERSIONED BRAND LOGO === -->
                        <!-- What: Append the uploaded logo modification time to its URL so hosting and browser caches recognize each replacement. -->
                        <!-- Why: Visitors should immediately see the current UTMSPACE branding after the image is updated on InfinityFree. -->
                        <div class="utm-logo">
                            <img src="css/images/utmspace%20logo.png?v=<?php echo filemtime(__DIR__ . '/css/images/utmspace logo.png'); ?>" alt="UTMSPACE Logo" class="utmspace-logo-img" 
                                 style="background: transparent;"
                                 onerror="this.onerror=null; this.src='css/images/utm_space.jpeg';">
                            <div class="logo-divider"></div>
                        </div>
                        
                        <div class="text-center mb-3">
                            <div class="role-badge">
                                <i class="fas fa-receipt me-1" style="color: var(--utm-red);"></i>
                                <span style="color: var(--utm-navy); font-weight: 600;">Staff Pay and Claim System</span>
                            </div>
                        </div>
                        
                        <!-- === SECTION: OBJECTIVE SYSTEM INTRODUCTION === -->
                        <!-- What: Identify the application by its official project name and summarize its implemented workflows. -->
                        <!-- Why: Factual wording supports academic documentation requirements without making subjective quality claims. -->
                        <h2 class="h3 text-center mb-3 main-title">UTMSPACE Staff Pay and Claim System</h2>
                        <p class="text-center text-muted mb-4">Submit expense claims, monitor claim status, review records, and export reports through one role-based platform.</p>
                        
                        <div class="d-flex justify-content-center gap-3 mb-4 flex-wrap">
                            <a href="login.php" class="btn btn-primary-custom">
                                <i class="fas fa-sign-in-alt me-2"></i>Login Here
                            </a>
                            <a href="register.php" class="btn btn-secondary-custom">
                                <i class="fas fa-user-plus me-2"></i>Register Now
                            </a>
                        </div>
                        
                        <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                        <div class="row g-3 mt-2">
                            <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center h-100">
                                    <i class="fas fa-file-invoice-dollar feature-icon"></i>
                                    <p class="mb-1 fw-bold" style="color: var(--utm-navy); font-size: 14px;">Submit Claims</p>
                                    <small class="text-muted">With receipt uploads</small>
                                </div>
                            </div>
                            <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center h-100">
                                    <i class="fas fa-chart-line feature-icon"></i>
                                    <p class="mb-1 fw-bold" style="color: var(--utm-navy); font-size: 14px;">Track Status</p>
                                    <small class="text-muted">Pending to Paid</small>
                                </div>
                            </div>
                            <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center h-100">
                                    <i class="fas fa-chart-pie feature-icon"></i>
                                    <p class="mb-1 fw-bold" style="color: var(--utm-navy); font-size: 14px;">Dashboard</p>
                                    <small class="text-muted">Review & approve</small>
                                </div>
                            </div>
                            <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                            <div class="col-md-3 col-sm-6">
                                <div class="feature-box text-center h-100">
                                    <i class="fas fa-file-export feature-icon"></i>
                                    <p class="mb-1 fw-bold" style="color: var(--utm-navy); font-size: 14px;">Export Reports</p>
                                    <small class="text-muted">PDF & CSV formats</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                        <div class="row text-center">
                            <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                            <div class="col-md-4 mb-2 mb-md-0">
                                <span class="text-muted small">
                                    <i class="fas fa-file-circle-plus footer-icon"></i> Online claim submission
                                </span>
                            </div>
                            <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                            <div class="col-md-4 mb-2 mb-md-0">
                                <span class="text-muted small">
                                    <i class="fas fa-clipboard-check footer-icon"></i> Finance review workflow
                                </span>
                            </div>
                            <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                            <div class="col-md-4">
                                <span class="text-muted small">
                                    <i class="fas fa-user-shield footer-icon"></i> Role-based access control
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
