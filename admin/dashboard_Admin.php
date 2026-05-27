<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: dashboard_Admin.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// === SECTION 1: SECURITY & CONFIGURATION ===
// Start the session to access user variables (e.g., user_id, role)
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();

// Security Check: Ensure the user is logged in AND has the 'admin' role.
// If they are a normal staff or not logged in, redirect them back to the login page immediately.
// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
// CONDITION: Evaluates `if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') ` so the application can choose the correct business rule branch for the current user action.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: ../login.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}

// Connect to the database
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once '../db.php';

// === SECTION 2: FETCH LIVE STATISTICS FOR CARDS ===
// 1. Fetch total counts for Staff, Admin, and All Users
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$total_staff   = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='staff'")->fetch_assoc()['c'];
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$total_admin   = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch_assoc()['c'];
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$total_users   = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];

// 2. Fetch Claim Statistics (with safety check)
$total_claims = 0;
$total_paid   = 0;
$pending_count = 0;

// Check if the 'claims' table exists before querying to prevent SQL errors on a fresh setup
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
// BEST PRACTICE: Checking for the claims table prevents dashboard errors on a newly imported database.
$claims_exist = $conn->query("SHOW TABLES LIKE 'claims'")->num_rows > 0;

// CONDITION: Evaluates `if ($claims_exist) ` so the application can choose the correct business rule branch for the current user action.
if ($claims_exist) {
    // Count total claims regardless of status
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    // WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
    $total_claims  = $conn->query("SELECT COUNT(*) AS c FROM claims")->fetch_assoc()['c'];
    // Calculate the sum of all claims that have been successfully 'Paid'
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    // WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
    $total_paid    = $conn->query("SELECT IFNULL(SUM(amount),0) AS s FROM claims WHERE status='Paid'")->fetch_assoc()['s'];
    // Count claims that are currently waiting for approval ('Pending')
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    // WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
    $pending_count = $conn->query("SELECT COUNT(*) AS c FROM claims WHERE status='Pending'")->fetch_assoc()['c'];
}

