<?php

/**
 * Public profile page (qrsume.com/<username>).
 *
 * Expects from assets/db.php: $db, $user_id, $username_url, $personalinfo,
 * $contactinfo, $visibility, $results, $is_local_dev.
 *
 * Layout: coloured identity panel (photo, contact, skills, languages, CV, QR)
 * + white content column (name, about, featured, experience, education,
 * projects, custom sections). On phones the panel splits in two: identity on
 * top, extras below the content.
 */

// ---------------------------------------------------------------------
// Page settings. Stored per user as JSON in user_page_settings.settings;
// anything missing or invalid falls back to the default.
// ---------------------------------------------------------------------
$pageSettingOptions = [
    'panel'      => ['solid', 'gradient', 'soft'],
    'panel_side' => ['left', 'right'],
    'avatar'     => ['circle', 'rounded'],
    'font'       => ['modern', 'classic', 'technical'],
    'theme'      => ['light', 'dark', 'auto'],
    'skills'     => ['chips', 'list'],
    'status'     => ['none', 'open', 'freelance', 'hiring'],
    'show_views' => ['1', '0'],
];
$page = [
    'accent' => '', // empty = site default (--primary)
    'panel' => 'solid',
    'panel_side' => 'left',
    'avatar' => 'circle',
    'font' => 'modern',
    'theme' => 'light',
    'skills' => 'chips',
    'status' => 'none',
    'show_views' => '1',
];

$savedSettings = [];
try {
    $stmt_settings = $db->prepare("SELECT settings FROM user_page_settings WHERE user_id = ?");
    $stmt_settings->execute([$user_id]);
    $savedSettings = json_decode((string) $stmt_settings->fetchColumn(), true) ?: [];
} catch (PDOException $ex) {
    // Table not created yet: use defaults
}
// Local dev only: try any setting from the URL, e.g. ?ps_panel=soft&ps_theme=dark
if (!empty($is_local_dev)) {
    foreach (array_keys($page) as $key) {
        if (isset($_GET['ps_' . $key])) {
            $savedSettings[$key] = (string) $_GET['ps_' . $key];
        }
    }
}
foreach ($pageSettingOptions as $key => $allowed) {
    if (isset($savedSettings[$key]) && in_array((string) $savedSettings[$key], $allowed, true)) {
        $page[$key] = (string) $savedSettings[$key];
    }
}
if (isset($savedSettings['accent']) && preg_match('/^#?[0-9a-fA-F]{6}$/', $savedSettings['accent'])) {
    $page['accent'] = '#' . ltrim($savedSettings['accent'], '#');
}

// ---------------------------------------------------------------------
// Data prep
// ---------------------------------------------------------------------
$e = fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$has = fn ($v) => $v !== null && trim((string) $v) !== '';

$pi = $personalinfo ?? [];
$ci = $contactinfo ?? [];
$firstName = $pi['personal_name'] ?? '';
$lastName = $pi['personal_lastname'] ?? '';
$headline = $pi['personal_profession'] ?? '';
$bio = $pi['personal_bio'] ?? '';

// Lifetime profile views, recorded by recordPageView() in assets/stats.php (same number as the dashboard)
$stmt_views = $db->prepare("SELECT views FROM user_statistics WHERE user_id = ?");
$stmt_views->execute([$user_id]);
$visits = (int) $stmt_views->fetchColumn();

$cvUrl = $pi['cv_url'] ?? '';
$photo = $pi['personal_photo'] ?? '';
$hasPhoto = $has($photo) && $photo !== 'default.webp';

$initials = '';
foreach (preg_split('/[\s\-]+/u', trim($firstName . ' ' . $lastName)) as $word) {
    if ($word !== '' && preg_match('/^\p{Lu}/u', $word) && mb_strlen($initials) < 2) {
        $initials .= mb_substr($word, 0, 1);
    }
}
$initials = $initials ?: mb_strtoupper(mb_substr($firstName ?: '?', 0, 1));

$orgInitials = function (string $name): string {
    $name = preg_replace('/\b(S\.?L\.?U?\.?|S\.?A\.?|A\/S|Ltd\.?|Inc\.?|GmbH)\b/iu', '', $name);
    $out = '';
    foreach (preg_split('/[\s,]+/u', trim($name)) as $word) {
        if ($word !== '' && mb_strlen($out) < 2) {
            $out .= mb_strtoupper(mb_substr($word, 0, 1));
        }
    }
    return $out;
};

$showEmail = $has($ci['email'] ?? '') && ($visibility['email'] ?? 1) == 1;
$showPhone = $has($ci['phone_number'] ?? '') && ($visibility['phone'] ?? 1) == 1;
$phoneHref = preg_replace('/[^0-9+]/', '', (string) ($ci['phone_number'] ?? ''));
$phoneHref = str_starts_with($phoneHref, '+') ? $phoneHref : '+' . $phoneHref;

