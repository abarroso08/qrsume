<?php
declare(strict_types=1);

/**
 * Resume Preview Page
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Load one user's resume data.
 * - Let the user edit the preview directly.
 * - Let the user choose visible sections and section order.
 * - Let premium users remove QR code and QRsume links from the generated PDF.
 * - Show free users a small preview of the branding they are paying to remove.
 * - Show/hide QRsume link + bottom link + QR icon in the live preview depending on the QR checkbox.
 * - Submit the edited content to create_pdf.php.
 */

include("assets/head.php");
include("assets/premium.php");

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectToError(): never
{
    header("Location: https://qrsume.com/error.php");
    exit();
}

function firstRow(array $rows): ?array
{
    return $rows[0] ?? null;
}

function rowsByUser(PDO $pdo, string $table, int $userId, string $columns = '*'): array
{
    return db_select($pdo, $table, $columns, ['user_id' => $userId]);
}

function resolveUsername(): string
{
    if (!empty($_GET['username'])) {
        return (string) $_GET['username'];
    }

    if (!empty($_SESSION['username'])) {
        return (string) $_SESSION['username'];
    }

    redirectToError();
}

function getResumeUser(PDO $pdo, string $username): array
{
    $stmt = $pdo->prepare("SELECT id, privilege FROM users WHERE username = :username LIMIT 1");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        redirectToError();
    }

    return $user;
}

// -----------------------------------------------------------------------------
// Data loading
// -----------------------------------------------------------------------------

$username = resolveUsername();
$pdo = $db;

try {
    $resumeUser = getResumeUser($pdo, $username);
    $userId = (int) $resumeUser['id'];

    $account = firstRow(db_select($pdo, 'users', 'username,email', ['id' => $userId])) ?? [];
    $personal = firstRow(rowsByUser($pdo, 'personalinfo', $userId)) ?? [];
    $contact = firstRow(rowsByUser($pdo, 'contactinfo', $userId)) ?? [];

    $education = rowsByUser($pdo, 'education', $userId);
    $experience = rowsByUser($pdo, 'experience', $userId);
    $languages = rowsByUser($pdo, 'languages', $userId);
    $skills = rowsByUser($pdo, 'aptitudes', $userId);
    $projects = rowsByUser($pdo, 'interests', $userId);
    $customSections = rowsByUser($pdo, 'custom_sections', $userId);

    $canRemoveBranding = userHasPurchase($db, $userId, 'remove_qrsume_branding');
} catch (PDOException $exception) {
    error_log('Resume preview database error: ' . $exception->getMessage());
    redirectToError();
}

$photoUrl = !empty($personal['personal_photo'])
    ? 'images/' . e($personal['personal_photo'])
    : '';

$sectionOrder = ['education', 'experience', 'skills', 'languages', 'projects'];

foreach ($customSections as $index => $section) {
    $sectionOrder[] = 'custom_' . $index;
}

$defaultSectionOrder = implode(',', $sectionOrder);
$profileUrl = 'https://qrsume.com/' . $username;
$profileText = 'qrsume.com/' . $username;
?>

