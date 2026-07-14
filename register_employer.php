<?php
include 'db_connect.php';

$error = "";
$success = "";

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
        $existing = $stmt->get_result();

        if ($existing->num_rows > 0) {
            $error = "That username or email is already registered.";
        } else {
            $stmt = $conn->prepare("SELECT employer_id FROM employers WHERE registration_number = ?");
            $stmt->bind_param("s", $registration_number);
            $stmt->execute();
            $existing_reg = $stmt->get_result();

            if ($existing_reg->num_rows > 0) {
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
                    $success = "Registration successful! You can now log in.";
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
    <title>Employer Registration</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Employer Registration</span>
        <span><a href="login.php">&larr; Back to Login</a></span>
    </div>

    <div class="page">
        <h2>Employer Registration</h2>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            <?php if ($success != "") { ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php } ?>

            <form method="POST" action="register_employer.php">
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

                <button type="submit">Register</button>
            </form>
        </div>
    </div>
</body>
</html>