<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM employees WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    die("No employee profile found for this account.");
}

$employee_id = $employee['employee_id'];
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$sql = "SELECT * FROM contributions WHERE employee_id = ?";
$types = "i";
$params = [$employee_id];
if ($year_filter > 0) {
    $sql .= " AND contribution_year = ?";
    $types .= "i";
    $params[] = $year_filter;
}
$sql .= " ORDER BY contribution_year ASC, contribution_month ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$filename = "my_nssf_statement_" . $employee['nssf_number'] . "_" . date('Y-m-d') . ".csv";
$filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Month', 'Year', 'Gross Salary', 'Employee Contribution (5%)',
    'Employer Contribution (10%)', 'Total Contribution', 'Date Paid', 'Status'
]);

$months = ["", "January","February","March","April","May","June","July","August","September","October","November","December"];

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $months[$row['contribution_month']],
        $row['contribution_year'],
        number_format($row['gross_salary'], 2, '.', ''),
        number_format($row['employee_contribution'], 2, '.', ''),
        number_format($row['employer_contribution'], 2, '.', ''),
        number_format($row['total_contribution'], 2, '.', ''),
        $row['date_paid'] ?: '',
        ucfirst($row['status']),
    ]);
}

fclose($output);
exit();
?>