<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: forgot_password.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once "db.php";

// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require 'vendor/Exception.php';
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require 'vendor/PHPMailer.php';
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require 'vendor/SMTP.php';

// WHY: Importing PHPMailer classes keeps the password-reset email workflow maintainable instead of manually implementing SMTP calls.
use PHPMailer\PHPMailer\PHPMailer;
// WHY: Importing PHPMailer classes keeps the password-reset email workflow maintainable instead of manually implementing SMTP calls.
use PHPMailer\PHPMailer\Exception;

$message = '';
$status = ''; // success or error

// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if ($_SERVER['REQUEST_METHOD'] === 'POST') ` so the application can choose the correct business rule branch for the current user action.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $email = trim($_POST['email']);
    
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($email)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($email)) {
        $status = 'error';
        $message = 'Please enter your email address.';
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    } else {
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $stmt->bind_param("s", $email);
        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        $stmt->execute();
        // WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
        $result = $stmt->get_result();
        
        // CONDITION: Evaluates `if ($result->num_rows === 1) ` so the application can choose the correct business rule branch for the current user action.
        if ($result->num_rows === 1) {
            // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
            $user = $result->fetch_assoc();
            
            // SECURITY: random_bytes() generates cryptographically secure reset-token entropy so password-reset links cannot be predicted.
            // BEST PRACTICE: bin2hex() converts secure binary token bytes into a URL-safe reset-token string.
            $token = bin2hex(random_bytes(32));
            // SECURITY: The password-reset token expires after 15 minutes to limit abuse if a link is exposed.
            // WHY: Date formatting converts database timestamps into human-readable dates for review and reports.
            $expire_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expire = ? WHERE email = ?");
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
            $update_stmt->bind_param("sss", $token, $expire_time, $email);
            
            // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
            // CONDITION: Evaluates `if ($update_stmt->execute()) ` so the application can choose the correct business rule branch for the current user action.
            if ($update_stmt->execute()) {
                $reset_link = "http://localhost/finalproject/PROJECT_SEC43_GROUP16/reset_password.php?token=" . $token;
                
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'utmspaceclaim.demo@gmail.com'; 
                    $mail->Password   = 'kjvsghjthnholvyi'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('utmspaceclaim.demo@gmail.com', 'UTMSPACE Admin');
                    $mail->addAddress($email, $user['name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - UTMSPACE';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                            <h2 style='color: #0B3B5E;'>UTMSPACE Claim System</h2>
                            <p>Hi {$user['name']},</p>
                            <p>We received a request to reset your password. Click the button below to set a new password:</p>
                            <p>
                                <a href='{$reset_link}' style='background-color: #C1272D; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Reset Password</a>
                            </p>
                            <p style='color: #666; font-size: 12px;'>This link will expire in 15 minutes.<br>If you did not request this, please ignore this email.</p>
                        </div>
                    ";

                    $mail->send();
                    $status = 'success';
                    $message = 'A password reset link has been sent to your email!';
                } catch (Exception $e) {
                    $status = 'error';
                    $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
            } else {
                $status = 'error';
                $message = "Database error. Could not generate token.";
            }
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            $status = 'error';
            $message = "No account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
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
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(16px); border-radius: 30px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); width: 100%; max-width: 450px; position: relative; z-index: 1; }
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control { border-radius: 12px; padding: 12px; background: rgba(255,255,255,0.8); }
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:focus { box-shadow: 0 0 0 3px rgba(193, 39, 45, 0.1); border-color: var(--utm-red); }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-custom { background: var(--utm-navy); color: white; border-radius: 50px; padding: 12px; font-weight: 600; width: 100%; transition: 0.3s; border: none; }
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-custom:hover { background: #082c47; transform: translateY(-2px); color: white; }
    </style>
</head>
<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->
<body>
    <div class="blurry-bg"></div>
    <div class="glass-card animate__animated animate__fadeInUp">
        <div class="text-center mb-4">
            <i class="fas fa-key fa-3x mb-3" style="color: var(--utm-red);"></i>
            <h3 class="fw-bold" style="color: var(--utm-navy);">Forgot Password</h3>
            <p class="text-muted small">Enter your email to receive a password reset link.</p>
        </div>

        <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
        <form method="POST" id="forgotForm">
            <div class="mb-4">
                <label class="form-label text-navy fw-semibold"><i class="fas fa-envelope me-2 opacity-75"></i>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="name@utmspace.edu.my" required>
            </div>
            <button type="submit" class="btn btn-custom mb-3" id="submitBtn">
                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
            </button>
            <div class="text-center">
                <a href="login.php" class="text-decoration-none" style="color: var(--utm-gray);"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
            </div>
        </form>
    </div>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script>
        // WHY: Submit handling prevents weak or invalid forms from being sent and displays clear user feedback.
        document.getElementById('forgotForm').addEventListener('submit', function() {
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            document.getElementById('submitBtn').disabled = true;
        });

        // CONDITION: Evaluates `if($status === 'success')` so the application can choose the correct business rule branch for the current user action.
        <?php if($status === 'success'): ?>
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                icon: 'success', title: 'Email Sent!', text: '<?php echo addslashes($message); ?>',
                confirmButtonColor: '#0B3B5E'
            // WHY: Redirecting after success moves the user to the next logical workflow page without manual navigation.
            }).then(() => { window.location.href = 'login.php'; });
        // CONDITION: Evaluates `elseif($status === 'error')` so the application can choose the correct business rule branch for the current user action.
        <?php elseif($status === 'error'): ?>
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                icon: 'error', title: 'Oops...', text: '<?php echo addslashes($message); ?>',
                confirmButtonColor: '#C1272D'
            });
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Reset Link';
            document.getElementById('submitBtn').disabled = false;
        <?php endif; ?>
    </script>
</body>
</html>