<?php
include('head.php');

$error = $success = "";

// Step 1: User enters username or email
if (isset($_POST['request_verification'])) {
    $identifier = trim($_POST['identifier']);

    // Check if user exists
    $stmt = $db->prepare("SELECT id, email FROM users WHERE email = :identifier");
    $stmt->bindParam(":identifier", $identifier);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['reset_email'] = $user['email'];
        $_SESSION['reset_verification_code'] = rand(100000, 999999);

        // Send verification code
        $subject = "Password Reset Verification Code";
        $message = "Your verification code is: " . $_SESSION['reset_verification_code'];
        $headers = "From: no-reply@qrsume.com\r\n";

        if (mail($_SESSION['reset_email'], $subject, $message, $headers)) {
            $_SESSION['reset_pending'] = true;
            $success = "A verification code has been sent to your email.";
        } else {
            $error = "Failed to send verification email.";
        }
    } else {
        $error = "No account found with that email.";
    }
}

// Step 2: User enters verification code and new password
if (isset($_POST['reset_password'])) {
    if ($_POST['verification_code'] == $_SESSION['reset_verification_code']) {
        $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE users SET password_hash = :password WHERE id = :id");
        $stmt->bindParam(":password", $hashedPassword);
        $stmt->bindParam(":id", $_SESSION['reset_user_id']);

        if ($stmt->execute()) {
            $success = "Password updated successfully! You can now log in.";
            unset($_SESSION['reset_pending'], $_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_verification_code']);
        } else {
            $error = "Error updating password.";
        }
    } else {
        $error = "Incorrect verification code.";
    }
}
?>

<!-- Bootstrap UI -->
<body>
<?php include("nav_profile.php"); ?>
<div class="container mt-4">
    <?php if ($success): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Step 1: Request Verification Code -->
    <?php if (!isset($_SESSION['reset_pending'])): ?>
        <div class="card mt-4">
            <div class="card-header"><h2 class="mb-0">Reset Password</h2></div>
            <div class="card-body">
                <form method="POST">
                    <label><strong>Enter Email:</strong></label>
                    <input type="text" name="identifier" class="form-control" required>
                    <button type="submit" name="request_verification" class="btn btn-primary mt-3">Send Verification Code</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Step 2: Verify Code & Reset Password -->
    <?php if (isset($_SESSION['reset_pending'])): ?>
        <div class="card mt-4">
            <div class="card-header"><h2 class="mb-0">Enter Verification Code & New Password</h2></div>
            <div class="card-body">
                <form method="POST">
                    <label><strong>Enter Verification Code:</strong></label>
                    <input type="text" name="verification_code" class="form-control" required>
                    <label><strong>New Password:</strong></label>
                    <input type="password" name="new_password" class="form-control" required>
                    <button type="submit" name="reset_password" class="btn btn-success mt-3">Reset Password</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include("footer.php"); ?>
</body>
