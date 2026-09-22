<!-- Load HTML head -->
<?php
include "assets/db.php";

// ✅ Error control for development (optional)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// ✅ Ensure required GET parameters exist
if (!isset($_GET['id'], $_GET['user_id'])) {
    header('Location: error.php');
    exit();
}

$article_id = intval($_GET["id"]);
$user_id = intval($_GET["user_id"]);

// ✅ Fetch article from DB
$stmt = $db->prepare("SELECT * FROM blogarticles WHERE article_id = :article_id");
$stmt->bindValue(":article_id", $article_id, PDO::PARAM_INT);
$stmt->execute();
$article = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Redirect if article not found or unpublished
if (!$article || $article["article_status"] !== "published") {
    header('Location: error.php');
    exit();
}

// ✅ Fetch user (for username / author)
$stmt_user = $db->prepare("SELECT username FROM users WHERE id = :user_id");
$stmt_user->bindValue(":user_id", $user_id, PDO::PARAM_INT);
$stmt_user->execute();
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// ✅ Redirect if user does not exist
if (!$user) {
    header('Location: error.php');
    exit();
}

$username_url = $user['username'];
$tags = explode(",", $article['article_tags']);

// ✅ Increment view count
$stmt_views = $db->prepare("UPDATE blogarticles SET views = views + 1 WHERE article_id = :article_id");
$stmt_views->bindValue(":article_id", $article_id, PDO::PARAM_INT);
$stmt_views->execute();

// ✅ Set dynamic meta tags (for SEO and social)
$meta_title = htmlspecialchars($article["article_title"] . " | QRsume", ENT_QUOTES, 'UTF-8');
$meta_description = mb_strimwidth(strip_tags($article["article_content"]), 0, 160, '...');
$meta_image = "https://qrsume.com/images/" . urlencode($article["article_photo"]);
$meta_url = "https://qrsume.com/article.php?user_id={$user_id}&id={$article_id}";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="language" content="en">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">

    <!-- Title and Description -->
    <title><?= htmlspecialchars($article["article_title"] ?? "QRsume Article") ?> | QRsume</title>
    <meta name="description" content="<?= htmlspecialchars(substr(strip_tags($article['article_content']), 0, 160)) ?>">
    <meta name="keywords" content="QR Resume, Resume Blog, Career Tips, <?= htmlspecialchars(implode(', ', explode(',', $article['article_tags'] ?? ''))) ?>">

    <!-- Favicon & Apple Touch -->
    <link rel="icon" href="https://qrsume.com/images/favicon (4).ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="https://qrsume.com/images/favicon (4).ico">
    <link rel="icon" type="image/png" sizes="32x32" href="https://qrsume.com/images/favicon (4).ico">
    <link rel="icon" type="image/png" sizes="16x16" href="https://qrsume.com/images/favicon (4).ico">

    <!-- Open Graph (Facebook, LinkedIn) -->
    <meta property="og:title" content="<?= htmlspecialchars($article["article_title"] ?? "QRsume Article") ?>">
    <meta property="og:description" content="<?= htmlspecialchars(substr(strip_tags($article['article_content']), 0, 150)) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:image" content="https://qrsume.com/images/<?= htmlspecialchars($article['article_photo'] ?? 'og-image.png') ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($article["article_title"] ?? "QRsume Article") ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars(substr(strip_tags($article['article_content']), 0, 150)) ?>">
    <meta name="twitter:image" content="https://qrsume.com/images/<?= htmlspecialchars($article['article_photo'] ?? 'qrsume_logo_barroso.webp') ?>">

    <!-- Bootstrap and Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Custom Variables -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        :root {
            --primary: <?= htmlspecialchars($primary) ?>;
            --secondary: <?= htmlspecialchars($primary) ?>;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
        pre[class^="language-"] {
            background: #1e1e1e;
            color: #dcdcdc;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Fira Code', monospace;
            overflow-x: auto;
        }
        code {
            color: #c7254e;
            padding: 2px 4px;
            border-radius: 4px;
            font-family: 'Fira Code', monospace;
        }
        .pre-scrollable {
            max-height: 350px;
            overflow-y: auto;
        }
    </style>

<body>
<?php include "assets/nav_profile.php"; ?>

<div class="container-xxl overflow-hidden">
    <article class="container-fluid col-12 col-md-8 mt-5">
        <!-- Article Image -->
        <img class="w-100 img-fluid rounded" style="object-fit:cover; height:30vh;" 
             src="images/<?= htmlspecialchars($article["article_photo"], ENT_QUOTES, 'UTF-8') ?>" 
             alt="<?= htmlspecialchars($article["article_title"], ENT_QUOTES, 'UTF-8') ?>">

        <!-- Article Header -->
        <header class="mb-4">
            <h1 class="fw-bold display-6" style="color:var(--primary)">
                <?= htmlspecialchars($article["article_title"], ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <div class="text-muted fst-italic mb-2">
                Posted on: <?= htmlspecialchars($article["article_date"], ENT_QUOTES, 'UTF-8') ?>
            </div>

            <!-- Tags -->
            <?php foreach ($tags as $tag): ?>
                <a class="badge bg-secondary text-decoration-none link-light me-1" href="#!">
                    <?= htmlspecialchars(trim($tag), ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>

            <p class="text-primary fw-bold my-2 text-capitalize">
                <?= htmlspecialchars($article["article_status"], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </header>

        <!-- Main Content -->
        <section class="mb-5 mt-5" style="text-align:justify;">
            <?= $article["article_content"]; ?>
        </section>
    </article>
    
    <!-- Share Buttons -->
<div class="container-fluid col-12 col-md-8 mt-5">
    <h5 class="fw-bold mb-3">Share this article:</h5>
    <div class="d-flex gap-3 flex-wrap">
        <?php
        $encodedTitle = urlencode($article['article_title']);
$encodedUrl = urlencode("https://qrsume.com/article.php?user_id={$user_id}&id={$article_id}");
?>

        <a class="btn btn-outline-primary" 
           href="https://twitter.com/intent/tweet?text=<?= $encodedTitle ?>&url=<?= $encodedUrl ?>" 
           target="_blank" rel="noopener noreferrer">
            <i class="bi bi-twitter me-1"></i> Twitter
        </a>

        <a class="btn btn-outline-primary" 
           href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedUrl ?>" 
           target="_blank" rel="noopener noreferrer">
            <i class="bi bi-facebook me-1"></i> Facebook
        </a>

        <a class="btn btn-outline-primary" 
           href="https://www.linkedin.com/shareArticle?mini=true&url=<?= $encodedUrl ?>&title=<?= $encodedTitle ?>" 
           target="_blank" rel="noopener noreferrer">
            <i class="bi bi-linkedin me-1"></i> LinkedIn
        </a>

        <a class="btn btn-outline-success" 
           href="https://wa.me/?text=<?= $encodedTitle ?>%20<?= $encodedUrl ?>" 
           target="_blank" rel="noopener noreferrer">
            <i class="bi bi-whatsapp me-1"></i> WhatsApp
        </a>
    </div>
</div>

</div>



<?php include "assets/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
