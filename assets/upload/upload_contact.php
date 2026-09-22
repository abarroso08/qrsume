<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

session_start();
include('../db.php'); // Ensure database connection

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "save_contactinfo") {
    try {
        if (!isset($_SESSION['id'])) {
            throw new Exception("❌ Error: User is not logged in.");
        }

        $user_id = $_SESSION['id'];

        // ✅ Sanitize input
        $phone_number = cleanInput($_POST['phone_number'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $github = cleanInput($_POST['github'] ?? '');
        $facebook = cleanInput($_POST['facebook'] ?? '');
        $linkedin = cleanInput($_POST['linkedin'] ?? '');
        $twitter = cleanInput($_POST['twitter'] ?? '');

        // ✅ Check if the user already has contact info
        $stmt = $db->prepare("SELECT user_id FROM contactinfo WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            // ✅ Update existing entry
            $stmt = $db->prepare("UPDATE contactinfo SET phone_number = ?, email = ?, github = ?, facebook = ?, linkedin = ?, twitter = ? WHERE user_id = ?");
            $stmt->execute([$phone_number, $email, $github, $facebook, $linkedin, $twitter, $user_id]);
        } else {
            // ✅ Insert new entry
            $stmt = $db->prepare("INSERT INTO contactinfo (user_id, phone_number, email, github, facebook, linkedin, twitter) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $phone_number, $email, $github, $facebook, $linkedin, $twitter]);
        }

        // ✅ Redirect after success
        header("Location: ../profile.php?success_contact=" . urlencode("Contact information saved successfully.") . "#contact");
        exit();
    } catch (Exception $e) {
        // ✅ Log error and redirect to error page
        error_log("Contact Info Save Error: " . $e->getMessage());
        header("Location: ../error.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // ✅ Redirect if request is invalid
    header("Location: ../error.php?error=" . urlencode("Invalid request."));
    exit();
}
