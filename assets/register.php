<?php
$headTitle = "Sign up";
include("head.php");

$error = "";
$success = "";
$showVerification = false;

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        $username = htmlspecialchars(trim(strtolower($_POST['username'])));
        $email = strtolower(filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL));
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        $stmt = $db->prepare("SELECT username, email FROM users WHERE username = :username OR email = :email");
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif (strlen($username) < 4 || strlen($username) > 25) {
            $error = "Username must be between 4 and 25 characters.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif ($user) {
            if ($user['username'] === $username) {
                $error = "Error: Username already exists.";
            } elseif ($user['email'] === $email) {
                $error = "Error: Email already exists.";
            }
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $verificationCode = rand(100000, 999999);

            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['hashedPassword'] = $hashedPassword;
            $_SESSION['verificationCode'] = $verificationCode;

            $subject = "Your Verification Code";
            $message = "Hello $username,\n\nYour verification code is: $verificationCode\n\nEnter this code to verify your account.";
            $headers = "From: no-reply@qrsume.com\r\n";
            $headers .= "Reply-To: no-reply@qrsume.com\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            if (mail($email, $subject, $message, $headers)) {
                $showVerification = true;
            } else {
                $error = "Could not send verification email.";
            }
        }
    } elseif (isset($_POST['verify'])) {
        $userCode = trim($_POST['verification_code']);
        $username = $_SESSION['username'] ?? 'Someone';

        if ($userCode == $_SESSION['verificationCode']) {
            $to = "alejandrobarrosobueso@gmail.com, ppdepablolopezk9@gmail.com";
            $subject = "🎉 ¡Un nuevo usuario se ha unido a QRSume! 🚀";
            $message = "
<html>
<head>
  <title>📢 Noticias frescas... al menos para los que trabajamos!!!</title>
</head>
<body style='font-family: Arial, sans-serif; color: #333;'>
  <h2 style='color: #E91E63;'>¡Vaya, otro registro más en QRSume! 💥</h2>
  <p>" . $username . " se ha unido mientras algunos <em>duermen la siesta</em> o se pierden viendo vídeos de tiroteos, alguien acaba de unirse a <strong>QRSume</strong>, nuestra plataforma que (al menos *yo*) sigo empujando hacia adelante 📈.</p>
  <p>Esto no es magia... es trabajo. Trabajo real. Ya sabes, ese concepto abstracto que algunos mencionan pero que otros (cof cof, yo) realmente hacemos 💻🧠.</p>
  <p>Pero oye, sin rencores. Solo quería que supieras que mientras tú piensas en ideas... yo las ejecuto 😎.</p>
  <hr>
  <p style='font-size: 14px; color: #888;'>Este mensaje fue enviado automáticamente por alguien que claramente trabaja más que tú 🤖</p>
</body>
</html>
";
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: QRSume <no-reply@qrsume.com>\r\n";

            mail($to, $subject, $message, $headers);

            try {
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, is_verified) VALUES (:username, :email, :password, 1)");
                $stmt->bindParam(":username", $_SESSION['username']);
                $stmt->bindParam(":email", $_SESSION['email']);
                $stmt->bindParam(":password", $_SESSION['hashedPassword']);
                if ($stmt->execute()) {
                    $user_id = $db->lastInsertId();

                    $cv_url = 'https://qrsume.com/pdf2.php?username=' . $_SESSION['username'];

                    $stmt = $db->prepare("INSERT INTO personalinfo (user_id, personal_name, personal_lastname, personal_profession, personal_bio, cv_url) 
                        VALUES (:user_id, 'Your Name', 'Your Last Name', 'Your Profession', 'This is a short bio about yourself.', :cv_url)");
                    $stmt->bindParam(":user_id", $user_id);
                    $stmt->bindParam(":cv_url", $cv_url);
                    $stmt->execute();

                    $stmt = $db->prepare("INSERT INTO contactinfo (user_id, phone_number, email, github, linkedin, twitter) 
                        VALUES (:user_id, '123-456-7890', 'youremail@gmail.com', 'https://github.com/yourusername', 
                        'https://linkedin.com/in/yourusername', 'https://twitter.com/yourusername')");
                    $stmt->bindParam(":user_id", $user_id);
                    $stmt->execute();

                    $stmt = $db->prepare("INSERT INTO education (user_id, date, place_of_study, name_of_studies, brief_description) 
                        VALUES (:user_id, '2018 - 2022', 'Your University, Your City', 'Your Degree', 
                        'Studied various subjects related to your degree and participated in extracurricular activities.')");
                    $stmt->bindParam(":user_id", $user_id);
                    $stmt->execute();

                    $stmt = $db->prepare("INSERT INTO experience (user_id, date, place_of_work, job_name, brief_description) 
                        VALUES (:user_id, '2022 - Present', 'Your Company, Your City', 'Your Job Title', 
                        'Worked on various projects, improving skills in different areas.')");
                    $stmt->bindParam(":user_id", $user_id);
                    $stmt->execute();

                    $languages = [
                        ['English', 'Fluent'],
                        ['Spanish', 'Intermediate'],
                        ['French', 'Basic']
                    ];
                    $stmt = $db->prepare("INSERT INTO languages (user_id, language, level) VALUES (:user_id, :language, :level)");
                    foreach ($languages as $lang) {
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':language' => $lang[0],
                            ':level' => $lang[1]
                        ]);
                    }

                    $aptitudes = ['Teamwork', 'Communication', 'Problem Solving', 'Leadership'];
                    $stmt = $db->prepare("INSERT INTO aptitudes (user_id, aptitude) VALUES (:user_id, :aptitude)");
                    foreach ($aptitudes as $aptitude) {
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':aptitude' => $aptitude
                        ]);
                    }

                    $interests = [
                        ['Technology', 'Passionate about the latest tech trends.'],
                        ['Music', 'Enjoys playing instruments and composing songs.'],
                        ['Traveling', 'Loves exploring new cultures and destinations.']
                    ];
                    $stmt = $db->prepare("INSERT INTO interests (user_id, interest, description) VALUES (:user_id, :interest, :description)");
                    foreach ($interests as $interest) {
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':interest' => $interest[0],
                            ':description' => $interest[1]
                        ]);
                    }

                    $stmt = $db->prepare("SELECT id, privilege FROM users WHERE username = :username");
                    $stmt->bindParam(":username", $_SESSION['username']);
                    $stmt->execute();
                    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($userRow) {
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['username'] = $_SESSION['username'];
                        $_SESSION['privilege'] = $userRow['privilege'];
                        $_SESSION['id'] = $userRow['id'];

                        header("Location: https://qrsume.com/create_resume/form_with_login.php");
                        exit();
                    } else {
                        $error = "Unexpected error: could not retrieve your user ID after registration.";
                    }



                } else {
                    $error = "Something went wrong. Please try again.";
                }
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = "Incorrect verification code.";
            $showVerification = true;
        }
    }
}

