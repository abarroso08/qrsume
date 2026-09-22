<?php require "db.php";
$primary = "#2c3e50";
$secondary = "#737373";

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <title><?= htmlspecialchars(($headTitle ?? $personalinfo['personal_name'] ?? '')).' ' ?> QRsume</title>
    <meta name="description" content="Generate professional, customizable QR resumes quickly and effortlessly with QRsume. Create yours today!">
    <meta name="keywords" content="QR Resume, professional resume, customizable resumes, online resume generator, QRsume, resume, job, internship, trabajo, curriculum, CV, prácticas">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= isset($personalinfo["personal_name"]) && $personalinfo["personal_name"] !== '' ? htmlspecialchars($personalinfo["personal_name"]) . "'s QR Resume" : "QRsume" ?>">
    <meta property="og:description" content="Explore <?= htmlspecialchars($personalinfo["personal_name"] ?? "a professional") ?>'s QR resume powered by QRsume—interactive, modern, and easy to share.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:image" content="http://qrsume.com/images/og-image.png">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= isset($personalinfo["personal_name"]) && $personalinfo["personal_name"] !== '' ? htmlspecialchars($personalinfo["personal_name"]) . "'s QR Resume" : "QRsume" ?>">
    <meta name="twitter:description" content="Discover <?= htmlspecialchars($personalinfo["personal_name"] ?? "a professional") ?>'s interactive QR resume on QRsume.">
    <meta name="twitter:image" content="http://qrsume.com/images/qrsume_logo_barroso.webp">

    <link rel="icon" href="https://qrsume.com/images/favicon (4).ico" type="image/x-icon">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    
    <!-- jQuery (via Google CDN) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    

    <base href="http://qrsume.com/">

    <style>
    body{
        font-family: "inter";
    }
        :root {
            --primary: <?= htmlspecialchars($primary) ?>;
            --secondary: <?= htmlspecialchars($primary) ?>;
        }
    </style>
</head>