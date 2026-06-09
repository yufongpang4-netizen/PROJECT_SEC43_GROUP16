<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: payment_success.php verifies a completed Stripe Checkout Session and records the claim as Paid.
// The following comments explain what each block does and why the database update
// must occur only after Stripe confirms a successful sandbox payment.
// ============================================================================

// === SECTION: SESSION INITIALIZATION ===
// What: Resume the Finance session that originally created the Stripe Checkout Session.
// Why: The server-side payment token stored in session links the Stripe redirect to the approved claim safely.
session_start();

// === SECTION: FINANCE ACCESS CONTROL ===
// What: Permit only authenticated Finance users to finalize a Stripe payment redirect.
// Why: Claim payment status is a Finance-only workflow and must not be reachable by Staff or Admin sessions.
// SECURITY: Preventing broken access control by enforcing role validation before any payment verification occurs.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'finance') {
    header("Location: ../login.php");
    exit();
}

// === SECTION: DEPENDENCY LOADING ===
// What: Load database and email helpers used after Stripe verification.
// Why: Successful payment must update claims securely and preserve the existing Staff notification workflow.
require_once '../db.php';
require_once '../mailer_helper.php';

// === SECTION: STRIPE SANDBOX CONFIGURATION ===
// What: Store the Stripe test secret key placeholder used to retrieve the Checkout Session.
// Why: Server-side verification must use the secret key so browser URL data alone cannot mark a claim as Paid.
// SECURITY: Replace this placeholder with the same Stripe test secret key used in process_payment_Finance.php.
$stripe_secret_key = 'sk_test_51TgPf4LDkNzP5xzkoOFPgxkCysx5OtIhRUQkBqbMK3HhaexFIadF82Q9rLny4MA3oxE8DkjMkcFJOBCXLvTF998y00vkGP1YcB';

// === SECTION: RESULT PAGE RENDERER ===
// What: Show a SweetAlert result and redirect Finance back to the dashboard.
// Why: Stripe redirect pages should provide clear feedback and then return users to the claim workflow.
function renderStripeResult($icon, $title, $message, $redirect_url = 'dashboard_Finance.php')
{
    // SECURITY: Preventing XSS by escaping result strings before embedding them into JavaScript.
    $safe_icon = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
    $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safe_redirect = htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8');
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Result - UTMSPACE</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>

    <body>
        <script>
            // === SECTION: SWEETALERT PAYMENT RESULT ===
            // What: Present payment verification status after Stripe redirects back to UTMSPACE.
            // Why: Finance receives an immediate explanation and is returned to the dashboard without manual navigation.
            Swal.fire({
                icon: '<?php echo $safe_icon; ?>',
                title: '<?php echo $safe_title; ?>',
                text: '<?php echo $safe_message; ?>',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Return to Dashboard',
                allowOutsideClick: false
            }).then(() => {
                window.location.href = '<?php echo $safe_redirect; ?>';
            });
        </script>
    </body>

    </html>
<?php
    exit();
}

// === SECTION: STRIPE SESSION RETRIEVAL HELPER ===
// What: Retrieve a Checkout Session from Stripe using pure PHP cURL.
// Why: The project avoids Composer and the Stripe SDK for shared-hosting compatibility.
function retrieveStripeCheckoutSession($stripe_secret_key, $session_id, &$error_message)
{
    if (!function_exists('curl_init')) {
        $error_message = 'PHP cURL is not enabled on this hosting environment.';
        return null;
    }

    $url = 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($session_id);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $stripe_secret_key . ':',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        $error_message = 'Stripe connection failed: ' . $curl_error;
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        $error_message = 'Stripe returned an unreadable response.';
        return null;
    }

    if ($http_code < 200 || $http_code >= 300) {
        $error_message = $decoded['error']['message'] ?? 'Stripe Checkout Session could not be verified.';
        return null;
    }

    return $decoded;
}

