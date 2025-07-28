<?php
$headTitle = "Settings";
include('head.php');

if (!isset($_SESSION['id'])) {
     header("Location: https://qrsume.com/error.php");
    exit();
}

$userId = $_SESSION['id'];

// Fetch user info
$stmt = $db->prepare("SELECT username, email, password_hash, last_username_change FROM users WHERE id = :id");
$stmt->bindParam(":id", $userId, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Profile Updates
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_username'])) {
        $newUsername = trim($_POST['username']);
        $lastChange = strtotime($user['last_username_change']);
        $now = time();
        $daysSinceChange = ($now - $lastChange) / (60 * 60 * 24);

        // Check if username already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :id");
        $stmt->bindParam(":username", $newUsername);
        $stmt->bindParam(":id", $userId);
        $stmt->execute();
        $usernameExists = $stmt->fetchColumn();

        if ($usernameExists) {
            $error = "Username already taken. Please choose another.";
        } elseif ($daysSinceChange < 15) {
            $error = "You can only change your username every 15 days.";
        } else {
            echo $newUsername;
            $stmt = $db->prepare("UPDATE users SET username = :username, last_username_change = NOW() WHERE id = :id");
            $stmt->bindParam(":username", $newUsername);
            $stmt->bindParam(":id", $userId);
            $_SESSION["username"] = $newUsername;
            if ($stmt->execute()) {
                $success = "Username updated successfully!";
            } else {
                $error = "Error updating username.";
            }
        }
    } elseif (isset($_POST['request_email_verification'])) {
        $newEmail = trim($_POST['email']);

        // Check if the email is already in use
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(":email", $newEmail);
        $stmt->execute();
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // If the email is the same as the current one, do nothing
        if ($newEmail === $user['email']) {
            $error = "This is already your current email.";
        } 
        // If the email exists for another user, show an error
        elseif ($existingUser) {
            $error = "This email is already in use. Please choose a different one.";
        } 
        // If it's a new email, proceed with verification
        else {
            $_SESSION['new_email'] = $newEmail;
            $_SESSION['verification_code'] = rand(100000, 999999);
    
            $subject = "Email Verification Code";
            $message = "Your verification code is: " . $_SESSION['verification_code'];
            $headers = "From: no-reply@qrsume.com\r\n";
    
            if (mail($_SESSION['new_email'], $subject, $message, $headers)) {
                $_SESSION['email_verification_pending'] = true;
            } else {
                $error = "Failed to send verification email.";
            }
        }
        
        
        
        
        
    } elseif (isset($_POST['verify_email'])) {
        if ($_POST['verification_code'] == $_SESSION['verification_code']) {
            $stmt = $db->prepare("UPDATE users SET email = :email WHERE id = :id");
            $stmt->bindParam(":email", $_SESSION['new_email']);
            $stmt->bindParam(":id", $userId);
            if ($stmt->execute()) {
                $success = "Email updated successfully!";
                unset($_SESSION['email_verification_pending'], $_SESSION['new_email'], $_SESSION['verification_code']);
            } else {
                $error = "Error updating email.";
            }
        } else {
            $error = "Incorrect verification code.";
        }
    } elseif (isset($_POST['request_password_verification'])) {
        $_SESSION['verification_code'] = rand(100000, 999999);

        $subject = "Password Reset Verification Code";
        $message = "Your verification code is: " . $_SESSION['verification_code'];
        $headers = "From: no-reply@qrsume.com\r\n";

        if (mail($user['email'], $subject, $message, $headers)) {
            $_SESSION['password_verification_pending'] = true;
        } else {
            $error = "Failed to send verification email.";
        }
    } elseif (isset($_POST['verify_password'])) {
        if ($_POST['verification_code'] == $_SESSION['verification_code']) {
            $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = :password WHERE id = :id");
            $stmt->bindParam(":password", $hashedPassword);
            $stmt->bindParam(":id", $userId);
            if ($stmt->execute()) {
                $success = "Password updated successfully!";
                unset($_SESSION['password_verification_pending'], $_SESSION['verification_code']);
            } else {
                $error = "Error updating password.";
            }
        } else {
            $error = "Incorrect verification code.";
        }
    }
}
?>

<!-- Bootstrap UI -->
<body>
<?php include("nav.php"); ?>
<div class="container mt-7">
    <?php if (isset($success)): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Username Update -->
    <div class="card mt-4">
        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#usernameCollapse" style="cursor:pointer;">
            <h2 class="mb-0">Change Username</h2>
        </div>
        <div id="usernameCollapse" class="collapse show">
            <div class="card-body">
                <form method="POST" action="https:/qrsume.com/assets/user_settings.php">
                    <label><strong>New Username:</strong></label><br>
                    <small class="form-text text-muted">You can only change your username every 15 days.</small>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                    <button type="submit" name="update_username" class="btn btn-primary mt-3">Update</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Email Update -->
    <div class="card mt-4">
        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#emailCollapse" style="cursor:pointer;">
            <h2 class="mb-0">Change Email</h2>
        </div>
        <div id="emailCollapse" class="collapse show">
            <div class="card-body">
                <form method="POST" action="https:/qrsume.com/assets/user_settings.php">
                    <label><strong>Email:</strong></label></br>
                    <small class="form-text text-muted">This is the email used for your account registration and login. It is not the email displayed on your resume or website.</small>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    <button type="submit" name="request_email_verification" class="btn btn-primary mt-3">Change email</button>
                </form>

                <?php if (isset($_SESSION['email_verification_pending'])): ?>
                    <form method="POST" action="https:/qrsume.com/assets/user_settings.php" class="mt-3">
                        <label><strong>Enter Verification Code:</strong></label>
                        <input type="text" name="verification_code" class="form-control" required>
                        <button type="submit" name="verify_email" class="btn btn-success mt-3">Confirm</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Password Update -->
    <div class="card my-4">
        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#passwordCollapse" style="cursor:pointer;">
            <h2 class="mb-0">Change Password</h2>
        </div>
        <div id="passwordCollapse" class="collapse show">
            <div class="card-body">
                <form method="POST" action="https:/qrsume.com/assets/user_settings.php">
                    <button type="submit" name="request_password_verification" class="btn btn-primary">Send Verification Code</button>
                </form>

                <?php if (isset($_SESSION['password_verification_pending'])): ?>
                    <form method="POST" action="https:/qrsume.com/assets/user_settings.php" class="mt-3">
                        <label><strong>Enter Verification Code:</strong></label>
                        <input type="text" name="verification_code" class="form-control" required>
                        <label><strong>New Password:</strong></label>
                        <input type="password" name="new_password" class="form-control" required>
                        <button type="submit" name="verify_password" class="btn btn-success mt-3">Update Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <a class="btn btn-danger d-flex align-items-center px-3 mt-2 w-25 w-md-100" href="../assets/logout.php">
  <i class="bi bi-box-arrow-right me-2"></i> Logout
</a>

</div>
<?php
include("footer.php");?>
</body>