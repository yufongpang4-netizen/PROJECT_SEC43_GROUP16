<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: login.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once "db.php";
// SECTION: SECURITY HELPER LOADING - Loads reusable CSRF protection for the login form.
require_once "csrf_helper.php";

// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// CONDITION: Evaluates `if (isset($_SESSION['user_id'])) ` so the application can choose the correct business rule branch for the current user action.
if (isset($_SESSION['user_id'])) {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: index.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}

$error = '';
$success_redirect = '';

// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if ($_SERVER['REQUEST_METHOD'] === 'POST') ` so the application can choose the correct business rule branch for the current user action.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // SECURITY: Preventing CSRF by validating that login credentials came from the legitimate UTMSPACE form.
    // Why: Even authentication forms should reject forged submissions to reduce cross-site request abuse.
    if (!requireValidCsrfToken($_POST['csrf_token'] ?? '', $error)) {
        // The shared helper sets a safe user-facing error message.
    } else {
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $email = trim($_POST['email']);
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $password = $_POST['password'];

    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($email) || empty($password)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($email) || empty($password)) {
        $error = "Please enter email and password.";
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    } else {
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $stmt = $conn->prepare("SELECT id, name, email, password, role, status, email_verified FROM users WHERE email = ?");
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
            // SECURITY: password_verify() compares the submitted password with the stored bcrypt hash without exposing the original password.
            // CONDITION: Evaluates `if (password_verify($password, $user['password'])) ` so the application can choose the correct business rule branch for the current user action.
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'Inactive') {
                    $error = "Your account has been deactivated. Please contact Admin.";
                // === SECTION: EMAIL VERIFICATION LOGIN GATE ===
                // What: Block login for accounts that have not completed email verification.
                // Why: Registration must prove inbox ownership before the user can access claim submission or payment-related data.
                // SECURITY: Preventing unauthorized access by rejecting valid passwords when email_verified is still false.
                } elseif ((int) ($user['email_verified'] ?? 0) !== 1) {
                    $error = "Please verify your email address before logging in. Check your inbox for the UTMSPACE verification email.";
                // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];

                    // CONDITION: Evaluates `if ($user['role'] === 'staff') ` so the application can choose the correct business rule branch for the current user action.
                    if ($user['role'] === 'staff') {
                        $success_redirect = "staff/dashboard_Staff.php";
                    } elseif ($user['role'] === 'finance') {
                        $success_redirect = "finance/dashboard_Finance.php";
                    // CONDITION: Evaluates `} elseif ($user['role'] === 'admin') ` so the application can choose the correct business rule branch for the current user action.
                    } elseif ($user['role'] === 'admin') {
                        $success_redirect = "admin/dashboard_Admin.php";
                    }
                }
            // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
            } else {
                $error = "Invalid email or password!";
            }
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            $error = "Invalid email or password!";
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --utm-navy: #0B3B5E;
            --utm-red: #C1272D;
            --utm-gray: #475569;
            --utm-light: #F8FAFC;
            --utm-dark: #082c47;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        
        .login-page { position: relative; min-height: 100vh; }
        
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
            background: rgba(11, 59, 94, 0.65);
        }
        
        /* WHY: The content wrapper centers the authentication or landing card over the background image. */
        .content-wrapper {
            position: relative; z-index: 1; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 20px;
        }
        
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .glass-card {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            width: 100%; max-width: 450px;
        }

        .utm-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .utmspace-logo-img {
            max-width: 150px;
            width: 100%;
            height: auto;
            margin-bottom: 10px;
            background: transparent;
            transition: transform 0.2s ease;
        }
        
        .utmspace-logo-img:hover {
            transform: scale(1.05);
        }
        
        .logo-divider {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--utm-red), #ff4b52);
            margin: 10px auto 0;
            border-radius: 3px;
        }
        
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control {
            border-radius: 15px; padding: 12px 18px;
            border: 1px solid rgba(11, 59, 94, 0.1);
            background: rgba(255, 255, 255, 0.5); transition: all 0.3s;
        }
        
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(193, 39, 45, 0.1);
            border-color: var(--utm-red); background: white;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-primary-custom {
            background: var(--utm-navy); color: white; border-radius: 50px;
            padding: 12px; font-weight: 600; transition: all 0.3s; border: none;
            box-shadow: 0 4px 15px rgba(11, 59, 94, 0.2);
        }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-primary-custom:hover {
            background: var(--utm-dark); transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(11, 59, 94, 0.3); color: white;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-outline-custom {
            border: 2px solid var(--utm-navy); color: var(--utm-navy);
            border-radius: 50px; padding: 10px; font-weight: 600;
            transition: all 0.3s; text-decoration: none;
            display: block; text-align: center;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-outline-custom:hover { background: var(--utm-navy); color: white; }

        /* SECTION: ANIMATION - Keyframes add subtle entrance motion to guide attention without changing business logic. */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        
        .forgot-link {
            color: var(--utm-red);
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .forgot-link:hover {
            color: var(--utm-navy);
            text-decoration: underline !important;
        }
    </style>
</head>
<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->
<body class="login-page">
    <div class="blurry-bg"></div>
    <div class="content-wrapper">
        <div class="glass-card p-4 p-md-5 fade-in-up">
            
            <div class="utm-logo">
                <a href="index.php" class="d-inline-block">
                    <img src="css/images/utmspace logo.png" alt="UTMSPACE Logo" class="utmspace-logo-img" 
                         style="background: transparent;"
                         onerror="this.src='css/images/utm_space1.jpg'">
                </a>
                <div class="logo-divider"></div>
            </div>

            <div class="text-center mb-4">
                <h3 class="fw-bold" style="color: var(--utm-navy);">Welcome Back</h3>
                <p class="text-muted">Enter your credentials to access your account</p>
            </div>

            <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
            <form method="POST" id="loginForm">
                <?php echo csrfInputField(); ?>
                <div class="mb-3">
                    <label class="form-label text-navy ms-1"><i class="fas fa-envelope me-2 opacity-75"></i>Email Address</label>
                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                    <input type="email" name="email" class="form-control" placeholder="name@utmspace.edu.my" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-navy ms-1 mb-1"><i class="fas fa-lock me-2 opacity-75"></i>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100 mb-3" id="loginBtn">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
                
                <div class="text-center mb-4">
                    <a href="forgot_password.php" class="text-decoration-none small forgot-link" style="font-size: 0.9rem;">Forgot Password?</a>
                </div>
            </form>

            <div class="text-center">
                <p class="text-muted mb-3">Don't have an account yet?</p>
                <a href="register.php" class="btn btn-outline-custom w-100">
                    <i class="fas fa-user-plus me-2"></i>Create New Account
                </a>
            </div>
        </div>
    </div>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script>
        // SECTION: DOM READY HANDLER - Runs UI logic only after page elements exist, preventing null references.
        document.addEventListener('DOMContentLoaded', function() {
            // CONDITION: Evaluates `if ($success_redirect)` so the application can choose the correct business rule branch for the current user action.
            <?php if ($success_redirect): ?>
                document.getElementById('loginBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
                document.getElementById('loginBtn').disabled = true;

                // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
                Swal.fire({
                    icon: 'success',
                    title: 'Welcome, <?php echo addslashes($_SESSION['user_name']); ?>!',
                    text: 'Authenticating your account...',
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true,
                    background: 'rgba(255, 255, 255, 0.95)',
                    backdrop: `rgba(11, 59, 94, 0.6)`,
                    showClass: {
                        popup: 'animate__animated animate__zoomIn'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__zoomOut'
                    }
                }).then(() => {
                    // WHY: Redirecting after success moves the user to the next logical workflow page without manual navigation.
                    window.location.href = '<?php echo $success_redirect; ?>';
                });
            <?php endif; ?>

            // CONDITION: Evaluates `if ($error)` so the application can choose the correct business rule branch for the current user action.
            <?php if ($error): ?>
                // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
                Swal.fire({
                    icon: 'error',
                    title: 'Login Failed',
                    html: '<?php echo addslashes($error); ?>',
                    confirmButtonColor: '#0B3B5E',
                    background: 'rgba(255, 255, 255, 0.95)',
                    backdrop: `rgba(11, 59, 94, 0.3)`,
                    showClass: { popup: 'animate__animated animate__shakeX' }
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>
