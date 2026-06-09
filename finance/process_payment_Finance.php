<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: process_payment_Finance.php creates a Stripe Checkout Session for an approved claim.
// The following comments explain what each block does and why it protects the
// Finance payment workflow, claim status integrity, and examiner-readable security design.
// ============================================================================

// === SECTION: SESSION INITIALIZATION ===
// What: Start the PHP session so the system can identify the authenticated Finance user and store the pending Stripe payment token.
// Why: Stripe returns through a browser redirect, so the claim reference must be tied back to the same Finance session.
session_start();

// === SECTION: FINANCE ACCESS CONTROL ===
// What: Allow only authenticated Finance users to create Stripe Checkout Sessions.
// Why: Staff and Admin users must not be able to initiate payment processing from the Finance workflow.
// SECURITY: Preventing broken access control by enforcing role-based authorization before any payment logic executes.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'finance') {
    header("Location: ../login.php");
    exit();
}

// === SECTION: DEPENDENCY LOADING ===
// What: Load the shared database connection and CSRF helper.
// Why: Payment initiation needs prepared statements for claim lookup and the existing CSRF control for trusted form submission.
require_once '../db.php';
require_once '../csrf_helper.php';

// === SECTION: STRIPE SANDBOX CONFIGURATION ===
// What: Store the Stripe test secret key placeholder used by the cURL API request.
// Why: The project runs on shared hosting without Composer, so this file calls Stripe directly using PHP cURL.
// SECURITY: Replace this value with a Stripe test secret key from the Stripe Dashboard; never expose the secret key in client-side code.
$stripe_secret_key = 'sk_test_51TgPf4LDkNzP5xzkoOFPgxkCysx5OtIhRUQkBqbMK3HhaexFIadF82Q9rLny4MA3oxE8DkjMkcFJOBCXLvTF998y00vkGP1YcB';

// === SECTION: USER-FACING RESULT PAGE ===
// What: Render a SweetAlert error page when checkout cannot be started.
// Why: Finance users should receive clear feedback without seeing raw Stripe or PHP errors.
function renderStripeStartError($title, $message)
{
    // SECURITY: Preventing XSS by escaping server-generated messages before inserting them into JavaScript strings.
    $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Stripe Checkout Error - UTMSPACE</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>

    <body>
        <script>
            // === SECTION: SWEETALERT CHECKOUT ERROR ===
            // What: Show a clear payment-start failure and return Finance to approved claims.
            // Why: A guided redirect keeps the user inside the correct payment workflow after an API or validation failure.
            Swal.fire({
                icon: 'error',
                title: '<?php echo $safe_title; ?>',
                text: '<?php echo $safe_message; ?>',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Back to Claims'
            }).then(() => {
                window.location.href = 'All_Claim_Finance.php?status=Approved';
            });
        </script>
    </body>

    </html>
<?php
    exit();
}

// === SECTION: STRIPE API REQUEST HELPER ===
// What: Send a form-encoded POST request to Stripe Checkout Session API using cURL.
// Why: InfinityFree shared hosting may not support Composer, so direct HTTPS API calls keep the integration deployable.
function createStripeCheckoutSession($stripe_secret_key, array $payload, &$error_message)
{
    // SECURITY: Confirm cURL is available before attempting an external payment API call.
    // Why: A missing PHP cURL extension would otherwise produce unclear runtime failures during the Finance demo.
    if (!function_exists('curl_init')) {
        $error_message = 'PHP cURL is not enabled on this hosting environment.';
        return null;
    }

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_USERPWD => $stripe_secret_key . ':',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
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

    if ($http_code < 200 || $http_code >= 300 || empty($decoded['url'])) {
        $error_message = $decoded['error']['message'] ?? 'Stripe Checkout Session could not be created.';
        return null;
    }

    return $decoded;
}

// === SECTION: REQUEST METHOD VALIDATION ===
// What: Accept only POST requests from protected Finance payment forms.
// Why: Payment sessions must not be created by bookmarkable GET URLs.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: All_Claim_Finance.php?status=Approved");
    exit();
}

// === SECTION: CSRF TOKEN VALIDATION ===
// What: Verify the CSRF token before reading claim payment input.
// Why: A forged external page must not be able to start a Stripe payment for a logged-in Finance user.
// SECURITY: Preventing Cross-Site Request Forgery by requiring the existing session-bound Finance form token.
$error = '';
if (!requireValidCsrfToken($_POST['csrf_token'] ?? '', $error)) {
    renderStripeStartError('Security Check Failed', $error);
}

