<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: csrf_helper.php centralizes CSRF protection for UTMSPACE forms.
// These comments explain what each security block does and why it protects
// Staff, Finance, and Admin workflows from forged browser submissions.
// ============================================================================

// === SECTION 1: CSRF TOKEN CREATION ===
// What: Create or reuse one unpredictable token for the current browser session.
// Why: State-changing forms need a session-bound secret so attackers cannot submit actions from another website.
function generateCsrfToken()
{
    // SECURITY: random_bytes() creates cryptographically strong token material.
    // Why: A CSRF token must be difficult to guess so forged forms cannot pass server validation.
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// === SECTION 2: CSRF TOKEN HTML FIELD ===
// What: Render the hidden input used by POST forms across the system.
// Why: A shared renderer keeps every form consistent and prevents missing or misspelled token field names.
function csrfInputField()
{
    // SECURITY: Preventing XSS by escaping the token before placing it into an HTML attribute.
    // Why: Even session-generated values must be encoded before output to preserve strict output discipline.
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// === SECTION 3: CSRF TOKEN VALIDATION ===
// What: Compare the submitted token with the session token using a timing-safe comparison.
// Why: Every state-changing request must prove that it originated from a legitimate UTMSPACE form.
function validateCsrfToken($submittedToken)
{
    // SECURITY: hash_equals() prevents timing attacks during token comparison.
    // Why: Token validation must not leak information about the expected token value.
    return isset($_SESSION['csrf_token'])
        && is_string($submittedToken)
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

// === SECTION 4: REQUEST-LEVEL CSRF GUARD ===
// What: Validate the current request and provide one standard error message.
// Why: Pages can stop unsafe processing early without duplicating CSRF validation logic.
function requireValidCsrfToken($submittedToken, &$errorMessage)
{
    // SECURITY: Preventing CSRF by rejecting missing, expired, or forged tokens before business logic executes.
    // Why: Staff claim changes, Finance decisions, and Admin actions must only run from trusted in-application forms.
    if (!validateCsrfToken($submittedToken)) {
        $errorMessage = 'Security validation failed. Please refresh the page and try again.';
        return false;
    }

    return true;
}
