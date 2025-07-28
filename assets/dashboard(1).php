<?php
$errors=true;
$headTitle = "Dashboard";
// Include head.php with file existence check
if (!file_exists('head.php')) {
    die('Error: head.php not found.');
}
include 'head.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Validate session variables
$userId = $_SESSION['id'] ?? null;
$username = $_SESSION['username'] ?? 'User';
$privilege = $_SESSION['privilege'] ?? 'user';

if (!$userId) {
    die('Error: User ID not set in session.');
}

// Initialize database (assuming $db is a PDO instance from head.php)
try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection error: ' . $e->getMessage());
}

// 2) Load today's stats from page_stats
$todayStats = ['views' => 0];
$stmt = $db->prepare("
    SELECT views, downloads, qr_scans
    FROM page_stats
    WHERE user_id = :uid AND stat_date = CURDATE()
");
$stmt->execute([':uid' => $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && $row['views'] !== null) {
    $stats =  $row;
}


// 2) Build 7-day arrays from page_stats
$labels = $views = $dl = $qr = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels[] = date('D', strtotime($d));

    try {
        $stmt = $db->prepare(
            'SELECT views, downloads, qr_scans
             FROM page_stats
             WHERE user_id = :uid
             AND stat_date = :dt'
        );
        $stmt->execute([':uid' => $userId, ':dt' => $d]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $views[] = (int) ($r['views'] ?? 0);
        $dl[]    = (int) ($r['downloads'] ?? 0);
        $qr[]    = (int) ($r['qr_scans'] ?? 0);
    } catch (PDOException $e) {
        error_log('Error fetching page stats: ' . $e->getMessage());
        $views[] = 0;
        $dl[] = 0;
        $qr[] = 0;
    }
}

?>


<body class="bg-light d-flex flex-column align-items-center justify-content-between" style="min-height:100vh;">
    <!-- Loading overlay -->
<!-- Loading overlay -->
<div id="loading-overlay" style="
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background-color: white;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-family: 'Segoe UI', sans-serif;
">
    <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Recollecting all your site data...</span>
    </div>
    <p id="loading-text" class="text-muted fs-5"></p>
</div>

<?php
// Include navigation with file existence check
if (file_exists('nav_dashboard.php')) {
    include 'nav_dashboard.php';
} else {
    echo '<p class="text-danger text-center">Error: Navigation file not found.</p>';
}
?>

<div class="container my-5 bg-light">
    <h1 class="fw-bold text-center mb-3">Hi, <?= htmlspecialchars($username) ?>! Get ready to land your dream job</h1>

    <div class="row g-4">
        <!-- Admin Tools -->
        <?php if ($privilege === 'admin'): ?>
        <div class="col-12">
            <div class="card bg-dark text-white shadow-sm">
                <div class="card-body">
                    <h4 class="card-title text-center mb-3">Admin Tools</h4>
                    <div class="row justify-content-center">
                        <div class="col-md-3 mb-2">
                            <a href="admin/admin_users.php" class="btn btn-danger w-100">User Management</a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="admin/admin_pages.php" class="btn btn-secondary w-100">Static Pages</a>
                        </div>
                        <?php if ($userId == 1): ?>
                        <div class="col-md-3 mb-2">
                            <a href="admin/view_tables.php" class="btn btn-primary w-100">View Database</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="container-fluid py-4">
            <!-- Row 1: Stats / Preview / Link -->
            <div class="row g-4 mb-4">
                <!-- 1. Quick Stats -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0 text-muted">Today’s Stats</h6>
                        </div>
                        <div class="card-body d-flex justify-content-around align-items-center">
                            <div class="text-center">
                                <h4 class="mb-1"><?= number_format($stats['views']) ?></h4>
                                <small class="text-muted">Views</small>
                            </div>
                            <div class="text-center">
                                <h4 class="mb-1"><?= number_format($stats['downloads']) ?></h4>
                                <small class="text-muted">Downloads</small>
                            </div>
                            <div class="text-center">
                                <h4 class="mb-1"><?= number_format($stats['qr_scans']) ?></h4>
                                <small class="text-muted">QR scans</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Resume Preview -->
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow rounded-4" style="background-color:#e0e7ff;">
                        <div class="card-body d-flex flex-column justify-content-between p-0">
                            
                            <div class="text-center px-4 pt-4">
                                
                                <h6 class="text-muted">Resume Preview</h6>
                                <img src="https://qrsume.com/images/resume-preview_barroso.webp"
                                     class="img-fluid w-50 rounded"
                                     alt="Resume preview for <?= htmlspecialchars($username) ?>"
                                     style="max-height:150px;">
                            </div>
                            <div class="mt-auto d-flex justify-content-center gap-3 p-3 bg-light rounded-bottom">
                                <a href="/preview.php"
                                   class="text-secondary text-decoration-none d-flex align-items-center gap-1">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <div class="vr"></div>
                                <a href="/<?= htmlspecialchars($username) ?>"
                                   class="text-secondary text-decoration-none d-flex align-items-center gap-1">
                                    <i class="bi bi-globe"></i> Online
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Online Resume Link -->
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow rounded-4">
                        <div class="card-body text-center p-4">
                            <h6 class="text-muted">Your Public URL</h6>
                            <a href="<?= htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . '/' . $username) ?>"
                               class="d-block fs-5">
                                <?= htmlspecialchars($_SERVER['HTTP_HOST'] . '/' . $username) ?>
                            </a>
                            <button class="btn btn-outline-primary btn-sm mt-3" onclick="
                                navigator.clipboard.writeText(location.origin + '/<?= htmlspecialchars($username) ?>');
                                this.textContent='Copied!';
                                setTimeout(() => this.textContent='Copy Link', 2000);
                            ">
                                Copy Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Tools -->
            <div class="row g-4">
                <!-- 4. Profile & Settings -->
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow rounded-4" style="background-color:#e0e7ff;">
                        <div class="card-body d-flex flex-column justify-content-between text-center p-4">
                            <span class="badge bg-info mb-2">ESSENTIAL</span>
                            <h5 class="fw-bold">Customize Your Site</h5>
                            <p class="text-muted">Edit your online resume and settings in one click.</p>
                            <div class="mt-auto d-flex justify-content-center gap-3 bg-light rounded-3 p-3">
                                <a href="https://qrsume.com/create_resume/form_with_login.php" class="text-secondary text-decoration-none">
                                    <i class="bi bi-pencil-square"></i> Edit online resume
                                </a>
                                <div class="vr"></div>
                                <a href="/assets/user_settings.php" class="text-secondary text-decoration-none">
                                    <i class="bi bi-gear"></i> Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Blog Management -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-dark text-white text-center">
                            <h5 class="mb-0">Blog Management</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="d-grid gap-2">
                                <a href="/assets/form.php" class="btn btn-primary">+ Create Article</a>
                                <a href="/assets/delete_article.php" class="btn btn-warning">Modify Article</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Analytics -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-dark text-white text-center">
                            <h5 class="mb-0">Your Statistics</h5>
                        </div>
                        <div class="card-body p-3">
                            <canvas id="viewsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer with file existence check
if (file_exists('footer.php')) {
    include 'footer.php';
} else {
    echo '<p class="text-danger text-center">Error: Footer file not found.</p>';
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const phrases = [
        "Recollecting all your site data...",
        "You’re making the web cooler with this amazing profile.",
        "Polishing up your stats for maximum shine.",
        "Loading your digital awesomeness...",
        "Just a sec! We're boosting your profile magic.",
        "Crunching numbers like a data wizard...",
        "Making your page sparkle ✨",
        "Summoning your resume greatness..."
    ];

    // Select a random phrase and display it
    document.getElementById('loading-text').textContent =
        phrases[Math.floor(Math.random() * phrases.length)];

    // Remove overlay on load
    window.addEventListener('load', function () {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.transition = 'opacity 0.5s ease';
            overlay.style.opacity = 0;
            setTimeout(() => overlay.remove(), 500);
        }
    });

// Validate data before rendering chart
const labels = <?= json_encode($labels) ?> || [];
const viewsData = <?= json_encode($views) ?> || [];
const downloadsData = <?= json_encode($dl) ?> || [];
const qrData = <?= json_encode($qr) ?> || [];

const ctx = document.getElementById('viewsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Views',
                data: viewsData,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.2)',
                tension: 0.4
            },
            {
                label: 'Downloads',
                data: downloadsData,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.2)',
                tension: 0.4
            },
            {
                label: 'QR scans',
                data: qrData,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                tension: 0.4
            }
        ]
    },
    options: {
        scales: {
            y: { beginAtZero: true }
        },
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

</body>
</html>