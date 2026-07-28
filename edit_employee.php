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

$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ownership check - only this employer's own employee can be edited
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? AND employer_id = ?");
$stmt->bind_param("ii", $employee_id, $employer_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    die("Employee not found, or does not belong to your company.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = trim($_POST['phone']);
    $monthly_salary = $_POST['monthly_salary'];

    if ($monthly_salary === "" || $monthly_salary <= 0) {
        $error = "Monthly salary must be greater than zero.";
    } else {
        $stmt = $conn->prepare("UPDATE employees SET phone = ?, monthly_salary = ? WHERE employee_id = ?");
        $stmt->bind_param("sdi", $phone, $monthly_salary, $employee_id);

        if ($stmt->execute()) {
            log_activity($conn, 'edited', 'employees', $employee_id,
                "Updated employee " . $employee['first_name'] . " " . $employee['last_name'] . " (salary/phone)");
            header("Location: employer_dashboard.php?panel=employees&updated=1");
            exit();
        } else {
            $error = "Could not update employee.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Employee</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Employer</span>
        <span><a href="employer_dashboard.php">&larr; Back to Dashboard</a></span>
    </div>

    <div class="page">
        <h2>Edit Employee</h2>
        <p style="color:#5B6B62;">
            <?php echo htmlspecialchars($employee['first_name'] . " " . $employee['last_name']); ?>
            &middot; NSSF: <?php echo htmlspecialchars($employee['nssf_number']); ?>
        </p>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>

            <form method="POST" action="edit_employee.php?id=<?php echo $employee_id; ?>">
                <label>Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>">

                <label>Monthly Gross Salary (UGX)</label>
                <input type="number" name="monthly_salary" step="0.01" min="0" value="<?php echo $employee['monthly_salary']; ?>" required>
                <p style="font-size:0.78rem; color:#5B6B62; margin-top:4px;">This is used automatically whenever you generate a TRN for this employee.</p>

                <button type="submit">Update Employee</button>
            </form>

            <p style="margin-top:16px; font-size:0.85rem; color:#5B6B62;">
                Note: NSSF number, National ID, and name are fixed identity details and are not editable here.
            </p>
        </div>
    </div>
</body>
</html>