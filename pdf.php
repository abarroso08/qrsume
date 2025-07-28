<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require 'vendor/autoload.php';
include("cv1.php");
$name=$username;
// create new PDF document
$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->setAutoPageBreak(false);

 $tagvs = array('h1' => array(0 => array('h' => '', 'n' => 0), 1 => array('h' => 0, 'n' => 0)));
 $pdf->setHtmlVSpace($tagvs);
 $pdf->SetMargins(0,0, 0,0 );
// ---------------------------------------------------------

// add a page
$pdf->AddPage();



$pageWidth = $pdf->getPageWidth();
$pageHeight = $pdf->getPageHeight();
$x = $pageWidth -20;
$y = $pageHeight -40;

// output the HTML content
$pdf->writeHTMLCell(
    $x, // $wx (float)
    $y, // $wy (float)
    10, // $x (float)
    0, // $y (float)
    $html, // $html (string)
    0, // $border (mixed)
    0, // $ln (int)
    false, // $fill (boolean)
    true, // $reseth (boolean)
    '', // $align (string)
    true // $autopadding (boolean)
);

$style = array(
    'border' => 0,
    'vpadding' => 'auto',
    'hpadding' => 'auto',
    'fgcolor' => array(0,0,0),
    'bgcolor' => false, //array(255,255,255)
    'module_width' => 2, // width of a single module in points
    'module_height' => 2 // height of a single module in points
);
$x = $pageWidth -35;
$y = $pageHeight -35;
// QRCODE,L : QR-CODE high error correction

$text = 'qrsume.com/'.$username;
$pdf->write2DBarcode($text, 'QRCODE,H', $x, $y, 35, 35, $style, '');

$pdf->SetFont('helvetica', '', 7);

// Get the width of the text
$text = "qrsume.com/".$username;
$textWidth = $pdf->GetStringWidth($text);

// Calculate the new x coordinate to center the text
$centeredX = $x - ($textWidth / 2)-70;
$centeredY = $pageHeight-5;
// Ensure www. is added to the link
$link = "https://www." . ltrim($text, "https://");

// Set text color to blue
$pdf->SetTextColor(0, 0, 255);

// Set font to underline
$pdf->SetFont('', 'U');

// Position the text
$pdf->SetXY($centeredX, $centeredY);

// Create the clickable link that opens in a new window
$pdf->Write(0, $text, $link, false, '', true);

// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output("CV_".$personalinfo["personal_name"].'.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+