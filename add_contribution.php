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
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $employee_id = $_POST['employee_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $gross_salary = $_POST['gross_salary'];

    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? AND employer_id = ?");
    $stmt->bind_param("ii", $employee_id, $employer_id);
    $stmt->execute();
    $check = $stmt->get_result();

    if ($check->num_rows == 0) {
        $error = "Invalid employee selected.";
    } elseif ($gross_salary <= 0) {
        $error = "Gross salary must be greater than zero.";
    } else {
        // ---- Handle optional proof of payment upload ----
        $proof_filename = null;
        if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB

            $file_type = mime_content_type($_FILES['proof_file']['tmp_name']);
            $file_size = $_FILES['proof_file']['size'];

            if (!in_array($file_type, $allowed_types)) {
                $error = "Proof of payment must be a PDF, JPG, or PNG file.";
            } elseif ($file_size > $max_size) {
                $error = "Proof of payment file must be smaller than 5MB.";
            } else {
                $extension = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
                // Unique, safe filename: employer + employee + timestamp, so files never overwrite each other
                $proof_filename = "receipt_" . $employer_id . "_" . $employee_id . "_" . time() . "." . $extension;
                $destination = "uploads/receipts/" . $proof_filename;

                if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], $destination)) {
                    $error = "Could not save the uploaded file. Please try again.";
                    $proof_filename = null;
                }
            }
        }

        if ($error == "") {
            $employee_contribution = $gross_salary * 0.05;
            $employer_contribution = $gross_salary * 0.10;
            $total_contribution = $employee_contribution + $employer_contribution;

            $stmt = $conn->prepare("INSERT INTO contributions
                (employee_id, contribution_month, contribution_year, gross_salary, employee_contribution, employer_contribution, total_contribution, date_paid, status, proof_file)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'paid', ?)");
            $stmt->bind_param("iiidddds", $employee_id, $month, $year, $gross_salary, $employee_contribution, $employer_contribution, $total_contribution, $proof_filename);

            if ($stmt->execute()) {
                $success = "Contribution recorded successfully.";
                $new_contribution_id = $conn->insert_id;
                $stmt2 = $conn->prepare("SELECT first_name, last_name FROM employees WHERE employee_id = ?");
                $stmt2->bind_param("i", $employee_id);
                $stmt2->execute();
                $emp = $stmt2->get_result()->fetch_assoc();
                log_activity($conn, 'added', 'contributions', $new_contribution_id,
                    "Added contribution for " . $emp['first_name'] . " " . $emp['last_name'] . " ($month/$year)");
            } else {
                $error = "Could not save. A record for this employee/month/year may already exist.";
                // Clean up the uploaded file since the database insert failed
                if ($proof_filename && file_exists("uploads/receipts/" . $proof_filename)) {
                    unlink("uploads/receipts/" . $proof_filename);
                }
            }
        }
    }
}

$stmt = $conn->prepare("SELECT employee_id, first_name, last_name FROM employees WHERE employer_id = ?");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$employees = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Contribution</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Employer</span>
        <span><a href="employer_dashboard.php">&larr; Back to Dashboard</a></span>
    </div>

    <div class="page">
        <h2>Add Monthly Contribution</h2>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            <?php if ($success != "") { ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php } ?>

            <form method="POST" action="add_contribution.php" enctype="multipart/form-data">
                <label>Employee</label>
                <select name="employee_id" required>
                    <option value="">-- Select Employee --</option>
                    <?php while ($emp = $employees->fetch_assoc()) { ?>
                        <option value="<?php echo $emp['employee_id']; ?>">
                            <?php echo htmlspecialchars($emp['first_name'] . " " . $emp['last_name']); ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Month</label>
                <select name="month" required>
                    <?php
                    $months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
                    foreach ($months as $index => $name) {
                        $num = $index + 1;
                        echo "<option value='$num'>$name</option>";
                    }
                    ?>
                </select>

                <label>Year</label>
                <input type="number" name="year" value="<?php echo date('Y'); ?>" min="2000" max="2100" required>

                <label>Gross Salary (UGX)</label>
                <input type="number" name="gross_salary" step="0.01" min="0" required>

                <label>Proof of Payment (optional)</label>
                <input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png">
                <p style="font-size:0.78rem; color:#5B6B62; margin-top:4px;">PDF, JPG, or PNG. Max 5MB.</p>

                <button type="submit">Save Contribution</button>
            </form>

            <p style="margin-top:16px; font-size:0.85rem; color:#5B6B62;">
                Employee contribution (5%) and Employer contribution (10%) are calculated automatically.
            </p>
        </div>
    </div>
</body>
</html>