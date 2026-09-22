<?php

if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
// Database connection
include("assets/db.php");
include("assets/stats.php");
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
$user_id = $user['id'];
$sessionUsername = $_SESSION['username'] ?? null;
if ($user_id !== false && $user_id !== null) {
    if ($sessionUsername === null || $username !== $sessionUsername) {
        recordDownload($db, $user_id); // Log the download
    }
}


try {
    $pdo = $db;
    // Fetch user Info
    $stmt = $pdo->prepare("SELECT username,email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    // Fetch Personal Info
    $stmt = $pdo->prepare("SELECT * FROM personalinfo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $personal = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Contact Info
    $stmt = $pdo->prepare("SELECT * FROM contactinfo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Education
    $stmt = $pdo->prepare("SELECT * FROM education WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $education = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Experience
    $stmt = $pdo->prepare("SELECT * FROM experience WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $experience = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Languages
    $stmt = $pdo->prepare("SELECT * FROM languages WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Skills (Aptitudes)
    $stmt = $pdo->prepare("SELECT * FROM aptitudes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Interests
    $stmt = $pdo->prepare("SELECT * FROM interests WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $interests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch Custom_sections
    $custom_sections = db_select($pdo, 'custom_sections', '*', ['user_id' => $user_id]);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Include TCPDF
require 'vendor/autoload.php';



$username = $user['username'];
// Create PDF
$pdf = new TCPDF('P', 'pt', 'Letter', true, 'UTF-8', false);

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
$fontName         = 'times';
$titleFontSize    = 24;
$sectionFontSize  = 14;
$titleFontSize    = 11;
$contentFontSize  = 10;

//VARIABLES
$header_position = "C";
$display_QR = true;
$display_bottom_link = true;
$display_education = true;
$display_experience = true;
$display_skills = true;
$display_languages = true;
$display_projects = true;

$languages_dots = "• ";


// Add page
$pdf->AddPage();

// ========== HEADER ==========
$pdf->SetFont($fontName, 'B', 25);
$pdf->Cell(0, 15, $personal['personal_name'] . ' ' . $personal['personal_lastname'], 0, 1, $header_position);

$pdf->SetFont($fontName, '', 10);
$text = "qrsume.com/".$username;

//$pdf->Cell(0, 15, "{$contact['email']} | {$contact['phone_number']} | {$text}", 0, 1, $header_position);

$pdf->Cell(0, 15, "{$text}", 0, 1, $header_position);

$link = "https://www." . ltrim($text, "https://");
$pdf->SetTextColor(0, 0, 255);
// Set font to underline
$pdf->SetFont('', 'U');
//$pdf->Write(0, $text, $link, false, '', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetLineStyle(array('width' => 1, 'color' => array(0, 0, 0)));
$pdf->Ln(10);

// ========== SUMMARY ==========
//$pdf->SetFont($fontName, 'B', $sectionFontSize);
//$pdf->Cell(0, 20, 'SUMMARY', 0, 1, 'L');
//$pdf->SetFont($fontName, '', $contentFontSize);
//$pdf->MultiCell(0, 15, $personal['personal_bio'], 0, 'L');
//$pdf->Ln(10);

// ========== EDUCATION ==========
if (!empty($education) && $display_education) {
    $pdf->SetFont($fontName, 'B', $sectionFontSize);
    $pdf->Cell(0, 20, 'EDUCATION', 0, 1, 'L');
    $pdf->Line($marginL, $pdf->GetY(), $pageWidth - $marginR, $pdf->GetY());
    $pdf->Ln(3);
    foreach ($education as $edu) {
        $pdf->SetFont($fontName, 'B', $titleFontSize);
        $pdf->MultiCell($pageWidth * 3 / 4, 15, "{$edu['name_of_studies']}", 0, 'L', 0, 0, '', '', true);
        $pdf->Cell(0, 15, "{$edu['date']}", 0, 1, 'R');

        $pdf->SetFont($fontName, '', $contentFontSize);
        //$pdf->MultiCell(0, 15, "{$edu['place_of_study']}\n{$edu['brief_description']}", 0, 'L');
        // Get current position
        $y = $pdf->GetY();
        // Estimate how many lines the text will occupy
        $lines = $pdf->getNumLines("{$edu['name_of_studies']}", ($pageWidth * 3 / 4) - 30);
        // If it wraps (more than 1 line), move Y up by 1 line
        if ($lines > 1) {
            $y += 15;
            $pdf->setXY($pdf->GetX(), $y);
        }
        $pdf->MultiCell(0, 15, "{$edu['place_of_study']}", 0, 'L');
        $pdf->Ln(3);
    }
}


// ========== WORK EXPERIENCE ==========
if (!empty($experience) && $display_experience) {
    $pdf->SetFont($fontName, 'B', $sectionFontSize);
    $pdf->Cell(0, 20, 'WORK EXPERIENCE', 0, 1, 'L');
    $pdf->Line($marginL, $pdf->GetY(), $pageWidth - $marginR, $pdf->GetY());
    $pdf->Ln(3);
    foreach ($experience as $exp) {
        $pdf->SetFont($fontName, 'B', $titleFontSize);
        $pdf->Cell(0, 15, "{$exp['job_name']}, {$exp['place_of_work']}", 0, 0, 'L');
        $pdf->Cell(0, 15, "{$exp['date']}", 0, 1, 'R');

        $pdf->SetFont($fontName, '', $contentFontSize);
        $pdf->SetX($pdf->GetX() + 10);
        $pdf->MultiCell(0, 15, "{$exp['brief_description']}", 0, 'L');
        $pdf->SetX($pdf->GetX() - 10);
        $pdf->Ln(5);
    }
}

// ========== SKILLS ==========
if (!empty($skills) && $display_skills) {
    $pdf->SetFont($fontName, 'B', $sectionFontSize);
    $pdf->Cell(0, 20, 'SKILLS', 0, 1, 'L');
    $pdf->Line($marginL, $pdf->GetY(), $pageWidth - $marginR, $pdf->GetY());
    $pdf->SetFont($fontName, '', $contentFontSize);
    $counter = 1;
    $pdf->Ln(3);
    foreach ($skills as $skill) {
        if ($counter == 1) {
            $pdf->Cell($pageWidth / 2, 15, "• " . $skill['aptitude'], 0, 0, 'L');
            $counter++;
        } else {
            $pdf->Cell($pageWidth / 2, 15, "• " . $skill['aptitude'], 0, 1, 'L');
            $counter = 1;
        }
    }
}

// ========== LANGUAGES ==========
if (!empty($languages) && $display_languages) {
    $pdf->SetFont($fontName, 'B', $sectionFontSize);
    $pdf->Cell(0, 20, 'LANGUAGES', 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->Line($marginL, $pdf->GetY(), $pageWidth - $marginR, $pdf->GetY());
    $pdf->SetFont($fontName, '', $contentFontSize);
    $pdf->Ln(3);
    foreach ($languages as $lang) {
        $pdf->Cell(0, 15, $languages_dots."{$lang['language']}"."  "." ({$lang['level']})", 0, 1, 'L');
        $pdf->Ln(5);
    }
}
// ========== INTERESTS ==========
if (!empty($interests) && $display_projects) {
    $pdf->SetFont($fontName, 'B', $sectionFontSize);
    $pdf->Cell(0, 20, 'PROJECTS', 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->Line($marginL, $pdf->GetY(), $pageWidth - $marginR, $pdf->GetY());
    $pdf->Ln(3);
    foreach ($interests as $int) {
        $pdf->SetFont($fontName, 'B', $titleFontSize);

        // Print the bullet point as a bold line using MultiCell
        $pdf->MultiCell(0, 15, "• " . "{$int['interest']}", 0, 'L');

        // Reset font and print the description
        $pdf->SetFont($fontName, '', $contentFontSize);
        $pdf->SetX($pdf->GetX() + 10);
        $pdf->MultiCell(0, 10, "{$int['description']}", 0, 'L');
        $pdf->SetX($pdf->GetX() - 10);
        $pdf->Ln(5); // Line break for spacing
    }
}
// ========== CUSTOM SECTIONS ==========
if (!empty($custom_sections)) {
    foreach ($custom_sections as $section) {
        // Section title (bold, underline)
        $pdf->SetFont($fontName, 'B', $sectionFontSize);
        $pdf->Cell(0, 20, strtoupper($section['section_title']), 0, 1, 'L');
        $pdf->Ln(2);
        $pdf->Line($marginL, $pdf->GetY(), $pageWidth - $marginR, $pdf->GetY());
        $pdf->Ln(3);

        // Section content (multi-line)
        $pdf->SetFont($fontName, '', $contentFontSize);
        $pdf->MultiCell(0, 10, $section['section_content'], 0, 'L');
        $pdf->Ln(8); // Extra spacing between custom sections
    }
}

// ========== QR CODE ==========
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

// Define the QR Code text
// Define the QR Code destination URL with qr_scan parameter
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); // safe encoding
$text = "qrsume.com/" . $username;
$qrLink = "https://www.qrsume.com/" . $username . "?qr_scan=true";


// Page dimensions

$qrSize = 80;  // QR Code size

// Position QR code in the **ottom-right corner**
$x = $pageWidth - $qrSize - $marginR / 2; // 50px padding from right
$y = $pageHeight - $qrSize - 10; // 80px padding from bottom (extra space for text)

// Print QR Code
if ($display_QR) {
    $pdf->SetMargins(0, 0, 0);
    $pdf->write2DBarcode($qrLink, 'QRCODE,H', $x, $y, $qrSize, $qrSize, $style, '');
    $pdf->SetMargins($marginL, $marginT, $marginR);
}
// Position text directly below the QR code
$pdf->SetFont('helvetica', '', 8);
$qrtext = 'Full Profile & Projects';
$qrtextWidth = $pdf->GetStringWidth($qrtext);
$textX = $x + $qrSize / 2 - $qrtextWidth / 2 - 5; // Align with the left side of the QR code
$textY = $y + $qrSize - 5 ; // 5px below the QR code

// Move cursor to the calculated position

$pdf->SetXY($textX, $textY);

// Write the clickable link below the QR code
if ($display_QR) {
    $pdf->Cell(0, 1, $qrtext, 0, 1, 'L');
}

// ========== BOTTOM LINK CODE ==========
// Set font for the link
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(0, 0, 255); // Blue color
$pdf->SetFont('', 'U'); // Underline font
// Position the text
$pdf->SetXY($x, $textY - 10);
// Create the clickable link
if ($display_bottom_link) {
    $pdf->Write(0, $text, $qrLink, false, 'C', true);
}

// Your tracking pixel URL
$trackingUrl = 'https://qrsume.com/email/track_open.php?email_id=abc123';

// HTML content with tracking pixel
$html = <<<EOD
<h1>PDF with Tracking Pixel</h1>
<p>This PDF has a hidden image for tracking purposes.</p>
<img src="$trackingUrl" width="1" height="1" style="display:none;" alt="" />
EOD;

// Allow unsafe image loading (disable security checks)
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->writeHTML($html, true, false, true, false, '');

// ========== OUTPUT PDF ==============
$pdf->Output("resume_".$personalinfo["personal_name"].'.pdf', 'I');
