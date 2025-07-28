<footer class="text-white py-5 mt-5 w-100" style="background-color: #212529; font-family: 'Inter', sans-serif;">
  <div class="container">
    <div class="row gy-4 d-flex justify-content-between">

      <!-- Navigation -->
      <div class="col-6 col-md-3 d-flex flex-column align-items-center">
        <h6 class="fw-semibold text-uppercase mb-3  text-center">Navigation</h6>
        <ul class="list-unstyled">
          <li><a href="https://qrsume.com/page.php?web=tutorial" class="link-light text-decoration-none">How it Works</a></li>
          <li><a href="https://qrsume.com/#templates" class="link-light text-decoration-none">Templates</a></li>
          <li><a href="https://qrsume.com/page.php?web=tips" class="link-light text-decoration-none">Resume Tips</a></li>
        </ul>
      </div>

      <!-- About QRsume -->
      <div class="col-6 col-md-3 d-flex flex-column align-items-center">
        <h6 class="fw-semibold text-uppercase mb-3 text-center">QRsume</h6>
        <ul class="list-unstyled">
          <li><a href="https://qrsume.com/page.php?web=aboutus" class="link-light text-decoration-none">About Us</a></li>
          <li><a href="https://qrsume.com/page.php?web=contactus" class="link-light text-decoration-none">Contact</a></li>
          <li><a href="https://qrsume.com/page.php?web=faq" class="link-light text-decoration-none">FAQ</a></li>
        </ul>
      </div>

      <!-- Legal -->
      <div class="col-6 col-md-3 d-flex flex-column align-items-center">
        <h6 class="fw-semibold text-uppercase mb-3 text-center">Legal</h6>
        <ul class="list-unstyled">
          <li><a href="https://qrsume.com/page.php?web=privacypolicy" class="link-light text-decoration-none">Privacy Policy</a></li>
          <li><a href="https://qrsume.com/page.php?web=termsandconditions" class="link-light text-decoration-none">Terms & Conditions</a></li>
        
        </ul>
      </div>

      <!-- Social Media -->
      <div class="col-6 col-md-3 d-flex flex-column align-items-center">
        <h6 class="fw-semibold text-uppercase mb-3 text-center">Follow Us</h6>
        <div class="d-flex gap-3 justify-content-around">
          <a href="https://twitter.com/qrsume" target="_blank" class="text-white-50 fs-4"><i class="bi bi-twitter"></i></a>
          <a href="https://instagram.com/qrsume" target="_blank" class="text-white-50 fs-4"><i class="bi bi-instagram"></i></a>
          <a href="https://linkedin.com/company/qrsume" target="_blank" class="text-white-50 fs-4"><i class="bi bi-linkedin"></i></a>
        </div>
      </div>

    </div>

    </div>

    <hr class="border-light my-4" />

    <div class="text-center text-white-50 small">
      © 2025 QRsume. All rights reserved.
    </div>
  </div>

    
</footer>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" 
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" 
    crossorigin="anonymous"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Handle cookie consent (only if the button exists)
    let cookieBtn = document.getElementById('acceptCookies');
    if (cookieBtn) {
        cookieBtn.addEventListener('click', function () {
            document.querySelector('.cookie-consent').style.display = 'none';
        });
    }

    // Handle feedback modal (only if the button exists)
    let feedbackBtn = document.getElementById("feedbackBtn");
    let feedbackModalElement = document.getElementById("feedbackModal");
    if (feedbackBtn && feedbackModalElement) {
        feedbackBtn.addEventListener("click", function () {
            let feedbackModal = bootstrap.Modal.getOrCreateInstance(feedbackModalElement);
            feedbackModal.show();
        });
    }

    // Handle star rating (only if stars exist)
    let stars = document.querySelectorAll(".star");
    let ratingInput = document.getElementById("rating");
    if (stars.length > 0 && ratingInput) {
        stars.forEach(star => {
            star.addEventListener("click", function () {
                let rating = this.getAttribute("data-value");
                ratingInput.value = rating;

                // Fill stars up to the selected rating
                stars.forEach(s => {
                    s.classList.remove("text-warning");
                    if (s.getAttribute("data-value") <= rating) {
                        s.classList.add("text-warning");
                    }
                });
            });
        });
    }

    // Handle feedback submission (only if submit button exists)
    let submitFeedbackBtn = document.getElementById("submitFeedback");
    if (submitFeedbackBtn) {
        submitFeedbackBtn.addEventListener("click", function () {
            let rating = ratingInput.value;
            let comment = document.getElementById("comment").value;

            fetch("https://qrsume.com/assets/submit_feedback.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `rating=${rating}&comment=${encodeURIComponent(comment)}`
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                let feedbackModal = bootstrap.Modal.getInstance(feedbackModalElement);
                if (feedbackModal) {
                    feedbackModal.hide();
                }
            });
        });
    }
});
</script>

<!-- Track Interactions Script -->
<?php $db = null; ?>
