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

// AUDIT: Activity logging creates an accountability trail for key actions such as claim submission, cancellation, and payment.
function logActivity($conn, $user_id, $action, $details = '') {
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: SQL is prepared separately from user data so identifiers, filters, and form values can be bound safely.
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
    // SECURITY: Using Prepared Statements to prevent SQL Injection.
    // WHY: bind_param() attaches typed values to SQL placeholders, preventing input from becoming executable SQL.
    $stmt->bind_param("iss", $user_id, $action, $details);
    // WHY: Executing the prepared statement performs the validated database operation for the current workflow.
    $stmt->execute();
    $stmt->close();
}
?>
 