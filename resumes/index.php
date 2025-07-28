<?php
require "../assets/head.php"; // assumes PDO connection in $db
$headTitle = "All Resumes";

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['id'] ?? null;
$username = $_SESSION['username'] ?? 'User';
if (!$userId) die('Error: User ID not set in session.');

try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("SELECT * FROM resumes WHERE user_id = :uid ORDER BY updated_at DESC");
    $stmt->execute([':uid' => $userId]);
    $resumes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error loading resumes: ' . $e->getMessage());
}
?>

<style>
    .custom-card {
        border-radius: 20px;
        box-shadow: 2px 2px 8px 0px rgba(0,0,0,0.10);
    }
    .bottom-round {
        border-radius: 0 0 20px 20px;
    }
</style>

<body class="bg-light d-flex flex-column" style="min-height:100vh;">
<?php if (file_exists('../assets/nav_dashboard.php')) include '../assets/nav_dashboard.php'; ?>

<div class="container my-2">
    <h2 class="fw-bold text-center mb-4">Your Resumes</h2>

    <?php if (empty($resumes)): ?>
        <div class="alert alert-info text-center">
            You haven’t created any resumes yet.
            <a href="/create_resume/form_with_login.php" class="fw-bold text-decoration-none">Create one now</a>.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($resumes as $resume): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 custom-card">
                        <div class="card-body p-4">
                            <h5 class="text-dark mb-1"><?= htmlspecialchars($resume['title']) ?></h5>
                            <?php if (!empty($resume['description'])): ?>
                                <p class="text-muted small mb-2"><?= nl2br(htmlspecialchars($resume['description'])) ?></p>
                            <?php endif; ?>
                            <ul class="list-unstyled small">
                                <li><strong>Version:</strong> <?= $resume['version'] ?></li>
                                <li><strong>Last used:</strong> <?= $resume['last_used'] ?? 'Never' ?></li>
                                <li><strong>Updated:</strong> <?= date('M d, Y', strtotime($resume['updated_at'])) ?></li>
                                <?php if (!empty($resume['tags'])): ?>
                                    <li><strong>Tags:</strong>
                                        <?php foreach (explode(',', $resume['tags']) as $tag): ?>
                                            <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars(trim($tag)) ?></span>
                                        <?php endforeach; ?>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="d-flex justify-content-center gap-3 p-3 bg-light bottom-round">
                            <a href="/preview.php?resume=<?= $resume['resume_id'] ?>" class="text-decoration-none fw-bold text-secondary">
                                <i class="bi bi-eye"></i> Preview
                            </a>
                            <div class="vr"></div>
                            <a href="/edit_resume.php?id=<?= $resume['resume_id'] ?>" class="text-decoration-none text-secondary">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (file_exists('../assets/footer.php')) include '../assets/footer.php'; ?>
</body>
</html>
