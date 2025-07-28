<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('../db.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!isset($_SESSION['id'])) {
            throw new Exception("❌ Error: User is not logged in.");
        }

        $user_id = $_SESSION['id'];
        $id = $_POST['interest_id'] ?? null;
        $action = $_POST['action'] ?? '';

        // ✅ Handle DELETE action first
        if ($action === "delete_interest" && $id) {
            $stmt = $db->prepare("DELETE FROM interests WHERE interest_id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            header("Location: ../profile.php?success_interest=" . urlencode("✅ Interest entry deleted successfully.") . "#interests");
            exit();
        }

        // ✅ Handle INSERT/UPDATE actions
        if ($action === "save_interest") {
            $stmt = $db->prepare("SELECT COUNT(*) FROM interests WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $interestCount = $stmt->fetchColumn();

            if (!$id && $interestCount >= 5) {
                throw new Exception("❌ You cannot add more than 5 interests.");
            }

            $interest = cleanInput($_POST['interest'] ?? '');
            $description = cleanInput($_POST['description'] ?? '');

            if ($id) {
                $stmt = $db->prepare("UPDATE interests SET interest = ?, description = ? WHERE interest_id = ? AND user_id = ?");
                $stmt->execute([$interest, $description, $id, $user_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO interests (user_id, interest, description) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $interest, $description]);
            }

            header("Location: ../profile.php?success_interest=" . urlencode("✅ Interest saved successfully.") . "#interests");
            exit();
        }
    } catch (Exception $e) {
        header("Location: ../profile.php?error_interest=" . urlencode($e->getMessage()) . "#interests");
        exit();
    }
}
?>
