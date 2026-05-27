<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: reset_password.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once "db.php";

$error = '';
$success = false;
$valid_token = false;
$user_id = null;

// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
// CONDITION: Evaluates `if (isset($_GET['token'])) ` so the application can choose the correct business rule branch for the current user action.
if (isset($_GET['token'])) {
    // WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
    $token = $_GET['token'];
    
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
    $stmt = $conn->prepare("SELECT id, reset_token_expire FROM users WHERE reset_token = ?");
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
    $stmt->bind_param("s", $token);
    // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
    $stmt->execute();
    // WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
    $result = $stmt->get_result();
    
    // CONDITION: Evaluates `if ($result->num_rows === 1) ` so the application can choose the correct business rule branch for the current user action.
    if ($result->num_rows === 1) {
        // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
        $user = $result->fetch_assoc();
        $expire_time = strtotime($user['reset_token_expire']);
        $current_time = time();
        
        // CONDITION: Evaluates `if ($current_time <= $expire_time) ` so the application can choose the correct business rule branch for the current user action.
        if ($current_time <= $expire_time) {
            $valid_token = true;
            $user_id = $user['id'];
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            $error = "This password reset link has expired. Please request a new one.";
        }
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    } else {
        $error = "Invalid password reset link.";
    }
// CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
} else {
    $error = "No reset token provided.";
}

// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) ` so the application can choose the correct business rule branch for the current user action.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $new_password = $_POST['new_password'];
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $confirm_password = $_POST['confirm_password'];
    
    // CONDITION: Evaluates `if (strlen($new_password) < 6) ` so the application can choose the correct business rule branch for the current user action.
    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    } else {
        // SECURITY: Hashing password using bcrypt.
        // WHY: Only the password hash is stored, protecting the original password from database disclosure.
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expire = NULL WHERE id = ?");
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        // CONDITION: Evaluates `if ($update_stmt->execute()) ` so the application can choose the correct business rule branch for the current user action.
        if ($update_stmt->execute()) {
            $success = true;
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <title>Reset Password - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root { --utm-navy: #0B3B5E; --utm-red: #C1272D; }
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0f4f8; }
        /* WHY: The blurred background preserves institutional branding while keeping foreground forms readable. */
        .blurry-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: url('css/images/utm.jpg'); background-size: cover; background-position: center; filter: blur(12px); transform: scale(1.1); z-index: 0; }
        /* WHY: The blurred background preserves institutional branding while keeping foreground forms readable. */
        .blurry-bg::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 59, 94, 0.65); }
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); border-radius: 30px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); width: 100%; max-width: 450px; position: relative; z-index: 1; }
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control { border-radius: 12px; padding: 12px; }
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:focus { box-shadow: 0 0 0 3px rgba(11, 59, 94, 0.1); border-color: var(--utm-navy); }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-custom { background: var(--utm-navy); color: white; border-radius: 50px; padding: 12px; font-weight: 600; width: 100%; transition: 0.3s; border: none; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-custom:hover { background: #082c47; transform: translateY(-2px); color: white; }
    </style>
</head>
<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->
<body>
    <div class="blurry-bg"></div>
    <div class="glass-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold" style="color: var(--utm-navy);">Reset Password</h3>
            <p class="text-muted small">Create a new, strong password.</p>
        </div>

        <!-- CONDITION: Evaluates `if (!$valid_token && !$success)` so the application can choose the correct business rule branch for the current user action. -->
        <?php if (!$valid_token && !$success): ?>
            <div class="alert alert-danger text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                <?php echo htmlspecialchars($error); ?>
            </div>
            <div class="text-center mt-4">
                <a href="forgot_password.php" class="btn btn-custom">Request New Link</a>
            </div>
        <!-- CONDITION: Evaluates `elseif ($success)` so the application can choose the correct business rule branch for the current user action. -->
        <?php elseif ($success): ?>
            <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
            <script>
                // SECTION: DOM READY HANDLER - Runs UI logic only after page elements exist, preventing null references.
                document.addEventListener('DOMContentLoaded', function() {
                    // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Updated!',
                        text: 'Your password has been changed successfully. You can now login.',
                        confirmButtonColor: '#0B3B5E',
                        allowOutsideClick: false
                    }).then((result) => {
                        // WHY: Redirecting after success moves the user to the next logical workflow page without manual navigation.
                        window.location.href = 'login.php';
                    });
                });
            </script>
        <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
        <?php else: ?>
            <!-- CONDITION: Evaluates `if ($error)` so the application can choose the correct business rule branch for the current user action. -->
            <?php if ($error): ?>
                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-navy fw-semibold">New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Enter new password">
                </div>
                <div class="mb-4">
                    <label class="form-label text-navy fw-semibold">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Confirm new password">
                </div>
                <button type="submit" class="btn btn-custom mb-3">
                    <i class="fas fa-save me-2"></i>Save New Password
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>