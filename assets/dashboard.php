<?php

$headTitle = "Dashboard";

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

require_once __DIR__ . '/premium.php';
require_once __DIR__ . '/../PDFtoCV/lib/PdfResumeImport.php';

$userId = (int) ($_SESSION['id'] ?? 0);
$username = (string) ($_SESSION['username'] ?? 'User');
$privilege = $_SESSION['privilege'] ?? 'user';

if ($userId === 0) {
    die('Error: User ID not set in session.');
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fetchAllRows(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// -----------------------------------------------------------------------------
// Statistics: today, the last 7 days and all time (page_stats, one row per day)
// -----------------------------------------------------------------------------

$labels = $views = $downloads = $qrScans = [];
$byDate = [];
foreach (fetchAllRows($db, 'SELECT stat_date, views, downloads, qr_scans FROM page_stats WHERE user_id = ? AND stat_date >= ?',
    [$userId, date('Y-m-d', strtotime('-6 days'))]) as $row) {
    $byDate[substr((string) $row['stat_date'], 0, 10)] = $row;
}
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $labels[] = $i === 0 ? 'Today' : date('D', strtotime($day));
    $views[] = (int) ($byDate[$day]['views'] ?? 0);
    $downloads[] = (int) ($byDate[$day]['downloads'] ?? 0);
    $qrScans[] = (int) ($byDate[$day]['qr_scans'] ?? 0);
}

$totals = fetchAllRows($db, 'SELECT SUM(views) AS views, SUM(downloads) AS downloads, SUM(qr_scans) AS qr_scans FROM page_stats WHERE user_id = ?', [$userId])[0] ?? [];

$statsData = [
    'today' => ['views' => end($views), 'downloads' => end($downloads), 'qr_scans' => end($qrScans)],
    'week' => ['views' => array_sum($views), 'downloads' => array_sum($downloads), 'qr_scans' => array_sum($qrScans)],
    'total' => ['views' => (int) ($totals['views'] ?? 0), 'downloads' => (int) ($totals['downloads'] ?? 0), 'qr_scans' => (int) ($totals['qr_scans'] ?? 0)],
];

// -----------------------------------------------------------------------------
// Resume checklist: what is filled in, ignoring the examples a new account starts with
// -----------------------------------------------------------------------------

$personal = fetchAllRows($db, 'SELECT * FROM personalinfo WHERE user_id = ?', [$userId])[0] ?? [];
$contact = fetchAllRows($db, 'SELECT * FROM contactinfo WHERE user_id = ?', [$userId])[0] ?? [];
$columnValues = fn(string $sql) => array_map('trim', array_column(fetchAllRows($db, $sql, [$userId]), 'value'));
$educationTitles = $columnValues('SELECT name_of_studies AS value FROM education WHERE user_id = ?');
$experienceTitles = $columnValues('SELECT job_name AS value FROM experience WHERE user_id = ?');
$skillNames = $columnValues('SELECT aptitude AS value FROM aptitudes WHERE user_id = ?');
$languageNames = $columnValues('SELECT language AS value FROM languages WHERE user_id = ?');

$isFilled = fn(?string $value, array $examples = []) => trim((string) $value) !== '' && !in_array(trim((string) $value), $examples, true);
$hasReal = fn(array $values, array $examples) => count(array_filter($values, fn($v) => $v !== '' && !in_array($v, $examples, true))) > 0;
$isSampleSet = function (array $values, array $sample): bool {
    sort($values);
    sort($sample);
    return $values === $sample;
};

$formUrl = 'https://qrsume.com/create_resume/form_with_login.php';
$checklist = [
    ['label' => 'Name and job title', 'step' => 0, 'done' => $isFilled($personal['personal_name'] ?? '', ['Your Name'])
        && $isFilled($personal['personal_profession'] ?? '', ['Your Profession'])],
    ['label' => 'Profile photo', 'step' => 0, 'done' => $isFilled($personal['personal_photo'] ?? '', ['default.webp'])],
    ['label' => 'About you', 'step' => 0, 'done' => $isFilled($personal['personal_bio'] ?? '', ['This is a short bio about yourself.'])],
    ['label' => 'Email and phone', 'step' => 1, 'done' => $isFilled($contact['email'] ?? '', ['youremail@gmail.com'])
        && $isFilled($contact['phone_number'] ?? '', ['123-456-7890'])],
    ['label' => 'Education', 'step' => 2, 'done' => $hasReal($educationTitles, ['Your Degree'])],
    ['label' => 'Work experience', 'step' => 3, 'done' => $hasReal($experienceTitles, ['Your Job Title'])],
    ['label' => 'Skills', 'step' => 4, 'done' => $skillNames !== [] && !$isSampleSet($skillNames, ['Teamwork', 'Communication', 'Problem Solving', 'Leadership'])],
    ['label' => 'Languages', 'step' => 5, 'done' => $languageNames !== [] && !$isSampleSet($languageNames, ['English', 'Spanish', 'French'])],
];
$doneCount = count(array_filter($checklist, fn($item) => $item['done']));
$completion = (int) round($doneCount / count($checklist) * 100);
$nextItem = null;
foreach ($checklist as $item) {
    if (!$item['done']) {
        $nextItem = $item;
        break;
    }
}

$firstName = $isFilled($personal['personal_name'] ?? '', ['Your Name']) ? trim($personal['personal_name']) : $username;

// -----------------------------------------------------------------------------
// Plan, blog and public page
// -----------------------------------------------------------------------------

$isPremium = userHasPurchase($db, $userId, PdfResumeImport::PREMIUM_PRODUCT);
try {
    $imports = (new PdfResumeImport($db))->allowance($userId, $privilege === 'admin');
} catch (PDOException $exception) {
    $imports = null; // pdf_imports table not created yet
}

$articleCounts = ['published' => 0, 'draft' => 0];
foreach (fetchAllRows($db, 'SELECT article_status, COUNT(*) AS total FROM blogarticles WHERE user_id = ? GROUP BY article_status', [$userId]) as $row) {
    $status = $row['article_status'] === 'published' ? 'published' : 'draft';
    $articleCounts[$status] += (int) $row['total'];
}

$publicUrl = 'https://qrsume.com/' . rawurlencode($username);
$publicText = 'qrsume.com/' . $username;

// QR code of the public page (the same one printed on the PDF resume)
$qrSvg = '';
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('TCPDF2DBarcode')) {
        $qrSvg = (new TCPDF2DBarcode($publicUrl . '?qr_scan=true', 'QRCODE,M'))->getBarcodeSVGcode(4, 4, '#111317');
    }
}
$qrDataUri = $qrSvg !== '' ? 'data:image/svg+xml;base64,' . base64_encode($qrSvg) : '';
?>
<style>
  body {
    background: #f5f6f8;
  }

  .dash {
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
    padding: 1.5rem 16px 4rem;
  }

  .dash-card {
    height: 100%;
    padding: 1.25rem;
    border: 1px solid #e7e8ec;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 6px 20px rgba(15, 23, 42, .04);
  }

  .dash-card h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
  }

  .dash-muted {
    color: #6b7280;
    font-size: .9rem;
  }

  .dash-header h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 750;
  }

  .admin-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
    margin-bottom: 1rem;
    padding: .55rem .9rem;
    border-radius: 12px;
    background: #111827;
    color: #e5e7eb;
    font-size: .9rem;
  }

  .admin-strip a {
    padding: .2rem .65rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, .1);
    color: #fff;
    text-decoration: none;
  }

  .admin-strip a:hover {
    background: rgba(255, 255, 255, .2);
  }

  .completion-ring {
    --value: 0;
    flex: 0 0 auto;
    width: 74px;
    height: 74px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: conic-gradient(#4f46e5 calc(var(--value) * 1%), #e5e7eb 0);
  }

  .completion-ring span {
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #fff;
    font-weight: 750;
  }

  .checklist {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .35rem 1rem;
    margin: 1rem 0 0;
    padding: 0;
    list-style: none;
  }

  .checklist a {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .4rem .5rem;
    border-radius: 8px;
    color: #1f2937;
    text-decoration: none;
  }

  .checklist a:hover {
    background: #f3f4f6;
  }

  .checklist .bi-check-circle-fill {
    color: #16a34a;
  }

  .checklist .bi-circle {
    color: #9ca3af;
  }

  .checklist .is-todo {
    font-weight: 600;
  }

  .checklist .action {
    margin-left: auto;
    color: #4f46e5;
    font-size: .8rem;
    font-weight: 600;
  }

  .share-url {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .35rem .35rem .35rem .75rem;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f9fafb;
  }

  .share-url a {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 600;
  }

  .share-qr {
    width: 112px;
    height: 112px;
    padding: 6px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
  }

  .stat-tabs .btn {
    font-size: .8rem;
  }

  .stat-tile {
    padding: .75rem;
    border-radius: 12px;
    background: #f9fafb;
  }

  .stat-tile .value {
    font-size: 1.6rem;
    font-weight: 750;
    line-height: 1.1;
  }

  .stat-tile .dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    margin-right: .3rem;
    border-radius: 50%;
  }

  .chart-box {
    position: relative;
    height: 190px;
  }

  .plan-badge {
    padding: .2rem .6rem;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
  }

  .plan-badge.is-premium {
    background: #fef3c7;
    color: #92400e;
  }

  .plan-badge.is-free {
    background: #f3f4f6;
    color: #4b5563;
  }

  .plan-list {
    margin: .75rem 0 1rem;
    padding: 0;
    list-style: none;
    font-size: .9rem;
  }

  .plan-list li {
    display: flex;
    gap: .5rem;
    padding: .2rem 0;
  }

  @media (max-width: 575px) {
    .checklist {
      grid-template-columns: 1fr;
    }
  }
