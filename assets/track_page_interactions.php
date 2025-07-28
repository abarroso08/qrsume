<?php
include 'db.php';
$data = json_decode(file_get_contents("php://input"), true);
$ip = $_SERVER['REMOTE_ADDR'];
$page_url = $_SERVER['REQUEST_URI'];

// Update Page Interactions
$stmt = $db->prepare("UPDATE page_views SET clicks = clicks + ?, scroll_depth = GREATEST(scroll_depth, ?) WHERE ip_address = ? AND page_url = ? ORDER BY visit_time DESC LIMIT 1");
$stmt->execute([$data['clicks'], $data['scrollDepth'], $ip, $page_url]);
?>
