<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: All_Claim_Finance.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// =========================================================================
// SECTION 1: SESSION MANAGEMENT & ROLE VALIDATION
// Purpose: Ensure only users with the 'finance' role can access this page.
// =========================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();

// Security Check: If not logged in OR role is not 'finance', kick them out to login.
// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
// CONDITION: Evaluates `if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') ` so the application can choose the correct business rule branch for the current user action.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: ../login.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}

// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once '../db.php';
// === SECTION: CSRF DEFENSE DEPENDENCY ===
// What: Load the centralized CSRF helper for the Finance Pay Now forms.
// Why: Starting Stripe Checkout is a payment workflow action and must originate from the authenticated Finance session.
require_once '../csrf_helper.php';

// =========================================================================
// SECTION 2: DYNAMIC SQL QUERY BUILDER (FOR FILTERING)
// Purpose: Securely build a SQL query based on the selected dropdown filter.
// =========================================================================
// Get the requested status from the URL, default to 'All' if not provided.
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
$status_filter = $_GET['status'] ?? 'All';

$where_parts = []; // Array to store SQL 'WHERE' conditions
$params      = []; // Array to store the actual values to bind
$types       = ''; // String to store the data types (e.g., 's' for string)

// If the user selects a specific status (not 'All'), add it to the query conditions.
// CONDITION: Evaluates `if ($status_filter !== 'All') ` so the application can choose the correct business rule branch for the current user action.
if ($status_filter !== 'All') {
    $where_parts[] = "c.status = ?"; // Use '?' as a placeholder to prevent SQL Injection
    $params[]      = $status_filter;
    $types        .= 's';
}

// Combine the conditions. If there are conditions, prepend "WHERE ", otherwise leave empty.
$where_sql = $where_parts ? "WHERE " . implode(" AND ", $where_parts) : "";

// Build the final SQL query using an INNER JOIN to fetch the staff's name and department.
$sql = "
    SELECT c.id, u.name AS staff, u.staff_id, u.department, c.claim_type, c.amount, c.expense_date, c.status, c.submitted_at
    FROM claims c
    JOIN users u ON c.user_id = u.id
    $where_sql
    ORDER BY c.submitted_at DESC
";

// Execute the dynamic query securely using Prepared Statements.
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
$stmt = $conn->prepare($sql);
// CONDITION: Evaluates `if ($params) ` so the application can choose the correct business rule branch for the current user action.
if ($params) {
    // Spread operator (...) dynamically binds however many parameters are in the array.
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
    $stmt->bind_param($types, ...$params);
}
// WHY: Executing the prepared statement performs the validated database operation for the current workflow.
$stmt->execute();
// Fetch all results as an associative array.
// WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
// WHY: fetch_all(MYSQLI_ASSOC) collects every result row for table rendering and report generation.
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// =========================================================================
// SECTION 3: FETCH AGGREGATE COUNTS FOR DROPDOWN UI
// Purpose: Count how many claims are in each status to display numbers in the dropdown.
// =========================================================================
$counts = [];
// Run a GROUP BY query to get counts for all statuses simultaneously (highly efficient).
// WHY: This read-only aggregate query calculates dashboard/report metrics directly from current database records.
$count_result = $conn->query("SELECT status, COUNT(*) as c FROM claims GROUP BY status");
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
while ($row = $count_result->fetch_assoc()) {
    $counts[$row['status']] = $row['c'];
}
// Calculate the grand total for the 'All Claims' option.
// WHY: Aggregating claim amounts supports Finance/Admin reporting totals without manual calculation.
$counts['All'] = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">

