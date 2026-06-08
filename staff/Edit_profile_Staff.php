<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: Edit_profile_Staff.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start();
// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once "../db.php";

// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
// CONDITION: Evaluates `if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') ` so the application can choose the correct business rule branch for the current user action.
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: ../login.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch current user data
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
$stmt = $conn->prepare("SELECT staff_id, name, email, phone, department, created_at FROM users WHERE id=?");
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
$stmt->bind_param("i", $user_id);
// WHY: Executing the prepared statement performs the validated database operation for the current workflow.
$stmt->execute();
// WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
$result = $stmt->get_result();
// WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
$user_data = $result->fetch_assoc();
$stmt->close();

$current_phone = $user_data['phone'] ?? '';
$current_dept = $user_data['department'] ?? '';
$staff_id = $user_data['staff_id'];
// WHY: Date formatting converts database timestamps into human-readable dates for review and reports.
$join_year = date('Y', strtotime($user_data['created_at']));

// === SECTION: STAFF DEPARTMENT POLICY ===
// What: Define the only departments that Staff users are allowed to select in their profile.
// Why: Finance is a role-specific department for Finance users, while Staff profiles must remain limited to operational staff departments for accurate reporting.
$valid_staff_departments = ['Human Resources', 'Information Technology', 'Marketing', 'Sales'];

// === SECTION: LEGACY DEPARTMENT NORMALIZATION ===
// What: Convert old shorthand values into the approved full department names before rendering the dropdown.
// Why: Existing database records may still contain shortcuts such as "IT" or "HR", and the UI should display the clean business label.
$legacy_staff_department_map = [
    'IT' => 'Information Technology',
    'HR' => 'Human Resources',
];
$current_dept = $legacy_staff_department_map[$current_dept] ?? $current_dept;

// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// CONDITION: Evaluates `if($_SERVER['REQUEST_METHOD'] == 'POST') ` so the application can choose the correct business rule branch for the current user action.
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $email = trim($_POST['email']);
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $phone = trim($_POST['phone']);
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $department = trim($_POST['department'] ?? '');
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $password = $_POST['password'];
    
    // ========== VALIDATION RULES ==========
    $errors = [];
    
    // 1. Email validation
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($email)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($email)) {
        $errors[] = "Email address is required.";
    // VALIDATION: FILTER_VALIDATE_EMAIL verifies email structure before storage or notification delivery.
    // CONDITION: Evaluates `} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address (e.g., name@domain.com).";
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    } else {
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $check_email->bind_param("si", $email, $user_id);
        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        $check_email->execute();
        $check_email->store_result();
        // CONDITION: Evaluates `if ($check_email->num_rows > 0) ` so the application can choose the correct business rule branch for the current user action.
        if ($check_email->num_rows > 0) {
            $errors[] = "This email is already registered by another user.";
        }
        $check_email->close();
    }
    
    // 2. Phone validation
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // VALIDATION: This regular expression accepts Malaysian mobile numbers beginning with 01, +601, or 601 followed by the required digits.
    // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
    // CONDITION: Evaluates `if (!empty($phone) && !preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) ` so the application can choose the correct business rule branch for the current user action.
    if (!empty($phone) && !preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) {
        $errors[] = "Please enter a valid phone number! Example: 0123456789 or +60123456789";
    }

    // 2A. Department validation
    // VALIDATION: This condition accepts only the approved Staff department list before profile data is saved.
    // SECURITY: Preventing invalid department injection by rejecting browser-modified values such as Finance, Operations, or Customer Service.
    // Why: Staff department values drive reports and must stay consistent with the official business categories.
    if (empty($department) || !in_array($department, $valid_staff_departments, true)) {
        $errors[] = "Please select a valid Staff department.";
    }
    
    // 3. Password validation
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (!empty($password)) ` so the application can choose the correct business rule branch for the current user action.
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        // VALIDATION: This regular expression enforces at least one uppercase letter in the password-strength rule.
        // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
        // CONDITION: Evaluates `if (!preg_match('/[A-Z]/', $password)) ` so the application can choose the correct business rule branch for the current user action.
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least 1 uppercase letter.";
        }
        // VALIDATION: This regular expression enforces at least one uppercase letter in the password-strength rule.
        // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
        // CONDITION: Evaluates `if (!preg_match('/[a-z]/', $password)) ` so the application can choose the correct business rule branch for the current user action.
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least 1 lowercase letter.";
        }
        // VALIDATION: This regular expression enforces at least one number to improve password complexity.
        // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
        // CONDITION: Evaluates `if (!preg_match('/[0-9]/', $password)) ` so the application can choose the correct business rule branch for the current user action.
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least 1 number.";
        }
        // VALIDATION: The regular expression enforces a strict input pattern before data is accepted.
        // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
        // CONDITION: Evaluates `if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?]/', $password)) ` so the application can choose the correct business rule branch for the current user action.
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?]/', $password)) {
            $errors[] = "Password must contain at least 1 special character (!@#$%^&* etc).";
        }
    }
    
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($errors)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($errors)) {
        // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
        if (!empty($password)) {
            // SECURITY: Hashing password using bcrypt.
            // WHY: Only the password hash is stored, protecting the original password from database disclosure.
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
            $stmt = $conn->prepare("UPDATE users SET email=?, phone=?, department=?, password=? WHERE id=?");
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
            $stmt->bind_param("ssssi", $email, $phone, $department, $hashed_password, $user_id);
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
            $stmt = $conn->prepare("UPDATE users SET email=?, phone=?, department=? WHERE id=?");
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
            $stmt->bind_param("sssi", $email, $phone, $department, $user_id);
        }
        
        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        // CONDITION: Evaluates `if($stmt->execute()) ` so the application can choose the correct business rule branch for the current user action.
        if($stmt->execute()) {
            $success = "Profile updated successfully!";
            $_SESSION['email'] = $email;
            
            // CONDITION: Evaluates `if (function_exists('logActivity')) ` so the application can choose the correct business rule branch for the current user action.
            if (function_exists('logActivity')) {
                // AUDIT: Activity logging creates an accountability trail for key actions such as claim submission, cancellation, and payment.
                logActivity($conn, $user_id, 'Edit Profile', 'Updated personal profile information.');
            }
            
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
            $stmt2 = $conn->prepare("SELECT staff_id, name, email, phone, department, created_at FROM users WHERE id=?");
            // SECURITY: Using Prepared Statements to prevent SQL Injection.
            // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
            $stmt2->bind_param("i", $user_id);
            // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
            $stmt2->execute();
            // WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
            $result2 = $stmt2->get_result();
            // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
            $user_data = $result2->fetch_assoc();
            $current_phone = $user_data['phone'] ?? '';
            $current_dept = $user_data['department'] ?? '';
            $stmt2->close();
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            $error = "Failed to update profile: " . $conn->error;
        }
        $stmt->close();
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* STAFF - DARK BLUE THEME WITH LIGHT BACKGROUND */
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --staff-primary: #0f2b4d;
            --staff-secondary: #1e4d8c;
            --staff-accent: #3b82f6;
            --staff-bg: #f0f4f8;
            --staff-card: #ffffff;
            --staff-text: #1e293b;
            --staff-gray: #64748b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        html, body { height: 100%; margin: 0; padding: 0; }
        
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body {
            background: var(--staff-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* BOOTSTRAP LAYOUT: The full-width container lets dashboard pages use the complete viewport for side navigation plus content. */
        .container-fluid { height: 100%; overflow: hidden; }
        /* BOOTSTRAP LAYOUT: The zero-gutter row removes unwanted spacing between the sidebar and the main workspace. */
        .row.g-0 { height: 100%; }
        
        /* Sidebar */
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar {
            background: linear-gradient(180deg, #0f2b4d 0%, #1e4d8c 100%);
            height: 100vh;
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
            position: sticky;
            top: 0;
        }
        
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar { width: 5px; }
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 5px; }
        
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link:hover {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            transform: translateX(5px);
        }
        
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link.active {
            background: #3b82f6;
            color: #0f2b4d;
            font-weight: 600;
        }
        
        /* Main Content */
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }
        
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar { width: 8px; }
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 10px; }
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 10px; }
        
        /* Page Header */
        /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
        .page-header {
            background: linear-gradient(135deg, #0f2b4d 0%, #1e4d8c 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        /* Form Card */
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .form-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-label {
            font-weight: 600;
            color: #0f2b4d;
            margin-bottom: 8px;
        }
        
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* SECTION: FORM CONTROLS - Consistent input styling helps users enter claim/account data accurately. */
        .form-control:disabled {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .invalid-feedback-custom {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-save {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
            color: white;
        }
        
        /* Profile Card */
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .profile-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(135deg, #0f2b4d 0%, #1e4d8c 100%);
        }
        
        .profile-avatar {
            position: relative;
            margin-top: 50px;
        }
        
        .profile-avatar i {
            font-size: 80px;
            color: #3b82f6;
            background: white;
            border-radius: 50%;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .profile-name {
            margin-top: 20px;
            color: #0f2b4d;
        }
        
        .profile-role {
            color: #64748b;
            font-size: 14px;
        }
        
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #eef2ff;
        }
        
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 12px; color: #64748b; }
        .info-value { font-weight: 600; color: #0f2b4d; }
        
        /* SECTION: ANIMATION - Keyframes add subtle entrance motion to guide attention without changing business logic. */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in { animation: fadeIn 0.5s ease-out; }
        hr { border-color: #e2e8f0; }
        
        /* SECTION: RESPONSIVE RULES - These rules adapt sidebars, cards, and tables for smaller screens. */
        @media (max-width: 768px) {
            /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
            .sidebar { height: auto; position: relative; }
            /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
            .main-content { height: auto; overflow-y: visible; }
        }
    </style>
</head>
<!-- SECTION: PAGE BODY - Begins the visible interface for the current UTMSPACE workflow. -->
<body>
<!-- BOOTSTRAP LAYOUT: container-fluid spans the full browser width so dashboards can use the complete workspace. -->
<div class="container-fluid p-0">
    <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
    <div class="row g-0">
        
        <!-- BOOTSTRAP LAYOUT: col-md-3/col-lg-2 reserves a narrower column for role navigation on medium and large screens. -->
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-receipt fs-1" style="color: #3b82f6;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Staff Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <!-- SECTION: ROLE NAVIGATION - Provides role-specific movement between the pages allowed for the current user. -->
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Staff.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard</a>
                    <a class="nav-link" href="New_Claim_Staff.php"><i class="fas fa-plus-circle fa-fw me-2"></i> New Claim</a>
                    <a class="nav-link" href="Claim_History_Staff.php"><i class="fas fa-history fa-fw me-2"></i> Claim History</a>
                    <a class="nav-link active" href="Edit_profile_Staff.php"><i class="fas fa-user-edit fa-fw me-2"></i> Edit Profile</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout</a>
                </nav>
            </div>
        </div>
        
        <!-- BOOTSTRAP LAYOUT: col-md-9/col-lg-10 allocates the wider content area for tables, dashboards, and forms. -->
        <div class="col-md-9 col-lg-10 main-content">
            
            <div class="page-header fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="mb-1"><i class="fas fa-user-edit me-2" style="color: #3b82f6;"></i>Edit Profile</h3>
                        <p class="mb-0 opacity-75">Update your personal information and account settings</p>
                    </div>
                </div>
            </div>
            
            <!-- BOOTSTRAP LAYOUT: row creates the horizontal grid used to align sidebars, content columns, cards, or form fields. -->
            <div class="row g-4 fade-in">
                <div class="col-md-8">
                    <div class="form-card">
                        <div class="card-body p-4">
                            <h5 class="mb-4" style="color: #0f2b4d;"><i class="fas fa-pen me-2" style="color: #3b82f6;"></i>Personal Information</h5>
                            
                            <!-- SECTION: USER INPUT FORM - Captures business data that will be validated server-side before database changes occur. -->
                            <form method="POST" id="editProfileForm">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-user me-1" style="color: #3b82f6;"></i>Full Name</label>
                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['name']); ?>" disabled>
                                    <small class="text-muted">Name cannot be changed. Contact admin for changes.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-envelope me-1" style="color: #3b82f6;"></i>Email Address *</label>
                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                    <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                    <div id="emailFeedback" class="invalid-feedback-custom"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-phone me-1" style="color: #3b82f6;"></i>Phone Number</label>
                                    <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                    <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($current_phone); ?>" placeholder="0123456789">
                                    <div id="phoneFeedback" class="invalid-feedback-custom"></div>
                                    <small class="text-muted">Malaysian format: 0123456789 or +60123456789</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-building me-1" style="color: #3b82f6;"></i>Department</label>
                                    <select name="department" id="department" class="form-select">
                                        <?php foreach ($valid_staff_departments as $department_option): ?>
                                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                            <!-- WHY: Department labels are encoded before display so report-related profile values cannot render unsafe HTML. -->
                                            <option value="<?php echo htmlspecialchars($department_option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($current_dept == $department_option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($department_option, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label"><i class="fas fa-key me-1" style="color: #3b82f6;"></i>New Password</label>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current password">
                                    <div id="passwordFeedback" class="invalid-feedback-custom"></div>
                                    <small class="text-muted">Minimum 8 characters with uppercase, lowercase, number, and special character</small>
                                </div>
                                
                                <div id="passwordRequirements" class="small mb-3" style="display: none;">
                                    <div id="req-length" class="text-muted">✗ At least 8 characters</div>
                                    <div id="req-upper" class="text-muted">✗ At least 1 uppercase letter</div>
                                    <div id="req-lower" class="text-muted">✗ At least 1 lowercase letter</div>
                                    <div id="req-number" class="text-muted">✗ At least 1 number</div>
                                    <div id="req-special" class="text-muted">✗ At least 1 special character</div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" class="btn btn-save"><i class="fas fa-save me-2"></i>Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- BOOTSTRAP LAYOUT: col-md-4 creates three balanced columns for summary cards or supporting panels. -->
                <div class="col-md-4">
                    <div class="profile-card">
                        <div class="card-body p-4">
                            <div class="profile-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                            <h5 class="profile-name"><?php echo htmlspecialchars($user_data['name']); ?></h5>
                            <p class="profile-role">Staff Member</p>
                            
                            <hr>
                            
                            <div class="info-item">
                                <div class="info-label">Staff ID</div>
                                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                <div class="info-value"><?php echo htmlspecialchars($staff_id); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo $join_year; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                <div class="info-value"><?php echo htmlspecialchars($current_dept ?: 'Not set'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                <div class="info-value" style="word-break: break-all;"><?php echo htmlspecialchars($user_data['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                <div class="info-value"><?php echo htmlspecialchars($current_phone ?: 'Not provided'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
<script>
    // SECTION: CLIENT FUNCTION - Encapsulates repeated UI logic so page behavior is easier to audit and maintain.
    function validateEmail() {
        const email = document.getElementById('email').value;
        const feedback = document.getElementById('emailFeedback');
        // VALIDATION: The JavaScript regular expression mirrors server-side rules to improve user feedback before submission.
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        // CONDITION: Evaluates `if (!email) ` so the application can choose the correct business rule branch for the current user action.
        if (!email) {
            feedback.innerHTML = 'Email address is required.';
            return false;
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else if (!regex.test(email)) {
            feedback.innerHTML = 'Please enter a valid email address.';
            return false;
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            feedback.innerHTML = '';
            return true;
        }
    }
    
    const phoneInputField = document.getElementById('phone');
    // CONDITION: Evaluates `if (phoneInputField) ` so the application can choose the correct business rule branch for the current user action.
    if (phoneInputField) {
        // WHY: Real-time input validation gives users immediate feedback before server-side validation runs.
        phoneInputField.addEventListener('input', function() {
            // VALIDATION: This input mask allows only digits and plus signs so Malaysian phone numbers remain clean.
            this.value = this.value.replace(/[^0-9+]/g, '');
        });
    }

    // SECTION: CLIENT FUNCTION - Encapsulates repeated UI logic so page behavior is easier to audit and maintain.
    function validatePhone() {
        const phone = document.getElementById('phone').value;
        const feedback = document.getElementById('phoneFeedback');
        // VALIDATION: The JavaScript regular expression mirrors server-side rules to improve user feedback before submission.
        const regex = /^(\+?6?01)[0-9]{8,9}$/;
        
        // CONDITION: Evaluates `if (phone && !regex.test(phone)) ` so the application can choose the correct business rule branch for the current user action.
        if (phone && !regex.test(phone)) {
            feedback.innerHTML = 'Please enter a valid Malaysian phone number.';
            return false;
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            feedback.innerHTML = '';
            return true;
        }
    }
    
    const passwordInput = document.getElementById('password');
    const reqContainer = document.getElementById('passwordRequirements');
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');
    const passwordFeedback = document.getElementById('passwordFeedback');
    
    // SECTION: CLIENT FUNCTION - Encapsulates repeated UI logic so page behavior is easier to audit and maintain.
    function validatePassword() {
        const password = passwordInput.value;
        
        // CONDITION: Evaluates `if (password.length === 0) ` so the application can choose the correct business rule branch for the current user action.
        if (password.length === 0) {
            reqContainer.style.display = 'none';
            passwordFeedback.innerHTML = '';
            return true;
        }
        
        reqContainer.style.display = 'block';
        
        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
        
        updateRequirement(reqLength, hasLength);
        updateRequirement(reqUpper, hasUpper);
        updateRequirement(reqLower, hasLower);
        updateRequirement(reqNumber, hasNumber);
        updateRequirement(reqSpecial, hasSpecial);
        
        const isValid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial;
        
        // CONDITION: Evaluates `if (password && !isValid) ` so the application can choose the correct business rule branch for the current user action.
        if (password && !isValid) {
            passwordFeedback.innerHTML = 'Please meet all password requirements.';
            return false;
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            passwordFeedback.innerHTML = '';
            return true;
        }
    }
    
    // SECTION: CLIENT FUNCTION - Encapsulates repeated UI logic so page behavior is easier to audit and maintain.
    function updateRequirement(element, isValid) {
        // CONDITION: Evaluates `if (isValid) ` so the application can choose the correct business rule branch for the current user action.
        if (isValid) {
            element.innerHTML = '✓ ' + element.innerText.substring(2);
            element.style.color = '#28a745';
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            element.innerHTML = '✗ ' + element.innerText.substring(2);
            element.style.color = '#6c757d';
        }
    }
    
    // WHY: Real-time input validation gives users immediate feedback before server-side validation runs.
    document.getElementById('email').addEventListener('input', validateEmail);
    // WHY: Real-time input validation gives users immediate feedback before server-side validation runs.
    document.getElementById('phone').addEventListener('input', validatePhone);
    // WHY: Real-time input validation gives users immediate feedback before server-side validation runs.
    passwordInput.addEventListener('input', validatePassword);
    
    // SECTION: CLIENT FUNCTION - Encapsulates repeated UI logic so page behavior is easier to audit and maintain.
    function validateForm() {
        return validateEmail() && validatePhone() && validatePassword();
    }
    
    // WHY: Submit handling prevents weak or invalid forms from being sent and displays clear user feedback.
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        // CONDITION: Evaluates `if (!validateForm()) ` so the application can choose the correct business rule branch for the current user action.
        if (!validateForm()) {
            // WHY: preventDefault() pauses browser submission so client-side validation or confirmation can run first.
            e.preventDefault();
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please fix the errors in the form before submitting.',
                confirmButtonColor: '#3b82f6',
                showClass: { popup: 'animate__animated animate__shakeX' }
            });
        }
    });

    // CONDITION: Evaluates `if($success)` so the application can choose the correct business rule branch for the current user action.
    <?php if($success): ?>
        // WHY: A toast configuration provides non-blocking feedback after low-risk successful actions.
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
        Toast.fire({
            icon: 'success',
            title: '<?php echo addslashes($success); ?>'
        });
    // CONDITION: Evaluates `elseif($error)` so the application can choose the correct business rule branch for the current user action.
    <?php elseif($error): ?>
        // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
        Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            html: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#3b82f6'
        });
    <?php endif; ?>
</script>
</body>
</html>
