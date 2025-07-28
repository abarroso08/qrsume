<?php
include 'paypal_config.php';
session_start(); // Ensure user session is available

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "User not logged in."]);
    exit();
}

$userId = $_SESSION['user_id']; // Retrieve user ID from session
$data = json_decode(file_get_contents("php://input"), true);
$subscriptionID = $data['subscriptionID'];
$plan = $data['plan'];

if (!$subscriptionID || !$plan) {
    echo json_encode(["error" => "Invalid data."]);
    exit();
}

// Validate plan type
$validPlans = ['basic', 'pro', 'premium'];
if (!in_array($plan, $validPlans)) {
    echo json_encode(["error" => "Invalid plan selected."]);
    exit();
}

// Save subscription in MySQL
$conn = new mysqli("localhost", "your_db_user", "your_db_password", "your_database");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$sql = "UPDATE users SET subscription = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $plan, $userId);

if ($stmt->execute()) {
    echo json_encode(["message" => "Subscription updated successfully!"]);
} else {
    echo json_encode(["error" => "Failed to update subscription."]);
}

$stmt->close();
$conn->close();
?>
