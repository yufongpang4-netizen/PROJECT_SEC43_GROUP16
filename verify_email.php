<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: verify_email.php completes Staff registration through a short-lived
// numeric code instead of a public-domain email link. The following comments
// explain what each block performs and why it protects account activation.
// ============================================================================

// === SECTION: SESSION INITIALIZATION ===
// What: Resume the registration session so the previously entered email can be shown on the verification form.
// Why: Preserving workflow context improves usability without treating session data as proof of verification.
session_start();

// === SECTION: DEPENDENCY LOADING ===
// What: Load database, CSRF, and centralized email services used by verification and resend actions.
// Why: Account activation changes authentication records and must use the same protected services as registration.
require_once 'db.php';
require_once 'csrf_helper.php';
require_once 'mailer_helper.php';

$message_title = 'Verify Your Email';
$message_body = 'Enter the six-digit code sent to your registered email address.';
$message_type = 'info';
$show_login_link = false;
$verification_complete = false;
$email = trim($_POST['email'] ?? ($_SESSION['pending_verification_email'] ?? ''));

// === SECTION: LEGACY LINK COMPATIBILITY ===
// What: Continue accepting previously issued 64-character verification links until those records expire.
// Why: Accounts created before the code-based update should not become permanently unusable during deployment.
$legacy_token = trim($_GET['token'] ?? '');
if ($legacy_token !== '' && preg_match('/^[a-f0-9]{64}$/', $legacy_token)) {
    // SECURITY: Preventing SQL Injection by binding the legacy token before account lookup.
    $legacyStmt = $conn->prepare("SELECT id, email_verified, email_verification_expires_at FROM users WHERE email_verification_token = ? LIMIT 1");
    $legacyStmt->bind_param('s', $legacy_token);
    $legacyStmt->execute();
    $legacyUser = $legacyStmt->get_result()->fetch_assoc();
    $legacyStmt->close();

    if (!$legacyUser) {
        $message_title = 'Verification Failed';
        $message_body = 'This verification link is invalid or has already been used.';
        $message_type = 'error';
    } elseif ((int) $legacyUser['email_verified'] === 1) {
        $message_title = 'Already Verified';
        $message_body = 'Your email address has already been verified. You may log in now.';
        $message_type = 'info';
        $show_login_link = true;
        $verification_complete = true;
    } elseif (empty($legacyUser['email_verification_expires_at']) || strtotime($legacyUser['email_verification_expires_at']) < time()) {
        $message_title = 'Verification Expired';
        $message_body = 'This verification link has expired. Request a new code below.';
        $message_type = 'error';
    } else {
        $verified = 1;
        $legacyUserId = (int) $legacyUser['id'];
        $legacyUpdate = $conn->prepare("UPDATE users SET email_verified = ?, email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?");
        $legacyUpdate->bind_param('ii', $verified, $legacyUserId);

        if ($legacyUpdate->execute()) {
            $message_title = 'Email Verified';
            $message_body = 'Your email address has been verified successfully. You may log in now.';
            $message_type = 'success';
            $show_login_link = true;
            $verification_complete = true;
        }
        $legacyUpdate->close();
    }
}

