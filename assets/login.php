<?php
$headTitle = "Sign in";
include("head.php");

// Check if the admin is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
} else {
    // Get the redirect URL from query parameter or fallback to self
    $redirect_url = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : $_SERVER['PHP_SELF'];

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $user_input = htmlspecialchars(trim($_POST['user_input'])); // Can be username or email
        $password = trim($_POST['password']);
        
        if (empty($user_input) || empty($password)) {
            $error = "Both fields are required.";
        } else {
            // Check if input is an email
            if (filter_var($user_input, FILTER_VALIDATE_EMAIL)) {
                $stmt_login = $db->prepare("SELECT id, username, password_hash, privilege FROM users WHERE email = :user_input");
            } else {
                $stmt_login = $db->prepare("SELECT id, username, password_hash, privilege FROM users WHERE username = :user_input");
            }

            $stmt_login->bindValue(":user_input", $user_input, PDO::PARAM_STR);
            $stmt_login->execute();
            
            $user = $stmt_login->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "User not found.";
            } else {
                $hashedPassword = $user['password_hash'];
                $privilege = $user['privilege'];

                if (password_verify($password, $hashedPassword)) {
                    if ($privilege == "banned") {
                        die("You are banned.");
                    }

                    // Store user data in the session
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['privilege'] = $privilege;
                    $_SESSION['id'] = $user['id'];
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    $to = "alejandrobarrosobueso@gmail.com";
                    $subject = "User Login";
                    $link = "https://qrsume.com/" . urlencode($user['username']);
                    $message = "User login:\n\n$link\n";
                    $headers = "From: qrsume@gmail.com\r\n" .
                               "Reply-To: no-reply@qrsume.com\r\n" .
                               "X-Mailer: PHP/" . phpversion();

                    mail($to, $subject, $message, $headers);

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Incorrect password.";
                }
            }
        }
    }

if(isset($_GET["username"])){
    $success = "Great, your account has been created, now log in to start creating your resume";
    
}
?>
<body class="d-flex flex-column align-items-center justify-content-between" style="min-height:100vh;">
    <?php include("nav2.php"); ?>
        <div class="container-fluid">
        <div class="row justify-content-center">
        <div class="col-md-5 col-12">
            <div >
                <div class="text-center bg-white px-5 pt-5">
                    <h1 style="margin: 0;font-family:'inter';" class=" fw-bold"  >Sign in</h1>
                    <p class="text-muted pt-3">Good to see you again! Welcome back</p>
                </div>
                <div class="card-body px-2">
                    
                    <!-- Error/Success Messages -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger text-center p-2"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success text-center p-2"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="post" action="https://qrsume.com/assets/login.php" onsubmit="return validateForm()">
                        
                        <!-- Username or Email -->
                        <div class="mb-4">
                            <label for="user_input" class="form-label">Username or Email</label>
                            <input type="text" class="form-control py-2"
                                   placeholder="Enter username or email"
                                   name="user_input"
                                   id="user_input"
                                   value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>"
                                   required>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control py-2"
                                   placeholder="Enter password"
                                   id="password"
                                   name="password"
                                   required>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-flex w-100 justify-content-center p-3 mb-2">
                            <button type="submit" class="btn btn-primary w-100 px-4 p-2 fw-bold" 
                            style="
                            background-color:#1e40af;
                            border-radius:32px; font-family:'inter';
                            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4);">
                                Sign in
                            </button>
                        </div>
                        
                    </form>

                    <!-- Register Link -->
                    <div class="text-center text-muted w-100">Don't have an account? 
                    <br>
                        <a href="https://qrsume.com/assets/register.php" class="btn btn-light px-4 p-2 mt-2 mb-3 w-100" style="border-radius:32px; font-family:'inter';">Sign up now</a>
                        <br>
                        Forgot Your Password?
                        <br>
                        <a href="https://qrsume.com/assets/reset_password.php" class="mt-1" >Reset password here</a>
                    </div>
                    
                    
                </div>
            </div>
        </div>
        </div>
        </div>

    <?php include("footer.php"); ?>

    <script>
        function validateForm() {
            let userInput = document.getElementById("user_input").value.trim();
            let password = document.getElementById("password").value.trim();

            if (userInput === "" || password === "") {
                alert("Both fields are required.");
                return false;
            }

            return true;
        }
    </script>
</body>
</html>
<?php } ?>
