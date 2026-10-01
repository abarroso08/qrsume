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

    $stmt = $pdo->prepare("SELECT personal_photo FROM personalinfo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $storedPhoto = (string) ($stmt->fetchColumn() ?: '');

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



$username = $user['username'];
// Create PDF
$pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);

// Document settings
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor($personal['personal_name']);
$pdf->SetTitle("Resume - " . $personal['personal_name']);

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

$languages_dots = "• ";

$titles = array_map('mb_strtoupper', resumeSectionTitles(resumeLanguage()));
$titles['full_profile'] = resumeSectionTitles(resumeLanguage())['full_profile'];
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
$contactHeight = $contentFontSize + 4;

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
    $pdf->SetY($headerTop + max(0, ($photoHeight - $nameHeight - $contactHeight) / 2));
}

$pdf->SetFont($fontName, 'B', $nameFontSize);
$pdf->SetX($headerX);
$pdf->Cell($headerWidth, $nameHeight, trim($personal['personal_name'] . ' ' . $personal['personal_lastname']), 0, 1, $header_position, false, '', 1);

$pdf->SetFont($fontName, '', $contentFontSize);

$contactParts = array_values(array_filter(
    [trim($contact['email']), trim($contact['phone_number'])],
    static fn(string $part): bool => $part !== ''
));
$contactInfo = implode(' | ', $contactParts);
$linkText = '';
if ($display_branding) {
    $linkText = $profileText;
    if ($contactInfo !== '') {
        $contactInfo .= ' | ';
    }
}

// Medir anchos
$contactWidth = $pdf->GetStringWidth($contactInfo);
$linkWidth = $pdf->GetStringWidth($linkText);
$totalWidth = $contactWidth + $linkWidth;

// Calcular X: centrado sin foto, alineado a la izquierda junto a la foto
$pdf->SetX($header_position === "L" ? $headerX : $headerX + max(0, ($headerWidth - $totalWidth) / 2));

// Escribir parte en negro
$pdf->SetTextColor(0, 0, 0);
if ($contactInfo !== '') {
    $pdf->Cell($contactWidth, $contactHeight, $contactInfo, 0, 0);
}

// Escribir parte azul como link
if ($linkText !== '') {
    $pdf->SetTextColor(0, 0, 255);
    $pdf->Cell($linkWidth, $contactHeight, $linkText, 0, 0, 'L', false, $profileUrl);
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Ln($contactHeight);

if ($hasPhoto) {
    $pdf->SetY(max($pdf->GetY(), $headerTop + $photoHeight + 5));
}

$pdf->SetLineStyle(array('width' => 1, 'color' => array(0, 0, 0)));


//=========== PERSONAL BIO ========
if ($personalBio && trim($personal['personal_bio']) !== '') {
    $pdf->SetFont($fontName, '', $contentFontSize);
    $pdf->Ln(10);
    $pdf->MultiCell(0, 15, "{$personal['personal_bio']}", 0, 'L');
    $pdf->Ln($spaceSection);
}

function printSectionTitle(TCPDF $pdf, string $title, string $fontName, float $sectionFontSize, float $marginL, float $marginR, float $pageWidth, float $spaceTitle): void
{
    $pdf->SetFont($fontName, 'B', $sectionFontSize);
    $pdf->Cell(0, 20, $title, 0, 1, 'L');
    $pdf->Line($marginL, $pdf->GetY(), $pageWidth - $marginR, $pdf->GetY());
    $pdf->Ln($spaceTitle);
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
                $pdf->MultiCell($pageWidth * 3 / 4, 15, "{$edu['name_of_studies']}", 0, 'L', 0, 0, '', '', true);
                $pdf->Cell(0, 15, "{$edu['date']}", 0, 1, 'R');

                // Estimate lines
                $lines = $pdf->getNumLines("{$edu['name_of_studies']}", ($pageWidth * 3 / 4) - 15);
                if ($lines > 1) {
                    $pdf->setXY($pdf->GetX(), $pdf->GetY() + 15);
                }

                $pdf->SetFont($fontName, '', $contentFontSize);
                $pdf->MultiCell(0, 15, "{$edu['place_of_study']}", 0, 'L');

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

                // "Job title - Company"
                $jobTitle = resumeJobTitle($exp);
                $pdf->MultiCell($pageWidth * 3 / 4, 15, $jobTitle, 0, 'L', 0, 0, '', '', true);
                $pdf->Cell(0, 15, "{$exp['date']}", 0, 1, 'R');

                // Adjust vertical position if job title wraps
                $lines = $pdf->getNumLines($jobTitle, ($pageWidth * 3 / 4) - 30);
                if ($lines > 1) {
                    $pdf->setXY($pdf->GetX(), $pdf->GetY() + 15);
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
            $counter = 1;
            foreach ($skills as $skill) {
                if ($counter == 1) {
                    $pdf->Cell($pageWidth / 2, 15, "• " . $skill['aptitude'], 0, 0, 'L');
                    $counter++;
                } else {
                    $pdf->Cell($pageWidth / 2, 15, "• " . $skill['aptitude'], 0, 1, 'L');
                    $counter = 1;
                }
            }
            if ($counter == 2) {
                $pdf->Ln(15);
            }
            $pdf->Ln($spaceSection);
            break;

        // ========== LANGUAGES ==========
        case 'languages':
            if (empty($languages) || !$display_languages) {
                break;
            }
            printSectionTitle($pdf, $titles['languages'], $fontName, $sectionFontSize, $marginL, $marginR, $pageWidth, $spaceTitle);
            $pdf->SetFont($fontName, '', $contentFontSize);
            $counterLang = 1;
            foreach ($languages as $lang) {
                $text = $languages_dots . resumeLanguageLine($lang);
                if ($counterLang == 1) {
                    $pdf->Cell($pageWidth / 2, 15, $text, 0, 0, 'L');
                    $counterLang++;
                } else {
                    $pdf->Cell($pageWidth / 2, 15, $text, 0, 1, 'L');
                    $counterLang = 1;
                }
            }
            if ($counterLang == 2) {
                $pdf->Ln(15);
            }
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
