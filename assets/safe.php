<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Prevent session hijacking
if (!isset($_SESSION['fingerprint'])) {
    session_destroy();
    header("Location: login.php");
    exit();
} elseif ($_SESSION['fingerprint'] !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Implement inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) { // 30 minutes
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();
?>