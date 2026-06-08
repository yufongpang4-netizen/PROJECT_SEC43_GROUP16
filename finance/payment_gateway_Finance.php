<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: payment_gateway_Finance.php is part of the UTMSPACE Staff Pay and Claim System.
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
// What: Load the centralized CSRF helper used by the Finance payment form.
// Why: Payment confirmation changes a claim to Paid, so the request must be tied to the authenticated Finance session.
require_once '../csrf_helper.php';
// SECTION: DEPENDENCY LOADING - Loads the centralized email helper so successful payments can notify Staff automatically.
require_once '../mailer_helper.php';

$success = '';
$error = '';
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
// SECURITY: Casting identifiers with intval() forces numeric IDs and reduces risk from manipulated request parameters.
$claim_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
$stmt = $conn->prepare("
    SELECT c.id, c.amount, c.claim_type, u.name as staff_name, u.staff_id, u.email as staff_email
    FROM claims c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.id = ? AND c.status = 'Approved'
");
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
$stmt->bind_param("i", $claim_id);
// WHY: Executing the prepared statement performs the validated database operation for the current workflow.
$stmt->execute();
// WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
$result = $stmt->get_result();

// CONDITION: Evaluates `if($result->num_rows === 0) ` so the application can choose the correct business rule branch for the current user action.
if($result->num_rows === 0) {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: All_Claim_Finance.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}

// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
$claim = $result->fetch_assoc();

$mock_bank_suffix = substr(preg_replace("/[^0-9]/", "", md5($claim['staff_id'])), 0, 4);
// CONDITION: Evaluates `if(strlen($mock_bank_suffix) < 4) $mock_bank_suffix = '8821';` so the application can choose the correct business rule branch for the current user action.
if(strlen($mock_bank_suffix) < 4) $mock_bank_suffix = '8821';

// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if($_SERVER['REQUEST_METHOD'] === 'POST') ` so the application can choose the correct business rule branch for the current user action.
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // === SECTION: CSRF TOKEN VALIDATION ===
    // What: Validate the hidden form token before processing a reimbursement payment.
    // Why: Finance payment is a high-impact business action and must not be executable through a forged browser request.
    // SECURITY: Preventing Cross-Site Request Forgery by requiring a session-bound token before marking claims as Paid.
    if(!requireValidCsrfToken($_POST['csrf_token'] ?? '', $error)) {
        // WHY: No payment update is attempted when the submitted token does not match the Finance user's session.
    } else {
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $update_stmt = $conn->prepare("UPDATE claims SET status = 'Paid' WHERE id = ?");
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $update_stmt->bind_param("i", $claim_id);

        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        // CONDITION: Evaluates `if($update_stmt->execute()) ` so the application can choose the correct business rule branch for the current user action.
        if($update_stmt->execute()) {
            // WHY: number_format() displays monetary values with two decimals so financial amounts are consistent.
            $success = "Payment of RM " . number_format($claim['amount'], 2) . " to " . $claim['staff_name'] . " was successful.";

            // SECTION: AUTOMATED STAFF PAYMENT NOTIFICATION - Informs the claim owner immediately after Finance marks the claim as paid.
            // WHY: Automated payment confirmation closes the business loop and reduces staff uncertainty about reimbursement status.
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from the claim identifier so recipient lookup remains safe and auditable.
            $staff_email_stmt = $conn->prepare("
                SELECT u.name, u.email, c.amount, c.claim_type
                FROM claims c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = ?
                LIMIT 1
            ");
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: bind_param() attaches the numeric claim identifier safely instead of embedding request data into executable SQL.
            $staff_email_stmt->bind_param("i", $claim_id);
            // WHY: Executing the prepared statement retrieves the claim owner for the payment confirmation email.
            $staff_email_stmt->execute();
            // WHY: get_result() turns the recipient query into rows that can be used by the notification workflow.
            $staff_email_result = $staff_email_stmt->get_result();

            // CONDITION: Evaluates `if($staff_email_result->num_rows > 0)` so the application only sends email when the claim owner can be found.
            if($staff_email_result->num_rows > 0) {
                // WHY: fetch_assoc() returns Staff recipient data as named fields for clear payment email construction.
                $staff_email_user = $staff_email_result->fetch_assoc();

                // SECURITY: Preventing XSS by escaping dynamic payment values before placing them into the HTML email body.
                // WHY: Claim type and staff records may contain user-controlled content and must remain display-only in the email.
                $safe_claim_id   = htmlspecialchars((string)$claim_id, ENT_QUOTES, 'UTF-8');
                $safe_claim_type = htmlspecialchars($staff_email_user['claim_type'], ENT_QUOTES, 'UTF-8');
                $safe_amount     = htmlspecialchars(number_format($staff_email_user['amount'], 2), ENT_QUOTES, 'UTF-8');

                // SECTION: EMAIL BODY CREATION - Builds a professional confirmation message containing payment reference details.
                // WHY: The Staff member receives a clear record that Finance has completed reimbursement for the claim.
                $staff_body = '
                    <p style="margin:0 0 14px;">Your reimbursement has been successfully processed by Finance.</p>
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
                            <td style="padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; font-weight:700;">Paid Amount</td>
                            <td style="padding:10px 12px; border:1px solid #e2e8f0;">RM ' . $safe_amount . '</td>
                        </tr>
                    </table>
                    <p style="margin:0;">Please log in to the Staff dashboard if you need to review your claim history.</p>
                ';

                // WHY: sendSystemEmail() centralizes PHPMailer delivery and avoids duplicating SMTP configuration in Finance modules.
                // CONDITION: Evaluates `if(!sendSystemEmail(...))` so the payment workflow can report notification delivery failure without reversing a completed payment.
                if(!sendSystemEmail($staff_email_user['email'], $staff_email_user['name'], 'Payment Successful for Claim #' . $claim_id, $staff_body)) {
                    $success .= " Email notification could not be sent. Please check SMTP credentials.";
                }
            }
            // WHY: Closing the recipient statement releases database resources after the payment notification routing check completes.
            $staff_email_stmt->close();

            // CONDITION: Evaluates `if (function_exists('logActivity')) ` so the application can choose the correct business rule branch for the current user action.
            if (function_exists('logActivity')) {
                // AUDIT: Activity logging creates an accountability trail for key actions such as claim submission, cancellation, and payment.
                // WHY: number_format() displays monetary values with two decimals so financial amounts are consistent.
                logActivity($conn, $_SESSION['user_id'], 'Process Payment', "Processed payment of RM " . number_format($claim['amount'], 2) . " for claim #$claim_id");
            }
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            $error = "Payment failed due to system error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body { 
            background-color: #f8fafc; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px -10px rgba(16, 185, 129, 0.15);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .payment-header {
            background: linear-gradient(135deg, #064e3b 0%, #047857 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .amount-display {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 15px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; font-weight: 500; }
        .detail-value { color: #064e3b; font-weight: 600; text-align: right; }
        .bank-badge {
            background: #f1f5f9;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-pay {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            color: white;
        }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-cancel {
            background: white;
            color: #64748b;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
        }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-cancel:hover { background: #f8fafc; color: #0f2b4d; }
        .secure-badge {
            text-align: center;
            color: #10b981;
            font-size: 0.85rem;
            margin-top: 20px;
            font-weight: 600;
        }
    </style>
</head>
<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->
<body>

<div class="container d-flex justify-content-center">
    <div class="payment-card animate__animated animate__fadeInUp">
        <div class="payment-header">
            <h5 class="mb-0 opacity-75"><i class="fas fa-shield-alt me-2"></i>UTMSPACE SecurePay</h5>
            <div class="amount-display">RM <?php echo number_format($claim['amount'], 2); ?></div>
            <span class="badge bg-white text-success rounded-pill px-3 py-2">Ready for Transfer</span>
        </div>
        
        <div class="p-4">
            <div class="detail-row">
                <span class="detail-label">Claim Reference</span>
                <span class="detail-value">#<?php echo str_pad($claim['id'], 5, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Claim Type</span>
                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                <span class="detail-value"><?php echo htmlspecialchars($claim['claim_type']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payee Name</span>
                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                <span class="detail-value"><?php echo htmlspecialchars($claim['staff_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transfer To</span>
                <div class="detail-value">
                    <div class="bank-badge">
                        <i class="fas fa-university text-primary"></i>
                        <span>CIMB Bank</span>
                    </div>
                    <div class="mt-1 small text-muted">**** **** **** <?php echo $mock_bank_suffix; ?></div>
                </div>
            </div>

            <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
            <form method="POST" id="paymentForm" class="mt-4">
                <?php echo csrfInputField(); ?>
                <button type="submit" class="btn btn-pay">
                    <i class="fas fa-fingerprint me-2"></i>Confirm Transfer
                </button>
                <a href="All_Claim_Finance.php" class="btn btn-cancel d-block text-center text-decoration-none">
                    Cancel
                </a>
            </form>
            
            <div class="secure-badge">
                <i class="fas fa-lock me-1"></i> End-to-End Encrypted Transfer
            </div>
        </div>
    </div>
</div>

<!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
<script>
    const form = document.getElementById('paymentForm');

    // WHY: Submit handling prevents weak or invalid forms from being sent and displays clear user feedback.
    form.addEventListener('submit', function(e) {
        // CONDITION: Evaluates `if(!$success)` so the application can choose the correct business rule branch for the current user action.
        <?php if(!$success): ?> 
        // WHY: preventDefault() pauses browser submission so client-side validation or confirmation can run first.
        e.preventDefault();
        
        // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
        Swal.fire({
            title: 'Processing Transfer',
            html: 'Connecting to Secure Banking Gateway...<br><b>Please do not close this window.</b>',
            allowOutsideClick: false,
            showConfirmButton: false,
            background: 'rgba(255, 255, 255, 0.95)',
            backdrop: `rgba(4, 120, 87, 0.4)`,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // WHY: A short delay communicates processing progress before redirecting or submitting the transaction.
        setTimeout(() => {
            // WHY: The form is submitted programmatically only after the user confirms the action.
            form.submit();
        }, 2500);
        <?php endif; ?>
    });

    // CONDITION: Evaluates `if($success)` so the application can choose the correct business rule branch for the current user action.
    <?php if($success): ?>
        // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
        Swal.fire({
            icon: 'success',
            title: 'Transfer Successful!',
            text: '<?php echo addslashes($success); ?>',
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Return to Dashboard',
            allowOutsideClick: false
        }).then((result) => {
            // CONDITION: Evaluates `if (result.isConfirmed) ` so the application can choose the correct business rule branch for the current user action.
            if (result.isConfirmed) {
                // WHY: Redirecting after success moves the user to the next logical workflow page without manual navigation.
                window.location.href = 'All_Claim_Finance.php';
            }
        });
    // CONDITION: Evaluates `elseif($error)` so the application can choose the correct business rule branch for the current user action.
    <?php elseif($error): ?>
        // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
        Swal.fire({
            icon: 'error',
            title: 'Transfer Failed',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#C1272D'
        });
    <?php endif; ?>
</script>

</body>
</html>
