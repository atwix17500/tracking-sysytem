<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$employer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM employers WHERE employer_id = ?");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

if (!$employer) {
    die("Employer not found.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $company_name = trim($_POST['company_name']);
    $registration_number = trim($_POST['registration_number']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);

    if ($company_name == "" || $registration_number == "") {
        $error = "Company name and registration number are required.";
    } else {
        // Make sure the registration number isn't taken by a DIFFERENT employer
        $stmt = $conn->prepare("SELECT employer_id FROM employers WHERE registration_number = ? AND employer_id != ?");
        $stmt->bind_param("si", $registration_number, $employer_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "That registration number is already used by another employer.";
        } else {
            $stmt = $conn->prepare("UPDATE employers SET company_name = ?, registration_number = ?, address = ?, phone = ? WHERE employer_id = ?");
            $stmt->bind_param("ssssi", $company_name, $registration_number, $address, $phone, $employer_id);

            if ($stmt->execute()) {
                log_activity($conn, 'edited', 'employers', $employer_id, "Edited employer $company_name");
                header("Location: admin_dashboard.php?panel=employers&updated=1");
                exit();
            } else {
                $error = "Could not update employer.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Employer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Admin</span>
        <span><a href="admin_dashboard.php?panel=employers">&larr; Back to Employers</a></span>
    </div>

    <div class="page">
        <h2>Edit Employer</h2>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>

            <form method="POST" action="edit_employer.php?id=<?php echo $employer_id; ?>">
                <label>Company Name</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($employer['company_name']); ?>" required>

                <label>Registration Number</label>
                <input type="text" name="registration_number" value="<?php echo htmlspecialchars($employer['registration_number']); ?>" required>

                <label>Address</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($employer['address']); ?>">

                <label>Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($employer['phone']); ?>">

                <button type="submit">Update Employer</button>
            </form>

            <p style="margin-top:16px; font-size:0.85rem; color:#5B6B62;">
                Note: login credentials (username/password) are not editable here.
            </p>
        </div>
    </div>
</body>
</html>