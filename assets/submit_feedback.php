<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

// Include the database connection file
include('db.php'); 

// Set content type to plain text
header("Content-Type: text/plain");

// Ensure it is a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize inputs
    $rating = isset($_POST["rating"]) ? intval($_POST["rating"]) : 0;
    $comment = isset($_POST["comment"]) ? trim($_POST["comment"]) : "";

    // Validate the rating value
    if ($rating >= 0 && $rating <= 5) {
        try {
            // Prepare the SQL statement with NOW() for submitted_at
            $stmt = $db->prepare("INSERT INTO feedback (rating, comment, submitted_at) VALUES (:rating, :comment, NOW())");
            
            // Bind parameters correctly for PDO
            $stmt->bindValue(":rating", $rating, PDO::PARAM_INT);
            $stmt->bindValue(":comment", $comment, PDO::PARAM_STR);

            // Execute the statement
            if ($stmt->execute()) {
                echo "Thank you for your feedback!";
            } else {
                echo "Error submitting feedback.";
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage(); // Debugging message
        }
        exit;
    } else {
        echo "Invalid rating.";
        exit;
    }
}

// If request method is not POST, output an error
echo "Invalid request.";
exit;
?>
