<?php
session_start();
include 'db_connect.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($user['role'] == 'employer') {
                header("Location: employer_dashboard.php");
            } else {
                header("Location: employee_dashboard.php");
            }
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "No account found with that username.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>NSSF Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <h2>NSSF Contributions</h2>
            <p class="login-sub">Sign in to your account</p>

            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>

            <form method="POST" action="login.php">
                <label>Username</label>
                <input type="text" name="username" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <button type="submit">Login</button>
            </form>

            <p style="text-align:center; margin-top:18px; font-size:0.85rem;">
                Don't have an account? <a href="register_employer.php">Register as an Employer</a>
            </p>
        </div>
    </div>
</body>
</html>