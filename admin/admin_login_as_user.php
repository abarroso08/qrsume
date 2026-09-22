<?php

$errors = true;
include '../assets/db.php';

// Only allow if logged in as admin
if (!isset($_SESSION['privilege']) || $_SESSION['privilege'] !== 'admin') {
    die("Access denied.");
}

// Validate POST
if (!isset($_POST['target_user_id'], $_POST['target_username'])) {
    die("Invalid request.");
}

$target_id = (int) $_POST['target_user_id'];
$target_username = trim($_POST['target_username']);

// Optional: check if user exists in DB
$stmt = $db->prepare("SELECT id, username FROM users WHERE id = ? AND username = ?");
$stmt->execute([$target_id, $target_username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Set session to act as that user
$_SESSION['id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['privilege'] = 'admin'; // prevent acting as admin accidentally

// Optional: store admin's real identity in a separate session var
$_SESSION['admin_impersonating'] = true;

// Redirect to user's dashboard
// Redirect to user's dashboard
header("Location: https://qrsume.com/assets/dashboard.php");
exit();
