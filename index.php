<!--
    Author: Alejandro Barroso Bueso
--!>
<!-- Incluimos el header -->
<?php
include "assets/head.php";

$stmt_views = $db->prepare("UPDATE personalinfo SET visits = visits + 1 WHERE user_id = ?");
$stmt_views ->execute([$user_id]);

if (!isset($_GET['username'])) {
    ?>
    <!-- index.php -->
<?php
// Include the database connection if needed for this page
//include 'includes/db.php';
    ?>
<head>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
  h1,h2{
      font-family: "inter"; !important
      color:#2c3d51; !important
  }
    .bg-blue-button {
    background-color: #1e40af !important;
    }
  </style>
</head>
<body>
<?php
    include("assets/nav_site.php");
    ?>

<section class="container py-3 bg-body" >
  <div class="row align-items-center">
    
    <!-- Left: Resume Preview Image -->
    <div class="col-md-6 text-center mb-4 mb-md-0">
      <img 
        src="/images/resume_landing_barroso.webp" 
        alt="Resume preview" 
        class="img-fluid mt-3"
        style="max-height:80vh"
      >
    </div>

    <!-- Right: Hero Text Content -->
    <div class="col-md-6 text-center text-md-start">
      <h5 class="text-uppercase text-secondary mb-2 fw-bold" style="letter-spacing: 2px;">Dont. Waste. Your. Time.</h5>
      <h1 class="fw-bold mb-3 display-3">Create a resume in 5 minutes</h1>
      <p class="mb-1 fw-semibold">Just type your info, we’ll do the rest.</p>
      <p class="mb-4 fw-semibold">No design. No code. Instant result.</p>
      <div>
        <a href="https://qrsume.com/assets/register.php" class="btn btn-warning py-2 px-3 fw-bold me-2">Build my resume</a>
        <a href="#templates" class="btn btn-primary py-2 px-3 fw-bold bg-blue-button">Explore Templates</a>
      </div>
    </div>

  </div>
</section>


<section class="py-5 my-5 text-center">
  <div class="container">
    <!-- Title -->
    <h2 class="fw-bold mb-3" style="font-size: 2.5rem;">
      Only <span class="fw-bold display-3">3</span> Steps
    </h2>

    <!-- Subtitle -->
    <p class=" mb-5 fs-5 text-muted">
      Check out how simple it is to generate a resume and personal webpage
      <span class="fw-bold text-decoration-underline">at the same time.</span>
    </p>

    <!-- Steps Grid -->
    <div class="row justify-content-center g-4">
      <!-- Step 1 -->
      <div class="col-10 col-sm-6 col-md-4 col-lg-3">
        <div class="px-4 py-5 rounded h-100 shadow-sm" style="background-color:#e0e7ff">
          <div class="fw-bold mb-2" style="font-size: 2.5rem; color: #1e40af;">1</div>
          <div class="fw-bold fs-5">Create Account</div>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="col-10 col-sm-6 col-md-4 col-lg-3">
        <div class="px-4 py-5  rounded h-100 shadow-sm" style="background-color:#e0e7ff">
          <div class="fw-bold mb-2" style="font-size: 2.5rem; color: #1e40af;">2</div>
          <div class="fw-bold fs-5">Add Your Info</div>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="col-10 col-sm-6 col-md-4 col-lg-3">
        <div class="px-4 py-5  rounded h-100 shadow-sm" style="background-color:#e0e7ff">
          <div class="fw-bold mb-2" style="font-size: 2.5rem; color: #1e40af;">3</div>
          <div class="fw-bold fs-5">Get Resume & Website</div>
        </div>
      </div>
    </div>
  </div>
</section>



<section class="py-5 my-5 text-center" id="templates">
  <div class="container">
    <!-- Header -->
    <h2 class="fw-bold mb-3" >
      Undecided? We Already Thought That
    </h2>
    <p class="text-muted fs-5 mb-5">
      Use one of our templates: optimized to impress and easy to customize.
    </p>

    <!-- Template Previews -->
    <div class="row justify-content-center g-4">
      <!-- Template 1 -->
      <div class="col-10 col-md-6 col-lg-5">
        <img 
          src="images/template1.webp" 
          alt="Template Example 1" 
          class="img-thumbnail rounded shadow"
          style="max-height:400px;"
        >
      </div>

      <!-- Template 2 -->
      <div class="col-10 col-md-6 col-lg-5 ">
        <img 
          src="images/template2.webp" 
          alt="Template Example 2" 
          class="img-thumbnail rounded shadow"
          style="max-height:400px;"
        >
      </div>
    </div>
  </div>
</section>


<section class="py-5 my-5 text-center">
  <div class="container">
    <!-- Section Title -->
    <h2 class="fw-bold mb-5">Why QRsume?</h2>

    <!-- Icon Boxes -->
    <div class="row justify-content-center g-4">
      
      <!-- Box 1 -->
      <div class="col-10 col-sm-6 col-md-4 col-lg-3">
        <div class="p-4 rounded-4 shadow-sm h-100">
          <img src="https://img.icons8.com/ios-filled/50/1e40af/source-code.png" alt="Code Icon" style="height: 40px;">
          <h5 class="fw-semibold mt-3 mb-1">Resume in 5 min</h5>
          <p class="text-muted mb-0">Fast and effortless resume creation.</p>
        </div>
      </div>

      <!-- Box 2 -->
      <div class="col-10 col-sm-6 col-md-4 col-lg-3">
        <div class="p-4 rounded-4 shadow-sm h-100">
          <img src="https://img.icons8.com/ios-filled/50/1e40af/internet--v1.png" alt="Website Icon" style="height: 40px;">
          <h5 class="fw-semibold mt-3 mb-1">Website & Blog</h5>
          <p class="text-muted mb-0">Stand out with a personal portfolio and upload unlimited projects.</p>
        </div>
      </div>

      <!-- Box 3 -->
      <div class="col-10 col-sm-6 col-md-4 col-lg-3">
        <div class="p-4 rounded-4 shadow-sm h-100">
          <img src="https://img.icons8.com/ios-filled/50/1e40af/us-dollar.png" alt="Dollar Icon" style="height: 40px;">
          <h5 class="fw-semibold mt-3 mb-1">Free for Students</h5>
          <p class="text-muted mb-0">Get started without paying a cent.</p>
        </div>
      </div>

    </div>
  </div>
</section>


<section class="py-5 my-5 text-center">
  <div class="container">
    <!-- Title -->
    <h2 class="fw-bold mb-4">Our Vision</h2>

    <!-- Quote 1 -->
    <p class="fst-italic fw-semibold mx-auto" style="max-width: 750px;">
      “The <span class="fw-bold text-decoration-underline">Resume</span> is the index, the 
      <span class="fw-bold text-decoration-underline">QR</span> is the gateway, and the 
      <span class="fw-bold text-decoration-underline">Web</span> is the full story”
    </p>

    <!-- Centered Flow -->
    <h2 class="fw-bold my-5" style="font-size: 2rem;">
      Resume → QR → Web
    </h2>

    <!-- Quote 2 -->
    <p class="fst-italic mx-auto" style="max-width: 700px;">
      “Bring your CV to life: a short version on paper, a full version online”
    </p>
  </div>
</section>


<section class="py-5 my-5 text-center">
  <div class="container">
    <!-- Section Title -->
    <h2 class="fw-bold mb-5">Doubts Are Not Allowed</h2>

    <!-- FAQ Cards -->
    <div class="row justify-content-center g-4">
      <!-- Card 1 -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="p-4 rounded-4 shadow-sm h-100 text-start">
          <h5 class="fw-semibold">Can I export to PDF or Word?</h5>
          <p class="text-muted mb-0">Yes. Download to PDF instantly, or to Word if you want to personalize it later.</p>
        </div>
      </div>

      <!-- Card 2 -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="p-4 rounded-4 shadow-sm h-100 text-start">
          <h5 class="fw-semibold">Is it free?</h5>
          <p class="text-muted mb-0">Yes. Premium features are coming soon</p>
        </div>
      </div>

      <!-- Card 3 -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="p-4 rounded-4 shadow-sm h-100 text-start">
          <h5 class="fw-semibold">Do I need to code?</h5>
          <p class="text-muted mb-0">No. Just type. We handle the design and tech.</p>
        </div>
      </div>

      <!-- Card 4 -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="p-4 rounded-4 shadow-sm h-100 text-start">
          <h5 class="fw-semibold">Can I edit it later?</h5>
          <p class="text-muted mb-0">Yes. Log in anytime to update your info, CV or website instantly.</p>
        </div>
      </div>
    </div>

    <!-- Buttons -->
    <div class="d-flex justify-content-center gap-3 mt-5 flex-wrap">
      <a href="contact.php" class="btn px-4 py-2 fw-semibold text-white" style="background-color: #1e40af;">
        Contact Us
      </a>
      <a href="https://qrsume.com/assets/register.php" class="btn px-4 py-2 fw-semibold" style="background-color: #facc15; color: #1e293b; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
        Build my resume
      </a>
    </div>
  </div>
</section>



     <!-- Include footer -->
     <?php include 'assets/footer.php'; ?>
     <!-- Cookie Consent Popup -->
<div id="cookie-popup" class="position-fixed bottom-0 start-50 translate-middle-x bg-dark text-white p-3 rounded shadow-lg"
     style="display: none; width: 90%; max-width: 400px; z-index: 1000;">
    <p class="mb-2">
        🍪 We use cookies to enhance your experience. You can accept or decline tracking.
    </p>
    <div class="d-flex justify-content-between">
        <button id="accept-cookies" class="btn btn-primary btn-sm">Accept</button>
        <button id="decline-cookies" class="btn btn-secondary btn-sm">Decline</button>
        <a href="/privacy-policy.php" class="text-white small">Learn More</a>
    </div>
</div>


<!-- JavaScript to Show/Hide Cookie Popup -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Check if cookies were accepted or declined
    if (!document.cookie.includes("new_visitor") && !document.cookie.includes("cookie_declined")) {
        document.getElementById("cookie-popup").style.display = "block";
    }

    // Accept cookies
    document.getElementById("accept-cookies").addEventListener("click", function () {
        document.cookie = "new_visitor=1; path=/; max-age=" + (365 * 24 * 60 * 60); // Store for 1 year
        document.getElementById("cookie-popup").style.display = "none";
        location.reload(); // Reload to apply tracking
    });

    // Decline cookies
    document.getElementById("decline-cookies").addEventListener("click", function () {
        document.cookie = "cookie_declined=1; path=/; max-age=" + (365 * 24 * 60 * 60); // Store for 1 year
        document.getElementById("cookie-popup").style.display = "none";
    });
});
</script>

    <?php
} else {
    include('assets/stats.php');

    $username = $_GET['username'] ?? '';
    $sessionUsername = $_SESSION['username'] ?? '';

    // Only proceed if we're visiting someone else's profile
    if ($username !== '' && $username !== $sessionUsername) {
        // Fetch the profile owner's user ID
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :uname");
        $stmt->execute([':uname' => $username]);
        $user_id = $stmt->fetchColumn();

        if ($user_id !== false && $user_id !== null) {
            // Always record a page view
            recordPageView($db, $user_id);

            // Conditionally record a QR scan
            if (isset($_GET['qr_scan']) && $_GET['qr_scan'] === 'true') {
                recordQRScan($db, $user_id);
            }
        }
    }


    ?>

<?php include "assets/profile_page.php"; ?>

     <?php include "assets/footer.php";
} ?>
     


     