<style>
  :root {
    --preview-page-width: 816px;
    --preview-page-min-height: 1056px;
    --resume-font-base: 16px;
    --resume-font-title: 18px;
    --resume-font-section: 22px;
    --resume-font-name: 40px;
    --resume-gap: 10px;
    --sidebar-width: 320px;
    --navbar-height: 56px;
  }

  body {
    min-width: 1024px;
    background: #f5f6f8;
  }

  #navbar {
    top: 0 !important;
    transition: none !important;
    z-index: 1030;
  }

  #nav-spacer {
    height: var(--navbar-height);
  }

  .resume-builder {
    display: grid;
    grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
    min-height: calc(100vh - var(--navbar-height));
  }

  .builder-sidebar {
    position: sticky;
    top: var(--navbar-height);
    align-self: start;
    height: calc(100vh - var(--navbar-height));
    overflow-y: auto;
    padding: 1.25rem;
    background: #ffffff;
    border-right: 1px solid #e4e6ea;
    box-shadow: 8px 0 24px rgba(0, 0, 0, .035);
    z-index: 10;
  }

  .sidebar-header {
    margin-bottom: 1.25rem;
  }

  .sidebar-header h1 {
    margin: 0 0 .4rem;
    font-size: 1.35rem;
    font-weight: 750;
    line-height: 1.2;
  }

  .sidebar-header p {
    margin: 0;
    color: #6c757d;
    font-size: .92rem;
    line-height: 1.45;
  }

  .sidebar-card {
    margin-bottom: 1rem;
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 14px;
    background: #fbfbfc;
  }

  .sidebar-card h2 {
    margin: 0 0 .8rem;
    color: #212529;
    font-size: .82rem;
    font-weight: 750;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .control-field {
    margin-bottom: .85rem;
  }

  .control-field:last-child {
    margin-bottom: 0;
  }

  .control-field label,
  .control-check label {
    font-size: .9rem;
    font-weight: 600;
    color: #343a40;
  }

  .control-field .form-select {
    margin-top: .35rem;
  }

  .control-check {
    display: flex;
    align-items: flex-start;
    gap: .6rem;
    margin-bottom: .7rem;
  }

  .control-check:last-child {
    margin-bottom: 0;
  }

  .control-check input {
    flex: 0 0 auto;
    width: 1rem;
    height: 1rem;
    margin-top: .2rem;
  }

  .control-check small {
    display: block;
    margin-top: .1rem;
    color: #868e96;
    font-size: .76rem;
    font-weight: 400;
    line-height: 1.35;
  }

  .branding-preview-box {
    margin-top: .85rem;
    padding: .85rem;
    border: 1px solid #facc15;
    border-radius: 14px;
    background: #fffbeb;
  }

  .branding-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .65rem;
  }

  .branding-preview-title {
    color: #713f12;
    font-size: .82rem;
    font-weight: 800;
  }

  .branding-preview-badge {
    padding: .18rem .45rem;
    border-radius: 999px;
    background: #fef3c7;
    color: #92400e;
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
  }

  .branding-preview-resume {
    position: relative;
    min-height: 125px;
    padding: .8rem;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .015);
  }

  .branding-preview-line {
    height: 7px;
    margin-bottom: .45rem;
    border-radius: 999px;
    background: #e5e7eb;
  }

  .branding-preview-line.short {
    width: 42%;
    height: 10px;
    background: #cbd5e1;
  }

  .branding-preview-line.medium {
    width: 70%;
  }

  .branding-preview-footer {
    position: absolute;
    right: .75rem;
    bottom: .65rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .25rem;
  }

  .branding-preview-qr {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #111827;
    border-radius: 6px;
    background: #fff;
    color: #111827;
    font-size: 1.7rem;
    line-height: 1;
  }

  .branding-preview-link {
    color: #2563eb;
    font-size: .68rem;
    font-weight: 700;
    text-decoration: underline;
  }

  .branding-preview-note {
    margin: .65rem 0 0;
    color: #78350f;
    font-size: .76rem;
    line-height: 1.35;
  }

  .premium-status-box {
    margin-top: .85rem;
    padding: .75rem;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    background: #f0fdf4;
    color: #166534;
    font-size: .78rem;
    line-height: 1.35;
  }

  .section-order-list {
    display: grid;
    gap: .5rem;
  }

  .section-order-item {
    width:90%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .6rem .7rem;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    background: #ffffff;
    cursor: grab;
    user-select: none;
    font-size: .9rem;
    font-weight: 600;
    color: #343a40;
    transition: border-color .15s ease, box-shadow .15s ease, opacity .15s ease;
  }

  .section-order-item:hover {
    border-color: #adb5bd;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
  }

  .section-order-item:active {
    cursor: grabbing;
  }

  .section-order-item.dragging {
    opacity: .45;
  }

  .section-order-label {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    min-width: 0;
    margin: 0;
    cursor: pointer;
    font-size: .9rem;
    font-weight: 600;
    color: #343a40;
  }

  .section-order-label input {
    flex: 0 0 auto;
    width: 1rem;
    height: 1rem;
    margin: 0;
  }

  .section-order-label span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .section-order-handle {
    flex: 0 0 auto;
    color: #868e96;
    font-size: 1rem;
    line-height: 1;
    cursor: grab;
  }

  .section-description-toggle {
    margin-top: .85rem;
    padding-top: .85rem;
    border-top: 1px solid #e9ecef;
  }

  .sidebar-actions {
    display: grid;
    gap: .65rem;
    margin-top: 1rem;
  }

  .sidebar-actions .btn {
    width: 100%;
  }

  .sidebar-note {
    margin-top: .75rem;
    color: #6c757d;
    font-size: .8rem;
    line-height: 1.4;
    text-align: center;
  }

  .builder-preview-area {
    min-width: 0;
    padding: 2rem 2rem 4rem;
    overflow-x: auto;
  }

  .preview-topbar {
    max-width: var(--preview-page-width);
    margin: 0 auto 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    color: #6c757d;
    font-size: .92rem;
  }

  .preview-topbar strong {
    color: #212529;
  }

  .resume-container {
    position: relative;
    width: var(--preview-page-width);
    min-height: var(--preview-page-min-height);
    margin: auto;
    padding: 2.5rem;
    padding-bottom: 5.5rem;
    background: #ffffff;
    box-shadow: 0 0 14px rgba(0, 0, 0, .13);
    font-family: 'Times New Roman', Times, serif;
    font-size: var(--resume-font-base);
    color: #111;
  }

  .resume-header {
    display: flex;
    align-items: center;
    gap: 1.2rem;
    margin-bottom: calc(var(--resume-gap) * .7);
  }

  .resume-header__main {
    flex: 1;
    min-width: 0;
  }

  .name-row,
  .contact-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
  }

  .name-row .editable-input {
    font-size: var(--resume-font-name);
    font-weight: 700;
    line-height: 1.05;
  }

  .name-row,
  .contact-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
  }

  .qrsume-preview-link,
  .qrsume-bottom-link {
    color: #2563eb;
    font-size: var(--resume-font-base);
    font-weight: 500;
    text-decoration: underline;
    white-space: nowrap;
  }

  .resume-branding-bottom-link {
    position: absolute;
    left: 50%;
    bottom: 1.35rem;
    transform: translateX(-50%);
    text-align: center;
    z-index: 2;
  }

  .resume-branding-qr {
    position: absolute;
    right: 2rem;
    bottom: 1.2rem;
    width: 64px;
    text-align: center;
    z-index: 2;
  }

  .resume-branding-qr-box {
    width: 58px;
    height: 58px;
    margin: 0 auto .2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #111827;
    border-radius: 6px;
    background: #fff;
    color: #111827;
    font-size: 2.3rem;
    line-height: 1;
  }

  .resume-branding-qr-label {
    display: block;
    color: #111827;
    font-size: .62rem;
    font-weight: 600;
    line-height: 1.1;
  }

  .photo-wrapper {
    flex: 0 0 auto;
    transition: opacity .2s ease;
  }

  .photo-wrapper.is-muted {
    opacity: .22;
  }

  #photoInput {
    display: none;
  }

  .photo-preview,
  .photo-placeholder {
    width: 110px;
    height: 130px;
    border-radius: 6px;
    cursor: pointer;
  }

  .photo-preview {
    display: block;
    object-fit: cover;
    border: 2px solid #cfcfcf;
  }

  .photo-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 2px dashed #a9a9a9;
    color: #888;
    font-size: 12px;
    text-align: center;
  }

  .photo-placeholder svg {
    opacity: .55;
  }

  .resume-section {
    margin-top: calc(var(--resume-gap) * 1.4);
  }

  .section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: calc(var(--resume-gap) * .45);
    padding-bottom: .15rem;
    border-bottom: 1px solid #111;
    font-size: var(--resume-font-section);
    font-weight: 700;
  }

  .resume-entry {
    margin-bottom: var(--resume-gap);
  }

  .entry-line {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
  }

  .entry-title {
    flex: 1;
    display: flex;
    min-width: 0;
  }

  .entry-title .editable-input,
  .entry-date .editable-input,
  .editable-input.fw-bold {
    font-size: var(--resume-font-title);
    font-weight: 700;
  }

  .entry-date {
    flex: 0 0 25%;
    text-align: right;
  }

  .two-column-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 1.5rem;
    row-gap: .15rem;
  }

  .language-list {
    display: grid;
    grid-template-columns: minmax(160px, 260px);
    gap: .15rem;
  }

  .language-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
  }

  .editable-input,
  .editable-textarea {
    width: 100%;
    padding: 0;
    margin: 0;
    border: none;
    border-bottom: 1px dashed transparent;
    background: transparent;
    color: inherit;
    line-height: 1.25;
    cursor: text;
    transition: border-color .15s ease, background .15s ease;
  }

  .editable-textarea {
    overflow: hidden;
    resize: none;
    font-family: inherit;
    font-size: var(--resume-font-base);
  }

  .editable-input:hover,
  .editable-textarea:hover {
    border-bottom-color: #b7b7b7;
  }

  .editable-input:focus,
  .editable-textarea:focus {
    outline: none;
    border-bottom-color: #777;
    background: rgba(13, 110, 253, .035);
  }

  .text-end { text-align: right; }
  .text-start { text-align: left; }
  .text-center { text-align: center; }
  .fw-bold { font-weight: 700; }

  .is-hidden-in-preview {
    display: none !important;
  }

  @media (max-width: 1100px) {
    body {
      min-width: 0;
    }

    .resume-builder {
      grid-template-columns: 1fr;
    }

    .builder-sidebar {
      position: relative;
      top: 0;
      height: auto;
      border-right: none;
      border-bottom: 1px solid #e4e6ea;
    }

    .builder-preview-area {
      padding: 1.25rem;
    }
  }

  @media print {
    body {
      min-width: 0;
      background: #fff;
    }

    nav,
    footer,
    .builder-sidebar,
    .preview-topbar {
      display: none !important;
    }

    .resume-builder {
      display: block;
    }

    .builder-preview-area {
      padding: 0;
      overflow: visible;
    }

    .resume-container {
      width: auto;
      min-height: auto;
      margin: 0;
      padding: 0;
      padding-bottom: 5rem;
      box-shadow: none;
    }
  }
