<?php
declare(strict_types=1);

/**
 * Resume Preview Page
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Load one user's resume data.
 * - Show an A4 sheet laid out like create_pdf.php, split into pages where the PDF breaks.
 * - Let the user edit any text on the sheet, and add, remove or reorder entries.
 * - Let the user choose visible sections and section order.
 * - Let premium users remove QR code and QRsume links from the generated PDF.
 * - Show free users a small preview of the branding they are paying to remove.
 * - Show/hide QRsume link + bottom link + QR icon in the live preview depending on the QR checkbox.
 * - Submit the edited content to create_pdf.php.
 */

include("assets/head.php");
require_once("assets/resume_options.php");

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

    $canRemoveBranding = resumeCanRemoveBranding($db, $userId, (string) ($account['username'] ?? $username));
} catch (PDOException $exception) {
    error_log('Resume preview database error: ' . $exception->getMessage());
    redirectToError();
}

// default.webp is the generic placeholder: treat it as "no photo" (it is never printed in the PDF)
$photoUrl = !empty($personal['personal_photo']) && $personal['personal_photo'] !== 'default.webp'
    ? 'images/' . e($personal['personal_photo'])
    : '';

$sectionOrder = ['education', 'experience', 'skills', 'languages', 'projects'];

foreach ($customSections as $index => $section) {
    $sectionOrder[] = 'custom_' . $index;
}

$defaultSectionOrder = implode(',', $sectionOrder);
$resumeLanguage = resumeLanguage();
$sectionTitles = ['en' => resumeSectionTitles('en'), 'es' => resumeSectionTitles('es')];
$profileUrl = 'https://qrsume.com/' . $username;
$profileText = 'qrsume.com/' . $username;
$profession = resumeRealValue((string) ($personal['personal_profession'] ?? ''));
$webLinks = [];
foreach (['linkedin', 'github'] as $network) {
    $link = resumeRealValue((string) ($contact[$network] ?? ''));
    if ($link !== '') {
        $webLinks[] = [resumeLinkText($link), resumeLinkUrl($link)];
    }
}

// -----------------------------------------------------------------------------
// Sheet rendering
// -----------------------------------------------------------------------------

/**
 * One editable piece of text on the sheet. Its text is posted as $name when the PDF is generated.
 */
function editable(
    string $name,
    ?string $value,
    string $placeholder,
    string $class = '',
    string $tag = 'span',
    bool $multiline = false,
    string $attributes = ''
): string {
    $value = str_replace(["\r\n", "\r"], "\n", (string) $value);

    return sprintf(
        '<%1$s class="ed %2$s" contenteditable="plaintext-only" spellcheck="true" data-name="%3$s" data-placeholder="%4$s"%5$s%6$s>%7$s</%1$s>',
        $tag,
        $class,
        e($name),
        e($placeholder),
        $multiline ? ' data-multiline' : '',
        $attributes !== '' ? ' ' . $attributes : '',
        e(trim($value) === '' ? '' : $value)
    );
}

function entryTools(): string
{
    return '<div class="entry-tools" contenteditable="false">'
        . '<button type="button" data-action="up" title="Move up" aria-label="Move up">&uarr;</button>'
        . '<button type="button" data-action="down" title="Move down" aria-label="Move down">&darr;</button>'
        . '<button type="button" data-action="remove" title="Remove from this resume" aria-label="Remove">&times;</button>'
        . '</div>';
}

function itemRemoveButton(): string
{
    return '<button type="button" class="item-remove" data-action="remove" contenteditable="false" title="Remove" aria-label="Remove">&times;</button>';
}

function sectionHeading(string $key, string $title, string $addType = ''): string
{
    $html = '<h2 class="pb sheet-heading">'
        . editable("section_titles[$key]", $title, 'Section title', 'sheet-heading-text', 'span', false, 'data-default-title="' . e($key) . '"');

    if ($addType !== '') {
        $html .= '<button type="button" class="sheet-add" data-add="' . e($addType) . '" contenteditable="false">+ Add</button>';
    }

    return $html . '</h2>';
}

function renderEducationEntry(string $i, array $edu): string
{
    return '<div class="sheet-entry" data-entry>' . entryTools()
        . editable("education[$i][name_of_studies]", $edu['name_of_studies'] ?? '', 'Degree / studies', 'pb sheet-text sheet-entry-title', 'div')
        . '<div class="pb sheet-text sheet-meta">'
        . editable("education[$i][place_of_study]", $edu['place_of_study'] ?? '', 'Institution')
        . '<span class="meta-sep"> | </span>'
        . editable("education[$i][date]", $edu['date'] ?? '', 'Dates')
        . '</div>'
        . editable("education[$i][desc]", $edu['brief_description'] ?? '', 'Description', 'pb sheet-text edu-desc', 'div', true)
        . '</div>';
}

function renderExperienceEntry(string $i, array $exp): string
{
    return '<div class="sheet-entry" data-entry>' . entryTools()
        . '<div class="pb sheet-text sheet-entry-title">'
        . editable("experience[$i][job_name]", $exp['job_name'] ?? '', 'Job title')
        . '<span class="job-sep"> - </span>'
        . editable("experience[$i][place_of_work]", $exp['place_of_work'] ?? '', 'Company')
        . '</div>'
        . editable("experience[$i][date]", $exp['date'] ?? '', 'Dates', 'pb sheet-text sheet-meta', 'div')
        . editable("experience[$i][brief_description]", $exp['brief_description'] ?? '', 'Describe your responsibilities and achievements', 'pb sheet-text sheet-indented', 'div', true)
        . '</div>';
}

