<?php
session_start();
include 'db.php'; // Include your DB connection

if (!isset($_SESSION['user_id'])) {
    echo "Please log in.";
    exit();
}

$userId = $_SESSION['user_id'];

$result = $conn->query("SELECT subscription FROM users WHERE id = $userId");
$user = $result->fetch_assoc();

echo "<h2>Your Current Plan: " . ucfirst($user['subscription']) . "</h2>";
?>
