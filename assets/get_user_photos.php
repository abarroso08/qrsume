<?php
include("../db.php"); // or wherever your db.php is

session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$stmt = $db->prepare("SELECT photo_url FROM photos WHERE user_id = :user_id");
$stmt->bindValue(':user_id', $_SESSION['id'], PDO::PARAM_INT);
$stmt->execute();
$photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json');
echo json_encode($photos);
?>