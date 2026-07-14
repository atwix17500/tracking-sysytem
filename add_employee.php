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
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $nssf_number = trim($_POST['nssf_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $national_id = trim($_POST['national_id']);
    $phone = trim($_POST['phone']);
    $date_joined = $_POST['date_joined'];

    if ($username == "" || $password == "" || $email == "" || $nssf_number == "" || $first_name == "" || $last_name == "" || $national_id == "" || $date_joined == "") {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "That username or email is already registered.";
        } else {
            $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE nssf_number = ? OR national_id = ?");
            $stmt->bind_param("ss", $nssf_number, $national_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "That NSSF number or National ID is already registered.";
            } else {
                $conn->begin_transaction();
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'employee')");
                    $stmt->bind_param("sss", $username, $hashed_password, $email);
                    $stmt->execute();
                    $new_user_id = $conn->insert_id;

                    $stmt = $conn->prepare("INSERT INTO employees (user_id, employer_id, nssf_number, first_name, last_name, national_id, phone, date_joined) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissssss", $new_user_id, $employer_id, $nssf_number, $first_name, $last_name, $national_id, $phone, $date_joined);
                    $stmt->execute();

                    $conn->commit();
                    log_activity($conn, 'added', 'employees', $conn->insert_id,
                        "Added employee " . $first_name . " " . $last_name . " (NSSF: $nssf_number)");
                    $success = "Employee added successfully.";
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
    <title>Add Employee</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Employer</span>
        <span><a href="employer_dashboard.php">&larr; Back to Dashboard</a></span>
    </div>

    <div class="page">
        <h2>Add New Employee</h2>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            <?php if ($success != "") { ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php } ?>

            <form method="POST" action="add_employee.php">
                <h3 style="margin-top:0;">Login Details</h3>
                <label>Username</label>
                <input type="text" name="username" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <label>Email</label>
                <input type="email" name="email" required>

                <h3>Employee Details</h3>
                <label>NSSF Number</label>
                <input type="text" name="nssf_number" required>

                <label>First Name</label>
                <input type="text" name="first_name" required>

                <label>Last Name</label>
                <input type="text" name="last_name" required>

                <label>National ID</label>
                <input type="text" name="national_id" required>

                <label>Phone</label>
                <input type="text" name="phone">

                <label>Date Joined</label>
                <input type="date" name="date_joined" required>

                <button type="submit">Add Employee</button>
            </form>
        </div>
    </div>
</body>
</html>