</style>

</head>
<body>
<script>
  window.disableNavbarScrollEffect = true;
</script>

<?php include("assets/nav_profile.php"); ?>

<form
  id="resumeForm"
  action="https://qrsume.com/create_pdf.php"
  method="POST"
  enctype="multipart/form-data"
>
  <input type="hidden" name="section_order" id="sectionOrderInput" value="<?= e($defaultSectionOrder) ?>">

  <main class="resume-builder">
    <aside class="builder-sidebar" aria-label="Resume options">
      <div class="sidebar-header">
        <h1>Resume Preview</h1>
        <p>Edit the resume on the right, choose style options, select sections, and drag them into your preferred order.</p>
      </div>

      <section class="sidebar-card" aria-labelledby="style-controls-title">
        <h2 id="style-controls-title">Style</h2>

        <div class="control-field">
          <label for="font">Font</label>
          <select name="font" id="font" class="form-select">
            <option value="times">Times</option>
            <option value="helvetica">Helvetica</option>
          </select>
        </div>

        <div class="control-field">
          <label for="contentFontSize">Font size</label>
          <select name="contentFontSize" id="contentFontSize" class="form-select">
            <option value="9">Small</option>
            <option value="10" selected>Default</option>
            <option value="11">Large</option>
            <option value="12">Extra large</option>
          </select>
        </div>

        <div class="control-field">
          <label for="spaceSize">Section spacing</label>
          <select name="spaceSize" id="spaceSize" class="form-select">
            <option value="5">Compact</option>
            <option value="10" selected>Default</option>
            <option value="20">Spacious</option>
            <option value="30">Very spacious</option>
          </select>
        </div>
      </section>

      <section class="sidebar-card" aria-labelledby="content-controls-title">
        <h2 id="content-controls-title">Content</h2>
        <div class="control-check mt-2">
          <input type="checkbox" name="show_photo" id="togglePhoto" checked>
          <label for="togglePhoto">
            Profile photo
            <small>Keep the photo in the PDF. Click the photo area to upload a new one.</small>
          </label>
        </div>
        <div class="control-check">
          <input type="checkbox" name="show_bio" id="toggleBio" checked>
          <label for="toggleBio">
            Personal bio
            <small>Show or hide the summary paragraph in the preview and PDF.</small>
          </label>
        </div>
        

        <?php if ($canRemoveBranding): ?>
          <div class="control-check">
            <input type="checkbox" name="show_QR" id="toggleQr" checked>
            <label for="toggleQr">
              QR code & links
              <small>You can disable this because you purchased branding removal.</small>
            </label>
          </div>

          <div class="premium-status-box">
            <strong>Premium unlocked.</strong><br>
            Uncheck “QR code & links” if you want a clean PDF without QRsume branding.
          </div>
        <?php else: ?>
          <input type="hidden" name="show_QR" value="1">

          <div class="control-check">
            <input type="checkbox" id="toggleQr" checked disabled>
            <label for="toggleQr">
              QR code & links
              <small>Free resumes include QRsume branding.</small>
            </label>
          </div>

          <button class="btn btn-link btn-sm p-0 text-muted"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#brandingPreviewCollapse"
        aria-expanded="false"
        aria-controls="brandingPreviewCollapse"
        title="Preview free PDF branding">
  <i class="bi bi-info-circle"></i>
