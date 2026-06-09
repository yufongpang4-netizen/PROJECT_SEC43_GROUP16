<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: payment_cancel.php handles Stripe Checkout cancellation without changing claim status.
// The following comments explain why cancelled payments must clean up temporary
// payment session data while preserving the approved claim for later payment.
// ============================================================================

// === SECTION: SESSION INITIALIZATION ===
// What: Resume the Finance session that started Stripe Checkout.
// Why: The pending payment token can be removed when Finance cancels or exits the Stripe payment page.
session_start();

// === SECTION: FINANCE ACCESS CONTROL ===
// What: Permit only authenticated Finance users to view the cancellation result.
// Why: Payment cancellation belongs to the Finance workflow and should not expose protected pages to other roles.
// SECURITY: Preventing broken access control by enforcing Finance role validation before rendering the result.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'finance') {
    header("Location: ../login.php");
    exit();
}

// === SECTION: PAYMENT TOKEN CLEANUP ===
// What: Remove the pending Stripe payment token from the session when the Checkout flow is cancelled.
// Why: Cancelled Stripe sessions should not remain available for later success verification attempts.
// SECURITY: Rejecting malformed tokens prevents unnecessary access to session payment data.
$payment_token = trim($_GET['payment_token'] ?? '');
if (preg_match('/^[a-f0-9]{64}$/', $payment_token)) {
    unset($_SESSION['stripe_payment_claims'][$payment_token]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - UTMSPACE</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <script>
        // === SECTION: SWEETALERT CANCELLATION FEEDBACK ===
        // What: Inform Finance that Stripe Checkout was cancelled and no claim status was changed.
        // Why: The user should understand that the approved claim remains pending payment and can be retried safely.
        Swal.fire({
            icon: 'warning',
            title: 'Payment Cancelled',
            text: 'Stripe Checkout was cancelled. No database changes were made to the claim.',
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Back to Approved Claims'
        }).then(() => {
            window.location.href = 'All_Claim_Finance.php?status=Approved';
        });
    </script>
</body>

</html>