$username = $_POST['username'] ?? "";
$email = $_POST['email'] ?? "";
?>

<body class="d-flex flex-column align-items-center justify-content-between" style="min-height:100vh;">
<?php include("nav_site.php");?>
 <div class="container-fluid">
        <div class="row justify-content-center">
        <div class="col-md-5 col-12">
            <div >
        <div class="text-center bg-white px-5 pt-5">
            <h1 style="margin: 0;font-family:'inter';" class="fw-bold">Sign up</h1>
            <p class="text-muted pt-3">Let us get to know you better</p>
        </div>
        <div class="card-body px-2">
            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger text-center p-2" style="font-size: 13px;"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success text-center p-2" style="font-size: 13px;"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Verification Form -->
            <?php if ($showVerification): ?>
                <form method="POST" action="https://qrsume.com/assets/register.php">
                    <p class="text-center" style="font-size: 13px;">A verification code has been sent to <b><?php echo htmlspecialchars($_SESSION['email']); ?></b></p>
                    <div class="mb-3">
                        <label for="verification_code" class="form-label" style="font-weight: 600; font-size: 13px;">Verification Code</label>
                        <input type="text" name="verification_code" id="verification_code" class="form-control"
                               required
                               style="border: 1px solid #ccc; border-radius: 6px; padding: 7px; font-size: 13px;">
                    </div>
                    <button type="submit" name="verify" class="btn btn-success w-100"
                            style="font-size: 14px; font-weight: bold;">
                        Verify
                    </button>
                </form>
            <?php else: ?>
                <!-- Registration Form -->
                <form id="register_form" method="POST" action="https://qrsume.com/assets/register.php" onsubmit="return validateForm()">
                    <input type="hidden" name="register" value="1">

                    <div class="mb-2">
                        <label for="username" class="form-label">Username<span class="text-muted" style="font-size:12px;"> (Your URL: qrsume.com/username)</span></label>
                        <input type="text" name="username" id="username" class="form-control py-2"
                               value="<?php echo htmlspecialchars($username); ?>"
                               required placeholder="Enter username" maxlength="25">
                        <small id="usernameError" class="text-danger m-0 p-0" style="display:none; font-size: 12px;">
                            Username must be 4-25 characters (letters, numbers, '_', or '.')
                        </small>
                    </div>

                    <div class="mb-2">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control py-2"
                               value="<?php echo htmlspecialchars($email); ?>"
                               required placeholder="example@example.com">
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control py-2" required>
                    </div>

                    <div class="mb-1">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control py-2" required>
                        <small id="passwordError" class="text-danger" style="visibility:hidden; font-size: 11px;">
                            Passwords do not match.
                        </small>
                    </div>
                    
                    <div class="mb-2 form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="privacyConsent" name="privacyConsent" required>
                        <label class="form-check-label" for="privacyConsent" style="font-size: 13px;">
                            I have read and accept the 
                            <a href="https://qrsume.com/page.php?web=termsandconditions" target="_blank">Terms and Conditions</a>.
                        </label>
                    </div>
                    

                    <div class="d-flex w-100 justify-content-center mb-2">
                        <button type="submit" class="btn btn-primary w-100 px-4 p-2 fw-bold"
                                style="background-color:#1e40af; border-radius:32px; font-family:'inter'; box-shadow: 0 4px 12px #1e40af;">
                            Sign up
                        </button>
                    </div>
                </form>

            <div class="text-center text-muted mt-2">Already have an account?<br>
                <a href="https://qrsume.com/assets/login.php" class="btn btn-light px-4 p-2 mt-2 mb-3 w-100"
                   style="border-radius:32px; font-family:'inter';">Sign in now</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
    </div>
