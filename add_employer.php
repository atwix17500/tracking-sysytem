<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $company_name = trim($_POST['company_name']);
    $registration_number = trim($_POST['registration_number']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);

    if ($username == "" || $password == "" || $email == "" || $company_name == "" || $registration_number == "") {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "That username or email is already registered.";
        } else {
            $stmt = $conn->prepare("SELECT employer_id FROM employers WHERE registration_number = ?");
            $stmt->bind_param("s", $registration_number);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "That company registration number is already registered.";
            } else {
                $conn->begin_transaction();
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'employer')");
                    $stmt->bind_param("sss", $username, $hashed_password, $email);
                    $stmt->execute();
                    $new_user_id = $conn->insert_id;

                    $stmt = $conn->prepare("INSERT INTO employers (user_id, company_name, registration_number, address, phone) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $new_user_id, $company_name, $registration_number, $address, $phone);
                    $stmt->execute();

                    $conn->commit();
                    log_activity($conn, 'added', 'employers', $conn->insert_id,
                        "Added employer $company_name (Reg: $registration_number)");
                    header("Location: admin_dashboard.php?panel=employers&added=1");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Employer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Admin</span>
        <span><a href="admin_dashboard.php?panel=employers">&larr; Back to Employers</a></span>
    </div>

    <div class="page">
        <h2>Add New Employer</h2>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>

            <form method="POST" action="add_employer.php">
                <h3 style="margin-top:0;">Login Details</h3>
                <label>Username</label>
                <input type="text" name="username" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <label>Email</label>
                <input type="email" name="email" required>

                <h3>Company Details</h3>
                <label>Company Name</label>
                <input type="text" name="company_name" required>

                <label>Registration Number</label>
                <input type="text" name="registration_number" required>

                <label>Address</label>
                <input type="text" name="address">

                <label>Phone</label>
                <input type="text" name="phone">

                <button type="submit">Add Employer</button>
            </form>
        </div>
    </div>
</body>
</html>