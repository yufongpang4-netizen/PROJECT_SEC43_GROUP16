<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: reset_password.php securely changes an account password after the
// user proves inbox ownership with a short-lived six-digit recovery code.
// ============================================================================

// === SECTION: SESSION INITIALIZATION ===
// What: Resume the recovery session so the requested email can be carried from the Forgot Password page.
// Why: Session context improves usability but is never treated as proof that the user owns the account.
session_start();

// === SECTION: DEPENDENCY LOADING ===
// What: Load the shared database and CSRF services used by the credential-change form.
// Why: Password changes are security-sensitive database actions and require centralized protection.
require_once 'db.php';
require_once 'csrf_helper.php';

$error = '';
$success = false;
$email = trim($_POST['email'] ?? ($_SESSION['pending_reset_email'] ?? ''));

// === SECTION: PASSWORD RESET FORM HANDLER ===
// What: Validate the submitted email, recovery code, and new password before changing credentials.
// Why: All required evidence must be verified by the server in one controlled transaction.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!requireValidCsrfToken($_POST['csrf_token'] ?? '', $error)) {
        // The shared helper provides a safe user-facing message.
    } else {
        $email = trim($_POST['email'] ?? '');
        $resetCode = trim($_POST['reset_code'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // === SECTION: RECOVERY ATTEMPT LIMIT ===
        // What: Track failed code submissions per email and browser session.
        // Why: Six-digit codes require online-attempt controls to reduce automated guessing during their short lifetime.
        $attemptKey = hash('sha256', strtolower($email));
        $attempts = (int) ($_SESSION['password_reset_attempts'][$attemptKey] ?? 0);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter the same valid email address used for the reset request.';
        } elseif ($attempts >= 5) {
            $error = 'Too many incorrect attempts. Request a new password reset code.';
        } elseif (!preg_match('/^[0-9]{6}$/', $resetCode)) {
            $error = 'The password reset code must contain exactly six digits.';
        } elseif (
            strlen($newPassword) < 8 ||
            !preg_match('/[A-Z]/', $newPassword) ||
            !preg_match('/[a-z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword) ||
            !preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'\",.<>?\/\\\\|`~]/', $newPassword)
        ) {
            // VALIDATION: Apply the same strict policy used by registration and profile password changes.
            $error = 'Password must contain at least 8 characters, 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            // SECURITY: Preventing SQL Injection by binding the account email before retrieving the reset-code hash.
            $userStmt = $conn->prepare("SELECT id, reset_token, reset_token_expire FROM users WHERE email = ? LIMIT 1");
            $userStmt->bind_param('s', $email);
            $userStmt->execute();
            $user = $userStmt->get_result()->fetch_assoc();
            $userStmt->close();

            $submittedHash = hash('sha256', $resetCode);

            if (!$user || empty($user['reset_token'])) {
                $_SESSION['password_reset_attempts'][$attemptKey] = $attempts + 1;
                $error = 'The email address or password reset code is incorrect.';
            } elseif (empty($user['reset_token_expire']) || strtotime($user['reset_token_expire']) < time()) {
                $error = 'This password reset code has expired. Request a new code.';
            } elseif (!hash_equals($user['reset_token'], $submittedHash)) {
                // SECURITY: hash_equals() prevents timing differences during recovery-code comparison.
                $_SESSION['password_reset_attempts'][$attemptKey] = $attempts + 1;
                $remainingAttempts = max(0, 4 - $attempts);
                $error = 'The email address or password reset code is incorrect. Remaining attempts: ' . $remainingAttempts . '.';
            } else {
                // SECURITY: Hash the new password before storage and clear the single-use recovery code atomically.
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $userId = (int) $user['id'];
                $storedToken = $user['reset_token'];
                $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expire = NULL WHERE id = ? AND reset_token = ?");
                $updateStmt->bind_param('sis', $hashedPassword, $userId, $storedToken);
                $updateStmt->execute();
                $updated = $updateStmt->affected_rows;
                $updateStmt->close();

                if ($updated === 1) {
                    unset($_SESSION['pending_reset_email'], $_SESSION['password_reset_attempts'][$attemptKey]);
                    $success = true;

                    // === SECTION: ACTIVITY LOGGING ===
                    // What: Record successful credential recovery after the code is consumed.
                    // Why: Password changes are security-sensitive events that require an audit trail.
                    if (function_exists('logActivity')) {
                        logActivity($conn, $userId, 'Password Reset Complete', 'User password was reset successfully using a six-digit code.');
                    }
                } else {
                    $error = 'The password could not be updated. Request a new reset code and try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* === SECTION: PAGE FOUNDATION === */
        /* What: Center the recovery form on a restrained institutional background. */
        /* Why: A focused layout reduces mistakes while entering security and password information. */
        body { min-height:100vh; margin:0; padding:24px; background:#0B3B5E; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; display:flex; align-items:center; justify-content:center; }
        .reset-card { width:100%; max-width:520px; background:#fff; border-radius:8px; padding:32px; box-shadow:0 20px 45px rgba(0,0,0,.25); }
        .reset-icon { width:68px; height:68px; margin:0 auto 18px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#e8f1f8; color:#0B3B5E; font-size:28px; }
        .form-control { min-height:48px; border-radius:8px; }
        .code-input { font-size:24px; font-weight:700; letter-spacing:8px; text-align:center; }
        .btn-custom { min-height:48px; width:100%; background:#0B3B5E; color:#fff; border:0; border-radius:8px; font-weight:600; }
        .btn-custom:hover { background:#082f4b; color:#fff; }
        .password-rules { font-size:13px; color:#64748b; }
    </style>
</head>
<body>
    <!-- === SECTION: ANIMATED PASSWORD RESET PANEL === -->
    <!-- What: Introduce the password recovery form with a brief entrance animation. -->
    <!-- Why: The motion directs attention to the security workflow without obstructing form access. -->
    <main class="reset-card animate__animated animate__fadeInUp">
        <div class="reset-icon"><i class="fas fa-key"></i></div>
        <h3 class="text-center fw-bold mb-2" style="color:#0B3B5E;">Reset Password</h3>
        <p class="text-center text-muted mb-4">Enter the six-digit code sent to your email and create a new password.</p>

        <?php if ($success): ?>
            <!-- === SECTION: SUCCESSFUL RESET STATE === -->
            <!-- What: Keep the page free from additional form controls after the password has been changed. -->
            <!-- Why: The animated confirmation below provides one clear and safe next action: returning to Login. -->
        <?php else: ?>
            <?php if ($error): ?>
                <!-- SECURITY: Preventing XSS by escaping password-reset errors before display. -->
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <!-- === SECTION: PROTECTED PASSWORD RESET FORM === -->
            <!-- What: Collect the registered email, recovery code, and matching replacement passwords. -->
            <!-- Why: The server requires inbox proof and a policy-compliant password before updating credentials. -->
            <form method="POST" id="resetForm" autocomplete="off">
                <?php echo csrfInputField(); ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Registered Email</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Six-Digit Reset Code</label>
                    <input type="text" id="resetCode" name="reset_code" class="form-control code-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="000000">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="new_password" id="newPassword" class="form-control" required minlength="8">
                    <div class="password-rules mt-2">Minimum 8 characters with uppercase, lowercase, number and special character.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-custom"><i class="fas fa-save me-2"></i>Save New Password</button>
            </form>

            <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-decoration-none">Request a new reset code</a>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // === SECTION: PASSWORD RESET STATUS POPUP ===
        // What: Display initial instructions, validation errors, or successful completion through one animated dialog.
        // Why: Consistent visual feedback makes the recovery workflow easier to understand and reduces repeated submissions.
        document.addEventListener('DOMContentLoaded', function () {
            // SECURITY: PHP values are JSON encoded before entering JavaScript to prevent script injection.
            const resetSuccessful = <?php echo $success ? 'true' : 'false'; ?>;
            const resetError = <?php echo json_encode($error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const popupIcon = resetSuccessful ? 'success' : (resetError ? 'error' : 'info');
            const popupTitle = resetSuccessful ? 'Password Updated' : (resetError ? 'Reset Unsuccessful' : 'Reset Your Password');
            const popupMessage = resetSuccessful
                ? 'Your password has been changed successfully. You can now log in.'
                : (resetError || 'Enter the six-digit code sent to your email and create a new password.');

            Swal.fire({
                icon: popupIcon,
                title: popupTitle,
                text: popupMessage,
                confirmButtonText: resetSuccessful ? 'Go to Login' : 'Continue',
                confirmButtonColor: '#0B3B5E',
                allowOutsideClick: !resetSuccessful,
                backdrop: 'rgba(4, 30, 48, 0.68)',
                showClass: {
                    popup: 'animate__animated animate__zoomIn'
                },
                hideClass: {
                    popup: 'animate__animated animate__zoomOut'
                }
            }).then(function () {
                // === SECTION: POST-POPUP NAVIGATION ===
                // What: Send completed recoveries to Login and return incomplete recoveries to the code field.
                // Why: The page automatically places the user at the next relevant action in the workflow.
                if (resetSuccessful) {
                    window.location.href = 'login.php';
                    return;
                }

                const resetCodeInput = document.getElementById('resetCode');
                if (resetCodeInput) {
                    resetCodeInput.focus();
                }
            });
        });
    </script>
</body>
</html>
