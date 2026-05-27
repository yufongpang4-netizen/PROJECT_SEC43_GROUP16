<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: dashboard_Finance.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();
// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
// CONDITION: Evaluates `if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') ` so the application can choose the correct business rule branch for the current user action.
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: ../login.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}
 
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once '../db.php';
 
// Get stats from real database
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$total     = $conn->query("SELECT COUNT(*) as c FROM claims")->fetch_assoc()['c'];
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$pending   = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Pending'")->fetch_assoc()['c'];
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$approved  = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Approved'")->fetch_assoc()['c'];
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$paid      = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Paid'")->fetch_assoc()['c'];
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$rejected  = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Rejected'")->fetch_assoc()['c'];
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$cancelled = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Cancelled'")->fetch_assoc()['c'];
 
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$recent_result = $conn->query("
    SELECT c.id, u.name, c.amount, c.status, c.claim_type, c.submitted_at
    FROM claims c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.submitted_at DESC
    LIMIT 6
");
// WHY: fetch_all(MYSQLI_ASSOC) collects every result row for table rendering and report generation.
$recent_claims = $recent_result->fetch_all(MYSQLI_ASSOC);
 
// Total amount pending payment (approved but not paid)
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$pending_amount = $conn->query("SELECT SUM(amount) as total FROM claims WHERE status='Approved'")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* FINANCE - DARK GREEN THEME WITH LIGHT BACKGROUND */
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --finance-primary: #064e3b;
            --finance-secondary: #047857;
            --finance-accent: #10b981;
            --finance-bg: #ecfdf5;
            --finance-card: #ffffff;
            --finance-text: #064e3b;
            --finance-gray: #6b7280;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        html, body { height: 100%; margin: 0; padding: 0; }
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body { background: var(--finance-bg); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        
        /* BOOTSTRAP LAYOUT: The full-width container lets dashboard pages use the complete viewport for side navigation plus content. */
        .container-fluid { height: 100%; overflow: hidden; }
        /* BOOTSTRAP LAYOUT: The zero-gutter row removes unwanted spacing between the sidebar and the main workspace. */
        .row.g-0 { height: 100%; }
        
        /* Sidebar */
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar { background: linear-gradient(180deg, #064e3b 0%, #047857 100%); height: 100vh; color: white; transition: all 0.3s ease; overflow-y: auto; position: sticky; top: 0; }
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar { width: 5px; }
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 5px; }
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.85); padding: 12px 20px; margin: 5px 0; border-radius: 10px; transition: all 0.3s ease; }
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link:hover { background: rgba(16, 185, 129, 0.2); color: #10b981; transform: translateX(5px); }
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link.active { background: #10b981; color: #064e3b; font-weight: 600; }
        
        /* Main Content */
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content { height: 100vh; overflow-y: auto; padding: 20px; }
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar { width: 8px; }
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-thumb { background: #10b981; border-radius: 10px; } 
        
        /* Page Header */
        /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
        .page-header { background: linear-gradient(135deg, #064e3b 0%, #047857 100%); border-radius: 20px; padding: 20px 25px; color: white; margin-bottom: 25px; }
        
        /* Stats Cards with Hover Colors */
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card { background: white; border-radius: 20px; padding: 20px; transition: all 0.3s ease; border: 1px solid #d1fae5; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03); cursor: pointer; height: 100%; }
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); }
        
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-total:hover { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-color: #10b981; }
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-total:hover .stat-icon { background: #10b981; color: white; }
        
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-pending:hover { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-color: #f59e0b; }
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-pending:hover .stat-icon { background: #f59e0b; color: white; }
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-pending:hover .stat-number { color: #b45309; }
        
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-approved:hover { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-color: #3b82f6; }
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-approved:hover .stat-icon { background: #3b82f6; color: white; }
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-approved:hover .stat-number { color: #1e40af; }
        
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-paid:hover { background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-color: #8b5cf6; }
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-paid:hover .stat-icon { background: #8b5cf6; color: white; }
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-card-paid:hover .stat-number { color: #6d28d9; }
        
        .stat-icon { width: 55px; height: 55px; background: rgba(16, 185, 129, 0.1); border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #10b981; margin: 0 auto 15px; transition: all 0.3s ease; }
        .stat-number { font-size: 28px; font-weight: 700; color: #064e3b; margin-bottom: 5px; transition: all 0.3s ease; }
        .stat-label { color: #6b7280; font-size: 14px; font-weight: 500; transition: all 0.3s ease; }
        
        /* Alert Banner */
        .payment-alert { background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border-left: 4px solid #f59e0b; border-radius: 15px; padding: 15px 20px; margin-bottom: 25px; color: #92400e; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        
        /* Action & Summary Cards */
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .action-card, .summary-card, .recent-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); transition: all 0.3s ease; }
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .action-card:hover, .summary-card:hover, .recent-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); }
        
        /* Buttons */
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-primary-custom { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s ease; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4); color: white; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-secondary-custom { background: #f1f5f9; color: #064e3b; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s ease; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-secondary-custom:hover { background: #e2e8f0; transform: translateY(-2px); color: #064e3b; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-dark-custom { background: #1f2937; color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s ease; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-dark-custom:hover { background: #374151; transform: translateY(-2px); color: white; }
        
        /* Status Badges */
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-pending { background: #fef3c7; color: #d97706; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-approved { background: #d1fae5; color: #059669; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-paid { background: #dbeafe; color: #2563eb; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-rejected { background: #fee2e2; color: #dc2626; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-cancelled { background: #e5e7eb; color: #4b5563; }
        
        /* Summary Items */
        .summary-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
        .summary-item:last-child { border-bottom: none; }
        .summary-label { display: flex; align-items: center; gap: 10px; }
        .summary-value { font-weight: 700; color: #064e3b; font-size: 18px; }
        
        /* Table */
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom { margin-bottom: 0; }
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom th { color: #064e3b; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding: 12px 15px; }
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom td { vertical-align: middle; padding: 12px 15px; }
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom tr:hover { background: #f8fafc; }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-view { background: #10b981; color: white; border-radius: 8px; padding: 5px 12px; font-size: 12px; transition: all 0.3s ease; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-view:hover { background: #059669; transform: translateY(-2px); color: white; }
        
        .badge-number { background: #ef4444; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 5px; }
        
        /* SECTION: ANIMATION - Keyframes add subtle entrance motion to guide attention without changing business logic. */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        hr { border-color: #e5e7eb; }
        
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        /* SECTION: RESPONSIVE RULES - These rules adapt sidebars, cards, and tables for smaller screens. */
        @media (max-width: 768px) { .sidebar { height: auto; position: relative; } }
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
                        <i class="fas fa-chart-line fs-1" style="color: #10b981;"></i>
                        <h5 class="mt-2">UTMSPACE</h5>
                        <small>Finance Portal</small>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <!-- SECTION: ROLE NAVIGATION - Provides role-specific movement between the pages allowed for the current user. -->
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard_Finance.php">
                            <i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="All_Claim_Finance.php">
                            <i class="fas fa-file-invoice fa-fw me-2"></i> All Claims
                        </a>
                        <a class="nav-link" href="Export_Report_Finance.php">
                            <i class="fas fa-download fa-fw me-2"></i> Export Report
                        </a>
                        <hr style="border-color: rgba(255,255,255,0.2);">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>
 
            <!-- BOOTSTRAP LAYOUT: col-md-9/col-lg-10 allocates the wider content area for tables, dashboards, and forms. -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="mb-1">
                                <i class="fas fa-chart-line me-2" style="color: #10b981;"></i>
                                Finance Dashboard
                            </h3>
                            <p class="mb-0 opacity-75">Manage and review all staff claims</p>
                        </div>
                        <div class="mt-2 mt-sm-0">
                            <i class="fas fa-user-circle me-1"></i> 
                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </div>
                    </div>
                </div>
 
                <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                <div class="row g-4 mb-4 fade-in">
                    <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                    <div class="col-md-3 col-sm-6">
                        <a href="All_Claim_Finance.php" class="text-decoration-none">
                            <div class="stat-card stat-card-total text-center">
                                <div class="stat-icon mx-auto"><i class="fas fa-file-invoice"></i></div>
                                <div class="stat-number"><?php echo $total; ?></div>
                                <div class="stat-label">Total Claims <i class="fas fa-external-link-alt ms-1 small"></i></div>
                            </div>
                        </a>
                    </div>
                    <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                    <div class="col-md-3 col-sm-6">
                        <a href="All_Claim_Finance.php?status=Pending" class="text-decoration-none">
                            <div class="stat-card stat-card-pending text-center">
                                <div class="stat-icon mx-auto"><i class="fas fa-clock"></i></div>
                                <div class="stat-number"><?php echo $pending; ?></div>
                                <div class="stat-label">Pending Approval <i class="fas fa-external-link-alt ms-1 small"></i></div>
                            </div>
                        </a>
                    </div>
                    <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                    <div class="col-md-3 col-sm-6">
                        <a href="All_Claim_Finance.php?status=Approved" class="text-decoration-none">
                            <div class="stat-card stat-card-approved text-center">
                                <div class="stat-icon mx-auto"><i class="fas fa-hourglass-half"></i></div>
                                <div class="stat-number"><?php echo $approved; ?></div>
                                <div class="stat-label">Pending Payment <i class="fas fa-external-link-alt ms-1 small"></i></div>
                            </div>
                        </a>
                    </div>
                    <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                    <div class="col-md-3 col-sm-6">
                        <a href="All_Claim_Finance.php?status=Paid" class="text-decoration-none">
                            <div class="stat-card stat-card-paid text-center">
                                <div class="stat-icon mx-auto"><i class="fas fa-check-circle"></i></div>
                                <div class="stat-number"><?php echo $paid; ?></div>
                                <div class="stat-label">Paid Claims <i class="fas fa-external-link-alt ms-1 small"></i></div>
                            </div>
                        </a>
                    </div>
                </div>
 
                <!-- CONDITION: Evaluates `if($approved > 0)` so the application can choose the correct business rule branch for the current user action. -->
                <?php if($approved > 0): ?>
                <div class="payment-alert fade-in">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <i class="fas fa-exclamation-circle me-2" style="color: #f59e0b;"></i>
                            <strong><?php echo $approved; ?> claim(s)</strong> approved and awaiting payment —
                            Total: <strong>RM <?php echo number_format($pending_amount, 2); ?></strong>
                        </div>
                        <a href="All_Claim_Finance.php?status=Approved" class="btn btn-sm mt-2 mt-sm-0" style="background: #f59e0b; color: white; border-radius: 10px;">
                            <i class="fas fa-money-bill me-1"></i> Process Payment
                        </a>
                    </div>
                </div>
                <?php endif; ?>
 
                <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                <div class="row g-4 fade-in">
                    <div class="col-md-5">
                        <div class="action-card mb-4">
                            <div class="card-body p-4">
                                <h5 style="color: #064e3b;">
                                    <i class="fas fa-bolt me-2" style="color: #10b981;"></i>
                                    Quick Actions
                                </h5>
                                <hr>
                                <a href="All_Claim_Finance.php?status=Pending" class="btn btn-primary-custom w-100 mb-3">
                                    <i class="fas fa-clock me-2"></i>Review Pending Claims
                                    <!-- CONDITION: Evaluates `if($pending > 0)` so the application can choose the correct business rule branch for the current user action. -->
                                    <?php if($pending > 0): ?>
                                        <span class="badge-number"><?php echo $pending; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="All_Claim_Finance.php" class="btn btn-secondary-custom w-100 mb-3">
                                    <i class="fas fa-list me-2"></i>View All Claims
                                </a>
                                <a href="Export_Report_Finance.php" class="btn btn-dark-custom w-100">
                                    <i class="fas fa-download me-2"></i>Export Monthly Report
                                </a>
                            </div>
                        </div>

                        <div class="summary-card">
                            <div class="card-body p-4">
                                <h5 style="color: #064e3b;">
                                    <i class="fas fa-chart-pie me-2" style="color: #10b981;"></i>
                                    Status Breakdown
                                </h5>
                                <hr>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span></div>
                                    <div class="summary-value"><?php echo $pending; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-badge status-approved"><i class="fas fa-hourglass-half"></i> Approved</span></div>
                                    <div class="summary-value"><?php echo $approved; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-badge status-paid"><i class="fas fa-check-circle"></i> Paid</span></div>
                                    <div class="summary-value"><?php echo $paid; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span></div>
                                    <div class="summary-value"><?php echo $rejected; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-badge status-cancelled"><i class="fas fa-ban"></i> Cancelled</span></div>
                                    <div class="summary-value"><?php echo $cancelled; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
 
                    <div class="col-md-7">
                        <div class="recent-card h-100">
                            <div class="card-body p-4 d-flex flex-column">
                                <h5 style="color: #064e3b;">
                                    <i class="fas fa-history me-2" style="color: #10b981;"></i>
                                    Recent Claims
                                </h5>
                                <hr>
                                <div class="table-responsive flex-grow-1">
                                    <!-- SECTION: DATA TABLE - Presents database records in an examiner-friendly format for review, audit, or reporting. -->
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>Staff</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- CONDITION: Evaluates `if(empty($recent_claims))` so the application can choose the correct business rule branch for the current user action. -->
                                            <?php if(empty($recent_claims)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-5">
                                                    <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                                    No claims found
                                                </td>
                                            </tr>
                                            <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                                            <?php else: ?>
                                            <?php foreach($recent_claims as $rc): ?>
                                            <tr>
                                                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                <td class="fw-semibold"><?php echo htmlspecialchars($rc['name']); ?></td>
                                                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                <td><?php echo htmlspecialchars($rc['claim_type']); ?></td>
                                                <td class="fw-bold" style="color: #064e3b;">RM <?php echo number_format($rc['amount'], 2); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($rc['status']); ?>">
                                                        <i class="fas <?php echo match(strtolower($rc['status'])) { 'pending' => 'fa-clock', 'approved' => 'fa-hourglass-half', 'paid' => 'fa-check-circle', 'cancelled' => 'fa-ban', default => 'fa-times-circle' }; ?>"></i>
                                                        <?php echo ucfirst($rc['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="Claim_details_Finance.php?id=<?php echo $rc['id']; ?>" class="btn btn-view btn-sm">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="All_Claim_Finance.php" class="btn btn-secondary-custom w-100 mt-3">
                                    <i class="fas fa-arrow-right me-2"></i>View All Claims
                                </a>
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