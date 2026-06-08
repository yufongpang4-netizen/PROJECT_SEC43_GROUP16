<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: Manage_User_Admin.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
// =========================================================================
// SECTION 1: SESSION MANAGEMENT & SECURITY VALIDATION
// Purpose: Ensure only authenticated Administrators can access this module.
// =========================================================================
// SECTION: SESSION INITIALIZATION - Starts or resumes the browser session so the application can identify the current authenticated user.
session_start(); // Resume the active session

// Security Check: Kick out anyone who isn't logged in OR isn't an 'admin'.
// SECURITY: This session condition prevents unauthenticated users from reaching protected business pages.
// SECURITY: Role-based branching separates Staff, Finance, and Admin privileges so users can only access their permitted workflow.
// CONDITION: Evaluates `if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') ` so the application can choose the correct business rule branch for the current user action.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // SECURITY: Redirecting immediately protects restricted pages after a failed authorization or invalid-record check.
    header("Location: ../login.php");
    // BEST PRACTICE: Terminating after redirect prevents the remaining protected HTML or PHP logic from executing accidentally.
    exit(); // Stop script execution immediately
}

// SECTION: DEPENDENCY LOADING - Loads shared services so this page uses the same database and library logic as the rest of the system.
require_once '../db.php'; // Include database connection

// Initialize variables for UI feedback messages
$message      = '';
$message_type = 'success';
$view_user    = null;

// =========================================================================
// SECTION 2: TOGGLE USER STATUS (ACTIVATE / DEACTIVATE)
// Purpose: Safely allow admins to suspend or reactivate accounts.
// =========================================================================
// Check if the URL contains 'toggle_status' and ensure it is a valid number to prevent SQL errors.
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
// CONDITION: Evaluates `if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) ` so the application can choose the correct business rule branch for the current user action.
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    // WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
    $uid = (int)$_GET['toggle_status']; // Cast to integer for extra security

    // Query to check the current status and role of the targeted user
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
    $chk = $conn->prepare("SELECT role, name, status FROM users WHERE id=?");
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
    $chk->bind_param("i", $uid);
    // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
    $chk->execute();
    // WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();

    // Business Logic & Security Constraints:
    // CONDITION: Evaluates `if (!$row) ` so the application can choose the correct business rule branch for the current user action.
    if (!$row) {
        $message      = "User not found.";
        $message_type = 'error';
    // CONDITION: Evaluates `} elseif ($row['role'] === 'admin') ` so the application can choose the correct business rule branch for the current user action.
    } elseif ($row['role'] === 'admin') {
        // Prevent deactivating other admins to avoid locking everyone out of the system
        $message      = "Cannot alter the status of an Admin account.";
        $message_type = 'error';
    // CONDITION: Evaluates `} elseif ((int)$uid === (int)$_SESSION['user_id']) ` so the application can choose the correct business rule branch for the current user action.
    } elseif ((int)$uid === (int)$_SESSION['user_id']) {
        // Prevent the current admin from deactivating themselves accidentally
        $message      = "You cannot deactivate your own account.";
        $message_type = 'error';
    // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
    } else {
        // Flip the status: If Active, make Inactive. If Inactive, make Active.
        $new_status = ($row['status'] === 'Active') ? 'Inactive' : 'Active';

        // Execute the update query securely using Prepared Statements
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $upd = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $upd->bind_param("si", $new_status, $uid);
        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        $upd->execute();
        $upd->close();

        // Set success message to display via SweetAlert later
        // SECURITY: addslashes() protects generated JavaScript strings from breaking syntax when server messages contain quotes.
        $message = "User " . addslashes($row['name']) . " is now " . $new_status . ".";
        $message_type = 'success';
    }
}

