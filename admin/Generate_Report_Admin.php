<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: Generate_Report_Admin.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// =========================================================================
// SECTION 1: SESSION MANAGEMENT & SECURITY VALIDATION
// Purpose: Protect the page and ensure only admins can access it.
// =========================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();

// Security Check: If the user is not logged in or is not an 'admin', redirect to login page.
// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
// CONDITION: Evaluates `if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') ` so the application can choose the correct business rule branch for the current user action.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: ../login.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}

// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once '../db.php';

// =========================================================================
// SECTION 2: INITIALIZE FILTER VARIABLES
// Purpose: Capture user input from the URL parameters using the GET method.
// Note: We use GET instead of POST here so the filter parameters stay in the URL, 
// allowing users to bookmark the specific report or refresh without resubmitting data.
// The '??' is the Null Coalescing Operator (assigns an empty string if the GET parameter is missing).
// =========================================================================
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
$filter_role = $_GET['filter_role']   ?? '';
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
$filter_dept = $_GET['department']    ?? '';
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
$date_from   = $_GET['date_from']     ?? '';
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
$date_to     = $_GET['date_to']       ?? '';

$generated   = false; // Flag to track if the "Generate" button was clicked
$users       = [];    // Array to store the fetched report data

// =========================================================================
// SECTION 3: CSV EXPORT LOGIC
// Purpose: Generate a downloadable Excel-compatible CSV file if the export button is clicked.
// =========================================================================
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
// CONDITION: Evaluates `if (isset($_GET['export']) && $_GET['export'] === 'csv') ` so the application can choose the correct business rule branch for the current user action.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // 1. Build and run the query based on the current active filters
    $q = buildQuery($filter_role, $filter_dept, $date_from, $date_to);
    $res = runQuery($conn, $q['sql'], $q['types'], $q['params']);

    // 2. Modify HTTP Headers to force the browser to download a file instead of displaying a web page
    // WHY: Export headers instruct the browser to download the report instead of rendering a normal web page.
    header('Content-Type: text/csv');
    // Dynamically generate the filename with today's date (e.g., UTMSpace_Users_20260523.csv)
    // WHY: Export headers instruct the browser to download the report instead of rendering a normal web page.
    header('Content-Disposition: attachment; filename="UTMSpace_Users_' . date('Ymd') . '.csv"');

    // 3. Open the PHP output stream to write data directly to the browser download
    // WHY: php://output streams the generated CSV directly to the browser download response.
    $out = fopen('php://output', 'w');

    // 4. Write the CSV column headers
    // BEST PRACTICE: fputcsv() writes properly escaped CSV rows so commas and special characters do not corrupt exported reports.
    fputcsv($out, ['ID', 'Staff ID', 'Name', 'Email', 'Phone', 'Role', 'Department', 'Status', 'Registered']);

    // 5. Loop through the database results and write each row into the CSV file
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    while ($row = $res->fetch_assoc()) {
        // BEST PRACTICE: fputcsv() writes properly escaped CSV rows so commas and special characters do not corrupt exported reports.
        fputcsv($out, [
            $row['id'],
            $row['staff_id'] ?? '',
            $row['name'],
            $row['email'],
            $row['phone'] ?? '',
            ucfirst($row['role']), // Capitalize the first letter of the role
            $row['department'] ?? '',
            $row['status'] ?? 'Active',
            // WHY: Date formatting converts database timestamps into human-readable dates for review and reports.
            date('d M Y', strtotime($row['created_at'])), // Format timestamp into a readable date
        ]);
    }

    fclose($out); // Close the file stream
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit(); // Terminate the script completely so no HTML is accidentally appended to the CSV file
}

