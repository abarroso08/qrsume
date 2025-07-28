<?php
// Debugging - show PHP errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("../../assets/head.php");

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['action'])
    && $_POST['action'] === "save_custom_sections_group") {

    if (!isset($_SESSION['id'])) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=7&error=unauthorized");
        exit();
    }

    $user_id = $_SESSION['id'];

    // Arrays from the form
    $ids            = $_POST['section_id'] ?? [];
    $titles         = $_POST['section_title'] ?? [];
    $contents       = $_POST['section_content'] ?? [];
    $deleteFlags    = $_POST['delete_flag'] ?? [];

    $success = true;

    for ($i = 0; $i < count($titles); $i++) {
        $section_id = trim($ids[$i] ?? '');
        $toDelete   = isset($deleteFlags[$i]) && $deleteFlags[$i] === 'true';

        // Clean input
        $title   = cleanInput($titles[$i] ?? '');
        $content = cleanInput($contents[$i] ?? '');

        // Skip empty new entries
        if ($title === '' && $content === '' && !$section_id) {
            continue;
        }

        if ($toDelete && !empty($section_id)) {
            // DELETE
            $ok = db_delete($db, 'custom_sections', ['section_id' => $section_id, 'user_id' => $user_id]);
        } elseif (!empty($section_id)) {
            // UPDATE
            $data = [
                'section_title'   => $title,
                'section_content' => $content
            ];
            $ok = db_update($db, 'custom_sections', $data, ['section_id' => $section_id, 'user_id' => $user_id]);
        } else {
            // INSERT
            $data = [
                'user_id'         => $user_id,
                'section_title'   => $title,
                'section_content' => $content
            ];
            $ok = db_insert($db, 'custom_sections', $data);
        }

        if (!$ok) {
            $success = false;
            break;
        }
    }

    if ($success) {
        header("Location: https://qrsume.com/preview.php");
        exit();
    } else {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=7&error=save_failed");
        exit();
    }

} else {
    header("Location: https://qrsume.com/create_resume/form_with_login.php?section=7&error=bad_request");
    exit();
}


?>