// =========================================================================
// SECTION 3: ADD NEW USER LOGIC & SERVER-SIDE VALIDATION
// Purpose: Process new user registration securely with strict data rules.
// =========================================================================
// Check if a POST request was submitted specifically for adding a user
// SECTION: FORM SUBMISSION HANDLER - Processes user input only after an intentional form submission.
// WHY: Reading POST data captures the user-submitted business values before validation and database updates.
// CONDITION: Evaluates `if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') ` so the application can choose the correct business rule branch for the current user action.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {

    // Sanitize input: trim() removes accidental whitespace from the beginning/end of inputs
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $new_name     = trim($_POST['name'] ?? '');
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $new_staff_id = trim($_POST['staff_id'] ?? '');
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $new_email    = trim($_POST['email'] ?? '');
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $new_phone    = trim($_POST['phone'] ?? '');
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $new_role     = $_POST['role'] ?? '';
    // BEST PRACTICE: trim() removes accidental whitespace before validation so values are stored and compared consistently.
    $new_dept     = trim($_POST['department'] ?? '');
    // WHY: Reading POST data captures the user-submitted business values before validation and database updates.
    $new_pass     = $_POST['password'] ?? '';

    $errors = []; // Array to collect validation error messages

    // --- Data Validation Rules (Regex & Logic) ---
    // Validate Name: Only letters, spaces, apostrophes, and hyphens allowed. Length 2-50.
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($new_name)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($new_name)) {
        $errors[] = "Full name is required.";
    // VALIDATION: The regular expression enforces a strict input pattern before data is accepted.
    // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
    // CONDITION: Evaluates `} elseif (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $new_name)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $new_name)) {
        $errors[] = "Name must be 2-50 characters (letters, spaces only).";
    }

    // Validate Staff ID: Only alphanumeric characters allowed. Length 5-15.
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($new_staff_id)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($new_staff_id)) {
        $errors[] = "Staff ID is required.";
    // VALIDATION: The regular expression enforces a strict input pattern before data is accepted.
    // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
    // CONDITION: Evaluates `} elseif (!preg_match('/^[a-zA-Z0-9]{5,15}$/', $new_staff_id)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!preg_match('/^[a-zA-Z0-9]{5,15}$/', $new_staff_id)) {
        $errors[] = "Staff ID must be 5-15 characters (letters/numbers).";
    }

    // Validate Email: Uses PHP's built-in filter to check for valid email format (e.g., text@domain.com)
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($new_email)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($new_email)) {
        $errors[] = "Email address is required.";
    // VALIDATION: FILTER_VALIDATE_EMAIL verifies email structure before storage or notification delivery.
    // CONDITION: Evaluates `} elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Validate Phone: Checks for standard Malaysian format (e.g., 0123456789 or +601...)
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($new_phone)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($new_phone)) {
        $errors[] = "Phone number is required.";
    // VALIDATION: This regular expression accepts Malaysian mobile numbers beginning with 01, +601, or 601 followed by the required digits.
    // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
    // CONDITION: Evaluates `} elseif (!preg_match('/^(\+?6?01)[0-9]{8,9}$/', $new_phone)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!preg_match('/^(\+?6?01)[0-9]{8,9}$/', $new_phone)) {
        $errors[] = "Please enter a valid Malaysian phone number! (e.g., 0123456789)";
    }

    // Validate Password Strength: Minimum 6 chars, must contain both letters and numbers
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($new_pass)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($new_pass)) {
        $errors[] = "Password is required.";
    } elseif (strlen($new_pass) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    // VALIDATION: This regular expression enforces at least one number to improve password complexity.
    // WHY: match maps each role or claim status to a consistent visual outcome, keeping business states predictable.
    // CONDITION: Evaluates `} elseif (!preg_match('/[A-Za-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) ` so the application can choose the correct business rule branch for the current user action.
    } elseif (!preg_match('/[A-Za-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
        $errors[] = "Password must contain letters and numbers.";
    }

    // Validate Role: Ensure the role is strictly one of the 3 allowed options (prevents HTML manipulation)
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($new_role) || !in_array($new_role, ['staff', 'finance', 'admin'])) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($new_role) || !in_array($new_role, ['staff', 'finance', 'admin'])) {
        $errors[] = "Please select a valid role.";
    }

    // Validate Department: Mandatory only if the role is 'staff'
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if ($new_role == 'staff' && empty($new_dept)) ` so the application can choose the correct business rule branch for the current user action.
    if ($new_role == 'staff' && empty($new_dept)) {
        $errors[] = "Department is required for Staff accounts.";
    }

    // --- Duplication Check ---
    // If no validation errors so far, check if the Email or Staff ID already exists in the database
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($errors)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($errors)) {
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $dup = $conn->prepare("SELECT id FROM users WHERE email = ? OR staff_id = ?");
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $dup->bind_param("ss", $new_email, $new_staff_id);
        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        $dup->execute();
        $dup->store_result(); // Store result to check the row count

        // CONDITION: Evaluates `if ($dup->num_rows > 0) ` so the application can choose the correct business rule branch for the current user action.
        if ($dup->num_rows > 0) {
            $errors[] = "Email or Staff ID is already registered.";
        }
        $dup->close();
    }

    // --- Database Insertion ---
    // If all validations pass, proceed to insert the user into the database
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (empty($errors)) ` so the application can choose the correct business rule branch for the current user action.
    if (empty($errors)) {
        // SECURITY: Hash the password using bcrypt. NEVER store plain-text passwords!
        // SECURITY: Hashing password using bcrypt.
        // WHY: Only the password hash is stored, protecting the original password from database disclosure.
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

        // Auto-assign departments for special roles
        // CONDITION: Evaluates `if ($new_role == 'finance') ` so the application can choose the correct business rule branch for the current user action.
        if ($new_role == 'finance') {
            $new_dept = 'Finance';
        } elseif ($new_role == 'admin') {
            $new_dept = NULL;
        }

        // Insert query using Prepared Statements to prevent SQL injection
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $ins = $conn->prepare("INSERT INTO users (name, staff_id, email, password, department, phone, role, status) VALUES (?,?,?,?,?,?,?,'Active')");
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $ins->bind_param("sssssss", $new_name, $new_staff_id, $new_email, $hashed_password, $new_dept, $new_phone, $new_role);

        // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
        // CONDITION: Evaluates `if ($ins->execute()) ` so the application can choose the correct business rule branch for the current user action.
        if ($ins->execute()) {
            // SECURITY: addslashes() protects generated JavaScript strings from breaking syntax when server messages contain quotes.
            $message = "New user " . addslashes($new_name) . " added successfully.";
            $message_type = 'success';
        // CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome.
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $ins->close();
    }

    // If there were any errors during validation, combine them into a single string for the popup
    // VALIDATION: This condition rejects incomplete input so the database does not receive unusable claim or account records.
    // CONDITION: Evaluates `if (!empty($errors)) ` so the application can choose the correct business rule branch for the current user action.
    if (!empty($errors)) {
        $message = implode("<br>", $errors); // Join array elements with an HTML line break
        $message_type = 'error';
    }
}

