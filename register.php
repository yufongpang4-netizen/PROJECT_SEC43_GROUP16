<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: register.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once "db.php";
// SECTION: EMAIL SERVICE LOADING - Loads the centralized PHPMailer helper used for account verification messages.
require_once "mailer_helper.php";
// SECTION: SECURITY HELPER LOADING - Loads reusable CSRF protection for the public registration form.
require_once "csrf_helper.php";

// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// CONDITION: Evaluates `if (isset($_SESSION['user_id'])) ` so the application can choose the correct business rule branch for the current user action.
if (isset($_SESSION['user_id'])) {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: index.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}

$success = '';
$error = '';

// === SECTION 1A: REGISTRATION ROLE POLICY ===
// What: Public registration is restricted to Staff accounts only.
// Why: Finance and Admin accounts carry elevated privileges and must be created by an existing Admin, not self-selected by public users.
$role = 'staff';
$bg_image = 'css/images/staff.jpeg';

// === SECTION 1B: STAFF DEPARTMENT POLICY ===
// What: Define the official departments available to Staff self-registration.
// Why: Department data must still be collected for reporting, but it must remain limited to approved Staff departments.
$valid_staff_departments = ['Human Resources', 'Information Technology', 'Marketing', 'Sales'];

// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if ($_SERVER['REQUEST_METHOD'] === 'POST') ` so the application can choose the correct business rule branch for the current user action.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // SECURITY: Preventing CSRF by validating that registration data came from the legitimate UTMSPACE form.
    // Why: Public account creation changes authentication records and must not be triggered by a forged external page.
    if (!requireValidCsrfToken($_POST['csrf_token'] ?? '', $error)) {
        // The shared helper sets a safe user-facing error message.
    } else {
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $name = trim($_POST['name']);
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $staff_id = trim($_POST['staff_id']);
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $email = trim($_POST['email']);
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $password = $_POST['password'];
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $phone = trim($_POST['phone']);
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $department = trim($_POST['department'] ?? '');

    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates required Staff registration fields so incomplete public accounts are rejected before database insertion.
    if (empty($name) || empty($staff_id) || empty($email) || empty($password) || empty($phone) || empty($department)) {
        $error = "Please fill in all required fields including Phone number and Department.";
        // VALIDATION: FILTER_VALIDATE_EMAIL verifies email structure before storage or notification delivery.
        // CONDITION: Evaluates `} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
        // VALIDATION: The department must be selected from the approved Staff list before insertion.
        // SECURITY: Preventing privilege and reporting abuse by rejecting browser-modified department values.
    } elseif (!in_array($department, $valid_staff_departments, true)) {
        $error = "Please select a valid Staff department.";
        // VALIDATION: This regular expression accepts Malaysian mobile numbers beginning with 01, +601, or 601 followed by the required digits.
        // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
        // CONDITION: Evaluates `} elseif (!preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) {
        $error = "Please enter a valid Malaysian phone number! (e.g., 0123456789)";
        // VALIDATION: Strict password policy enforces length, uppercase, lowercase, number, and special character requirements.
        // WHY: Stronger passwords reduce the risk of unauthorized access to claim and payment information.
    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\\\|`~]/', $password)
    ) {
        $error = "Password must contain at least 8 characters, 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.";
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    } else {
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR staff_id = ?");
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $check->bind_param("ss", $email, $staff_id);
        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        $check->execute();
        // WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
        // CONDITION: Evaluates `if ($check->get_result()->num_rows > 0) ` so the application can choose the correct business rule branch for the current user action.
        if ($check->get_result()->num_rows > 0) {
            $error = "Email or Staff ID already exists.";
            // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            // SECURITY: Hashing password using bcrypt.
            // WHY: Only the password hash is stored, protecting the original password from database disclosure.
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // === SECTION: EMAIL VERIFICATION TOKEN GENERATION ===
            // What: Create a random verification token and expiry timestamp for the new Staff account.
            // Why: Login must be blocked until the user proves ownership of the registered email address.
            // SECURITY: random_bytes() produces cryptographically secure token material for the verification link.
            $email_verified = 0;
            $verification_token = bin2hex(random_bytes(32));
            $verification_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
            // TRANSACTION: The account insert and verification email are treated as one registration workflow.
            // WHY: If the email cannot be sent, the user should not be left with an unusable unverified account that blocks re-registration.
            $conn->begin_transaction();
            $stmt = $conn->prepare("INSERT INTO users (name, staff_id, email, department, password, phone, role, status, email_verified, email_verification_token, email_verification_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?)");
            if (!$stmt) {
                $conn->rollback();
                $error = "Registration setup is incomplete. Please run the email verification SQL migration first.";
            } else {
                // SECURITY: Using Prepared Statements to prevent SQL Injection.
                // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
                $stmt->bind_param("sssssssiss", $name, $staff_id, $email, $department, $hashed_password, $phone, $role, $email_verified, $verification_token, $verification_expires_at);
                // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
                // CONDITION: Evaluates `if ($stmt->execute())` so the application can choose the correct business rule branch for the current user action.
                if ($stmt->execute()) {
                    // === SECTION: VERIFICATION EMAIL DELIVERY ===
                    // What: Build a signed account verification link and email it to the registered user.
                    // Why: The business workflow requires verified email ownership before a Staff account can access the claim portal.
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
                    $verification_link = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/verify_email.php?token=' . urlencode($verification_token);

                    // SECURITY: Preventing XSS by escaping dynamic email content before inserting it into the HTML message.
                    $safe_link = htmlspecialchars($verification_link, ENT_QUOTES, 'UTF-8');

                    $verification_body = '
                        <p style="margin:0 0 14px;">Your UTMSPACE Staff account has been created successfully.</p>
                        <p style="margin:0 0 18px;">Please verify your email address before logging in to the claim system.</p>
                        <p style="margin:0 0 20px;">
                            <a href="' . $safe_link . '" style="display:inline-block; background:#0B3B5E; color:#ffffff; padding:12px 18px; border-radius:8px; text-decoration:none; font-weight:700;">
                                Verify Email Address
                            </a>
                        </p>
                        <p style="margin:0; color:#64748b; font-size:13px;">This verification link expires in 24 hours.</p>
                        <p style="margin:12px 0 0; color:#64748b; font-size:13px;">If the button does not work, open this link in your browser:<br>' . $safe_link . '</p>
                    ';

                    if (sendSystemEmail($email, $name, 'Verify your UTMSPACE account', $verification_body)) {
                        // TRANSACTION: Commit only after the verification email has been accepted by the SMTP service.
                        // WHY: This keeps the registration workflow consistent for users and avoids duplicate unverified records after mail failures.
                        $conn->commit();
                        $success = "Registration successful. Please check your email and verify your account before logging in.";
                    } else {
                        $conn->rollback();
                        $error = "Registration could not be completed because the verification email was not sent. Please check SMTP settings or try again later.";
                    }
                }
                // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
                else {
                    $conn->rollback();
                    $error = "Registration failed.";
                }
                $stmt->close();
            }
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
    <title>Register - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --utm-navy: #0B3B5E;
            --utm-red: #C1272D;
            --utm-gray: #475569;
            --utm-light: #F8FAFC;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* WHY: The blurred background preserves institutional branding while keeping foreground forms readable. */
        .blurry-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?php echo $bg_image; ?>');
            background-size: cover;
            background-position: center;
            filter: blur(12px);
            transform: scale(1.1);
            z-index: 0;
        }

        /* WHY: The blurred background preserves institutional branding while keeping foreground forms readable. */
        .blurry-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 59, 94, 0.7);
        }

        /* WHY: The content wrapper centers the authentication or landing card over the background image. */
        .content-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .glass-card {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 700px;
        }

        /* Logo Styles - SAME AS INDEX */
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
        }

        .logo-divider {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--utm-red), #ff4b52);
            margin: 10px auto 0;
            border-radius: 3px;
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control,
        .form-select {
            border-radius: 12px;
            padding: 10px 15px;
            border: 1px solid rgba(11, 59, 94, 0.1);
            background: rgba(255, 255, 255, 0.5);
        }

        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:focus,
        .form-select:focus {
            border-color: var(--utm-red);
            box-shadow: 0 0 0 4px rgba(193, 39, 45, 0.1);
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-primary-custom {
            background: var(--utm-navy);
            color: white;
            border-radius: 50px;
            padding: 12px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-primary-custom:hover {
            background: var(--utm-red);
            transform: translateY(-2px);
        }

        .role-badge {
            background: rgba(193, 39, 45, 0.1);
            color: var(--utm-navy);
            padding: 6px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
        }

        /* SECTION: ANIMATION - Keyframes add subtle entrance motion to guide attention without changing business logic. */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
    </style>
</head>
<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->

<body class="register-page">
    <div class="blurry-bg"></div>
    <div class="content-wrapper">
        <div class="glass-card p-4 p-md-5 fade-in-up">

            <!-- Logo - SAME AS INDEX -->
            <div class="utm-logo">
                <img src="css/images/utmspace logo.png" alt="UTMSPACE Logo" class="utmspace-logo-img"
                    style="background: transparent;"
                    onerror="this.src='css/images/utm_space1.jpg'">
                <div class="logo-divider"></div>
            </div>

            <div class="text-center mb-4">
                <h3 class="fw-bold" style="color: var(--utm-navy);">Staff Registration</h3>
                <div class="role-badge mb-2"><i class="fas fa-id-badge me-2"></i>UTMSPACE Staff Portal</div>
            </div>

            <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
            <form method="POST" id="regForm">
                <?php echo csrfInputField(); ?>
                <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
                <div class="row g-3">
                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                    <div class="col-md-6">
                        <label class="form-label ms-1">Full Name *</label>
                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                        <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                    <div class="col-md-6">
                        <label class="form-label ms-1">Staff ID *</label>
                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                        <input type="text" name="staff_id" class="form-control" required value="<?php echo htmlspecialchars($_POST['staff_id'] ?? ''); ?>">
                    </div>
                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                    <div class="col-md-6">
                        <label class="form-label ms-1">Email *</label>
                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                        <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <!-- BOOTSTRAP LAYOUT: col-md-6 gives each field half the row on medium screens so forms remain scannable. -->
                    <div class="col-md-6">
                        <label class="form-label ms-1">Phone *</label>
                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                        <!-- WHY: The previous phone value is encoded before display so failed submissions can repopulate safely without rendering executable HTML. -->
                        <input type="tel" name="phone" id="phoneInput" class="form-control" required
                            pattern="^(\+?6?01)[0-9]{8,9}$"
                            title="Please enter a valid Malaysian phone number (e.g., 0123456789)"
                            value="<?php echo htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label ms-1">Department *</label>
                        <select name="department" class="form-select" required>
                            <option value="">Choose your department...</option>
                            <?php foreach ($valid_staff_departments as $department_option): ?>
                                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                <!-- WHY: Department values are encoded before display so submitted profile metadata remains safe and report-ready. -->
                                <option value="<?php echo htmlspecialchars($department_option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($_POST['department'] ?? '') == $department_option) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($department_option, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label ms-1">Password *</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <ul class="small text-muted mt-2 list-unstyled ms-1" id="reqs">
                            <li id="req-length">✗ Minimum 8 characters</li>
                            <li id="req-upper">✗ At least 1 uppercase letter</li>
                            <li id="req-lower">✗ At least 1 lowercase letter</li>
                            <li id="req-number">✗ At least 1 number</li>
                            <li id="req-special">✗ At least 1 special character</li>
                        </ul>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary-custom w-100 mt-4 shadow-sm">
                    <i class="fas fa-user-check me-2"></i>Create Account
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <p class="mb-0">Already a member? <a href="login.php" class="fw-bold" style="color: var(--utm-red);">Log In</a></p>
            </div>
        </div>
    </div>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script>
        const p = document.getElementById('password');
        const passwordRequirements = {
            length: document.getElementById('req-length'),
            upper: document.getElementById('req-upper'),
            lower: document.getElementById('req-lower'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };
        const form = document.getElementById('regForm');
        let isPasswordValid = false;

        // SECTION: CLIENT PASSWORD REQUIREMENT RENDERING - Updates one checklist row with a tick or cross.
        // WHY: Real-time feedback helps users correct weak passwords before submitting the registration form.
        function updatePasswordRequirement(element, isValid) {
            const requirementText = element.textContent.substring(2);
            element.textContent = (isValid ? '✓ ' : '✗ ') + requirementText;
            element.style.color = isValid ? '#28a745' : '#6c757d';
        }

        // SECTION: CLIENT PASSWORD POLICY CHECK - Mirrors the server-side strict password rule.
        // WHY: Running the same rule in the browser gives immediate UX feedback while the PHP rule remains the authoritative security control.
        function validatePasswordPolicy() {
            const v = p.value;
            const hasLength = v.length >= 8;
            const hasUpper = /[A-Z]/.test(v);
            const hasLower = /[a-z]/.test(v);
            const hasNumber = /[0-9]/.test(v);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(v);

            updatePasswordRequirement(passwordRequirements.length, hasLength);
            updatePasswordRequirement(passwordRequirements.upper, hasUpper);
            updatePasswordRequirement(passwordRequirements.lower, hasLower);
            updatePasswordRequirement(passwordRequirements.number, hasNumber);
            updatePasswordRequirement(passwordRequirements.special, hasSpecial);

            isPasswordValid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial;
            return isPasswordValid;
        }

        // WHY: Real-time input validation gives users immediate feedback before server-side validation runs.
        p.addEventListener('input', validatePasswordPolicy);

        const phoneInput = document.getElementById('phoneInput');
        // CONDITION: Evaluates `if (phoneInput) ` so the application can choose the correct business rule branch for the current user action.
        if (phoneInput) {
            // WHY: Real-time input validation gives users immediate feedback before server-side validation runs.
            phoneInput.addEventListener('input', function() {
                // VALIDATION: This input mask allows only digits and plus signs so Malaysian phone numbers remain clean.
                this.value = this.value.replace(/[^0-9+]/g, '');
            });
        }

        // WHY: Submit handling prevents weak or invalid forms from being sent and displays clear user feedback.
        form.addEventListener('submit', function(e) {
            // CONDITION: Evaluates `if (!isPasswordValid) ` so the application can choose the correct business rule branch for the current user action.
            if (!validatePasswordPolicy()) {
                // WHY: preventDefault() pauses browser submission so client-side validation or confirmation can run first.
                e.preventDefault();
                // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
                Swal.fire({
                    icon: 'warning',
                    title: 'Weak Password',
                    text: 'Please ensure your password has at least 8 characters, 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.',
                    confirmButtonColor: '#C1272D',
                    background: 'rgba(255, 255, 255, 0.95)',
                    backdrop: `rgba(11, 59, 94, 0.4)`
                });
            }
        });

        // CONDITION: Evaluates `if($success)` so the application can choose the correct business rule branch for the current user action.
        <?php if ($success): ?>
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                icon: 'success',
                title: 'Check Your Email',
                text: '<?php echo addslashes($success); ?>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#0B3B5E',
                background: 'rgba(255, 255, 255, 0.95)',
                backdrop: `rgba(11, 59, 94, 0.4)`
            });
            // CONDITION: Evaluates `elseif($error)` so the application can choose the correct business rule branch for the current user action.
        <?php elseif ($error): ?>
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#C1272D',
                background: 'rgba(255, 255, 255, 0.95)',
                backdrop: `rgba(11, 59, 94, 0.4)`,
                showClass: {
                    popup: 'animate__animated animate__shakeX'
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>
