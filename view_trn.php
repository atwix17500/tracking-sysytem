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
$employer_id = $employer['employer_id'];

$trn_number = isset($_GET['trn']) ? trim($_GET['trn']) : '';

// Ownership check: only show contributions that belong to THIS employer's employees
$stmt = $conn->prepare("
    SELECT c.*, e.first_name, e.last_name, e.nssf_number
    FROM contributions c
    JOIN employees e ON c.employee_id = e.employee_id
    WHERE c.trn_number = ? AND e.employer_id = ?
    ORDER BY e.first_name, e.last_name
");
$stmt->bind_param("si", $trn_number, $employer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("TRN not found, or it does not belong to your company.");
}

$rows = [];
$total_amount = 0;
$all_paid = true;
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $total_amount += $row['total_contribution'];
    if ($row['status'] != 'paid') {
        $all_paid = false;
    }
}
$batch_month = $rows[0]['contribution_month'];
$batch_year = $rows[0]['contribution_year'];

// ---- Handle "Mark entire TRN as Paid" ----
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_paid'])) {
    $stmt = $conn->prepare("
        UPDATE contributions c
        JOIN employees e ON c.employee_id = e.employee_id
        SET c.status = 'paid', c.date_paid = CURDATE()
        WHERE c.trn_number = ? AND e.employer_id = ?
    ");
    $stmt->bind_param("si", $trn_number, $employer_id);
    $stmt->execute();

    log_activity($conn, 'edited', 'contributions', null, "Marked TRN $trn_number as fully paid");
    header("Location: view_trn.php?trn=" . urlencode($trn_number));
    exit();
}

$months = ["", "January","February","March","April","May","June","July","August","September","October","November","December"];
?>
<!DOCTYPE html>
<html>
<head>
    <title>TRN <?php echo htmlspecialchars($trn_number); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Employer</span>
        <span><a href="employer_dashboard.php">&larr; Back to Dashboard</a></span>
    </div>

    <div class="page">
        <h2>TRN Confirmation</h2>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
                <div>
                    <p style="margin:0; font-size:0.8rem; color:#5B6B62; text-transform:uppercase; letter-spacing:0.4px;">Transaction Reference Number</p>
                    <p style="margin:2px 0 0; font-size:1.3rem; font-weight:800; color:var(--green-dark); font-family:monospace;"><?php echo htmlspecialchars($trn_number); ?></p>
                </div>
                <div>
                    <?php if ($all_paid) { ?>
                        <span class="status paid" style="font-size:0.9rem; padding:6px 14px;">Fully Paid</span>
                    <?php } else { ?>
                        <span class="status pending" style="font-size:0.9rem; padding:6px 14px;">Awaiting Payment</span>
                    <?php } ?>
                </div>
            </div>

            <p style="color:#5B6B62;">
                Period: <strong><?php echo $months[$batch_month] . " " . $batch_year; ?></strong>
                &nbsp;&middot;&nbsp; Employees Billed: <strong><?php echo count($rows); ?></strong>
                &nbsp;&middot;&nbsp; Total Amount: <strong>UGX <?php echo number_format($total_amount, 2); ?></strong>
            </p>

            <table>
                <tr>
                    <th>Employee</th><th>NSSF Number</th><th>Gross Salary</th><th>Employee (5%)</th><th>Employer (10%)</th><th>Total</th><th>Status</th>
                </tr>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['nssf_number']); ?></td>
                        <td><?php echo number_format($row['gross_salary'], 2); ?></td>
                        <td><?php echo number_format($row['employee_contribution'], 2); ?></td>
                        <td><?php echo number_format($row['employer_contribution'], 2); ?></td>
                        <td><?php echo number_format($row['total_contribution'], 2); ?></td>
                        <td><span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                    </tr>
                <?php } ?>
            </table>

            <?php if (!$all_paid) { ?>
                <form method="POST" action="view_trn.php?trn=<?php echo urlencode($trn_number); ?>" style="max-width:none; margin-top:18px;" onsubmit="return confirm('Confirm that this TRN has been paid at the bank/mobile money, and mark all these contributions as Paid?');">
                    <input type="hidden" name="mark_paid" value="1">
                    <button type="submit">Mark this TRN as Paid</button>
                </form>
                <p style="font-size:0.78rem; color:#5B6B62; margin-top:8px;">
                    Use this once you've actually paid this TRN at the bank or via mobile money.
                </p>
            <?php } ?>

            <p style="margin-top:20px;"><button onclick="window.print()" type="button">Print / Save as PDF</button></p>
        </div>
    </div>
</body>
</html>