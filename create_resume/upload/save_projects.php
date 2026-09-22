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
    && $_POST['action'] === "save_projects_group") {

    if (!isset($_SESSION['id'])) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=4&error=unauthorized");
        exit();
    }

    $user_id = $_SESSION['id'];

    // Input arrays
    $ids          = $_POST['interest_id'] ?? [];
    $names        = $_POST['interest'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    $deleteFlags  = $_POST['delete_flag'] ?? [];

    $success = true;

    for ($i = 0; $i < count($names); $i++) {
        $project_id = trim($ids[$i] ?? '');
        $toDelete   = isset($deleteFlags[$i]) && $deleteFlags[$i] === 'true';

        // Sanitize inputs
        $name = cleanInput($names[$i] ?? '');
        $desc = cleanInput($descriptions[$i] ?? '');

        // Skip fully blank new entries
        if ($name === '' && $desc === '' && !$project_id) {
            continue;
        }

        if ($toDelete && !empty($project_id)) {
            // DELETE
            $ok = db_delete($db, 'interests', ['interest_id' => $project_id, 'user_id' => $user_id]);
        } elseif (!empty($project_id)) {
            // UPDATE
            $data = [
                'interest'    => $name,
                'description' => $desc,
            ];
            $ok = db_update($db, 'interests', $data, ['interest_id' => $project_id, 'user_id' => $user_id]);
        } else {
            // INSERT
            $data = [
                'user_id'     => $user_id,
                'interest'    => $name,
                'description' => $desc,
            ];
            $ok = db_insert($db, 'interests', $data);
        }

        if (!$ok) {
            $success = false;
            break;
        }
    }

    if ($success) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=5");
        exit();
    } else {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=4&error=save_failed");
        exit();
    }

} else {
    header("Location: https://qrsume.com/create_resume/form_with_login.php?section=4&error=bad_request");
    exit();
}
