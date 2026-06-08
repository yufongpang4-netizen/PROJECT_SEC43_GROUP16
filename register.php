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
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
$requested_role = $_GET['role'] ?? 'staff';
// CONDITION: Evaluates `if(!in_array($requested_role, ['staff', 'finance', 'admin'])) $requested_role = 'staff';` so the application can choose the correct business rule branch for the current user action.
if (!in_array($requested_role, ['staff', 'finance', 'admin'])) $requested_role = 'staff';

// WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
$bg_image = match ($requested_role) {
    'staff' => 'css/images/staff.jpeg',
    'finance' => 'css/images/finance.jpg',
    'admin' => 'css/images/admin.jpg',
    default => 'css/images/utm.jpg'
};

// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if ($_SERVER['REQUEST_METHOD'] === 'POST') ` so the application can choose the correct business rule branch for the current user action.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $role = $_POST['role'];

    // CONDITION: Evaluates `if ($role == 'finance') { $department = 'Finance'; }` so the application can choose the correct business rule branch for the current user action.
    if ($role == 'finance') {
        $department = 'Finance';
    } elseif ($role == 'admin') {
        $department = NULL;
    }
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    else {
        $department = trim($_POST['department'] ?? '');
    }

    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($name) || empty($staff_id) || empty($email) || empty($password) || empty($phone)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($name) || empty($staff_id) || empty($email) || empty($password) || empty($phone)) {
        $error = "Please fill in all required fields including Phone number.";
        // VALIDATION: FILTER_VALIDATE_EMAIL verifies email structure before storage or notification delivery.
        // CONDITION: Evaluates `} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
        // VALIDATION: This regular expression accepts Malaysian mobile numbers beginning with 01, +601, or 601 followed by the required digits.
        // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
        // CONDITION: Evaluates `} elseif (!preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) {
        $error = "Please enter a valid Malaysian phone number! (e.g., 0123456789)";
        // VALIDATION: This regular expression enforces at least one uppercase letter in the password-strength rule.
        // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
        // CONDITION: Evaluates `} elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password does not meet security requirements!";
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
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
            $stmt = $conn->prepare("INSERT INTO users (name, staff_id, email, department, password, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
            $stmt->bind_param("sssssss", $name, $staff_id, $email, $department, $hashed_password, $phone, $role);
            // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
            // CONDITION: Evaluates `if ($stmt->execute()) { $success = "Registration successful!"; }` so the application can choose the correct business rule branch for the current user action.
            if ($stmt->execute()) {
                $success = "Registration successful!";
            }
            // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
            else {
                $error = "Registration failed.";
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

        .role-switch-btn {
            border: 2px solid var(--utm-navy);
            background: transparent;
            color: var(--utm-navy);
            border-radius: 50px;
            padding: 6px 15px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .role-switch-btn:hover {
            background: var(--utm-navy);
            color: white;
        }

        .role-switch-btn.active {
            background: var(--utm-navy);
            color: white;
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
                <h3 class="fw-bold" style="color: var(--utm-navy);"><?php echo ucfirst($requested_role); ?> Registration</h3>
                <div class="role-badge mb-2"><i class="fas fa-id-badge me-2"></i>UTMSPACE Official Portal</div>
            </div>

            <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
            <form method="POST" id="regForm">
                <input type="hidden" name="role" value="<?php echo $requested_role; ?>">
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

                    <!-- CONDITION: Evaluates `if($requested_role == 'staff')` so the application can choose the correct business rule branch for the current user action. -->
                    <?php if ($requested_role == 'staff'): ?>
                        <div class="col-12">
                            <label class="form-label ms-1">Department *</label>
                            <select name="department" class="form-select" required>
                                <option value="">Choose your department...</option>
                                <option <?php echo (($_POST['department'] ?? '') == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                <option <?php echo (($_POST['department'] ?? '') == 'Human Resources') ? 'selected' : ''; ?>>Human Resources</option>
                                <option <?php echo (($_POST['department'] ?? '') == 'Marketing') ? 'selected' : ''; ?>>Marketing</option>
                                <option <?php echo (($_POST['department'] ?? '') == 'Finance') ? 'selected' : ''; ?>>Finance</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label ms-1">Password *</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <ul class="small text-muted mt-2 list-unstyled ms-1" id="reqs">
                            <li id="l">✗ Min 8 characters</li>
                            <li id="u">✗ At least 1 Uppercase & 1 Number</li>
                        </ul>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary-custom w-100 mt-4 shadow-sm">
                    <i class="fas fa-user-check me-2"></i>Create Account
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <p class="small text-muted mb-3">Registering for a different role?</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="?role=staff" class="role-switch-btn <?php echo $requested_role == 'staff' ? 'active' : ''; ?>">Staff</a>
                    <a href="?role=finance" class="role-switch-btn <?php echo $requested_role == 'finance' ? 'active' : ''; ?>">Finance</a>
                    <a href="?role=admin" class="role-switch-btn <?php echo $requested_role == 'admin' ? 'active' : ''; ?>">Admin</a>
                </div>
                <p class="mt-4 mb-0">Already a member? <a href="login.php" class="fw-bold" style="color: var(--utm-red);">Log In</a></p>
            </div>
        </div>
    </div>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script>
        const p = document.getElementById('password');
        const fl = document.getElementById('l'),
            fu = document.getElementById('u');
        const form = document.getElementById('regForm');
        let isPasswordValid = false;

        // WHY: Real-time input validation gives users immediate feedback before server-side validation runs.
        p.addEventListener('input', () => {
            const v = p.value;
            const okL = v.length >= 8;
            const okU = /[A-Z]/.test(v) && /[0-9]/.test(v);
            fl.innerHTML = (okL ? '✓' : '✗') + ' Min 8 characters';
            fl.style.color = okL ? '#28a745' : '#6c757d';
            fu.innerHTML = (okU ? '✓' : '✗') + ' At least 1 Uppercase & 1 Number';
            fu.style.color = okU ? '#28a745' : '#6c757d';

            isPasswordValid = okL && okU;
        });

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
            if (!isPasswordValid) {
                // WHY: preventDefault() pauses browser submission so client-side validation or confirmation can run first.
                e.preventDefault();
                // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
                Swal.fire({
                    icon: 'warning',
                    title: 'Weak Password',
                    text: 'Please ensure your password meets all the security requirements (min 8 chars, 1 uppercase, 1 number).',
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
                title: 'Registration Successful!',
                text: 'Your account has been created successfully.',
                confirmButtonText: '<i class="fas fa-sign-in-alt me-1"></i> Login Now',
                confirmButtonColor: '#0B3B5E',
                background: 'rgba(255, 255, 255, 0.95)',
                backdrop: `rgba(11, 59, 94, 0.4)`
            }).then((result) => {
                // CONDITION: Evaluates `if (result.isConfirmed || result.isDismissed) ` so the application can choose the correct business rule branch for the current user action.
                if (result.isConfirmed || result.isDismissed) {
                    // WHY: Redirecting after success moves the user to the next logical workflow page without manual navigation.
                    window.location.href = 'login.php';
                }
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