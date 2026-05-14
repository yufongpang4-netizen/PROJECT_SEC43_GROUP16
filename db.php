<?php

$host     = "localhost";
$user     = "root";
$password = "";
$database = "utmspace_claim";
 
$conn = new mysqli($host, $user, $password, $database);
 
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
 
$conn->set_charset("utf8mb4");

function logActivity($conn, $user_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}
?>
 