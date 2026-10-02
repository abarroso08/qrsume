<?php

declare(strict_types=1);

/**
 * PDF to CV
 * -----------------------------------------------------------------------------
 * Upload a resume PDF, read it with Adobe PDF Services "Extract PDF" (free tier),
 * and get its content back in the QRsume resume structure (resume.schema.json).
 *
 * - Browser: shows the JSON with copy / download buttons.
 * - API: POST with "Accept: application/json" (or ?format=json) returns the JSON itself.
 *
 * Needs ADOBE_PDF_SERVICES_CLIENT_ID and ADOBE_PDF_SERVICES_CLIENT_SECRET in .env
 * (optional ADOBE_PDF_SERVICES_REGION=eu to process documents in Europe).
 */

require_once __DIR__ . '/../assets/db.php';
require_once __DIR__ . '/lib/AdobePdfExtract.php';
require_once __DIR__ . '/lib/ResumeExtractor.php';

const PDFTOCV_MAX_BYTES = 10 * 1024 * 1024; // lowered to PHP's upload limits if those are smaller
const PDFTOCV_MAX_PAGES = 10;            // resumes are short; keeps the free monthly quota for real use
const PDFTOCV_CONVERSIONS_PER_HOUR = 10;

final class PdfToCvError extends RuntimeException
{
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Largest upload this server accepts: our limit, or PHP's upload_max_filesize / post_max_size if smaller.
 */
function maxUploadBytes(): int
{
    $limit = PDFTOCV_MAX_BYTES;
    foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
        $value = trim((string) ini_get($setting));
        $bytes = function_exists('ini_parse_quantity')
            ? ini_parse_quantity($value)
            : (int) $value * (1024 ** (int) strpos('bkmg', strtolower(substr($value, -1)) ?: 'b'));
        if ($bytes > 0) {
            $limit = min($limit, $bytes);
        }
    }

    return $limit;
}

function formatMegabytes(int $bytes): string
{
    return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
}

function respondJson(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Validate the upload, send it to Adobe and map the result to the resume structure.
 */
function convertUploadedPdf(): array
{
    // Above post_max_size PHP drops the whole request body, token included
    if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        throw new PdfToCvError('The PDF is too large. The limit is ' . formatMegabytes(maxUploadBytes()) . '.', 413);
    }

    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        throw new PdfToCvError('Your session expired. Reload the page and try again.', 403);
    }

    $clientId = (string) env('ADOBE_PDF_SERVICES_CLIENT_ID', '');
    $clientSecret = (string) env('ADOBE_PDF_SERVICES_CLIENT_SECRET', '');
    if ($clientId === '' || $clientSecret === '') {
        throw new PdfToCvError('PDF import is not configured yet.', 503);
    }

    $upload = $_FILES['cv_pdf'] ?? null;
    $uploadError = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        throw new PdfToCvError('Choose a PDF file first.', 400);
    }
    if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($upload['size'] ?? 0) > maxUploadBytes()) {
        throw new PdfToCvError('The PDF is too large. The limit is ' . formatMegabytes(maxUploadBytes()) . '.', 413);
    }
    if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        throw new PdfToCvError('The upload failed. Please try again.', 400);
    }

    $content = (string) file_get_contents($upload['tmp_name']);
    if (!str_starts_with($content, '%PDF-')) {
        throw new PdfToCvError('That file is not a PDF.', 415);
    }

    // Page objects are not always visible (compressed object streams), so this only catches obvious cases
    $pages = preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $content);
    if ($pages > PDFTOCV_MAX_PAGES) {
        throw new PdfToCvError('The PDF has more than ' . PDFTOCV_MAX_PAGES . ' pages. Please upload just your resume.', 413);
    }

    // Each conversion uses the shared Adobe free quota, so limit how often one session can run it
    $recent = array_filter($_SESSION['pdftocv_runs'] ?? [], fn(int $time) => $time > time() - 3600);
    if (count($recent) >= PDFTOCV_CONVERSIONS_PER_HOUR) {
        throw new PdfToCvError('You have imported several PDFs in the last hour. Please try again later.', 429);
    }
    $recent[] = time();
    $_SESSION['pdftocv_runs'] = array_values($recent);

    set_time_limit(150);
    $adobe = new AdobePdfExtract($clientId, $clientSecret, (string) env('ADOBE_PDF_SERVICES_REGION', 'us'));
    $structuredData = $adobe->extract($upload['tmp_name']);

    return (new ResumeExtractor())->extract($structuredData);
}