// === SECTION: PAYMENT NOTIFICATION HELPER ===
// What: Notify the Staff member after Stripe payment is verified and the database status is updated.
// Why: Staff should receive confirmation that reimbursement is complete without repeatedly checking claim history.
function sendStripePaymentEmail($conn, $claim_id, $stripe_session_id)
{
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // Why: The claim owner lookup binds the verified numeric claim ID instead of embedding request values into SQL.
    $stmt = $conn->prepare("
        SELECT c.id, c.amount, c.claim_type, u.name, u.email
        FROM claims c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $claim_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipient = $result->fetch_assoc();
    $stmt->close();

    if (!$recipient) {
        return false;
    }

    // SECURITY: Preventing XSS by escaping payment values before placing them into an HTML email body.
    // Why: Email content must treat database values as display-only text.
    $safe_claim_id = htmlspecialchars((string) $recipient['id'], ENT_QUOTES, 'UTF-8');
    $safe_claim_type = htmlspecialchars($recipient['claim_type'], ENT_QUOTES, 'UTF-8');
    $safe_amount = htmlspecialchars(number_format((float) $recipient['amount'], 2), ENT_QUOTES, 'UTF-8');
    $safe_session_id = htmlspecialchars($stripe_session_id, ENT_QUOTES, 'UTF-8');

    // === SECTION: EMAIL BODY CREATION ===
    // What: Build a payment-success email containing both claim and Stripe sandbox references.
    // Why: Staff receive a clear audit-friendly confirmation that Finance completed payment through Stripe Checkout.
    $body = '
        <p style="margin:0 0 14px;">Your claim reimbursement has been successfully processed through Stripe Checkout Sandbox.</p>
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
            <tr>
                <td style="padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; font-weight:700;">Stripe Session</td>
                <td style="padding:10px 12px; border:1px solid #e2e8f0;">' . $safe_session_id . '</td>
            </tr>
        </table>
        <p style="margin:0;">Please log in to the Staff dashboard to review your claim history.</p>
    ';

    return sendSystemEmail($recipient['email'], $recipient['name'], 'Payment Successful for Claim #' . $recipient['id'], $body);
}

// === SECTION: REDIRECT PARAMETER VALIDATION ===
// What: Validate the Stripe session ID and server payment token returned in the success URL.
// Why: URL parameters are user-controlled and must not directly decide which claim becomes Paid.
// SECURITY: Rejecting malformed redirect values prevents unnecessary Stripe lookups and token probing.
$session_id = trim($_GET['session_id'] ?? '');
$payment_token = trim($_GET['payment_token'] ?? '');

if (!preg_match('/^cs_(test|live)_[A-Za-z0-9_]+$/', $session_id)) {
    renderStripeResult('error', 'Invalid Stripe Session', 'The payment session reference is missing or invalid.', 'All_Claim_Finance.php?status=Approved');
}

if (!preg_match('/^[a-f0-9]{64}$/', $payment_token)) {
    renderStripeResult('error', 'Invalid Payment Token', 'The payment token is missing or invalid.', 'All_Claim_Finance.php?status=Approved');
}

// === SECTION: SERVER-SIDE PAYMENT TOKEN CHECK ===
// What: Confirm that the payment token was created by process_payment_Finance.php in this Finance session.
// Why: The claim ID is recovered from server session data instead of trusting a browser-supplied claim_id.
// SECURITY: Preventing tampered claim updates by requiring a matching server-side payment token.
$pending_payments = $_SESSION['stripe_payment_claims'] ?? [];
$pending = $pending_payments[$payment_token] ?? null;

if (!$pending || (time() - (int) ($pending['created_at'] ?? 0)) > 1800) {
    unset($_SESSION['stripe_payment_claims'][$payment_token]);
    renderStripeResult('error', 'Payment Session Expired', 'Please start the payment again from the approved claim list.', 'All_Claim_Finance.php?status=Approved');
}

$claim_id = (int) $pending['claim_id'];
$expected_amount_cents = (int) $pending['amount_cents'];
$expected_currency = strtolower($pending['currency'] ?? 'myr');

// === SECTION: DATABASE CLAIM RECHECK ===
// What: Confirm the claim is still Approved before accepting Stripe payment success.
// Why: A claim may have changed status while the user was on the Stripe Checkout page.
// SECURITY: Using Prepared Statements to prevent SQL Injection.
$claim_stmt = $conn->prepare("SELECT id, amount, status FROM claims WHERE id = ? LIMIT 1");
$claim_stmt->bind_param("i", $claim_id);
$claim_stmt->execute();
$claim_result = $claim_stmt->get_result();
$claim = $claim_result->fetch_assoc();
$claim_stmt->close();

if (!$claim) {
    unset($_SESSION['stripe_payment_claims'][$payment_token]);
    renderStripeResult('error', 'Claim Not Found', 'The claim linked to this payment no longer exists.', 'All_Claim_Finance.php?status=Approved');
}

if ($claim['status'] === 'Paid') {
    unset($_SESSION['stripe_payment_claims'][$payment_token]);
    renderStripeResult('success', 'Payment Already Recorded', 'This claim was already marked as Paid.', 'dashboard_Finance.php');
}

if ($claim['status'] !== 'Approved') {
    unset($_SESSION['stripe_payment_claims'][$payment_token]);
    renderStripeResult('error', 'Claim Status Changed', 'Only approved claims can be marked as Paid.', 'All_Claim_Finance.php?status=Approved');
}

// === SECTION: STRIPE PAYMENT VERIFICATION ===
// What: Retrieve the Checkout Session from Stripe and verify payment status, claim metadata, amount, and currency.
// Why: The database update is allowed only when Stripe confirms the exact expected payment.
$error = '';
$stripe_session = retrieveStripeCheckoutSession($stripe_secret_key, $session_id, $error);
if (!$stripe_session) {
    renderStripeResult('error', 'Stripe Verification Failed', $error, 'All_Claim_Finance.php?status=Approved');
}

$stripe_claim_id = (int) ($stripe_session['metadata']['claim_id'] ?? 0);
$stripe_reference_id = (int) ($stripe_session['client_reference_id'] ?? 0);
$stripe_amount = (int) ($stripe_session['amount_total'] ?? 0);
$stripe_currency = strtolower($stripe_session['currency'] ?? '');
$payment_status = strtolower($stripe_session['payment_status'] ?? '');

if (
    $payment_status !== 'paid' ||
    $stripe_claim_id !== $claim_id ||
    $stripe_reference_id !== $claim_id ||
    $stripe_amount !== $expected_amount_cents ||
    $stripe_currency !== $expected_currency
) {
    renderStripeResult('error', 'Payment Verification Failed', 'Stripe did not confirm the expected claim payment details.', 'All_Claim_Finance.php?status=Approved');
}

// === SECTION: VERIFIED PAYMENT DATABASE UPDATE ===
// What: Mark the claim as Paid only after all Stripe verification checks pass.
// Why: This prevents direct database updates unless Stripe confirms a successful sandbox payment for the expected claim.
// SECURITY: Using Prepared Statements to prevent SQL Injection.
$update_stmt = $conn->prepare("UPDATE claims SET status = 'Paid' WHERE id = ? AND status = 'Approved'");
$update_stmt->bind_param("i", $claim_id);
$update_stmt->execute();
$affected_rows = $update_stmt->affected_rows;
$update_stmt->close();

unset($_SESSION['stripe_payment_claims'][$payment_token]);

if ($affected_rows < 1) {
    renderStripeResult('error', 'Payment Not Recorded', 'Stripe payment was verified, but the claim status could not be updated.', 'All_Claim_Finance.php?status=Approved');
}

// === SECTION: AUDIT AND EMAIL NOTIFICATION ===
// What: Log the verified Stripe payment and notify the Staff claim owner.
// Why: Finance actions should leave an accountability trail and close the reimbursement communication loop.
if (function_exists('logActivity')) {
    logActivity($conn, $_SESSION['user_id'], 'Stripe Payment', "Verified Stripe Checkout payment for claim #$claim_id using session $session_id");
}

sendStripePaymentEmail($conn, $claim_id, $session_id);

renderStripeResult('success', 'Stripe Payment Successful', 'The payment was verified and the claim has been marked as Paid.', 'dashboard_Finance.php');
