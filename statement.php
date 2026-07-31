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

$stmt = $conn->prepare("SELECT * FROM employers WHERE employer_id = ?");
$stmt->bind_param("i", $employee['employer_id']);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

// Optional year filter, e.g. statement.php?year=2026 for an "annual statement"
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

$rows = [];
$total_paid = 0;
$total_all = 0;
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $total_all += $row['total_contribution'];
    if ($row['status'] == 'paid') {
        $total_paid += $row['total_contribution'];
    }
}

$months = ["", "January","February","March","April","May","June","July","August","September","October","November","December"];

// Get available years for the filter dropdown
$stmt = $conn->prepare("SELECT DISTINCT contribution_year FROM contributions WHERE employee_id = ? ORDER BY contribution_year DESC");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$years_available = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Contribution Statement</title>
    <link rel="stylesheet" href="style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .topbar { display: none !important; }
            body { background: #fff; }
            .page { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="topbar no-print">
        <span class="brand">NSSF &middot; Employee</span>
        <span><a href="employee_dashboard.php">&larr; Back to Dashboard</a></span>
    </div>

    <div class="page">
        <div class="no-print" style="margin-bottom:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <form method="GET" action="statement.php" style="max-width:none; display:flex; gap:10px; align-items:flex-end;">
                <div>
                    <label style="margin-top:0;">Filter by Year</label>
                    <select name="year">
                        <option value="0">All Years</option>
                        <?php while ($y = $years_available->fetch_assoc()) {
                            $sel = ($y['contribution_year'] == $year_filter) ? "selected" : "";
                            echo "<option value='" . $y['contribution_year'] . "' $sel>" . $y['contribution_year'] . "</option>";
                        } ?>
                    </select>
                </div>
                <button type="submit" style="margin-top:0;">Apply</button>
            </form>
            <a href="export_my_statement.php?year=<?php echo $year_filter; ?>" style="display:inline-block; padding:11px 16px; background:var(--gold); color:#2b2109; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.9rem;">
                Download CSV
            </a>
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; border-bottom:2px solid var(--green); padding-bottom:16px; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">NSSF Contribution Statement</h2>
                    <p style="color:#5B6B62; margin:4px 0 0;"><?php echo $year_filter > 0 ? "Year: $year_filter" : "All Years on Record"; ?></p>
                </div>
                <div style="text-align:right; font-size:0.85rem; color:#5B6B62;">
                    Generated: <?php echo date('d M Y, H:i'); ?>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <p style="margin:0; font-size:0.78rem; text-transform:uppercase; color:#5B6B62; letter-spacing:0.4px;">Employee</p>
                    <p style="margin:2px 0;"><strong><?php echo htmlspecialchars($employee['first_name'] . " " . $employee['last_name']); ?></strong></p>
                    <p style="margin:2px 0; font-size:0.9rem;">NSSF No: <?php echo htmlspecialchars($employee['nssf_number']); ?></p>
                    <p style="margin:2px 0; font-size:0.9rem;">National ID: <?php echo htmlspecialchars($employee['national_id']); ?></p>
                </div>
                <div>
                    <p style="margin:0; font-size:0.78rem; text-transform:uppercase; color:#5B6B62; letter-spacing:0.4px;">Employer</p>
                    <p style="margin:2px 0;"><strong><?php echo htmlspecialchars($employer['company_name']); ?></strong></p>
                    <p style="margin:2px 0; font-size:0.9rem;">Reg No: <?php echo htmlspecialchars($employer['registration_number']); ?></p>
                </div>
            </div>

            <table>
                <tr>
                    <th>Month</th><th>Year</th><th>Gross Salary</th><th>Employee (5%)</th><th>Employer (10%)</th><th>Total</th><th>Date Paid</th><th>Status</th>
                </tr>
                <?php if (count($rows) == 0) { ?>
                    <tr><td colspan="8" style="text-align:center; color:#5B6B62;">No contributions found for this period.</td></tr>
                <?php } ?>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td><?php echo $months[$row['contribution_month']]; ?></td>
                        <td><?php echo $row['contribution_year']; ?></td>
                        <td><?php echo number_format($row['gross_salary'], 2); ?></td>
                        <td><?php echo number_format($row['employee_contribution'], 2); ?></td>
                        <td><?php echo number_format($row['employer_contribution'], 2); ?></td>
                        <td><?php echo number_format($row['total_contribution'], 2); ?></td>
                        <td><?php echo $row['date_paid'] ? $row['date_paid'] : '-'; ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                    </tr>
                <?php } ?>
            </table>

            <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:16px; text-align:right;">
                <p style="margin:2px 0;">Total Recorded: <strong>UGX <?php echo number_format($total_all, 2); ?></strong></p>
                <p style="margin:2px 0;">Total Paid: <strong>UGX <?php echo number_format($total_paid, 2); ?></strong></p>
            </div>

            <p style="margin-top:24px; font-size:0.78rem; color:#5B6B62;">
                This is a system-generated statement from the NSSF Contributions Tracking System and reflects records held at the time of generation.
            </p>
        </div>
    </div>
</body>
</html>