// =========================================================================
// SECTION 4: DYNAMIC QUERY BUILDER FUNCTION
// Purpose: Construct the SQL query dynamically based on which filters are active.
// =========================================================================
function buildQuery($role, $dept, $from, $to)
{
    // Start with a base query. 'WHERE 1=1' is a neat trick that is always true, 
    // allowing us to append multiple 'AND' conditions safely without checking if it's the first condition.
    $sql    = "SELECT * FROM users WHERE 1=1";
    $params = []; // Array to hold the actual values to be bound
    $types  = ''; // String to track data types (e.g., 's' for string) for prepared statements

    // If a role filter is selected, append it to the SQL query
    // CONDITION: Evaluates `if ($role !== '') ` so the application can choose the correct business rule branch for the current user action.
    if ($role !== '') {
        $sql      .= " AND role=?";
        $params[] = $role;
        $types    .= 's';
    }
    // If a department filter is selected
    // CONDITION: Evaluates `if ($dept !== '') ` so the application can choose the correct business rule branch for the current user action.
    if ($dept !== '') {
        $sql      .= " AND department=?";
        $params[] = $dept;
        $types    .= 's';
    }
    // If a Start Date is provided
    // CONDITION: Evaluates `if ($from !== '') ` so the application can choose the correct business rule branch for the current user action.
    if ($from !== '') {
        $sql      .= " AND DATE(created_at) >= ?";
        $params[] = $from;
        $types    .= 's';
    }
    // If an End Date is provided
    // CONDITION: Evaluates `if ($to !== '') ` so the application can choose the correct business rule branch for the current user action.
    if ($to !== '') {
        $sql      .= " AND DATE(created_at) <= ?";
        $params[] = $to;
        $types    .= 's';
    }

    // Always order the newest records first
    $sql .= " ORDER BY created_at DESC";

    // Return the query parts as an associative array using the compact() function
    return compact('sql', 'types', 'params');
}

// =========================================================================
// SECTION 5: SECURE QUERY EXECUTION FUNCTION
// Purpose: Execute the SQL query using Prepared Statements to prevent SQL Injection attacks.
// =========================================================================
function runQuery($conn, $sql, $types, $params)
{
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
    $stmt = $conn->prepare($sql);
    // If there are parameters to bind, use the spread operator (...) to unpack the array into arguments
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
    // CONDITION: Evaluates `if ($params) $stmt->bind_param($types, ...$params);` so the application can choose the correct business rule branch for the current user action.
    if ($params) $stmt->bind_param($types, ...$params);
    // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
    $stmt->execute();
    // WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
    return $stmt->get_result();
}

// =========================================================================
// SECTION 6: GENERATE REPORT LOGIC
// Purpose: Fetch the data only when the admin clicks the "Generate" button.
// =========================================================================
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
// CONDITION: Evaluates `if (isset($_GET['generate'])) ` so the application can choose the correct business rule branch for the current user action.
if (isset($_GET['generate'])) {
    $generated = true; // Set flag to true so HTML knows to display the report table
    $q         = buildQuery($filter_role, $filter_dept, $date_from, $date_to);
    $res       = runQuery($conn, $q['sql'], $q['types'], $q['params']);

    // Fetch all results into the $users array
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    while ($row = $res->fetch_assoc()) $users[] = $row;
}

// =========================================================================
// SECTION 7: FETCH DEPARTMENT LIST FOR DROPDOWN
// Purpose: Get a unique list of all available departments for the filter dropdown.
// =========================================================================
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$depts_res   = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = [];
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
while ($d = $depts_res->fetch_assoc()) $departments[] = $d['department'];
?>
<!DOCTYPE html>
<html lang="en">

