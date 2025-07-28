<?php
$headTitle = "Create Article";
include "head.php";

//Function to create slug
function slugify($text) {
    // Convert to lowercase
    $text = strtolower($text);

    // Replace accented characters (é, ñ, etc.)
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

    // Replace non letter or digits by -
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

    // Trim
    $text = trim($text, '-');

    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // If empty, return "n-a"
    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}

//Function to check if slug exist
function isSlugExists($slug, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blogarticles WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetchColumn() > 0;
}

//Function to iterate till a unique slug is fount
function generateUniqueSlug($title, $pdo) {
    $slug = slugify($title);
    $baseSlug = $slug;
    $counter = 1;

    while (isSlugExists($slug, $pdo)) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }

    return $slug;
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form inputs
    // Function to sanitize input fields (removes harmful characters)
    function cleanInput2($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    // Validate and sanitize input data
    $article_title = !empty($_POST['article_title']) ? $_POST['article_title'] : null;
    $article_date = !empty($_POST['article_date']) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $_POST['article_date']) ? $_POST['article_date'] : null;
    $article_content = !empty($_POST['article_content']) ? $_POST['article_content'] : null;
    $article_tags = !empty($_POST['article_tags']) ? $_POST['article_tags'] : null;
    $article_summary = !empty($_POST['article_summary']) ? $_POST['article_summary'] : null;
    
    // Validate article status (must be 'draft' or 'published')
    $valid_statuses = ['draft', 'published'];
    $article_status = (!empty($_POST['article_status']) && in_array($_POST['article_status'], $valid_statuses)) ? $_POST['article_status'] : 'draft';
    
    // Validate image filename (optional)
    $article_photo = (!empty($_POST['article_photo'])) ? $_POST['article_photo'] : 'default.webp';
    
    // If any required field is missing, return an error
    if (!$article_title || !$article_date || !$article_content || !$article_status) {
        die("Error: Missing required fields.");
    }
    
    // Now your data is sanitized and safe to use in database operations!

    
    // Check if article_id is passed (edit mode)
    if (isset($_POST['article_id']) && !empty($_POST['article_id'])) {
        // Update existing article
        $article_id = intval($_POST['article_id']); // Ensure the ID is an integer
        $sql = "UPDATE blogarticles 
                SET article_title = :article_title, article_date = :article_date, article_content = :article_content, 
                    article_tags = :article_tags, article_summary = :article_summary, article_status = :article_status, 
                    article_photo = :article_photo
                WHERE article_id = :article_id AND user_id = :user_id";
    } else {
        // Insert a new article
        $sql = "INSERT INTO blogarticles 
                (article_title, user_id, article_date, article_content, article_tags, article_summary, article_status, article_photo) 
                VALUES (:article_title, :user_id, :article_date, :article_content, :article_tags, :article_summary, :article_status, :article_photo)";
    }

    // Prepare the SQL query
    $stmt = $db->prepare($sql);

    // Bind values to the placeholders
    $stmt->bindParam(':article_title', $article_title, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $_SESSION['id'], PDO::PARAM_INT);
    $stmt->bindParam(':article_date', $article_date, PDO::PARAM_STR);
    $stmt->bindParam(':article_content', $article_content, PDO::PARAM_STR);
    $stmt->bindParam(':article_tags', $article_tags, PDO::PARAM_STR);
    $stmt->bindParam(':article_summary', $article_summary, PDO::PARAM_STR);
    $stmt->bindParam(':article_status', $article_status, PDO::PARAM_STR);
    $stmt->bindParam(':article_photo', $article_photo, PDO::PARAM_STR);

    // Bind article_id only if updating
    if (isset($article_id)) {
        $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
    }

    // Execute the query
if ($stmt->execute()) {
    if (isset($article_id)) {
        $message = "Article updated successfully";
    } else {
        $message = "New article created successfully";
    }
    $username = $_SESSION['username'];
    ?>
    <div class="container mt-4">
        <div class="alert alert-success text-center" role="alert">
            <h4 class="alert-heading"><?= $message ?></h4>
            <hr>
            <a href="<?= $username ?>" class="btn btn-primary">Go to Homepage</a>
            <a href="https://qrsume.com/assets/delete_article.php" class="btn btn-primary">Keep editing</a>
            <a href="https://qrsume.com/assets/dashboard.php" class="btn btn-primary">Dashboard</a>
        </div>
    </div>
    <?php
} else {
    ?>
    <div class="container mt-4">
        <div class="alert alert-danger text-center" role="alert">
            <h4 class="alert-heading">Error processing the article.</h4>
        </div>
    </div>
    <?php
}

}
?>
