<?php
include "head.php";

if (isset($_GET["edit"])) {
     $id = $_GET["edit"];
     $id = strval(intval($_GET["edit"]));
     $stmt2 = $db->prepare("SELECT * FROM `blogarticles` WHERE `user_id` = :user_id AND `article_id` = $id "); // NOTA: no usar declaraciones preparadas para nombres de tablas
     $stmt2->bindValue(":user_id",$_SESSION['id'],PDO::PARAM_INT);
     $stmt2->execute();
     $article  = $stmt2->fetchAll(PDO::FETCH_ASSOC);
     if ($article === array()) {
          header('Location: error.php');
          exit();
     } else {

          $article = $article[0];
     }
}

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
     header("Location: ../assets/login.php?redirect_to=" . urlencode($_SERVER['REQUEST_URI']));
     exit();
} else {
    $sql = "SELECT photo_url FROM photos WHERE user_id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':user_id', $_SESSION['id'], PDO::PARAM_INT);
    $stmt->execute();
    $photos = $stmt->fetchAll(PDO::FETCH_COLUMN); // Fetch only the photo_url column

?>
     <script src="https://cdn.tiny.cloud/1/rjzppgs8otrsz9tmfcrgp004nml1z0mz636x1huk653hubwt/tinymce/7/tinymce.min.js"
          referrerpolicy="origin"></script>
    <style>
      #loader-wrapper {
        position: fixed;
        width: 100%;
        height: 100%;
        background: #ffffff;
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity 0.5s ease;
      }
    
      #loader-wrapper.fade-out {
        opacity: 0;
        pointer-events: none;
      }
    </style>


     <body>
         <?php include("nav.php");?>
         <!-- Loader -->
         <div id="loader-wrapper" style="position: fixed; width: 100%; height: 100%; background: #ffffff; z-index: 9999; display: flex; flex-direction: column; justify-content: center; align        -items: center;">
              <div class="spinner-border text-primary mb-3" role="status" style="width: 4rem; height: 4rem;">
                   <span class="visually-hidden">Loading...</span>
              </div>
              <p class="text-center text-muted fs-5">Loading your project,<br>it may take a few seconds if your project is big.</p>
         </div>

          <!-- Include Bootstrap CSS -->
          <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">

          <body>
               <div class="container py-4">
                    <div class="card shadow-lg border-0">
                        <div class="card-header text-dark text-center py-3">
                            <h2 class="mb-0">Create/Edit Article</h2>
                        </div>
                        <div class="card-body">
                            
                            
                            <div class="container my-5">
                                 <h5 class="text-center">Upload Image</h5>
                                 <!-- Display Error or Success Messages -->
                                 <?php if (isset($_GET['errors'])): ?>
                                      <div class="alert alert-danger text-center" role="alert">
                                           <?= htmlspecialchars($_GET['errors']) ?>
                                      </div>
                                 <?php endif; ?>
             
                                 <?php if (isset($_GET['success'])): ?>
                                      <div class="alert alert-success text-center" role="alert">
                                           <?= htmlspecialchars($_GET['success']) ?>
                                      </div>
                                 <?php endif; ?>
                                 <form action="../assets/upload_photo.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                     <input type="hidden" name="source" value="create_article">
                                      <div class="mb-3">
                                           <label for="article_photo" class="form-label">Select Image to Upload:</label>
                                           <input class="form-control" type="file" name="article_photo" id="article_photo" accept="image/*" required>
                                           <div class="invalid-feedback">Please select an image to upload.</div>
                                      </div>
                                      <button type="submit" class="btn btn-primary">Upload Image</button>
                                 </form>
                            </div>
                            
                            
                            
                            <form action="https://qrsume.com/assets/create_article.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                
                                <!-- Hidden article_id field when in edit mode -->
                                <?php if (isset($_GET['edit'])): ?>
                                    <input type="hidden" name="article_id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                
                                <div class="row">
                                    <!-- Article Title -->
                                    <div class="col-md-6 mb-3">
                                        <label for="article_title" class="form-label fw-semibold">Article Title:</label>
                                        <input type="text" class="form-control shadow-sm" name="article_title" id="article_title" 
                                               value="<?= isset($article["article_title"]) ? htmlspecialchars($article["article_title"], ENT_QUOTES, 'UTF-8') : "" ?>" required>
                                        <div class="invalid-feedback">Please enter the article title.</div>
                                    </div>
                
                                   <!-- Article Date -->
                                    <div class="col-md-6 mb-3">
                                        <label for="article_date" class="form-label fw-semibold">Article Date:</label>
                                        <?php
                                            $defaultDate = !empty($article["article_date"]) 
                                                ? htmlspecialchars($article["article_date"], ENT_QUOTES, 'UTF-8') 
                                                : date('Y-m-d');
                                        ?>
                                        <input type="date" class="form-control shadow-sm" name="article_date" id="article_date" 
                                               value="<?= $defaultDate ?>" required>
                                        <div class="invalid-feedback">Please choose an article date.</div>
                                    </div>

                                </div>
                
                                
                
                                <div class="row">
                                    <!-- Article Tags -->
                                    <div class="col-md-6 mb-3">
                                        <label for="article_tags" class="form-label fw-semibold">Tags (comma separated):</label>
                                        <input type="text" class="form-control shadow-sm" name="article_tags" 
                                               value="<?= htmlspecialchars($article['article_tags'] ?? '', ENT_QUOTES, 'UTF-8') ?>" id="article_tags">
                                    </div>
                
                                    <!-- Article Summary -->
                                    <div class="col-md-6 mb-3">
                                        <label for="article_summary" class="form-label fw-semibold">Summary:</label>
                                        <textarea class="form-control shadow-sm" name="article_summary" id="article_summary" rows="2"><?= htmlspecialchars($article["article_summary"] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                </div>
                
                                <div class="row">
                                    <!-- Article Status -->
                                    <div class="col-md-6 mb-3">
                                        <label for="article_status" class="form-label fw-semibold">Article Status:</label>
                                        <select class="form-select shadow-sm" name="article_status" id="article_status" required>
                                            <option value="draft" <?= ($article["article_status"] ?? '') == "draft" ? 'selected' : '' ?>>Draft</option>
                                            <option value="published" <?= ($article["article_status"] ?? '') == "published" ? 'selected' : '' ?>>Published</option>
                                        </select>
                                        <div class="invalid-feedback">Please select the article status.</div>
                                    </div>
                
                                    <!-- Article Image -->
                                    <div class="col-md-6 mb-3">
                                        <label for="article_photo" class="form-label fw-semibold">Featured Image:</label>
                                        <select class="form-control shadow-sm" name="article_photo" id="article_photo" required>
                                            <?php
                                                $selectedPhoto = $article["article_photo"] ?? 'default.webp';
                                            ?>
                                            <option value="default.webp" <?= $selectedPhoto === 'default.webp' ? 'selected' : '' ?>>
                                                default.webp
                                            </option>
                                    
                                            <?php foreach ($photos as $photo): ?>
                                                <option value="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" 
                                                        <?= $selectedPhoto === $photo ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select an article front image.</div>
                                    </div>

                                    
                                    <!-- Article Content -->
                                <div class="mb-3 mx-0">
                                    <label for="article_content" class="form-label fw-semibold">Article Content:</label>
                                    <textarea class="form-control shadow-sm" name="article_content" id="article_content" rows="10"><?= htmlspecialchars($article["article_content"] ?? '', ENT_QUOTES, 'UTF-8') ?>
</textarea>
                                </div>
                                </div>
                
                                <!-- Submit Button -->
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                                        <i class="bi bi-upload me-2"></i>Submit Article
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php include("footer.php");?>


               <!-- Optional: Custom Script for Bootstrap Form Validation -->
               <script>
                    // Example starter JavaScript for disabling form submissions if there are invalid fields
                    (function() {
                         'use strict'
                         var forms = document.querySelectorAll('.needs-validation')
                         Array.prototype.slice.call(forms)
                              .forEach(function(form) {
                                   form.addEventListener('submit', function(event) {
                                        if (!form.checkValidity()) {
                                             event.preventDefault()
                                             event.stopPropagation()
                                        }
                                        form.classList.add('was-validated')
                                   }, false)
                              })
                    })()
               </script>
          </body>


          <!-- Include TinyMCE -->
<script>
 tinymce.init({
  selector: 'textarea#article_content',
  height: 400,
  plugins: [
    'fullscreen',
    'anchor', 'autolink', 'charmap', 'codesample', 'emoticons',
    'image', 'link', 'lists', 'media', 'searchreplace',
    'table', 'visualblocks', 'wordcount', 'paste','preview'
  ],
  toolbar: 'fullscreen preview | undo redo | bold italic underline strikethrough subscript superscript | blocks fontfamily fontsize | link image code table | align lineheight checklist numlist  bullist |  indent outdent | anchor | codesample | emoticons charmap | removeformat'
});

    window.addEventListener('load', function () {
        const loader = document.getElementById('loader-wrapper');
        if (loader) {
            loader.classList.add('fade-out');
            setTimeout(() => loader.style.display = 'none', 500);
        }
    });



</script>


          <?php if (isset($_GET['success'])): ?>
    <script>
        window.addEventListener('load', function () {
                //history.back(); // You can adjust delay
        });
    </script>
<?php endif; ?>

          
     </body>

     </html>
<?php
}
?>