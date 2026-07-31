<?php
include 'db_connect.php';

$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');
$error = "";
$success = "";

// Look up a user with this exact token, that hasn't expired yet
$stmt = $conn->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $error = "This password reset link is invalid or has expired. Please request a new one.";
}

if ($user && $_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password == "" || $confirm_password == "") {
        $error = "Please fill in both password fields.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Set the new password AND clear the token, so this link can't be reused
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user['user_id']);
        $stmt->execute();

        $success = "Your password has been reset successfully. You can now log in.";
        $user = null; // hide the form now that it's done
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <h2>Reset Password</h2>
            <p class="login-sub">Choose a new password for your account</p>

            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            <?php if ($success != "") { ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php } ?>

            <?php if ($user) { ?>
                <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <label>New Password</label>
                    <input type="password" name="password" required>

                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>

                    <button type="submit">Reset Password</button>
                </form>
            <?php } ?>

            <p style="text-align:center; margin-top:18px; font-size:0.85rem;">
                <a href="login.php">&larr; Back to Login</a>
            </p>
        </div>
    </div>
</body>
</html>