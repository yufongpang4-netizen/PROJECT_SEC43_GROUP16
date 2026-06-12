<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: db.php is part of the UTMSPACE Staff Pay and Claim System.
// The following comments intentionally explain both what each block does and why
// it supports authentication, claim governance, reporting accuracy, security, or
// examiner-readable user interaction during the final-year project defense.
// ============================================================================
date_default_timezone_set('Asia/Kuala_Lumpur');

$host     = "localhost";
$user     = "root";
$password = "";
$database = "utmspace_claim";

$conn = new mysqli($host, $user, $password, $database);

// CONDITION: Evaluates `if ($conn->connect_error) ` so the application can choose the correct business rule branch for the current user action.
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// === SECTION: CLIENT IP DETECTION FOR AUDIT LOGGING ===
// What: Resolve the best available client IP address from the current request.
// Why: Online hosting may pass the real visitor address through proxy headers, while local XAMPP normally uses REMOTE_ADDR.
function getClientIpAddress()
{
    // SECURITY: Header values are treated as untrusted and validated with FILTER_VALIDATE_IP before storage.
    // Why: Attackers can manipulate forwarded headers, so only a valid IP address should be written into audit records.
    $candidate_headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($candidate_headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        // === SECTION: HOSTING HEADER NORMALIZATION ===
        // What: Extract the first IP candidate from common proxy formats used by shared hosting providers.
        // Why: InfinityFree or upstream proxies may store client IPs in forwarded headers instead of REMOTE_ADDR.
        $raw_value = trim(explode(',', $_SERVER[$header])[0]);

        if ($header === 'HTTP_FORWARDED' && preg_match('/for="?([^";,]+)"?/i', $raw_value, $matches)) {
            $raw_value = $matches[1];
        }

        // SECURITY: Remove wrapper characters and ports before validation without trusting the original header string.
        // Why: Some hosts report values such as "203.0.113.10:443" or "[2001:db8::1]".
        $ip_address = trim($raw_value, " \t\n\r\0\x0B\"[]");

        if (preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:\d+$/', $ip_address)) {
            $ip_address = preg_replace('/:\d+$/', '', $ip_address);
        }

        if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
            return $ip_address;
        }
    }

    // WHY: Some free-hosting environments may not expose a usable client IP; storing a clear label is more useful than NULL.
    return 'Unavailable';
}

// === SECTION: ACTIVITY LOGGING SERVICE ===
// What: Write user actions such as claim submission, approval, payment, profile update, and account changes into activity_log.
// Why: The project defense requires accountability evidence showing who performed important business actions and when.
function logActivity($conn, $user_id, $action, $details = '')
{
    // SECURITY: Casting the session user ID keeps the audit insert strongly typed before bind_param().
    // Why: Activity logging should never allow browser/session values to become executable SQL.
    $safe_user_id = is_numeric($user_id) ? (int)$user_id : null;
    $ip_address = getClientIpAddress();

    // === SECTION: OPTIONAL IP COLUMN SUPPORT ===
    // What: Detect whether the deployed database has an ip_address column before choosing the INSERT statement.
    // Why: Some local databases may not include ip_address, while the hosted InfinityFree table already does.
    $has_ip_column = false;
    $column_check = $conn->query("SHOW COLUMNS FROM activity_log LIKE 'ip_address'");
    if ($column_check && $column_check->num_rows > 0) {
        $has_ip_column = true;
    }

    if ($column_check) {
        $column_check->close();
    }

    if ($has_ip_column) {
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }

        // SECURITY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $stmt->bind_param("isss", $safe_user_id, $action, $details, $ip_address);
    } else {
        // SECURITY: Using Prepared Statements to prevent SQL Injection.
        // WHY: This fallback keeps older local databases working even before the optional ip_address column is added.
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
        if (!$stmt) {
            return false;
        }

        // SECURITY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
        $stmt->bind_param("iss", $safe_user_id, $action, $details);
    }

    // WHY: Returning the execution result makes logging failures detectable without breaking the user-facing workflow.
    $result = $stmt->execute();
    $stmt->close();

    // === SECTION: ALL-ADMIN ACTIVITY EMAIL DISTRIBUTION ===
    // What: Email every active Admin after, and only after, the corresponding activity record is stored successfully.
    // Why: The database remains the authoritative audit trail while email gives all Administrators immediate operational awareness.
    if ($result) {
        require_once __DIR__ . '/mailer_helper.php';

        // CONDITION: Confirm the centralized helper is available so email failure never interrupts the completed business action.
        if (function_exists('sendAdminActivityNotification')) {
            sendAdminActivityNotification($conn, $safe_user_id, $action, $details, $ip_address);
        }
    }

    return $result;
}
