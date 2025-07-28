<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../db.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION['id'])) {
        throw new Exception("❌ Error: User is not logged in.");
    }

    $user_id = $_SESSION['id'];
    $id = $_POST['section_id'] ?? null;
    $action = $_POST['action'] ?? '';

    // ✅ DELETE
    if ($action === "delete_custom_section" && $id) {
        $stmt = $db->prepare("DELETE FROM custom_sections WHERE section_id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);

        header("Location: ../profile.php?success_custom_section=" . urlencode("✅ Section deleted successfully.") . "#customSections");
        exit();
    }

    // ✅ SAVE (INSERT / UPDATE)
    if ($action === "save_custom_section") {
        // ✅ Sanitize input
        $section_title = cleanInput($_POST['section_title'] ?? '');
        $section_content = cleanInput($_POST['section_content'] ?? '');

        if ($id) {
            // ✅ Update
            $stmt = $db->prepare("UPDATE custom_sections SET section_title = ?, section_content = ? WHERE section_id = ? AND user_id = ?");
            $stmt->execute([$section_title, $section_content, $id, $user_id]);
        } else {
            // ✅ Insert
            $stmt = $db->prepare("INSERT INTO custom_sections (user_id, section_title, section_content) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $section_title, $section_content]);
        }

        header("Location: ../profile.php?success_custom_section=" . urlencode("✅ Section saved successfully.") . "#customSections");
        exit();
    }

} else {
    header("Location: ../error.php?error=" . urlencode("Invalid request."));
    exit();
}
