<?php
// Enable error reporting (development only)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("../../assets/head.php");

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['action'])
    && $_POST['action'] === "save_aptitudes_group") {

    if (!isset($_SESSION['id'])) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=5&error=unauthorized");
        exit();
    }

    $user_id = $_SESSION['id'];

    // Input arrays from form
    $ids         = $_POST['aptitude_id'] ?? [];
    $names       = $_POST['aptitude'] ?? [];
    $deleteFlags = $_POST['delete_flag'] ?? [];

    $success = true;

    for ($i = 0; $i < count($names); $i++) {
        $apt_id   = trim($ids[$i] ?? '');
        $toDelete = isset($deleteFlags[$i]) && $deleteFlags[$i] === 'true';

        // Sanitize input
        $aptitude = cleanInput($names[$i] ?? '');

        // Skip fully blank new rows
        if ($aptitude === '' && !$apt_id) {
            continue;
        }

        if ($toDelete && !empty($apt_id)) {
            // DELETE
            $ok = db_delete($db, 'aptitudes', ['aptitude_id' => $apt_id, 'user_id' => $user_id]);
        } elseif (!empty($apt_id)) {
            // UPDATE
            $data = ['aptitude' => $aptitude];
            $ok = db_update($db, 'aptitudes', $data, ['aptitude_id' => $apt_id, 'user_id' => $user_id]);
        } else {
            // INSERT
            $data = ['user_id' => $user_id, 'aptitude' => $aptitude];
            $ok = db_insert($db, 'aptitudes', $data);
        }

        if (!$ok) {
            $success = false;
            break;
        }
    }

    if ($success) {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=6");
        exit();
    } else {
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=5&error=save_failed");
        exit();
    }

} else {
    header("Location: https://qrsume.com/create_resume/form_with_login.php?section=5&error=bad_request");
    exit();
}


?>
