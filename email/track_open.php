<?php
// Show errors for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your DB connection, make sure $db is a valid PDO instance
include("../assets/db.php");

// Prevent any output before headers
ob_start();

// Get input safely
$email_id = $_GET['email_id'] ?? '';
$recipient_email = $_GET['recipient_email'] ?? null;

if (!$email_id) {
    http_response_code(400);
    exit("Missing email_id");
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    // Check if this is the first open for this email_id
    $stmt = $db->prepare("SELECT COUNT(*) FROM email_tracking WHERE email_id = ?");
    $stmt->execute([$email_id]);
    $exists = $stmt->fetchColumn() > 0;
    
    if(!$exists){
        // Insert tracking record only if it does NOT exist yet (first open)
        $insert = $db->prepare("INSERT INTO email_tracking 
            (email_id, recipient_email, ip_address, user_agent, is_first_open) 
            VALUES (:email_id, :recipient_email, :ip_address, :user_agent, TRUE)");

        $insert->execute([
            ':email_id' => $email_id,
            ':recipient_email' => $recipient_email,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ]);
}
} catch (Exception $e) {
    // Optional: log error or ignore to not break pixel loading
    // error_log($e->getMessage());
}

// Clear any output buffer so headers can be sent properly
ob_end_clean();

// Send image headers
header("Content-Type: image/png");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Output a 1x1 transparent PNG
echo base64_decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAkcB9B0koyEAAAAASUVORK5CYII="
);
exit();
