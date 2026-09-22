<?php
if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

include("../assets/head.php");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../assets/login.php");
    exit();
}
$sectionNumber = isset($_GET['section']) ? (int) $_GET['section'] : 0;
$prevSection = max(0, $sectionNumber - 1);
$nextSection = $sectionNumber + 1;
$isLastSection = ($sectionNumber == 7);
$isFirstSection = ($sectionNumber == 0);

$highlightStyle = 'background-color: rgba(241, 85, 85, 0.15); border: 2px solid #F15555; border-radius: 8px;';
// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$quick_access_tables = ['personalinfo', 'contactinfo', 'aptitudes', 'education', 'experience', 'interests', 'languages', 'custom_sections'];

// Fetch User Data
$results = [];
foreach ($quick_access_tables as $table) {
    $query = "SELECT * FROM `$table` WHERE `user_id` = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['id']]);
    $results[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$personalinfo = $results['personalinfo'][0] ?? null;

$sql = "SELECT photo_url FROM photos WHERE user_id = :user_id";
$stmt = $db->prepare($sql);
$stmt->bindValue(':user_id', $_SESSION['id'], PDO::PARAM_INT);
$stmt->execute();
$photos = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<style>
  body {
    background-color: #f4f4f5;
  }
  h2 {
    font-family: "Inter", sans-serif;
  }
  .section-round {
    border-radius: 12px;
  }
  .section-round-top {
    border-radius: 12px 12px 0 0;
  }
  .hover-highlight:hover {
    background-color: #f4f4f5;
  }
  .delete-btn i {
    transition: color 0.3s ease;
  }
  .delete-btn:hover i {
    color: red;
  }
  .error-message {
    color: red;
    font-size: 0.9em;
    margin-top: 5px;
    display: none;
  }
  .success-message {
    color: green;
    font-size: 0.9em;
    margin-top: 5px;
    display: none;
  }


  #sectionWrapper::-webkit-scrollbar {
    width: 8px;
  }
  #sectionWrapper::-webkit-scrollbar-thumb {
    background-color: #ccc;
    border-radius: 4px;
  }
  .resume-container {
      --font-name: 20px; /* Reduced from 28px */
      --font-section: 12px; /* Reduced from 16px */
      --font-title: 10px; /* Reduced from 14px */
      --font-base: 8px; /* Reduced from 12px */
    }
</style>

<body class="d-flex justify-content-center align-items-center flex-column">
<div id="loading-overlay" style="
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background-color: white;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-family: 'Segoe UI', sans-serif;
">
    <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Recollecting all your site data...</span>
    </div>
    <p id="loading-text" class="text-muted fs-5">Recollecting all your site data...</p>