<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report - Admin | UTMSPACE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">

    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* =========================================================================
           SECTION 8: CSS STYLING
           ========================================================================= */
        /* CSS Variables for UTMSPACE Theme */
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --admin-primary: #2e1065;
            --admin-secondary: #4c1d95;
            --admin-accent: #8b5cf6;
            --admin-bg: #faf5ff;
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
            background: var(--admin-bg);
            font-family: 'Segoe UI', Tahoma, sans-serif;
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

        /* Main Content Layout */
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }

        /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
        .page-header {
            background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }

        /* Filter Form Card */
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .filter-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-label {
            font-weight: 600;
            color: #2e1065;
            margin-bottom: 8px;
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control,
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-select {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:focus,
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        /* Custom Buttons Styling */
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-generate,
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-reset {
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-generate {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
            color: white;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-reset {
            background: #f1f5f9;
            color: #2e1065;
            border: none;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-reset:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            color: #2e1065;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-export-csv {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-print {
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }

        /* Report Result Card */
        .report-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }

        .filters-info {
            background: #faf5ff;
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-left: 4px solid #8b5cf6;
        }

        /* Table and Badges */
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom thead {
            background: #f1f5f9;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom th {
            color: #2e1065;
            font-weight: 600;
            padding: 12px 15px;
            border: none;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f3e8ff;
        }

        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-active {
            background: #d1fae5;
            color: #059669;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* DataTables Customizations */
        /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
        .dataTables_filter input {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 6px 12px;
            margin-left: 10px;
        }

        .page-item.active .page-link {
            background-color: #8b5cf6 !important;
            border-color: #8b5cf6 !important;
            color: white !important;
        }

        /* CSS specifically for Print View */
        /* When the user presses Ctrl+P or clicks the Print button, these rules hide unnecessary UI elements like the sidebar and filter forms, so only the raw table data is printed on the physical paper. */
        /* SECTION: PRINT RULES - Print styles hide navigation and controls so exported reports focus on official data only. */
        @media print {

            /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
            .sidebar,
            /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
            .btn-generate,
            /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
            .btn-reset,
            /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
            .btn-export-csv,
            /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
            .btn-print,
            /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
            .page-header button,
            /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
            .dataTables_filter,
            /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
            .dataTables_paginate,
            /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
            .dataTables_info,
            /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
            .dataTables_length {
                display: none !important;
            }

            .col-md-9,
            /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
            .main-content {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
            body {
                background: white !important;
            }

            /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
            .filter-card {
                display: none !important;
            }

            .report-card {
                box-shadow: none;
                border: none;
            }
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
                        <i class="fas fa-user-shield fs-1" style="color:#8b5cf6;"></i>
                        <h5 class="mt-2">UTMSPACE</h5>
                        <small>Admin Portal</small>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <!-- SECTION: ROLE NAVIGATION - Provides role-specific movement between the pages allowed for the current user. -->
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard_Admin.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard</a>
                        <a class="nav-link" href="Manage_User_Admin.php"><i class="fas fa-users fa-fw me-2"></i> Manage Accounts</a>
                        <a class="nav-link" href="Manage_Claims_Admin.php"><i class="fas fa-file-invoice-dollar fa-fw me-2"></i> Manage Claims</a>
                        <a class="nav-link active" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar fa-fw me-2"></i> Generate Report</a>
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
                            <h3 class="mb-1"><i class="fas fa-chart-bar me-2" style="color: #8b5cf6;"></i>Generate User Reports</h3>
                            <p class="mb-0 opacity-75">Export user data with custom filters</p>
                        </div>
                    </div>
                </div>

                <div class="filter-card fade-in">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3"><i class="fas fa-filter me-2" style="color: #8b5cf6;"></i>Filter Users</h5>
                        <hr>

                        <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
                        <form method="GET">
                            <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                            <div class="row g-3">

                                <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                                <div class="col-md-3">
                                    <label class="form-label">Role</label>
                                    <select name="filter_role" class="form-select">
                                        <option value="">All Roles</option>
                                        <option value="staff" <?php echo $filter_role === 'staff'   ? 'selected' : ''; ?>>Staff</option>
                                        <option value="finance" <?php echo $filter_role === 'finance' ? 'selected' : ''; ?>>Finance</option>
                                        <option value="admin" <?php echo $filter_role === 'admin'   ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>

                                <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
                                <div class="col-md-3">
                                    <label class="form-label">Department</label>
                                    <select name="department" class="form-select">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                            <option value="<?php echo htmlspecialchars($dept); ?>"
                                                <?php echo $filter_dept === $dept ? 'selected' : ''; ?>>
                                                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                <?php echo htmlspecialchars($dept); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Registered From</label>
                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Registered To</label>
                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="generate" class="btn btn-generate flex-grow-1">
                                            <i class="fas fa-chart-line me-1"></i> Generate
                                        </button>
                                        <a href="Generate_Report_Admin.php" class="btn btn-reset">
                                            <i class="fas fa-sync-alt"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- CONDITION: Evaluates `if ($generated)` so the application can choose the correct business rule branch for the current user action. -->
                <?php if ($generated): ?>
                    <div class="report-card fade-in mb-5">
                        <div class="card-body p-0">

                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h5 class="card-title mb-2 mb-sm-0" style="color: #2e1065; font-weight:600;">
                                    <i class="fas fa-users me-2" style="color: #8b5cf6;"></i>User Report
                                    <span class="text-muted fs-6 ms-2">(<?php echo count($users); ?> record<?php echo count($users) !== 1 ? 's' : ''; ?>)</span>
                                </h5>

                                <div class="d-flex gap-2">
                                    <a href="?generate=1&filter_role=<?php echo urlencode($filter_role); ?>&department=<?php echo urlencode($filter_dept); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&export=csv"
                                        class="btn btn-export-csv">
                                        <i class="fas fa-file-csv me-1"></i> Export CSV
                                    </a>

                                    <button class="btn btn-print" onclick="window.print()">
                                        <i class="fas fa-print me-1"></i> Print
                                    </button>
                                </div>
                            </div>

                            <div class="filters-info">
                                <i class="fas fa-info-circle me-2" style="color: #8b5cf6;"></i>
                                <strong>Filters Applied:</strong>
                                Role: <strong><?php echo $filter_role ? ucfirst($filter_role) : 'All'; ?></strong> |
                                Department: <strong><?php echo $filter_dept ?: 'All'; ?></strong> |
                                Registered: <strong><?php echo $date_from ?: 'Start'; ?></strong> to <strong><?php echo $date_to ?: 'Now'; ?></strong>
                            </div>

                            <!-- CONDITION: Evaluates `if (empty($users))` so the application can choose the correct business rule branch for the current user action. -->
                            <?php if (empty($users)): ?>
                                <div class="empty-state" style="text-align: center; padding: 60px;">
                                    <div class="empty-icon" style="font-size: 60px; color: #cbd5e1; margin-bottom: 20px;"><i class="fas fa-inbox"></i></div>
                                    <h5 style="color: #2e1065;">No users found</h5>
                                    <p class="text-muted">Try adjusting your filter criteria</p>
                                </div>
                            <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                            <?php else: ?>

                                <?php $role_counts = array_count_values(array_column($users, 'role')); ?>
                                <div class="mb-3 d-flex gap-2 flex-wrap">
                                    <?php foreach ($role_counts as $r => $cnt): ?>
                                        <span class="role-badge role-<?php echo $r; ?>" style="background: <?php echo $r == 'staff' ? '#3b82f6' : ($r == 'finance' ? '#10b981' : '#ef4444'); ?>; color: white;">
                                            <?php echo ucfirst($r); ?>: <?php echo $cnt; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="table-responsive">
                                    <!-- SECTION: DATA TABLE - Presents database records in an examiner-friendly format for review, audit, or reporting. -->
                                    <table class="table table-custom" id="generatedReportTable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Staff ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Role</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                                <th>Registered</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $i => $u): ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo $i + 1; ?></td>
                                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                    <td><code><?php echo htmlspecialchars($u['staff_id'] ?? '—'); ?></code></td>
                                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                    <td class="fw-semibold"><?php echo htmlspecialchars($u['name']); ?></td>
                                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                    <td><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                                                    <td><span class="role-badge" style="background: <?php echo $u['role'] == 'staff' ? '#3b82f6' : ($u['role'] == 'finance' ? '#10b981' : '#ef4444'); ?>; color: white;"><?php echo ucfirst($u['role']); ?></span></td>
                                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                                    <td><?php echo htmlspecialchars($u['department'] ?? '—'); ?></td>
                                                    <td><span class="<?php echo ($u['status'] ?? 'Active') == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $u['status'] ?? 'Active'; ?></span></td>
                                                    <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script>
        // Execute this script only after the DOM has fully loaded
        // SECTION: JQUERY READY HANDLER - Initializes table plugins after the HTML table has been rendered.
        $(document).ready(function() {
            // Target the HTML table using its ID and initialize DataTables plugin
            // SECTION: DATATABLES CONFIGURATION - Adds search, sorting, and pagination for examiner-friendly record review.
            $('#generatedReportTable').DataTable({
                // DATATABLES: pageLength controls visible rows to balance density and readability.
                "pageLength": 10, // Show 10 records per page by default
                // DATATABLES: lengthChange lets users choose how many records to inspect per page during review.
                "lengthChange": false, // Hide the "show X entries" dropdown to keep UI clean
                "language": {
                    // Customize the text and add FontAwesome icons to the search bar and pagination buttons
                    "search": "<i class='fas fa-search' style='color: #8b5cf6;'></i> Search within results:",
                    "paginate": {
                        "next": "<i class='fas fa-chevron-right'></i>",
                        "previous": "<i class='fas fa-chevron-left'></i>"
                    }
                }
            });
        });
    </script>
</body>

</html>