$socials = [];
foreach (['linkedin' => 'LinkedIn', 'github' => 'GitHub', 'twitter' => 'X', 'facebook' => 'Facebook'] as $key => $label) {
    $url = $ci[$key] ?? '';
    if ($has($url) && preg_match('#^https?://#i', $url)) {
        $socials[] = ['url' => $url, 'label' => $label, 'icon' => $key === 'twitter' ? 'twitter-x' : $key];
    }
}

$profileUrl = 'qrsume.com/' . ($username_url ?: '');
$statusText = ['open' => 'Open to work', 'freelance' => 'Available for freelance', 'hiring' => 'Hiring'][$page['status']] ?? '';

$experience = $results['experience'] ?? [];
$education = $results['education'] ?? [];
$skills = array_values(array_filter(array_column($results['aptitudes'] ?? [], 'aptitude'), $has));
$languages = $results['languages'] ?? [];
$projects = $results['interests'] ?? [];
$customSections = $results['custom_sections'] ?? [];

$stmt_articles = $db->prepare("SELECT article_id, article_title, article_summary, article_date, article_photo
    FROM blogarticles WHERE user_id = ? AND article_status = 'published' ORDER BY article_date DESC LIMIT 3");
$stmt_articles->execute([$user_id]);
$articles = $stmt_articles->fetchAll(PDO::FETCH_ASSOC);

// QR code for the share card (TCPDF ships with the project for the PDFs)
$qrDataUri = '';
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('TCPDF2DBarcode')) {
        $qr = new TCPDF2DBarcode('https://qrsume.com/' . $username_url . '?qr_scan=true', 'QRCODE,M');
        $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qr->getBarcodeSVGcode(4, 4, '#111317'));
    }
}

$skillLimit = 12;
$fontHref = [
    'classic' => 'https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,700&display=swap',
    'technical' => 'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&display=swap',
][$page['font']] ?? '';

$qpClasses = implode(' ', [
    'qp',
    'panel-' . $page['panel'],
    'side-' . $page['panel_side'],
    'avatar-' . $page['avatar'],
    'font-' . $page['font'],
    'skills-' . $page['skills'],
    $statusText ? 'has-status' : '',
]);

/** Paragraph that collapses to $lines lines with a "See more" toggle. */
$clamp = function (string $text, int $lines, string $class = 'qp-prose') use ($e): string {
    return '<div class="qp-clamp-wrap"><p class="' . $class . ' qp-clamp" style="--lines:' . $lines . '">'
        . $e($text) . '</p><button type="button" class="qp-more" hidden>See more</button></div>';
};

