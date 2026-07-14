<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$employer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Find the linked user account too, so we can remove it as well
$stmt = $conn->prepare("SELECT user_id, company_name FROM employers WHERE employer_id = ?");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

if ($employer) {
    // Deleting the user cascades to the employer row (ON DELETE CASCADE),
    // which in turn cascades to their employees, and then to those employees' contributions.
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $employer['user_id']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        log_activity($conn, 'deleted', 'employers', $employer_id,
            "Deleted employer " . $employer['company_name'] . " (and all their employees/contributions)");
    }
}

header("Location: admin_dashboard.php?panel=employers&deleted=1");
exit();
?>