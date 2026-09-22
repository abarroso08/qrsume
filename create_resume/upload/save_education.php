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
    && $_POST['action'] === "save_educationinfo_group") {

    if (!isset($_SESSION['id'])) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=2&error=unauthorized");
        exit();
    }

    $user_id = $_SESSION['id'];

    // Get posted arrays
    $ids           = $_POST['education_id'] ?? [];
    $names         = $_POST['name_of_studies'] ?? [];
    $dates         = $_POST['date'] ?? [];
    $places        = $_POST['place_of_study'] ?? [];
    $descriptions  = $_POST['brief_description'] ?? [];
    $deleteFlags   = $_POST['delete_flag'] ?? [];

    $success = true;

    for ($i = 0; $i < count($names); $i++) {
        $edu_id   = trim($ids[$i] ?? '');
        $toDelete = isset($deleteFlags[$i]) && $deleteFlags[$i] === 'true';

        // Sanitize inputs
        $name  = cleanInput($names[$i] ?? '');
        $date  = cleanInput($dates[$i] ?? '');
        $place = cleanInput($places[$i] ?? '');
        $desc  = cleanInput($descriptions[$i] ?? '');

        // Skip completely empty rows
        if ($name === '' && $date === '' && $place === '' && $desc === '' && !$edu_id) {
            continue;
        }

        // DELETE
        if ($toDelete && !empty($edu_id)) {
            $ok = db_delete($db, 'education', ['education_id' => $edu_id, 'user_id' => $user_id]);
        }
        // UPDATE
        elseif (!empty($edu_id)) {
            $data = [
                'name_of_studies'   => $name,
                'date'              => $date,
                'place_of_study'    => $place,
                'brief_description' => $desc,
            ];
            $ok = db_update($db, 'education', $data, ['education_id' => $edu_id, 'user_id' => $user_id]);
        }
        // INSERT
        else {
            $data = [
                'user_id'           => $user_id,
                'name_of_studies'   => $name,
                'date'              => $date,
                'place_of_study'    => $place,
                'brief_description' => $desc,
            ];
            $ok = db_insert($db, 'education', $data);
        }

        if (!$ok) {
            $success = false;
            break;
        }
    }

    if ($success) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=3");
        exit();
    } else {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=2&error=save_failed");
        exit();
    }

} else {
    header("Location: https://qrsume.com/create_resume/form_with_login.php?section=2&error=bad_request");
    exit();
}
