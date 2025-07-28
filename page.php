<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('./assets/head.php');

$page = isset($_GET['web']) ? $_GET['web'] : 'aboutus';

$stmt = $db->prepare("SELECT page_title, page_content FROM static_pages WHERE page_slug = :slug");
$stmt->execute([':slug' => $page]);



if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $title = $row['page_title'];
    $content = $row['page_content'];
} else {
    $title = "Page Not Found";
    $content = "<p>This page does not exist yet.</p>";
}
?>


<body class="bg-light d-flex flex-column align-items-center justify-content-between" style="min-height:100vh">
    <?php include("assets/nav2.php");?>
    <div class="container py-5" >
        <div><?= $content ?></div>
    </div>
    <?php include("assets/footer.php");?>
</body>
</html>