</button>

<div class="collapse mt-2" id="brandingPreviewCollapse">
  <div class="branding-preview-box">
    <div class="branding-preview-header">
      <span class="branding-preview-title">What appears in free PDFs</span>
      <span class="branding-preview-badge">Preview</span>
    </div>

    <div class="branding-preview-resume">
      <div class="branding-preview-line short"></div>

      <div class="branding-preview-footer">
        <div class="branding-preview-qr">
          <i class="bi bi-qr-code"></i>
        </div>

        <div class="branding-preview-link">
          <?= e($profileText) ?>
        </div>
      </div>
    </div>

    <p class="branding-preview-note">
      Remove the QR code and QRsume profile links from your generated PDF.
    </p>
  </div>
</div>

<a href="/checkout_remove_branding.php"
   class="btn btn-warning w-100 mt-2 d-flex align-items-center justify-content-center gap-1">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
       class="bi bi-unlock2-fill" viewBox="0 0 16 16">
    <path fill-rule="evenodd"
          d="M8 0c1.07 0 2.041.42 2.759 1.104l.14.14.062.08a.5.5 0 0 1-.71.675l-.076-.066-.216-.205A3 3 0 0 0 5 4v2h6.5A2.5 2.5 0 0 1 14 8.5v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4"/>
  </svg>

  Remove branding