<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Claims - Finance | UTMSPACE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">

    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* =========================================================================
           SECTION 5: CUSTOM CSS (FINANCE THEME)
           Purpose: Establish a unique Green color scheme for the Finance portal.
           ========================================================================= */
        /* Define CSS Variables for the Finance Green Theme */
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --finance-primary: #064e3b;
            /* Dark Green */
            --finance-secondary: #047857;
            /* Medium Green */
            --finance-accent: #10b981;
            /* Bright Green (Buttons/Highlights) */
            --finance-bg: #ecfdf5;
            /* Very Light Green (Background) */
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
            background: var(--finance-bg);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar {
            background: linear-gradient(180deg, #064e3b 0%, #047857 100%);
            height: 100vh;
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
            position: sticky;
            top: 0;
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
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            transform: translateX(5px);
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link.active {
            background: #10b981;
            color: #064e3b;
            font-weight: 600;
        }

        /* Main Content & Scrollbar */
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
            background: #e2e8f0;
            border-radius: 10px;
        }

        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-thumb {
            background: #10b981;
            border-radius: 10px;
        }

        /* Header & Cards */
        /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
        .page-header {
            background: linear-gradient(135deg, #064e3b 0%, #047857 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }

        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #d1fae5;
        }

        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            padding: 20px;
        }

        /* Form Inputs */
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-label {
            font-weight: 600;
            color: #064e3b;
            margin-bottom: 8px;
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-select {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 10px 35px 10px 15px !important;
            transition: all 0.3s ease;
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        /* Table Styling */
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom {
            margin-bottom: 0;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom thead {
            background: #f1f5f9;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom th {
            color: #064e3b;
            font-weight: 600;
            padding: 15px;
            border: none;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom th.sorting_asc,
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom th.sorting_desc {
            color: #10b981 !important;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eef2ff;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom tr:hover {
            background: #f8fafc;
        }

        /* Status Badges */
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-pending {
            background: #fef3c7;
            color: #d97706;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-approved {
            background: #d1fae5;
            color: #059669;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-paid {
            background: #dbeafe;
            color: #2563eb;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-rejected {
            background: #fee2e2;
            color: #dc2626;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-cancelled {
            background: #e5e7eb;
            color: #4b5563;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        /* Action Buttons */
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-review {
            background: #10b981;
            color: white;
            border-radius: 8px;
            padding: 6px 15px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-review:hover {
            background: #059669;
            transform: translateY(-2px);
            color: white;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-pay-now {
            background: #059669;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 15px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(5, 150, 105, 0.3);
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-pay-now:hover {
            background: #047857;
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 8px rgba(5, 150, 105, 0.4);
        }

        /* Animation & Misc */
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

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        .record-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        /* DataTables Custom Overrides */
        /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
        .dataTables_filter input {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 6px 12px;
            margin-left: 10px;
        }

        /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
        .dataTables_filter input:focus {
            border-color: #10b981;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .page-item.active .page-link {
            background-color: #10b981 !important;
            border-color: #10b981 !important;
            color: white !important;
        }

        .page-link {
            color: #064e3b !important;
            border-radius: 6px;
            margin: 0 2px;
        }

        /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
        .dataTables_length select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 5px 30px 5px 12px !important;
            margin: 0 5px;
        }

        /* SECTION: RESPONSIVE RULES - These rules adapt sidebars, cards, and tables for smaller screens. */
        @media (max-width: 768px) {
            /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
            .sidebar {
                height: auto;
                position: relative;
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
                        <i class="fas fa-chart-line fs-1" style="color: #10b981;"></i>
                        <h5 class="mt-2">UTMSPACE</h5>
                        <small>Finance Portal</small>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <!-- SECTION: ROLE NAVIGATION - Provides role-specific movement between the pages allowed for the current user. -->
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard_Finance.php">
                            <i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="All_Claim_Finance.php">
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
                            <h3 class="mb-1"><i class="fas fa-file-invoice me-2" style="color: #10b981;"></i>All Submitted Claims</h3>
                            <p class="mb-0 opacity-75">Review and manage all staff claims</p>
                        </div>
                        <span class="record-badge mt-2 mt-sm-0">
                            <i class="fas fa-list me-1"></i> <?php echo count($claims); ?> record(s) found
                        </span>
                    </div>
                </div>

                <div class="filter-card fade-in">
                    <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
                    <form method="GET" action="All_Claim_Finance.php">
                        <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                        <div class="row align-items-end g-3">
                            <div class="col-12">
                                <label class="form-label"><i class="fas fa-filter me-1" style="color: #10b981;"></i>Filter by Database Status</label>

                                <!-- WHY: Auto-submitting the filter dropdown refreshes the report immediately after the user changes status selection. -->
                                <select name="status" class="form-select w-auto" onchange="this.form.submit()">
                                    <option value="All" <?php echo $status_filter == 'All'       ? 'selected' : ''; ?>>All Claims (<?php echo $counts['All'] ?? 0; ?>)</option>
                                    <option value="Pending" <?php echo $status_filter == 'Pending'   ? 'selected' : ''; ?>>Pending (<?php echo $counts['Pending'] ?? 0; ?>)</option>
                                    <option value="Approved" <?php echo $status_filter == 'Approved'  ? 'selected' : ''; ?>>Approved (<?php echo $counts['Approved'] ?? 0; ?>)</option>
                                    <option value="Rejected" <?php echo $status_filter == 'Rejected'  ? 'selected' : ''; ?>>Rejected (<?php echo $counts['Rejected'] ?? 0; ?>)</option>
                                    <option value="Paid" <?php echo $status_filter == 'Paid'      ? 'selected' : ''; ?>>Paid (<?php echo $counts['Paid'] ?? 0; ?>)</option>
                                    <option value="Cancelled" <?php echo $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled (<?php echo $counts['Cancelled'] ?? 0; ?>)</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-card fade-in">
                    <div class="table-responsive">
                        <!-- SECTION: DATA TABLE - Presents database records in an examiner-friendly format for review, audit, or reporting. -->
                        <table class="table table-custom" id="financeClaimsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Submitted</th>
                                    <th>Staff Name</th>
                                    <th>Staff ID</th>
                                    <th>Department</th>
                                    <th>Claim Type</th>
                                    <th>Amount (RM)</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- CONDITION: Evaluates `if (!empty($claims))` so the application can choose the correct business rule branch for the current user action. -->
                                <?php if (!empty($claims)): ?>
                                    <?php foreach ($claims as $i => $claim): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo $i + 1; ?></td>
                                            <td><?php echo date('d M Y', strtotime($claim['submitted_at'])); ?></td>

                                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                            <td class="fw-semibold"><?php echo htmlspecialchars($claim['staff']); ?></td>
                                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                            <td><code><?php echo htmlspecialchars($claim['staff_id']); ?></code></td>
                                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                            <td><?php echo htmlspecialchars($claim['department'] ?: '—'); ?></td>
                                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                            <td><?php echo htmlspecialchars($claim['claim_type']); ?></td>

                                            <td class="fw-bold" style="color: #064e3b;">RM <?php echo number_format($claim['amount'], 2); ?></td>

                                            <td>
                                                <span class="status-<?php echo strtolower($claim['status']); ?>">
                                                    <?php echo ucfirst($claim['status']); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div class="d-flex align-items-center gap-2" style="min-width: 170px; flex-wrap: nowrap;">

                                                    <a href="Claim_details_Finance.php?id=<?php echo $claim['id']; ?>" class="btn-review" style="margin: 0; padding: 6px 12px; height: 32px; display: inline-flex; align-items: center; white-space: nowrap;">
                                                        <i class="fas fa-eye me-1"></i> Review
                                                    </a>

                                                    <!-- CONDITION: Evaluates `if ($claim['status'] === 'Approved')` so the application can choose the correct business rule branch for the current user action. -->
                                                    <?php if ($claim['status'] === 'Approved'): ?>
                                                        <!-- === SECTION: STRIPE PAYMENT START FORM === -->
                                                        <!-- What: Submit the approved claim ID to the Stripe Checkout creator through POST. -->
                                                        <!-- Why: Pay Now must keep the existing CSRF protection before any payment session is created. -->
                                                        <form method="POST" action="process_payment_Finance.php" style="margin: 0;">
                                                            <?php echo csrfInputField(); ?>
                                                            <input type="hidden" name="claim_id" value="<?php echo (int)$claim['id']; ?>">
                                                            <button type="submit" class="btn-pay-now" style="margin: 0; padding: 6px 12px; height: 32px; display: inline-flex; align-items: center; white-space: nowrap;">
                                                                <i class="fas fa-money-check-alt me-1"></i> Pay Now
                                                            </button>
                                                        </form>
                                                    <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                                                    <?php else: ?>
                                                        <div style="width: 85px; height: 32px;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
        <script>
            // Wait until HTML DOM is fully loaded
            // SECTION: JQUERY READY HANDLER - Initializes table plugins after the HTML table has been rendered.
            $(document).ready(function() {
                // Target the specific table ID and initialize the DataTables plugin
                // SECTION: DATATABLES CONFIGURATION - Adds search, sorting, and pagination for examiner-friendly record review.
                $('#financeClaimsTable').DataTable({
                    // DATATABLES: pageLength controls visible rows to balance density and readability.
                    "pageLength": 10, // Default rows per page
                    // DATATABLES: ordering allows users to sort records when checking chronology, staff names, or financial amounts.
                    "ordering": true, // Allow user to click headers to sort columns
                    // DATATABLES: searching enables quick lookup of claims or accounts without manual scanning.
                    "searching": true, // Enable the instant search box
                    "info": true, // Show "Showing 1 to 10 of X entries" at the bottom
                    // DATATABLES: lengthChange lets users choose how many records to inspect per page during review.
                    "lengthChange": true, // Allow user to change between 10/25/50 rows
                    "language": {
                        // Customize UI text and inject FontAwesome icons
                        "search": "<i class='fas fa-search' style='color: #10b981;'></i> Search:",
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
