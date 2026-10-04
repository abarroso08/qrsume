<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);           // Report all types of errors
    ini_set('display_errors', '1');     // Display errors on screen
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

// Store user ID for further use
$user_id = (int) $user['id'];

/**
 * Load a photo, center-crop it to the preview's 110:130 ratio and return it as JPEG bytes.
 */
function loadResumePhoto(string $path): ?string
{
    if (!function_exists('imagecreatefromstring') || !is_file($path)) {
        return null;
    }

    $data = file_get_contents($path);
    $image = $data !== false ? @imagecreatefromstring($data) : false;
    if (!$image) {
        return null;
    }

    // Respect phone camera orientation, like the browser preview does
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $rotation = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
        if ($rotation !== 0) {
            $image = imagerotate($image, $rotation, 0);
        }
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $ratio = 110 / 130;

    if ($width / $height > $ratio) {
        $cropHeight = $height;
        $cropWidth = (int) round($height * $ratio);
    } else {
        $cropWidth = $width;
        $cropHeight = (int) round($width / $ratio);
    }

    $output = imagecreatetruecolor(330, 390);
    imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
    imagecopyresampled(
        $output, $image, 0, 0,
        (int) (($width - $cropWidth) / 2), (int) (($height - $cropHeight) / 2),
        330, 390, $cropWidth, $cropHeight
    );

    ob_start();
    imagejpeg($output, null, 90);
    return ob_get_clean() ?: null;
}