</style>

<body>
<?php include 'nav_dashboard.php'; ?>

<main class="dash">
  <?php if ($privilege === 'admin'): ?>
    <nav class="admin-strip" aria-label="Admin tools">
      <strong class="me-1"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Admin</strong>
      <a href="https://qrsume.com/admin/admin_users.php">Users</a>
      <a href="https://qrsume.com/admin/admin_pages.php">Static pages</a>
      <a href="https://qrsume.com/PDFtoCV/">PDF to CV</a>
      <?php if ($userId === 1): ?>
        <a href="https://qrsume.com/admin/view_tables.php">Database</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

  <!-- Greeting and main actions -->
  <header class="dash-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h1>Hi, <?= e($firstName) ?>!</h1>
      <p class="dash-muted mb-0">Here is your resume at a glance.</p>
    </div>
    <div class="d-grid d-sm-flex flex-wrap gap-2 dash-actions">
      <a class="btn btn-primary" href="<?= e($formUrl) ?>"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Edit resume</a>
      <a class="btn btn-outline-primary" href="https://qrsume.com/preview.php"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Preview &amp; download PDF</a>
      <a class="btn btn-outline-secondary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>View my page</a>
    </div>
  </header>

  <div class="row g-3">
    <!-- Resume checklist -->
    <div class="col-lg-8">
      <section class="dash-card" aria-labelledby="resumeTitle">
        <div class="d-flex align-items-center gap-3">
          <div class="completion-ring" style="--value: <?= $completion ?>;" role="img" aria-label="Resume <?= $completion ?>% complete">
            <span><?= $completion ?>%</span>
          </div>
          <div class="flex-grow-1">
            <h2 id="resumeTitle">Your resume</h2>
            <p class="dash-muted mb-0">
              <?php if ($nextItem === null): ?>
                Everything is filled in. Keep it up to date and share it.
              <?php else: ?>
                <?= $doneCount ?> of <?= count($checklist) ?> parts done. Next: <strong><?= e(mb_strtolower($nextItem['label'])) ?></strong>.
              <?php endif; ?>
            </p>
          </div>
          <?php if ($nextItem !== null): ?>
            <a class="btn btn-primary btn-sm d-none d-sm-inline-block" href="<?= e($formUrl . '?section=' . $nextItem['step']) ?>">Continue <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
          <?php endif; ?>
        </div>

        <ul class="checklist">
          <?php foreach ($checklist as $item): ?>
            <li>
              <a href="<?= e($formUrl . '?section=' . $item['step']) ?>">
                <i class="bi <?= $item['done'] ? 'bi-check-circle-fill' : 'bi-circle' ?>" aria-hidden="true"></i>
                <span class="<?= $item['done'] ? '' : 'is-todo' ?>"><?= e($item['label']) ?></span>
                <span class="visually-hidden"><?= $item['done'] ? '(done)' : '(to do)' ?></span>
                <span class="action"><?= $item['done'] ? 'Edit' : 'Add' ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($completion < 50 && $imports !== null && $imports['remaining'] > 0): ?>
          <a class="d-flex align-items-center gap-2 mt-3 p-2 rounded-3 text-decoration-none" style="background:#eff6ff;"
             href="<?= e($formUrl) ?>#pdf-import">
            <i class="bi bi-file-earmark-arrow-up fs-5 text-primary" aria-hidden="true"></i>
            <span class="text-dark"><strong>Have a CV in PDF?</strong> Import it and we will fill in your resume for you.</span>
            <i class="bi bi-arrow-right ms-auto text-primary" aria-hidden="true"></i>
          </a>
        <?php endif; ?>
      </section>
    </div>

    <!-- Public page and QR code -->
    <div class="col-lg-4">
      <section class="dash-card" aria-labelledby="shareTitle">
        <h2 id="shareTitle" class="mb-2">Your page</h2>
        <div class="share-url mb-3">
          <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e($publicText) ?></a>
          <button type="button" class="btn btn-sm btn-outline-primary" id="copyLink" data-url="<?= e($publicUrl) ?>">
            <i class="bi bi-clipboard me-1" aria-hidden="true"></i><span>Copy</span>
          </button>
        </div>
        <div class="d-flex gap-3 align-items-center">
          <?php if ($qrDataUri !== ''): ?>
            <img class="share-qr" src="<?= $qrDataUri ?>" alt="QR code to your page" width="112" height="112">
          <?php endif; ?>
          <div class="d-grid gap-2 flex-grow-1">
            <?php if ($qrDataUri !== ''): ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= $qrDataUri ?>" download="qrsume-<?= e($username) ?>-qr.svg">
                <i class="bi bi-download me-1" aria-hidden="true"></i>Download QR
              </a>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($publicUrl) ?>" target="_blank" rel="noopener noreferrer">
              <i class="bi bi-linkedin me-1" aria-hidden="true"></i>Share on LinkedIn
            </a>
          </div>
        </div>
      </section>
    </div>

    <!-- Statistics -->
    <div class="col-lg-8">
      <section class="dash-card" aria-labelledby="statsTitle">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h2 id="statsTitle">Statistics</h2>
          <div class="btn-group btn-group-sm stat-tabs" role="group" aria-label="Period">
            <button type="button" class="btn btn-outline-primary" data-period="today">Today</button>
            <button type="button" class="btn btn-outline-primary active" data-period="week" aria-pressed="true">Last 7 days</button>
            <button type="button" class="btn btn-outline-primary" data-period="total">All time</button>
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-4">
            <div class="stat-tile">
              <div class="value" data-stat="views"><?= number_format($statsData['week']['views']) ?></div>
              <div class="dash-muted"><span class="dot" style="background:#4f46e5"></span>Page views</div>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-tile">
              <div class="value" data-stat="downloads"><?= number_format($statsData['week']['downloads']) ?></div>
              <div class="dash-muted"><span class="dot" style="background:#f59e0b"></span>Downloads</div>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-tile">
              <div class="value" data-stat="qr_scans"><?= number_format($statsData['week']['qr_scans']) ?></div>
              <div class="dash-muted"><span class="dot" style="background:#10b981"></span>QR scans</div>
            </div>
          </div>
        </div>
        <div class="chart-box">
          <canvas id="statsChart" aria-label="Views, downloads and QR scans over the last 7 days" role="img"></canvas>
        </div>
      </section>
    </div>

    <!-- Plan and blog -->
    <div class="col-lg-4 d-flex flex-column gap-3">
      <section class="dash-card" aria-labelledby="planTitle">
        <div class="d-flex justify-content-between align-items-center">
          <h2 id="planTitle">Your plan</h2>
          <span class="plan-badge <?= $isPremium ? 'is-premium' : 'is-free' ?>"><?= $isPremium ? 'Premium' : 'Free' ?></span>
        </div>
        <ul class="plan-list">
          <li>
            <i class="bi <?= $isPremium ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary' ?>" aria-hidden="true"></i>
            <?= $isPremium ? 'PDFs without QRsume branding' : 'PDFs include QRsume branding' ?>
          </li>
          <?php if ($imports !== null): ?>
            <li>
              <i class="bi bi-file-earmark-arrow-up text-primary" aria-hidden="true"></i>
              <?php if ($imports['unlimited']): ?>
                Unlimited CV imports from PDF
              <?php else: ?>
                <?= $imports['remaining'] ?> CV import<?= $imports['remaining'] === 1 ? '' : 's' ?> from PDF left
              <?php endif; ?>
            </li>
          <?php endif; ?>
        </ul>
        <?php if (!$isPremium): ?>
          <a class="btn btn-warning btn-sm w-100" href="https://qrsume.com/checkout_remove_branding.php">
            <i class="bi bi-unlock2-fill me-1" aria-hidden="true"></i>Go premium · €1.99
          </a>
        <?php endif; ?>
      </section>

      <section class="dash-card" aria-labelledby="blogTitle">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <h2 id="blogTitle">Blog</h2>
          <span class="dash-muted small"><?= $articleCounts['published'] ?> published · <?= $articleCounts['draft'] ?> draft<?= $articleCounts['draft'] === 1 ? '' : 's' ?></span>
        </div>
        <p class="dash-muted small mb-3">Articles appear on your public page.</p>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary flex-fill" href="https://qrsume.com/assets/article_form.php"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New article</a>
          <a class="btn btn-sm btn-outline-secondary flex-fill" href="https://qrsume.com/assets/delete_article.php"><i class="bi bi-list-ul me-1" aria-hidden="true"></i>Manage</a>
        </div>
      </section>
    </div>
  </div>
