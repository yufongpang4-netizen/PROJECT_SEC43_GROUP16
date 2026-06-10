<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: logout.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();

// === SECTION: LOGOUT ACTIVITY DEPENDENCY ===
// What: Load the database helper before clearing the session.
// Why: The system must record the logout event while the authenticated user ID is still available.
require_once "db.php";

// === SECTION: ACTIVITY LOGGING ===
// What: Record that the current user intentionally logged out.
// Why: Login and logout records help prove session lifecycle activity in the audit trail.
if (isset($_SESSION['user_id']) && function_exists('logActivity')) {
    logActivity($conn, $_SESSION['user_id'], 'Logout', 'User logged out successfully.');
}

$_SESSION = array();

// CONDITION: Evaluates `if (ini_get("session.use_cookies")) ` so the application can choose the correct business rule branch for the current user action.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out... | UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --utm-navy: #0B3B5E;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow: hidden; }
        
        /* WHY: The blurred background preserves institutional branding while keeping foreground forms readable. */
        .blurry-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('css/images/utm.jpg');
            background-size: cover; background-position: center;
            filter: blur(12px); transform: scale(1.1); z-index: 0;
        }
        
        /* WHY: The blurred background preserves institutional branding while keeping foreground forms readable. */
        .blurry-bg::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(11, 59, 94, 0.75);
        }

        .premium-logout-popup {
            border-radius: 24px !important;
            padding: 2.5em 2em !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
            border: 1px solid rgba(255,255,255,0.8) !important;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px) !important;
        }
        
        .premium-logout-progress {
            background: linear-gradient(90deg, #0B3B5E 0%, #3b82f6 100%) !important;
            height: 4px !important;
            border-radius: 10px;
        }
    </style>
</head>
<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->
<body>
    <div class="blurry-bg"></div>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script>
        // SECTION: DOM READY HANDLER - Runs UI logic only after page elements exist, preventing null references.
        document.addEventListener('DOMContentLoaded', function() {
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                title: 'Signing Out',
                html: '<div class="mt-2 text-muted fw-semibold" style="font-size: 1.05rem;"><i class="fas fa-shield-alt me-2" style="color: #3b82f6;"></i>Securely clearing your session...</div>',
                timer: 1500,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                color: 'var(--utm-navy)',
                customClass: {
                    popup: 'premium-logout-popup',
                    timerProgressBar: 'premium-logout-progress'
                },
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then(() => {
                // WHY: Redirecting after success moves the user to the next logical workflow page without manual navigation.
                window.location.href = 'login.php';
            });
        });
    </script>
</body>
</html>
