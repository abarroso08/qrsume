<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Database connection
include("assets/db.php");

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

$user_id = $user['id'];

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

    $education       = $_POST['education'] ?? [];
    $experience       = $_POST['experience'] ?? [];
    $skills           = $_POST['skills'] ?? [];
    $languages        = $_POST['languages'] ?? [];
    $interests        = $_POST['projects'] ?? [];
    $custom_sections  = $_POST['custom_sections'] ?? [];
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$username = $user['username'];

$language = isset($_GET['spanish']) && $_GET['spanish'] === 'true' ? 'spanish' : 'english';

$spanish_titles = [
    'education'       => 'Educación',
    'work_experience' => 'Experiencia Laboral',
    'skills'          => 'Aptitudes',
    'languages'       => 'Idiomas',
    'projects'        => 'Proyectos',
];
$english_titles = [
    'education'       => 'Education',
    'work_experience' => 'Work Experience',
    'skills'          => 'Skills',
    'languages'       => 'Languages',
    'projects'        => 'Projects',
];
$titles = ($language === 'spanish') ? $spanish_titles : $english_titles;

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
$tex .= "\\begin{document}\n\n";

// ========== HEADER ==========
$tex .= "{\\Huge\\bfseries " . latexEscape(trim($personal['personal_name'] . ' ' . $personal['personal_lastname'])) . "}\\\\[0.3em]\n";
$contactLine = [];
if ($contact['email'] !== '') {
    $contactLine[] = latexEscape($contact['email']);
}
if ($contact['phone_number'] !== '') {
    $contactLine[] = latexEscape($contact['phone_number']);
}
$contactLine[] = '\\href{' . $profileLink . '}{qrsume.com/' . latexEscape($username) . '}';
$tex .= implode(' $\\vert$ ', $contactLine) . "\n\n";

// ========== BIO ==========
if ($personalBio && $personal['personal_bio'] !== '') {
    $tex .= "\\vspace{0.8em}\n" . latexEscape($personal['personal_bio']) . "\n\n";
}

// ========== EDUCATION ==========
if (!empty($education) && $display_education) {
    $tex .= latexSection($titles['education']);
    foreach ($education as $edu) {
        $tex .= "\\textbf{" . latexEscape($edu['name_of_studies'] ?? '') . "} \\hfill " . latexEscape($edu['date'] ?? '') . "\\\\\n";
        $tex .= latexEscape($edu['place_of_study'] ?? '') . "\n\n";
        if ($showDescription && !empty($edu['desc'])) {
            $tex .= latexEscape($edu['desc']) . "\n\n";
        }
    }
}

// ========== WORK EXPERIENCE ==========
if (!empty($experience) && $display_experience) {
    $tex .= latexSection($titles['work_experience']);
    foreach ($experience as $exp) {
        $jobTitle = trim(($exp['job_name'] ?? '') . ' ' . ($exp['place_of_work'] ?? ''));
        $tex .= "\\textbf{" . latexEscape($jobTitle) . "} \\hfill " . latexEscape($exp['date'] ?? '') . "\\\\\n";
        $tex .= latexEscape($exp['brief_description'] ?? '') . "\n\n";
    }
}

// ========== SKILLS ==========
if (!empty($skills) && $display_skills) {
    $tex .= latexSection($titles['skills']);
    $tex .= "\\begin{itemize}[leftmargin=1.2em,itemsep=0pt,topsep=0pt]\n";
    foreach ($skills as $skill) {
        $tex .= "\\item " . latexEscape($skill['aptitude'] ?? '') . "\n";
    }
    $tex .= "\\end{itemize}\n\n";
}

// ========== LANGUAGES ==========
if (!empty($languages) && $display_languages) {
    $tex .= latexSection($titles['languages']);
    $tex .= "\\begin{itemize}[leftmargin=1.2em,itemsep=0pt,topsep=0pt]\n";
    foreach ($languages as $lang) {
        $tex .= "\\item " . latexEscape(($lang['language'] ?? '') . ' (' . ($lang['level'] ?? '') . ')') . "\n";
    }
    $tex .= "\\end{itemize}\n\n";
}

// ========== PROJECTS / INTERESTS ==========
if (!empty($interests) && $display_projects) {
    $tex .= latexSection($titles['projects']);
    foreach ($interests as $int) {
        $tex .= "\\textbf{" . latexEscape($int['interest'] ?? '') . "}\\\\\n";
        $tex .= latexEscape($int['description'] ?? '') . "\n\n";
    }
}

// ========== CUSTOM SECTIONS ==========
if (!empty($custom_sections)) {
    foreach ($custom_sections as $i => $section) {
        if (empty($display_custom_sections[$i])) {
            continue;
        }
        $tex .= latexSection(strtoupper($section['section_title'] ?? ''));
        $tex .= latexEscape($section['section_content'] ?? '') . "\n\n";
    }
}

$tex .= "\\end{document}\n";

// ========== OUTPUT .tex FILE ==========
$filename = 'resume_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $personal['personal_name'] ?: $username) . '.tex';

header('Content-Type: application/x-tex; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($tex));
echo $tex;
exit();
