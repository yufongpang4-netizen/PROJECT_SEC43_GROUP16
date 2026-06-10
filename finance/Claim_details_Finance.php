<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: Claim_details_Finance.php is part of the UTMSPACE Staff Pay and Claim System.
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
// === SECTION: CSRF DEFENSE DEPENDENCY ===
// What: Load the centralized CSRF helper used by protected Finance forms.
// Why: Finance approval and rejection change claim status, so each POST must prove it came from the authenticated browser session.
require_once '../csrf_helper.php';
// SECTION: DEPENDENCY LOADING - Loads the centralized email helper so approval and rejection decisions notify Staff automatically.
require_once '../mailer_helper.php';
 
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
// SECURITY: Casting identifiers with intval() forces numeric IDs and reduces risk from manipulated request parameters.
$claim_id = intval($_GET['id'] ?? 0);
// CONDITION: Evaluates `if(!$claim_id) ` so the application can choose the correct business rule branch for the current user action.
if(!$claim_id) {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: All_Claim_Finance.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}
 
$success = '';
$error   = '';

// SECTION: AUTOMATED CLAIM DECISION NOTIFICATION - Sends Staff an email after Finance approves or rejects a claim.
// WHY: Staff should receive immediate decision feedback without repeatedly checking the dashboard, closing the review communication loop.
function sendClaimDecisionNotification($conn, $claim_id, $decision, $remark)
{
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: SQL is prepared separately from the claim identifier so email recipient lookup remains safe and auditable.
    $recipient_stmt = $conn->prepare("
        SELECT c.id, c.claim_type, c.amount, u.name, u.email
        FROM claims c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
        LIMIT 1
    ");
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: bind_param() attaches the numeric claim identifier safely instead of embedding workflow data in executable SQL.
    $recipient_stmt->bind_param("i", $claim_id);
    // WHY: Executing the prepared statement retrieves the Staff recipient for the decision email.
    $recipient_stmt->execute();
    // WHY: get_result() turns the recipient query into a readable result set for notification logic.
    $recipient_result = $recipient_stmt->get_result();

    // CONDITION: Evaluates `if($recipient_result->num_rows === 0)` so the application avoids sending email when the claim owner cannot be found.
    if($recipient_result->num_rows === 0) {
        $recipient_stmt->close();
        return false;
    }

    // WHY: fetch_assoc() returns one recipient row as named fields for clear email construction.
    $recipient = $recipient_result->fetch_assoc();
    $recipient_stmt->close();

    // SECURITY: Preventing XSS by escaping dynamic claim values before placing them into the HTML email body.
    // WHY: Claim fields and Finance remarks can contain user-controlled text and must remain display-only in email clients.
    $safe_claim_id   = htmlspecialchars((string)$recipient['id'], ENT_QUOTES, 'UTF-8');
    $safe_claim_type = htmlspecialchars($recipient['claim_type'], ENT_QUOTES, 'UTF-8');
    $safe_amount     = htmlspecialchars(number_format($recipient['amount'], 2), ENT_QUOTES, 'UTF-8');
    $safe_decision   = htmlspecialchars($decision, ENT_QUOTES, 'UTF-8');
    $safe_remark     = nl2br(htmlspecialchars($remark !== '' ? $remark : 'No additional remark provided.', ENT_QUOTES, 'UTF-8'));

    // SECTION: EMAIL BODY CREATION - Builds a claim decision message that Staff can read without opening the system first.
    // WHY: Clear claim reference, amount, status, and remark details help Staff understand the Finance decision immediately.
    $decision_body = '
        <p style="margin:0 0 14px;">Finance has updated the decision for your claim.</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:18px 0;">
            <tr>
                <td style="padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; font-weight:700;">Claim ID</td>
                <td style="padding:10px 12px; border:1px solid #e2e8f0;">#' . $safe_claim_id . '</td>
            </tr>
            <tr>
                <td style="padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; font-weight:700;">Claim Type</td>
                <td style="padding:10px 12px; border:1px solid #e2e8f0;">' . $safe_claim_type . '</td>
            </tr>
            <tr>
                <td style="padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; font-weight:700;">Amount</td>
                <td style="padding:10px 12px; border:1px solid #e2e8f0;">RM ' . $safe_amount . '</td>
            </tr>
            <tr>
                <td style="padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; font-weight:700;">Decision</td>
                <td style="padding:10px 12px; border:1px solid #e2e8f0;">' . $safe_decision . '</td>
            </tr>
            <tr>
                <td style="padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; font-weight:700;">Finance Remark</td>
                <td style="padding:10px 12px; border:1px solid #e2e8f0;">' . $safe_remark . '</td>
            </tr>
        </table>
        <p style="margin:0;">Please log in to the Staff dashboard to review the full claim record.</p>
    ';

    // WHY: sendSystemEmail() centralizes PHPMailer delivery and keeps this Finance module focused on decision workflow data.
    return sendSystemEmail($recipient['email'], $recipient['name'], 'Claim #' . $recipient['id'] . ' ' . $decision, $decision_body);
}

// Handle form actions
// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if($_SERVER['REQUEST_METHOD'] == 'POST') ` so the application can choose the correct business rule branch for the current user action.
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // === SECTION: CSRF TOKEN VALIDATION ===
    // What: Validate the hidden form token before accepting Finance approval or rejection input.
    // Why: This prevents a malicious external page from forcing a logged-in Finance user to change a claim decision.
    // SECURITY: Preventing Cross-Site Request Forgery by requiring a session-bound token on every Finance decision POST.
    if(!requireValidCsrfToken($_POST['csrf_token'] ?? '', $error)) {
        // WHY: The workflow stops immediately when the token is missing or invalid so no claim status can be changed silently.
    } else {
        // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
        $remark = trim($_POST['comments'] ?? '');
 
        // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
        // CONDITION: Evaluates `if(isset($_POST['approve'])) ` so the application can choose the correct business rule branch for the current user action.
        if(isset($_POST['approve'])) {
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
            $stmt = $conn->prepare("UPDATE claims SET status='Approved', finance_comment=? WHERE id=?");
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
            $stmt->bind_param('si', $remark, $claim_id);
            // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
            // CONDITION: Evaluates `if($stmt->execute())` so email notification is attempted only after the approval update succeeds.
            if($stmt->execute()) {
                $success = "Claim has been APPROVED successfully!";
                // === SECTION: ACTIVITY LOGGING ===
                // What: Record that Finance approved this claim.
                // Why: Approval decisions affect reimbursement workflow and should be visible in the audit trail.
                if (function_exists('logActivity')) {
                    logActivity($conn, $_SESSION['user_id'], 'Approve Claim', "Approved claim #$claim_id");
                }
                // SECTION: AUTOMATED CLAIM DECISION NOTIFICATION - Notifies Staff that Finance approved the claim.
                // WHY: Approval email lets Staff know the claim can move toward payment without manually refreshing the portal.
                if(!sendClaimDecisionNotification($conn, $claim_id, 'Approved', $remark)) {
                    $success .= " Email notification could not be sent. Please check SMTP credentials.";
                }
            // CONDITION: This fallback executes when the approval update fails, keeping database failure separate from notification failure.
            } else {
                $error = "Approval failed due to system error.";
            }
            $stmt->close();

        // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
        // CONDITION: Evaluates `} elseif(isset($_POST['reject'])) ` so the application can choose the correct business rule branch for the current user action.
        } elseif(isset($_POST['reject'])) {
            // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
            if(empty($remark)) {
                $error = "Please provide a reason for rejection.";
            // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
            } else {
                // SECURITY: Using Prepared Statements to prevent SQL Injection.
                // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
                $stmt = $conn->prepare("UPDATE claims SET status='Rejected', finance_comment=? WHERE id=?");
                // SECURITY: Using Prepared Statements to prevent SQL Injection.
                // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
                $stmt->bind_param('si', $remark, $claim_id);
                // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
                // CONDITION: Evaluates `if($stmt->execute())` so email notification is attempted only after the rejection update succeeds.
                if($stmt->execute()) {
                    $success = "Claim has been REJECTED.";
                    // === SECTION: ACTIVITY LOGGING ===
                    // What: Record that Finance rejected this claim.
                    // Why: Rejection decisions require accountability because they stop or delay reimbursement.
                    if (function_exists('logActivity')) {
                        logActivity($conn, $_SESSION['user_id'], 'Reject Claim', "Rejected claim #$claim_id");
                    }
                    // SECTION: AUTOMATED CLAIM DECISION NOTIFICATION - Notifies Staff that Finance rejected the claim and includes the required remark.
                    // WHY: Rejection email gives Staff the reason quickly so they can decide whether to correct and resubmit the claim.
                    if(!sendClaimDecisionNotification($conn, $claim_id, 'Rejected', $remark)) {
                        $success .= " Email notification could not be sent. Please check SMTP credentials.";
                    }
                // CONDITION: This fallback executes when the rejection update fails, keeping database failure separate from notification failure.
                } else {
                    $error = "Rejection failed due to system error.";
                }
                $stmt->close();
            }
        }
    }
}

// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
$stmt = $conn->prepare("
    SELECT c.*, u.name AS staff, u.staff_id, u.email AS staff_email, u.department
    FROM claims c
    JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
");
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
$stmt->bind_param('i', $claim_id);
// WHY: Executing the prepared statement performs the validated database operation for the current workflow.
$stmt->execute();
// WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
$claim = $stmt->get_result()->fetch_assoc();
 
// CONDITION: Evaluates `if(!$claim) ` so the application can choose the correct business rule branch for the current user action.
if(!$claim) {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: All_Claim_Finance.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}
 
$status = strtolower($claim['status']);
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Details - Finance | UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
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
        body {
            background: var(--finance-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* BOOTSTRAP LAYOUT: The full-width container lets dashboard pages use the complete viewport for side navigation plus content. */
        .container-fluid { height: 100%; overflow: hidden; }
        /* BOOTSTRAP LAYOUT: The zero-gutter row removes unwanted spacing between the sidebar and the main workspace. */
        .row.g-0 { height: 100%; }
        
        /* Sidebar */
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
        
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }
        
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar { width: 8px; }
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-thumb { background: #10b981; border-radius: 10px; }
        
        /* Page Header */
        /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
        .page-header {
            background: linear-gradient(135deg, #064e3b 0%, #047857 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        /* Cards */
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .info-card, .receipt-card, .action-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .card-title {
            color: #064e3b;
            font-size: 18px;
            font-weight: 600;
        }
        
        /* Info Grid */
        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-weight: 600;
            color: #064e3b;
            margin-bottom: 12px;
        }
        
        .info-value-amount {
            font-size: 28px;
            font-weight: 700;
            color: #10b981;
        }
        
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-pending { background: #fef3c7; color: #d97706; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-approved { background: #d1fae5; color: #059669; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-paid { background: #dbeafe; color: #2563eb; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-rejected { background: #fee2e2; color: #dc2626; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-cancelled { background: #e5e7eb; color: #4b5563; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        
        /* Buttons */
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s ease;
        }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4); color: white; }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-reject {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s ease;
        }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-reject:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(239, 68, 68, 0.4); color: white; }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-paid {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s ease;
        }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-paid:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4); color: white; }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-back {
            background: #f1f5f9; color: #064e3b; border: none; border-radius: 10px; padding: 10px 20px; transition: all 0.3s ease;
        }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-back:hover { background: #e2e8f0; transform: translateY(-2px); color: #064e3b; }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-preview, .btn-download { border-radius: 10px; padding: 10px; font-weight: 500; transition: all 0.3s ease; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-preview { background: #10b981; color: white; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-preview:hover { background: #059669; transform: translateY(-2px); color: white; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-download { background: #064e3b; color: white; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-download:hover { background: #047857; transform: translateY(-2px); color: white; }
        
        /* Alert States */
        .alert-info-custom { background: #dbeafe; border-left: 4px solid #2563eb; color: #1e40af; }
        .alert-success-custom { background: #d1fae5; border-left: 4px solid #059669; color: #065f46; }
        .alert-danger-custom { background: #fee2e2; border-left: 4px solid #dc2626; color: #991b1b; }
        .alert-warning-custom { background: #fef3c7; border-left: 4px solid #d97706; color: #92400e; }
        .alert-secondary-custom { background: #f3f4f6; border-left: 4px solid #6b7280; color: #374151; }
        
        /* Form Controls */
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control, .form-select { border-radius: 12px; border: 1px solid #e5e7eb; padding: 12px 15px; transition: all 0.3s ease; }
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:focus, .form-select:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        
        /* SECTION: ANIMATION - Keyframes add subtle entrance motion to guide attention without changing business logic. */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        hr { border-color: #e5e7eb; }
        
        .receipt-img { max-height: 180px; object-fit: contain; border-radius: 10px; }
        .remark-box { background: #f0fdf4; border-radius: 12px; padding: 15px; }
        
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
                            <h3 class="mb-1">
                                <i class="fas fa-file-invoice me-2" style="color: #10b981;"></i>
                                Claim Details
                            </h3>
                            <p class="mb-0 opacity-75">Review and manage claim #<?php echo $claim['id']; ?></p>
                        </div>
                        <a href="All_Claim_Finance.php" class="btn btn-back mt-2 mt-sm-0">
                            <i class="fas fa-arrow-left me-2"></i>Back to Claims
                        </a>
                    </div>
                </div>
 
                <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                <div class="row g-4 fade-in">
                    <div class="col-md-7">
                        <div class="info-card">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title">
                                        <i class="fas fa-receipt me-2" style="color: #10b981;"></i>
                                        Claim Information
                                    </h5>
                                    <span class="status-<?php echo $status; ?>">
                                        <i class="fas <?php echo match($status) { 'pending' => 'fa-clock', 'approved' => 'fa-hourglass-half', 'paid' => 'fa-check-circle', 'cancelled' => 'fa-ban', default => 'fa-times-circle' }; ?> me-1"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                                <hr>
                                
                                <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                                <div class="row">
                                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                    <div class="col-md-6">
                                        <div class="info-label">Claim ID</div>
                                        <div class="info-value">#<?php echo $claim['id']; ?></div>
                                    </div>
                                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                    <div class="col-md-6">
                                        <div class="info-label">Submitted On</div>
                                        <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($claim['submitted_at'])); ?></div>
                                    </div>
                                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                    <div class="col-md-6">
                                        <div class="info-label">Staff Name</div>
                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <div class="info-value"><?php echo htmlspecialchars($claim['staff']); ?></div>
                                    </div>
                                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                    <div class="col-md-6">
                                        <div class="info-label">Staff ID</div>
                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <div class="info-value"><?php echo htmlspecialchars($claim['staff_id']); ?></div>
                                    </div>
                                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                    <div class="col-md-6">
                                        <div class="info-label">Email</div>
                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <div class="info-value"><?php echo htmlspecialchars($claim['staff_email']); ?></div>
                                    </div>
                                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                    <div class="col-md-6">
                                        <div class="info-label">Department</div>
                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <div class="info-value"><?php echo htmlspecialchars($claim['department'] ?: '—'); ?></div>
                                    </div>
                                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                    <div class="col-md-6">
                                        <div class="info-label">Claim Type</div>
                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <div class="info-value"><?php echo htmlspecialchars($claim['claim_type']); ?></div>
                                    </div>
                                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                    <div class="col-md-6">
                                        <div class="info-label">Expense Date</div>
                                        <div class="info-value"><?php echo $claim['expense_date'] ? date('d M Y', strtotime($claim['expense_date'])) : '-'; ?></div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="info-label">Amount</div>
                                        <div class="info-value-amount">RM <?php echo number_format($claim['amount'], 2); ?></div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="info-label">Description</div>
                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <!-- SECURITY: Multi-line user text is escaped before line breaks are added, preventing stored XSS in descriptions or remarks. -->
                                        <div class="info-value"><?php echo nl2br(htmlspecialchars($claim['description'] ?? '-')); ?></div>
                                    </div>
                                    
                                    <!-- CONDITION: Evaluates `if(!empty($claim['finance_comment']))` so the application can choose the correct business rule branch for the current user action. -->
                                    <?php if(!empty($claim['finance_comment'])): ?>
                                    <div class="col-12 mt-3">
                                        <div class="remark-box">
                                            <i class="fas fa-comment me-2" style="color: #10b981;"></i>
                                            <strong>Finance Remark</strong>
                                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                            <!-- SECURITY: Multi-line user text is escaped before line breaks are added, preventing stored XSS in descriptions or remarks. -->
                                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($claim['finance_comment'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
 
                    <div class="col-md-5">
                        <div class="receipt-card mb-4">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-paperclip" style="font-size: 45px; color: #10b981;"></i>
                                <h5 class="mt-3" style="color: #064e3b;">Attached Receipt</h5>
                                
                                <!-- CONDITION: Evaluates `if(!empty($claim['receipt']))` so the application can choose the correct business rule branch for the current user action. -->
                                <?php if(!empty($claim['receipt'])): ?>
                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                    <p class="text-muted small"><?php echo htmlspecialchars($claim['receipt']); ?></p>
                                    
                                    <?php
                                    $receipt_path = '../uploads/receipts/' . $claim['receipt'];
                                    $ext = strtolower(pathinfo($claim['receipt'], PATHINFO_EXTENSION));
                                    ?>
                                    
                                    <!-- CONDITION: Evaluates `if(in_array($ext, ['jpg','jpeg','png','gif']))` so the application can choose the correct business rule branch for the current user action. -->
                                    <?php if(in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                        <img src="<?php echo $receipt_path; ?>" class="receipt-img mb-3" alt="Receipt">
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo $receipt_path; ?>" target="_blank" class="btn btn-preview">
                                            <i class="fas fa-eye me-2"></i>Preview Receipt
                                        </a>
                                        <a href="<?php echo $receipt_path; ?>" download class="btn btn-download">
                                            <i class="fas fa-download me-2"></i>Download Receipt
                                        </a>
                                    </div>
                                <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                                <?php else: ?>
                                    <div class="alert-warning-custom p-3 rounded mt-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No receipt attached to this claim.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
 
                        <div class="action-card">
                            <div class="card-body p-4">
                                <h5 class="card-title">
                                    <i class="fas fa-gavel me-2" style="color: #10b981;"></i>
                                    Finance Actions
                                </h5>
                                <hr>
 
                                <!-- CONDITION: Evaluates `if($status == 'pending')` so the application can choose the correct business rule branch for the current user action. -->
                                <?php if($status == 'pending'): ?>
                                    <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
                                    <form method="POST" id="actionForm">
                                        <?php echo csrfInputField(); ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Comments / Remarks</label>
                                            <textarea name="comments" id="finance-remark" class="form-control" rows="3"
                                                placeholder="Add your comments here... (required for rejection)"></textarea>
                                            <small class="text-muted mt-1 d-block">
                                                <i class="fas fa-info-circle me-1"></i> Remarks will be visible to the staff member
                                            </small>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-approve" onclick="confirmAction('approve')">
                                                <i class="fas fa-check-circle me-2"></i>Approve Claim
                                            </button>
                                            <button type="button" class="btn btn-reject" onclick="confirmAction('reject')">
                                                <i class="fas fa-times-circle me-2"></i>Reject Claim
                                            </button>
                                            <input type="hidden" name="" id="hidden-action">
                                        </div>
                                    </form>
 
                                <!-- CONDITION: Evaluates `elseif($status == 'approved')` so the application can choose the correct business rule branch for the current user action. -->
                                <?php elseif($status == 'approved'): ?>
                                    <div class="alert-warning-custom p-3 mb-3 rounded">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Claim Approved</strong><br>
                                        This claim has been approved and is ready for payment transfer.
                                    </div>
                                    <div class="d-grid">
                                        <!-- === SECTION: STRIPE PAYMENT START FORM === -->
                                        <!-- What: Start Stripe Checkout through a protected POST request instead of a direct simulated-payment URL. -->
                                        <!-- Why: Payment initiation is a Finance state-changing workflow and must preserve CSRF protection before contacting Stripe. -->
                                        <form method="POST" action="process_payment_Finance.php">
                                            <?php echo csrfInputField(); ?>
                                            <input type="hidden" name="claim_id" value="<?php echo (int)$claim['id']; ?>">
                                            <button type="submit" class="btn btn-paid w-100 text-center">
                                                <i class="fas fa-money-check-alt me-2"></i>Proceed to Stripe Checkout
                                            </button>
                                        </form>
                                    </div>
 
                                <!-- CONDITION: Evaluates `elseif($status == 'paid')` so the application can choose the correct business rule branch for the current user action. -->
                                <?php elseif($status == 'paid'): ?>
                                    <div class="alert-success-custom p-3 text-center rounded">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Claim Paid</strong><br>
                                        This claim has been paid. No further action required.
                                    </div>
 
                                <!-- CONDITION: Evaluates `elseif($status == 'rejected')` so the application can choose the correct business rule branch for the current user action. -->
                                <?php elseif($status == 'rejected'): ?>
                                    <div class="alert-danger-custom p-3 text-center rounded">
                                        <i class="fas fa-times-circle me-2"></i>
                                        <strong>Claim Rejected</strong><br>
                                        This claim has been rejected.
                                    </div>
                                    
                                <!-- CONDITION: Evaluates `elseif($status == 'cancelled')` so the application can choose the correct business rule branch for the current user action. -->
                                <?php elseif($status == 'cancelled'): ?>
                                    <div class="alert-secondary-custom p-3 text-center rounded">
                                        <i class="fas fa-ban me-2"></i>
                                        <strong>Claim Cancelled</strong><br>
                                        This claim was cancelled and is no longer valid.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script>
        // SECTION: CLIENT FUNCTION - Encapsulates repeated UI logic so page behavior is easier to audit and maintain.
        // SECTION: CONFIRMATION WORKFLOW - Requires deliberate confirmation before irreversible or high-impact actions occur.
        function confirmAction(actionType) {
            const remark = document.getElementById('finance-remark').value.trim();
            
            // CONDITION: Evaluates `if (actionType === 'reject' && remark === '') ` so the application can choose the correct business rule branch for the current user action.
            if (actionType === 'reject' && remark === '') {
                // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
                Swal.fire({
                    icon: 'error',
                    title: 'Remark Required',
                    text: 'You must provide a reason when rejecting a claim.',
                    confirmButtonColor: '#10b981',
                    showClass: { popup: 'animate__animated animate__shakeX' }
                });
                return;
            }

            const title = actionType === 'approve' ? 'Approve Claim?' : 'Reject Claim?';
            const text = actionType === 'approve' ? 'Are you sure you want to approve this claim for payment?' : 'This will reject the claim and return it to the staff.';
            const confirmColor = actionType === 'approve' ? '#10b981' : '#ef4444';
            const confirmIcon = actionType === 'approve' ? '<i class="fas fa-check me-1"></i> Yes, Approve' : '<i class="fas fa-times me-1"></i> Yes, Reject';

            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                cancelButtonColor: '#6b7280',
                confirmButtonText: confirmIcon
            }).then((result) => {
                // CONDITION: Evaluates `if (result.isConfirmed) ` so the application can choose the correct business rule branch for the current user action.
                if (result.isConfirmed) {
                    const form = document.getElementById('actionForm');
                    const hiddenAction = document.getElementById('hidden-action');
                    hiddenAction.name = actionType;
                    hiddenAction.value = '1';
                    // WHY: The form is submitted programmatically only after the user confirms the action.
                    form.submit();
                }
            });
        }

        // CONDITION: Evaluates `if($success)` so the application can choose the correct business rule branch for the current user action.
        <?php if($success): ?>
            // WHY: A toast configuration provides non-blocking feedback after low-risk successful actions.
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            Toast.fire({
                icon: 'success',
                title: '<?php echo addslashes(strip_tags($success)); ?>'
            });
        // CONDITION: Evaluates `elseif($error)` so the application can choose the correct business rule branch for the current user action.
        <?php elseif($error): ?>
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                icon: 'error',
                title: 'Action Failed',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#10b981'
            });
        <?php endif; ?>
    </script>
</body>
</html>