</a>
        <?php endif; ?>

        
      </section>

      <section class="sidebar-card" aria-labelledby="section-controls-title">
        <h2 id="section-controls-title">Sections</h2>

        <div id="sectionOrderList" class="section-order-list">
          <div class="section-order-item" draggable="true" data-section="education">
            <label class="section-order-label" for="showEducation">
              <input type="checkbox" name="show_education" id="showEducation" data-preview-section="educationSection" checked>
              <span>Education</span>
            </label>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <div class="section-order-item" draggable="true" data-section="experience">
            <label class="section-order-label" for="showExperience">
              <input type="checkbox" name="show_experience" id="showExperience" data-preview-section="experienceSection" checked>
              <span>Work experience</span>
            </label>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <div class="section-order-item" draggable="true" data-section="skills">
            <label class="section-order-label" for="showSkills">
              <input type="checkbox" name="show_skills" id="showSkills" data-preview-section="skillsSection" checked>
              <span>Aptitudes</span>
            </label>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <div class="section-order-item" draggable="true" data-section="languages">
            <label class="section-order-label" for="showLanguages">
              <input type="checkbox" name="show_languages" id="showLanguages" data-preview-section="languagesSection" checked>
              <span>Languages</span>
            </label>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <div class="section-order-item" draggable="true" data-section="projects">
            <label class="section-order-label" for="showProjects">
              <input type="checkbox" name="show_projects" id="showProjects" data-preview-section="projectsSection" checked>
              <span>Projects</span>
            </label>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <?php if (!empty($customSections)): ?>
            <?php foreach ($customSections as $index => $section): ?>
              <?php $customSectionId = 'customSection' . $index; ?>
              <div class="section-order-item" draggable="true" data-section="custom_<?= $index ?>">
                <label class="section-order-label" for="showCustomSection<?= $index ?>">
                  <input
                    type="checkbox"
                    name="custom_sections[<?= $index ?>][show]"
                    id="showCustomSection<?= $index ?>"
                    value="1"
                    data-preview-section="<?= e($customSectionId) ?>"
                    checked
                  >
                  <span><?= e($section['section_title'] ?: 'Untitled Section') ?></span>
                </label>
                <span class="section-order-handle" title="Drag to reorder">☰</span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="control-check section-description-toggle">
          <input type="checkbox" name="show_education_description" id="showEducationDescription">
          <label for="showEducationDescription">Show education descriptions</label>
        </div>

        <p class="sidebar-note">Use each checkbox to show or hide a section. Drag rows up or down to change the order in the preview and PDF.</p>
      </section>

      <section class="sidebar-card" aria-labelledby="generate-controls-title">
        <h2 id="generate-controls-title">Generate PDF</h2>
        <div class="sidebar-actions">
          <button type="submit" class="btn btn-primary" formaction="https://qrsume.com/create_pdf.php?lan=en" formtarget="_blank">
            Generate PDF (English)
          </button>
          <button type="submit" class="btn btn-secondary" formaction="https://qrsume.com/create_pdf.php?lan=es" formtarget="_blank">
            Generar PDF (Español)
          </button>
        </div>
        <p class="sidebar-note">The preview is editable. Final PDF spacing may adjust slightly for print formatting.</p>
      </section>
    </aside>

    <section class="builder-preview-area" aria-label="Editable resume preview">
      <div class="preview-topbar">
        <span><strong>Live preview</strong> — click any text field to edit it.</span>
        <span>A4-style page</span>
      </div>

      <article class="resume-container" id="resumePreview" aria-label="Resume preview">
        <header class="resume-header">
          <div class="photo-wrapper" id="photoWrapper">
            <input type="file" id="photoInput" name="photo_upload" accept="image/*">
            <input type="hidden" name="existing_photo_url" value="<?= $photoUrl ?>">

            <img
              id="photoPreview"
              class="photo-preview"
              src="<?= $photoUrl ?>"
              alt="Profile photo"
              title="Click to change photo"
              <?= $photoUrl ? '' : 'style="display:none;"' ?>
            >

            <div
              id="photoPlaceholder"
              class="photo-placeholder"
              title="Click to add photo"
              <?= $photoUrl ? 'style="display:none;"' : '' ?>
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M10.5 8.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                <path d="M2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4H2zm.5 2a.5.5 0 1 1 0-1 .5.5 0 0 1 0 1zm9 2.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0z"/>
              </svg>
              <span>Click to add<br>photo</span>
            </div>
          </div>

          <div class="resume-header__main">
            <div class="name-row">
              <input class="editable-input text-end" type="text" name="personal_name" value="<?= e($personal['personal_name'] ?? '') ?>" placeholder="Name">
              <input class="editable-input text-start" type="text" name="personal_lastname" value="<?= e($personal['personal_lastname'] ?? '') ?>" placeholder="Last name">
            </div>

            <div class="contact-row">
              <input
                class="editable-input text-end"
                type="text"
                name="email"
                value="<?= e($contact['email'] ?? $account['email'] ?? '') ?>"
                placeholder="Email"
              >

              <span aria-hidden="true">|</span>

              <input
                class="editable-input text-start text-center"
                type="text"
                name="phone_number"
                value="<?= e($contact['phone_number'] ?? '') ?>"
                placeholder="Phone number"
              >

              <span class="qrsume-branding-preview-item" id="qrsumePreviewSeparator" aria-hidden="true">|</span>

              <a
                id="qrsumePreviewLink"
                class="qrsume-preview-link qrsume-branding-preview-item"
                href="<?= e($profileUrl) ?>"
                target="_blank"
                rel="noopener noreferrer"
              >
                <?= e($profileText) ?>
              </a>
            </div>
          </div>
        </header>

        <section class="resume-section" id="bioSection">
          <textarea class="editable-textarea" name="personal_bio" rows="4" placeholder="Write a short professional bio..."><?= e($personal['personal_bio'] ?? '') ?></textarea>
        </section>

        <section class="resume-section" id="educationSection" data-resume-section="education">
          <div class="section-heading">
            <span>Education</span>
          </div>

          <?php foreach ($education as $index => $edu): ?>
            <div class="resume-entry">
              <div class="entry-line">
                <div class="entry-title">
                  <input class="editable-input fw-bold" type="text" name="education[<?= $index ?>][name_of_studies]" value="<?= e($edu['name_of_studies'] ?? '') ?>" placeholder="Degree / studies">
                </div>
                <div class="entry-date">
                  <input class="editable-input fw-bold text-end" type="text" name="education[<?= $index ?>][date]" value="<?= e($edu['date'] ?? '') ?>" placeholder="Date">
                </div>
              </div>
              <input class="editable-input" type="text" name="education[<?= $index ?>][place_of_study]" value="<?= e($edu['place_of_study'] ?? '') ?>" placeholder="Institution">
              <textarea class="editable-textarea education-description" name="education[<?= $index ?>][desc]" rows="2" placeholder="Education description"><?= e($edu['brief_description'] ?? '') ?></textarea>
            </div>
          <?php endforeach; ?>
        </section>

        <section class="resume-section" id="experienceSection" data-resume-section="experience">
          <div class="section-heading">
            <span>Work Experience</span>
          </div>

          <?php foreach ($experience as $index => $exp): ?>
            <div class="resume-entry">
              <div class="entry-line">
                <div class="entry-title">
                  <input class="editable-input fw-bold" type="text" name="experience[<?= $index ?>][job_name]" value="<?= e($exp['job_name'] ?? '') ?>" placeholder="Job title">
                  <input class="editable-input fw-bold" type="text" name="experience[<?= $index ?>][place_of_work]" value="<?= e($exp['place_of_work'] ?? '') ?>" placeholder="Company">
                </div>
                <div class="entry-date">
                  <input class="editable-input fw-bold text-end" type="text" name="experience[<?= $index ?>][date]" value="<?= e($exp['date'] ?? '') ?>" placeholder="Date">
                </div>
              </div>
              <textarea class="editable-textarea" name="experience[<?= $index ?>][brief_description]" rows="3" placeholder="Describe your responsibilities and achievements..."><?= e($exp['brief_description'] ?? '') ?></textarea>
            </div>
          <?php endforeach; ?>
        </section>

        <section class="resume-section" id="skillsSection" data-resume-section="skills">
          <div class="section-heading">
            <span>Aptitudes</span>
          </div>

          <div class="two-column-list">
            <?php foreach ($skills as $index => $skill): ?>
              <input class="editable-input" type="text" name="skills[<?= $index ?>][aptitude]" value="<?= e($skill['aptitude'] ?? '') ?>" placeholder="Skill">
            <?php endforeach; ?>
          </div>
        </section>

        <section class="resume-section" id="languagesSection" data-resume-section="languages">
          <div class="section-heading">
            <span>Languages</span>
          </div>

          <div class="language-list">
            <?php foreach ($languages as $index => $lang): ?>
              <div class="language-row">
                <input class="editable-input" type="text" name="languages[<?= $index ?>][language]" value="<?= e($lang['language'] ?? '') ?>" placeholder="Language">
                <input class="editable-input" type="text" name="languages[<?= $index ?>][level]" value="<?= e($lang['level'] ?? '') ?>" placeholder="Level">
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="resume-section" id="projectsSection" data-resume-section="projects">
          <div class="section-heading">
            <span>Projects</span>
          </div>

          <?php foreach ($projects as $index => $project): ?>
            <div class="resume-entry">
              <input class="editable-input fw-bold" type="text" name="projects[<?= $index ?>][interest]" value="<?= e($project['interest'] ?? '') ?>" placeholder="Project title">
              <textarea class="editable-textarea" name="projects[<?= $index ?>][description]" rows="2" placeholder="Project description"><?= e($project['description'] ?? '') ?></textarea>
            </div>
          <?php endforeach; ?>
        </section>

        <?php if (!empty($customSections)): ?>
          <?php foreach ($customSections as $index => $section): ?>
            <?php $customSectionId = 'customSection' . $index; ?>
            <section class="resume-section" id="<?= e($customSectionId) ?>" data-resume-section="custom_<?= $index ?>">
              <div class="section-heading">
                <span><?= e($section['section_title'] ?: 'Untitled Section') ?></span>
              </div>

              <input type="hidden" name="custom_sections[<?= $index ?>][section_title]" value="<?= e($section['section_title'] ?? '') ?>">
              <textarea class="editable-textarea" name="custom_sections[<?= $index ?>][section_content]" rows="3" placeholder="Section content"><?= e($section['section_content'] ?? '') ?></textarea>
            </section>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="resume-branding-bottom-link qrsume-branding-preview-item" id="qrsumeBottomLinkWrapper">
          <a
            id="qrsumeBottomLink"
            class="qrsume-bottom-link"
            href="<?= e($profileUrl) ?>"
            target="_blank"
            rel="noopener noreferrer"
          >
            Created with QRsume - <?= e($profileText) ?>
          </a>
        </div>

        <div class="resume-branding-qr qrsume-branding-preview-item" id="qrsumePreviewQr">
          <div class="resume-branding-qr-box">
            <i class="bi bi-qr-code"></i>
          </div>
          <span class="resume-branding-qr-label">Full Profile<br>&amp; Projects</span>
        </div>
      </article>
    </section>
  </main>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const resumePreview = document.getElementById('resumePreview');
  const fontSelector = document.getElementById('font');
  const fontSizeSelector = document.getElementById('contentFontSize');
  const spacingSelector = document.getElementById('spaceSize');
  const photoInput = document.getElementById('photoInput');
  const photoPreview = document.getElementById('photoPreview');
  const photoPlaceholder = document.getElementById('photoPlaceholder');
  const photoWrapper = document.getElementById('photoWrapper');
  const togglePhoto = document.getElementById('togglePhoto');
  const toggleBio = document.getElementById('toggleBio');
  const toggleQr = document.getElementById('toggleQr');
  const toggleEducationDescription = document.getElementById('showEducationDescription');
  const sectionOrderList = document.getElementById('sectionOrderList');
  const sectionOrderInput = document.getElementById('sectionOrderInput');
  const qrsumeBrandingPreviewItems = document.querySelectorAll('.qrsume-branding-preview-item');

  const fontFamilies = {
    times: "'Times New Roman', Times, serif",
    helvetica: "'Helvetica Neue', Helvetica, Arial, sans-serif"
  };

  function applyFont(fontKey) {
    resumePreview.style.fontFamily = fontFamilies[fontKey] || fontFamilies.times;
  }

  function applyFontSize(sizePt) {
    const basePx = Number(sizePt) * 1.6;

    resumePreview.style.setProperty('--resume-font-base', `${basePx}px`);
    resumePreview.style.setProperty('--resume-font-title', `${basePx * 1.1}px`);
    resumePreview.style.setProperty('--resume-font-section', `${basePx * 1.4}px`);
    resumePreview.style.setProperty('--resume-font-name', `${basePx * 2.5}px`);

    resizeAllTextareas();
  }

  function applySpacing(value) {
    resumePreview.style.setProperty('--resume-gap', `${Number(value)}px`);
    resizeAllTextareas();
  }

  function setPreviewVisibility(sectionId, shouldShow) {
    const section = document.getElementById(sectionId);
    if (!section) return;

    section.classList.toggle('is-hidden-in-preview', !shouldShow);
  }

  function setQrsumeBrandingVisibility(shouldShow) {
    qrsumeBrandingPreviewItems.forEach(item => {
      item.classList.toggle('is-hidden-in-preview', !shouldShow);
    });
  }

  function setEducationDescriptionVisibility(shouldShow) {
    document.querySelectorAll('.education-description').forEach(description => {
      description.classList.toggle('is-hidden-in-preview', !shouldShow);
    });

    resizeAllTextareas();
  }

  function resizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight}px`;
  }

  function resizeAllTextareas() {
    document.querySelectorAll('.editable-textarea').forEach(resizeTextarea);
  }

  function triggerPhotoUpload() {
    photoInput.click();
  }

  function getDragAfterElement(container, y) {
    const draggableElements = [
      ...container.querySelectorAll('.section-order-item:not(.dragging)')
    ];

    return draggableElements.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;

      if (offset < 0 && offset > closest.offset) {
        return { offset, element: child };
      }

      return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
  }

  function getCurrentSectionOrder() {
    if (!sectionOrderList) return [];

    return [...sectionOrderList.querySelectorAll('.section-order-item')]
      .map(item => item.dataset.section)
      .filter(Boolean);
  }

  function updateSectionOrderInput() {
    if (!sectionOrderInput) return;
    sectionOrderInput.value = getCurrentSectionOrder().join(',');
  }

  function reorderPreviewSections() {
    const order = getCurrentSectionOrder();

    order.forEach(sectionKey => {
      const section = resumePreview.querySelector(`[data-resume-section="${sectionKey}"]`);
      if (section) {
        resumePreview.appendChild(section);
      }
    });

    const bottomLink = document.getElementById('qrsumeBottomLinkWrapper');
    const qr = document.getElementById('qrsumePreviewQr');

    if (bottomLink) resumePreview.appendChild(bottomLink);
    if (qr) resumePreview.appendChild(qr);

    resizeAllTextareas();
  }

  function syncSectionOrder() {
    updateSectionOrderInput();
    reorderPreviewSections();
  }

  function initializeSectionDragAndDrop() {
    if (!sectionOrderList || !sectionOrderInput) return;

    sectionOrderList.addEventListener('dragstart', event => {
      const item = event.target.closest('.section-order-item');
      if (!item) return;
      item.classList.add('dragging');
    });

    sectionOrderList.addEventListener('dragend', event => {
      const item = event.target.closest('.section-order-item');
      if (!item) return;
      item.classList.remove('dragging');
      syncSectionOrder();
    });

    sectionOrderList.addEventListener('dragover', event => {
      event.preventDefault();

      const draggingItem = sectionOrderList.querySelector('.dragging');
      if (!draggingItem) return;

      const afterElement = getDragAfterElement(sectionOrderList, event.clientY);

      if (afterElement === null) {
        sectionOrderList.appendChild(draggingItem);
      } else {
        sectionOrderList.insertBefore(draggingItem, afterElement);
      }
    });

    syncSectionOrder();
  }

  fontSelector.addEventListener('change', event => applyFont(event.target.value));
  fontSizeSelector.addEventListener('change', event => applyFontSize(event.target.value));
  spacingSelector.addEventListener('change', event => applySpacing(event.target.value));

  toggleBio.addEventListener('change', event => {
    setPreviewVisibility('bioSection', event.target.checked);
  });

  if (toggleQr) {
    toggleQr.addEventListener('change', event => {
      setQrsumeBrandingVisibility(event.target.checked);
    });
  }

  togglePhoto.addEventListener('change', event => {
    photoWrapper.classList.toggle('is-muted', !event.target.checked);
  });

  toggleEducationDescription.addEventListener('change', event => {
    setEducationDescriptionVisibility(event.target.checked);
  });

  document.querySelectorAll('[data-preview-section]').forEach(toggle => {
    toggle.addEventListener('change', event => {
      setPreviewVisibility(event.target.dataset.previewSection, event.target.checked);
    });
  });

  document.querySelectorAll('.editable-textarea').forEach(textarea => {
    textarea.addEventListener('input', () => resizeTextarea(textarea));
    resizeTextarea(textarea);
  });

  photoPreview.addEventListener('click', triggerPhotoUpload);
  photoPlaceholder.addEventListener('click', triggerPhotoUpload);

  photoInput.addEventListener('change', event => {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      alert('Please upload a valid image file.');
      photoInput.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = loadEvent => {
      photoPreview.src = loadEvent.target.result;
      photoPreview.style.display = 'block';
      photoPlaceholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });

  initializeSectionDragAndDrop();
  applyFont(fontSelector.value);
  applyFontSize(fontSizeSelector.value);
  applySpacing(spacingSelector.value);
  setEducationDescriptionVisibility(toggleEducationDescription.checked);

  if (toggleQr) {
    setQrsumeBrandingVisibility(toggleQr.checked);
  }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
