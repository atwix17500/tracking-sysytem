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

$contribution_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT c.*, e.first_name, e.last_name, e.employer_id
                         FROM contributions c
                         JOIN employees e ON c.employee_id = e.employee_id
                         WHERE c.contribution_id = ? AND e.employer_id = ?");
$stmt->bind_param("ii", $contribution_id, $employer_id);
$stmt->execute();
$contribution = $stmt->get_result()->fetch_assoc();

if (!$contribution) {
    die("Contribution not found, or it does not belong to your company.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $month = $_POST['month'];
    $year = $_POST['year'];
    $gross_salary = $_POST['gross_salary'];
    $status = $_POST['status'];

    if ($gross_salary <= 0) {
        $error = "Gross salary must be greater than zero.";
    } else {
        // ---- Handle optional new proof of payment upload (replaces old one if provided) ----
        $proof_filename = $contribution['proof_file']; // keep existing by default
        if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024;

            $file_type = mime_content_type($_FILES['proof_file']['tmp_name']);
            $file_size = $_FILES['proof_file']['size'];

            if (!in_array($file_type, $allowed_types)) {
                $error = "Proof of payment must be a PDF, JPG, or PNG file.";
            } elseif ($file_size > $max_size) {
                $error = "Proof of payment file must be smaller than 5MB.";
            } else {
                $extension = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
                $new_filename = "receipt_" . $employer_id . "_" . $contribution['employee_id'] . "_" . time() . "." . $extension;
                $destination = "uploads/receipts/" . $new_filename;

                if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $destination)) {
                    // Delete the old file, if there was one, now that the new one is saved
                    if ($proof_filename && file_exists("uploads/receipts/" . $proof_filename)) {
                        unlink("uploads/receipts/" . $proof_filename);
                    }
                    $proof_filename = $new_filename;
                } else {
                    $error = "Could not save the uploaded file. Please try again.";
                }
            }
        }

        if ($error == "") {
            $employee_contribution = $gross_salary * 0.05;
            $employer_contribution = $gross_salary * 0.10;
            $total_contribution = $employee_contribution + $employer_contribution;

            $stmt = $conn->prepare("UPDATE contributions
                SET contribution_month = ?, contribution_year = ?, gross_salary = ?,
                    employee_contribution = ?, employer_contribution = ?, total_contribution = ?, status = ?, proof_file = ?
                WHERE contribution_id = ?");
            $stmt->bind_param("iiddddssi", $month, $year, $gross_salary, $employee_contribution, $employer_contribution, $total_contribution, $status, $proof_filename, $contribution_id);

            if ($stmt->execute()) {
                log_activity($conn, 'edited', 'contributions', $contribution_id,
                    "Edited contribution for " . $contribution['first_name'] . " " . $contribution['last_name'] . " (status: " . $status . ")");
                header("Location: employer_dashboard.php?panel=contributions&updated=1");
                exit();
            } else {
                $error = "Could not update. A record for this employee/month/year may already exist.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Contribution</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Employer</span>
        <span><a href="employer_dashboard.php">&larr; Back to Dashboard</a></span>
    </div>

    <div class="page">
        <h2>Edit Contribution</h2>
        <p style="color:#5B6B62;">Employee: <strong><?php echo htmlspecialchars($contribution['first_name'] . " " . $contribution['last_name']); ?></strong></p>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>

            <form method="POST" action="edit_contribution.php?id=<?php echo $contribution_id; ?>" enctype="multipart/form-data">
                <label>Month</label>
                <select name="month" required>
                    <?php
                    $months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
                    foreach ($months as $index => $name) {
                        $num = $index + 1;
                        $selected = ($num == $contribution['contribution_month']) ? "selected" : "";
                        echo "<option value='$num' $selected>$name</option>";
                    }
                    ?>
                </select>

                <label>Year</label>
                <input type="number" name="year" value="<?php echo $contribution['contribution_year']; ?>" min="2000" max="2100" required>

                <label>Gross Salary (UGX)</label>
                <input type="number" name="gross_salary" step="0.01" min="0" value="<?php echo $contribution['gross_salary']; ?>" required>

                <label>Status</label>
                <select name="status" required>
                    <?php
                    $statuses = ['pending', 'paid', 'overdue'];
                    foreach ($statuses as $s) {
                        $selected = ($s == $contribution['status']) ? "selected" : "";
                        echo "<option value='$s' $selected>" . ucfirst($s) . "</option>";
                    }
                    ?>
                </select>

                <label>Proof of Payment</label>
                <?php if ($contribution['proof_file']) { ?>
                    <p style="margin: 6px 0; font-size:0.85rem;">
                        Current file: <a href="uploads/receipts/<?php echo htmlspecialchars($contribution['proof_file']); ?>" target="_blank">View current receipt</a>
                    </p>
                <?php } else { ?>
                    <p style="margin: 6px 0; font-size:0.85rem; color:#5B6B62;">No receipt uploaded yet.</p>
                <?php } ?>
                <input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png">
                <p style="font-size:0.78rem; color:#5B6B62; margin-top:4px;">Uploading a new file will replace the current one. PDF, JPG, or PNG. Max 5MB.</p>

                <button type="submit">Update Contribution</button>
            </form>

            <p style="margin-top:16px; font-size:0.85rem; color:#5B6B62;">
                Employee (5%) and Employer (10%) contributions are recalculated automatically from the gross salary.
            </p>
        </div>
    </div>
</body>
</html>