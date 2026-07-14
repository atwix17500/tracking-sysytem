<?php
session_start();
include 'db_connect.php';

// Protect this page - only logged in employers can access it
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Find this employer's own record
$stmt = $conn->prepare("SELECT * FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];

$contribution_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get details for the log BEFORE deleting (once it's gone, we can't look it up anymore)
$stmt = $conn->prepare("SELECT c.contribution_month, c.contribution_year, e.first_name, e.last_name
                         FROM contributions c
                         JOIN employees e ON c.employee_id = e.employee_id
                         WHERE c.contribution_id = ? AND e.employer_id = ?");
$stmt->bind_param("ii", $contribution_id, $employer_id);
$stmt->execute();
$details_row = $stmt->get_result()->fetch_assoc();

// Only delete if this contribution belongs to one of THIS employer's employees
$stmt = $conn->prepare("DELETE c FROM contributions c
                         JOIN employees e ON c.employee_id = e.employee_id
                         WHERE c.contribution_id = ? AND e.employer_id = ?");
$stmt->bind_param("ii", $contribution_id, $employer_id);
$stmt->execute();

if ($details_row && $stmt->affected_rows > 0) {
    log_activity($conn, 'deleted', 'contributions', $contribution_id,
        "Deleted contribution for " . $details_row['first_name'] . " " . $details_row['last_name'] .
        " (" . $details_row['contribution_month'] . "/" . $details_row['contribution_year'] . ")");
}

header("Location: employer_dashboard.php?panel=contributions&deleted=1");
exit();
?>