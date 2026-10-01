<?php

declare(strict_types=1);

/**
 * Resume generation options shared by preview.php, create_pdf.php and create_latex.php.
 */

require_once __DIR__ . '/premium.php';

const RESUME_BUILT_IN_SECTIONS = ['education', 'experience', 'skills', 'languages', 'projects'];

const RESUME_SECTION_TITLES = [
    'en' => [
        'education'    => 'Education',
        'experience'   => 'Work Experience',
        'skills'       => 'Skills',
        'languages'    => 'Languages',
        'projects'     => 'Projects',
        'full_profile' => 'Full Profile & Projects',
    ],
    'es' => [
        'education'    => 'Educación',
        'experience'   => 'Experiencia Laboral',
        'skills'       => 'Aptitudes',
        'languages'    => 'Idiomas',
        'projects'     => 'Proyectos',
        'full_profile' => 'Perfil Completo y Proyectos',
    ],
];

/**
 * Resume language: POST/GET "lan" (en|es), with the legacy "?spanish=true" still accepted.
 */
function resumeLanguage(): string
{
    $lan = $_POST['lan'] ?? $_GET['lan'] ?? null;

    if ($lan === null && ($_GET['spanish'] ?? '') === 'true') {
        $lan = 'es';
    }

    return $lan === 'es' ? 'es' : 'en';
}

function resumeSectionTitles(string $language): array
{
    return RESUME_SECTION_TITLES[$language] ?? RESUME_SECTION_TITLES['en'];
}

/**
 * Parse the comma-separated section order sent by preview.php.
 * Unknown keys and duplicates are dropped; missing sections keep their default position at the end.
 */
function resumeSectionOrder(string $rawOrder, array $customSectionIndexes): array
{
    $allowed = RESUME_BUILT_IN_SECTIONS;
    foreach ($customSectionIndexes as $index) {
        $allowed[] = 'custom_' . $index;
    }

    $order = [];
    foreach (explode(',', $rawOrder) as $key) {
        $key = trim($key);
        if (in_array($key, $allowed, true) && !in_array($key, $order, true)) {
            $order[] = $key;
        }
    }

    foreach ($allowed as $key) {
        if (!in_array($key, $order, true)) {
            $order[] = $key;
        }
    }

    return $order;
}

/**
 * Branding can only be removed by the logged-in owner of the resume who purchased it.
 */
function resumeCanRemoveBranding(PDO $db, int $userId, string $username): bool
{
    if (!isset($_SESSION['username']) || strcasecmp((string) $_SESSION['username'], $username) !== 0) {
        return false;
    }

    try {
        return userHasPurchase($db, $userId, 'remove_qrsume_branding');
    } catch (PDOException $exception) {
        error_log('Branding purchase check failed: ' . $exception->getMessage());
        return false;
    }
}

/**
 * Whether the QR code and QRsume links must be printed. The "show_QR" checkbox is only honoured
 * for users allowed to remove branding; everyone else always gets it.
 */
function resumeShowBranding(PDO $db, int $userId, string $username): bool
{
    return !resumeCanRemoveBranding($db, $userId, $username) || isset($_POST['show_QR']);
}

/**
 * "Job title - Company", skipping empty parts.
 */
function resumeJobTitle(array $experience): string
{
    $parts = array_filter(
        [trim((string) ($experience['job_name'] ?? '')), trim((string) ($experience['place_of_work'] ?? ''))],
        static fn(string $part): bool => $part !== ''
    );

    return implode(' - ', $parts);
}

/**
 * Safe download filename based on the person's name.
 */
function resumeFilename(string $name, string $lastname, string $fallback, string $extension): string
{
    $base = trim($name . ' ' . $lastname);
    if ($base === '') {
        $base = $fallback;
    }

    // Drop accents (José -> Jose) so the name survives the ASCII filter below
    if (class_exists('Normalizer')) {
        $base = preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($base, Normalizer::FORM_D)) ?? $base;
    }

    $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base);

    return 'resume_' . trim((string) $base, '_') . '.' . $extension;
}
