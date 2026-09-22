<?php
// Turn on error reporting (for debugging)
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

include "assets/head.php";

// Check and sanitize user_id
if (!isset($_GET['user_id'])) {
    header('Location: https://qrsume.com/error.php');
    exit();
} else {
    $user_id = (int) $_GET['user_id'];
    if ($user_id <= 0) {
        header("Location: https://qrsume.com/error.php");
        exit();
    }

    $stmt_blog = $db->prepare("SELECT personal_name, personal_lastname FROM personalinfo WHERE user_id = :user_id");
    $stmt_blog->bindValue(":user_id", $user_id, PDO::PARAM_INT);
    $stmt_blog->execute();

    $user = $stmt_blog->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $name = $user['personal_name'];
        $lastname = $user['personal_lastname'];
    } else {
        header("Location: error.php");
        exit();
    }

    // Get all tags for this user
    $stmt_tags = $db->prepare("SELECT article_tags FROM blogarticles WHERE user_id = :user_id AND article_status = 'published'");
    $stmt_tags->bindValue(":user_id", $user_id, PDO::PARAM_INT);
    $stmt_tags->execute();
    $allTagsRaw = $stmt_tags->fetchAll(PDO::FETCH_COLUMN);

    $tagSet = [];
    foreach ($allTagsRaw as $tagList) {
        $tags = array_map('trim', explode(',', $tagList));
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $tagSet[strtolower($tag)] = $tag;
            }
        }
    }
    ksort($tagSet);
}
?>
</head>

<body>
<?php include "assets/nav_profile.php"; ?>

<div class="container text-center pt-4">
    <h1 class="fw-bold mb-3">Welcome to <?= htmlspecialchars($name) ?>'s blog</h1>

    <!-- Tag filter buttons -->
    <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
        <button class="btn btn-outline-secondary btn-sm active" data-tag="all">All</button>
        <?php foreach ($tagSet as $tagSlug => $tagText): ?>
            <button class="btn btn-outline-secondary btn-sm" data-tag="<?= htmlspecialchars(strtolower(trim($tagSlug))) ?>">
                <?= htmlspecialchars($tagText) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<div class="container-xxl overflow-hidden">
    <div class="row gy-3 p-md-5 p-3" id="blog">
        <?php
        $stmt_blog = $db->query("SELECT * FROM `blogarticles` WHERE `user_id` = $user_id AND `article_status` = 'published' ORDER BY `article_date` DESC");
$results_blog["blogarticles"] = $stmt_blog->fetchAll(PDO::FETCH_ASSOC);

foreach ($results_blog["blogarticles"] as $article):
    if ($article["article_status"] == "published"):
        $tags = explode(',', $article['article_tags']);
        $dataTags = implode('|', array_map('strtolower', array_map('trim', $tags)));
        ?>
        <div class="col-sm-6 blog-card" style="display:flex;" data-tags="<?= htmlspecialchars($dataTags) ?>">
    <div class="card text-white border-0 w-100 h-100" style="background-color: var(--primary);">
        <div class="row g-0 h-100">
            <!-- IMAGE COLUMN -->
            <div class="col-md-5 p-0">
                <!-- Square image on small devices -->
                <div class="d-block d-md-none position-relative w-100" style="padding-top: 100%;">
                    <img src="images/<?= htmlspecialchars($article["article_photo"], ENT_QUOTES, 'UTF-8') ?>" 
                         alt="Article image"
                         class="img-fluid position-absolute top-0 start-0 w-100 h-100 rounded-top"
                         style="object-fit: cover;">
                </div>

                <!-- Full height image on md and up -->
                <div class="d-none d-md-block h-100">
                    <img src="images/<?= htmlspecialchars($article["article_photo"], ENT_QUOTES, 'UTF-8') ?>" 
                         alt="Article image"
                         class="img-fluid w-100 h-100 rounded-start"
                         style="object-fit: cover;">
                </div>
            </div>

            <!-- TEXT COLUMN -->
            <div class="col-md-7 d-flex flex-column h-md-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h4 class="card-title fw-bold mb-3"><?= htmlspecialchars($article["article_title"], ENT_QUOTES, 'UTF-8') ?></h4>
                        <p class="card-text mb-auto" style="min-height:80px; font-size:14px;!important"><?= htmlspecialchars($article["article_summary"], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <time class="text-muted" datetime="<?= htmlspecialchars($article["article_date"]) ?>">
                            <?= htmlspecialchars($article["article_date"], ENT_QUOTES, 'UTF-8') ?>
                        </time>
                        <a href="article.php?user_id=<?= htmlspecialchars($user_id) ?>&id=<?= htmlspecialchars($article["article_id"], ENT_QUOTES, 'UTF-8') ?>" 
                           class="btn btn-primary">Read more</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <?php endif; endforeach; ?>
    </div>
</div>

<?php include "assets/footer.php"; ?>

<!-- Filter Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const buttons = document.querySelectorAll('[data-tag]');
    const articles = document.querySelectorAll('.blog-card');

    buttons.forEach(btn => {
        btn.addEventListener('click', function () {
            const selected = this.getAttribute('data-tag');

            buttons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            articles.forEach(article => {
                
                const tags = article.getAttribute('data-tags')?.toLowerCase().split('|') || [];
                if (selected === 'all' || tags.includes(selected)) {
                    article.style.display = 'flex';
                } else {
                    article.style.display = 'none';
                }
            });
        });
    });
});
</script>

<!-- Style active tag -->
<style>
.btn-outline-secondary.active {
    background-color: #343a40;
    color: white;
    border-color: #343a40;
}
</style>
</body>
</html>