</div>
<?php include("footer.php") ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    let usernameInput = document.getElementById("username");
    let registerForm = document.getElementById("register_form");
    let passwordInput = document.getElementById("password");
    let confirmPasswordInput = document.getElementById("confirm_password");

    let usernameError = document.getElementById("usernameError");
    let passwordError = document.getElementById("passwordError");

    usernameInput.addEventListener("input", function () {
        this.value = this.value.replace(/[^a-zA-Z0-9_.]/g, '');
    });

    function validatePasswords() {
        if (passwordInput.value !== confirmPasswordInput.value) {
            passwordError.style.visibility = "visible";
        } else {
            passwordError.style.visibility = "hidden";
        }
    }

    passwordInput.addEventListener("input", validatePasswords);
    confirmPasswordInput.addEventListener("input", validatePasswords);
    confirmPasswordInput.addEventListener("blur", validatePasswords);
});

function validateForm() {
    let username = document.getElementById("username").value.trim();
    let password = document.getElementById("password").value;
    let confirmPassword = document.getElementById("confirm_password").value;

    let usernameRegex = /^[a-zA-Z0-9_.]{4,25}$/;
    let usernameError = document.getElementById("usernameError");
    let passwordError = document.getElementById("passwordError");
    let valid = true;
    let consent = document.getElementById("privacyConsent").checked;
    if (!consent) {
        alert("You must accept the Terms and Conditions to register.");
        return false;
    }

    if (!usernameRegex.test(username)) {
        usernameError.style.display = "block";
        valid = false;
    } else {
        usernameError.style.display = "none";
    }

    if (password !== confirmPassword) {
        passwordError.style.visibility = "visible";
        valid = false;
    } else {
        passwordError.style.visibility = "hidden";
    }

    return valid;
}

document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('focus', function() {
        this.style.borderColor = '#007bff';
    });
    input.addEventListener('blur', function() {
        this.style.borderColor = '#ccc';
    });
});
</script>
</body>
</html>
