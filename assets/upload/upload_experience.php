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
        $id = $_POST['experience_id'] ?? null;
        $action = $_POST['action'] ?? '';

        // ✅ Handle DELETE action first
        if ($action === "delete_experience" && $id) {
            $stmt = $db->prepare("DELETE FROM experience WHERE experience_id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            header("Location: ../profile.php?success_experience=" . urlencode("✅ Work experience entry deleted successfully.") . "#experience");
            exit();
        }

        // ✅ Handle INSERT/UPDATE actions
        if ($action === "save_experience") {
            $stmt = $db->prepare("SELECT COUNT(*) FROM experience WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $experienceCount = $stmt->fetchColumn();

            if (!$id && $experienceCount >= 5) {
                throw new Exception("❌ You cannot add more than 5 work experience entries.");
            }

            $date = cleanInput($_POST['date'] ?? '');
            $place_of_work = cleanInput($_POST['place_of_work'] ?? '');
            $job_name = cleanInput($_POST['job_name'] ?? '');
            $brief_description = cleanInput($_POST['brief_description'] ?? '');

            if ($id) {
                $stmt = $db->prepare("UPDATE experience SET date = ?, place_of_work = ?, job_name = ?, brief_description = ? WHERE experience_id = ? AND user_id = ?");
                $stmt->execute([$date, $place_of_work, $job_name, $brief_description, $id, $user_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO experience (user_id, date, place_of_work, job_name, brief_description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $date, $place_of_work, $job_name, $brief_description]);
            }

            header("Location: ../profile.php?success_experience=" . urlencode("✅ Work experience saved successfully.") . "#experience");
            exit();
        }
    } catch (Exception $e) {
        header("Location: ../profile.php?error_experience=" . urlencode($e->getMessage()) . "#experience");
        exit();
    }
}