// =========================================================================
// SECTION 4: DATA FETCHING FOR UI
// =========================================================================

// Fetch specific user data if 'view' parameter is passed (Used for a View Modal)
// WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
// CONDITION: Evaluates `if (isset($_GET['view']) && is_numeric($_GET['view'])) ` so the application can choose the correct business rule branch for the current user action.
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    // WHY: GET parameters support filters, selected records, and dashboard links that can be bookmarked or refreshed.
    $vid   = (int)$_GET['view'];
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
    $vstmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
    $vstmt->bind_param("i", $vid);
    // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
    $vstmt->execute();
    // WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
    // WHY: fetch_assoc() returns one database row as named fields, making the business data readable and display-ready.
    $view_user = $vstmt->get_result()->fetch_assoc();
    $vstmt->close();
}

// Fetch all users to display in the main DataTables list (newest first)
$sql = "SELECT * FROM users ORDER BY id DESC";
// SECURITY: Using Prepared Statements to prevent SQL Injection.
// WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
$stmt = $conn->prepare($sql);
// WHY: Executing the prepared statement performs the validated database operation for the current workflow.
$stmt->execute();
// WHY: get_result() turns the executed query into rows that can be rendered in dashboards, tables, or decision screens.
$users_result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<!-- SECTION: DOCUMENT METADATA - Loads responsive settings and external UI libraries required by this page. -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin | UTMSPACE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">

    <!-- SECTION: PAGE-SPECIFIC CSS - Defines local layout and visual rules for this screen. -->
    <style>
        /* =========================================================================
           SECTION 5: CUSTOM CSS STYLING
           ========================================================================= */
        /* SECTION: DESIGN TOKENS - Central color variables keep role themes consistent across cards, buttons, and navigation. */
        :root {
            --admin-primary: #2e1065;
            --admin-secondary: #4c1d95;
            --admin-accent: #8b5cf6;
            --admin-bg: #faf5ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        /* SECTION: PAGE FOUNDATION - Body rules set the base font, background, and overflow behavior for the whole screen. */
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: var(--admin-bg);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            overflow-x: hidden;
        }

        /* BOOTSTRAP LAYOUT: The full-width container lets dashboard pages use the complete viewport for side navigation plus content. */
        .container-fluid {
            height: 100%;
            overflow: hidden;
        }

        /* BOOTSTRAP LAYOUT: The zero-gutter row removes unwanted spacing between the sidebar and the main workspace. */
        .row.g-0 {
            height: 100%;
        }

        /* Sidebar Styling */
        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar {
            background: linear-gradient(180deg, #2e1065 0%, #4c1d95 100%);
            height: 100vh;
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
            position: sticky;
            top: 0;
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 5px;
        }

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
            background: rgba(139, 92, 246, 0.2);
            color: #8b5cf6;
            transform: translateX(5px);
        }

        /* SECTION: SIDEBAR NAVIGATION - The fixed-height sidebar keeps role-specific navigation visible for repeated dashboard use. */
        .sidebar .nav-link.active {
            background: #8b5cf6;
            color: #2e1065;
            font-weight: 600;
        }

        /* Main Content Layout */
        /* SECTION: MAIN WORKSPACE - A scrollable content panel allows long tables and forms without moving the sidebar. */
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }

        /* SECTION: PAGE HEADER - The banner identifies the current workflow and gives immediate user context. */
        .page-header {
            background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }

        /* SECTION: CARD/PANEL COMPONENT - This visual container groups related controls or records so business information is easier to scan. */
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            padding: 20px;
        }

        /* Button Styling */
        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-add {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
            color: white;
        }

        /* Table Styling */
        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom {
            margin-bottom: 0;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom thead {
            background: #f1f5f9;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom th {
            color: #2e1065;
            font-weight: 600;
            padding: 15px;
            border: none;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f3e8ff;
        }

        /* SECTION: TABLE READABILITY - Table styling supports quick review of users, claims, and report rows. */
        .table-custom tr:hover {
            background: #faf5ff;
        }

        /* UI Badges for Roles and Statuses */
        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .role-staff {
            background: #3b82f6;
            color: white;
        }

        .role-finance {
            background: #10b981;
            color: white;
        }

        .role-admin {
            background: #ef4444;
            color: white;
        }

        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-active {
            background: #d1fae5;
            color: #059669;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* SECTION: STATUS PRESENTATION - Status colors distinguish Pending, Approved, Paid, Rejected, and Cancelled claim states. */
        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Action Buttons in Table */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-view {
            background: #8b5cf6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-view:hover {
            background: #7c3aed;
            color: white;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-deactivate {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
        }

        /* SECTION: ACTION BUTTONS - Button styling highlights primary actions such as submitting claims, approving claims, exporting reports, or paying claims. */
        .btn-activate {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
        }

        /* DataTables Custom Overrides */
        /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
        .dataTables_filter input {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 6px 12px;
            margin-left: 10px;
        }

        /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
        .dataTables_filter label {
            color: #2e1065;
            font-weight: 500;
        }

        /* SECTION: DATATABLES OVERRIDES - Plugin styling is adjusted so search, pagination, and length controls match the portal theme. */
        .dataTables_length select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 5px 30px 5px 12px !important;
            margin: 0 5px;
        }

        .page-item.active .page-link {
            background-color: #8b5cf6 !important;
            border-color: #8b5cf6 !important;
            color: white !important;
        }

        .page-link {
            color: #2e1065 !important;
        }

        /* Page Load Animation */
        /* SECTION: ANIMATION - Keyframes add subtle entrance motion to guide attention without changing business logic. */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
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
                        <i class="fas fa-user-shield fs-1" style="color:#8b5cf6;"></i>
                        <h5 class="mt-2">UTMSPACE</h5>
                        <small>Admin Portal</small>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <!-- SECTION: ROLE NAVIGATION - Provides role-specific movement between the pages allowed for the current user. -->
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard_Admin.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard</a>
                        <a class="nav-link active" href="Manage_User_Admin.php"><i class="fas fa-users fa-fw me-2"></i> Manage Accounts</a>
                        <a class="nav-link" href="Manage_Claims_Admin.php"><i class="fas fa-file-invoice-dollar fa-fw me-2"></i> Manage Claims</a>
                        <a class="nav-link" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar fa-fw me-2"></i> Generate Report</a>
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
                            <h3 class="mb-1"><i class="fas fa-users me-2" style="color: #8b5cf6;"></i>Manage User Accounts</h3>
                            <p class="mb-0 opacity-75">View, add, and manage staff, finance, and admin accounts</p>
                        </div>
                        <button class="btn btn-add mt-2 mt-sm-0" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus me-2"></i>Add New User
                        </button>
                    </div>
                </div>

                <div class="table-card fade-in">
                    <div class="table-responsive">
                        <!-- SECTION: DATA TABLE - Presents database records in an examiner-friendly format for review, audit, or reporting. -->
                        <table class="table table-custom" id="adminUsersTable">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <td><code><?php echo htmlspecialchars($user['staff_id'] ?? '—'); ?></code></td>

                                        <td class="fw-semibold">
                                            <i class="fas <?php echo match ($user['role']) {
                                                                'finance' => 'fa-user-tie',
                                                                'admin' => 'fa-user-shield',
                                                                default => 'fa-user'
                                                            }; ?> me-2" style="color: #8b5cf6;"></i>
                                            <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </td>

                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>

                                        <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>

                                        <!-- SECURITY: Escaping output to prevent XSS attacks. -->
                                        <td><?php echo htmlspecialchars($user['department'] ?? '—'); ?></td>

                                        <td><span class="<?php echo $user['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $user['status']; ?></span></td>

                                        <td class="action-buttons">
                                            <a href="?view=<?php echo $user['id']; ?>" class="btn btn-view"><i class="fas fa-eye me-1"></i> View</a>

                                            <!-- CONDITION: Evaluates `if ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$_SESSION['user_id'])` so the application can choose the correct business rule branch for the current user action. -->
                                            <?php if ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$_SESSION['user_id']): ?>

                                                <?php if ($user['status'] === 'Active'): ?>
                                                    <a href="#" class="btn btn-deactivate" onclick="confirmAction('Deactivate', '<?php echo addslashes($user['name']); ?>', '?toggle_status=<?php echo $user['id']; ?>')"><i class="fas fa-ban me-1"></i> Deactivate</a>

                                                <!-- CONDITION: This fallback executes when the previous branch is false, ensuring the workflow has a clear alternative outcome. -->
                                                <?php else: ?>
                                                    <a href="#" class="btn btn-activate" onclick="confirmAction('Activate', '<?php echo addslashes($user['name']); ?>', '?toggle_status=<?php echo $user['id']; ?>')"><i class="fas fa-check-circle me-1"></i> Activate</a>
                                                <?php endif; ?>

                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION: MODAL DIALOG - Provides the missing Add New User interface targeted by the page header button. -->
    <!-- WHY: The Add New User button uses data-bs-target="#addUserModal"; this modal must exist so Bootstrap can open the account-creation form. -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <!-- SECTION: MODAL DIALOG - Keeps account creation in the current Admin workflow without leaving the Manage Accounts page. -->
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%); color: white;">
                    <h5 class="modal-title" id="addUserModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- SECTION: USER INPUT FORM - Captures new account details that are validated server-side before database insertion. -->
                <!-- WHY: The hidden action value connects this modal form to the existing add_user POST handler at the top of the file. -->
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name *</label>
                                <input type="text" name="name" class="form-control" required placeholder="Enter full name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Staff ID *</label>
                                <input type="text" name="staff_id" class="form-control" required placeholder="Enter staff ID">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email *</label>
                                <input type="email" name="email" class="form-control" required placeholder="name@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone *</label>
                                <input type="tel" name="phone" class="form-control" required placeholder="0123456789">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Role *</label>
                                <select name="role" id="addUserRole" class="form-select" required>
                                    <option value="">Select role</option>
                                    <option value="staff">Staff</option>
                                    <option value="finance">Finance</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="addUserDepartmentGroup">
                                <label class="form-label fw-semibold">Department</label>
                                <input type="text" name="department" id="addUserDepartment" class="form-control" placeholder="Required for Staff">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Password *</label>
                                <input type="password" name="password" class="form-control" required placeholder="Minimum 6 characters with letters and numbers">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-add">
                            <i class="fas fa-save me-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- SECTION: CLIENT-SIDE BEHAVIOR - Loads JavaScript used for validation, alerts, navigation, charts, or tables. -->
    <script>
        // SECTION: JQUERY READY HANDLER - Initializes table plugins after the HTML table has been rendered.
        $(document).ready(function() {
            // Initialize DataTables for pagination, searching, and sorting
            // SECTION: DATATABLES CONFIGURATION - Adds search, sorting, and pagination for examiner-friendly record review.
            $('#adminUsersTable').DataTable({
                // DATATABLES: pageLength controls visible rows to balance density and readability.
                "pageLength": 10,
                "language": {
                    "search": "<i class='fas fa-search' style='color: #8b5cf6;'></i> Search:",
                    "paginate": {
                        "next": "<i class='fas fa-chevron-right'></i>",
                        "previous": "<i class='fas fa-chevron-left'></i>"
                    }
                }
            });
        });

        // Custom function to trigger a SweetAlert confirmation before Deactivating/Activating a user.
        // It dynamically changes text and button colors based on the action passed into it.
        // SECTION: CLIENT FUNCTION - Encapsulates repeated UI logic so page behavior is easier to audit and maintain.
        // SECTION: CONFIRMATION WORKFLOW - Requires deliberate confirmation before irreversible or high-impact actions occur.
        function confirmAction(action, name, url) {
            let color = action === 'Deactivate' ? '#dc3545' : '#10b981'; // Red for deactivate, Green for activate
            // SECTION: SWEETALERT FEEDBACK - Shows high-visibility success, error, warning, or confirmation messages for important actions.
            Swal.fire({
                title: `Are you sure?`,
                text: `Do you want to ${action.toLowerCase()} the account for ${name}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: color,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${action} it!`
            }).then((result) => {
                // If user clicks "Yes", execute the PHP GET request by changing the URL
                // CONDITION: Evaluates `if (result.isConfirmed) ` so the application can choose the correct business rule branch for the current user action.
                if (result.isConfirmed) {
                    // WHY: Redirecting after success moves the user to the next logical workflow page without manual navigation.
                    window.location.href = url;
                }
            })
        }

        // SECTION: ADD USER FORM UX - Keeps the department field aligned with the selected account role.
        // WHY: Staff accounts require a department for reporting, while Finance and Admin departments are assigned by server-side business rules.
        const addUserRole = document.getElementById('addUserRole');
        const addUserDepartment = document.getElementById('addUserDepartment');

        // CONDITION: Evaluates `if(addUserRole && addUserDepartment)` so the page remains stable if the modal is not rendered.
        if(addUserRole && addUserDepartment) {
            addUserRole.addEventListener('change', function() {
                const isStaff = this.value === 'staff';
                addUserDepartment.required = isStaff;
                addUserDepartment.disabled = !isStaff;
                addUserDepartment.value = isStaff ? addUserDepartment.value : '';
            });
            addUserDepartment.disabled = true;
        }

        // Capture PHP validation messages and trigger a sleek "Toast" notification using SweetAlert.
        // CONDITION: Evaluates `if ($message)` so the application can choose the correct business rule branch for the current user action.
        <?php if ($message): ?>
            // WHY: A toast configuration provides non-blocking feedback after low-risk successful actions.
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
            });
            Toast.fire({
                icon: '<?php echo $message_type === 'success' ? 'success' : 'error'; ?>',
                // addslashes() ensures quotes inside the error message don't break the JS syntax
                title: '<?php echo addslashes($message); ?>'
            });
        <?php endif; ?>
    </script>
</body>

</html>
