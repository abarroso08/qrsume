<?php
$subject = "Your Verification Code";
    $message = "Your verification code is: 1234";
    $headers = "From: no-reply@alejandrobarroso.com";

    if (mail("alejandrobarrosobueso@gmail.com", $subject, $message, $headers)) {
        echo "A verification code has been sent to your email. Please check your inbox.";
        header("Location: verify_code.php");
    } else {
        echo "Failed to send verification email.";
    }
?>