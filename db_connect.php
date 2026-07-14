<?php
// Database connection settings
$host = "localhost";
$username = "root";   // default XAMPP MySQL username
$password = "";        // default XAMPP MySQL password is empty
$database = "nssf_system";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---- Reusable activity logging function ----
// Call this right after any successful add/edit/delete action.
// Relies on $_SESSION being already started by the calling page.
function log_activity($conn, $action, $table_name, $record_id, $details) {
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'unknown';
    $role = $_SESSION['role'] ?? 'unknown';

    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, username, role, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssis", $user_id, $username, $role, $action, $table_name, $record_id, $details);
    $stmt->execute();
}
?>