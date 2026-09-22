<?php


$headTitle = "Dashboard";
// Include head.php with file existence check
if (!file_exists('head.php')) {
    die('Error: head.php not found.');
}


include "head.php";

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
$stats = ['views' => 0, 'downloads' => 0, 'qr_scans' => 0];
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


// 3) Build 7-day arrays from page_stats
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

$total_views = $total_downloads = $total_qrscans = 0;

try {
    $stmt = $db->prepare("SELECT user_id, SUM(views) as total_views, SUM(downloads) as total_downloads, SUM(qr_scans) as total_scans FROM `page_stats` WHERE `user_id` = :uid");
    $stmt->execute([':uid' => $userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $total_views = (int)$row['total_views'];
        $total_downloads = (int)$row['total_downloads'];
        $total_qrscans = (int)$row['total_scans'];
    }

} catch (PDOException $e) {
    error_log('Error fetching total page stats: ' . $e->getMessage());
}



?>
<style>
  .custom-card {
  border-radius: 20px; /* Big smooth rounded corners */
  box-shadow: 2px 2px 8px 0px rgba(0,0,0,0.10);
}
  .custom-stats-card{
      border-radius: 0 0 20px 20px; /* Big smooth rounded corners */
  }
  .custom-stats-card-shadow{
      box-shadow: 2px 2px 8px 0px rgba(0,0,0,0.10);
  }
  .custom-stats-card-top-left{
      border-radius:  20px 0 0 0; /* Big smooth rounded corners */
  }
  
  .custom-stats-card-top-right{
      border-radius:   0 20px 0 0; /* Big smooth rounded corners */
  }
  .custom-stats-button-muted:hover{
      background-color: #e0e7ff;
      cursor:pointer;
  }
  
 
.bottom-round{
     border-radius: 0 0 20px 20px;
}


</style>

<body class="bg-light l d-flex flex-column align-items-center justify-content-between" style="min-height:100vh;">
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
            <div class="card bg-dark text-white shadow">
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
    <!-- Row 1: Stats / Analytics / Resume -->
    <div class="row g-4 mb-4">

        <!-- 1. Quick Stats -->
        <div class="col-md-4">
            <div class="d-flex flex-column h-100 custom-card" style="background-color:transparent;">
                <div class=" d-flex ">
                    <h5 id="today-stats-button" class="h-100 d-flex align-items-center custom-stats-card-top-left mb-0 text-dark text-center w-50 px-4 py-2 bg-white">Today’s Stats</h5>
                    <h5 id="total-stats-button" class="h-100 d-flex align-items-center custom-stats-card-top-right mb-0 custom-stats-button-muted text-dark text-center w-50 px-4 py-2 bg-muted">Total Stats</h5>
                </div>
                <div class="bg-white card-body d-flex border-0 custom-stats-card justify-content-around align-items-center p-4">
                    <div class="text-center">
                        <h4 id="views" class="mb-1"><?= number_format($stats['views']) ?></h4>
                        <small class="text-muted">Views</small>
                    </div>
                    <div  class="text-center">
                        <h4 id="downloads" class="mb-1"><?= number_format($stats['downloads']) ?></h4>
                        <small class="text-muted">Downloads</small>
                    </div>
                    <div class="text-center">
                        <h4 id="qr_scans" class="mb-1"><?= number_format($stats['qr_scans']) ?></h4>
                        <small class="text-muted">QR scans</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Your Statistics (Analytics) -->
        <div class="col-md-4">
            <div class="card h-100 border-0 custom-card rounded-4">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div class="text-center px-4 pt-4">
                        <h5 class="text-dark">Your Statistics</h5>
                    </div>
                    <div class="px-3 pb-2">
                        <canvas id="viewsChart" style="height: 150px; width: 100%;"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- 6. Your Public URL -->
<div class="col-md-4">
    <div class="card h-100 border-0 custom-card rounded-4">
        <div class="card-body text-center p-4">
            <h5 class="text-dark">Your Public URL</h5>
            <a href="<?= htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . '/' . $username) ?>"
               class="d-block fs-5">
                <?= htmlspecialchars($_SERVER['HTTP_HOST'] . '/' . $username) ?>
            </a>

            <!-- Copy Link Button -->
            <button class="btn btn-outline-primary btn-sm mt-3" onclick="
                navigator.clipboard.writeText(location.origin + '/<?= htmlspecialchars($username) ?>');
                this.textContent='Copied!';
                setTimeout(() => this.textContent='Copy Link', 2000);
            ">
                Copy Link
            </button>

            <!-- LinkedIn Share Button -->
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/' . $username) ?>"
               class="btn btn-outline-secondary btn-sm mt-3"
               target="_blank" rel="noopener noreferrer">
                <i class="bi bi-linkedin me-1"></i> Share on LinkedIn
            </a>
        </div>
    </div>
</div>



    </div>

    <!-- Row 2: Customize / Blog Management / Public URL -->
    <div class="row g-4">

        <!-- 4. Customize your site -->
        <div class="col-md-4">
            <div class="card h-100 border-0 custom-card rounded-4" style="background-color:#e0e7ff;">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div class="text-center px-4 pt-4 d-flex flex-column justify-content-between align-items-center">
                        <h5 class="text-dark fw-bold">Customize your site</h5>
                        <div class="d-flex justify-content-around w-100">
                            <i class="bi bi-person-gear" style="font-size:46px; color:#4f46e5;"></i>
                        </div>
                        <p class="text-muted">Edit your online resume and settings easily.</p>
                    </div>
                    <div class="mt-auto d-flex justify-content-center gap-3 p-3 bg-light bottom-round">
                        <a href="https://qrsume.com/create_resume/form_with_login.php"
                           class="text-decoration-none d-flex align-items-center gap-1 fw-bold"
                           style="color: #4f46e5;">
                            <i class="bi bi-pencil-square"></i> Edit Resume
                        </a>
                        <div class="vr"></div>
                        <a href="/assets/user_settings.php"
                           class="text-secondary text-decoration-none d-flex align-items-center gap-1">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
                <!-- 3. Resume Preview -->
        <div class="col-md-4">
            <div class="card h-100 border-0 custom-card rounded-4" style="background-color:#e0e7ff;">
                <div class="card-body h-100 d-flex flex-column justify-content-between p-0">
                    <div class="text-center h-100" style="padding">
                        <h5 class="text-dark" style="padding-top:1.5rem; padding-bottom:1rem;">Resume Preview</h5>
                        <img src="https://qrsume.com/images/resume-preview_barroso.webp"
                             class="img-fluid rounded"
                             alt="Resume preview for <?= htmlspecialchars($username) ?>"
                             style="width:75%">
                    </div>
                    <div class="mt-auto d-flex flex-column align-items-center gap-2 p-3 bg-light bottom-round">
                        <a href="/preview.php" class="btn fw-bold text-white w-100 d-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #4f46e5, #6366f1); border-radius: 12px;">
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                                Preview &amp; Download PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>

<!-- 5. Blog Manager -->
<div class="col-md-4">
    <div class="card h-100 border-0 custom-card">
        <div class="card-body d-flex flex-column justify-content-between p-0">
            
            <!-- Top Text -->
            <div class="text-center px-4 pt-4">
                <h5 class="text-dark mb-1">Blog Manager</h5>
                <p class="text-muted small">Manage your content easily.</p>
            </div>

            <!-- Buttons -->
            <div class="mt-auto d-flex flex-column align-items-center gap-2 p-3 bg-light bottom-round">
                
                <!-- New Article Button -->
                <a href="https://qrsume.com/assets/article_form.php"
                   class="text-decoration-none d-flex align-items-center gap-1 fw-bold"
                   style="color: #4f46e5;">
                    <i class="bi bi-pencil-square"></i>
                    New Article
                </a>

                <!-- Edit Existing Link -->
                <a href="/assets/delete_article.php"
                   class="text-secondary small d-flex align-items-center gap-1 mt-2 text-decoration-none">
                    <i class="bi bi-tools"></i>
                    Edit Existing
                </a>

            </div>

        </div>
    </div>
</div>





    </div>
</div>

    </div>
</div>

<?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) { ?>
        <!-- Feedback Button -->
        <button id="feedbackBtn" class="btn btn-primary position-fixed bottom-0 start-0 m-3">
            Give Feedback
        </button>
        <!-- Feedback Modal -->
        <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Rate Us</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <!-- Star Rating -->
                        <div id="stars" class="mb-3">
                            <i class="fa fa-star star" data-value="1"></i>
                            <i class="fa fa-star star" data-value="2"></i>
                            <i class="fa fa-star star" data-value="3"></i>
                            <i class="fa fa-star star" data-value="4"></i>
                            <i class="fa fa-star star" data-value="5"></i>
                        </div>
                        <input type="hidden" id="rating" value="0">
                        <textarea id="comment" class="form-control" rows="3" placeholder="Tell us why you chose this rating..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button id="submitFeedback" class="btn btn-success">Submit</button>
                    </div>
                </div>
            </div>
        </div>
        

        <!-- FontAwesome for stars -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <?php } ?>

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
    const statsData = {
    today: {
      views: <?= (int)$stats['views'] ?>,
      downloads: <?= (int)$stats['downloads'] ?>,
      qr_scans: <?= (int)$stats['qr_scans'] ?>
    },
    total: {
      views: <?= $total_views ?>,
      downloads: <?= $total_downloads ?>,
      qr_scans: <?= $total_qrscans ?>
    }
  };
    
    
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

$(document).ready(function () {
      $("#total-stats-button, #today-stats-button").click( function() {
          const clickedId = $(this).attr("id");
          
          $(this).removeClass("custom-stats-button-muted bg-muted");
          $(this).addClass("bg-white");
          
          if(clickedId == "today-stats-button"){
              $("#total-stats-button").removeClass("bg-white");
              $("#total-stats-button").addClass("custom-stats-button-muted bg-muted");
          }else{
              $("#today-stats-button").removeClass("bg-white");
              $("#today-stats-button").addClass("custom-stats-button-muted bg-muted");
          }
      });
      
      $("#today-stats-button, #total-stats-button").click(function () {
        const type = $(this).attr("id") === "today-stats-button" ? "today" : "total";
      
        $("#views").text(statsData[type].views.toLocaleString());
        $("#downloads").text(statsData[type].downloads.toLocaleString());
        $("#qr_scans").text(statsData[type].qr_scans.toLocaleString());
      });

    });

</script>

</body>
</html>