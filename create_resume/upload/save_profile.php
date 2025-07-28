<?php

include("../../assets/head.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" 
    && isset($_POST['action']) 
    && $_POST['action'] === "save_personalinfo") {

    if (!isset($_SESSION['id'])) {
        // Unauthorized access
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=0&error=unauthorized");
        exit();
    }

    $id       = $_POST['id'] ?? null;
    $user_id  = $_SESSION['id'];

    // Clean inputs
    $name       = cleanInput($_POST['personal_name'] ?? '');
    $lastname   = cleanInput($_POST['personal_lastname'] ?? '');
    $profession = cleanInput($_POST['personal_profession'] ?? '');
    $bio        = cleanInput($_POST['personal_bio'] ?? '');

    $cv_url = isset($_SESSION['username']) 
              ? "pdf2.php?username=" . urlencode($_SESSION['username']) 
              : '';

    $data = [
        'personal_name'       => $name,
        'personal_lastname'   => $lastname,
        'personal_profession' => $profession,
        'personal_bio'        => $bio,
        'cv_url'              => $cv_url
    ];

    if ($id) {
        // Update existing record
        $ok = db_update($db, 'personalinfo', $data, ['id' => $id, 'user_id' => $user_id]);
    } else {
        // Insert new record
        $data['user_id'] = $user_id;
        $ok = db_insert($db, 'personalinfo', $data);
    }

    if ($ok) {
        // ✅ Success — redirect to section 1
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=1");
        exit();
    } else {
        // ❌ Error — redirect to section 0 with error message
        header("Location: https://qrsume.com/create_resume/form_with_login.php?section=0&error=save_failed");
        exit();
    }

} else {
    // Bad request
    header("Location: https://qrsume.com/create_resume/form_with_login.php?section=0&error=bad_request");
    exit();
}
?>