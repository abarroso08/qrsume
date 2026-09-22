<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

include "../head.php";
$user_id = $_SESSION['id'];
$username = $_SESSION['username'];
$referrer = $_POST['referrer'] ?? '../assets/article_form.php';

// 🔁 Function to stop and redirect with error
function redirectWithError($message, $referrer)
{
    header("Location: $referrer?errors=" . urlencode($message));
    exit();
}

// ✅ Check file upload
if (!isset($_FILES['article_photo']) || $_FILES['article_photo']['error'] !== UPLOAD_ERR_OK) {
    redirectWithError("❌ Error: No file uploaded or file upload error.", $referrer);
}

// ✅ Check user ID
if (!isset($user_id) || empty($user_id)) {
    redirectWithError("❌ Error: user_id is not defined.", $referrer);
}
if (!isset($username) || empty($username)) {
    redirectWithError("❌ Error: user_id is not defined.", $referrer);
}
// ✅ Setup
$originalFileName = basename($_FILES['article_photo']['name']);
$fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
$base_dir =  "../../images/".$username . "/";
$profile_dir = $base_dir . "profile_picture/";
$article_dir = $base_dir . "article_pictures/";

// ✅ Create directories if they don't exist
foreach ([$base_dir, $profile_dir, $article_dir] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ✅ Determine upload source and path
$source = $_POST['source'] ?? '';
if ($source === 'profile_picture') {
    $image_name = $username . "_profile_picture.webp";
    $target_file = $profile_dir . $image_name;
} elseif ($source === 'create_article' && isset($_POST['article_id'])) {
    $article_id = preg_replace('/[^0-9]/', '', $_POST['article_id']);
    $image_name = "article_" . $article_id . ".webp";
    $target_file = $article_dir . $image_name;
} else {
    redirectWithError("Error: Invalid upload source or missing article ID.", $referrer);
}

// ✅ Validate image
$check = getimagesize($_FILES["article_photo"]["tmp_name"]);
if ($check === false) {
    redirectWithError("Error: File is not a valid image.", $referrer);
}

$allowed_formats = ["jpg", "jpeg", "png", "gif", "webp"];
if (!in_array($fileExtension, $allowed_formats)) {
    redirectWithError("Error: Only JPG, JPEG, PNG, GIF and WEBP formats are allowed.", $referrer);
}

// ✅ Load the image
switch ($fileExtension) {
    case 'jpg':
    case 'jpeg':
        $image = imagecreatefromjpeg($_FILES["article_photo"]["tmp_name"]);
        break;
    case 'png':
        $image = imagecreatefrompng($_FILES["article_photo"]["tmp_name"]);
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        break;
    case 'gif':
        $image = imagecreatefromgif($_FILES["article_photo"]["tmp_name"]);
        break;
    case 'webp':
        $image = imagecreatefromwebp($_FILES["article_photo"]["tmp_name"]);
        break;
    default:
        redirectWithError("Error: Unsupported image format.", $referrer);
}

// ✅ Crop image to square (only for profile)
if ($source !== 'create_article') {
    $width = imagesx($image);
    $height = imagesy($image);
    $size = min($width, $height);
    $x_offset = ($width - $size) / 2;
    $y_offset = ($height - $size) / 2;

    // Create a square image
    $croppedImage = imagecreatetruecolor($size, $size);
    imagecopyresampled($croppedImage, $image, 0, 0, $x_offset, $y_offset, $size, $size, $size, $size);

    $squareImage = imagecreatetruecolor(500, 500);
    imagecopyresampled($squareImage, $croppedImage, 0, 0, 0, 0, 500, 500, $size, $size);

    imagedestroy($croppedImage); // Clean up
} else {
    $squareImage = $image;
}

// ✅ Compress to under 100KB
$quality = 80;
do {
    ob_start();
    imagewebp($squareImage, null, $quality);
    $compressedData = ob_get_contents();
    ob_end_clean();
    $fileSize = strlen($compressedData);
    $quality -= 10;
} while ($fileSize > 75 * 1024 && $quality > 10);

// ✅ Save image (overwrite allowed)
imagewebp($squareImage, "../images/" .$target_file, $quality);
imagedestroy($image);
if ($squareImage !== $image) {
    imagedestroy($squareImage);
}
unlink($_FILES["article_photo"]["tmp_name"]);

// ✅ Store path in DB
try {
    if ($source === 'profile_picture') {
        $dataToUpdate = ['personal_photo' => $username . "/profile_picture/" . $image_name . "?ts=".filemtime('../../images/' . $username . '/profile_picture/' . $image_name)];
        $conditions = ['user_id' => $user_id];
        db_update($db, 'personalinfo', $dataToUpdate, $conditions);
        $success_message = "Profle photo uploaded and stored successfully.";
    } elseif ($source === 'create_article' && isset($_POST['article_id'])) {
        $dataToUpdate = ['article_photo' => $username . "/article_pictures/" . $image_name . "?ts=".filemtime('../../images/' . $username . '/article_pictures/' . $image_name)];
        $conditions = ['user_id' => $user_id, 'article_id' => $_POST['article_id']];
        db_update($db, 'blogarticles', $dataToUpdate, $conditions);
    }
} catch (PDOException $e) {
    redirectWithError("Database Exception: " . $e->getMessage(), $referrer);
}

// ✅ Success redirect
header("Location: $referrer?success_profile=" . urlencode($success_message));
exit();