</main>

<!-- Feedback button and modal (handled in footer.php) -->
<button id="feedbackBtn" class="btn btn-primary btn-sm position-fixed bottom-0 start-0 m-3">
  <i class="bi bi-chat-heart me-1" aria-hidden="true"></i>Give feedback
</button>
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Rate Us</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const statsData = <?= json_encode($statsData) ?>;

  // Period switch for the three numbers
  document.querySelectorAll('[data-period]').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('[data-period]').forEach(other => {
        other.classList.toggle('active', other === button);
        other.setAttribute('aria-pressed', String(other === button));
      });
      Object.entries(statsData[button.dataset.period]).forEach(([key, value]) => {
        document.querySelector(`[data-stat="${key}"]`).textContent = Number(value).toLocaleString();
      });
    });
  });

  // Copy the public link
  const copyButton = document.getElementById('copyLink');
  copyButton?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(copyButton.dataset.url);
      copyButton.querySelector('span').textContent = 'Copied!';
      setTimeout(() => { copyButton.querySelector('span').textContent = 'Copy'; }, 2000);
    } catch (error) {
      window.prompt('Copy your link:', copyButton.dataset.url);
    }
  });

  // Last 7 days chart
  if (window.Chart) {
    new Chart(document.getElementById('statsChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
          { label: 'Page views', data: <?= json_encode($views) ?>, borderColor: '#4f46e5', backgroundColor: 'rgba(79, 70, 229, .12)', fill: true, tension: .35 },
          { label: 'Downloads', data: <?= json_encode($downloads) ?>, borderColor: '#f59e0b', backgroundColor: 'transparent', tension: .35 },
          { label: 'QR scans', data: <?= json_encode($qrScans) ?>, borderColor: '#10b981', backgroundColor: 'transparent', tension: .35 },
        ],
      },
      options: {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { display: false } },
      },
    });
  }
});
</script>
</body>
</html>
