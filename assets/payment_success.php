<?php
declare(strict_types=1);

$headTitle = "Payment Successful";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


include "head.php";

$username = $_SESSION['username'] ?? null;
$previewUrl = '/preview.php';

if ($username) {
    $publicProfileUrl = 'https://qrsume.com/' . urlencode((string) $username);
} else {
    $publicProfileUrl = 'https://qrsume.com';
}

?>

<style>
  body {
    min-height: 100vh;
    background: #f5f6f8;
  }

  .payment-success-page {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
  }

  .payment-success-card {
    width: 100%;
    max-width: 620px;
    border: 0;
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 14px 40px rgba(15, 23, 42, .10);
    overflow: hidden;
  }

  .payment-success-top {
    padding: 2.5rem 2rem 1.5rem;
    text-align: center;
    background: linear-gradient(135deg, #eef2ff 0%, #ffffff 100%);
  }

  .payment-success-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 1.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #dcfce7;
    color: #16a34a;
    font-size: 2.4rem;
  }

  .payment-success-top h1 {
    margin: 0 0 .65rem;
    color: #111827;
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -.03em;
  }

  .payment-success-top p {
    max-width: 480px;
    margin: 0 auto;
    color: #64748b;
    font-size: 1rem;
    line-height: 1.55;
  }

  .payment-success-body {
    padding: 1.5rem 2rem 2rem;
  }

  .success-feature-box {
    padding: 1rem;
    border: 1px solid #bbf7d0;
    border-radius: 16px;
    background: #f0fdf4;
    color: #166534;
    font-size: .95rem;
    line-height: 1.45;
  }

  .payment-actions {
    display: grid;
    gap: .75rem;
    margin-top: 1.25rem;
  }

  .payment-actions .btn {
    border-radius: 14px;
    font-weight: 700;
    padding: .8rem 1rem;
  }

  .small-help {
    margin-top: 1rem;
    color: #64748b;
    font-size: .85rem;
    line-height: 1.4;
    text-align: center;
  }
</style>

</head>
<body>

<?php
include("nav_profile.php");
?>

<main class="payment-success-page">
  <section class="payment-success-card">
    <div class="payment-success-top">
      <div class="payment-success-icon">
        <i class="bi bi-check2-circle"></i>
      </div>

      <h1>Payment successful</h1>

      <p>
        Your branding removal feature is now unlocked. You can generate clean resume PDFs without
        the QR code and QRsume profile links.
      </p>
    </div>

    <div class="payment-success-body">
      <div class="success-feature-box">
        <strong>Premium feature unlocked:</strong><br>
        Remove QRsume branding from your generated resume PDFs whenever you want.
      </div>

      <div class="payment-actions">
        <a href="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">
          <i class="bi bi-file-earmark-pdf-fill me-1"></i>
          Return to resume preview
        </a>
      </div>

      <p class="small-help">
        In the preview page, uncheck <strong>QR code &amp; links</strong> before generating the PDF.
      </p>
    </div>
  </section>
</main>

<?php
if (file_exists(__DIR__ . '/footer.php')) {
    include __DIR__ . '/footer.php';
}
?>

</body>
</html>