// Skills and languages are one comma-separated paragraph, like the PDF
function renderSkillItem(string $i, array $skill): string
{
    return '<span class="sheet-inline-item" data-entry>' . itemRemoveButton()
        . editable("skills[$i][aptitude]", $skill['aptitude'] ?? '', 'Skill')
        . '<span class="inline-sep">, </span></span>';
}

function renderLanguageItem(string $i, array $lang): string
{
    return '<span class="sheet-inline-item" data-entry>' . itemRemoveButton()
        . editable("languages[$i][language]", $lang['language'] ?? '', 'Language')
        . '<span class="level-open"> (</span>'
        . editable("languages[$i][level]", $lang['level'] ?? '', 'Level')
        . '<span class="level-close">)</span><span class="inline-sep">, </span></span>';
}

function renderProjectEntry(string $i, array $project): string
{
    return '<div class="sheet-entry" data-entry>' . entryTools()
        . '<div class="pb sheet-text sheet-project-title">• '
        . editable("projects[$i][interest]", $project['interest'] ?? '', 'Project title')
        . '</div>'
        . editable("projects[$i][description]", $project['description'] ?? '', 'Project description', 'pb sheet-html', 'div')
        . '</div>';
}

?>

<style>
  :root {
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
    max-width: 595.28pt;
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

  .page-badge {
    flex: 0 0 auto;
    padding: .25rem .65rem;
    border: 1px solid #bbf7d0;
    border-radius: 999px;
    background: #f0fdf4;
    color: #166534;
    font-size: .8rem;
    font-weight: 700;
    white-space: nowrap;
  }

  .page-badge.is-multi {
    border-color: #fde68a;
    background: #fffbeb;
    color: #92400e;
  }

  .page-badge.is-checking {
    opacity: .65;
  }

  /*
   * The sheet mirrors create_pdf.php in PDF points: A4 page, margins 40/20/30pt,
   * 2.835pt cell padding, line height = font size x 1.25, and the same minimum
   * cell heights and spacing. Elements marked .pb are the blocks TCPDF moves to
   * the next page as a whole; the script inserts the page breaks between them.
   */
  .sheet-stack {
    --c: 10pt;
    --sp: 10pt;
    --pad: 2.835pt;
    --space-section: calc(var(--sp) * .5);
    --space-title: calc(var(--sp) * .4);
    --space-content: calc(var(--sp) * .3);
    --space-list: calc(var(--sp) * .1);
    --sheet-font: 'Times New Roman', Times, 'Liberation Serif', serif;
    position: relative;
    width: 595.28pt;
    min-height: 841.89pt;
    margin: 0 auto;
    color: #000;
  }

  .sheet-stack[data-font="helvetica"] {
    --sheet-font: Helvetica, Arial, 'Liberation Sans', sans-serif;
  }

  .sheet-page {
    position: absolute;
    left: 0;
    width: 100%;
    height: 841.89pt;
    background: #fff;
    box-shadow: 0 0 14px rgba(0, 0, 0, .13);
  }

  .sheet-page-label {
    position: absolute;
    top: 5px;
    right: 8px;
    color: #adb5bd;
    font: 600 11px/1 system-ui, sans-serif;
  }

  .sheet-page.is-extra .sheet-page-label {
    color: #b45309;
  }

  .sheet-content {
    position: relative;
    z-index: 1;
    padding: 20pt 30pt 0 40pt;
    font-family: var(--sheet-font);
    font-size: var(--c);
    line-height: 1.25;
  }

  .sheet-section,
  .sheet-entry,
  .sheet-list {
    display: flow-root;
  }

  .sheet-section {
    position: relative;
    margin-bottom: var(--space-section);
  }

  .sheet-section.is-empty {
    display: none;
  }

  .sheet-entry {
    position: relative;
    margin-bottom: var(--space-content);
  }

  /* Header */
  .sheet-header {
    position: relative;
    text-align: center;
  }

  .sheet-header.has-photo {
    display: flex;
    min-height: 83pt;
    text-align: left;
  }

  .sheet-photo {
    display: none;
    flex: 0 0 auto;
    width: 66pt;
    height: 78pt;
    padding: 0;
    border: 0;
    background: none;
    cursor: pointer;
  }

  .has-photo .sheet-photo {
    display: block;
  }

  .sheet-photo img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    outline: .75pt solid #cfcfcf;
    outline-offset: -.375pt;
  }

  .sheet-photo-add {
    position: absolute;
    top: 0;
    right: calc(100% + 4px);
    display: none;
    padding: 2px 6px;
    border: 1px dashed #adb5bd;
    border-radius: 6px;
    background: #fff;
    color: #6c757d;
    font: 600 11px/1.4 system-ui, sans-serif;
    white-space: nowrap;
  }

  .sheet-header.can-add-photo:hover .sheet-photo-add,
  .sheet-header.can-add-photo:focus-within .sheet-photo-add {
    display: block;
  }

  .sheet-header-text {
    flex: 1;
    min-width: 0;
  }

  .has-photo .sheet-header-text {
    margin-left: 12pt;
  }

  .sheet-name {
    padding: 0 var(--pad);
    font-size: calc(var(--c) * 2.5);
    font-weight: 700;
    line-height: 1.25;
    white-space: nowrap;
  }

  .sheet-contact {
    height: calc(var(--c) + 4pt);
    padding: 0 var(--pad);
    line-height: calc(var(--c) + 4pt);
    white-space: nowrap;
  }

  .sheet-link {
    color: #00f;
    text-decoration: none;
  }

  /* Text blocks (TCPDF MultiCell: min height, top aligned, wraps inside the cell padding) */
  .sheet-text {
    min-height: 15pt;
    padding: 0 var(--pad);
    overflow-wrap: anywhere;
  }

  .sheet-text[data-multiline] {
    white-space: pre-wrap;
  }

  .sheet-bio {
    margin-top: 10pt;
  }

  .sheet-indented {
    width: 446.46pt;
    min-height: 20pt;
    margin-left: 10pt;
  }

  .edu-desc {
    margin-top: 1pt;
  }

  .hide-edu-desc .edu-desc {
    display: none;
  }

  /* Project descriptions go through writeHTML: no padding, no minimum height, newlines collapse */
  .sheet-html {
    min-height: calc(var(--c) * 1.25);
    overflow-wrap: anywhere;
  }

  .sheet-project-title {
    font-size: calc(var(--c) * 1.1);
    font-weight: 700;
  }

  /* Section heading: Cell(0, 20) plus a 1pt rule */
  .sheet-heading {
    position: relative;
    height: 20pt;
    margin: 0 0 var(--space-title);
    padding: 0 var(--pad);
    font-family: inherit;
    font-size: calc(var(--c) * 1.3);
    font-weight: 700;
    line-height: 20pt;
    text-transform: uppercase;
    white-space: nowrap;
  }

  .sheet-heading::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -.5pt;
    height: 1pt;
    background: #000;
  }

  /* Entry title (MultiCell, bold) and the grey line under it (dates, school) */
  .sheet-entry-title {
    font-size: calc(var(--c) * 1.1);
    font-weight: 700;
  }

  .sheet-meta {
    color: #505050;
  }

  .sheet-experience .sheet-meta {
    margin-bottom: var(--space-list);
  }

  .sheet-profession {
    padding: 0 var(--pad);
    color: #3c3c3c;
    font-size: calc(var(--c) * 1.2);
    line-height: 1.25;
    white-space: nowrap;
  }

  /* Skills and languages: one comma-separated paragraph */
  .sheet-inline-item {
    position: relative;
  }

  .sheet-inline-item:last-child .inline-sep {
    display: none;
  }

  /* Editable text */
  .ed {
    border-radius: 2px;
    outline: none;
    transition: background-color .15s ease;
  }

  .ed:hover {
    background: rgba(37, 99, 235, .06);
  }

  .ed:focus {
    background: rgba(37, 99, 235, .1);
    box-shadow: 0 0 0 1px rgba(37, 99, 235, .35);
  }

  .ed:empty::before {
    content: attr(data-placeholder);
    color: #adb5bd;
    text-transform: none;
  }

  /* Hover tools: they float in the page margin so they never change the layout */
  .entry-tools {
    position: absolute;
    top: 0;
    right: calc(100% + 3px);
    display: flex;
    gap: 2px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .15s ease;
  }

  .sheet-entry:hover > .entry-tools,
  .sheet-entry:focus-within > .entry-tools {
    opacity: 1;
    pointer-events: auto;
  }

  .entry-tools button,
  .item-remove {
    width: 18px;
    height: 18px;
    padding: 0;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #fff;
    color: #495057;
    font: 600 12px/1 system-ui, sans-serif;
    cursor: pointer;
  }

  .entry-tools button:hover,
  .item-remove:hover {
    border-color: #adb5bd;
    color: #000;
  }

  .entry-tools button[data-action="remove"]:hover,
  .item-remove:hover {
    border-color: #fca5a5;
    color: #b91c1c;
  }

  .item-remove {
    position: absolute;
    top: -15px;
    left: 0;
    width: 16px;
    height: 16px;
    font-size: 11px;
    opacity: 0;
    pointer-events: none;
  }

  .sheet-inline-item:hover .item-remove,
  .sheet-inline-item:focus-within .item-remove {
    opacity: 1;
    pointer-events: auto;
  }

  .sheet-add {
    position: absolute;
    top: 50%;
    right: var(--pad);
    padding: 1px 7px;
    transform: translateY(-50%);
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font: 600 11px/1.4 system-ui, sans-serif;
    text-transform: none;
    opacity: 0;
    cursor: pointer;
    transition: opacity .15s ease;
  }

  .sheet-section:hover .sheet-add,
  .sheet-section:focus-within .sheet-add {
    opacity: 1;
  }

  /* QRsume branding, drawn on the last page exactly where the PDF puts it */
  .sheet-branding {
    position: absolute;
    left: 0;
    z-index: 2;
    width: 100%;
    height: 841.89pt;
    pointer-events: none;
    font-family: Helvetica, Arial, sans-serif;
  }

  .sheet-qr {
    position: absolute;
    top: 771.89pt;
    left: 520.28pt;
    width: 60pt;
    height: 60pt;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #111;
    font-size: 54pt;
    line-height: 1;
  }

  .sheet-qr-label,
  .sheet-bottom-link {
    position: absolute;
    top: 828pt;
    font-size: 8pt;
    line-height: 1;
    white-space: nowrap;
    transform: translateX(-50%);
  }

  .sheet-qr-label {
    left: 540.28pt;
  }

  .sheet-bottom-link {
    left: 50%;
    color: #00f;
    text-decoration: underline;
  }

  .pb-spacer {
    pointer-events: none;
  }

  /* Parts that only show while editing and are not printed */
  .is-ghost {
    color: #adb5bd;
  }

  .sheet-inline-item:not(:hover):not(:focus-within) .is-ghost,
  .sheet-inline-item:not(:hover):not(:focus-within) .is-ghost-field {
    display: none;
  }

  .sidebar-add {
    flex: 0 0 auto;
    margin-left: auto;
    margin-right: .4rem;
    padding: .05rem .45rem;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: .72rem;
    font-weight: 700;
  }

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
    .preview-topbar,
    .entry-tools,
    .item-remove,
    .sheet-add,
    .sheet-page-label {
      display: none !important;
    }

    .resume-builder {
      display: block;
    }

    .builder-preview-area {
      padding: 0;
      overflow: visible;
    }

    .sheet-page {
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
          <label for="resumeLanguage">Resume language</label>
          <select name="lan" id="resumeLanguage" class="form-select">
            <option value="en"<?= $resumeLanguage === 'en' ? ' selected' : '' ?>>English</option>
            <option value="es"<?= $resumeLanguage === 'es' ? ' selected' : '' ?>>Español</option>
          </select>
        </div>
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
            <small>Keep the photo in the PDF. Click the photo on the page to upload a new one.</small>
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
            <button type="button" class="sidebar-add" data-add="education" hidden>+ Add</button>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <div class="section-order-item" draggable="true" data-section="experience">
            <label class="section-order-label" for="showExperience">
              <input type="checkbox" name="show_experience" id="showExperience" data-preview-section="experienceSection" checked>
              <span>Work experience</span>
            </label>
            <button type="button" class="sidebar-add" data-add="experience" hidden>+ Add</button>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <div class="section-order-item" draggable="true" data-section="skills">
            <label class="section-order-label" for="showSkills">
              <input type="checkbox" name="show_skills" id="showSkills" data-preview-section="skillsSection" checked>
              <span>Skills</span>
            </label>
            <button type="button" class="sidebar-add" data-add="skills" hidden>+ Add</button>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <div class="section-order-item" draggable="true" data-section="languages">
            <label class="section-order-label" for="showLanguages">
              <input type="checkbox" name="show_languages" id="showLanguages" data-preview-section="languagesSection" checked>
              <span>Languages</span>
            </label>
            <button type="button" class="sidebar-add" data-add="languages" hidden>+ Add</button>
            <span class="section-order-handle" title="Drag to reorder">☰</span>
          </div>

          <div class="section-order-item" draggable="true" data-section="projects">
            <label class="section-order-label" for="showProjects">
              <input type="checkbox" name="show_projects" id="showProjects" data-preview-section="projectsSection" checked>
              <span>Projects</span>
            </label>
            <button type="button" class="sidebar-add" data-add="projects" hidden>+ Add</button>
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
          <button type="submit" class="btn btn-primary" formaction="https://qrsume.com/create_pdf.php" formtarget="_blank">
            Generate PDF
          </button>
          <button type="submit" class="btn btn-outline-secondary" formaction="https://qrsume.com/create_latex.php" formtarget="_blank">
            Download LaTeX (.tex)
          </button>
        </div>
        <p class="sidebar-note">Page breaks in the preview follow the PDF layout, and the page count is checked against the real PDF.</p>
      </section>
    </aside>

    <section class="builder-preview-area" aria-label="Editable resume preview">
      <div class="preview-topbar">
        <span><strong>Live preview</strong> — click any text to edit it. Hover an entry to move or remove it.</span>
        <span class="page-badge" id="pageBadge" role="status">1 page</span>
      </div>

      <div class="sheet-stack" id="sheetStack" data-font="times">
        <div class="sheet-pages" id="sheetPages" aria-hidden="true"></div>

        <article class="sheet-content" id="resumePreview" aria-label="Resume preview">
          <header class="pb sheet-header" id="sheetHeader">
            <input type="file" id="photoInput" name="photo_upload" accept="image/*" hidden>
            <input type="hidden" name="existing_photo_url" value="<?= $photoUrl ?>">
            <button type="button" class="sheet-photo" id="photoButton" title="Click to change photo">
              <img id="photoPreview" src="<?= $photoUrl ?>" alt="Profile photo" <?= $photoUrl ? '' : 'hidden' ?>>
            </button>
            <button type="button" class="sheet-photo-add" id="photoAddButton">+ Photo</button>

            <div class="sheet-header-text">
              <div class="sheet-name">
                <?= editable('personal_name', $personal['personal_name'] ?? '', 'Name') ?>
                <?= editable('personal_lastname', $personal['personal_lastname'] ?? '', 'Last name') ?>
              </div>
              <?= editable('personal_profession', $profession, 'Job title (optional)', 'sheet-profession', 'div') ?>
              <div class="sheet-contact"><?= editable('email', $contact['email'] ?? $account['email'] ?? '', 'Email') ?><span id="contactSeparator"> | </span><?= editable('phone_number', $contact['phone_number'] ?? '', 'Phone number') ?></div>
              <div class="sheet-contact" id="webLinks" data-count="<?= count($webLinks) ?>" title="LinkedIn and GitHub come from your profile (create_resume form, Contact step)"><?php foreach ($webLinks as $index => [$linkText, $linkUrl]): ?><?= $index > 0 ? '<span> | </span>' : '' ?><a class="sheet-link" href="<?= e($linkUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($linkText) ?></a><?php endforeach; ?><span id="linkSeparator" class="qrsume-branding-preview-item"> | </span><a id="qrsumePreviewLink" class="sheet-link qrsume-branding-preview-item" href="<?= e($profileUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($profileText) ?></a></div>
            </div>
          </header>

          <section class="sheet-section sheet-bio" id="bioSection">
            <?= sectionHeading('summary', $sectionTitles[$resumeLanguage]['summary']) ?>
            <?= editable('personal_bio', $personal['personal_bio'] ?? '', 'Write a short professional bio...', 'pb sheet-text', 'div', true) ?>
          </section>

          <section class="sheet-section" id="educationSection" data-resume-section="education">
            <?= sectionHeading('education', $sectionTitles[$resumeLanguage]['education'], 'education') ?>
            <div class="sheet-list" data-list="education" data-next-index="<?= count($education) ?>">
              <?php foreach ($education as $index => $edu): ?>
                <?= renderEducationEntry((string) $index, $edu) ?>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="sheet-section sheet-experience" id="experienceSection" data-resume-section="experience">
            <?= sectionHeading('experience', $sectionTitles[$resumeLanguage]['experience'], 'experience') ?>
            <div class="sheet-list" data-list="experience" data-next-index="<?= count($experience) ?>">
              <?php foreach ($experience as $index => $exp): ?>
                <?= renderExperienceEntry((string) $index, $exp) ?>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="sheet-section" id="skillsSection" data-resume-section="skills">
            <?= sectionHeading('skills', $sectionTitles[$resumeLanguage]['skills'], 'skills') ?>
            <div class="pb sheet-text sheet-list sheet-inline" data-list="skills" data-next-index="<?= count($skills) ?>">
              <?= implode('', array_map(fn($index, $skill) => renderSkillItem((string) $index, $skill), array_keys($skills), $skills)) ?>
            </div>
          </section>

          <section class="sheet-section" id="languagesSection" data-resume-section="languages">
            <?= sectionHeading('languages', $sectionTitles[$resumeLanguage]['languages'], 'languages') ?>
            <div class="pb sheet-text sheet-list sheet-inline" data-list="languages" data-next-index="<?= count($languages) ?>">
              <?= implode('', array_map(fn($index, $lang) => renderLanguageItem((string) $index, $lang), array_keys($languages), $languages)) ?>
            </div>
          </section>

          <section class="sheet-section" id="projectsSection" data-resume-section="projects">
            <?= sectionHeading('projects', $sectionTitles[$resumeLanguage]['projects'], 'projects') ?>
            <div class="sheet-list" data-list="projects" data-next-index="<?= count($projects) ?>">
              <?php foreach ($projects as $index => $project): ?>
                <?= renderProjectEntry((string) $index, $project) ?>
              <?php endforeach; ?>
            </div>
          </section>

          <?php foreach ($customSections as $index => $section): ?>
            <section class="sheet-section" id="customSection<?= $index ?>" data-resume-section="custom_<?= $index ?>">
              <h2 class="pb sheet-heading"><?= editable("custom_sections[$index][section_title]", $section['section_title'] ?? '', 'Section title', 'sheet-custom-title', 'span', false, 'data-custom-index="' . $index . '"') ?></h2>
              <?= editable("custom_sections[$index][section_content]", $section['section_content'] ?? '', 'Section content', 'pb sheet-text', 'div', true) ?>
            </section>
          <?php endforeach; ?>
        </article>

        <div class="sheet-branding qrsume-branding-preview-item" id="sheetBranding" aria-hidden="true">
          <div class="sheet-qr"><i class="bi bi-qr-code"></i></div>
          <span class="sheet-qr-label" data-section-title="full_profile"><?= e($sectionTitles[$resumeLanguage]['full_profile']) ?></span>
          <span class="sheet-bottom-link"><?= e($profileText) ?></span>
        </div>
      </div>
    </section>
  </main>

  <div id="editableSync" hidden></div>
</form>

<template id="tpl-education"><?= renderEducationEntry('__I__', []) ?></template>
<template id="tpl-experience"><?= renderExperienceEntry('__I__', []) ?></template>
<template id="tpl-skills"><?= renderSkillItem('__I__', []) ?></template>
<template id="tpl-languages"><?= renderLanguageItem('__I__', []) ?></template>
<template id="tpl-projects"><?= renderProjectEntry('__I__', []) ?></template>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // PDF geometry (create_pdf.php), converted to CSS px
  const PT = 4 / 3;
  const PAGE_HEIGHT = 841.89 * PT;
  const PAGE_TOP = 20 * PT;                // top margin
  const PAGE_BREAK_AT = (841.89 - 15) * PT; // SetAutoPageBreak(true, 15)
  const PAGE_GAP = 24;
  const PAGE_STRIDE = PAGE_HEIGHT + PAGE_GAP;

  const form = document.getElementById('resumeForm');
  const stack = document.getElementById('sheetStack');
  const pagesLayer = document.getElementById('sheetPages');
  const resumePreview = document.getElementById('resumePreview');
  const sheetHeader = document.getElementById('sheetHeader');
  const sheetBranding = document.getElementById('sheetBranding');
  const pageBadge = document.getElementById('pageBadge');
  const editableSync = document.getElementById('editableSync');
  const languageSelector = document.getElementById('resumeLanguage');
  const fontSelector = document.getElementById('font');
  const fontSizeSelector = document.getElementById('contentFontSize');
  const spacingSelector = document.getElementById('spaceSize');
  const togglePhoto = document.getElementById('togglePhoto');
  const toggleBio = document.getElementById('toggleBio');
  const toggleQr = document.getElementById('toggleQr');
  const toggleEducationDescription = document.getElementById('showEducationDescription');
  const photoInput = document.getElementById('photoInput');
  const photoPreview = document.getElementById('photoPreview');
  const sectionOrderList = document.getElementById('sectionOrderList');
  const sectionOrderInput = document.getElementById('sectionOrderInput');
  const contactSeparator = document.getElementById('contactSeparator');
  const linkSeparator = document.getElementById('linkSeparator');
  const sectionTitles = <?= json_encode($sectionTitles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  let hasPhoto = photoPreview.getAttribute('src') !== '';
  let pendingPhoto = false;

  // ---------------------------------------------------------------------------
  // Editable text
  // ---------------------------------------------------------------------------

  const supportsPlaintextOnly = (() => {
    const probe = document.createElement('div');
    probe.contentEditable = 'plaintext-only';
    return probe.contentEditable === 'plaintext-only';
  })();

  function setupEditables(root) {
    if (supportsPlaintextOnly) return;
    root.querySelectorAll('.ed').forEach(element => {
      element.contentEditable = 'true';
    });
  }

  function fieldValue(element) {
    // innerText keeps the line breaks of multi-line fields; headings use textContent to skip text-transform
    return element.hasAttribute('data-multiline')
      ? element.innerText.replace(/ /g, ' ').replace(/\s+$/, '')
      : element.textContent.replace(/ /g, ' ').trim();
  }

  function fieldByName(name) {
    return resumePreview.querySelector(`.ed[data-name="${CSS.escape(name)}"]`);
  }

  // Copy every editable field into hidden inputs so the normal form post carries them
  function syncEditableFields() {
    editableSync.replaceChildren();
    resumePreview.querySelectorAll('.ed[data-name]').forEach(element => {
      if (element.classList.contains('sheet-heading-text') && element.dataset.custom !== '1') return;

      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = element.dataset.name;
      input.value = fieldValue(element);
      editableSync.append(input);
    });
  }

  resumePreview.addEventListener('keydown', event => {
    const field = event.target.closest('.ed');
    if (field && event.key === 'Enter' && !field.hasAttribute('data-multiline')) {
      event.preventDefault();
      field.blur();
    }
  });

  resumePreview.addEventListener('paste', event => {
    const field = event.target.closest('.ed');
    if (!field) return;

    event.preventDefault();
    let text = event.clipboardData.getData('text/plain');
    if (!field.hasAttribute('data-multiline')) {
      text = text.replace(/\s*[\r\n]+\s*/g, ' ');
    }
    document.execCommand('insertText', false, text);
  });

  resumePreview.addEventListener('input', event => {
    const field = event.target.closest('.ed');
    if (!field) return;

    // Leave the field truly empty so its placeholder shows again
    if (field.textContent === '' && field.innerHTML !== '') {
      field.innerHTML = '';
    }

    if (field.classList.contains('sheet-heading-text')) {
      field.dataset.custom = '1';
    }

    if (field.dataset.customIndex !== undefined) {
      const label = document.querySelector(`[data-section="custom_${field.dataset.customIndex}"] .section-order-label span`);
      if (label) label.textContent = fieldValue(field) || 'Untitled Section';
    }

    contentChanged();
  });

  // ---------------------------------------------------------------------------
  // Entries: add, remove, move
  // ---------------------------------------------------------------------------

  function addEntry(type) {
    const list = resumePreview.querySelector(`[data-list="${type}"]`);
    const template = document.getElementById(`tpl-${type}`);
    if (!list || !template) return;

    const index = Number(list.dataset.nextIndex || 0);
    list.dataset.nextIndex = String(index + 1);

    const holder = document.createElement('div');
    holder.innerHTML = template.innerHTML.replaceAll('__I__', String(index)).trim();
    const entry = holder.firstElementChild;
    setupEditables(entry);
    list.append(entry);

    // Adding to a hidden section makes no sense: show it
    const toggle = document.querySelector(`[data-section="${type}"] input[type="checkbox"]`);
    if (toggle && !toggle.checked) {
      toggle.checked = true;
      toggle.dispatchEvent(new Event('change'));
    }

    contentChanged();
    entry.querySelector('.ed').focus();
  }

  function entrySiblings(entry) {
    const list = entry.closest('.sheet-list');
    return [...list.querySelectorAll('[data-entry]')];
  }

  resumePreview.addEventListener('click', event => {
    const addButton = event.target.closest('[data-add]');
    if (addButton) {
      addEntry(addButton.dataset.add);
      return;
    }

    const actionButton = event.target.closest('[data-action]');
    if (!actionButton) return;

    const entry = actionButton.closest('[data-entry]');
    const list = entry.closest('.sheet-list');
    const siblings = entrySiblings(entry);
    const position = siblings.indexOf(entry);

    if (actionButton.dataset.action === 'remove') {
      entry.remove();
    } else if (actionButton.dataset.action === 'up' && position > 0) {
      siblings[position - 1].before(entry);
    } else if (actionButton.dataset.action === 'down' && position < siblings.length - 1) {
      siblings[position + 1].after(entry);
    }

    contentChanged();
  });

  document.querySelectorAll('.sidebar-add').forEach(button => {
    button.addEventListener('click', () => addEntry(button.dataset.add));
  });

  // A section with no entries is not printed, so it is hidden here too (it can be re-added from the sidebar)
  function updateEmptySections() {
    resumePreview.querySelectorAll('[data-list]').forEach(list => {
      const isEmpty = !list.querySelector('[data-entry]');
      list.closest('.sheet-section').classList.toggle('is-empty', isEmpty);

      const sidebarAdd = document.querySelector(`.sidebar-add[data-add="${list.dataset.list}"]`);
      if (sidebarAdd) sidebarAdd.hidden = !isEmpty;
    });
  }

  // ---------------------------------------------------------------------------
  // Header
  // ---------------------------------------------------------------------------

  function brandingIsShown() {
    return !toggleQr || toggleQr.checked;
  }

  function updateSeparators() {
    const email = fieldValue(fieldByName('email'));
    const phone = fieldValue(fieldByName('phone_number'));
    contactSeparator.hidden = !(email && phone);

    // Second contact line: LinkedIn, GitHub and the QRsume link
    const webLinks = document.getElementById('webLinks');
    const profileLinks = Number(webLinks.dataset.count || 0);
    linkSeparator.hidden = !(brandingIsShown() && profileLinks > 0);
    webLinks.hidden = !(profileLinks > 0 || brandingIsShown());

    resumePreview.querySelectorAll('.meta-sep').forEach(separator => {
      separator.hidden = !(fieldValue(separator.previousElementSibling) && fieldValue(separator.nextElementSibling));
    });

    // An empty level shows as a placeholder while editing, without the brackets the PDF leaves out
    resumePreview.querySelectorAll('.level-open').forEach(open => {
      const level = open.nextElementSibling;
      const isEmpty = fieldValue(level) === '';
      open.classList.toggle('is-ghost', isEmpty);
      open.parentElement.querySelector('.level-close').classList.toggle('is-ghost', isEmpty);
      level.classList.toggle('is-ghost-field', isEmpty);
    });

    resumePreview.querySelectorAll('.job-sep').forEach(separator => {
      const [job, company] = separator.parentElement.querySelectorAll('.ed');
      separator.hidden = !(fieldValue(job) && fieldValue(company));
    });
  }

  function updateHeaderLayout() {
    const showPhoto = togglePhoto.checked && hasPhoto;
    sheetHeader.classList.toggle('has-photo', showPhoto);
    sheetHeader.classList.toggle('can-add-photo', togglePhoto.checked && !hasPhoto);
  }

  function choosePhoto() {
    photoInput.click();
  }

  document.getElementById('photoButton').addEventListener('click', choosePhoto);
  document.getElementById('photoAddButton').addEventListener('click', choosePhoto);

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
      photoPreview.hidden = false;
      hasPhoto = true;
      pendingPhoto = true;
      updateHeaderLayout();
      contentChanged();
    };
    reader.readAsDataURL(file);
  });

  // ---------------------------------------------------------------------------
  // Pages
  // ---------------------------------------------------------------------------

  let estimatedPages = 1;
  let exactPages = null;
  let layoutQueued = false;

  // A short timer rather than requestAnimationFrame, which never fires in a background tab
  function scheduleLayout() {
    if (layoutQueued) return;
    layoutQueued = true;
    setTimeout(() => {
      layoutQueued = false;
      layoutPages();
    }, 30);
  }

  // Lay the content out the way TCPDF does: a block that would cross the bottom margin
  // (15pt) starts on the next page, below its 20pt top margin.
  function layoutPages() {
    resumePreview.querySelectorAll('.pb-spacer').forEach(spacer => spacer.remove());

    // Next to the photo, the name and contact lines are centered vertically on its 78pt height
    const headerText = sheetHeader.querySelector('.sheet-header-text');
    headerText.style.paddingTop = '';
    if (sheetHeader.classList.contains('has-photo')) {
      headerText.style.paddingTop = `${Math.max(0, (78 * PT - headerText.offsetHeight) / 2)}px`;
    }

    const origin = stack.getBoundingClientRect().top;
    let contentBottom = 0;

    resumePreview.querySelectorAll('.pb').forEach(block => {
      if (!block.offsetParent) return;

      const rect = block.getBoundingClientRect();
      const top = rect.top - origin;
      const pageTop = Math.max(0, Math.floor(top / PAGE_STRIDE)) * PAGE_STRIDE;

      if (rect.bottom - origin > pageTop + PAGE_BREAK_AT + 0.5 && top > pageTop + PAGE_TOP + 0.5) {
        const spacer = document.createElement('div');
        spacer.className = 'pb-spacer';
        spacer.style.height = `${pageTop + PAGE_STRIDE + PAGE_TOP - top}px`;
        block.before(spacer);
      }

      contentBottom = Math.max(contentBottom, block.getBoundingClientRect().bottom - origin);
    });

    estimatedPages = Math.max(1, Math.floor((contentBottom - 0.5) / PAGE_STRIDE) + 1);
    renderPages(estimatedPages);
    updateBadge();
  }

  function renderPages(count) {
    const pages = [];
    for (let i = 0; i < count; i++) {
      const page = document.createElement('div');
      page.className = 'sheet-page' + (i > 0 ? ' is-extra' : '');
      page.style.top = `${i * PAGE_STRIDE}px`;

      if (count > 1) {
        const label = document.createElement('span');
        label.className = 'sheet-page-label';
        label.textContent = `Page ${i + 1} of ${count}`;
        page.append(label);
      }

      pages.push(page);
    }

    pagesLayer.replaceChildren(...pages);
    stack.style.height = `${count * PAGE_STRIDE - PAGE_GAP}px`;
    sheetBranding.style.top = `${(count - 1) * PAGE_STRIDE}px`;
  }

  function updateBadge() {
    const pages = exactPages ?? estimatedPages;
    pageBadge.textContent = pages === 1 ? '1 page' : `${pages} pages`;
    pageBadge.classList.toggle('is-multi', pages > 1);
    pageBadge.classList.toggle('is-checking', exactPages === null);
    pageBadge.title = exactPages === null
      ? 'Estimated from the preview, checking against the PDF…'
      : 'Checked against the generated PDF';
  }

  // Ask the server how many pages the real PDF has (the preview can differ by a line in rare cases)
  let checkTimer = null;
  let checkSequence = 0;

  function scheduleExactCheck() {
    clearTimeout(checkTimer);
    exactPages = null;
    updateBadge();
    checkTimer = setTimeout(checkExactPages, 1200);
  }

  async function checkExactPages() {
    const sequence = ++checkSequence;
    syncEditableFields();

    const data = new FormData(form);
    data.delete('photo_upload');
    if (pendingPhoto) data.set('photo_pending', '1');

    try {
      const response = await fetch('create_pdf.php?page_count=1', { method: 'POST', body: data, credentials: 'same-origin' });
      if (!response.ok) return;

      const result = await response.json();
      if (sequence === checkSequence && Number.isInteger(result.pages)) {
        exactPages = result.pages;
        updateBadge();
      }
    } catch (error) {
      // Keep the estimate when the check is not available
    }
  }

  function contentChanged() {
    updateSeparators();
    updateEmptySections();
    scheduleLayout();
    scheduleExactCheck();
  }

  // ---------------------------------------------------------------------------
  // Style options
  // ---------------------------------------------------------------------------

  function applyLanguage(language) {
    const titles = sectionTitles[language] || sectionTitles.en;
    resumePreview.querySelectorAll('.sheet-heading-text[data-default-title]').forEach(heading => {
      if (heading.dataset.custom !== '1') {
        heading.textContent = titles[heading.dataset.defaultTitle];
      }
    });
    document.querySelectorAll('[data-section-title]').forEach(element => {
      element.textContent = titles[element.dataset.sectionTitle] || element.textContent;
    });
  }

  function setPreviewVisibility(sectionId, shouldShow) {
    const section = document.getElementById(sectionId);
    if (section) section.classList.toggle('is-hidden-in-preview', !shouldShow);
  }

  function setBrandingVisibility(shouldShow) {
    document.querySelectorAll('.qrsume-branding-preview-item').forEach(item => {
      item.classList.toggle('is-hidden-in-preview', !shouldShow);
    });
  }

  languageSelector.addEventListener('change', event => {
    applyLanguage(event.target.value);
    contentChanged();
  });

  fontSelector.addEventListener('change', event => {
    stack.dataset.font = event.target.value;
    contentChanged();
  });

  fontSizeSelector.addEventListener('change', event => {
    stack.style.setProperty('--c', `${Number(event.target.value)}pt`);
    contentChanged();
  });

  spacingSelector.addEventListener('change', event => {
    stack.style.setProperty('--sp', `${Number(event.target.value)}pt`);
    contentChanged();
  });

  toggleBio.addEventListener('change', event => {
    setPreviewVisibility('bioSection', event.target.checked);
    contentChanged();
  });

  togglePhoto.addEventListener('change', () => {
    updateHeaderLayout();
    contentChanged();
  });

  toggleEducationDescription.addEventListener('change', event => {
    resumePreview.classList.toggle('hide-edu-desc', !event.target.checked);
    contentChanged();
  });

  if (toggleQr) {
    toggleQr.addEventListener('change', event => {
      setBrandingVisibility(event.target.checked);
      contentChanged();
    });
  }

  document.querySelectorAll('[data-preview-section]').forEach(toggle => {
    toggle.addEventListener('change', event => {
      setPreviewVisibility(event.target.dataset.previewSection, event.target.checked);
      contentChanged();
    });
  });

  // ---------------------------------------------------------------------------
  // Section order (drag and drop in the sidebar)
  // ---------------------------------------------------------------------------

  function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.section-order-item:not(.dragging)')];

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
    return [...sectionOrderList.querySelectorAll('.section-order-item')]
      .map(item => item.dataset.section)
      .filter(Boolean);
  }

  function syncSectionOrder() {
    const order = getCurrentSectionOrder();
    sectionOrderInput.value = order.join(',');

    order.forEach(sectionKey => {
      const section = resumePreview.querySelector(`[data-resume-section="${sectionKey}"]`);
      if (section) resumePreview.appendChild(section);
    });

    contentChanged();
  }

  sectionOrderList.addEventListener('dragstart', event => {
    const item = event.target.closest('.section-order-item');
    if (item) item.classList.add('dragging');
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

  // ---------------------------------------------------------------------------
  // Start
  // ---------------------------------------------------------------------------

  form.addEventListener('submit', syncEditableFields);

  setupEditables(resumePreview);
  stack.dataset.font = fontSelector.value;
  stack.style.setProperty('--c', `${Number(fontSizeSelector.value)}pt`);
  stack.style.setProperty('--sp', `${Number(spacingSelector.value)}pt`);
  resumePreview.classList.toggle('hide-edu-desc', !toggleEducationDescription.checked);
  if (toggleQr) setBrandingVisibility(toggleQr.checked);
  updateHeaderLayout();
  syncSectionOrder();

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(scheduleLayout);
  }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
