<?php
include("../head.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "save_personalinfo") {
    $id = $_POST['id'] ?? null; // ID de la fila en personalinfo (si existe)
    $user_id = $_SESSION['id'];

    // Limpiar inputs
    $name = cleanInput($_POST['personal_name'] ?? '');
    $lastname = cleanInput($_POST['personal_lastname'] ?? '');
    $profession = cleanInput($_POST['personal_profession'] ?? '');
    $bio = cleanInput($_POST['personal_bio'] ?? '');
    $cv_url = "pdf2.php?username=" . $_SESSION['username'];
    $personal_photo = $_POST['profile_photo'] ?? 'default.webp';

    // Datos comunes
    $data = [
        'personal_photo' => $personal_photo,
        'personal_name' => $name,
        'personal_lastname' => $lastname,
        'personal_profession' => $profession,
        'personal_bio' => $bio,
        'cv_url' => $cv_url
    ];

    if ($id) {
        // Actualizar entrada existente
        $dataToUpdate = $data;
        $conditions = ['id' => $id, 'user_id' => $user_id];
        $success = db_update($db, 'personalinfo', $dataToUpdate, $conditions);
    } else {
        // Insertar nueva entrada
        $dataToInsert = array_merge(['user_id' => $user_id], $data);
        $success = db_insert($db, 'personalinfo', $dataToInsert);
    }

    if ($success) {
        header("Location: ../profile.php?success_profile=" . urlencode("Great! Your profile information has been updated successfully. 🎉"));
    } else {
        header("Location: ../profile.php?error_profile=" . urlencode("Oops! There was an issue saving your profile. Please try again."));
    }
    exit();

} else {
    header("Location: ../error.php");
    exit();
}
?>



