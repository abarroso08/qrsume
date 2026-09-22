<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

include "head.php";

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

// ✅ Setup
$target_dir = "../images/";
$originalFileName = basename($_FILES['article_photo']['name']);
$fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
$fileNameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
$truncatedFileName = (strlen($fileNameWithoutExt) > 15) ? substr($fileNameWithoutExt, 0, 15) : $fileNameWithoutExt;
$image_name =  $truncatedFileName . "_" . $_SESSION['username'] . ".webp";
$target_file = $target_dir . $image_name;

// ✅ Validate image
$check = getimagesize($_FILES["article_photo"]["tmp_name"]);
if ($check === false) {
    redirectWithError("❌ Error: File is not a valid image.", $referrer);
}

$allowed_formats = ["jpg", "jpeg", "png", "gif", "webp"];
if (!in_array($fileExtension, $allowed_formats)) {
    redirectWithError("❌ Error: Only JPG, JPEG, PNG, GIF and WEBP formats are allowed.", $referrer);
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
        redirectWithError("❌ Error: Unsupported image format.", $referrer);
}

// ✅ Crop image to square
$source = $_POST['source'] ?? '';
if ($source !== 'create_article') {
    $width = imagesx($image);
    $height = imagesy($image);
    $size = min($width, $height);
    $x_offset = ($width - $size) / 2;
    $y_offset = ($height - $size) / 2;

    $squareImage = imagecreatetruecolor($size, $size);
    imagecopyresampled($squareImage, $image, 0, 0, $x_offset, $y_offset, $size, $size, $size, $size);
} else {
    $squareImage = $image;
}

// ✅ Compress
$quality = 90;
do {
    ob_start();
    imagewebp($squareImage, null, $quality);
    $compressedData = ob_get_contents();
    ob_end_clean();
    $fileSize = strlen($compressedData);
    $quality -= 10;
} while ($fileSize > 100 * 1024 && $quality > 10);

// ✅ Check file existence
if (file_exists($target_file)) {
    redirectWithError("❌ Error: File already exists as " . htmlspecialchars($image_name), $referrer);
}

// ✅ Save final image
imagewebp($squareImage, $target_file, $quality);
imagedestroy($image);
if ($squareImage !== $image) {
    imagedestroy($squareImage);
}
unlink($_FILES["article_photo"]["tmp_name"]);

// ✅ Store in DB
try {
    $sql = "INSERT INTO photos (user_id, photo_url) VALUES (:user_id, :photo_url)";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':user_id', $_SESSION['id'], PDO::PARAM_INT);
    $stmt->bindValue(':photo_url', $image_name, PDO::PARAM_STR);

    if (!$stmt->execute()) {
        redirectWithError("❌ Database Error: " . implode(", ", $stmt->errorInfo()), $referrer);
    }
} catch (PDOException $e) {
    redirectWithError("❌ Database Exception: " . $e->getMessage(), $referrer);
}

// ✅ Redirect success
$success_message = "✅ File uploaded: " . htmlspecialchars($image_name) .
    ", compressed to optimal size, and stored successfully. Go back ← to return to your previous form.";
header("Location: $referrer?success=" . urlencode($success_message));
exit();
