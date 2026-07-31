<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

$settings = $conn->query("SELECT * FROM email_settings WHERE id = 1")->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $organization_name = trim($_POST['organization_name']);
    $sender_name = trim($_POST['sender_name']);
    $sender_title = trim($_POST['sender_title']);
    $address = trim($_POST['address']);
    $contact_phone = trim($_POST['contact_phone']);
    $fax = trim($_POST['fax']);
    $mobile = trim($_POST['mobile']);
    $website = trim($_POST['website']);
    $contact_email = trim($_POST['contact_email']);
    $values_tagline = trim($_POST['values_tagline']);

    $banner_filename = $settings['banner_filename'];

    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 3 * 1024 * 1024;

        $file_type = mime_content_type($_FILES['banner_image']['tmp_name']);
        $file_size = $_FILES['banner_image']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $error = "Logo must be a JPG or PNG image.";
        } elseif ($file_size > $max_size) {
            $error = "Logo image must be smaller than 3MB.";
        } else {
            $extension = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
            $new_filename = "banner_" . time() . "." . $extension;
            $destination = "uploads/email_banner/" . $new_filename;

            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $destination)) {
                if ($banner_filename && file_exists("uploads/email_banner/" . $banner_filename)) {
                    unlink("uploads/email_banner/" . $banner_filename);
                }
                $banner_filename = $new_filename;
            } else {
                $error = "Could not save the uploaded logo image.";
            }
        }
    }

    if ($error == "") {
        $stmt = $conn->prepare("UPDATE email_settings SET
            banner_filename = ?, organization_name = ?, sender_name = ?, sender_title = ?,
            address = ?, contact_phone = ?, fax = ?, mobile = ?, website = ?, contact_email = ?, values_tagline = ?
            WHERE id = 1");
        $stmt->bind_param("sssssssssss",
            $banner_filename, $organization_name, $sender_name, $sender_title,
            $address, $contact_phone, $fax, $mobile, $website, $contact_email, $values_tagline);

        if ($stmt->execute()) {
            $success = "Email signature settings updated successfully.";
            $settings = $conn->query("SELECT * FROM email_settings WHERE id = 1")->fetch_assoc();
        } else {
            $error = "Could not save settings.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Settings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="topbar">
        <span class="brand">NSSF &middot; Admin</span>
        <span><a href="admin_dashboard.php">&larr; Back to Dashboard</a></span>
    </div>

    <div class="page">
        <h2>Email Signature Settings</h2>
        <p style="color:#5B6B62; font-size:0.9rem;">
            This signature block will automatically be added to the bottom of every email sent to employers.
        </p>

        <div class="card">
            <?php if ($error != "") { ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>
            <?php if ($success != "") { ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php } ?>

            <?php if ($settings['banner_filename']) { ?>
                <p style="font-size:0.85rem; color:#5B6B62;">Current logo:</p>
                <img src="uploads/email_banner/<?php echo htmlspecialchars($settings['banner_filename']); ?>" style="max-width:200px; border-radius:8px; border:1px solid var(--border); margin-bottom:16px;">
            <?php } ?>

            <form method="POST" action="email_settings.php" enctype="multipart/form-data">
                <label>Logo</label>
                <input type="file" name="banner_image" accept=".jpg,.jpeg,.png">
                <p style="font-size:0.78rem; color:#5B6B62; margin-top:4px;">JPG or PNG, max 3MB.</p>

                <label>Sender Name</label>
                <input type="text" name="sender_name" value="<?php echo htmlspecialchars($settings['sender_name']); ?>" placeholder="e.g. Shillar Asiimwe">

                <label>Sender Title</label>
                <input type="text" name="sender_title" value="<?php echo htmlspecialchars($settings['sender_title']); ?>" placeholder="e.g. Human Resource - Talent Specialist">

                <label>Organization Name</label>
                <input type="text" name="organization_name" value="<?php echo htmlspecialchars($settings['organization_name']); ?>">

                <label>Address</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($settings['address']); ?>" placeholder="e.g. 13th Floor Workers House, Kampala - Uganda">

                <label>Phone</label>
                <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">

                <label>Fax</label>
                <input type="text" name="fax" value="<?php echo htmlspecialchars($settings['fax']); ?>">

                <label>Mobile</label>
                <input type="text" name="mobile" value="<?php echo htmlspecialchars($settings['mobile']); ?>">

                <label>Website</label>
                <input type="text" name="website" value="<?php echo htmlspecialchars($settings['website']); ?>">

                <label>Contact Email</label>
                <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>">

                <label>Values Tagline (shown in the bottom bar)</label>
                <input type="text" name="values_tagline" value="<?php echo htmlspecialchars($settings['values_tagline']); ?>" placeholder="e.g. Customer Centric, Innovation, Integrity, Teamwork, and Efficiency">

                <button type="submit">Save Settings</button>
            </form>
        </div>
    </div>
</body>
</html>