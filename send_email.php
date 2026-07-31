<?php
session_start();
include 'db_connect.php';
include 'mail_config.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$employer_id = isset($_GET['employer_id']) ? (int)$_GET['employer_id'] : (isset($_POST['employer_id']) ? (int)$_POST['employer_id'] : 0);

// Pull the employer's REAL stored email straight from the database - never typed by admin
$stmt = $conn->prepare("SELECT emp.*, u.email FROM employers emp JOIN users u ON emp.user_id = u.user_id WHERE emp.employer_id = ?");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

if (!$employer) {
    die("Employer not found.");
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if ($subject == "" || $message == "") {
        $error = "Please fill in both subject and message.";
    } else {
        // Pull the banner/footer settings admin configured on the Email Settings page
        $settings = $conn->query("SELECT * FROM email_settings WHERE id = 1")->fetch_assoc();

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
            $mail->addAddress($employer['email'], $employer['company_name']); // recipient pulled from DB, not typed

            // ---- Build the signature block: logo left, stacked details right, values bar at bottom ----
            $logo_html = "";
            if (!empty($settings['banner_filename'])) {
                $logo_path = "uploads/email_banner/" . $settings['banner_filename'];
                if (file_exists($logo_path)) {
                    $mail->addEmbeddedImage($logo_path, 'logoimg');
                    $logo_html = "<img src='cid:logoimg' style='max-width:120px; display:block;' alt='" . htmlspecialchars($settings['organization_name']) . "'>";
                }
            }

            $name_title_line = trim(($settings['sender_name'] ?? '') . ($settings['sender_title'] ? " | " . $settings['sender_title'] : ''));

            $signature_html = "
                <table cellpadding='0' cellspacing='0' style='width:100%; border-top:3px solid #0F4C81; margin-top:24px;'>
                    <tr>
                        <td style='padding:16px 0;'>
                            <table cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td style='padding-right:16px; vertical-align:top;'>$logo_html</td>
                                    <td style='vertical-align:top; font-family:Arial, sans-serif; font-size:13px; color:#1E2A24; line-height:1.6;'>
                                        " . ($name_title_line ? "<p style='margin:0; font-weight:bold;'>" . htmlspecialchars($name_title_line) . "</p>" : "") . "
                                        " . (!empty($settings['address']) ? "<p style='margin:0;'>" . htmlspecialchars($settings['address']) . "</p>" : "") . "
                                        " . (!empty($settings['contact_phone']) ? "<p style='margin:0;'>" . htmlspecialchars($settings['contact_phone']) . "</p>" : "") . "
                                        " . (!empty($settings['website']) ? "<p style='margin:0;'>" . htmlspecialchars($settings['website']) . "</p>" : "") . "
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    " . (!empty($settings['values_tagline']) ? "
                    <tr>
                        <td style='background:#0F4C81; color:#fff; padding:10px 14px; font-family:Arial, sans-serif; font-size:13px; font-weight:bold;'>
                            VALUES: " . htmlspecialchars($settings['values_tagline']) . "
                        </td>
                    </tr>" : "") . "
                </table>
            ";

            $message_html = nl2br(htmlspecialchars($message));

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width:600px; margin:0 auto;'>
                    <div style='padding:0 8px; color:#1E2A24; font-size:15px; line-height:1.6;'>
                        $message_html
                    </div>
                    $signature_html
                </div>
            ";
            $mail->AltBody = $message; // plain-text fallback for email clients that don't render HTML

            $mail->send();

            log_activity($conn, 'edited', 'employers', $employer_id,
                "Sent email to " . $employer['company_name'] . " (" . $employer['email'] . ") - Subject: $subject");

            $success = "Email sent successfully to " . $employer['email'];
        } catch (Exception $e) {
            $error = "Email could not be sent. Mailer Error: " . $mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Employer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Admin</span>
        <span><a href="admin_dashboard.php?panel=employers">&larr; Back to Employers</a></span>
    </div>

    <div class="page">
        <h2>Send Email</h2>
        <p style="color:#5B6B62;">
            To: <strong><?php echo htmlspecialchars($employer['company_name']); ?></strong>
            &lt;<?php echo htmlspecialchars($employer['email']); ?>&gt;
        </p>
        <p style="font-size:0.8rem; color:#5B6B62;">This address comes directly from the employer's account on file and cannot be changed here.</p>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            <?php if ($success != "") { ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php } ?>

            <form method="POST" action="send_email.php?employer_id=<?php echo $employer_id; ?>">
                <input type="hidden" name="employer_id" value="<?php echo $employer_id; ?>">

                <label>Subject</label>
                <input type="text" name="subject" required>

                <label>Message</label>
                <textarea name="message" rows="8" style="width:100%; padding:9px 10px; margin-top:6px; border:1px solid var(--border); border-radius:6px; font-family:inherit; font-size:0.95rem;" required></textarea>

                <button type="submit">Send Email</button>
            </form>
        </div>
    </div>
</body>
</html>