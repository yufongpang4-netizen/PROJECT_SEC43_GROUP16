<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: New_Claim_Staff.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once "../db.php";
// SECTION: DEPENDENCY LOADING - Loads the centralized email helper so successful claim submissions can notify Finance automatically.
require_once "../mailer_helper.php";
// SECTION: SECURITY HELPER LOADING - Loads reusable CSRF protection for state-changing Staff forms.
require_once "../csrf_helper.php";
// SECTION: UPLOAD HELPER LOADING - Loads hardened receipt upload validation and storage rules.
require_once "../receipt_upload_helper.php";

// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
// CONDITION: Evaluates `if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') ` so the application can choose the correct business rule branch for the current user action.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: ../login.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}

$success = '';
$error   = '';

// ========== CLAIM LIMITS CONFIGURATION ==========
// SECTION: CLAIM LIMITS CONFIGURATION - Defines financial policy limits used by both backend validation and user-facing guidance.
// WHY: Centralizing these amounts keeps monthly governance, per-claim policy, and real-time UX validation aligned for Staff submissions.
$MAX_CLAIM_AMOUNT_PER_MONTH = 500;    // Maximum RM 500 per month total
$MAX_NUMBER_OF_CLAIMS_PER_MONTH = 3;  // Maximum 3 claims per month
$MAX_AMOUNT_PER_CLAIM = 200;          // Maximum RM 200 per single claim

$user_id = $_SESSION['user_id'];
// WHY: Date formatting converts database timestamps into human-readable dates for review and reports.
$current_month = date('Y-m');

$check_sql = "
    SELECT 
        COUNT(*) as total_claims,
        SUM(amount) as total_amount
    FROM claims 
    WHERE user_id = ? 
    AND DATE_FORMAT(submitted_at, '%Y-%m') = ?
    AND status NOT IN ('Cancelled', 'Rejected')
";

// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
$check_stmt = $conn->prepare($check_sql);
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
$check_stmt->bind_param("is", $user_id, $current_month);
// WHY: Executing the prepared statement performs the validated database operation for the current workflow.
$check_stmt->execute();
// WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
$check_result = $check_stmt->get_result();
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
$monthly_data = $check_result->fetch_assoc();

$total_claims_this_month = $monthly_data['total_claims'] ?? 0;
$total_amount_this_month = $monthly_data['total_amount'] ?? 0;

// Check for pending claims
$pending_sql = "SELECT COUNT(*) as pending_count FROM claims WHERE user_id = ? AND status = 'Pending'";
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
$pending_stmt = $conn->prepare($pending_sql);
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
$pending_stmt->bind_param("i", $user_id);
// WHY: Executing the prepared statement performs the validated database operation for the current workflow.
$pending_stmt->execute();
// WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
$pending_result = $pending_stmt->get_result();
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
$pending_data = $pending_result->fetch_assoc();
$has_pending_claims = ($pending_data['pending_count'] > 0);

// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if($_SERVER['REQUEST_METHOD'] == 'POST') ` so the application can choose the correct business rule branch for the current user action.
if (($_SERVER['REQUEST_METHOD'] ?? '') == 'POST') {
    // SECURITY: Preventing CSRF by validating the Staff claim form token before processing claim data or receipt uploads.
    // Why: Claim submission changes business records and must originate from the legitimate UTMSPACE form.
    if (!requireValidCsrfToken($_POST['csrf_token'] ?? '', $error)) {
        // The shared helper sets a safe user-facing error message.
    } else {
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $claim_type   = $_POST['claim_type'];
    // WHY: Reading the raw amount preserves the exact submitted value so numeric validation can distinguish empty input from invalid text.
    $amount_input = $_POST['amount'] ?? '';
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    // BEST PRACTICE: Converting claim amounts to numeric values ensures financial limit checks use numeric comparison.
    $amount       = is_numeric($amount_input) ? floatval($amount_input) : 0;
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $expense_date = $_POST['date'];
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $description  = trim($_POST['description']);

    // Validate required fields
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if(empty($claim_type) || $amount_input === '' || empty($expense_date) || empty($description)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($claim_type) || $amount_input === '' || empty($expense_date) || empty($description)) {
        $error = "Please fill in all required fields.";
    }
    // CONDITION: Evaluates `elseif(!is_numeric($amount_input) || $amount <= 0) ` so the application can choose the correct business rule branch for the current user action.
    elseif (!is_numeric($amount_input) || $amount <= 0) {
        $error = "Amount must be greater than RM 0.";
    }
    // CONDITION: Evaluates `elseif($has_pending_claims) ` so the application can choose the correct business rule branch for the current user action.
    elseif ($has_pending_claims) {
        $error = "You have pending claims awaiting approval. Please wait before submitting new claims.";
    }
    // CONDITION: Evaluates `elseif($amount > $MAX_AMOUNT_PER_CLAIM) ` so the application can choose the correct business rule branch for the current user action.
    elseif ($amount > $MAX_AMOUNT_PER_CLAIM) {
        // WHY: number_format() displays monetary values with two decimals so financial amounts are consistent.
        $error = "Maximum claim amount per claim is RM " . number_format($MAX_AMOUNT_PER_CLAIM, 2) .
            // WHY: number_format() displays monetary values with two decimals so financial amounts are consistent.
            ". Your claim amount: RM " . number_format($amount, 2);
    }
    // CONDITION: Evaluates `elseif(($total_amount_this_month + $amount) > $MAX_CLAIM_AMOUNT_PER_MONTH) ` so the application can choose the correct business rule branch for the current user action.
    elseif (($total_amount_this_month + $amount) > $MAX_CLAIM_AMOUNT_PER_MONTH) {
        $remaining = $MAX_CLAIM_AMOUNT_PER_MONTH - $total_amount_this_month;
        $error = "Monthly claim limit reached! You have claimed RM " .
            // WHY: number_format() displays monetary values with two decimals so financial amounts are consistent.
            number_format($total_amount_this_month, 2) . " out of RM " .
            // WHY: number_format() displays monetary values with two decimals so financial amounts are consistent.
            number_format($MAX_CLAIM_AMOUNT_PER_MONTH, 2) .
            // WHY: number_format() displays monetary values with two decimals so financial amounts are consistent.
            ". Remaining: RM " . number_format($remaining, 2);
    }
    // CONDITION: Evaluates `elseif($total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ` so the application can choose the correct business rule branch for the current user action.
    elseif ($total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) {
        $error = "You have reached the maximum of " . $MAX_NUMBER_OF_CLAIMS_PER_MONTH .
            " claims for this month.";
    }
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    else {
        $status = 'Pending';

        // === SECTION: HARDENED RECEIPT UPLOAD ===
        // What: Validate and store the optional receipt using the shared upload helper.
        // Why: The helper enforces server-side MIME detection, size limits, random filenames, and upload-folder protections.
        $receipt_filename = saveReceiptUpload($_FILES['receipt'] ?? null, '../uploads/receipts/', $error);

        // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
        // CONDITION: Evaluates `if(empty($error)) ` so the application can choose the correct business rule branch for the current user action.
        if (empty($error)) {
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
            $stmt = $conn->prepare("
                INSERT INTO claims (user_id, claim_type, amount, expense_date, description, receipt, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
            $stmt->bind_param(
                "isdssss",
                $user_id,
                $claim_type,
                $amount,
                $expense_date,
                $description,
                $receipt_filename,
                $status
            );

            // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
            // CONDITION: Evaluates `if($stmt->execute()) ` so the application can choose the correct business rule branch for the current user action.
            if ($stmt->execute()) {
                // SECTION: AUTOMATED FINANCE NOTIFICATION - Captures the new claim reference and informs Finance that a review action is required.
                // WHY: Immediate email notification reduces manual checking and keeps the claim workflow moving after Staff submission.
                $new_claim_id = $stmt->insert_id;

                // SECURITY: Using Prepared Statements to prevent SQL Injection.
                // WHY: SQL is prepared separately from role-filter values so notification routing remains safe and auditable.
                $finance_role = 'finance';
                $finance_stmt = $conn->prepare("SELECT name, email FROM users WHERE role = ? LIMIT 1");
                // SECURITY: Using Prepared Statements to prevent SQL Injection.
                // WHY: bind_param() attaches the Finance role safely instead of embedding workflow criteria directly into executable SQL.
                $finance_stmt->bind_param("s", $finance_role);
                // WHY: Executing the prepared statement retrieves the Finance recipient responsible for the next claim-processing step.
                $finance_stmt->execute();
                // WHY: get_result() turns the recipient query into a readable result set for notification logic.
                $finance_result = $finance_stmt->get_result();

                // CONDITION: Evaluates `if($finance_result->num_rows > 0)` so the application only sends email when a Finance account exists.
                if ($finance_result->num_rows > 0) {
                    // WHY: fetch_assoc() returns Finance recipient data as named fields for clear email construction.
                    $finance_user = $finance_result->fetch_assoc();

                    // SECURITY: Preventing XSS by escaping dynamic claim values before placing them into the HTML email body.
                    // WHY: Email content may render in rich clients, so Staff-entered and database values must remain display-only text.
                    $safe_claim_type = htmlspecialchars($claim_type, ENT_QUOTES, 'UTF-8');
                    $safe_claim_id   = htmlspecialchars((string)$new_claim_id, ENT_QUOTES, 'UTF-8');
                    $safe_amount     = htmlspecialchars(number_format($amount, 2), ENT_QUOTES, 'UTF-8');

                    // SECTION: EMAIL BODY CREATION - Builds a concise Finance action message with the exact claim reference and amount.
                    // WHY: Finance can quickly identify the pending claim and prioritize payment workflow review.
                    $finance_body = '
                        <p style="margin:0 0 14px;">A new staff claim has been submitted and is awaiting Finance review.</p>
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
                        </table>
                        <p style="margin:0;">Please log in to the Finance dashboard to review and process this claim.</p>
                    ';

                    // WHY: sendSystemEmail() centralizes PHPMailer delivery and keeps business modules focused on workflow data.
                    sendSystemEmail($finance_user['email'], $finance_user['name'], 'New Claim Submitted for Finance Review', $finance_body);
                }
                // WHY: Closing the recipient statement releases database resources after the notification routing check completes.
                $finance_stmt->close();

                $success = "Claim submitted successfully! Finance will review your claim.";
                if (function_exists('logActivity')) {
                    // AUDIT: Activity logging creates an accountability trail for key actions such as claim submission, cancellation, and payment.
                    // WHY: number_format() displays monetary values with two decimals so financial amounts are consistent.
                    logActivity($conn, $user_id, 'Submit Claim', "Submitted claim of RM " . number_format($amount, 2) . " for " . $claim_type);
                }
                $total_amount_this_month += $amount;
                $total_claims_this_month++;
                // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
}

$remaining_amount = $MAX_CLAIM_AMOUNT_PER_MONTH - $total_amount_this_month;
$remaining_claims = $MAX_NUMBER_OF_CLAIMS_PER_MONTH - $total_claims_this_month;
$amount_percentage = ($total_amount_this_month / $MAX_CLAIM_AMOUNT_PER_MONTH) * 100;
$claim_percentage = ($total_claims_this_month / $MAX_NUMBER_OF_CLAIMS_PER_MONTH) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Claim - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* STAFF - DARK BLUE THEME WITH LIGHT BACKGROUND */
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --staff-primary: #0f2b4d;
            --staff-secondary: #1e4d8c;
            --staff-accent: #3b82f6;
            --staff-bg: #f0f4f8;
            --staff-card: #ffffff;
            --staff-text: #1e293b;
            --staff-gray: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body {
            background: var(--staff-bg);
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

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar {
            background: linear-gradient(180deg, #0f2b4d 0%, #1e4d8c 100%);
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
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            transform: translateX(5px);
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link.active {
            background: #3b82f6;
            color: #0f2b4d;
            font-weight: 600;
        }

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
            background: #3b82f6;
            border-radius: 10px;
        }

        /* Page Header */
        /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
        .page-header {
            background: linear-gradient(135deg, #0f2b4d 0%, #1e4d8c 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }

        /* Limit Cards with Hover Effects */
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .limit-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .limit-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .limit-card h5 {
            color: #0f2b4d;
            margin-bottom: 15px;
        }

        /* Stat Boxes inside Limit Card */
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box {
            background: #f8fafc;
            border-radius: 15px;
            padding: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box:hover {
            transform: translateY(-3px);
        }

        /* Monthly Budget Box - Blue theme */
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-budget {
            border-left: 4px solid #3b82f6;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-budget:hover {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-budget:hover .stat-value {
            color: #1e40af;
        }

        /* Claims Count Box - Yellow theme */
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-count {
            border-left: 4px solid #f59e0b;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-count:hover {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-count:hover .stat-value {
            color: #b45309;
        }

        /* Per Claim Limit Box - Green theme */
        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-limit {
            border-left: 4px solid #10b981;
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-limit:hover {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        }

        /* SECTION: KPI CARD - Summary panels expose claim counts, payment totals, or limits at a glance for decision-making. */
        .stat-box-limit:hover .stat-value {
            color: #065f46;
        }

        /* WHY: Progress bars convert monthly claim usage into a visual ratio that is easier to interpret than numbers alone. */
        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: #e2e8f0;
        }

        /* WHY: Progress bars convert monthly claim usage into a visual ratio that is easier to interpret than numbers alone. */
        .progress-bar {
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%);
            border-radius: 10px;
        }

        .stat-label-small {
            color: #64748b;
            font-size: 12px;
            margin-top: 5px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #0f2b4d;
            transition: all 0.3s ease;
        }

        .limit-warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 15px;
            border-radius: 10px;
            margin-top: 15px;
            color: #92400e;
            font-size: 14px;
        }

        .limit-danger {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        /* Form Card */
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .form-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-label {
            font-weight: 600;
            color: #0f2b4d;
            margin-bottom: 8px;
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control,
        .form-select {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:focus,
        .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-submit {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
            color: white;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-cancel {
            background: #f1f5f9;
            color: #0f2b4d;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            color: #0f2b4d;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-disabled {
            background: #cbd5e1;
            color: #64748b;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            cursor: not-allowed;
            border: none;
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

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        hr {
            border-color: #e2e8f0;
        }

        .input-group-text {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #0f2b4d;
        }

        .claim-disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        .info-box {
            background: #e8f0fe;
            border-radius: 12px;
            padding: 12px 15px;
            margin-top: 10px;
        }

        .info-box i {
            color: #3b82f6;
            margin-right: 8px;
        }

        /* SECTION: SWEETALERT THEME - Alert styling keeps confirmations and success messages visually consistent with the active role. */
        .swal2-backdrop-show {
            background: rgba(15, 43, 77, 0.6) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
        }

        /* SECTION: SWEETALERT THEME - Alert styling keeps confirmations and success messages visually consistent with the active role. */
        .premium-swal-popup {
            border-radius: 24px !important;
            padding: 2.5em 2em !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }

        /* SECTION: SWEETALERT THEME - Alert styling keeps confirmations and success messages visually consistent with the active role. */
        .premium-swal-progress {
            background: linear-gradient(90deg, #3b82f6 0%, #10b981 100%) !important;
            height: 6px !important;
            border-radius: 10px;
        }

        /* SECTION: RESPONSIVE RULES - These rules adapt sidebars, cards, and tables for smaller screens. */
        @media (max-width: 768px) {

            /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
            .sidebar {
                min-height: auto;
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
                        <i class="fas fa-receipt fs-1" style="color: #3b82f6;"></i>
                        <h5 class="mt-2">UTMSPACE</h5>
                        <small>Staff Portal</small>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <!-- SECTION: ROLE NAVIGATION - Provides role-specific movement between the pages allowed for the current user. -->
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard_Staff.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard</a>
                        <a class="nav-link active" href="New_Claim_Staff.php"><i class="fas fa-plus-circle fa-fw me-2"></i> New Claim</a>
                        <a class="nav-link" href="Claim_History_Staff.php"><i class="fas fa-history fa-fw me-2"></i> Claim History</a>
                        <a class="nav-link" href="Edit_profile_Staff.php"><i class="fas fa-user-edit fa-fw me-2"></i> Edit Profile</a>
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
                            <h3 class="mb-1"><i class="fas fa-plus-circle me-2" style="color: #3b82f6;"></i>Submit New Claim</h3>
                            <p class="mb-0 opacity-75">Fill in the details below to submit your expense claim</p>
                        </div>
                    </div>
                </div>

                <div class="limit-card fade-in">
                    <h5><i class="fas fa-chart-line me-2" style="color: #3b82f6;"></i>Your Monthly Claim Limits</h5>
                    <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                    <div class="row mt-3">
                        <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                        <div class="col-md-4 mb-3">
                            <div class="stat-box stat-box-budget">
                                <div class="stat-label-small">Monthly Budget</div>
                                <div class="stat-value">RM <?php echo number_format($total_amount_this_month, 2); ?> / RM <?php echo number_format($MAX_CLAIM_AMOUNT_PER_MONTH, 2); ?></div>
                                <div class="progress mt-2">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo min($amount_percentage, 100); ?>%"></div>
                                </div>
                                <div class="stat-label-small mt-1">Remaining: RM <?php echo number_format(max($remaining_amount, 0), 2); ?></div>
                            </div>
                        </div>
                        <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                        <div class="col-md-4 mb-3">
                            <div class="stat-box stat-box-count">
                                <div class="stat-label-small">Claims Count</div>
                                <div class="stat-value"><?php echo $total_claims_this_month; ?> / <?php echo $MAX_NUMBER_OF_CLAIMS_PER_MONTH; ?></div>
                                <div class="progress mt-2">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo min($claim_percentage, 100); ?>%"></div>
                                </div>
                                <div class="stat-label-small mt-1">Remaining: <?php echo max($remaining_claims, 0); ?> claims</div>
                            </div>
                        </div>
                        <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                        <div class="col-md-4 mb-3">
                            <div class="stat-box stat-box-limit">
                                <div class="stat-label-small">Per Claim Limit</div>
                                <div class="stat-value">RM <?php echo number_format($MAX_AMOUNT_PER_CLAIM, 2); ?></div>
                                <div class="info-box mt-2">
                                    <i class="fas fa-info-circle"></i>
                                    <small>Maximum amount per single claim</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CONDITION: Evaluates `if($has_pending_claims)` so the application can choose the correct business rule branch for the current user action. -->
                    <?php if ($has_pending_claims): ?>
                        <div class="limit-warning">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Pending Claims Alert!</strong> You have <?php echo $pending_data['pending_count']; ?> pending claim(s) awaiting approval.
                            You cannot submit new claims until they are processed.
                        </div>
                    <?php endif; ?>

                    <!-- CONDITION: Evaluates `if($total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH)` so the application can choose the correct business rule branch for the current user action. -->
                    <?php if ($total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH): ?>
                        <div class="limit-warning limit-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Monthly budget exhausted!</strong> You have reached your monthly claim limit of RM <?php echo number_format($MAX_CLAIM_AMOUNT_PER_MONTH, 2); ?>.
                        </div>
                    <?php endif; ?>

                    <!-- CONDITION: Evaluates `if($total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH)` so the application can choose the correct business rule branch for the current user action. -->
                    <?php if ($total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH): ?>
                        <div class="limit-warning limit-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Monthly claim limit reached!</strong> You have submitted the maximum of <?php echo $MAX_NUMBER_OF_CLAIMS_PER_MONTH; ?> claims this month.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-card fade-in mb-5">
                    <div class="card-body p-4">
                        <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
                        <form method="POST" enctype="multipart/form-data" id="claimForm"
                            <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'class="claim-disabled"' : ''; ?>>
                            <?php echo csrfInputField(); ?>
                            <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                            <div class="row">
                                <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-tag me-1" style="color: #3b82f6;"></i>Claim Type *</label>
                                    <select name="claim_type" class="form-select" required
                                        <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>>
                                        <option value="">Select claim type</option>
                                        <?php
                                        $types = ['Travel', 'Meal', 'Accommodation', 'Transportation', 'Office Supplies', 'Training', 'Medical'];
                                        foreach ($types as $t) {
                                            // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
                                            $sel = (($_POST['claim_type'] ?? '') == $t) ? 'selected' : '';
                                            echo "<option value=\"$t\" $sel>$t</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-dollar-sign me-1" style="color: #3b82f6;"></i>Amount (RM) *</label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text">RM</span>
                                        <!-- SECURITY: Preventing XSS by escaping the previously submitted amount before redisplaying it in the form value. -->
                                        <!-- WHY: Keeping the amount value after validation failure improves UX while preventing stored or reflected script injection. -->
                                        <input type="number" name="amount" id="claimAmount" step="0.01" min="0.01"
                                            max="200"
                                            class="form-control" required placeholder="0.00"
                                            value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                                            <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>>
                                        <!-- SECTION: REAL-TIME VALIDATION FEEDBACK - Provides immediate Bootstrap feedback for invalid claim amounts. -->
                                        <!-- WHY: Inline validation prevents avoidable submissions and helps Staff correct amount issues before server-side processing. -->
                                        <div id="amountFeedback" class="invalid-feedback"></div>
                                    </div>
                                    <small class="text-muted">Maximum: RM <?php echo number_format($MAX_AMOUNT_PER_CLAIM, 2); ?> per claim</small>
                                </div>

                                <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-calendar me-1" style="color: #3b82f6;"></i>Expense Date *</label>
                                    <!-- SECURITY: Preventing XSS by escaping the submitted date before redisplaying it in the form value. -->
                                    <!-- WHY: Staff can correct validation errors without losing the selected expense date, while the output remains display-only. -->
                                    <input type="date" name="date" class="form-control" required
                                        max="<?php echo date('Y-m-d'); ?>"
                                        value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>"
                                        <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>>
                                </div>

                                <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-paperclip me-1" style="color: #3b82f6;"></i>Attach Receipt</label>
                                    <!-- SECURITY: Receipt upload input is limited in the browser, while the PHP backend performs the authoritative validation. -->
                                    <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png"
                                        id="receiptFile" onchange="previewFile(this)"
                                        <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>>
                                    <small class="text-muted">Accepted: PDF, JPG, PNG (max 5MB)</small>
                                    <div id="filePreview" class="mt-2" style="display:none;">
                                        <img id="imgPreview" src="" alt="Preview" class="img-thumbnail" style="max-height:120px;">
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label"><i class="fas fa-align-left me-1" style="color: #3b82f6;"></i>Description *</label>
                                    <!-- SECURITY: Preventing XSS by escaping the submitted description before redisplaying it in the textarea. -->
                                    <!-- WHY: Staff-entered descriptions may contain special characters and must not become executable markup after validation feedback. -->
                                    <textarea name="description" rows="4" class="form-control" required
                                        placeholder="Describe your expense in detail (e.g., purpose, date, location)..."
                                        <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex gap-3">
                                <!-- CONDITION: Evaluates `if($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH)` so the application can choose the correct business rule branch for the current user action. -->
                                <?php if ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH): ?>
                                    <button type="button" class="btn btn-disabled"><i class="fas fa-ban me-2"></i>Claim Submission Disabled</button>
                                    <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                                <?php else: ?>
                                    <button type="submit" name="action" value="submit" id="submitClaimButton" class="btn btn-submit"><i class="fas fa-paper-plane me-2"></i>Submit Claim</button>
                                <?php endif; ?>
                                <a href="dashboard_Staff.php" class="btn btn-cancel"><i class="fas fa-times me-2"></i>Cancel</a>
                            </div>
                        </form>
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
        function previewFile(input) {
            const preview = document.getElementById('filePreview');
            const img = document.getElementById('imgPreview');
            // CONDITION: Evaluates `if(input.files && input.files[0]) ` so the application can choose the correct business rule branch for the current user action.
            if (input.files && input.files[0]) {
                const file = input.files[0];
                // WHY: Image-only preview logic avoids trying to render PDFs as image thumbnails.
                // CONDITION: Evaluates `if(file.type.startsWith('image/')) ` so the application can choose the correct business rule branch for the current user action.
                if (file.type.startsWith('image/')) {
                    // WHY: FileReader previews image receipts locally so staff can confirm the correct evidence before submission.
                    const reader = new FileReader();
                    reader.onload = e => {
                        img.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    // WHY: readAsDataURL converts a selected image into a browser-previewable source without uploading it first.
                    reader.readAsDataURL(file);
                    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
                } else {
                    preview.style.display = 'none';
                }
                // SECURITY: Client-side file-size checking warns users early, while server-side validation remains authoritative.
                // CONDITION: Evaluates `if(file.size > 5 * 1024 * 1024) ` so the application can choose the correct business rule branch for the current user action.
                if (file.size > 5 * 1024 * 1024) {
                    // SECTION: SWEETALERT FEEDBACK - Shows high-visibility validation messages without using standard browser alert boxes.
                    // WHY: SweetAlert2 keeps the warning consistent with the UTMSPACE user experience standards.
                    Swal.fire({
                        icon: 'warning',
                        title: 'Receipt Too Large',
                        text: 'File is too large. Maximum size is 5MB.',
                        confirmButtonColor: '#3b82f6'
                    });
                    input.value = '';
                    preview.style.display = 'none';
                }
            }
        }

        // SECTION: REAL-TIME AMOUNT VALIDATION - Watches the amount field and applies Bootstrap validation states immediately.
        // WHY: Staff receive instant, precise guidance and the Submit button remains disabled until the amount satisfies claim policy.
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('claimAmount');
            const amountFeedback = document.getElementById('amountFeedback');
            const submitButton = document.getElementById('submitClaimButton');
            const maxAmount = 200;

            // SECTION: VALIDATION STATE HELPER - Applies one consistent UI state for invalid and valid amount outcomes.
            // WHY: Centralizing class and button changes prevents duplicated logic and keeps the form response predictable.
            function setAmountValidationState(isValid, message) {
                // CONDITION: Evaluates `if(!amountInput || !amountFeedback || !submitButton)` so disabled workflows do not execute validation logic unnecessarily.
                if (!amountInput || !amountFeedback || !submitButton) {
                    return;
                }

                // CONDITION: Evaluates `if(isValid)` so the application can choose the correct visual and business state for the amount field.
                if (isValid) {
                    amountInput.classList.remove('is-invalid');
                    amountInput.classList.add('is-valid');
                    amountInput.setCustomValidity('');
                    amountFeedback.textContent = '';
                    submitButton.disabled = false;
                    // CONDITION: This fallback executes when the amount violates the client-side policy rule.
                } else {
                    amountInput.classList.remove('is-valid');
                    amountInput.classList.add('is-invalid');
                    amountInput.setCustomValidity(message);
                    amountFeedback.textContent = message;
                    submitButton.disabled = true;
                }
            }

            // SECTION: AMOUNT BUSINESS RULES - Enforces the user-facing RM 0 and RM 200 policy boundaries before form submission.
            // WHY: Early validation improves UX, reduces avoidable server requests, and protects Finance from invalid claim amounts.
            function validateAmountInput() {
                const amountValue = parseFloat(amountInput.value);

                // CONDITION: Evaluates `if(amountInput.value === '' || Number.isNaN(amountValue) || amountValue <= 0)` to block empty or non-positive claim values.
                if (amountInput.value === '' || Number.isNaN(amountValue) || amountValue <= 0) {
                    setAmountValidationState(false, 'Amount must be greater than RM 0');
                    return;
                }

                // CONDITION: Evaluates `if(amountValue > maxAmount)` to block claims above the configured policy ceiling.
                if (amountValue > maxAmount) {
                    setAmountValidationState(false, 'Policy Alert: Maximum claim per transaction is RM 200.');
                    return;
                }

                setAmountValidationState(true, '');
            }

            // CONDITION: Evaluates `if(amountInput && submitButton)` so real-time validation is attached only on active claim forms.
            if (amountInput && submitButton) {
                // WHY: The input event reacts on every change so Staff do not need to submit the form to discover amount problems.
                amountInput.addEventListener('input', validateAmountInput);
                // WHY: Initial validation disables Submit on an empty form, preventing accidental incomplete submissions.
                validateAmountInput();
            }
        });

        // CONDITION: Evaluates `if($success)` so the application can choose the correct business rule branch for the current user action.
        <?php if ($success): ?>
            // WHY: The countdown timer keeps users informed during automatic claim-history redirection.
            let timerInterval;
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                title: 'Submission Successful!',
                html: '<div class="mt-2 text-muted" style="font-size: 1.05rem;">Your expense claim has been successfully sent to Finance and is awaiting review.</div>' +
                    '<div class="mt-4 p-3 rounded-3" style="background: #f8fafc; border: 1px dashed #cbd5e1;">' +
                    '<i class="fas fa-rocket me-2" style="color: #3b82f6;"></i> Redirecting to Claim History in <b><span id="swal-timer" style="color: #0f2b4d; font-size: 1.2rem;">3</span></b> seconds...' +
                    '</div>',
                icon: 'success',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                color: '#0f2b4d',
                iconColor: '#10b981',
                customClass: {
                    popup: 'premium-swal-popup',
                    timerProgressBar: 'premium-swal-progress'
                },
                didOpen: () => {
                    const b = Swal.getHtmlContainer().querySelector('#swal-timer');
                    // WHY: The countdown timer keeps users informed during automatic claim-history redirection.
                    timerInterval = setInterval(() => {
                        b.textContent = Math.ceil(Swal.getTimerLeft() / 1000);
                    }, 100);
                },
                willClose: () => {
                    // WHY: The countdown timer keeps users informed during automatic claim-history redirection.
                    clearInterval(timerInterval);
                }
            }).then((result) => {
                // CONDITION: Evaluates `if (result.dismiss === Swal.DismissReason.timer) ` so the application can choose the correct business rule branch for the current user action.
                if (result.dismiss === Swal.DismissReason.timer) {
                    // WHY: Redirecting after success moves the user to the next logical workflow page without manual navigation.
                    window.location.href = 'Claim_History_Staff.php';
                }
            });
            // CONDITION: Evaluates `elseif($error)` so the application can choose the correct business rule branch for the current user action.
        <?php elseif ($error): ?>
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#3b82f6',
                showClass: {
                    popup: 'animate__animated animate__shakeX'
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>
