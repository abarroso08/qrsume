<?php

// Enable error reporting (for debugging)
if (php_sapi_name() === 'cli-server') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

include("../../assets/head.php");

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['action'])
    && $_POST['action'] === "save_languages_group") {

    if (!isset($_SESSION['id'])) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=6&error=unauthorized");
        exit();
    }

    $user_id = $_SESSION['id'];

    // Form input arrays
    $ids         = $_POST['languages_id'] ?? [];
    $langs       = $_POST['language'] ?? [];
    $levels      = $_POST['level'] ?? [];
    $deleteFlags = $_POST['delete_flag'] ?? [];

    $success = true;

    for ($i = 0; $i < count($langs); $i++) {
        $lang_id  = trim($ids[$i] ?? '');
        $toDelete = isset($deleteFlags[$i]) && $deleteFlags[$i] === 'true';

        // Sanitize inputs
        $language = cleanInput($langs[$i] ?? '');
        $level    = cleanInput($levels[$i] ?? '');

        // Skip completely blank new entries
        if ($language === '' && $level === '' && !$lang_id) {
            continue;
        }

        if ($toDelete && !empty($lang_id)) {
            // DELETE
            $ok = db_delete($db, 'languages', ['languages_id' => $lang_id, 'user_id' => $user_id]);
        } elseif (!empty($lang_id)) {
            // UPDATE
            $data = [
                'language' => $language,
                'level'    => $level,
            ];
            $ok = db_update($db, 'languages', $data, ['languages_id' => $lang_id, 'user_id' => $user_id]);
        } else {
            // INSERT
            $data = [
                'user_id'  => $user_id,
                'language' => $language,
                'level'    => $level,
            ];
            $ok = db_insert($db, 'languages', $data);
        }

        if (!$ok) {
            $success = false;
            break;
        }
    }

    if ($success) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=7");
        exit();
    } else {
        header("Location: &error=save_failed");
        exit();
    }

} else {
    header("Location: https://qrsume.com/create_resume/form_with_login.php?section=6&error=bad_request");
    exit();
}
