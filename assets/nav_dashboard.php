<style>
    #navbar {
    transition: top 0.3s ease-in-out;
}
</style>
<nav id="navbar" class="navbar navbar-expand-md navbar-dark bg-dark fixed-top shadow-sm py-2">
  <div class="container-fluid">
    
    <!-- Brand: Username -->
    <a class="navbar-brand d-flex align-items-center" href="<?= $_SESSION['username'] ?? '#' ?>">
      <span class="fw-bold px-2"><?= htmlspecialchars($personalinfo["personal_name"] ?? '') ?></span> 
      <?= htmlspecialchars($personalinfo["personal_lastname"] ?? '') ?>
    </a>

    <!-- Mobile Toggle -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Right Buttons -->
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center">

        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
          <!-- Main Menu Button -->
          <li class="nav-item mx-2">
            <a class="btn btn-outline-light d-flex align-items-center px-3" href="../assets/dashboard.php">
              <i class="bi bi-house-door-fill me-2"></i> Main Menu
            </a>
          </li>

          <!-- Settings Button -->
          <li class="nav-item mx-2">
            <a class="btn btn-outline-light d-flex align-items-center px-3" href="https://qrsume.com/assets/user_settings.php">
              <i class="bi bi-gear-fill me-2"></i> Settings
            </a>
          </li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>


<div id="nav-spacer" style="height:60px;"></div> <!-- This spacer will take the navbar's height -->
<script>
    // Store the previous scroll position
    let lastScrollTop = 0;
    const navbar = document.getElementById("navbar");

    // Adjust the navbar when the user scrolls
    window.addEventListener("scroll", function() {
        let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        if (currentScroll > lastScrollTop) {
            // Scrolling down, hide the navbar
            navbar.style.top = "-80px";  // Adjust the height of the navbar here
        } else {
            // Scrolling up, show the navbar
            navbar.style.top = "0";
        }
        lastScrollTop = currentScroll <= 0 ? 0 : currentScroll; // Prevent negative scrolling
    });
    
    // Adjust spacer height when the page loads or when resized
    function adjustNavSpacer() {
        let navHeight = navbar.offsetHeight;
        let navSpacer = document.getElementById("nav-spacer");
        if (navSpacer) {
            navSpacer.style.height = navHeight + "px";
        }
    }

    window.addEventListener("load", adjustNavSpacer);
    window.addEventListener("resize", adjustNavSpacer);
</script>