// === SECTION: CODE VERIFICATION AND RESEND HANDLER ===
// What: Process deliberate verification or resend requests submitted through protected POST forms.
// Why: Email ownership and code renewal both change authentication state and therefore require CSRF validation.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrfError = '';
    if (!requireValidCsrfToken($_POST['csrf_token'] ?? '', $csrfError)) {
        $message_title = 'Security Validation Failed';
        $message_body = $csrfError;
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? 'verify';
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message_title = 'Invalid Email';
            $message_body = 'Enter the same valid email address used during registration.';
            $message_type = 'error';
        } elseif ($action === 'verify') {
            $code = trim($_POST['verification_code'] ?? '');

            // VALIDATION: Accept exactly six digits so malformed or pasted URL content cannot reach token comparison logic.
            if (!preg_match('/^[0-9]{6}$/', $code)) {
                $message_title = 'Invalid Code';
                $message_body = 'The verification code must contain exactly six digits.';
                $message_type = 'error';
            } else {
                // SECURITY: Preventing SQL Injection by binding the registered email before retrieving verification state.
                $userStmt = $conn->prepare("SELECT id, email_verified, email_verification_token, email_verification_expires_at FROM users WHERE email = ? LIMIT 1");
                $userStmt->bind_param('s', $email);
                $userStmt->execute();
                $user = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();

                $submittedHash = hash('sha256', $code);

                if (!$user) {
                    $message_title = 'Verification Failed';
                    $message_body = 'The email address or verification code is incorrect.';
                    $message_type = 'error';
                } elseif ((int) $user['email_verified'] === 1) {
                    $message_title = 'Already Verified';
                    $message_body = 'Your email address has already been verified. You may log in now.';
                    $message_type = 'info';
                    $show_login_link = true;
                    $verification_complete = true;
                } elseif (empty($user['email_verification_expires_at']) || strtotime($user['email_verification_expires_at']) < time()) {
                    $message_title = 'Code Expired';
                    $message_body = 'The verification code has expired. Request a new code below.';
                    $message_type = 'error';
                } elseif (empty($user['email_verification_token']) || !hash_equals($user['email_verification_token'], $submittedHash)) {
                    // SECURITY: hash_equals() performs timing-safe comparison for the stored verification-code hash.
                    $message_title = 'Verification Failed';
                    $message_body = 'The email address or verification code is incorrect.';
                    $message_type = 'error';
                } else {
                    // SECURITY: Clear the single-use code after activation so it cannot be replayed.
                    $verified = 1;
                    $userId = (int) $user['id'];
                    $updateStmt = $conn->prepare("UPDATE users SET email_verified = ?, email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ? AND email_verified = 0");
                    $updateStmt->bind_param('ii', $verified, $userId);
                    $updateStmt->execute();
                    $updated = $updateStmt->affected_rows;
                    $updateStmt->close();

                    if ($updated === 1) {
                        unset($_SESSION['pending_verification_email']);
                        $message_title = 'Email Verified';
                        $message_body = 'Your email address has been verified successfully. You may log in to the UTMSPACE Claim System.';
                        $message_type = 'success';
                        $show_login_link = true;
                        $verification_complete = true;

                        if (function_exists('logActivity')) {
                            logActivity($conn, $userId, 'Verify Email', 'Staff email address verified using a six-digit code.');
                        }
                    }
                }
            }
        } elseif ($action === 'resend') {
            // === SECTION: RESEND RATE LIMIT ===
            // What: Permit at most one resend request per email and browser session every 60 seconds.
            // Why: A short cooldown reduces accidental duplicate mail and abuse of the shared SMTP account.
            $resendKey = hash('sha256', strtolower($email));
            $lastResend = (int) ($_SESSION['verification_resend_times'][$resendKey] ?? 0);

            if ((time() - $lastResend) < 60) {
                $message_title = 'Please Wait';
                $message_body = 'A verification code was recently requested. Please wait 60 seconds before trying again.';
                $message_type = 'info';
            } else {
                // SECURITY: Prepared lookup ensures only the exact pending account can receive a replacement code.
                $resendStmt = $conn->prepare("SELECT id, name, email_verified FROM users WHERE email = ? LIMIT 1");
                $resendStmt->bind_param('s', $email);
                $resendStmt->execute();
                $resendUser = $resendStmt->get_result()->fetch_assoc();
                $resendStmt->close();

                if (!$resendUser || (int) $resendUser['email_verified'] === 1) {
                    $message_title = 'Code Not Sent';
                    $message_body = 'No unverified account is available for that email address.';
                    $message_type = 'error';
                } else {
                    $newCode = (string) random_int(100000, 999999);
                    $newCodeHash = hash('sha256', $newCode);
                    $newExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    $resendUserId = (int) $resendUser['id'];

                    // SECURITY: Store only the replacement code hash and its expiry through a prepared update.
                    $codeUpdate = $conn->prepare("UPDATE users SET email_verification_token = ?, email_verification_expires_at = ? WHERE id = ? AND email_verified = 0");
                    $codeUpdate->bind_param('ssi', $newCodeHash, $newExpiry, $resendUserId);
                    $codeUpdate->execute();
                    $codeUpdated = $codeUpdate->affected_rows;
                    $codeUpdate->close();

                    $safeCode = htmlspecialchars($newCode, ENT_QUOTES, 'UTF-8');
                    $resendBody = '
                        <p style="margin:0 0 18px;">Use the following code to verify your UTMSPACE Staff account:</p>
                        <div style="margin:0 0 18px; padding:16px; background:#f8fafc; border:1px solid #cbd5e1; text-align:center; font-size:30px; font-weight:700; letter-spacing:8px; color:#0B3B5E;">' . $safeCode . '</div>
                        <p style="margin:0; color:#64748b; font-size:13px;">This replacement code expires in 15 minutes and can be used only once.</p>
                    ';

                    if ($codeUpdated === 1 && sendSystemEmail($email, $resendUser['name'], 'Your UTMSPACE verification code', $resendBody)) {
                        $_SESSION['verification_resend_times'][$resendKey] = time();
                        $_SESSION['pending_verification_email'] = $email;
                        $message_title = 'Code Sent';
                        $message_body = 'A new six-digit verification code has been sent to your email.';
                        $message_type = 'success';
                    } else {
                        $message_title = 'Code Not Sent';
                        $message_body = 'The verification email could not be delivered. Please try again later or contact the Administrator.';
                        $message_type = 'error';
                    }
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
    <title>Email Verification - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* === SECTION: PAGE FOUNDATION === */
        /* What: Center the verification workflow on a quiet institutional background. */
        /* Why: A focused screen helps Staff enter short security codes accurately. */
        body { min-height:100vh; margin:0; background:#0B3B5E; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; display:flex; align-items:center; justify-content:center; padding:24px; }
        .verification-card { width:100%; max-width:520px; background:#fff; border-radius:8px; padding:32px; box-shadow:0 20px 45px rgba(0,0,0,.25); }
        .verification-icon { width:68px; height:68px; margin:0 auto 18px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#e8f1f8; color:#0B3B5E; font-size:28px; }
        .form-control { min-height:48px; border-radius:8px; }
        .code-input { font-size:24px; font-weight:700; letter-spacing:8px; text-align:center; }
        .btn-primary-custom { background:#0B3B5E; color:#fff; border:0; border-radius:8px; min-height:46px; font-weight:600; }
        .btn-primary-custom:hover { background:#082f4b; color:#fff; }
        .btn-resend { background:transparent; color:#0B3B5E; border:1px solid #0B3B5E; border-radius:8px; min-height:46px; font-weight:600; }
    </style>
</head>
<body>
    <!-- === SECTION: ANIMATED VERIFICATION PANEL === -->
    <!-- What: Introduce the verification form with a brief entrance animation. -->
    <!-- Why: Motion draws attention to the security task without delaying access to the form. -->
    <main class="verification-card animate__animated animate__fadeInUp">
        <div class="verification-icon">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check' : ($message_type === 'error' ? 'fa-exclamation-triangle' : 'fa-envelope'); ?>"></i>
        </div>
        <!-- SECURITY: Preventing XSS by escaping verification status text before display. -->
        <h3 class="text-center fw-bold mb-2" style="color:#0B3B5E;"><?php echo htmlspecialchars($message_title, ENT_QUOTES, 'UTF-8'); ?></h3>
        <p class="text-center text-muted mb-4"><?php echo htmlspecialchars($message_body, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if (!$verification_complete): ?>
            <!-- === SECTION: CODE VERIFICATION FORM === -->
            <!-- What: Collect the registered email and single-use code with CSRF protection. -->
            <!-- Why: Both values are required to identify and activate only the intended pending account. -->
            <form method="POST" class="mb-3" autocomplete="off">
                <?php echo csrfInputField(); ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Registered Email</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Six-Digit Verification Code</label>
                    <input type="text" id="verificationCode" name="verification_code" class="form-control code-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="000000">
                </div>
                <button type="submit" name="action" value="verify" class="btn btn-primary-custom w-100 mb-3"><i class="fas fa-check-circle me-2"></i>Verify Email</button>

                <!-- === SECTION: CODE RESEND ACTION === -->
                <!-- What: Reuse the visible registered email field when requesting a replacement verification code. -->
                <!-- Why: Staff can recover verification even when they open this page directly and no registration email remains in session. -->
                <button type="submit" name="action" value="resend" formnovalidate class="btn btn-resend w-100"><i class="fas fa-paper-plane me-2"></i>Resend Verification Code</button>
            </form>
        <?php endif; ?>

        <div class="text-center">
            <?php if ($show_login_link): ?>
                <a href="login.php" class="text-decoration-none fw-semibold">Go to Login</a>
            <?php else: ?>
                <a href="register.php" class="text-decoration-none">Back to Registration</a>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // === SECTION: VERIFICATION STATUS POPUP ===
        // What: Present the current verification result in an animated SweetAlert2 dialog when the page opens.
        // Why: Immediate visual feedback helps Staff understand whether they must enter, resend, or confirm a code.
        document.addEventListener('DOMContentLoaded', function () {
            // SECURITY: Server-generated text is JSON encoded before entering JavaScript to prevent script injection.
            const verificationTitle = <?php echo json_encode($message_title, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const verificationMessage = <?php echo json_encode($message_body, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const verificationType = <?php echo json_encode($message_type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const verificationComplete = <?php echo $verification_complete ? 'true' : 'false'; ?>;

            Swal.fire({
                icon: verificationType,
                title: verificationTitle,
                text: verificationMessage,
                confirmButtonText: verificationComplete ? 'Go to Login' : 'Continue',
                confirmButtonColor: '#0B3B5E',
                backdrop: 'rgba(4, 30, 48, 0.68)',
                showClass: {
                    popup: 'animate__animated animate__zoomIn'
                },
                hideClass: {
                    popup: 'animate__animated animate__zoomOut'
                }
            }).then(function () {
                // === SECTION: POST-POPUP NAVIGATION ===
                // What: Continue verified users to login and return pending users to the code input.
                // Why: The next action remains clear and requires no unnecessary manual navigation.
                if (verificationComplete) {
                    window.location.href = 'login.php';
                    return;
                }

                const codeInput = document.getElementById('verificationCode');
                if (codeInput) {
                    codeInput.focus();
                }
            });
        });
    </script>
</body>
</html>
