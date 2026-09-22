<style>
    #navbar {
    transition: top 0.3s ease-in-out;
}
</style>
<nav id="navbar" class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
          <div class="container-fluid">
              <?php
              if ((isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)) { ?>
               <a class="navbar-brand" href="<?= empty($username_url) ? $_SESSION['username'] : $username_url;?>">
                    <span style="font-weight: bold;"><?php echo htmlspecialchars($personalinfo["personal_name"] ?? ''); ?></span> 
                    <?php echo htmlspecialchars($personalinfo["personal_lastname"] ?? ''); ?>
               </a>
               <?php } else {
                   ?>
               <a class="navbar-brand" href="<?= $username_url?>">
                    <span style="font-weight: bold;"><?php echo htmlspecialchars($personalinfo["personal_name"]); ?></span> <?php echo htmlspecialchars($personalinfo["personal_lastname"]); ?>
               </a>
               <?php } ?>
               <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
               </button>
               <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item d-block d-sm-none">
                              <a class="nav-link" href="index.php/#experience">Experience</a>
                         </li>
                         <li class="nav-item d-block d-sm-none">
                              <a class="nav-link" href="index.php/#education">Education</a>
                         </li>
                         <li class="nav-item d-block d-sm-none">
                              <a class="nav-link" href="index.php/#interest">Interests</a>
                         </li>
                         <li class="nav-item">
                              <a class="nav-link" href="blog.php/?user_id=<?= $user_id?>">Projects</a>
                         </li>
                         <li class="nav-item">
                              <a class="nav-link" href="<?= $username_url;?>#cv">Resume</a>
                         </li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
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


