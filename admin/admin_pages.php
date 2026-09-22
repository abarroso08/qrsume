<?php
if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
include '../assets/db.php';

if (!isset($_SESSION['privilege']) || $_SESSION['privilege'] !== 'admin') {
    die("Access denied. You must be an admin.");
}

$pages = $db->query("SELECT page_slug, page_title FROM static_pages ORDER BY page_title ASC")->fetchAll(PDO::FETCH_ASSOC);

$edit_slug = $_GET['edit'] ?? null;
$edit_data = ['page_slug' => '', 'page_title' => '', 'page_content' => ''];

if ($edit_slug) {
    $stmt = $db->prepare("SELECT * FROM static_pages WHERE page_slug = :slug");
    $stmt->execute([':slug' => $edit_slug]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: $edit_data;
}

$message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $slug = trim($_POST['page_slug']);
    $title = trim($_POST['page_title']);
    $content = trim($_POST['page_content']);

    $stmt = $db->prepare("INSERT INTO static_pages (page_slug, page_title, page_content)
                          VALUES (:slug, :title, :content)
                          ON DUPLICATE KEY UPDATE page_title = :title, page_content = :content");

    if ($stmt->execute([
        ':slug' => $slug,
        ':title' => $title,
        ':content' => $content
    ])) {
        $saved_link = "https://qrsume.com/page.php?web=" . urlencode($slug);
        $message = "Page saved successfully! <br><a href='$saved_link'>$saved_link</a>";
        $edit_slug = $slug;
    } else {
        $message = "❌ Error saving page.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manage Static Pages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        .full-height {
            height: 100vh;
            overflow-y: auto;
        }
        .no-padding {
            padding: 0 !important;
        }
        .form-control, textarea {
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid no-padding">
    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <div class="row g-0">
        <!-- Left: Form -->
        <div class="col-md-6 border-end full-height bg-white">
            <form method="POST" class="p-3">
                <div class="mb-3">
                    <label for="existing" class="form-label">Edit existing page</label>
                    <select id="existing" class="form-select" onchange="location.href='?edit=' + this.value">
                        <option value="">-- Select a page --</option>
                        <?php foreach ($pages as $p): ?>
                            <option value="<?= htmlspecialchars($p['page_slug']) ?>" <?= $edit_slug === $p['page_slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['page_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="page_slug" class="form-label">Page Identifier</label>
                    <input type="text" class="form-control" name="page_slug" id="page_slug" required value="<?= htmlspecialchars($edit_data['page_slug']) ?>">
                </div>

                <div class="mb-3">
                    <label for="page_title" class="form-label">Page Title</label>
                    <input type="text" class="form-control" name="page_title" id="page_title" required value="<?= htmlspecialchars($edit_data['page_title']) ?>">
                </div>

                <div class="mb-3">
                    <label for="page_content" class="form-label">Page Content (HTML allowed)</label>
                    <textarea
                        class="form-control"
                        name="page_content"
                        id="page_content"
                        oninput="updatePreview(); autoResize(this);"
                        style="overflow:hidden; resize:none;"
                        required
                    ><?= htmlspecialchars($edit_data['page_content']) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save Page</button>
            </form>
        </div>

        <!-- Right: Preview -->
        <div class="col-md-6 full-height">
            <div class="p-0 h-100 d-flex flex-column">
                <div id="preview" class="border rounded p-3 bg-white flex-grow-1 overflow-auto">
                    
                    <?= $edit_data['page_content'] ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
function updatePreview() {
    const content = document.getElementById("page_content").value;
    document.getElementById("preview").innerHTML = content;
}

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = (textarea.scrollHeight) + 'px';
}

window.addEventListener('DOMContentLoaded', () => {
    const ta = document.getElementById("page_content");
    if (ta) {
        autoResize(ta);
        updatePreview();
    }
});
</script>

</body>
</html>