// -----------------------------------------------------------------------------
// Request handling
// -----------------------------------------------------------------------------

$wantsJson = ($_GET['format'] ?? '') === 'json' || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($wantsJson) {
        respondJson(401, ['error' => 'Please log in first.']);
    }
    header('Location: ../assets/login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$resume = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = 200;

    try {
        $resume = convertUploadedPdf();
    } catch (PdfToCvError | AdobePdfExtractException $exception) {
        $error = $exception->getMessage();
        $status = $exception->getCode() >= 400 ? $exception->getCode() : 502;
    } catch (Throwable $exception) {
        error_log('PDFtoCV: ' . $exception->getMessage());
        $error = 'Something went wrong while reading the PDF.';
        $status = 500;
    }

    if ($wantsJson) {
        respondJson($status, $error === null ? $resume : ['error' => $error]);
    }
    if ($error !== null) {
        http_response_code($status);
    }
}

// db.php already loaded the logged-in user's $personalinfo, which the navbar shows

$resumeJson = $resume === null ? '' : json_encode($resume, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$counts = $resume === null ? [] : [
    'Experience' => count($resume['experience']),
    'Education' => count($resume['education']),
    'Skills' => count($resume['skills']),
    'Languages' => count($resume['languages']),
    'Projects' => count($resume['projects']),
    'Other sections' => count($resume['custom_sections']),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Import from PDF · QRsume</title>
  <link rel="icon" href="https://qrsume.com/images/favicon (4).ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: #f5f6f8;
      font-family: "Inter", system-ui, sans-serif;
    }

    .pdftocv {
      width: 100%;
      max-width: 760px;
      margin: 2rem auto 4rem;
      padding: 0 16px;
      flex: 1;
    }

    .pdftocv h1 {
      margin-bottom: .4rem;
      font-size: 1.6rem;
      font-weight: 700;
    }

    .pdftocv-lead {
      margin-bottom: 1.5rem;
      color: #6c757d;
    }

    .pdftocv-card {
      padding: 1.25rem;
      border: 1px solid #e4e6ea;
      border-radius: 14px;
      background: #fff;
      box-shadow: 0 8px 24px rgba(0, 0, 0, .04);
    }

    .drop-zone {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .35rem;
      padding: 2rem 1rem;
      border: 2px dashed #ced4da;
      border-radius: 12px;
      background: #fbfbfc;
      color: #495057;
      text-align: center;
      cursor: pointer;
      transition: border-color .15s ease, background-color .15s ease;
    }

    .drop-zone:hover,
    .drop-zone.is-dragging {
      border-color: #2563eb;
      background: #eff6ff;
    }

    .drop-zone i {
      font-size: 2rem;
      color: #2563eb;
    }

    .drop-zone input {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
    }

    .drop-zone-file {
      font-weight: 600;
      word-break: break-all;
    }

    .pdftocv-note {
      margin: .75rem 0 0;
      color: #868e96;
      font-size: .82rem;
    }

    .pdftocv-counts {
      display: flex;
      flex-wrap: wrap;
      gap: .4rem;
      margin-bottom: 1rem;
    }

    .pdftocv-counts span {
      padding: .2rem .6rem;
      border-radius: 999px;
      background: #f1f3f5;
      color: #495057;
      font-size: .8rem;
      font-weight: 600;
    }

    .pdftocv-json {
      max-height: 32rem;
      margin: 0;
      padding: 1rem;
      overflow: auto;
      border-radius: 10px;
      background: #0f172a;
      color: #e2e8f0;
      font-size: .8rem;
      line-height: 1.5;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../assets/nav_dashboard.php'; ?>

<main class="pdftocv">
  <h1>Import your resume from a PDF</h1>
  <p class="pdftocv-lead">Upload the PDF of an existing resume and we will read your details, experience, education, skills and languages from it.</p>

  <?php if ($error !== null): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form class="pdftocv-card mb-4" id="pdfForm" method="POST" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= maxUploadBytes() ?>">

    <label class="drop-zone" id="dropZone">
      <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
      <span class="drop-zone-file" id="fileName">Choose a PDF or drop it here</span>
      <small class="text-muted">PDF, up to <?= formatMegabytes(maxUploadBytes()) ?> and <?= PDFTOCV_MAX_PAGES ?> pages</small>
      <input type="file" name="cv_pdf" id="cvPdf" accept="application/pdf,.pdf" required>
    </label>

    <button type="submit" class="btn btn-primary w-100 mt-3" id="submitButton">
      <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner" role="status" aria-hidden="true"></span>
      <span id="submitLabel">Read my resume</span>
    </button>

    <p class="pdftocv-note">
      <i class="bi bi-shield-lock" aria-hidden="true"></i>
      The PDF is sent to Adobe PDF Services to read its text. Reading usually takes 10–30 seconds.
    </p>
  </form>

  <?php if ($resume !== null): ?>
    <section class="pdftocv-card" aria-labelledby="resultTitle">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0" id="resultTitle">
          <?= e(trim($resume['personal_info']['personal_name'] . ' ' . $resume['personal_info']['personal_lastname']) ?: 'Your resume') ?>
        </h2>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="copyJson"><i class="bi bi-clipboard me-1"></i>Copy JSON</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="downloadJson"><i class="bi bi-download me-1"></i>Download</button>
        </div>
      </div>

      <div class="pdftocv-counts">
        <?php foreach ($counts as $label => $count): ?>
          <span><?= e($label) ?>: <?= $count ?></span>
        <?php endforeach; ?>
      </div>

      <pre class="pdftocv-json" id="resumeJson"><?= e($resumeJson) ?></pre>
      <p class="pdftocv-note">Check the result: resumes have many layouts, so some details may land in the wrong field. The structure is described in <a href="resume.schema.json" target="_blank" rel="noopener">resume.schema.json</a>.</p>
    </section>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../assets/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('pdfForm');
  const input = document.getElementById('cvPdf');
  const dropZone = document.getElementById('dropZone');
  const fileName = document.getElementById('fileName');

  input.addEventListener('change', () => {
    fileName.textContent = input.files.length ? input.files[0].name : 'Choose a PDF or drop it here';
  });

  ['dragenter', 'dragover'].forEach(type => dropZone.addEventListener(type, () => dropZone.classList.add('is-dragging')));
  ['dragleave', 'drop'].forEach(type => dropZone.addEventListener(type, () => dropZone.classList.remove('is-dragging')));

  form.addEventListener('submit', () => {
    document.getElementById('submitButton').disabled = true;
    document.getElementById('submitSpinner').classList.remove('d-none');
    document.getElementById('submitLabel').textContent = 'Reading your resume…';
  });

  const jsonBlock = document.getElementById('resumeJson');
  if (!jsonBlock) return;

  document.getElementById('copyJson').addEventListener('click', async event => {
    try {
      await navigator.clipboard.writeText(jsonBlock.textContent);
      event.currentTarget.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
    } catch (error) {
      window.getSelection().selectAllChildren(jsonBlock);
    }
  });

  document.getElementById('downloadJson').addEventListener('click', () => {
    const blob = new Blob([jsonBlock.textContent], { type: 'application/json' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'resume.json';
    link.click();
    URL.revokeObjectURL(link.href);
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
