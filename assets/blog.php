<div class="row g-4 p-md-5 p-3" id="blog">

<?php 
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $user_id;

// TEMPORARY: FIX DATABASE QUERIES!
$stmt_blog = $db->query("SELECT * FROM `blogarticles` WHERE `user_id` = $user_id AND `article_status` = 'published' ORDER BY `article_date` DESC"); 
$results_blog["blogarticles"] = $stmt_blog->fetchAll(PDO::FETCH_ASSOC);

foreach ($results_blog["blogarticles"] as $article) :
    if ($article["article_status"] == "published"):
        $tags="";
        $dataTags="";
        if(!empty($article["article_tags"])){
            $tags = explode(',', $article['article_tags']);
            $dataTags = implode(' ', array_map('strtolower', array_map('trim', $tags)));
        }
?>

   <div class="col-sm-6 blog-card d-flex" data-tags="<?= htmlspecialchars($dataTags) ?>">
    <div class="card text-white border-0 w-100 h-100" style="background-color: var(--primary);">
        <div class="row g-0 h-100">
            <!-- IMAGE COLUMN -->
            <div class="col-md-5 p-0">
                <!-- Small screens: square image -->
                <div class="d-block d-md-none position-relative w-100" style="padding-top: 100%;">
                    <img class="img-fluid position-absolute top-0 start-0 w-100 h-100 rounded-top"
                         style="object-fit: cover;"
                         src="images/<?= htmlspecialchars($article["article_photo"], ENT_QUOTES, 'UTF-8') ?>" 
                         alt="Photo of article showing an illustration that describes the topic.">
                </div>

                <!-- Medium and up: full-height image -->
                <div class="d-none d-md-block h-100">
                    <img class="img-fluid w-100 h-100 rounded-start"
                         style="object-fit: cover;"
                         src="images/<?= htmlspecialchars($article["article_photo"], ENT_QUOTES, 'UTF-8') ?>" 
                         alt="Photo of article showing an illustration that describes the topic.">
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



<?php 
    endif;
endforeach; 
?>

</div>