// === SECTION 3: FETCH DATA FOR TABLES & LISTS ===
// Group users by their department to see which department has the most users
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$dept_res = $conn->query("
    SELECT department, COUNT(*) AS cnt
    FROM users
    WHERE department IS NOT NULL AND department != ''
    GROUP BY department
    ORDER BY cnt DESC
");

// Fetch the 5 most recently registered users to display in the "Recent Registrations" list
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$recent_users = $conn->query("
    SELECT name, role, department, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");

// === SECTION 4: PREPARE DATA FOR CHARTS ===
// Initialize empty arrays to hold chart labels (X-axis) and data (Y-axis)
$trend_labels = [];
$trend_data = [];
$dept_pie_labels = [];
$dept_pie_data = [];

// CONDITION: Evaluates `if ($claims_exist) ` so the application can choose the correct business rule branch for the current user action.
if ($claims_exist) {
    // 1. Monthly Trend Data (Line Chart)
    // Get the total paid amount grouped by month (e.g., 'Jan 2026') for the last 6 months
    $trend_sql = "SELECT DATE_FORMAT(submitted_at, '%b %Y') as month_name, SUM(amount) as total
                  FROM claims
                  WHERE status = 'Paid'
                  GROUP BY DATE_FORMAT(submitted_at, '%Y-%m'), month_name
                  ORDER BY DATE_FORMAT(submitted_at, '%Y-%m') DESC LIMIT 6";
    // WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
    $trend_res = $conn->query($trend_sql);

    $temp_labels = [];
    $temp_data = [];
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    while ($row = $trend_res->fetch_assoc()) {
        $temp_labels[] = $row['month_name'];
        // BEST PRACTICE: Converting claim amounts to numeric values ensures financial limit checks use numeric comparison.
        $temp_data[] = floatval($row['total']);
    }

    // The SQL query orders from newest to oldest month. 
    // We must reverse the arrays so the line chart reads naturally from left (oldest) to right (newest).
    $trend_labels = array_reverse($temp_labels);
    $trend_data = array_reverse($temp_data);

    // 2. Department Cost Data (Doughnut Chart)
    // Join the claims and users tables to calculate how much money was paid to each department
    $pie_sql = "SELECT u.department, SUM(c.amount) as total_cost
                FROM claims c
                JOIN users u ON c.user_id = u.id
                WHERE u.department IS NOT NULL AND u.department != '' 
                AND c.status = 'Paid'
                GROUP BY u.department";
    // WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
    $pie_res = $conn->query($pie_sql);
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    while ($row = $pie_res->fetch_assoc()) {
        $dept_pie_labels[] = $row['department'];
        // BEST PRACTICE: Converting claim amounts to numeric values ensures financial limit checks use numeric comparison.
        $dept_pie_data[] = floatval($row['total_cost']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* CSS styling variables for consistent theme colors */
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --admin-primary: #2e1065;
            --admin-secondary: #4c1d95;
            --admin-accent: #8b5cf6;
            --admin-bg: #faf5ff;
            --admin-card: #ffffff;
            --admin-text: #2e1065;
            --admin-gray: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body {
            background: var(--admin-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* BOOTSTRAP LAYOUT: The full-width container lets dashboard pages use the complete viewport for side navigation plus content. */
        .container-fluid {
            height: 100%;
            overflow: hidden;
        }

        /* BOOTSTRAP LAYOUT: The zero-gutter row removes unwanted spacing between the sidebar and the main workspace. */
        .row.g-0 {
            height: 100%;
        }

        /* Sidebar Styling */
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar {
            background: linear-gradient(180deg, #2e1065 0%, #4c1d95 100%);
            height: 100vh;
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
            position: sticky;
            top: 0;
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 5px;
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link:hover {
            background: rgba(139, 92, 246, 0.2);
            color: #8b5cf6;
            transform: translateX(5px);
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link.active {
            background: #8b5cf6;
            color: #2e1065;
            font-weight: 600;
        }

        /* Main Content Styling */
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }

        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar {
            width: 8px;
        }

        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-track {
            background: #e5e7eb;
            border-radius: 10px;
        }

        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-thumb {
            background: #8b5cf6;
            border-radius: 10px;
        }

        /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
        .page-header {
            background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }

        /* Top Statistics Cards */
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            border: 1px solid #f3e8ff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            cursor: pointer;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-staff:hover {
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            border-color: #8b5cf6;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-staff:hover .stat-icon {
            background: #8b5cf6;
            color: white;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-claims:hover {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #f59e0b;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-claims:hover .stat-icon {
            background: #f59e0b;
            color: white;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-paid:hover {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-color: #10b981;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-paid:hover .stat-icon {
            background: #10b981;
            color: white;
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #8b5cf6;
            margin: 0 auto 15px;
            transition: all 0.3s ease;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #2e1065;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }

        .badge-pending {
            background: #fef3c7;
            color: #d97706;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
        }

        /* Info Cards (Charts & Lists) */
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .info-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .card-title {
            color: #2e1065;
            font-size: 18px;
            font-weight: 600;
        }

        .dept-table {
            margin-bottom: 0;
        }

        .dept-table td {
            padding: 10px 0;
            border-bottom: 1px solid #f3e8ff;
        }

        .dept-badge {
            background: #8b5cf6;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .user-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .user-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f3e8ff;
        }

        .role-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .role-staff {
            background: #10b981;
            color: white;
        }

        .role-finance {
            background: #3b82f6;
            color: white;
        }

        .role-admin {
            background: #ef4444;
            color: white;
        }

        .user-meta {
            font-size: 11px;
            color: #6b7280;
        }

        /* WHY: The chart container reserves stable space so Chart.js canvases render without layout shift. */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        /* SECTION: ANIMATION - Keyframes add subtle entrance motion to guide attention without changing business logic. */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        hr {
            border-color: #f3e8ff;
        }

        /* SECTION: RESPONSIVE RULES - These rules adapt sidebars, cards, and tables for smaller screens. */
        @media (max-width: 768px) {
            /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
            .sidebar {
                height: auto;
                position: relative;
            }

            /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
            .main-content {
                height: auto;
                overflow-y: visible;
            }

            .stat-number {
                font-size: 22px;
            }
        }
    </style>
</head>

<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->
<body>
    <!-- BOOTSTRAP LAYOUT: container-fluid spans the full browser width so dashboards can use the complete workspace. -->
    <div class="container-fluid p-0">
        <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
        <div class="row g-0">

            <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-shield fs-1" style="color: #8b5cf6;"></i>
                        <h5 class="mt-2">UTMSPACE</h5>
                        <small>Admin Portal</small>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <!-- SECTION: ROLE NAVIGATION - Provides role-specific movement between the pages allowed for the current user. -->
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard_Admin.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard</a>
                        <a class="nav-link" href="Manage_User_Admin.php"><i class="fas fa-users fa-fw me-2"></i> Manage Accounts</a>
                        <a class="nav-link" href="Manage_Claims_Admin.php"><i class="fas fa-file-invoice-dollar fa-fw me-2"></i> Manage Claims</a>
                        <a class="nav-link" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar fa-fw me-2"></i> Generate Report</a>
                        <hr style="border-color: rgba(255,255,255,0.2);">
                        <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout</a>
                    </nav>
                </div>
            </div>

            <!-- BOOTSTRAP LAYOUT: col-md-9/col-lg-10 allocates the wider content area for tables, dashboards, and forms. -->
            <div class="col-md-9 col-lg-10 main-content">

                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="mb-1"><i class="fas fa-tachometer-alt me-2" style="color: #8b5cf6;"></i>Admin Dashboard</h3>
                            <p class="mb-0 opacity-75">Overview of system activity and user statistics</p>
                        </div>
                        <div class="mt-2 mt-sm-0">
                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </div>
                    </div>
                </div>

                <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                <div class="row g-4 mb-4 fade-in">
                    <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                    <div class="col-md-4 col-sm-12">
                        <a href="Manage_User_Admin.php" class="text-decoration-none">
                            <div class="stat-card stat-card-staff text-center">
                                <div class="stat-icon mx-auto"><i class="fas fa-users"></i></div>
                                <div class="stat-number"><?php echo $total_staff; ?></div>
                                <div class="stat-label">Total Staff <i class="fas fa-external-link-alt ms-1 small"></i></div>
                            </div>
                        </a>
                    </div>
                    <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                    <div class="col-md-4 col-sm-12">
                        <a href="Manage_Claims_Admin.php" class="text-decoration-none">
                            <div class="stat-card stat-card-claims text-center">
                                <div class="stat-icon mx-auto"><i class="fas fa-file-invoice"></i></div>
                                <div class="stat-number">
                                    <?php echo $total_claims; ?>
                                    <!-- CONDITION: Evaluates `if ($pending_count > 0)` so the application can choose the correct business rule branch for the current user action. -->
                                    <?php if ($pending_count > 0): ?>
                                        <span class="badge-pending"><?php echo $pending_count; ?> pending</span>
                                    <?php endif; ?>
                                </div>
                                <div class="stat-label">Total Claims <i class="fas fa-external-link-alt ms-1 small"></i></div>
                            </div>
                        </a>
                    </div>
                    <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                    <div class="col-md-4 col-sm-12">
                        <div class="stat-card stat-card-paid text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-dollar-sign"></i></div>
                            <div class="stat-number">RM <?php echo number_format($total_paid, 2); ?></div>
                            <div class="stat-label">Total Paid</div>
                        </div>
                    </div>
                </div>

                <!-- CONDITION: Evaluates `if ($claims_exist && !empty($trend_labels))` so the application can choose the correct business rule branch for the current user action. -->
                <?php if ($claims_exist && !empty($trend_labels)): ?>
                    <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                    <div class="row g-4 mb-4 fade-in">
                        <div class="col-md-7">
                            <div class="info-card h-100">
                                <div class="card-body p-4">
                                    <h5 class="card-title"><i class="fas fa-chart-line me-2" style="color: #8b5cf6;"></i>Monthly Paid Trend</h5>
                                    <hr>
                                    <div class="chart-container">
                                        <canvas id="trendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="info-card h-100">
                                <div class="card-body p-4">
                                    <h5 class="card-title"><i class="fas fa-chart-pie me-2" style="color: #8b5cf6;"></i>Paid Cost by Department</h5>
                                    <hr>
                                    <div class="chart-container">
                                        <canvas id="deptChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                <?php else: ?>
                    <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                    <div class="row g-4 mb-4 fade-in">
                        <div class="col-12">
                            <div class="info-card text-center p-5">
                                <i class="fas fa-chart-bar fa-3x mb-3" style="color: #e5e7eb;"></i>
                                <h5 class="text-muted">Not enough Paid claims to generate charts</h5>
                                <p class="text-muted small">Approve and pay some claims in the Finance portal to see the trends here.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                <div class="row g-4 mb-4 fade-in">
                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                    <div class="col-md-6">
                        <div class="info-card h-100">
                            <div class="card-body p-4">
                                <h5 class="card-title"><i class="fas fa-building me-2" style="color: #8b5cf6;"></i>Users by Department</h5>
                                <hr>
                                <!-- CONDITION: Evaluates `if ($dept_res && $dept_res->num_rows > 0)` so the application can choose the correct business rule branch for the current user action. -->
                                <?php if ($dept_res && $dept_res->num_rows > 0): ?>
                                    <!-- SECTION: DATA TABLE - Presents database records in an examiner-friendly format for review, audit, or reporting. -->
                                    <table class="dept-table table w-100">
                                        <tbody>
                                            <?php while ($d = $dept_res->fetch_assoc()): ?>
                                                <tr>
                                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                    <td><?php echo htmlspecialchars($d['department']); ?></td>
                                                    <td class="text-end"><span class="dept-badge"><?php echo $d['cnt']; ?> users</span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                                <?php else: ?>
                                    <p class="text-muted mb-0">No department data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                    <div class="col-md-6">
                        <div class="info-card h-100">
                            <div class="card-body p-4">
                                <h5 class="card-title"><i class="fas fa-user-plus me-2" style="color: #8b5cf6;"></i>Recent Registrations</h5>
                                <hr>
                                <!-- CONDITION: Evaluates `if ($recent_users && $recent_users->num_rows > 0)` so the application can choose the correct business rule branch for the current user action. -->
                                <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                                    <ul class="user-list">
                                        <?php while ($u = $recent_users->fetch_assoc()):
                                            // Dynamically assign badge colors based on user role
                                            // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
                                            $role_class = match ($u['role']) {
                                                'finance' => 'role-finance',
                                                'admin' => 'role-admin',
                                                default => 'role-staff'
                                            };
                                        ?>
                                            <li>
                                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                    <div>
                                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                        <span class="user-name"><?php echo htmlspecialchars($u['name']); ?></span>
                                                        <span class="role-badge <?php echo $role_class; ?> ms-2"><?php echo ucfirst($u['role']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="user-meta mt-1">
                                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($u['department'] ?? '—'); ?>
                                                    <span class="mx-1">•</span>
                                                    <i class="fas fa-calendar me-1"></i> <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                                                </div>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                                <?php else: ?>
                                    <p class="text-muted mb-0">No users registered yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="height: 20px;"></div>
            </div>
        </div>
    </div>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- CONDITION: Evaluates `if ($claims_exist && !empty($trend_labels))` so the application can choose the correct business rule branch for the current user action. -->
    <?php if ($claims_exist && !empty($trend_labels)): ?>
        <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
        <script>
            // Wait for the HTML DOM to load fully before executing the script
            // SECTION: DOM READY HANDLER - Runs UI logic only after page elements exist, preventing null references.
            document.addEventListener("DOMContentLoaded", function() {

                // --- 1. Line Chart: Monthly Trend ---
                const trendCtx = document.getElementById('trendChart').getContext('2d');
                // SECTION: CHART.JS CONFIGURATION - Converts database aggregates into visual analytics for the Admin dashboard.
                new Chart(trendCtx, {
                    // CHART.JS: A line chart is used because monthly paid totals are time-series data.
                    type: 'line',
                    data: {
                        // Use json_encode to securely pass PHP arrays to JavaScript variables
                        // SECURITY: json_encode() safely passes PHP values into JavaScript without manual string building.
                        labels: <?php echo json_encode($trend_labels); ?>,
                        // CHART.JS: datasets define the measured business values that appear in the chart.
                        datasets: [{
                            label: 'Total Paid (RM)',
                            // SECURITY: json_encode() safely passes PHP values into JavaScript without manual string building.
                            data: <?php echo json_encode($trend_data); ?>,
                            // CHART.JS: Color and point styling align the visualization with the portal theme.
                            borderColor: '#8b5cf6',
                            // CHART.JS: Color and point styling align the visualization with the portal theme.
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            borderWidth: 3,
                            // CHART.JS: Color and point styling align the visualization with the portal theme.
                            pointBackgroundColor: '#ffffff',
                            // CHART.JS: Color and point styling align the visualization with the portal theme.
                            pointBorderColor: '#8b5cf6',
                            // CHART.JS: Color and point styling align the visualization with the portal theme.
                            pointRadius: 5,
                            // CHART.JS: Color and point styling align the visualization with the portal theme.
                            pointHoverRadius: 7,
                            fill: true,
                            // CHART.JS: tension smooths the line to make month-to-month trends easier to follow.
                            tension: 0.4 // Makes the line curve smoothly instead of sharp angles
                        }]
                    },
                    options: {
                        // CHART.JS: responsive mode lets the chart resize with the dashboard card.
                        responsive: true,
                        // CHART.JS: Disabling aspect ratio lets CSS-controlled card height determine chart space.
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            // Custom tooltip to format the number with 'RM' prefix
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'RM ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                // CHART.JS: Starting the Y-axis at zero avoids exaggerating financial differences.
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });

                // --- 2. Doughnut Chart: Department Distribution ---
                const deptCtx = document.getElementById('deptChart').getContext('2d');
                // SECTION: CHART.JS CONFIGURATION - Converts database aggregates into visual analytics for the Admin dashboard.
                new Chart(deptCtx, {
                    // CHART.JS: A doughnut chart is used to show each department as a share of total paid claim cost.
                    type: 'doughnut',
                    data: {
                        // SECURITY: json_encode() safely passes PHP values into JavaScript without manual string building.
                        labels: <?php echo json_encode($dept_pie_labels); ?>,
                        // CHART.JS: datasets define the measured business values that appear in the chart.
                        datasets: [{
                            // SECURITY: json_encode() safely passes PHP values into JavaScript without manual string building.
                            data: <?php echo json_encode($dept_pie_data); ?>,
                            // Provide a visually distinct color palette for different departments
                            // CHART.JS: Color and point styling align the visualization with the portal theme.
                            backgroundColor: ['#8b5cf6', '#a78bfa', '#c4b5fd', '#5BC0BE', '#10b981', '#f59e0b', '#ef4444'],
                            borderWidth: 0,
                            hoverOffset: 5
                        }]
                    },
                    options: {
                        // CHART.JS: responsive mode lets the chart resize with the dashboard card.
                        responsive: true,
                        // CHART.JS: Disabling aspect ratio lets CSS-controlled card height determine chart space.
                        maintainAspectRatio: false,
                        plugins: {
                            // Position the legend on the right side for better readability
                            legend: {
                                position: 'right',
                                labels: {
                                    // CHART.JS: Color and point styling align the visualization with the portal theme.
                                    usePointStyle: true,
                                    padding: 15,
                                    boxWidth: 10
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return ' RM ' + context.parsed;
                                    }
                                }
                            }
                        },
                        // CHART.JS: cutout controls ring thickness so department shares remain readable.
                        cutout: '70%' // Determines how thick the doughnut ring is
                    }
                });
            });
        </script>
    <?php endif; ?>
</body>

</html>