<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

session_start();
include('../db.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!isset($_SESSION['id'])) {
            throw new Exception("❌ Error: User is not logged in.");
        }

        $user_id = $_SESSION['id'];
        $id = $_POST['aptitude_id'] ?? null;
        $action = $_POST['action'] ?? '';

        // ✅ Handle DELETE action first
        if ($action === "delete_aptitude" && $id) {
            $stmt = $db->prepare("DELETE FROM aptitudes WHERE aptitude_id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            header("Location: ../profile.php?success_aptitude=" . urlencode("✅ Aptitude entry deleted successfully.") . "#aptitudes");
            exit();
        }

        // ✅ Handle INSERT/UPDATE actions
        if ($action === "save_aptitude") {
            $stmt = $db->prepare("SELECT COUNT(*) FROM aptitudes WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $aptitudeCount = $stmt->fetchColumn();

            if (!$id && $aptitudeCount >= 5) {
                throw new Exception("❌ You cannot add more than 5 aptitudes.");
            }

            $aptitude = cleanInput($_POST['aptitude'] ?? '');

            if ($id) {
                $stmt = $db->prepare("UPDATE aptitudes SET aptitude = ? WHERE aptitude_id = ? AND user_id = ?");
                $stmt->execute([$aptitude, $id, $user_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO aptitudes (user_id, aptitude) VALUES (?, ?)");
                $stmt->execute([$user_id, $aptitude]);
            }

            header("Location: ../profile.php?success_aptitude=" . urlencode("✅ Aptitude saved successfully.") . "#aptitudes");
            exit();
        }
    } catch (Exception $e) {
        header("Location: ../profile.php?error_aptitude=" . urlencode($e->getMessage()) . "#aptitudes");
        exit();
    }
}
