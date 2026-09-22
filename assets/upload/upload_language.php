<?php

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

session_start();
include('../db.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!isset($_SESSION['id'])) {
            throw new Exception("❌ Error: User is not logged in.");
        }

        $user_id = $_SESSION['id'];
        $id = $_POST['languages_id'] ?? null;
        $action = $_POST['action'] ?? '';

        // Handle delete action
        if ($action === "delete_language" && $id) {
            $stmt = $db->prepare("DELETE FROM languages WHERE languages_id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            header("Location: ../profile.php?success_language=" . urlencode("✅ Language entry deleted successfully.") . "#languages");
            exit();
        }

        // ✅ Handle INSERT/UPDATE actions
        if ($action === "save_language") {
            $stmt = $db->prepare("SELECT COUNT(*) FROM languages WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $languageCount = $stmt->fetchColumn();

            if (!$id && $languageCount >= 5) {
                throw new Exception("❌ You cannot add more than 5 languages.");
            }

            $language = cleanInput($_POST['language'] ?? '');
            $level = cleanInput($_POST['level'] ?? '');

            if ($id) {
                $stmt = $db->prepare("UPDATE languages SET language = ?, level = ? WHERE languages_id = ? AND user_id = ?");
                $stmt->execute([$language, $level, $id, $user_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO languages (user_id, language, level) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $language, $level]);
            }

            header("Location: ../profile.php?success_language=" . urlencode("✅ Language saved successfully.") . "#languages");
            exit();
        }
    } catch (Exception $e) {
        header("Location: ../profile.php?error_language=" . urlencode($e->getMessage()) . "#languages");
        exit();
    }
}