</div>


  <div class="pt-4" style="width:95vw;min-height:95vh;">
    <div class="h-100 row g-4">
      <!-- Sidebar -->
      <div class="section-round col-lg-2 col-sm-6 col-md-4 col-12 d-none d-md-flex flex-column bg-white shadow p-2 h-100">
        <h4 class="text-center mb-3">Menu</h4>
        <ul class="nav flex-column">
          <li class="hover-highlight nav-item">
            <a class="nav-link active fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php" data-section="0">
              <i class="bi bi-person-circle me-2"></i> Profile
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=1" data-section="1">
              <i class="bi bi-envelope me-2"></i> Contact
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=2" data-section="2">
              <i class="bi bi-book me-2"></i> Education
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=3" data-section="3">
              <i class="bi bi-briefcase me-2"></i> Experience
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=4" data-section="4">
              <i class="bi bi-star me-2"></i> Projects
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=5" data-section="5">
              <i class="bi bi-lightbulb me-2"></i> Aptitudes
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=6" data-section="6">
              <i class="bi bi-translate me-2"></i> Languages
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=7" data-section="7">
              <i class="bi bi-ui-checks me-2"></i> Custom Sections
            </a>
          </li>
        </ul>
      </div>

      <!-- Main Content -->
      <div class="col-12 col-lg-6 col-md-8 h-100 position-relative">
        <div class="bg-white shadow section-round-top p-4 w-100" id="sectionWrapper" style="max-height: 80vh; overflow-y: auto;">
          <!-- Section 0: Personal Info -->
          <section class="form-section <?= $sectionNumber === 0 ? '' : 'd-none' ?>" id="section-0">
            <h2>Personal Information</h2>
            <form id="personalinfo-form" method="post" action="https://qrsume.com/create_resume/save_profile.php">
              <input type="hidden" name="action" value="save_personalinfo">
              <input type="hidden" name="id" value="<?= htmlspecialchars($personalinfo['id'] ?? '') ?>">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <div class="row">
                <div class="col-md-3 text-center mb-4">
                  <img id="profile-photo" class="img-fluid rounded mb-2" alt="Profile Photo"
                       src="images/<?= htmlspecialchars($personalinfo['personal_photo'] ?? 'default.webp') ?>">
                  <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#profilePicModal">Edit Photo</button>
                </div>
                <div class="col-md-9">
                  <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="personal_name" class="form-control" required
                           value="<?= htmlspecialchars($personalinfo['personal_name'] ?? '') ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="personal_lastname" class="form-control" required
                           value="<?= htmlspecialchars($personalinfo['personal_lastname'] ?? '') ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Profession</label>
                    <input type="text" name="personal_profession" class="form-control" required
                           value="<?= htmlspecialchars($personalinfo['personal_profession'] ?? '') ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Bio</label>
                    <textarea name="personal_bio" class="form-control" rows="4"><?= htmlspecialchars($personalinfo['personal_bio'] ?? '') ?></textarea>
                  </div>
                  <input type="hidden" name="cv_url" value="<?= htmlspecialchars($personalinfo['cv_url'] ?? '') ?>">
                  <div class="text-end">
                    <div class="error-message" id="personalinfo-error"></div>
                    <div class="success-message" id="personalinfo-success"></div>
                    <button type="submit" class="btn btn-primary">Save</button>
                  </div>
                </div>
              </div>
            </form>
          </section>

          <!-- Contact Info -->
          <div class="form-section <?= $sectionNumber === 1 ? '' : 'd-none' ?>" id="section-1">
            <h2 class="mb-4">Contact Information</h2>
            <form id="contactinfo-form" data-url="https://qrsume.com/assets/upload/upload_contact.php">
              <input type="hidden" name="action" value="save_contactinfo">
              <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="phone_number" class="form-label fw-semibold">Phone Number</label>
                  <input type="text" id="phone_number" name="phone_number" class="form-control"
                         value="<?= htmlspecialchars($results['contactinfo'][0]['phone_number'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="email" class="form-label fw-semibold">Email</label>
                  <input type="email" id="email" name="email" class="form-control"
                         value="<?= htmlspecialchars($results['contactinfo'][0]['email'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="github" class="form-label fw-semibold">GitHub</label>
                  <input type="url" id="github" name="github" class="form-control"
                         placeholder="https://" value="<?= htmlspecialchars($results['contactinfo'][0]['github'] ?? 'https://') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="facebook" class="form-label fw-semibold">Facebook</label>
                  <input type="url" id="facebook" name="facebook" class="form-control"
                         placeholder="https://" value="<?= htmlspecialchars($results['contactinfo'][0]['facebook'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="linkedin" class="form-label fw-semibold">LinkedIn</label>
                  <input type="url" id="linkedin" name="linkedin" class="form-control"
                         placeholder="https://" value="<?= htmlspecialchars($results['contactinfo'][0]['linkedin'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="twitter" class="form-label fw-semibold">Twitter</label>
                  <input type="url" id="twitter" name="twitter" class="form-control"
                         placeholder="https://" value="<?= htmlspecialchars($results['contactinfo'][0]['twitter'] ?? '') ?>">
                </div>
              </div>
              <div class="d-flex justify-content-end mt-3">
                <div class="error-message" id="contactinfo-error"></div>
                <div class="success-message" id="contactinfo-success"></div>
                <button type="submit" class="btn btn-primary w-25">Save</button>
              </div>
            </form>
          </div>

          <!-- Education -->
          <div class="form-section <?= $sectionNumber === 2 ? '' : 'd-none' ?>" id="section-2">
            <h2 class="mb-1">Education Information</h2>
            <p class="text-muted mb-4">Add the name of your school, what degree you obtained, your field of study, and your graduation year.</p>
            <?php if (!empty($results['education'])): ?>
              <?php foreach ($results['education'] as $edu): ?>
                <?php $eduId = $edu['education_id']; ?>
                <div class="overflow-hidden border mb-3" style="border-radius:12px;">
                  <div class="d-flex justify-content-between hover-highlight align-items-center p-3" data-bs-toggle="collapse" data-bs-target="#edu-collapse-<?= $eduId ?>" aria-expanded="false" role="button">
                    <h5 class="mb-0 fw-semibold fs-6"><?= htmlspecialchars($edu['name_of_studies']) ?: 'Untitled Education' ?></h5>
                    <i class="bi bi-chevron-down transition" id="chevron-<?= $eduId ?>"></i>
                  </div>
                  <div class="collapse fs-6" id="edu-collapse-<?= $eduId ?>">
                    <form id="education-form-<?= $eduId ?>" data-url="https://qrsume.com/assets/upload/upload_education.php" class="education-form p-3">
                      <input type="hidden" name="action" value="save_educationinfo">
                      <input type="hidden" name="education_id" value="<?= htmlspecialchars($eduId) ?>">
                      <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label for="name_of_studies_<?= $eduId ?>" class="form-label fw-semibold">Name of Studies</label>
                          <input type="text" id="name_of_studies_<?= $eduId ?>" name="name_of_studies" class="form-control"
                                 value="<?= htmlspecialchars($edu['name_of_studies'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="date_<?= $eduId ?>" class="form-label fw-semibold">Date</label>
                          <input type="text" id="date_<?= $eduId ?>" name="date" class="form-control"
                                 value="<?= htmlspecialchars($edu['date'] ?? '') ?>" placeholder="e.g., 2020–2022">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="place_of_study_<?= $eduId ?>" class="form-label fw-semibold">Place of Study</label>
                          <input type="text" id="place_of_study_<?= $eduId ?>" name="place_of_study" class="form-control"
                                 value="<?= htmlspecialchars($edu['place_of_study'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="brief_description_<?= $eduId ?>" class="form-label fw-semibold">Brief Description</label>
                          <textarea id="brief_description_<?= $eduId ?>" name="brief_description" class="form-control"><?= htmlspecialchars($edu['brief_description'] ?? '') ?></textarea>
                        </div>
                      </div>
                      <div class="error-message" id="education-error-<?= $eduId ?>"></div>
                      <div class="success-message" id="education-success-<?= $eduId ?>"></div>
                    </form>
                    <div class="d-flex justify-content-end gap-2 p-2">
                      <button type="submit" form="education-form-<?= $eduId ?>" class="btn btn-primary section-round">Update</button>
                      <form id="delete-education-form-<?= $eduId ?>" data-url="https://qrsume.com/assets/upload/upload_education.php">
                        <input type="hidden" name="action" value="delete_education">
                        <input type="hidden" name="education_id" value="<?= htmlspecialchars($eduId) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn delete-btn">
                          <i class="bi bi-trash3 fs-5"></i>
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (count($results['education']) < 5): ?>
              <div class="collapse mt-3 border rounded p-3" id="newEducationCollapse">
                <div class="d-flex justify-content-between align-items-center p-3">
                  <h5 class="mb-0 fw-semibold">Untitled Education</h5>
                </div>
                <form id="new-education-form" data-url="https://qrsume.com/assets/upload/upload_education.php" class="education-form">
                  <input type="hidden" name="action" value="save_educationinfo">
                  <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label for="new_date" class="form-label fw-semibold">Date</label>
                      <input type="text" id="new_date" name="date" class="form-control" placeholder="e.g., 2020–2022">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="new_place_of_study" class="form-label fw-semibold">Place of Study</label>
                      <input type="text" id="new_place_of_study" name="place_of_study" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="new_name_of_studies" class="form-label fw-semibold">Name of Studies</label>
                      <input type="text" id="new_name_of_studies" name="name_of_studies" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="new_brief_description" class="form-label fw-semibold">Brief Description</label>
                      <textarea id="new_brief_description" name="brief_description" class="form-control"></textarea>
                    </div>
                  </div>
                  <div class="error-message" id="new-education-error"></div>
                  <div class="success-message" id="new-education-success"></div>
                  <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary w-25">Add</button>
                  </div>
                </form>
              </div>
              <div class="border rounded mt-3 overflow-hidden" style="background-color:#f4f4f5;">
                <div class="d-flex justify-content-between align-items-center p-3"
                     data-bs-toggle="collapse" data-bs-target="#newEducationCollapse"
                     aria-expanded="false" aria-controls="newEducationCollapse" style="cursor: pointer;">
                  <h5 class="mb-0 fw-semibold">+ Add New Education</h5>
                  <i class="bi bi-chevron-down transition"></i>
                </div>
              </div>
            <?php else: ?>
              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 education entries.</p>
            <?php endif; ?>
          </div>

          <!-- Work Experience -->
          <div class="form-section <?= $sectionNumber === 3 ? '' : 'd-none' ?>" id="section-3">
            <h2 class="mb-4">Work Experience</h2>
            <?php if (!empty($results['experience'])): ?>
              <?php foreach ($results['experience'] as $job): ?>
                <?php $jobId = $job['experience_id']; ?>
                <div class="border rounded mb-3">
                  <div class="d-flex justify-content-between align-items-center p-3 bg-light" data-bs-toggle="collapse" data-bs-target="#job-collapse-<?= $jobId ?>" aria-expanded="false" role="button">
                    <h5 class="mb-0 fw-semibold">💼 <?= htmlspecialchars($job['job_name']) ?: 'Untitled Experience' ?></h5>
                    <i class="bi bi-chevron-down transition" id="chevron-<?= $jobId ?>"></i>
                  </div>
                  <div class="collapse" id="job-collapse-<?= $jobId ?>">
                    <form id="experience-form-<?= $jobId ?>" data-url="https://qrsume.com/assets/upload/upload_experience.php" class="experience-form p-3">
                      <input type="hidden" name="action" value="save_experience">
                      <input type="hidden" name="experience_id" value="<?= htmlspecialchars($jobId) ?>">
                      <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label for="job_name_<?= $jobId ?>" class="form-label fw-semibold">Job Name</label>
                          <input type="text" id="job_name_<?= $jobId ?>" name="job_name" class="form-control" value="<?= htmlspecialchars($job['job_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="date_<?= $jobId ?>" class="form-label fw-semibold">Date</label>
                          <input type="text" id="date_<?= $jobId ?>" name="date" class="form-control" value="<?= htmlspecialchars($job['date'] ?? '') ?>" placeholder="e.g., 2022–Present">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="place_of_work_<?= $jobId ?>" class="form-label fw-semibold">Place of Work</label>
                          <input type="text" id="place_of_work_<?= $jobId ?>" name="place_of_work" class="form-control" value="<?= htmlspecialchars($job['place_of_work'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="brief_description_<?= $jobId ?>" class="form-label fw-semibold">Brief Description</label>
                          <textarea id="brief_description_<?= $jobId ?>" name="brief_description" class="form-control"><?= htmlspecialchars($job['brief_description'] ?? '') ?></textarea>
                        </div>
                      </div>
                      <div class="error-message" id="experience-error-<?= $jobId ?>"></div>
                      <div class="success-message" id="experience-success-<?= $jobId ?>"></div>
                    </form>
                    <div class="d-flex justify-content-end gap-2 p-2">
                      <button type="submit" form="experience-form-<?= $jobId ?>" class="btn btn-primary section-round">Update</button>
                      <form id="delete-experience-form-<?= $jobId ?>" data-url="https://qrsume.com/assets/upload/upload_experience.php">
                        <input type="hidden" name="action" value="delete_experience">
                        <input type="hidden" name="experience_id" value="<?= htmlspecialchars($jobId) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-secondary section-round">Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (count($results['experience']) < 5): ?>
              <form id="new-experience-form" data-url="https://qrsume.com/assets/upload/upload_experience.php" class="experience-form border rounded p-3 mt-4" style="display:none;">
                <input type="hidden" name="action" value="save_experience">
                <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="new_job_name" class="form-label fw-semibold">Job Name</label>
                    <input type="text" id="new_job_name" name="job_name" class="form-control">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="new_date" class="form-label fw-semibold">Date</label>
                    <input type="text" id="new_date" name="date" class="form-control" placeholder="e.g., 2022–Present">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="new_place_of_work" class="form-label fw-semibold">Place of Work</label>
                    <input type="text" id="new_place_of_work" name="place_of_work" class="form-control">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="new_brief_description" class="form-label fw-semibold">Brief Description</label>
                    <textarea id="new_brief_description" name="brief_description" class="form-control"></textarea>
                  </div>
                </div>
                <div class="error-message" id="new-experience-error"></div>
                <div class="success-message" id="new-experience-success"></div>
                <div class="d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary w-25">Add</button>
                </div>
              </form>
              <div class="text-end mt-3">
                <button id="add-experience-btn" class="btn btn-outline-secondary btn-sm">+ Add New Work Experience</button>
              </div>
            <?php else: ?>
              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 work experience entries.</p>
            <?php endif; ?>
          </div>

          <!-- Projects -->
          <div class="form-section <?= $sectionNumber === 4 ? '' : 'd-none' ?>" id="section-4">
            <h2 class="mb-4">Projects</h2>
            <?php if (!empty($results['interests'])): ?>
              <?php foreach ($results['interests'] as $interest): ?>
                <?php $interestId = $interest['interest_id']; ?>
                <div class="border rounded mb-3">
                  <div class="d-flex justify-content-between align-items-center p-3 bg-light" data-bs-toggle="collapse" data-bs-target="#interest-collapse-<?= $interestId ?>" aria-expanded="false" role="button">
                    <h5 class="mb-0 fw-semibold">🚀 <?= htmlspecialchars($interest['interest']) ?: 'Untitled Project' ?></h5>
                    <i class="bi bi-chevron-down transition" id="chevron-<?= $interestId ?>"></i>
                  </div>
                  <div class="collapse" id="interest-collapse-<?= $interestId ?>">
                    <form id="interest-form-<?= $interestId ?>" data-url="https://qrsume.com/assets/upload/upload_interest.php" class="interest-form p-3">
                      <input type="hidden" name="action" value="save_interest">
                      <input type="hidden" name="interest_id" value="<?= htmlspecialchars($interestId) ?>">
                      <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label for="interest_<?= $interestId ?>" class="form-label fw-semibold">Project Name</label>
                          <input type="text" id="interest_<?= $interestId ?>" name="interest" class="form-control" value="<?= htmlspecialchars($interest['interest'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="description_<?= $interestId ?>" class="form-label fw-semibold">Description</label>
                          <textarea id="description_<?= $interestId ?>" name="description" class="form-control"><?= htmlspecialchars($interest['description'] ?? '') ?></textarea>
                        </div>
                      </div>
                      <div class="error-message" id="interest-error-<?= $interestId ?>"></div>
                      <div class="success-message" id="interest-success-<?= $interestId ?>"></div>
                    </form>
                    <div class="d-flex justify-content-end gap-2 p-2">
                      <button type="submit" form="interest-form-<?= $interestId ?>" class="btn btn-primary section-round">Update</button>
                      <form id="delete-interest-form-<?= $interestId ?>" data-url="https://qrsume.com/assets/upload/upload_interest.php">
                        <input type="hidden" name="action" value="delete_interest">
                        <input type="hidden" name="interest_id" value="<?= htmlspecialchars($interestId) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-secondary section-round">Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (count($results['interests']) < 5): ?>
              <form id="new-interest-form" data-url="https://qrsume.com/assets/upload/upload_interest.php" class="interest-form border rounded p-3 mt-4" style="display:none;">
                <input type="hidden" name="action" value="save_interest">
                <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="new_interest" class="form-label fw-semibold">Project Name</label>
                    <input type="text" id="new_interest" name="interest" class="form-control" placeholder="e.g., AI & Machine Learning">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="new_description" class="form-label fw-semibold">Description</label>
                    <textarea id="new_description" name="description" class="form-control"></textarea>
                  </div>
                </div>
                <div class="error-message" id="new-interest-error"></div>
                <div class="success-message" id="new-interest-success"></div>
                <div class="d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary w-25">Add</button>
                </div>
              </form>
              <div class="text-end mt-3">
                <button id="add-interest-btn" class="btn btn-outline-secondary btn-sm">+ Add New Project</button>
              </div>
            <?php else: ?>
              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 projects.</p>
            <?php endif; ?>
          </div>

          <!-- Aptitudes -->
          <div class="form-section <?= $sectionNumber === 5 ? '' : 'd-none' ?>" id="section-5">
            <h2 class="mb-4">Aptitudes</h2>
            <?php if (!empty($results['aptitudes'])): ?>
              <?php foreach ($results['aptitudes'] as $apt): ?>
                <?php $aptId = $apt['aptitude_id']; ?>
                <div class="border rounded mb-3">
                  <div class="d-flex justify-content-between align-items-center p-3 bg-light" data-bs-toggle="collapse" data-bs-target="#aptitude-collapse-<?= $aptId ?>" aria-expanded="false" role="button">
                    <h5 class="mb-0 fw-semibold">🧠 <?= htmlspecialchars($apt['aptitude']) ?: 'Untitled Aptitude' ?></h5>
                    <i class="bi bi-chevron-down transition" id="chevron-<?= $aptId ?>"></i>
                  </div>
                  <div class="collapse" id="aptitude-collapse-<?= $aptId ?>">
                    <form id="aptitude-form-<?= $aptId ?>" data-url="https://qrsume.com/assets/upload/upload_aptitude.php" class="aptitude-form p-3">
                      <input type="hidden" name="action" value="save_aptitude">
                      <input type="hidden" name="aptitude_id" value="<?= htmlspecialchars($aptId) ?>">
                      <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <div class="mb-3">
                        <label for="aptitude_<?= $aptId ?>" class="form-label fw-semibold">Aptitude</label>
                        <input type="text" id="aptitude_<?= $aptId ?>" name="aptitude" class="form-control" value="<?= htmlspecialchars($apt['aptitude'] ?? '') ?>">
                      </div>
                      <div class="error-message" id="aptitude-error-<?= $aptId ?>"></div>
                      <div class="success-message" id="aptitude-success-<?= $aptId ?>"></div>
                    </form>
                    <div class="d-flex justify-content-end gap-2 p-2">
                      <button type="submit" form="aptitude-form-<?= $aptId ?>" class="btn btn-primary section-round">Update</button>
                      <form id="delete-aptitude-form-<?= $aptId ?>" data-url="https://qrsume.com/assets/upload/upload_aptitude.php">
                        <input type="hidden" name="action" value="delete_aptitude">
                        <input type="hidden" name="aptitude_id" value="<?= htmlspecialchars($aptId) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-secondary section-round">Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (count($results['aptitudes']) < 5): ?>
              <form id="new-aptitude-form" data-url="https://qrsume.com/assets/upload/upload_aptitude.php" class="aptitude-form border rounded p-3 mt-4" style="display:none;">
                <input type="hidden" name="action" value="save_aptitude">
                <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-3">
                  <label for="new_aptitude" class="form-label fw-semibold">Aptitude</label>
                  <input type="text" id="new_aptitude" name="aptitude" class="form-control" placeholder="e.g., Communication Skills">
                </div>
                <div class="error-message" id="new-aptitude-error"></div>
                <div class="success-message" id="new-aptitude-success"></div>
                <div class="d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary w-25">Add</button>
                </div>
              </form>
              <div class="text-end mt-3">
                <button id="add-aptitude-btn" class="btn btn-outline-secondary btn-sm">+ Add New Aptitude</button>
              </div>
            <?php else: ?>
              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 aptitudes.</p>
            <?php endif; ?>
          </div>

          <!-- Languages -->
          <div class="form-section <?= $sectionNumber === 6 ? '' : 'd-none' ?>" id="section-6">
            <h2 class="mb-4">Languages</h2>
            <?php if (!empty($results['languages'])): ?>
              <?php foreach ($results['languages'] as $lang): ?>
                <?php $langId = $lang['languages_id']; ?>
                <div class="border rounded mb-3">
                  <div class="d-flex justify-content-between align-items-center p-3 bg-light" data-bs-toggle="collapse" data-bs-target="#language-collapse-<?= $langId ?>" aria-expanded="false" role="button">
                    <h5 class="mb-0 fw-semibold">🌐 <?= htmlspecialchars($lang['language']) ?: 'Untitled Language' ?></h5>
                    <i class="bi bi-chevron-down transition" id="chevron-<?= $langId ?>"></i>
                  </div>
                  <div class="collapse" id="language-collapse-<?= $langId ?>">
                    <form id="language-form-<?= $langId ?>" data-url="https://qrsume.com/assets/upload/upload_language.php" class="language-form p-3">
                      <input type="hidden" name="action" value="save_language">
                      <input type="hidden" name="languages_id" value="<?= htmlspecialchars($langId) ?>">
                      <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label for="language_<?= $langId ?>" class="form-label fw-semibold">Language</label>
                          <input type="text" id="language_<?= $langId ?>" name="language" class="form-control" value="<?= htmlspecialchars($lang['language'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                          <label for="level_<?= $langId ?>" class="form-label fw-semibold">Level</label>
                          <input type="text" id="level_<?= $langId ?>" name="level" class="form-control" value="<?= htmlspecialchars($lang['level'] ?? '') ?>">
                        </div>
                      </div>
                      <div class="error-message" id="language-error-<?= $langId ?>"></div>
                      <div class="success-message" id="language-success-<?= $langId ?>"></div>
                    </form>
                    <div class="d-flex justify-content-end gap-2 p-2">
                      <button type="submit" form="language-form-<?= $langId ?>" class="btn btn-primary section-round">Update</button>
                      <form id="delete-language-form-<?= $langId ?>" data-url="https://qrsume.com/assets/upload/upload_language.php">
                        <input type="hidden" name="action" value="delete_language">
                        <input type="hidden" name="languages_id" value="<?= htmlspecialchars($langId) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-secondary section-round">Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (count($results['languages']) < 5): ?>
              <form id="new-language-form" data-url="https://qrsume.com/assets/upload/upload_language.php" class="language-form border rounded p-3 mt-4" style="display:none;">
                <input type="hidden" name="action" value="save_language">
                <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="new_language" class="form-label fw-semibold">Language</label>
                    <input type="text" id="new_language" name="language" class="form-control" placeholder="e.g., Italian">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="new_level" class="form-label fw-semibold">Level</label>
                    <input type="text" id="new_level" name="level" class="form-control" placeholder="e.g., A1">
                  </div>
                </div>
                <div class="error-message" id="new-language-error"></div>
                <div class="success-message" id="new-language-success"></div>
                <div class="d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary w-25">Add</button>
                </div>
              </form>
              <div class="text-end mt-3">
                <button id="add-language-btn" class="btn btn-outline-secondary btn-sm">+ Add New Language</button>
              </div>
            <?php else: ?>
              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 languages.</p>
            <?php endif; ?>
          </div>

          <!-- Custom Sections -->
          <div class="form-section <?= $sectionNumber === 7 ? '' : 'd-none' ?>" id="section-7">
            <h2 class="mb-4">Custom Sections</h2>
            <?php if (!empty($results['custom_sections'])): ?>
              <?php foreach ($results['custom_sections'] as $section): ?>
                <?php $sectionId = $section['section_id']; ?>
                <div class="border rounded mb-3">
                  <div class="d-flex justify-content-between align-items-center p-3 bg-light" data-bs-toggle="collapse" data-bs-target="#custom-collapse-<?= $sectionId ?>" aria-expanded="false" role="button">
                    <h5 class="mb-0 fw-semibold">🧩 <?= htmlspecialchars($section['section_title']) ?: 'Untitled Section' ?></h5>
                    <i class="bi bi-chevron-down transition" id="chevron-<?= $sectionId ?>"></i>
                  </div>
                  <div class="collapse" id="custom-collapse-<?= $sectionId ?>">
                    <form id="custom-form-<?= $sectionId ?>" data-url="https://qrsume.com/assets/upload/upload_custom_section.php" class="custom-section-form p-3">
                      <input type="hidden" name="action" value="save_custom_section">
                      <input type="hidden" name="section_id" value="<?= htmlspecialchars($sectionId) ?>">
                      <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <div class="mb-3">
                        <label for="section_title_<?= $sectionId ?>" class="form-label fw-semibold">Section Title</label>
                        <input type="text" name="section_title" class="form-control" id="section_title_<?= $sectionId ?>" value="<?= htmlspecialchars($section['section_title']) ?>">
                      </div>
                      <div class="mb-3">
                        <label for="section_content_<?= $sectionId ?>" class="form-label fw-semibold">Section Content</label>
                        <textarea name="section_content" class="form-control" id="section_content_<?= $sectionId ?>"><?= htmlspecialchars($section['section_content']) ?></textarea>
                      </div>
                      <div class="error-message" id="custom-error-<?= $sectionId ?>"></div>
                      <div class="success-message" id="custom-success-<?= $sectionId ?>"></div>
                    </form>
                    <div class="d-flex justify-content-end gap-2 p-2">
                      <button type="submit" form="custom-form-<?= $sectionId ?>" class="btn btn-primary section-round">Update</button>
                      <form id="delete-custom-form-<?= $sectionId ?>" data-url="https://qrsume.com/assets/upload/upload_custom_section.php">
                        <input type="hidden" name="action" value="delete_custom_section">
                        <input type="hidden" name="section_id" value="<?= htmlspecialchars($sectionId) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-secondary section-round">Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <form id="new-custom-section-form" data-url="https://qrsume.com/assets/upload/upload_custom_section.php" class="border rounded p-3 mt-4">
              <input type="hidden" name="action" value="save_custom_section">
              <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <div class="mb-3">
                <label for="new_section_title" class="form-label fw-semibold">Section Title</label>
                <input type="text" name="section_title" class="form-control" id="new_section_title">
              </div>
              <div class="mb-3">
                <label for="new_section_content" class="form-label fw-semibold">Section Content</label>
                <textarea name="section_content" class="form-control" id="new_section_content"></textarea>
              </div>
              <div class="error-message" id="new-custom-section-error"></div>
              <div class="success-message" id="new-custom-section-success"></div>
              <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary w-25">Add</button>
              </div>
            </form>
          </div>
        </div>
        <!-- Navigation Buttons -->
        <div class="d-flex justify-content-between p-3 bg-white border-top" style="border-radius:0 0 12px 12px;">
  
  <!-- Back Button -->
  <a class="btn btn-secondary rounded-pill px-4 py-2 fw-semibold shadow-sm <?= $isFirstSection ? 'disabled' : '' ?>" 
    href="<?= $isFirstSection ? '#' : 'https://qrsume.com/create_resume/form_with_login.php?section=' . $prevSection ?>" 
    style="<?= $isFirstSection ? 'opacity: 0.5; pointer-events: none;' : '' ?>"
    id="prevBtn"><i class="bi bi-chevron-left me-1"></i> Back</a>

  <!-- Next/Finish Button -->
  <a class="btn btn-primary fw-semibold px-4 py-2 shadow-sm border-0" 
    style="border-radius: 999px; background-color: #2563eb;" 
    href="https://qrsume.com/create_resume/form_with_login.php?section=<?= $nextSection ?>" 
    id="nextBtn"><span id="nextBtnLabel"><?= $isLastSection ? 'Finish' : 'Next' ?></span> <i class="bi bi-chevron-right ms-1"></i></a>