try {
    $pdo = $db;
    // Fetch user Info
    $stmt = $pdo->prepare("SELECT username,email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT personal_photo, personal_profession FROM personalinfo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $storedPersonal = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $storedPhoto = (string) ($storedPersonal['personal_photo'] ?? '');

    $stmt = $pdo->prepare("SELECT linkedin, github FROM contactinfo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $storedContact = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // ========== GET POST DATA ==========
    $fontName = isset($_POST['font']) && in_array($_POST['font'], ['times', 'helvetica']) ? $_POST['font'] : 'times';
    $display_branding = resumeShowBranding($db, $user_id, $user['username']);
    $display_photo = isset($_POST['show_photo']);
    $display_education  = isset($_POST['show_education']);
    $display_experience = isset($_POST['show_experience']);
    $display_skills     = isset($_POST['show_skills']);
    $display_languages  = isset($_POST['show_languages']);
    $display_projects   = isset($_POST['show_projects']);
    $display_custom_sections = [];
    $showDescription = isset($_POST['show_education_description']);
    $personalBio = isset($_POST["show_bio"]);


    foreach ($_POST['custom_sections'] ?? [] as $i => $section) {
        // This will be true if the checkbox was checked (submitted)
        $display_custom_sections[$i] = isset($section['show']) && $section['show'] === '1';
    }


    $personal['personal_name']     = $_POST['personal_name'] ?? '';
    $personal['personal_lastname'] = $_POST['personal_lastname'] ?? '';
    $contact['email']              = $_POST['email'] ?? '';
    $contact['phone_number']       = $_POST['phone_number'] ?? '';

    $personal["personal_bio"] = $_POST['personal_bio'] ?? '';
    $personal['personal_profession'] = resumeRealValue((string) ($_POST['personal_profession'] ?? $storedPersonal['personal_profession'] ?? ''));
    $contact['linkedin'] = resumeRealValue((string) ($_POST['linkedin'] ?? $storedContact['linkedin'] ?? ''));
    $contact['github'] = resumeRealValue((string) ($_POST['github'] ?? $storedContact['github'] ?? ''));
    $education  = resumeEntries($_POST['education'] ?? [], ['name_of_studies', 'date', 'place_of_study', 'desc']);
    $experience = resumeEntries($_POST['experience'] ?? [], ['job_name', 'place_of_work', 'date', 'brief_description']);
    $skills     = resumeEntries($_POST['skills'] ?? [], ['aptitude']);
    $languages  = resumeEntries($_POST['languages'] ?? [], ['language', 'level']);
    $interests  = resumeEntries($_POST['projects'] ?? [], ['interest', 'description']);
    $custom_sections  = resumeEntries($_POST['custom_sections'] ?? [], ['section_title', 'section_content']);

    $sectionOrder = resumeSectionOrder((string) ($_POST['section_order'] ?? ''), array_keys($custom_sections));


} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ========== PHOTO ==========
// A freshly uploaded photo wins; otherwise use the stored profile photo (never a client-supplied path).
$photoData = null;
if ($display_photo) {
    $upload = $_FILES['photo_upload'] ?? null;
    if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        && $upload['size'] <= 10 * 1024 * 1024 && is_uploaded_file($upload['tmp_name'])) {
        $photoData = loadResumePhoto($upload['tmp_name']);
    }
    if ($photoData === null && $storedPhoto !== '' && $storedPhoto !== 'default.webp') {
        // Stored as "username/profile_picture/name.webp?ts=123": drop the cache-busting query
        // and make sure the resolved file stays inside images/
        $imagesDir = realpath(__DIR__ . '/images');
        $photoPath = realpath(__DIR__ . '/images/' . strtok($storedPhoto, '?'));
        if ($imagesDir !== false && $photoPath !== false && str_starts_with($photoPath, $imagesDir . DIRECTORY_SEPARATOR)) {
            $photoData = loadResumePhoto($photoPath);
        }
        if ($photoData === null) {
            error_log("create_pdf: could not load stored photo for user {$user_id}: {$storedPhoto}");
        }
    }
}

$pageCountOnly = isset($_GET['page_count']);
$hasPhoto = $photoData !== null || ($pageCountOnly && $display_photo && isset($_POST['photo_pending']));

// Include TCPDF
require 'vendor/autoload.php';

/**
 * TCPDF writes an invisible "Powered by TCPDF (www.tcpdf.org)" line on the last page,
 * which recruiting software reads as part of the CV.
 */
class ResumePdf extends TCPDF
{
    public function __construct(...$arguments)
    {
        parent::__construct(...$arguments);
        $this->tcpdflink = false;
    }
}



$username = $user['username'];
// Create PDF
$pdf = new ResumePdf('P', 'pt', 'A4', true, 'UTF-8', false);

// Document properties: recruiting software and AI screeners read these
$language = resumeLanguage();
$fullName = trim($personal['personal_name'] . ' ' . $personal['personal_lastname']);
$documentWord = $language === 'es' ? 'Currículum' : 'Resume';
$pdf->SetCreator('QRsume');
$pdf->SetAuthor($fullName);
$pdf->SetTitle(implode(' - ', array_filter([$fullName, $personal['personal_profession'], $documentWord])));
$pdf->SetSubject($personal['personal_profession'] !== '' ? $personal['personal_profession'] : $documentWord);
$pdf->SetKeywords(implode(', ', array_slice(array_values(array_filter(array_merge(
    array_map(fn(array $skill) => trim($skill['aptitude']), $skills),
    array_map(fn(array $lang) => trim($lang['language']), $languages)
))), 0, 40)));
$pdf->setLanguageArray(['a_meta_charset' => 'UTF-8', 'a_meta_dir' => 'ltr', 'a_meta_language' => $language, 'w_page' => 'page']);

// Disable header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(true, 15);
$pageWidth  = $pdf->getPageWidth();
$pageHeight = $pdf->getPageHeight();
$marginL = 40;
$marginT = 20;
$marginR = 30;
$pdf->SetMargins($marginL, $marginT, $marginR);

// Font settings

$contentFontSize = isset($_POST['contentFontSize']) && in_array((int) $_POST['contentFontSize'], [9, 10, 11, 12], true)
    ? (int) $_POST['contentFontSize']
    : 10;
$sectionFontSize = $contentFontSize * 1.3; // e.g. 14 if content is 10
$titleFontSize   = $contentFontSize * 1.1; // e.g. 11 if content is 10
$nameFontSize    = $contentFontSize * 2.5; // e.g. 25 if content is 10
$spaceSize = isset($_POST['spaceSize']) && in_array((int) $_POST['spaceSize'], [5, 10, 20, 30], true)
    ? (int) $_POST['spaceSize']
    : 10;
$spaceSection = 0.5  * $spaceSize;
$spaceTitle = 0.4    * $spaceSize;
$spaceContent = 0.3  * $spaceSize;
$spaceList = 0.1 * $spaceSize;


//VARIABLES
$header_position = "C";


$titles = array_map('mb_strtoupper', resumeSectionTitles(resumeLanguage()));
$titles['full_profile'] = resumeSectionTitles(resumeLanguage())['full_profile'];
$titles['summary'] = mb_strtoupper(resumeSectionTitles(resumeLanguage())['summary']);
foreach (resumeSectionTitleOverrides() as $key => $title) {
    $titles[$key] = mb_strtoupper($title);
}

$profileText = "qrsume.com/" . $username;
$profileUrl = "https://qrsume.com/" . rawurlencode($username);




// Add page
$pdf->AddPage();

// ========== HEADER ==========
$headerTop = $pdf->GetY();
$headerX = $marginL;
$headerWidth = $pageWidth - $marginL - $marginR;
$photoWidth = 66;
$photoHeight = 78;
$nameHeight = $nameFontSize * 1.25; // TCPDF never makes a cell shorter than font size x 1.25
$professionFontSize = $contentFontSize * 1.2;
$professionHeight = $professionFontSize * 1.25;
$contactHeight = $contentFontSize + 4;

// Contact lines as plain text (what parsers read) that are also links
$contactLines = [];
$directLine = [];
if (trim($contact['email']) !== '') {
    $directLine[] = [trim($contact['email']), 'mailto:' . trim($contact['email']), false];
}
if (trim($contact['phone_number']) !== '') {
    $directLine[] = [trim($contact['phone_number']), 'tel:' . preg_replace('/[^\d+]/', '', $contact['phone_number']), false];
}
if ($directLine !== []) {
    $contactLines[] = $directLine;
}
$webLine = [];
foreach (['linkedin', 'github'] as $network) {
    if ($contact[$network] !== '') {
        $webLine[] = [resumeLinkText($contact[$network]), resumeLinkUrl($contact[$network]), true];
    }
}
if ($display_branding) {
    $webLine[] = [$profileText, $profileUrl, true];
}
if ($webLine !== []) {
    $contactLines[] = $webLine;
}

$textBlockHeight = $nameHeight + ($personal['personal_profession'] !== '' ? $professionHeight : 0) + count($contactLines) * $contactHeight;

if ($hasPhoto) {
    if ($photoData !== null) {
        $pdf->Image('@' . $photoData, $marginL, $headerTop, $photoWidth, $photoHeight, 'JPG');
        $pdf->SetLineStyle(array('width' => 0.75, 'color' => array(207, 207, 207)));
        $pdf->Rect($marginL, $headerTop, $photoWidth, $photoHeight);
    }

    // Name and contact sit right next to the photo, left-aligned
    $header_position = "L";
    $headerX += $photoWidth + 12;
    $headerWidth -= $photoWidth + 12;
    $pdf->SetY($headerTop + max(0, ($photoHeight - $textBlockHeight) / 2));
}

$pdf->SetFont($fontName, 'B', $nameFontSize);
$pdf->SetX($headerX);
$pdf->Cell($headerWidth, $nameHeight, $fullName, 0, 1, $header_position, false, '', 1);

if ($personal['personal_profession'] !== '') {
    $pdf->SetFont($fontName, '', $professionFontSize);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetX($headerX);
    $pdf->Cell($headerWidth, $professionHeight, $personal['personal_profession'], 0, 1, $header_position, false, '', 1);
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->SetFont($fontName, '', $contentFontSize);
$separator = ' | ';
foreach ($contactLines as $line) {
    $width = 0;
    foreach ($line as $index => [$text]) {
        $width += $pdf->GetStringWidth($text) + ($index > 0 ? $pdf->GetStringWidth($separator) : 0);
    }
    // Centered without a photo, left-aligned next to it
    $pdf->SetX($header_position === "L" ? $headerX : $headerX + max(0, ($headerWidth - $width) / 2));

    foreach ($line as $index => [$text, $url, $isWebLink]) {
        if ($index > 0) {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($pdf->GetStringWidth($separator), $contactHeight, $separator, 0, 0, 'L', false, '', 0, false, 'T', 'M');
        }
        $isWebLink ? $pdf->SetTextColor(0, 0, 255) : $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($pdf->GetStringWidth($text), $contactHeight, $text, 0, 0, 'L', false, $url, 0, false, 'T', 'M');
    }
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln($contactHeight);
}

if ($hasPhoto) {
    $pdf->SetY(max($pdf->GetY(), $headerTop + $photoHeight + 5));
}

$pdf->SetLineStyle(array('width' => 1, 'color' => array(0, 0, 0)));


function printSectionTitle(TCPDF $pdf, string $title, string $fontName, float $sectionFontSize, float $marginL, float $marginR, float $pageWidth, float $spaceTitle): void
{
    $pdf->SetFont($fontName, 'B', $sectionFontSize);
    $pdf->Cell(0, 20, $title, 0, 1, 'L');
    $pdf->Line($marginL, $pdf->GetY(), $pageWidth - $marginR, $pdf->GetY());
    $pdf->Ln($spaceTitle);
}

/**
 * A grey line under an entry title (dates, school): read right after the title it belongs to.
 */
function printMetaLine(TCPDF $pdf, string $text, string $fontName, float $contentFontSize): void
{
    $pdf->SetFont($fontName, '', $contentFontSize);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->MultiCell(0, 15, $text, 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
}

//=========== PERSONAL BIO ========
if ($personalBio && trim($personal['personal_bio']) !== '') {
    $pdf->Ln(10);
    printSectionTitle($pdf, $titles['summary'], $fontName, $sectionFontSize, $marginL, $marginR, $pageWidth, $spaceTitle);
    $pdf->SetFont($fontName, '', $contentFontSize);
    $pdf->MultiCell(0, 15, "{$personal['personal_bio']}", 0, 'L');
    $pdf->Ln($spaceSection);
}


// ========== SECTIONS (in the order chosen in the preview) ==========
foreach ($sectionOrder as $sectionKey) {
    switch ($sectionKey) {

        // ========== EDUCATION ==========
        case 'education':
            if (empty($education) || !$display_education) {
                break;
            }
            printSectionTitle($pdf, $titles['education'], $fontName, $sectionFontSize, $marginL, $marginR, $pageWidth, $spaceTitle);

            foreach ($education as $edu) {
                $pdf->SetFont($fontName, 'B', $titleFontSize);
                $pdf->MultiCell(0, 15, $edu['name_of_studies'], 0, 'L');

                $meta = implode(' | ', array_filter([trim($edu['place_of_study']), trim($edu['date'])], 'strlen'));
                if ($meta !== '') {
                    printMetaLine($pdf, $meta, $fontName, $contentFontSize);
                }
                $pdf->SetFont($fontName, '', $contentFontSize);

                // Show description if enabled
                if (!empty($showDescription) && !empty($edu['desc'])) {
                    $pdf->Ln(1); // Small space before description
                    $pdf->MultiCell(0, 15, "{$edu['desc']}", 0, 'L');
                }

                $pdf->Ln($spaceContent);
            }
            $pdf->Ln($spaceSection);
            break;

        // ========== WORK EXPERIENCE ==========
        case 'experience':
            if (empty($experience) || !$display_experience) {
                break;
            }
            printSectionTitle($pdf, $titles['experience'], $fontName, $sectionFontSize, $marginL, $marginR, $pageWidth, $spaceTitle);

            foreach ($experience as $exp) {
                $pdf->SetFont($fontName, 'B', $titleFontSize);

                // "Job title - Company", then the dates on their own line
                $pdf->MultiCell(0, 15, resumeJobTitle($exp), 0, 'L');
                if (trim($exp['date']) !== '') {
                    printMetaLine($pdf, trim($exp['date']), $fontName, $contentFontSize);
                }
                $pdf->Ln($spaceList);
                $pdf->SetFont($fontName, '', $contentFontSize);
                $pdf->SetX($pdf->GetX() + 10);
                $pdf->MultiCell($pageWidth * 3 / 4, 20, "{$exp['brief_description']}", 0, 'L');
                $pdf->SetX($pdf->GetX() - 10);
                $pdf->Ln($spaceContent);
            }
            $pdf->Ln($spaceSection);
            break;

        // ========== SKILLS ==========
        case 'skills':
            if (empty($skills) || !$display_skills) {
                break;
            }
            printSectionTitle($pdf, $titles['skills'], $fontName, $sectionFontSize, $marginL, $marginR, $pageWidth, $spaceTitle);
            $pdf->SetFont($fontName, '', $contentFontSize);
            $pdf->MultiCell(0, 15, implode(', ', array_filter(array_map(fn(array $skill) => trim($skill['aptitude']), $skills), 'strlen')), 0, 'L');
            $pdf->Ln($spaceSection);
            break;

        // ========== LANGUAGES ==========
        case 'languages':
            if (empty($languages) || !$display_languages) {
                break;
            }
            printSectionTitle($pdf, $titles['languages'], $fontName, $sectionFontSize, $marginL, $marginR, $pageWidth, $spaceTitle);
            $pdf->SetFont($fontName, '', $contentFontSize);
            $pdf->MultiCell(0, 15, implode(', ', array_filter(array_map('resumeLanguageLine', $languages), 'strlen')), 0, 'L');
            $pdf->Ln($spaceSection);
            break;

        // ========== PROJECTS ==========
        case 'projects':
            if (empty($interests) || !$display_projects) {
                break;
            }
            printSectionTitle($pdf, $titles['projects'], $fontName, $sectionFontSize, $marginL, $marginR, $pageWidth, $spaceTitle);
            foreach ($interests as $int) {
                $pdf->SetFont($fontName, 'B', $titleFontSize);

                // Print the bullet point as a bold line using MultiCell
                $pdf->MultiCell(0, 15, "• " . "{$int['interest']}", 0, 'L');

                // Reset font and print the description
                $pdf->SetFont($fontName, '', $contentFontSize);
                $pdf->SetX($pdf->GetX() + 10);
                $desc = $int['description'];

                // Escapar caracteres especiales HTML primero
                $descEscaped = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');

                // Regex mejorado para evitar capturar paréntesis y puntos al final
                $descWithLinks = preg_replace_callback(
                    '/(https?:\/\/[^\s<>"\']+[^\s<>"\'.,;)])/i',
                    function ($matches) {
                        $url = $matches[1];
                        return '<a href="' . $url . '" style="color:#0000FF;">' . $url . '</a>';
                    },
                    $descEscaped
                );

                // Mostrarlo con TCPDF
                $pdf->writeHTML("<div>{$descWithLinks}</div>", true, false, true, false, '');

                $pdf->SetX($pdf->GetX() - 10);
                $pdf->Ln($spaceContent); // Line break for spacing
            }
            $pdf->Ln($spaceSection);
            break;

        // ========== CUSTOM SECTIONS ==========
        default:
            $i = substr($sectionKey, strlen('custom_'));
            $section = $custom_sections[$i] ?? null;

            // Only render if checkbox was checked
            if ($section === null || empty($display_custom_sections[$i])) {
                break;
            }

            printSectionTitle($pdf, mb_strtoupper($section['section_title'] ?? ''), $fontName, $sectionFontSize, $marginL, $marginR, $pageWidth, $spaceTitle);

            // Section content (multi-line)
            $pdf->SetFont($fontName, '', $contentFontSize);
            $pdf->MultiCell(0, 15, $section['section_content'] ?? '', 0, 'L');
            $pdf->Ln($spaceSection);
            break;
    }
}



if ($pageCountOnly) {
    header('Content-Type: application/json');
    echo json_encode(['pages' => $pdf->getNumPages()]);
    exit();
}

// ========== QR CODE + BOTTOM LINK ==========
if ($display_branding) {
    // QR Code styling
    $pdf->SetAutoPageBreak(false, 0);

    $style = array(
        'border' => 0,
        'vpadding' => 'auto',
        'hpadding' => 'auto',
        'fgcolor' => array(0,0,0),
        'bgcolor' => false,
        'module_width' => 2, // QR code module width
        'module_height' => 2  // QR code module height
    );

    // Define the QR Code destination URL with qr_scan parameter
    $qrLink = "https://www.qrsume.com/" . rawurlencode($username) . "?qr_scan=true";

    $qrSize = 60;  // QR Code size

    // Position QR code in the bottom-right corner
    $x = $pageWidth - $qrSize - $marginR / 2;
    $y = $pageHeight - $qrSize - 10;

    // Print QR Code
    $pdf->SetMargins(0, 0, 0);
    $pdf->write2DBarcode($qrLink, 'QRCODE,H', $x, $y, $qrSize, $qrSize, $style, '');
    $pdf->SetMargins($marginL, $marginT, $marginR);

    // Position text directly below the QR code
    $pdf->SetFont('helvetica', '', 8);
    $qrtext = $titles['full_profile'];
    $qrtextWidth = $pdf->GetStringWidth($qrtext);
    $textX = $x + $qrSize / 2 - $qrtextWidth / 2 - 5;
    $textY = $y + $qrSize - 5;

    $pdf->SetXY($textX - 5, $textY);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 1, $qrtext, 0, 1, 'L');

    // Bottom link
    $pdf->SetFont('helvetica', 'U', 8);
    $pdf->SetTextColor(0, 0, 255);
    $pdf->SetXY($x, $textY - 10);
    $pdf->Write(0, $profileText, $qrLink, false, 'C', true);
}

// ========== OUTPUT PDF ==============
$pdf->Output(resumeFilename($personal['personal_name'], $personal['personal_lastname'], $username, 'pdf'), 'I');
