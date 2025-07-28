<?php
include "head.php";
// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
     header("Location: ../assets/login.php?redirect_to=" . urlencode($_SERVER['REQUEST_URI']));
     exit();
} else {

     if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['article_id'])) {
          // Get form input
          $article_id = $_POST['article_id'];

          // Prepare the SQL query using placeholders for the parameters
          $sql = "DELETE FROM blogarticles WHERE `blogarticles`.`article_id` = :article_id";

          $stmt = $db->prepare($sql);

          // Bind value to the placeholder
          $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);

          // Execute the query
          if ($stmt->execute()) {
               echo "Article deleted successfully\n";
               header("Location: ../assets/delete_article.php");
               exit();
          } else {
               echo "Error deleting article.";
          }
     }
}
$stmt_blog = $db->prepare("SELECT * FROM `blogarticles` WHERE `user_id` = :user_id ORDER BY `article_date` DESC");// NOTA: no usar declaraciones preparadas para nombres de tablas
$stmt_blog->bindValue(":user_id",$_SESSION['id'], PDO::PARAM_INT);
$stmt_blog->execute();
$results["blogarticles"]  = $stmt_blog->fetchAll(PDO::FETCH_ASSOC);
?>
     <script src="https://cdn.tiny.cloud/1/rjzppgs8otrsz9tmfcrgp004nml1z0mz636x1huk653hubwt/tinymce/7/tinymce.min.js"
          referrerpolicy="origin"></script>
     </head>

     <body>
         <?php
         include("nav.php");
         ?>
          <div class="container mt-2">
    <!-- Page Header -->
    <div class="text-center h1 py-4 mb-4" style="border-bottom:2px solid var(--primary);color:var(--primary);">
        Manage Articles
    </div>


    <div class="row">
        <?php if (!empty($results["blogarticles"])): ?>
            <?php foreach ($results["blogarticles"] as $article): ?>
                <!-- Your existing article card code goes here -->
                <div class="col-lg-4 col-xl-4 mb-4">
                    <div class="card border-0 h-100 shadow d-flex flex-column align-items-center justify-content-between">
                        <div class="row g-0 h-100">
                            <!-- Article Image -->
                            <div class="col-md-5">
                                <img class="img-fluid rounded-start w-100" 
                                     style="aspect-ratio: 1/1; object-fit: cover;"
                                     src="images/<?= htmlspecialchars($article["article_photo"], ENT_QUOTES, 'UTF-8') ?>" 
                                     alt="Illustration of <?= htmlspecialchars($article["article_title"], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            
                            <!-- Article Content -->
                            <div class="col-md-7">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title fw-bold" style="color:var(--primary);"><?= htmlspecialchars($article["article_title"], ENT_QUOTES, 'UTF-8') ?></h5>
    
                                </div>
                            </div>
                        </div>
                        <div class="row px-3 mb-3 ">
                            <p class="card-text text-muted my-2" ><?= htmlspecialchars($article["article_summary"], ENT_QUOTES, 'UTF-8') ?></p>
                            <div>
                                <!-- Article Meta Info -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="bi bi-eye"></i> <?= htmlspecialchars($article["views"], ENT_QUOTES, 'UTF-8') ?> Views
                                        </small>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($article["article_status"], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> <?= htmlspecialchars($article["article_date"], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    </div>
                            <!-- Edit & Delete Buttons -->
                                    <div class="d-flex gap-2 mt-3">
                                        <div class="w-100">
                                            <a href="/assets/form.php?edit=<?= htmlspecialchars($article["article_id"], ENT_QUOTES, 'UTF-8') ?>" 
                                           class="btn btn-outline-success w-100">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                            
                                        </div>
                                    
                                        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" 
                                              class="d-inline w-100" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="article_id" value="<?= htmlspecialchars($article["article_id"], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-outline-danger w-100">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- Preview Button -->
                                    <div class="mt-2">
                                        <a href="/article.php?user_id=<?= htmlspecialchars($article["user_id"], ENT_QUOTES, 'UTF-8') ?>&id=<?= htmlspecialchars($article["article_id"], ENT_QUOTES, 'UTF-8') ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-primary w-100">
                                            <i class="bi bi-eye-fill"></i> Preview
                                        </a>
                                    </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center">
                <div class="alert alert-info p-4 rounded-3 shadow-sm">
                    <h5 class="mb-2">You haven't created any articles yet</h5>
                    <p>Start sharing your ideas with the world!</p>
                    <a href="https://qrsume.com/assets/form.php" class="btn btn-primary">Create Your First Article</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
        </div>
    </div>

          <?php
          include("footer.php");
          ?>
          <!-- Include Bootstrap JS (Optional for form validation) -->
          <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

          <!-- JavaScript for confirmation -->
          <script>
               function confirmDelete() {
                    return confirm("Are you sure you want to delete this article?");
               }
          </script>
     </body>

     </html>