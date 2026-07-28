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

$error = "";
$skipped_employees = []; // employees with no salary on file - cannot be included

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];

    // Find every employee under this employer who does NOT already have
    // a contribution record for this exact month/year. This is the only
    // "selection" step - it is entirely automatic, based on real DB data.
    $stmt = $conn->prepare("
        SELECT e.employee_id, e.first_name, e.last_name, e.nssf_number, e.monthly_salary
        FROM employees e
        WHERE e.employer_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM contributions c
            WHERE c.employee_id = e.employee_id
            AND c.contribution_month = ?
            AND c.contribution_year = ?
        )
    ");
    $stmt->bind_param("iii", $employer_id, $month, $year);
    $stmt->execute();
    $eligible = $stmt->get_result();

    $employees_to_bill = [];
    while ($row = $eligible->fetch_assoc()) {
        if ($row['monthly_salary'] <= 0) {
            // Cannot auto-calculate a contribution with no salary on file - skip and flag it
            $skipped_employees[] = $row['first_name'] . " " . $row['last_name'];
        } else {
            $employees_to_bill[] = $row;
        }
    }

    if (count($employees_to_bill) == 0) {
        if (count($skipped_employees) > 0) {
            $error = "No TRN generated. All remaining employees for this period are missing a monthly salary on file. Please set their salary under Edit Employee first.";
        } else {
            $error = "No TRN generated. Every employee already has a contribution recorded for this month/year.";
        }
    } else {
        // Generate a unique, unguessable TRN
        $trn_number = "TRN-" . $employer_id . "-" . date('Ymd') . "-" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $conn->begin_transaction();
        try {
            foreach ($employees_to_bill as $emp) {
                $salary = $emp['monthly_salary'];
                $employee_contribution = round($salary * 0.05, 2);
                $employer_contribution = round($salary * 0.10, 2);
                $total_contribution = $employee_contribution + $employer_contribution;

                $stmt = $conn->prepare("INSERT INTO contributions
                    (employee_id, contribution_month, contribution_year, gross_salary, employee_contribution, employer_contribution, total_contribution, date_paid, status, trn_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 'pending', ?)");
                $stmt->bind_param("iiidddds", $emp['employee_id'], $month, $year, $salary, $employee_contribution, $employer_contribution, $total_contribution, $trn_number);
                $stmt->execute();
            }

            $conn->commit();
            log_activity($conn, 'added', 'contributions', null,
                "Generated TRN $trn_number for " . count($employees_to_bill) . " employee(s) ($month/$year)");

            header("Location: view_trn.php?trn=" . urlencode($trn_number));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Something went wrong while generating the TRN. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Generate TRN</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Employer</span>
        <span><a href="employer_dashboard.php">&larr; Back to Dashboard</a></span>
    </div>

    <div class="page">
        <h2>Generate TRN (Transaction Reference Number)</h2>
        <p style="color:#5B6B62; font-size:0.9rem;">
            Select a month and year. The system will automatically find every employee who doesn't
            already have a contribution recorded for that period, and calculate their contribution
            using the monthly salary already on file. You cannot manually add employees or amounts here.
        </p>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            <?php if (count($skipped_employees) > 0) { ?>
                <p class="message error">
                    Skipped (no salary on file): <?php echo htmlspecialchars(implode(", ", $skipped_employees)); ?>.
                    Set their salary via <a href="employer_dashboard.php?panel=employees">Employees &rarr; Edit</a> first.
                </p>
            <?php } ?>

            <form method="POST" action="generate_trn.php">
                <label>Month</label>
                <select name="month" required>
                    <?php
                    $months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
                    $current_month = (int)date('n');
                    foreach ($months as $index => $name) {
                        $num = $index + 1;
                        $selected = ($num == $current_month) ? "selected" : "";
                        echo "<option value='$num' $selected>$name</option>";
                    }
                    ?>
                </select>

                <label>Year</label>
                <input type="number" name="year" value="<?php echo date('Y'); ?>" min="2000" max="2100" required>

                <button type="submit">Generate TRN</button>
            </form>
        </div>
    </div>
</body>
</html>