// === SECTION: CLAIM IDENTIFIER VALIDATION ===
// What: Read and validate the claim ID from the protected POST body.
// Why: Finance can only create Checkout Sessions for real approved claims that still require payment.
// SECURITY: Casting identifiers with intval() prevents manipulated request data from becoming executable SQL.
$claim_id = intval($_POST['claim_id'] ?? 0);
if ($claim_id <= 0) {
    renderStripeStartError('Invalid Claim', 'The selected claim could not be identified.');
}

// === SECTION: APPROVED CLAIM LOOKUP ===
// What: Fetch the approved claim and related Staff information before creating a Stripe payment.
// Why: The amount, claim type, and Staff name must come from the database, not from browser-submitted values.
// SECURITY: Using Prepared Statements to prevent SQL Injection.
$stmt = $conn->prepare("
    SELECT c.id, c.amount, c.claim_type, u.name AS staff_name, u.staff_id
    FROM claims c
    JOIN users u ON c.user_id = u.id
    WHERE c.id = ? AND c.status = 'Approved'
    LIMIT 1
");
$stmt->bind_param("i", $claim_id);
$stmt->execute();
$result = $stmt->get_result();
$claim = $result->fetch_assoc();
$stmt->close();

if (!$claim) {
    renderStripeStartError('Payment Not Available', 'This claim is not approved or has already been processed.');
}

// === SECTION: PAYMENT AMOUNT CONVERSION ===
// What: Convert Ringgit Malaysia into sen for Stripe unit_amount.
// Why: Stripe expects the smallest currency unit, so RM 200.00 must be sent as 20000.
$amount_cents = (int) round(((float) $claim['amount']) * 100);
if ($amount_cents <= 0) {
    renderStripeStartError('Invalid Amount', 'The claim amount is not valid for Stripe Checkout.');
}

// === SECTION: PAYMENT TOKEN CREATION ===
// What: Create a random server-side payment token and store expected claim details in the Finance session.
// Why: Stripe redirects back with this token, allowing payment_success.php to identify the claim without trusting a raw claim_id in the URL.
// SECURITY: random_bytes() creates an unpredictable payment token that protects claim references during redirect verification.
$payment_token = bin2hex(random_bytes(32));
$_SESSION['stripe_payment_claims'][$payment_token] = [
    'claim_id' => (int) $claim['id'],
    'amount_cents' => $amount_cents,
    'currency' => 'myr',
    'created_at' => time(),
];

// === SECTION: RETURN URL CONSTRUCTION ===
// What: Build success and cancel URLs from the current hosted Finance directory.
// Why: The same code works on localhost and InfinityFree without hardcoding the domain.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path;
$success_url = $base_url . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&payment_token=' . urlencode($payment_token);
$cancel_url = $base_url . '/payment_cancel.php?payment_token=' . urlencode($payment_token);

// === SECTION: STRIPE CHECKOUT PAYLOAD ===
// What: Prepare the Stripe Checkout Session payload with amount, currency, claim reference, and metadata.
// Why: Stripe metadata allows payment_success.php to verify that the returned session belongs to the expected claim.
$payload = [
    'mode' => 'payment',
    'payment_method_types[0]' => 'card',
    'line_items[0][price_data][currency]' => 'myr',
    'line_items[0][price_data][product_data][name]' => 'UTMSPACE Claim #' . (int) $claim['id'],
    'line_items[0][price_data][product_data][description]' => 'Claim Type: ' . $claim['claim_type'] . ' | Staff: ' . $claim['staff_name'],
    'line_items[0][price_data][unit_amount]' => $amount_cents,
    'line_items[0][quantity]' => 1,
    'client_reference_id' => (string) $claim['id'],
    'metadata[claim_id]' => (string) $claim['id'],
    'metadata[finance_user_id]' => (string) $_SESSION['user_id'],
    'success_url' => $success_url,
    'cancel_url' => $cancel_url,
];

// === SECTION: STRIPE SESSION CREATION ===
// What: Create the Checkout Session and redirect Finance to Stripe's hosted payment page.
// Why: The database is not updated here; claim status changes only after Stripe confirms successful payment.
$session = createStripeCheckoutSession($stripe_secret_key, $payload, $error);
if (!$session) {
    unset($_SESSION['stripe_payment_claims'][$payment_token]);
    renderStripeStartError('Stripe Checkout Failed', $error);
}

header("Location: " . $session['url']);
exit();
