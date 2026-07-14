<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

if (!$employer) {
    die("No employer profile found for this account.");
}

$employer_id = $employer['employer_id'];

// Read the same filters used on the dashboard, so the export matches what's on screen
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : "";
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : "";
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : "";
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : "";

$sql = "SELECT c.*, e.first_name, e.last_name, e.nssf_number
        FROM contributions c
        JOIN employees e ON c.employee_id = e.employee_id
        WHERE e.employer_id = ?";
$types = "i";
$params = [$employer_id];

if ($search_name != "") {
    $sql .= " AND CONCAT(e.first_name, ' ', e.last_name) LIKE ?";
    $types .= "s";
    $params[] = "%" . $search_name . "%";
}
if ($filter_month != "") {
    $sql .= " AND c.contribution_month = ?";
    $types .= "i";
    $params[] = $filter_month;
}
if ($filter_year != "") {
    $sql .= " AND c.contribution_year = ?";
    $types .= "i";
    $params[] = $filter_year;
}
if ($filter_status != "") {
    $sql .= " AND c.status = ?";
    $types .= "s";
    $params[] = $filter_status;
}

$sql .= " ORDER BY c.contribution_year DESC, c.contribution_month DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ---- Tell the browser this is a downloadable CSV file, not a webpage ----
$filename = "contributions_" . $employer['company_name'] . "_" . date('Y-m-d') . ".csv";
$filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename); // keep the filename filesystem-safe

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open a direct output stream and write CSV rows to it
$output = fopen('php://output', 'w');

// Late payment penalty - same rule as the dashboard (5% per month late, after the 15th of the following month)
function calculate_penalty($month, $year, $total_contribution, $status) {
    if ($status != 'overdue') {
        return 0;
    }
    $due_timestamp = mktime(0, 0, 0, $month + 1, 15, $year);
    $today_timestamp = strtotime(date('Y-m-d'));
    if ($today_timestamp <= $due_timestamp) {
        return 0;
    }
    $months_late = floor(($today_timestamp - $due_timestamp) / (30 * 24 * 60 * 60)) + 1;
    return round($total_contribution * 0.05 * $months_late, 2);
}

// Header row
fputcsv($output, [
    'Contribution ID', 'Employee Name', 'NSSF Number', 'Month', 'Year',
    'Gross Salary', 'Employee Contribution (5%)', 'Employer Contribution (10%)',
    'Total Contribution', 'Date Paid', 'Status', 'Late Penalty'
]);

$months = ["", "January","February","March","April","May","June","July","August","September","October","November","December"];

while ($row = $result->fetch_assoc()) {
    $penalty = calculate_penalty($row['contribution_month'], $row['contribution_year'], $row['total_contribution'], $row['status']);
    fputcsv($output, [
        $row['contribution_id'],
        $row['first_name'] . ' ' . $row['last_name'],
        $row['nssf_number'],
        $months[$row['contribution_month']],
        $row['contribution_year'],
        number_format($row['gross_salary'], 2, '.', ''),
        number_format($row['employee_contribution'], 2, '.', ''),
        number_format($row['employer_contribution'], 2, '.', ''),
        number_format($row['total_contribution'], 2, '.', ''),
        $row['date_paid'] ?: '',
        ucfirst($row['status']),
        $penalty > 0 ? number_format($penalty, 2, '.', '') : '0.00',
    ]);
}

fclose($output);
exit();
?>