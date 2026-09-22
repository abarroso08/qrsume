<?php

// Show errors (development only)
if (php_sapi_name() === 'cli-server') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

include("../../assets/head.php");

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['action'])
    && $_POST['action'] === "save_experienceinfo_group") {

    if (!isset($_SESSION['id'])) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=3&error=unauthorized");
        exit();
    }

    $user_id = $_SESSION['id'];

    // Arrays from form
    $ids           = $_POST['experience_id'] ?? [];
    $names         = $_POST['job_name'] ?? [];
    $dates         = $_POST['date'] ?? [];
    $places        = $_POST['place_of_work'] ?? [];
    $descriptions  = $_POST['brief_description'] ?? [];
    $deleteFlags   = $_POST['delete_flag'] ?? [];

    $success = true;

    for ($i = 0; $i < count($names); $i++) {
        $exp_id   = trim($ids[$i] ?? '');
        $toDelete = isset($deleteFlags[$i]) && $deleteFlags[$i] === 'true';

        // Sanitize
        $name     = cleanInput($names[$i] ?? '');
        $date     = cleanInput($dates[$i] ?? '');
        $place    = cleanInput($places[$i] ?? '');
        $desc     = cleanInput($descriptions[$i] ?? '');

        // Skip totally blank new rows
        if ($name === '' && $date === '' && $place === '' && $desc === '' && !$exp_id) {
            continue;
        }

        if ($toDelete && !empty($exp_id)) {
            // DELETE
            $ok = db_delete($db, 'experience', ['experience_id' => $exp_id, 'user_id' => $user_id]);
        } elseif (!empty($exp_id)) {
            // UPDATE
            $data = [
                'job_name'         => $name,
                'date'             => $date,
                'place_of_work'    => $place,
                'brief_description' => $desc,
            ];
            $ok = db_update($db, 'experience', $data, ['experience_id' => $exp_id, 'user_id' => $user_id]);
        } else {
            // INSERT
            $data = [
                'user_id'          => $user_id,
                'job_name'         => $name,
                'date'             => $date,
                'place_of_work'    => $place,
                'brief_description' => $desc,
            ];
            $ok = db_insert($db, 'experience', $data);
        }

        if (!$ok) {
            $success = false;
            break;
        }
    }

    if ($success) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=4");
        exit();
    } else {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=3&error=save_failed");
        exit();
    }

} else {
    header("Location: https://qrsume.com/create_resume/form_with_login.php?section=3&error=bad_request");
    exit();
}

// Input cleaning helper