/** Experience / education list. */
$timeline = function (array $rows, string $orgKey, string $titleKey) use ($e, $has, $clamp, $orgInitials): string {
    $html = '<ul class="qp-items">';
    foreach ($rows as $row) {
        $org = $row[$orgKey] ?? '';
        $html .= '<li class="qp-item"><div class="qp-logo" aria-hidden="true">' . $e($orgInitials($org ?: ($row[$titleKey] ?? ''))) . '</div><div>'
            . '<h3>' . $e($row[$titleKey] ?? '') . '</h3>'
            . ($has($org) ? '<p class="qp-org">' . $e($org) . '</p>' : '')
            . ($has($row['date'] ?? '') ? '<p class="qp-when">' . $e($row['date']) . '</p>' : '')
            . ($has($row['brief_description'] ?? '') ? $clamp($row['brief_description'], 3, 'qp-desc') : '')
            . '</div></li>';
    }
    return $html . '</ul>';
};
?>
<?php if ($fontHref): ?>
<link rel="stylesheet" href="<?= $e($fontHref) ?>">
<?php endif; ?>
<style>
    body.qp-body {
        /* Content column tokens (light) */
        --qp-accent: <?= $page['accent'] ? $e($page['accent']) : 'var(--primary)' ?>;
        --qp-bg: #ffffff;
        --qp-ink: #1a1d23;
        --qp-muted: #626876;
        --qp-line: #e4e6eb;
        --qp-accent-text: var(--qp-accent);
        --qp-accent-soft: color-mix(in srgb, var(--qp-accent) 10%, var(--qp-bg));
        --qp-status: #16a34a;
        /* Panel tokens */
        --qp-panel: var(--qp-accent);
        --qp-panel-ink: #ffffff;
        --qp-panel-muted: rgba(255, 255, 255, .72);
        --qp-panel-line: rgba(255, 255, 255, .22);
        --qp-panel-chip: rgba(255, 255, 255, .13);
        --qp-font-display: "Inter", system-ui, sans-serif;
        --qp-font-label: "Inter", system-ui, sans-serif;
        background: var(--qp-bg);
        color: var(--qp-ink);
    }
    body.qp-body.theme-dark {
        color-scheme: dark;
        --qp-bg: #121418;
        --qp-ink: #eceef2;
        --qp-muted: #9ca3af;
        --qp-line: #2a2e36;
        --qp-accent-text: color-mix(in srgb, var(--qp-accent) 50%, #ffffff);
        --qp-accent-soft: color-mix(in srgb, var(--qp-accent) 25%, var(--qp-bg));
        --qp-status: #4ade80;
    }
    @media (prefers-color-scheme: dark) {
        body.qp-body.theme-auto {
            color-scheme: dark;
            --qp-bg: #121418;
            --qp-ink: #eceef2;
            --qp-muted: #9ca3af;
            --qp-line: #2a2e36;
            --qp-accent-text: color-mix(in srgb, var(--qp-accent) 50%, #ffffff);
            --qp-accent-soft: color-mix(in srgb, var(--qp-accent) 25%, var(--qp-bg));
            --qp-status: #4ade80;
        }
    }

    .qp.panel-gradient { --qp-panel: linear-gradient(165deg, var(--qp-accent), color-mix(in srgb, var(--qp-accent) 55%, #000)); }
    .qp.panel-soft {
        --qp-panel: color-mix(in srgb, var(--qp-accent) 9%, var(--qp-bg));
        --qp-panel-ink: var(--qp-ink);
        --qp-panel-muted: var(--qp-muted);
        --qp-panel-line: color-mix(in srgb, var(--qp-accent) 20%, transparent);
        --qp-panel-chip: color-mix(in srgb, var(--qp-accent) 12%, var(--qp-bg));
    }
    .qp.font-classic { --qp-font-display: "Source Serif 4", Georgia, serif; }
    .qp.font-technical { --qp-font-label: "IBM Plex Mono", ui-monospace, monospace; }

    /* ---- Grid: panel top / content / panel bottom ---- */
    .qp {
        max-width: 1320px;
        margin: 0 auto;
        --qp-nav: 56px; /* fixed navbar height (nav_profile.php adds a spacer of this height) */
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        font-size: 15.5px;
        line-height: 1.55;
    }
    /* Phones: the side wrapper dissolves so its two halves bookend the content */
    .qp-side { display: contents; }
    .qp-id { order: 1; }
    .qp-main { order: 2; }
    .qp-extra { order: 3; }
    .qp-id, .qp-extra { background: var(--qp-panel); color: var(--qp-panel-ink); padding: 32px 24px; display: grid; gap: 22px; align-content: start; min-width: 0; }
    .qp-id { padding-top: 32px; }
    .qp-extra { padding-bottom: 48px; }
    .qp-main { padding: 32px 20px 48px; display: grid; gap: 40px; align-content: start; min-width: 0; }
    .qp-desktop { display: none; }

    /* Desktop: one sidebar that stays on screen while only the content scrolls */
    @media (min-width: 768px) {
        /* Overlap the navbar spacer so the panel can run to the very top: the navbar hides on scroll */
        .qp { grid-template-columns: minmax(290px, 32%) minmax(0, 1fr); margin-top: calc(-1 * var(--qp-nav)); }
        .qp.side-right { grid-template-columns: minmax(0, 1fr) minmax(290px, 32%); }
        .qp.side-right .qp-side { order: 2; }
        .qp.side-right .qp-main { order: 1; }
        .qp-side {
            display: flex; flex-direction: column; justify-content: safe center;
            gap: clamp(10px, 2vh, 22px);
            position: sticky; top: 0; align-self: start;
            height: 100vh; height: 100dvh;
            padding: calc(var(--qp-nav) + clamp(12px, 3vh, 36px)) 30px clamp(14px, 3vh, 36px);
            background: var(--qp-panel); color: var(--qp-panel-ink);
            /* Fallback when a profile has more than fits: scroll inside the panel, never clip */
            overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin;
            scrollbar-color: var(--qp-panel-line) transparent;
        }
        .qp-id, .qp-extra { background: none; padding: 0; gap: clamp(10px, 2vh, 22px); }
        .qp-main { padding: calc(var(--qp-nav) + 52px) clamp(28px, 5vw, 72px) 64px; gap: 48px; }
        .qp-desktop { display: block; }
        .qp-mobile { display: none !important; }
    }

    /* ---- Identity panel ---- */
    .qp-photo-wrap { display: grid; justify-items: center; gap: 12px; }
    .qp-avatar {
        width: min(200px, 60%); aspect-ratio: 1; max-width: 100%;
        border-radius: 50%; overflow: hidden; display: grid; place-items: center;
        border: 4px solid color-mix(in srgb, var(--qp-panel-ink) 85%, transparent);
        background: var(--qp-panel-chip); color: var(--qp-panel-ink);
        font-family: var(--qp-font-display); font-weight: 700; font-size: clamp(2.5rem, 6vw, 4rem);
    }
    .qp-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .avatar-rounded .qp-avatar { border-radius: 28px; }
    .has-status .qp-avatar { box-shadow: 0 0 0 4px var(--qp-status); }
    .qp-status {
        display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600;
        background: var(--qp-bg); color: var(--qp-status); border-radius: 999px; padding: 5px 12px; font-family: var(--qp-font-label);
    }
    .qp-status::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

    .qp-id .qp-name { text-align: center; color: var(--qp-panel-ink); }
    .qp-id .qp-headline { text-align: center; color: var(--qp-panel-muted); margin-inline: auto; }
    .qp-id .qp-meta { justify-content: center; color: var(--qp-panel-muted); }

    .qp-socials { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
    .qp-socials a {
        width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center;
        background: var(--qp-panel-chip); color: var(--qp-panel-ink); font-size: 1.2rem; text-decoration: none;
        transition: transform .15s, background .15s;
    }
    .qp-socials a:hover { transform: translateY(-2px); background: var(--qp-panel-line); }

    .qp-panel-title {
        font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .09em; margin: 0 0 12px;
        padding-bottom: 8px; border-bottom: 2px solid var(--qp-panel-line); color: var(--qp-panel-ink); font-family: var(--qp-font-label);
    }
    .qp-contact { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .qp-contact li { display: grid; grid-template-columns: 22px minmax(0, 1fr); gap: 10px; align-items: start; }
    .qp-contact i { font-size: 1.05rem; color: var(--qp-panel-muted); line-height: 1.5; }
    .qp-contact small { display: block; font-size: 12px; color: var(--qp-panel-muted); font-family: var(--qp-font-label); }
    .qp-contact a { color: var(--qp-panel-ink); text-decoration: none; font-weight: 500; overflow-wrap: anywhere; }
    .qp-contact a:hover { text-decoration: underline; }

    .qp-chips { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
    .qp-chips li { background: var(--qp-panel-chip); border-radius: 999px; padding: 5px 13px; font-size: 14px; font-weight: 500; overflow-wrap: anywhere; max-width: 100%; }
    .skills-list .qp-chips { display: grid; gap: 0; }
    .skills-list .qp-chips li { background: none; border-radius: 0; padding: 7px 0; border-top: 1px solid var(--qp-panel-line); }
    .skills-list .qp-chips li:first-child { border-top: 0; padding-top: 0; }
    .qp-langs { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
    .qp-langs li { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
    .qp-langs b { font-weight: 600; overflow-wrap: anywhere; }
    .qp-langs span { color: var(--qp-panel-muted); font-size: 13.5px; text-align: right; font-family: var(--qp-font-label); }
    .qp-panel .qp-more, .qp-extra .qp-more { color: var(--qp-panel-muted); }

    .qp-share { background: var(--qp-bg); color: var(--qp-ink); border-radius: 16px; padding: 14px; display: grid; grid-template-columns: 76px minmax(0, 1fr); gap: 10px 14px; align-items: center; }
    .qp-share img { width: 76px; height: 76px; background: #fff; border-radius: 8px; padding: 3px; border: 1px solid var(--qp-line); }
    .qp-share p { margin: 0 0 2px; font-size: 13px; color: var(--qp-muted); }
    .qp-share .qp-url { display: block; font-weight: 600; font-size: 14px; overflow-wrap: anywhere; font-family: var(--qp-font-label); }
    .qp-share .qp-btn { grid-column: 1 / -1; justify-content: center; }
    .qp-extra .qp-share .qp-btn { border-color: var(--qp-accent-text); color: var(--qp-accent-text); }
    .qp-extra .qp-share .qp-btn:hover { background: var(--qp-accent-soft); }

    /* ---- Buttons ---- */
    .qp-actions { display: flex; flex-wrap: wrap; gap: 10px; }
    .qp-id .qp-actions { justify-content: center; }
    .qp-btn {
        display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; padding: 9px 18px;
        font-weight: 600; font-size: 14.5px; text-decoration: none; border: 2px solid var(--qp-accent-text);
        color: var(--qp-accent-text); background: transparent; cursor: pointer; white-space: nowrap; transition: background .15s, color .15s;
    }
    .qp-btn:hover { background: var(--qp-accent-soft); color: var(--qp-accent-text); }
    .qp-btn.primary { background: var(--qp-accent); border-color: var(--qp-accent); color: #fff; }
    .qp-btn.primary:hover { background: color-mix(in srgb, var(--qp-accent) 82%, #000); color: #fff; }
    /* Buttons sitting on the coloured panel */
    .qp-id .qp-btn, .qp-extra .qp-btn { border-color: var(--qp-panel-ink); color: var(--qp-panel-ink); }
    .qp-id .qp-btn:hover, .qp-extra .qp-btn:hover { background: var(--qp-panel-chip); }
    .qp-id .qp-btn.primary, .qp-extra .qp-btn.primary { background: var(--qp-panel-ink); color: var(--qp-accent); border-color: var(--qp-panel-ink); }
    .panel-soft .qp-id .qp-btn.primary, .panel-soft .qp-extra .qp-btn.primary { background: var(--qp-accent); border-color: var(--qp-accent); color: #fff; }

    /* ---- Content column ---- */
    .qp-name {
        font-family: var(--qp-font-display); font-weight: 800; letter-spacing: -.02em; line-height: 1.08; margin: 0;
        font-size: clamp(2rem, 1.2rem + 3.2vw, 3.6rem); color: var(--qp-accent-text); text-wrap: balance; overflow-wrap: anywhere; hyphens: auto;
    }
    .qp-name .last { font-weight: 300; }
    .qp-headline { margin: 10px 0 0; font-size: clamp(1.05rem, .95rem + .6vw, 1.4rem); font-weight: 500; color: var(--qp-ink); max-width: 46ch; overflow-wrap: anywhere; text-wrap: balance; }
    .qp-meta { margin: 14px 0 0; display: flex; flex-wrap: wrap; gap: 6px 18px; color: var(--qp-muted); font-size: 14px; font-family: var(--qp-font-label); }
    .qp-meta span { display: inline-flex; align-items: center; gap: 6px; }
    .qp-hero .qp-actions { margin-top: 22px; }
    .qp-hero .qp-bio { margin-top: 26px; }

    .qp-sec-title {
        display: flex; align-items: center; gap: 14px; margin: 0 0 18px;
        font-family: var(--qp-font-display); font-weight: 700; font-size: 1.55rem; letter-spacing: -.01em; color: var(--qp-accent-text);
    }
    .qp-sec-title::after { content: ""; flex: 1; height: 2px; background: var(--qp-accent); opacity: .85; }
    .qp-sec-title .count { font-family: var(--qp-font-label); font-size: 13px; font-weight: 500; color: var(--qp-muted); letter-spacing: 0; }
    .qp-sec-title a { font-size: 14px; font-weight: 600; color: var(--qp-accent-text); text-decoration: none; order: 3; letter-spacing: 0; font-family: var(--qp-font-label); }
    .qp-sec-title a:hover { text-decoration: underline; }

    .qp-prose { margin: 0; white-space: pre-line; overflow-wrap: anywhere; max-width: 72ch; color: var(--qp-ink); }
    .qp-clamp { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: var(--lines, 4); overflow: hidden; }
    .qp-more { background: none; border: 0; padding: 0; margin-top: 6px; font-weight: 600; font-size: 14px; color: var(--qp-muted); cursor: pointer; }
    .qp-more:hover { color: var(--qp-accent-text); text-decoration: underline; }
    .qp-extra .qp-more:hover { color: var(--qp-panel-ink); }

    .qp-items { list-style: none; margin: 0; padding: 0; display: grid; }
    .qp-item { display: grid; grid-template-columns: 52px minmax(0, 1fr); gap: 16px; padding-block: 18px; border-top: 1px solid var(--qp-line); }
    .qp-item:first-child { border-top: 0; padding-top: 0; }
    .qp-item:last-child { padding-bottom: 0; }
    .qp-item.no-logo { grid-template-columns: minmax(0, 1fr); }
    .qp-logo {
        width: 52px; height: 52px; border-radius: 12px; display: grid; place-items: center;
        background: var(--qp-accent-soft); color: var(--qp-accent-text); font-weight: 700; font-size: 16px; font-family: var(--qp-font-display);
    }
    .qp-item h3 { margin: 0; font-size: 1.1rem; font-weight: 700; line-height: 1.3; color: var(--qp-ink); overflow-wrap: anywhere; }
    .qp-org { margin: 2px 0 0; font-weight: 600; color: var(--qp-accent-text); overflow-wrap: anywhere; }
    .qp-when { margin: 2px 0 0; font-size: 13.5px; color: var(--qp-muted); font-family: var(--qp-font-label); }
    .qp-desc { margin: 8px 0 0; overflow-wrap: anywhere; color: var(--qp-ink); max-width: 72ch; }

    #featured { container-type: inline-size; }
    .qp-feat { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
    .qp-feat a { display: grid; grid-template-rows: auto 1fr; border: 1px solid var(--qp-line); border-radius: 14px; overflow: hidden; text-decoration: none; color: var(--qp-ink); background: var(--qp-bg); transition: border-color .15s, transform .15s; }
    .qp-feat a:hover { border-color: var(--qp-accent-text); transform: translateY(-2px); }
    .qp-feat img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; background: var(--qp-accent-soft); }
    .qp-feat .body { padding: 14px 16px 16px; }
    .qp-feat h3 { margin: 0; font-size: 1rem; font-weight: 700; line-height: 1.3; }
    .qp-feat h3, .qp-feat p { overflow-wrap: anywhere; }
    .qp-feat p { margin: 6px 0 0; font-size: 14px; color: var(--qp-muted); }
    /* Narrow (or a single article): horizontal rows, image on the left */
    .qp-feat a { grid-template-columns: 120px minmax(0, 1fr); grid-template-rows: none; }
    .qp-feat img { height: 100%; min-height: 96px; aspect-ratio: auto; }
    /* Wide enough for 2-3 articles side by side: vertical cards, one column each */
    @container (min-width: 540px) {
        .qp-feat.n-2, .qp-feat.n-3 { grid-template-columns: repeat(var(--n), minmax(0, 1fr)); }
        .qp-feat.n-2 a, .qp-feat.n-3 a { grid-template-columns: none; grid-template-rows: auto 1fr; }
        .qp-feat.n-2 img, .qp-feat.n-3 img { height: auto; min-height: 0; aspect-ratio: 16 / 9; }
    }
    @container (min-width: 380px) {
        .qp-feat a { grid-template-columns: 160px minmax(0, 1fr); }
    }
    .qp-feat time { display: block; margin-top: 10px; font-size: 12.5px; color: var(--qp-muted); font-family: var(--qp-font-label); }

    .qp-made { text-align: center; font-size: 13.5px; color: var(--qp-muted); padding: 22px 16px 0; }
    .qp-made a { color: var(--qp-accent-text); font-weight: 600; text-decoration: none; }

    .qp-slot { display: grid; gap: 40px; }
    .qp-slot:empty { display: none; }
    .qp-slot .qp-chips li { background: var(--qp-accent-soft); color: var(--qp-accent-text); }
    .skills-list .qp-slot .qp-chips li { background: none; color: var(--qp-ink); border-color: var(--qp-line); }
    .qp-slot .qp-langs { max-width: 420px; }
    .qp-slot .qp-langs span { color: var(--qp-muted); }

    /* Desktop sidebar: everything scales with screen height so it fits without scrolling */
    @media (min-width: 768px) {
        .qp-avatar { width: clamp(84px, 17vh, 180px); border-width: 3px; font-size: clamp(2rem, 5vh, 3.6rem); }
        .qp-photo-wrap { gap: 8px; }
        .qp-socials a { width: clamp(34px, 5vh, 42px); height: clamp(34px, 5vh, 42px); font-size: 1.05rem; }
        .qp-panel-title { margin-bottom: clamp(6px, 1.2vh, 12px); padding-bottom: 6px; }
        .qp-contact { gap: 6px; }
        .qp-contact small { display: none; }
        .qp-contact li { grid-template-columns: 18px minmax(0, 1fr); font-size: 14.5px; }
        .qp-contact i { font-size: .95rem; }
        .qp-chips { gap: 6px; }
        .qp-chips li { padding: 3px 11px; font-size: 13.5px; }
        .qp-langs { gap: 4px; font-size: 14.5px; }
        .qp-share { padding: 10px 12px; grid-template-columns: clamp(52px, 8vh, 72px) minmax(0, 1fr); gap: 8px 12px; }
        .qp-share img { width: 100%; height: auto; aspect-ratio: 1; }
        .qp-share .qp-btn { padding: 5px 12px; font-size: 13.5px; }
    }

    /* Long names must not wrap the fixed navbar onto two lines */
    .qp-body .navbar-brand { max-width: calc(100% - 72px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .qp :focus-visible { outline: 2px solid var(--qp-status); outline-offset: 2px; }
    @media (prefers-reduced-motion: reduce) { .qp * { transition: none !important; } }
</style>
</head>

<body class="qp-body theme-<?= $e($page['theme']) ?>">
<?php include __DIR__ . "/nav_profile.php"; ?>

<div class="<?= $e($qpClasses) ?>">

    <div class="qp-side">
    <!-- Identity panel: photo, name (phones), socials, contact -->
    <aside class="qp-id">
        <div class="qp-photo-wrap">
            <div class="qp-avatar">
                <?php if ($hasPhoto): ?>
                    <img src="images/<?= $e($photo) ?>" alt="Photo of <?= $e($firstName) ?>" loading="eager">
                <?php else: ?>
                    <span aria-hidden="true"><?= $e($initials) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($statusText): ?><span class="qp-status"><?= $e($statusText) ?></span><?php endif; ?>
        </div>

        <div class="qp-mobile">
            <h1 class="qp-name"><?= $e($firstName) ?> <span class="last"><?= $e($lastName) ?></span></h1>
            <?php if ($has($headline)): ?><p class="qp-headline"><?= $e($headline) ?></p><?php endif; ?>
        </div>

        <?php if ($showEmail || $has($cvUrl)): ?>
            <div class="qp-actions qp-mobile">
                <?php if ($showEmail): ?><a class="qp-btn primary" href="mailto:<?= $e($ci['email']) ?>"><i class="bi bi-envelope"></i>Contact</a><?php endif; ?>
                <?php if ($has($cvUrl)): ?><a class="qp-btn" href="https://qrsume.com/<?= $e($cvUrl) ?>" target="_blank" download="CV.pdf"><i class="bi bi-download"></i>Download CV</a><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($socials): ?>
            <div class="qp-socials">
                <?php foreach ($socials as $s): ?>
                    <a href="<?= $e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= $e($s['label']) ?>"><i class="bi bi-<?= $e($s['icon']) ?>"></i></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($showEmail || $showPhone): ?>
            <section>
                <h2 class="qp-panel-title">Contact</h2>
                <ul class="qp-contact">
                    <?php if ($showEmail): ?>
                        <li><i class="bi bi-envelope"></i><div><small>Email</small><a href="mailto:<?= $e($ci['email']) ?>"><?= $e($ci['email']) ?></a></div></li>
                    <?php endif; ?>
                    <?php if ($showPhone): ?>
                        <li><i class="bi bi-telephone"></i><div><small>Phone</small><a href="tel:<?= $e($phoneHref) ?>"><?= $e($ci['phone_number']) ?></a></div></li>
                    <?php endif; ?>
                </ul>
            </section>
        <?php endif; ?>
    </aside>


    <!-- Panel extras: skills, languages, CV, share -->
    <aside class="qp-extra">
        <?php if ($skills): ?>
            <section data-movable>
                <h2 class="qp-panel-title">Skills</h2>
                <ul class="qp-chips">
                    <?php foreach ($skills as $i => $skill): ?>
                        <li<?= $i >= $skillLimit ? ' data-extra hidden' : '' ?>><?= $e($skill) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($skills) > $skillLimit): ?>
                    <button type="button" class="qp-more" data-skills="<?= count($skills) ?>">Show all <?= count($skills) ?> skills</button>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($languages): ?>
            <section data-movable>
                <h2 class="qp-panel-title">Languages</h2>
                <ul class="qp-langs">
                    <?php foreach ($languages as $lang): ?>
                        <li><b><?= $e($lang['language']) ?></b><?php if ($has($lang['level'])): ?><span><?= $e($lang['level']) ?></span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>


        <section class="qp-share">
            <?php if ($qrDataUri): ?><img src="<?= $qrDataUri ?>" alt="QR code linking to this profile"><?php endif; ?>
            <div>
                <p>Share this profile</p>
                <span class="qp-url" id="qp-url">qrsume.com/<wbr><?= $e($username_url) ?></span>
                <button type="button" class="qp-btn" id="qp-copy" data-url="https://<?= $e($profileUrl) ?>"><i class="bi bi-link-45deg"></i>Copy link</button>
            </div>
        </section>
    </aside>
    </div>

    <!-- Content column -->
    <main class="qp-main">
        <header class="qp-hero">
            <div class="qp-desktop">
                <h1 class="qp-name"><?= $e($firstName) ?> <span class="last"><?= $e($lastName) ?></span></h1>
                <?php if ($has($headline)): ?><p class="qp-headline"><?= $e($headline) ?></p><?php endif; ?>
                <p class="qp-meta">
                    <?php if ($page['show_views'] === '1' && $visits > 0): ?>
                        <span><i class="bi bi-eye"></i><?= number_format($visits) ?> profile <?= $visits === 1 ? 'view' : 'views' ?></span>
                    <?php endif; ?>
                    <span><i class="bi bi-link-45deg"></i><?= $e($profileUrl) ?></span>
                </p>
                <?php if ($showEmail || $showPhone || $has($cvUrl)): ?>
                    <div class="qp-actions">
                        <?php if ($showEmail): ?>
                            <a class="qp-btn primary" href="mailto:<?= $e($ci['email']) ?>"><i class="bi bi-envelope"></i>Contact me</a>
                        <?php elseif ($showPhone): ?>
                            <a class="qp-btn primary" href="tel:<?= $e($phoneHref) ?>"><i class="bi bi-telephone"></i>Call</a>
                        <?php endif; ?>
                        <?php if ($has($cvUrl)): ?>
                            <a class="qp-btn" id="cv" href="https://qrsume.com/<?= $e($cvUrl) ?>" target="_blank" download="CV.pdf"><i class="bi bi-download"></i>Download CV</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($has($bio)): ?>
                <section class="qp-bio">
                    <h2 class="qp-sec-title">About</h2>
                    <?= $clamp($bio, 5) ?>
                </section>
            <?php endif; ?>
        </header>

        <!-- Filled by script on desktop when the sidebar is taller than the screen -->
        <div class="qp-slot"></div>

        <?php if ($articles): ?>
            <section id="featured">
                <h2 class="qp-sec-title">Featured <a href="blog.php/?user_id=<?= (int) $user_id ?>">See all</a></h2>
                <div class="qp-feat n-<?= count($articles) ?>" style="--n:<?= count($articles) ?>">
                    <?php foreach ($articles as $a): ?>
                        <a href="article.php?user_id=<?= (int) $user_id ?>&id=<?= (int) $a['article_id'] ?>">
                            <img src="images/<?= $e($a['article_photo']) ?>" alt="" loading="lazy">
                            <div class="body">
                                <h3><?= $e($a['article_title']) ?></h3>
                                <?php if ($has($a['article_summary'])): ?><p><?= $e($a['article_summary']) ?></p><?php endif; ?>
                                <time datetime="<?= $e($a['article_date']) ?>"><?= $e(date('M j, Y', strtotime($a['article_date']) ?: time())) ?></time>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($experience): ?>
            <section id="experience">
                <h2 class="qp-sec-title">Experience</h2>
                <?= $timeline($experience, 'place_of_work', 'job_name') ?>
            </section>
        <?php endif; ?>

        <?php if ($education): ?>
            <section id="education">
                <h2 class="qp-sec-title">Education</h2>
                <?= $timeline($education, 'place_of_study', 'name_of_studies') ?>
            </section>
        <?php endif; ?>

        <?php if ($projects): ?>
            <section id="interest">
                <h2 class="qp-sec-title">Projects</h2>
                <ul class="qp-items">
                    <?php foreach ($projects as $p): ?>
                        <li class="qp-item no-logo"><div>
                            <h3><?= $e($p['interest']) ?></h3>
                            <?php if ($has($p['description'])): ?><?= $clamp($p['description'], 3, 'qp-desc') ?><?php endif; ?>
                        </div></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php foreach ($customSections as $section): ?>
            <?php if ($has($section['section_title']) || $has($section['section_content'])): ?>
                <section>
                    <h2 class="qp-sec-title"><?= $e($section['section_title']) ?></h2>
                    <?= $clamp((string) $section['section_content'], 6) ?>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>
    </main>
</div>

<p class="qp-made">Made with QRsume · <a href="https://qrsume.com/assets/register.php">Create your own profile</a></p>

<script>
(function () {
    // Show "See more" only where the text is actually cut off
    function measure() {
        document.querySelectorAll('.qp-clamp-wrap .qp-more').forEach(function (btn) {
            var p = btn.previousElementSibling;
            if (p.classList.contains('qp-clamp')) btn.hidden = p.scrollHeight <= p.clientHeight + 2;
        });
    }
    document.addEventListener('click', function (ev) {
        var more = ev.target.closest('.qp-clamp-wrap .qp-more');
        if (more) {
            var open = more.previousElementSibling.classList.toggle('qp-clamp');
            more.textContent = open ? 'See more' : 'See less';
        }
        var skills = ev.target.closest('[data-skills]');
        if (skills) {
            var extra = document.querySelectorAll('.qp-chips [data-extra]'), show = extra[0].hidden;
            extra.forEach(function (li) { li.hidden = !show; });
            skills.textContent = show ? 'Show fewer' : 'Show all ' + skills.dataset.skills + ' skills';
        }
        var copy = ev.target.closest('#qp-copy');
        if (copy) {
            var done = function () { copy.lastChild.textContent = 'Copied'; setTimeout(function () { copy.lastChild.textContent = 'Copy link'; }, 1500); };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(copy.dataset.url).then(done).catch(function () {
                    var r = document.createRange(); r.selectNodeContents(document.getElementById('qp-url'));
                    getSelection().removeAllRanges(); getSelection().addRange(r);
                });
            }
        }
    });
    // Desktop: keep the whole sidebar on screen. If it is taller than the window,
    // move skills, then languages, into the content column (and back when it fits).
    var side = document.querySelector('.qp-side'), slot = document.querySelector('.qp-slot');
    var movable = Array.prototype.slice.call(document.querySelectorAll('[data-movable]'));
    var anchors = movable.map(function (sec) { var a = document.createComment('movable'); sec.parentNode.insertBefore(a, sec); return a; });
    function place(sec, inContent) {
        var title = sec.querySelector('h2');
        title.className = inContent ? 'qp-sec-title' : 'qp-panel-title';
        if (inContent) slot.appendChild(sec); else anchors[movable.indexOf(sec)].after(sec);
    }
    function fitSide() {
        if (!side || !slot) return;
        movable.forEach(function (sec) { place(sec, false); });
        if (!window.matchMedia('(min-width: 768px)').matches) return;
        for (var i = 0; i < movable.length && side.scrollHeight > side.clientHeight + 1; i++) place(movable[i], true);
    }
    document.addEventListener('click', function (ev) { if (ev.target.closest('[data-skills]')) fitSide(); });

    var t; window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(function () { fitSide(); measure(); }, 120); });
    fitSide();
    measure();
    if (document.fonts) document.fonts.ready.then(function () { fitSide(); measure(); });
})();
</script>