</div>
      </div>
      
     <div class="col-4 d-none d-lg-block resume-container shadow" style="background: white; padding: 1.5rem; box-shadow: 0 0 8px rgba(0,0,0,0.1);">

    <!-- Profile and Contact (highlighted for sections 0 and 1) -->
    <div style="<?= in_array($sectionNumber, [0, 1]) ? $highlightStyle : '' ?>">
        <h1 class="fw-bold text-center" style="font-size: var(--font-name, 28px);">
            <?= htmlspecialchars($results['personalinfo'][0]['personal_name'] ?? '') ?>
            <?= htmlspecialchars($results['personalinfo'][0]['personal_lastname'] ?? '') ?>
        </h1>
        <div class="contact text-center" style="font-size: var(--font-base, 12px); margin-bottom: 0.8rem;">
            <?= htmlspecialchars($results['contactinfo'][0]['email'] ?? '') ?> |
            <?= htmlspecialchars($results['contactinfo'][0]['phone_number'] ?? '') ?>
        </div>
    </div>

    <!-- Education (highlighted for section 2) -->
    <?php if (!empty($results['education'])): ?>
        <div style="<?= $sectionNumber === 2 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 1.2rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
                Education
            </div>
            <?php foreach ($results['education'] as $edu): ?>
                <div class="row-group" style="margin-bottom: 0.8rem;">
                    <div class="d-flex justify-content-between align-items-center" style="font-size: var(--font-title, 14px);">
                        <div class="job-title fw-bold">
                            <?= htmlspecialchars($edu['name_of_studies'] ?? '') ?>
                        </div>
                        <div class="job-date" style="font-size: 8px;">
                            <?= htmlspecialchars($edu['date'] ?? '') ?>
                        </div>
                    </div>
                    <div style="font-size: var(--font-base, 12px);"><?= htmlspecialchars($edu['place_of_study'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Work Experience (highlighted for section 3) -->
    <?php if (!empty($results['experience'])): ?>
        <div style="<?= $sectionNumber === 3 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 1.2rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
                Work Experience
            </div>
            <?php foreach ($results['experience'] as $exp): ?>
                <div class="row-group" style="margin-bottom: 0.8rem;">
                    <div class="d-flex justify-content-between align-items-center" style="font-size: var(--font-title, 14px);">
                        <div class="job-title fw-bold">
                            <?= htmlspecialchars($exp['job_name'] ?? '') ?>, <?= htmlspecialchars($exp['place_of_work'] ?? '') ?>
                        </div>
                        <div class="job-date" style="font-size: 8px;">
                            <?= htmlspecialchars($exp['date'] ?? '') ?>
                        </div>
                    </div>
                    <div style="font-size: var(--font-base, 12px);"><?= htmlspecialchars($exp['brief_description'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Aptitudes (highlighted for section 5) -->
    <?php if (!empty($results['aptitudes'])): ?>
        <div style="<?= $sectionNumber === 5 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 1.2rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
                Aptitudes
            </div>
            <div class="row row-cols-2" style="font-size: var(--font-base, 12px);">
                <?php foreach ($results['aptitudes'] as $apt): ?>
                    <div class="col"><?= htmlspecialchars($apt['aptitude'] ?? '') ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Languages (highlighted for section 6) -->
    <?php if (!empty($results['languages'])): ?>
        <div style="<?= $sectionNumber === 6 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 1.2rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
                Languages
            </div>
            <?php foreach ($results['languages'] as $lang): ?>
                <div class="d-flex w-25" style="font-size: var(--font-base, 12px);">
                    <span class="w-50"><?= htmlspecialchars($lang['language'] ?? '') ?></span>
                    <span class="w-50"><?= htmlspecialchars($lang['level'] ?? '') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Projects (highlighted for section 4) -->
    <?php if (!empty($results['interests'])): ?>
        <div style="<?= $sectionNumber === 4 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 1.2rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
                Projects
            </div>
            <?php foreach ($results['interests'] as $int): ?>
                <div class="row-group" style="margin-bottom: 0.8rem;">
                    <div class="fw-bold" style="font-size: var(--font-title, 14px);"><?= htmlspecialchars($int['interest'] ?? '') ?></div>
                    <div style="font-size: var(--font-base, 12px);"><?= htmlspecialchars($int['description'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Custom Sections (highlighted for section 7) -->
    <?php if (!empty($results['custom_sections'])): ?>
        <div style="<?= $sectionNumber === 7 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 1.2rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
                Custom Sections
            </div>
            <?php foreach ($results['custom_sections'] as $section_item): ?>
                <div class="row-group" style="margin-bottom: 0.8rem;">
                    <div class="fw-bold" style="font-size: var(--font-title, 14px);"><?= htmlspecialchars($section_item['section_title'] ?? '') ?></div>
                    <div style="font-size: var(--font-base, 12px);"><?= htmlspecialchars($section_item['section_content'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
    </div>
  </div>

  <?php include("../assets/footer.php"); ?>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      

      // Form Toggle Module
      const formToggle = (() => {
        const toggles = [
          { buttonId: "add-experience-btn", formId: "new-experience-form" },
          { buttonId: "add-interest-btn", formId: "new-interest-form" },
          { buttonId: "add-aptitude-btn", formId: "new-aptitude-form" },
          { buttonId: "add-language-btn", formId: "new-language-form" },
        ];

        const init = () => {
          toggles.forEach(({ buttonId, formId }) => {
            const btn = document.getElementById(buttonId);
            const form = document.getElementById(formId);
            if (btn && form) {
              btn.addEventListener("click", () => {
                form.style.display = "block";
                btn.style.display = "none";
              });
            }
          });
        };

        return { init };
      })();


      // Profile Photo Preview Module
      const profilePhotoPreview = (() => {
        const photoSelect = document.getElementById("profile_photo");
        const photoImg = document.getElementById("profile-photo");

        const init = () => {
          if (photoSelect && photoImg) {
            photoSelect.addEventListener("change", () => {
              const val = photoSelect.value;
              if (val) photoImg.src = `images/${val}`;
            });
          }
        };

        return { init };
      })();

      // Initialize all modules
      formToggle.init();
      profilePhotoPreview.init();
    });
    

    // Remove overlay on load
    window.addEventListener('load', function () {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.transition = 'opacity 0.5s ease';
            overlay.style.opacity = 0;
            setTimeout(() => overlay.remove(), 500);
        }
    });
    
  </script>
</body>