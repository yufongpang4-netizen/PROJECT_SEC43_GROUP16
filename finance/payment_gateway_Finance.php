<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: payment_gateway_Finance.php is retained as a compatibility redirect after Stripe Checkout integration.
// The previous simulated payment flow updated the database directly; the new
// business workflow requires Stripe verification before any claim becomes Paid.
// ============================================================================

// === SECTION: SESSION INITIALIZATION ===
// What: Resume the user session before checking Finance authorization.
// Why: Legacy payment URLs must still respect role-based access control even though they no longer process payments.
session_start();

// === SECTION: FINANCE ACCESS CONTROL ===
// What: Allow only Finance users to reach the payment compatibility redirect.
// Why: Payment workflow pages must remain separated from Staff and Admin access.
// SECURITY: Preventing broken access control by redirecting unauthenticated or non-Finance users to login.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'finance') {
    header("Location: ../login.php");
    exit();
}

// === SECTION: LEGACY PAYMENT ROUTE DISABLEMENT ===
// What: Redirect old simulated payment URLs back to the claim details page or approved claims list.
// Why: The system must no longer mark claims as Paid without a verified Stripe Checkout Session.
// SECURITY: Preventing payment bypass by removing the direct database-update path from the legacy payment page.
$claim_id = intval($_GET['id'] ?? 0);
if ($claim_id > 0) {
    header("Location: Claim_details_Finance.php?id=" . $claim_id);
    exit();
}

header("Location: All_Claim_Finance.php?status=Approved");
exit();
