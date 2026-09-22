<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

include('../db.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION['id'])) {
        throw new Exception("❌ Error: User is not logged in.");
    }

    $user_id = $_SESSION['id'];
    $id = $_POST['education_id'] ?? null;
    $action = $_POST['action'] ?? '';
    echo $id;
    echo "hola";

    // ✅ Handle DELETE action first to prevent unintended inserts

    if ($action === "delete_education" && $id) {
        $stmt = $db->prepare("DELETE FROM education WHERE education_id = ?");
        $stmt->execute([$id]);

        header("Location: ../profile.php?success_education=" . urlencode("✅ Education entry deleted successfully.") . "#education");
        exit();
    }

    // ✅ Handle INSERT/UPDATE actions separately
    if ($action === "save_educationinfo") {
        // ✅ Count existing education entries
        $stmt = $db->prepare("SELECT COUNT(*) FROM education WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $educationCount = $stmt->fetchColumn();

        // ✅ Prevent adding more than 5 entries
        if (!$id && $educationCount >= 5) {
            throw new Exception("❌ You cannot add more than 5 education entries.");
        }

        // ✅ Sanitize input
        $date = cleanInput($_POST['date'] ?? '');
        $place_of_study = cleanInput($_POST['place_of_study'] ?? '');
        $name_of_studies = cleanInput($_POST['name_of_studies'] ?? '');
        $brief_description = cleanInput($_POST['brief_description'] ?? '');

        if ($id) {
            // ✅ Update existing entry
            $stmt = $db->prepare("UPDATE education SET date = ?, place_of_study = ?, name_of_studies = ?, brief_description = ? WHERE education_id = ? AND user_id = ?");
            $stmt->execute([$date, $place_of_study, $name_of_studies, $brief_description, $id, $user_id]);
        } else {
            // ✅ Insert new entry
            $stmt = $db->prepare("INSERT INTO education (user_id, date, place_of_study, name_of_studies, brief_description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $date, $place_of_study, $name_of_studies, $brief_description]);
        }

        header("Location: ../profile.php?success_education=" . urlencode("✅ Education information saved successfully.") . "#education");
        exit();
    }


} else {
    header("Location: ../error.php?error=" . urlencode("Invalid request."));
    exit();
}
