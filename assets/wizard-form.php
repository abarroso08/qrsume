<!-- Wizard-style Resume Assistant -->
<?php include('assets/head.php');?>
<div class="container my-5" id="resume-wizard">
  <h2 class="text-center mb-4">Let's Build Your Resume Together</h2>

  <form id="wizard-form" action="preview.php" method="POST">
    <!-- Step 1: Personal Info -->
    <div class="wizard-step" data-step="1">
      <h4>Step 1: Tell us about yourself</h4>
      <div class="mb-3">
        <label class="form-label">Your full name</label>
        <input type="text" name="personal_name" class="form-control" required placeholder="e.g., Alejandro Barroso">
      </div>
      <div class="mb-3">
        <label class="form-label">Your profession</label>
        <input type="text" name="personal_profession" class="form-control" placeholder="e.g., Data Science Student">
      </div>
      <div class="mb-3">
        <label class="form-label">Short bio (optional)</label>
        <textarea name="personal_bio" class="form-control" placeholder="Tell us what drives you or what you're passionate about..." rows="3"></textarea>
      </div>
    </div>

    <!-- Step 2: Education -->
    <div class="wizard-step d-none" data-step="2">
      <h4>Step 2: Your studies</h4>
      <div class="mb-3">
        <label class="form-label">Where do you study?</label>
        <input type="text" name="education[0][place_of_study]" class="form-control" placeholder="e.g., Purdue University">
      </div>
      <div class="mb-3">
        <label class="form-label">What are you studying?</label>
        <input type="text" name="education[0][name_of_studies]" class="form-control" placeholder="e.g., Data Science & Telecom">
      </div>
      <div class="mb-3">
        <label class="form-label">Dates</label>
        <input type="text" name="education[0][date]" class="form-control" placeholder="e.g., 2023 - Present">
      </div>
      <div class="mb-3">
        <label class="form-label">Brief description</label>
        <textarea name="education[0][desc]" class="form-control" rows="2" placeholder="e.g., Combining CS and engineering courses in an international program."></textarea>
      </div>
    </div>

    <!-- Step 3: Experience -->
    <div class="wizard-step d-none" data-step="3">
      <h4>Step 3: Experience (if any)</h4>
      <div class="mb-3">
        <label class="form-label">Where did you work?</label>
        <input type="text" name="experience[0][place_of_work]" class="form-control" placeholder="e.g., Freelance / Burger King / Internship">
      </div>
      <div class="mb-3">
        <label class="form-label">Job title</label>
        <input type="text" name="experience[0][job_name]" class="form-control" placeholder="e.g., Web Developer">
      </div>
      <div class="mb-3">
        <label class="form-label">Dates</label>
        <input type="text" name="experience[0][date]" class="form-control" placeholder="e.g., June - August 2022">
      </div>
      <div class="mb-3">
        <label class="form-label">What did you do?</label>
        <textarea name="experience[0][brief_description]" class="form-control" rows="2"></textarea>
      </div>
    </div>

    <!-- Step 4: Skills & Languages -->
    <div class="wizard-step d-none" data-step="4">
      <h4>Step 4: Skills & Languages</h4>
      <div class="mb-3">
        <label class="form-label">Mention one skill</label>
        <input type="text" name="skills[0][aptitude]" class="form-control" placeholder="e.g., Teamwork">
      </div>
      <div class="mb-3">
        <label class="form-label">Language</label>
        <input type="text" name="languages[0][language]" class="form-control" placeholder="e.g., English">
      </div>
      <div class="mb-3">
        <label class="form-label">Level</label>
        <input type="text" name="languages[0][level]" class="form-control" placeholder="e.g., C1">
      </div>
    </div>

    <!-- Step 5: Final -->
    <div class="wizard-step d-none" data-step="5">
      <h4>You're all set!</h4>
      <p>You're ready to preview and customize your resume before generating the PDF.</p>
      <div class="text-center">
        <button class="btn btn-success" type="submit">Continue to Preview</button>
      </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="mt-4 d-flex justify-content-between">
      <button type="button" class="btn btn-secondary" id="backBtn" disabled>Back</button>
      <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
    </div>
  </form>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    let currentStep = 1;
    const totalSteps = document.querySelectorAll(".wizard-step").length;

    function showStep(step) {
      document.querySelectorAll(".wizard-step").forEach(el => el.classList.add("d-none"));
      document.querySelector(`.wizard-step[data-step='${step}']`).classList.remove("d-none");
      document.getElementById("backBtn").disabled = step === 1;
      document.getElementById("nextBtn").classList.toggle("d-none", step === totalSteps);
    }

    document.getElementById("nextBtn").addEventListener("click", () => {
      if (currentStep < totalSteps) {
        currentStep++;
        showStep(currentStep);
      }
    });

    document.getElementById("backBtn").addEventListener("click", () => {
      if (currentStep > 1) {
        currentStep--;
        showStep(currentStep);
      }
    });

    showStep(currentStep);
  });
</script>
