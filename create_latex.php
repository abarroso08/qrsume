<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Database connection
include("assets/db.php");
require_once("assets/resume_options.php");

// Ensure user is logged in or username is provided
if (!isset($_GET['username'])) {
    if (!isset($_SESSION['username'])) {
        header("Location: https://qrsume.com/error.php");
        exit();
    } else {
        $username = $_SESSION['username'];
    }
} else {
    $username = $_GET['username'];
}

// Prepare and execute query
$stmt_login = $db->prepare("SELECT id, privilege FROM users WHERE username = :username");
$stmt_login->bindValue(":username", $username, PDO::PARAM_STR);
$stmt_login->execute();

// Fetch user
$user = $stmt_login->fetch(PDO::FETCH_ASSOC);

// If user not found, redirect to error page
if (!$user) {
    header("Location: https://qrsume.com/error.php");
    exit();
}

$user_id = (int) $user['id'];

try {
    $pdo = $db;
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ========== GET POST DATA (same shape as create_pdf.php) ==========
    $display_education  = isset($_POST['show_education']);
    $display_experience = isset($_POST['show_experience']);
    $display_skills      = isset($_POST['show_skills']);
    $display_languages   = isset($_POST['show_languages']);
    $display_projects    = isset($_POST['show_projects']);
    $showDescription = isset($_POST['show_education_description']);
    $personalBio = isset($_POST["show_bio"]);

    $display_custom_sections = [];
    foreach ($_POST['custom_sections'] ?? [] as $i => $section) {
        $display_custom_sections[$i] = isset($section['show']) && $section['show'] === '1';
    }

    $personal['personal_name']     = $_POST['personal_name'] ?? '';
    $personal['personal_lastname'] = $_POST['personal_lastname'] ?? '';
    $contact['email']              = $_POST['email'] ?? '';
    $contact['phone_number']       = $_POST['phone_number'] ?? '';
    $personal['personal_bio']      = $_POST['personal_bio'] ?? '';

    $stmt = $pdo->prepare('SELECT personal_profession FROM personalinfo WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $storedProfession = (string) ($stmt->fetchColumn() ?: '');
    $stmt = $pdo->prepare('SELECT linkedin, github FROM contactinfo WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $storedContact = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $personal['personal_profession'] = resumeRealValue((string) ($_POST['personal_profession'] ?? $storedProfession));
    $contact['linkedin'] = resumeRealValue((string) ($_POST['linkedin'] ?? $storedContact['linkedin'] ?? ''));
    $contact['github'] = resumeRealValue((string) ($_POST['github'] ?? $storedContact['github'] ?? ''));

    $education       = $_POST['education'] ?? [];
    $experience       = $_POST['experience'] ?? [];
    $skills           = $_POST['skills'] ?? [];
    $languages        = $_POST['languages'] ?? [];
    $interests        = $_POST['projects'] ?? [];
    $custom_sections  = $_POST['custom_sections'] ?? [];

    $display_branding = resumeShowBranding($db, $user_id, $user['username']);
    $sectionOrder = resumeSectionOrder((string) ($_POST['section_order'] ?? ''), array_keys($custom_sections));
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$username = $user['username'];

$titles = array_merge(resumeSectionTitles(resumeLanguage()), resumeSectionTitleOverrides());

/**
 * Escape a plain-text value for safe inclusion in LaTeX source.
 */
function latexEscape(?string $text): string
{
    $text = (string) $text;
    $text = str_replace('\\', "\x00BACKSLASH\x00", $text);
    $text = strtr($text, [
        '&' => '\\&',
        '%' => '\\%',
        '$' => '\\$',
        '#' => '\\#',
        '_' => '\\_',
        '{' => '\\{',
        '}' => '\\}',
        '~' => '\\textasciitilde{}',
        '^' => '\\textasciicircum{}',
    ]);
    $text = str_replace("\x00BACKSLASH\x00", '\\textbackslash{}', $text);
    // Collapse newlines from textareas into LaTeX paragraph breaks
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace("\n", "\\\\\n", $text);
    return $text;
}

function latexSection(string $title): string
{
    return "\\section*{" . latexEscape($title) . "}\n";
}

$profileLink = 'https://qrsume.com/' . rawurlencode($username);
$tex = '';

$tex .= "\\documentclass[10pt,a4paper]{article}\n";
$tex .= "\\usepackage[margin=0.85in]{geometry}\n";
$tex .= "\\usepackage[T1]{fontenc}\n";
$tex .= "\\usepackage[utf8]{inputenc}\n";
$tex .= "\\usepackage{titlesec}\n";
$tex .= "\\usepackage{enumitem}\n";
$tex .= "\\usepackage{xcolor}\n";
$tex .= "\\usepackage[hidelinks]{hyperref}\n";
$tex .= "\\pagenumbering{gobble}\n";
$tex .= "\\setlength{\\parindent}{0pt}\n";
$tex .= "\\titleformat{\\section}{\\large\\bfseries}{}{0em}{}[{\\vspace{-0.6em}\\hrule}]\n";
$tex .= "\\titlespacing{\\section}{0pt}{1em}{0.6em}\n";
// Document properties and a Unicode map for each glyph (pdflatex), so parsers read accents correctly
$fullName = trim($personal['personal_name'] . ' ' . $personal['personal_lastname']);
$keywords = implode(', ', array_filter(array_merge(
    array_map(fn(array $skill) => trim($skill['aptitude'] ?? ''), $skills),
    array_map(fn(array $lang) => trim($lang['language'] ?? ''), $languages)
)));
$tex .= "\\hypersetup{pdftitle={" . latexEscape(implode(' - ', array_filter([$fullName, $personal['personal_profession']]))) . "},"
    . " pdfauthor={" . latexEscape($fullName) . "}, pdfsubject={" . latexEscape($personal['personal_profession']) . "},"
    . " pdfkeywords={" . latexEscape($keywords) . "}, pdflang={" . resumeLanguage() . "}}\n";
$tex .= "\\ifdefined\\pdfgentounicode\\input{glyphtounicode}\\pdfgentounicode=1\\fi\n";
$tex .= "\\begin{document}\n\n";

// ========== HEADER ==========
$tex .= "{\\Huge\\bfseries " . latexEscape($fullName) . "}\\\\[0.3em]\n";
if ($personal['personal_profession'] !== '') {
    $tex .= "{\\large " . latexEscape($personal['personal_profession']) . "}\\\\[0.2em]\n";
}
$contactLine = [];
if ($contact['email'] !== '') {
    $contactLine[] = '\\href{mailto:' . $contact['email'] . '}{' . latexEscape($contact['email']) . '}';
}
if ($contact['phone_number'] !== '') {
    $contactLine[] = latexEscape($contact['phone_number']);
}
$webLine = [];
foreach (['linkedin', 'github'] as $network) {
    if ($contact[$network] !== '') {
        $webLine[] = '\\href{' . resumeLinkUrl($contact[$network]) . '}{' . latexEscape(resumeLinkText($contact[$network])) . '}';
    }
}
if ($display_branding) {
    $webLine[] = '\\href{' . $profileLink . '}{qrsume.com/' . latexEscape($username) . '}';
}
$tex .= implode(" \\\\\n", array_filter([implode(' $\\vert$ ', $contactLine), implode(' $\\vert$ ', $webLine)])) . "\n\n";

// ========== BIO ==========
if ($personalBio && $personal['personal_bio'] !== '') {
    $tex .= latexSection(resumeSectionTitles(resumeLanguage())['summary'] ?? 'Summary');
    $tex .= latexEscape($personal['personal_bio']) . "\n\n";
}

// ========== SECTIONS (in the order chosen in the preview) ==========
foreach ($sectionOrder as $sectionKey) {
    switch ($sectionKey) {
        // ========== EDUCATION ==========
        case 'education':
            if (empty($education) || !$display_education) {
                break;
            }
            $tex .= latexSection($titles['education']);
            foreach ($education as $edu) {
                $tex .= "\\textbf{" . latexEscape($edu['name_of_studies'] ?? '') . "}\\\\\n";
                $meta = implode(' | ', array_filter([trim($edu['place_of_study'] ?? ''), trim($edu['date'] ?? '')], 'strlen'));
                $tex .= "{\\color{darkgray}" . latexEscape($meta) . "}\n\n";
                if ($showDescription && !empty($edu['desc'])) {
                    $tex .= latexEscape($edu['desc']) . "\n\n";
                }
            }
            break;

        // ========== WORK EXPERIENCE ==========
        case 'experience':
            if (empty($experience) || !$display_experience) {
                break;
            }
            $tex .= latexSection($titles['experience']);
            foreach ($experience as $exp) {
                $jobTitle = resumeJobTitle($exp);
                $tex .= "\\textbf{" . latexEscape($jobTitle) . "}\\\\\n";
                if (trim($exp['date'] ?? '') !== '') {
                    $tex .= "{\\color{darkgray}" . latexEscape(trim($exp['date'])) . "}\\\\\n";
                }
                $tex .= latexEscape($exp['brief_description'] ?? '') . "\n\n";
            }
            break;

        // ========== SKILLS ==========
        case 'skills':
            if (empty($skills) || !$display_skills) {
                break;
            }
            $tex .= latexSection($titles['skills']);
            $tex .= latexEscape(implode(', ', array_filter(array_map(fn(array $skill) => trim($skill['aptitude'] ?? ''), $skills), 'strlen'))) . "\n\n";
            break;

        // ========== LANGUAGES ==========
        case 'languages':
            if (empty($languages) || !$display_languages) {
                break;
            }
            $tex .= latexSection($titles['languages']);
            $tex .= latexEscape(implode(', ', array_filter(array_map('resumeLanguageLine', $languages), 'strlen'))) . "\n\n";
            break;

        // ========== PROJECTS / INTERESTS ==========
        case 'projects':
            if (empty($interests) || !$display_projects) {
                break;
            }
            $tex .= latexSection($titles['projects']);
            foreach ($interests as $int) {
                $tex .= "\\textbf{" . latexEscape($int['interest'] ?? '') . "}\\\\\n";
                $tex .= latexEscape($int['description'] ?? '') . "\n\n";
            }
            break;

        // ========== CUSTOM SECTIONS ==========
        default:
            $i = substr($sectionKey, strlen('custom_'));
            $section = $custom_sections[$i] ?? null;
            if ($section === null || empty($display_custom_sections[$i])) {
                break;
            }
            $tex .= latexSection(mb_strtoupper($section['section_title'] ?? ''));
            $tex .= latexEscape($section['section_content'] ?? '') . "\n\n";
            break;
    }
}

$tex .= "\\end{document}\n";

// ========== OUTPUT .tex FILE ==========
$filename = resumeFilename($personal['personal_name'], $personal['personal_lastname'], $username, 'tex');

header('Content-Type: application/x-tex; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($tex));
echo $tex;
exit();
