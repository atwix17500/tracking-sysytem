<?php
include 'db_connect.php';
include 'mail_config.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if ($email == "") {
        $error = "Please enter your email address.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        // Always show the same success message whether or not the email exists.
        // This prevents someone from using this form to check which emails are registered.
        $success = "If an account exists for that email, a password reset link has been sent.";

        if ($user) {
            $token = bin2hex(random_bytes(32)); // long, unguessable token
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $token, $expires, $user['user_id']);
            $stmt->execute();

            $reset_link = "http://localhost/nssf/reset_password.php?token=" . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;

                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($user['email'], $user['username']);

                $mail->isHTML(false);
                $mail->Subject = "Password Reset - NSSF Contributions System";
                $mail->Body = "Hello " . $user['username'] . ",\n\n"
                    . "We received a request to reset your password. Click the link below to set a new password:\n\n"
                    . $reset_link . "\n\n"
                    . "This link will expire in 1 hour. If you did not request this, you can safely ignore this email.\n\n"
                    . "- NSSF Contributions Tracking System";

                $mail->send();
            } catch (Exception $e) {
                // Don't reveal mail errors to the person requesting the reset (avoids leaking account info)
                // but log it for the developer to see.
                error_log("Password reset email failed: " . $mail->ErrorInfo);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <h2>Forgot Password</h2>
            <p class="login-sub">Enter your email and we'll send you a reset link</p>

            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            <?php if ($success != "") { ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php } ?>

            <form method="POST" action="forgot_password.php">
                <label>Email</label>
                <input type="email" name="email" required>

                <button type="submit">Send Reset Link</button>
            </form>

            <p style="text-align:center; margin-top:18px; font-size:0.85rem;">
                <a href="login.php">&larr; Back to Login</a>
            </p>
        </div>
    </div>
</body>
</html>